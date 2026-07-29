import json
import os
import sys
import unittest
from pathlib import Path
from unittest.mock import patch


sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "src"))

from handler import lambda_handler  # noqa: E402


class HandlerTest(unittest.TestCase):
    def invoke(self, payload, token="test-token"):
        event = {
            "headers": {"X-Internal-Token": token},
            "body": json.dumps(payload),
        }

        with patch.dict(os.environ, {"INTERNAL_API_TOKEN": "test-token"}):
            result = lambda_handler(event, None)

        return result["statusCode"], json.loads(result["body"])

    def payload(self, **overrides):
        data = {
            "codigo_ticket": "TKT-001",
            "estado": "Emitido",
            "fecha_operacion": "2026-07-16",
            "current_date": "2026-07-16",
        }
        data.update(overrides)
        return data

    def test_issued_ticket_for_current_date_is_usable(self):
        status, body = self.invoke(self.payload())

        self.assertEqual(200, status)
        self.assertTrue(body["usable"])
        self.assertEqual("usable", body["code"])

    def test_issued_ticket_for_another_date_is_not_usable(self):
        status, body = self.invoke(self.payload(fecha_operacion="2026-07-15"))

        self.assertEqual(200, status)
        self.assertFalse(body["usable"])
        self.assertEqual("wrong_date", body["code"])

    def test_known_and_unknown_statuses_return_expected_codes(self):
        expectations = {
            "Validado": "already_validated",
            "Cancelado": "cancelled",
            "Procesando": "unsupported_status",
        }

        for ticket_status, expected_code in expectations.items():
            with self.subTest(ticket_status=ticket_status):
                status, body = self.invoke(self.payload(estado=ticket_status))
                self.assertEqual(200, status)
                self.assertFalse(body["usable"])
                self.assertEqual(expected_code, body["code"])

    def test_invalid_payload_is_rejected(self):
        status, body = self.invoke({"codigo_ticket": "TKT-001"})

        self.assertEqual(422, status)
        self.assertEqual("El payload de verificacion es invalido.", body["message"])

    def test_invalid_token_is_rejected(self):
        status, body = self.invoke(self.payload(), token="wrong-token")

        self.assertEqual(401, status)
        self.assertEqual("No autorizado.", body["message"])


if __name__ == "__main__":
    unittest.main()
