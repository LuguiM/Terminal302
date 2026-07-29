import json
import os
from hmac import compare_digest
from datetime import datetime, timezone


SUPPORTED_CODES = {
    "Emitido": ("usable", "El ticket esta emitido y corresponde a la fecha de operacion actual."),
    "Validado": ("already_validated", "El ticket ya fue validado."),
    "Cancelado": ("cancelled", "El ticket esta cancelado."),
}


def lambda_handler(event, context):
    headers = {str(key).lower(): value for key, value in (event.get("headers") or {}).items()}
    expected_token = os.environ.get("INTERNAL_API_TOKEN", "")

    received_token = str(headers.get("x-internal-token", ""))

    if not expected_token or not compare_digest(received_token, expected_token):
        return response(401, {"message": "No autorizado."})

    try:
        payload = parse_payload(event)
        validate_payload(payload)
    except (TypeError, ValueError, json.JSONDecodeError):
        return response(422, {"message": "El payload de verificacion es invalido."})

    result = verify_ticket(payload)

    return response(200, result)


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
