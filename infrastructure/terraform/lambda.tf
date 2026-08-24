data "archive_file" "ticket_validation" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  type        = "zip"
  source_dir  = "${path.module}/../../lambda/public-ticket-validation/src"
  output_path = "${path.module}/public-ticket-validation.zip"
  excludes    = ["__pycache__", "*.pyc"]
}

data "archive_file" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  type        = "zip"
  source_dir  = "${path.module}/../../lambda/digital-ticket-processor/src"
  output_path = "${path.module}/digital-ticket-processor.zip"
  excludes    = ["__pycache__", "*.pyc"]
}

data "aws_iam_policy_document" "lambda_assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "ticket_validation_lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  name               = "${local.name}-ticket-validation"
  assume_role_policy = data.aws_iam_policy_document.lambda_assume.json
}

data "aws_iam_policy_document" "ticket_validation_lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  statement {
    sid       = "WriteOwnLogs"
    actions   = ["logs:CreateLogStream", "logs:PutLogEvents"]
    resources = ["${aws_cloudwatch_log_group.lambda[0].arn}:*"]
  }

  statement {
    sid       = "ReadOnlyInternalToken"
    actions   = ["secretsmanager:GetSecretValue"]
    resources = [var.app_secret_arn]
  }
}

resource "aws_iam_role_policy" "ticket_validation_lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  name   = "logs-and-internal-token"
  role   = aws_iam_role.ticket_validation_lambda[0].id
  policy = data.aws_iam_policy_document.ticket_validation_lambda[0].json
}

resource "aws_cloudwatch_log_group" "lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  name              = "/aws/lambda/${local.name}-public-ticket-validation"
  retention_in_days = var.log_retention_days
}

resource "aws_lambda_function" "ticket_validation" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  function_name    = "${local.name}-public-ticket-validation"
  role             = aws_iam_role.ticket_validation_lambda[0].arn
  runtime          = "python3.13"
  handler          = "handler.lambda_handler"
  filename         = data.archive_file.ticket_validation[0].output_path
  source_code_hash = data.archive_file.ticket_validation[0].output_base64sha256
  architectures    = ["x86_64"]
  memory_size      = 128
  timeout          = 5

  environment {
    variables = {
      INTERNAL_API_TOKEN_SECRET_ARN = var.app_secret_arn
      INTERNAL_API_TOKEN_SECRET_KEY = "LAMBDA_INTERNAL_TOKEN"
    }
  }

  depends_on = [aws_cloudwatch_log_group.lambda]
}

resource "aws_apigatewayv2_api" "lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  name          = "${local.name}-ticket-validation"
  protocol_type = "HTTP"
}

resource "aws_apigatewayv2_integration" "lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  api_id                 = aws_apigatewayv2_api.lambda[0].id
  integration_type       = "AWS_PROXY"
  integration_uri        = aws_lambda_function.ticket_validation[0].invoke_arn
  payload_format_version = "2.0"
}

resource "aws_apigatewayv2_route" "lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  api_id    = aws_apigatewayv2_api.lambda[0].id
  route_key = "POST /tickets/verify"
  target    = "integrations/${aws_apigatewayv2_integration.lambda[0].id}"
}

resource "aws_apigatewayv2_stage" "lambda" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  api_id      = aws_apigatewayv2_api.lambda[0].id
  name        = "$default"
  auto_deploy = true

  default_route_settings {
    throttling_burst_limit = 50
    throttling_rate_limit  = 25
  }
}

resource "aws_lambda_permission" "api_gateway" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  statement_id  = "AllowApiGateway"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.ticket_validation[0].function_name
  principal     = "apigateway.amazonaws.com"
  source_arn    = "${aws_apigatewayv2_api.lambda[0].execution_arn}/*/*"
}

resource "aws_iam_role" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  name               = "${local.name}-digital-ticket-processor"
  assume_role_policy = data.aws_iam_policy_document.lambda_assume.json
}

data "aws_iam_policy_document" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  statement {
    sid       = "WriteOwnLogs"
    actions   = ["logs:CreateLogStream", "logs:PutLogEvents"]
    resources = ["${aws_cloudwatch_log_group.digital_ticket_processor[0].arn}:*"]
  }

  statement {
    sid       = "ReadPendingEvents"
    actions   = ["s3:GetObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.files.arn}/ticket-events/pending/*"]
  }

  statement {
    sid     = "ArchiveDeliveryEvents"
    actions = ["s3:PutObject"]
    resources = [
      "${aws_s3_bucket.files.arn}/ticket-events/completed/*",
      "${aws_s3_bucket.files.arn}/ticket-events/failed/*",
    ]
  }

  statement {
    sid       = "ReadDigitalDeliveryToken"
    actions   = ["secretsmanager:GetSecretValue"]
    resources = [var.app_secret_arn]
  }
}

resource "aws_iam_role_policy" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  name   = "delivery-events-and-internal-token"
  role   = aws_iam_role.digital_ticket_processor[0].id
  policy = data.aws_iam_policy_document.digital_ticket_processor[0].json
}

resource "aws_cloudwatch_log_group" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  name              = "/aws/lambda/${local.name}-digital-ticket-processor"
  retention_in_days = var.log_retention_days
}

resource "aws_lambda_function" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  function_name    = "${local.name}-digital-ticket-processor"
  role             = aws_iam_role.digital_ticket_processor[0].arn
  runtime          = "python3.13"
  handler          = "handler.lambda_handler"
  filename         = data.archive_file.digital_ticket_processor[0].output_path
  source_code_hash = data.archive_file.digital_ticket_processor[0].output_base64sha256
  architectures    = ["x86_64"]
  memory_size      = 128
  timeout          = 30

  environment {
    variables = {
      BACKEND_DELIVERY_URL          = "${local.app_url}/api/internal/digital-ticket-deliveries/process"
      BACKEND_MAX_ATTEMPTS          = "3"
      BACKEND_TIMEOUT_SECONDS       = "8"
      DELIVERY_EVENTS_BUCKET        = aws_s3_bucket.files.id
      INTERNAL_API_TOKEN_SECRET_ARN = var.app_secret_arn
      INTERNAL_API_TOKEN_SECRET_KEY = "DIGITAL_DELIVERY_INTERNAL_TOKEN"
    }
  }

  lifecycle {
    precondition {
      condition     = local.use_https
      error_message = "digital-ticket-processor requiere HTTPS para enviar el token interno al backend."
    }
  }

  depends_on = [aws_cloudwatch_log_group.digital_ticket_processor]
}

resource "aws_lambda_function_event_invoke_config" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  function_name                = aws_lambda_function.digital_ticket_processor[0].function_name
  maximum_event_age_in_seconds = 3600
  maximum_retry_attempts       = 2
}

resource "aws_lambda_permission" "s3_digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  statement_id   = "AllowS3DeliveryEvents"
  action         = "lambda:InvokeFunction"
  function_name  = aws_lambda_function.digital_ticket_processor[0].function_name
  principal      = "s3.amazonaws.com"
  source_arn     = aws_s3_bucket.files.arn
  source_account = data.aws_caller_identity.current.account_id
}

resource "aws_s3_bucket_notification" "digital_ticket_processor" {
  count = var.enable_digital_ticket_processor ? 1 : 0

  bucket = aws_s3_bucket.files.id

  lambda_function {
    lambda_function_arn = aws_lambda_function.digital_ticket_processor[0].arn
    events              = ["s3:ObjectCreated:*"]
    filter_prefix       = "ticket-events/pending/"
    filter_suffix       = ".json"
  }

  depends_on = [aws_lambda_permission.s3_digital_ticket_processor]
}
