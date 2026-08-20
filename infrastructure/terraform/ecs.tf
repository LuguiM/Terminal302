locals {
  listener_scheme = local.use_https ? "https" : "http"
  app_url         = var.app_domain != "" ? "${local.listener_scheme}://${var.app_domain}" : "http://${aws_lb.main.dns_name}"
  public_url      = var.public_domain != "" ? "${local.listener_scheme}://${var.public_domain}" : local.app_url
  cors_origins    = join(",", distinct([local.app_url, local.public_url]))
  lambda_url      = var.enable_ticket_validation_lambda ? aws_apigatewayv2_stage.lambda[0].invoke_url : ""

  backend_environment = [
    { name = "APP_NAME", value = "Terminal302" },
    { name = "APP_ENV", value = "production" },
    { name = "APP_DEBUG", value = "false" },
    { name = "APP_URL", value = local.app_url },
    { name = "FRONTEND_URL", value = local.app_url },
    { name = "CORS_ALLOWED_ORIGINS", value = local.cors_origins },
    { name = "APP_LOCALE", value = "es" },
    { name = "APP_FALLBACK_LOCALE", value = "es" },
    { name = "LOG_CHANNEL", value = "stderr" },
    { name = "LOG_LEVEL", value = "warning" },
    { name = "DB_CONNECTION", value = "pgsql" },
    { name = "DB_HOST", value = aws_db_instance.postgres.address },
    { name = "DB_PORT", value = tostring(aws_db_instance.postgres.port) },
    { name = "DB_DATABASE", value = var.db_name },
    { name = "DB_USERNAME", value = var.db_username },
    { name = "DB_SSLMODE", value = "require" },
    { name = "INITIAL_ADMIN_NAME", value = "Administrador Terminal302" },
    { name = "INITIAL_ADMIN_EMAIL", value = var.initial_admin_email },
    { name = "SESSION_DRIVER", value = "database" },
    { name = "CACHE_STORE", value = "database" },
    { name = "QUEUE_CONNECTION", value = "sync" },
    { name = "FILESYSTEM_DISK", value = "s3" },
    { name = "AWS_DEFAULT_REGION", value = var.aws_region },
    { name = "AWS_BUCKET", value = aws_s3_bucket.files.id },
    { name = "AWS_USE_PATH_STYLE_ENDPOINT", value = "false" },
    { name = "MAIL_MAILER", value = var.mail_mailer },
    { name = "MAIL_HOST", value = var.mail_host },
    { name = "MAIL_PORT", value = tostring(var.mail_port) },
    { name = "MAIL_SCHEME", value = var.mail_scheme },
    { name = "MAIL_FROM_ADDRESS", value = var.mail_from_address },
    { name = "MAIL_FROM_NAME", value = "Terminal302" },
    { name = "TICKET_VERIFICATION_DRIVER", value = var.enable_ticket_validation_lambda ? "http" : "local" },
    { name = "LAMBDA_BASE_URL", value = trimsuffix(local.lambda_url, "/") },
    { name = "LAMBDA_TIMEOUT_SECONDS", value = "3" },
  ]

  backend_secrets = [
    { name = "APP_KEY", valueFrom = "${var.app_secret_arn}:APP_KEY::" },
    { name = "LAMBDA_INTERNAL_TOKEN", valueFrom = "${var.app_secret_arn}:LAMBDA_INTERNAL_TOKEN::" },
    { name = "MAIL_USERNAME", valueFrom = "${var.app_secret_arn}:MAIL_USERNAME::" },
    { name = "MAIL_PASSWORD", valueFrom = "${var.app_secret_arn}:MAIL_PASSWORD::" },
    { name = "DB_PASSWORD", valueFrom = "${aws_db_instance.postgres.master_user_secret[0].secret_arn}:password::" },
  ]
}

resource "aws_ecs_task_definition" "migration" {
  family                   = "${local.name}-migration"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  task_role_arn            = aws_iam_role.backend_task.arn

  runtime_platform {
    operating_system_family = "LINUX"
    cpu_architecture        = "X86_64"
  }

  container_definitions = jsonencode([{
    name        = "migration"
    image       = "${aws_ecr_repository.app["backend"].repository_url}:${var.backend_image_tag}"
    essential   = true
    command     = ["php", "artisan", "migrate", "--force"]
    environment = local.backend_environment
    secrets = concat(local.backend_secrets, [
      { name = "INITIAL_ADMIN_PASSWORD", valueFrom = "${var.app_secret_arn}:INITIAL_ADMIN_PASSWORD::" },
    ])
    logConfiguration = {
      logDriver = "awslogs"
      options = {
        awslogs-group         = aws_cloudwatch_log_group.ecs["backend"].name
        awslogs-region        = var.aws_region
        awslogs-stream-prefix = "migration"
      }
    }
  }])
}

resource "aws_cloudwatch_log_group" "ecs" {
  for_each = toset(["backend", "frontend", "public-frontend"])

  name              = "/${var.project_name}/${var.environment}/${each.key}"
  retention_in_days = var.log_retention_days
}

resource "aws_ecs_cluster" "main" {
  name = local.name

  setting {
    name  = "containerInsights"
    value = "enabled"
  }
}

resource "aws_ecs_task_definition" "backend" {
  family                   = "${local.name}-backend"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  task_role_arn            = aws_iam_role.backend_task.arn

  runtime_platform {
    operating_system_family = "LINUX"
    cpu_architecture        = "X86_64"
  }

  container_definitions = jsonencode([{
    name         = "backend"
    image        = "${aws_ecr_repository.app["backend"].repository_url}:${var.backend_image_tag}"
    essential    = true
    environment  = local.backend_environment
    secrets      = local.backend_secrets
    portMappings = [{ containerPort = 8080, hostPort = 8080, protocol = "tcp" }]
    healthCheck = {
      command     = ["CMD-SHELL", "curl --fail --silent http://127.0.0.1:8080/up >/dev/null || exit 1"]
      interval    = 30
      timeout     = 5
      retries     = 3
      startPeriod = 30
    }
    logConfiguration = {
      logDriver = "awslogs"
      options = {
        awslogs-group         = aws_cloudwatch_log_group.ecs["backend"].name
        awslogs-region        = var.aws_region
        awslogs-stream-prefix = "ecs"
      }
    }
  }])
}

resource "aws_ecs_task_definition" "frontend" {
  for_each = {
    frontend        = var.frontend_image_tag
    public-frontend = var.public_frontend_image_tag
  }

  family                   = "${local.name}-${each.key}"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 256
  memory                   = 512
  execution_role_arn       = aws_iam_role.ecs_execution.arn
  task_role_arn            = aws_iam_role.frontend_task.arn

  runtime_platform {
    operating_system_family = "LINUX"
    cpu_architecture        = "X86_64"
  }

  container_definitions = jsonencode([{
    name         = each.key
    image        = "${aws_ecr_repository.app[each.key].repository_url}:${each.value}"
    essential    = true
    portMappings = [{ containerPort = 8080, hostPort = 8080, protocol = "tcp" }]
    healthCheck = {
      command     = ["CMD-SHELL", "wget -q -O - http://127.0.0.1:8080/healthz >/dev/null || exit 1"]
      interval    = 30
      timeout     = 5
      retries     = 3
      startPeriod = 10
    }
    logConfiguration = {
      logDriver = "awslogs"
      options = {
        awslogs-group         = aws_cloudwatch_log_group.ecs[each.key].name
        awslogs-region        = var.aws_region
        awslogs-stream-prefix = "ecs"
      }
    }
  }])
}

resource "aws_ecs_service" "backend" {
  name            = "backend"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.backend.arn
  desired_count   = var.backend_desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = aws_subnet.app[*].id
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.backend.arn
    container_name   = "backend"
    container_port   = 8080
  }

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }
  depends_on = [aws_lb_listener.http, aws_lb_listener.https, aws_lb_listener_rule.api]
}

resource "aws_ecs_service" "frontend" {
  for_each = {
    frontend = {
      desired_count = var.frontend_desired_count
      target_group  = aws_lb_target_group.frontend.arn
    }
    public-frontend = {
      desired_count = var.public_frontend_desired_count
      target_group  = aws_lb_target_group.public_frontend.arn
    }
  }

  name            = each.key
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.frontend[each.key].arn
  desired_count   = each.value.desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = aws_subnet.app[*].id
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = each.value.target_group
    container_name   = each.key
    container_port   = 8080
  }

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }
  depends_on = [aws_lb_listener.http, aws_lb_listener.https, aws_lb_listener_rule.api, aws_lb_listener_rule.public_frontend]
}
