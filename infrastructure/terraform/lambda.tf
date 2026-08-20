data "archive_file" "ticket_validation" {
  count = var.enable_ticket_validation_lambda ? 1 : 0

  type        = "zip"
  source_dir  = "${path.module}/../../lambda/public-ticket-validation/src"
  output_path = "${path.module}/public-ticket-validation.zip"
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

