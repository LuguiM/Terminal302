import io
import json
import os
import sys
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch


sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "src"))

import handler  # noqa: E402
from botocore.exceptions import ClientError  # noqa: E402


class HandlerTest(unittest.TestCase):
    def setUp(self):
        handler._cached_internal_token = None
        self.s3 = MagicMock()
        self.payload = {
            "schema_version": 1,
            "event_id": "123e4567-e89b-42d3-a456-426614174000",
            "ticket_id": 42,
            "created_at": "2026-08-20T12:00:00Z",
        }
        self.s3.get_object.side_effect = lambda **_kwargs: {
            "Body": io.BytesIO(json.dumps(self.payload).encode())
        }
        self.environment = {
            "DELIVERY_EVENTS_BUCKET": "terminal302-files",
            "BACKEND_DELIVERY_URL": "https://example.test/api/internal/digital-ticket-deliveries/process",
            "BACKEND_MAX_ATTEMPTS": "3",
        }

    def record(self, key=None):
        return {
            "s3": {
                "bucket": {"name": "terminal302-files"},
                "object": {
                    "key": key
                    or "ticket-events%2Fpending%2F123e4567-e89b-42d3-a456-426614174000.json"
                },
            }
        }

    def invoke_record(self, key=None, backend_status="completed"):
        with patch.dict(os.environ, self.environment, clear=True), patch.object(
            handler.boto3, "client", return_value=self.s3
        ), patch.object(
            handler, "call_backend_with_retry", return_value={"status": backend_status}
        ):
            return handler.lambda_handler({"Records": [self.record(key)]}, None)

    def test_valid_s3_event_is_processed_and_moved_to_completed(self):
        result = self.invoke_record()

        self.assertEqual(1, result["processed_records"])
        self.s3.put_object.assert_called_once()
        self.assertEqual(
            "ticket-events/completed/123e4567-e89b-42d3-a456-426614174000.json",
            self.s3.put_object.call_args.kwargs["Key"],
        )
        self.s3.delete_object.assert_called_once()

    def test_key_outside_pending_is_ignored(self):
        self.invoke_record("tickets%2Ffinal%2Fticket.png")

        self.s3.get_object.assert_not_called()
        self.s3.put_object.assert_not_called()

    def test_invalid_json_is_moved_to_failed_without_copying_raw_content(self):
        self.s3.get_object.side_effect = lambda **_kwargs: {
            "Body": io.BytesIO(b"not-json")
        }
        self.invoke_record()

        self.assertEqual(
            "ticket-events/failed/123e4567-e89b-42d3-a456-426614174000.json",
            self.s3.put_object.call_args.kwargs["Key"],
        )
        archived = json.loads(self.s3.put_object.call_args.kwargs["Body"])
        self.assertNotIn("not-json", json.dumps(archived))

    def test_duplicate_completed_event_is_archived_without_special_case(self):
        self.invoke_record(backend_status="completed")
        self.assertIn("completed", self.s3.put_object.call_args.kwargs["Key"])

    def test_duplicate_notification_after_pending_was_removed_is_ignored(self):
        self.s3.get_object.side_effect = ClientError(
            {"Error": {"Code": "NoSuchKey", "Message": "missing"}}, "GetObject"
        )

        result = self.invoke_record()

        self.assertEqual(1, result["processed_records"])
        self.s3.put_object.assert_not_called()

    def test_already_failed_delivery_moves_to_failed(self):
        self.invoke_record(backend_status="failed")
        self.assertIn("failed", self.s3.put_object.call_args.kwargs["Key"])

    def test_backend_failure_is_moved_to_failed_after_bounded_retry(self):
        with patch.dict(os.environ, self.environment, clear=True), patch.object(
            handler.boto3, "client", return_value=self.s3
        ), patch.object(
            handler,
            "call_backend_with_retry",
            side_effect=handler.RetryableDeliveryError("unavailable"),
        ):
            result = handler.lambda_handler({"Records": [self.record()]}, None)

        self.assertEqual(1, result["processed_records"])
        self.assertEqual(
            "ticket-events/failed/123e4567-e89b-42d3-a456-426614174000.json",
            self.s3.put_object.call_args.kwargs["Key"],
        )
        self.s3.delete_object.assert_called_once()

    def test_http_retry_stops_after_success(self):
        with patch.dict(os.environ, self.environment, clear=True), patch.object(
            handler, "call_backend", side_effect=[handler.RetryableDeliveryError(), {"status": "completed"}]
        ) as backend, patch.object(handler.time, "sleep"):
            result = handler.call_backend_with_retry(self.payload["event_id"])

        self.assertEqual("completed", result["status"])
        self.assertEqual(2, backend.call_count)

    def test_multiple_records_are_processed_individually(self):
        with patch.dict(os.environ, self.environment, clear=True), patch.object(
            handler.boto3, "client", return_value=self.s3
        ), patch.object(
            handler, "call_backend_with_retry", return_value={"status": "completed"}
        ):
            result = handler.lambda_handler(
                {"Records": [self.record(), self.record()]}, None
            )

        self.assertEqual(2, result["processed_records"])
        self.assertEqual(2, self.s3.put_object.call_count)


if __name__ == "__main__":
    unittest.main()
