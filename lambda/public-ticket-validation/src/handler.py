import json
import os
from hmac import compare_digest
from datetime import datetime, timezone

import boto3


SUPPORTED_CODES = {
    "Emitido": ("usable", "El ticket esta emitido y corresponde a la fecha de operacion actual."),
    "Validado": ("already_validated", "El ticket ya fue validado."),
    "Cancelado": ("cancelled", "El ticket esta cancelado."),
}

_cached_internal_token = None


def lambda_handler(event, context):
    headers = {str(key).lower(): value for key, value in (event.get("headers") or {}).items()}

    try:
        expected_token = get_internal_token()
    except Exception as error:
        log_event("secret_load_failed", context, error_type=type(error).__name__)
        return response(500, {"message": "No se pudo cargar la configuracion segura."})

    received_token = str(headers.get("x-internal-token", ""))

    if not expected_token or not compare_digest(received_token, expected_token):
        log_event("unauthorized", context)
        return response(401, {"message": "No autorizado."})

    try:
        payload = parse_payload(event)
        validate_payload(payload)
    except (TypeError, ValueError, json.JSONDecodeError):
        log_event("invalid_payload", context)
        return response(422, {"message": "El payload de verificacion es invalido."})

    result = verify_ticket(payload)
    log_event("verification_completed", context, result_code=result["code"])

    return response(200, result)


def get_internal_token():
    global _cached_internal_token

    if _cached_internal_token:
        return _cached_internal_token

    secret_arn = os.environ.get("INTERNAL_API_TOKEN_SECRET_ARN", "")
    if not secret_arn:
        return os.environ.get("INTERNAL_API_TOKEN", "")

    secret_key = os.environ.get("INTERNAL_API_TOKEN_SECRET_KEY", "LAMBDA_INTERNAL_TOKEN")
    secret_value = boto3.client("secretsmanager").get_secret_value(SecretId=secret_arn)["SecretString"]
    decoded = json.loads(secret_value)
    _cached_internal_token = str(decoded.get(secret_key, ""))

    return _cached_internal_token


def log_event(event_name, context, **details):
    print(json.dumps({
        "event": event_name,
        "request_id": getattr(context, "aws_request_id", None),
        **details,
    }))


def parse_payload(event):
    body = event.get("body", event)

    if isinstance(body, str):
        return json.loads(body)

    if not isinstance(body, dict):
        raise TypeError("The request body must be an object")

    return body


def validate_payload(payload):
    required = ("codigo_ticket", "estado", "fecha_operacion", "current_date")

    if not all(isinstance(payload.get(field), str) and payload[field].strip() for field in required):
        raise ValueError("Missing required fields")

    datetime.strptime(payload["fecha_operacion"], "%Y-%m-%d")
    datetime.strptime(payload["current_date"], "%Y-%m-%d")


def verify_ticket(payload):
    status = payload["estado"].strip()

    if status == "Emitido" and payload["fecha_operacion"] != payload["current_date"]:
        code = "wrong_date"
        message = "El ticket no corresponde a la fecha de operacion actual."
        usable = False
    elif status in SUPPORTED_CODES:
        code, message = SUPPORTED_CODES[status]
        usable = code == "usable"
    else:
        code = "unsupported_status"
        message = "El ticket no se encuentra en un estado utilizable."
        usable = False

    return {
        "usable": usable,
        "code": code,
        "message": message,
        "evaluated_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
    }


def response(status_code, body):
    return {
        "statusCode": status_code,
        "headers": {"Content-Type": "application/json"},
        "body": json.dumps(body),
    }
