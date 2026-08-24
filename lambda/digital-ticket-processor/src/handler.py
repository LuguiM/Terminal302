import json
import os
import time
from datetime import datetime, timezone
from urllib.error import HTTPError, URLError
from urllib.parse import unquote_plus
from urllib.request import Request, urlopen

import boto3
from botocore.exceptions import ClientError


PENDING_PREFIX = "ticket-events/pending/"
COMPLETED_PREFIX = "ticket-events/completed/"
FAILED_PREFIX = "ticket-events/failed/"
_cached_internal_token = None


class RetryableDeliveryError(RuntimeError):
    pass


def lambda_handler(event, context):
    failures = []

    for record in event.get("Records", []):
        try:
            process_record(record, context)
        except Exception as error:
            failures.append(type(error).__name__)
            log_event(
                "record_failed",
                context,
                status="failed",
                error_type=type(error).__name__,
            )

    if failures:
        raise RetryableDeliveryError(
            f"{len(failures)} S3 record(s) could not be processed: {','.join(failures)}"
        )

    return {"processed_records": len(event.get("Records", []))}


def process_record(record, context):
    started_at = time.monotonic()
    bucket = str(record.get("s3", {}).get("bucket", {}).get("name", ""))
    key = unquote_plus(str(record.get("s3", {}).get("object", {}).get("key", "")))
    expected_bucket = os.environ.get("DELIVERY_EVENTS_BUCKET", "")

    if not bucket or bucket != expected_bucket:
        log_event("record_ignored", context, status="ignored", error_type="UnexpectedBucket")
        return

    if not key.startswith(PENDING_PREFIX) or not key.endswith(".json"):
        log_event("record_ignored", context, status="ignored", error_type="UnexpectedKey")
        return

    event_id = key[len(PENDING_PREFIX) : -len(".json")]
    payload = None

    try:
        try:
            payload = read_payload(bucket, key)
        except ClientError as error:
            if error.response.get("Error", {}).get("Code") in ("NoSuchKey", "404"):
                log_event(
                    "duplicate_event_already_archived",
                    context,
                    event_id=event_id,
                    status="skipped",
                    duration_ms=elapsed_ms(started_at),
                )
                return
            raise
        validate_payload(payload, event_id)
        backend_result = call_backend_with_retry(event_id)
        status = backend_result.get("status")

        if status == "completed":
            archive_event(bucket, key, COMPLETED_PREFIX, payload, status)
        elif status in ("failed", "skipped"):
            archive_event(bucket, key, FAILED_PREFIX, payload, status)
        else:
            raise RetryableDeliveryError(f"Unexpected backend status: {status}")

        log_event(
            "delivery_processed",
            context,
            event_id=event_id,
            ticket_id=payload.get("ticket_id"),
            delivery_id=payload.get("ticket_id"),
            status=status,
            duration_ms=elapsed_ms(started_at),
        )
    except (ValueError, json.JSONDecodeError) as error:
        archive_invalid_event(bucket, key, event_id, error)
        log_event(
            "delivery_rejected",
            context,
            event_id=event_id,
            status="failed",
            duration_ms=elapsed_ms(started_at),
            error_type=type(error).__name__,
        )
    except RetryableDeliveryError as error:
        archive_backend_failure(bucket, key, event_id, payload, error)
        log_event(
            "delivery_retry_exhausted",
            context,
            event_id=event_id,
            ticket_id=payload.get("ticket_id") if isinstance(payload, dict) else None,
            status="failed",
            duration_ms=elapsed_ms(started_at),
            error_type="BackendUnavailable",
        )


def read_payload(bucket, key):
    body = boto3.client("s3").get_object(Bucket=bucket, Key=key)["Body"].read()
    return json.loads(body.decode("utf-8"))


def validate_payload(payload, event_id):
    if not isinstance(payload, dict):
        raise ValueError("Payload must be an object")
    if payload.get("schema_version") != 1:
        raise ValueError("Unsupported schema version")
    if payload.get("event_id") != event_id:
        raise ValueError("Event id does not match object key")
    if not isinstance(payload.get("ticket_id"), int) or payload["ticket_id"] < 1:
        raise ValueError("ticket_id must be a positive integer")
    if not isinstance(payload.get("created_at"), str) or not payload["created_at"]:
        raise ValueError("created_at is required")


def call_backend_with_retry(event_id):
    attempts = max(int(os.environ.get("BACKEND_MAX_ATTEMPTS", "3")), 1)
    delay = 0.25
    last_error = None

    for attempt in range(1, attempts + 1):
        try:
            return call_backend(event_id)
        except RetryableDeliveryError as error:
            last_error = error
            if attempt < attempts:
                time.sleep(delay)
                delay *= 2

    raise last_error or RetryableDeliveryError("Backend retry exhausted")


def call_backend(event_id):
    endpoint = os.environ["BACKEND_DELIVERY_URL"]
    body = json.dumps({"event_id": event_id}).encode("utf-8")
    request = Request(
        endpoint,
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Internal-Token": get_internal_token(),
        },
    )

    try:
        with urlopen(request, timeout=float(os.environ.get("BACKEND_TIMEOUT_SECONDS", "10"))) as response:
            return json.loads(response.read().decode("utf-8"))
    except HTTPError as error:
        if error.code == 409 or error.code >= 500:
            raise RetryableDeliveryError(f"Backend returned HTTP {error.code}") from error
        raise RuntimeError(f"Backend rejected the event with HTTP {error.code}") from error
    except (URLError, TimeoutError) as error:
        raise RetryableDeliveryError("Backend request failed") from error


def get_internal_token():
    global _cached_internal_token

    if _cached_internal_token:
        return _cached_internal_token

    secret_arn = os.environ["INTERNAL_API_TOKEN_SECRET_ARN"]
    secret_key = os.environ.get(
        "INTERNAL_API_TOKEN_SECRET_KEY", "DIGITAL_DELIVERY_INTERNAL_TOKEN"
    )
    secret_value = boto3.client("secretsmanager").get_secret_value(SecretId=secret_arn)[
        "SecretString"
    ]
    decoded = json.loads(secret_value)
    token = str(decoded.get(secret_key, ""))

    if not token:
        raise RuntimeError("Digital delivery internal token is missing")

    _cached_internal_token = token
    return token


def archive_event(bucket, source_key, target_prefix, payload, status):
    target_key = target_prefix + source_key.rsplit("/", 1)[-1]
    archived = {
        **payload,
        "result": {
            "status": status,
            "archived_at": utc_now(),
        },
    }
    s3 = boto3.client("s3")
    s3.put_object(
        Bucket=bucket,
        Key=target_key,
        Body=json.dumps(archived, separators=(",", ":")).encode("utf-8"),
        ContentType="application/json",
    )
    s3.delete_object(Bucket=bucket, Key=source_key)


def archive_invalid_event(bucket, source_key, event_id, error):
    target_key = FAILED_PREFIX + source_key.rsplit("/", 1)[-1]
    diagnostic = {
        "schema_version": 1,
        "event_id": event_id,
        "result": {
            "status": "failed",
            "error_type": type(error).__name__,
            "message": "El evento no cumple el esquema esperado.",
            "archived_at": utc_now(),
        },
    }
    s3 = boto3.client("s3")
    s3.put_object(
        Bucket=bucket,
        Key=target_key,
        Body=json.dumps(diagnostic, separators=(",", ":")).encode("utf-8"),
        ContentType="application/json",
    )
    s3.delete_object(Bucket=bucket, Key=source_key)


def archive_backend_failure(bucket, source_key, event_id, payload, _error):
    target_key = FAILED_PREFIX + source_key.rsplit("/", 1)[-1]
    diagnostic = {
        "schema_version": 1,
        "event_id": event_id,
        "ticket_id": payload.get("ticket_id") if isinstance(payload, dict) else None,
        "result": {
            "status": "failed",
            "error_type": "BackendUnavailable",
            "message": "El backend no estuvo disponible despues de los reintentos.",
            "archived_at": utc_now(),
        },
    }
    s3 = boto3.client("s3")
    s3.put_object(
        Bucket=bucket,
        Key=target_key,
        Body=json.dumps(diagnostic, separators=(",", ":")).encode("utf-8"),
        ContentType="application/json",
    )
    s3.delete_object(Bucket=bucket, Key=source_key)


def log_event(event_name, context, **details):
    print(
        json.dumps(
            {
                "event": event_name,
                "request_id": getattr(context, "aws_request_id", None),
                **{key: value for key, value in details.items() if value is not None},
            },
            separators=(",", ":"),
        )
    )


def elapsed_ms(started_at):
    return round((time.monotonic() - started_at) * 1000)


def utc_now():
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")
