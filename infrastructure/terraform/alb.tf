locals {
  create_certificate  = var.certificate_arn == "" && var.app_domain != "" && var.hosted_zone_id != ""
  use_https           = var.certificate_arn != "" || local.create_certificate
  active_listener_arn = local.use_https ? aws_lb_listener.https[0].arn : aws_lb_listener.http.arn
  active_certificate_arn = var.certificate_arn != "" ? var.certificate_arn : (
    local.create_certificate ? aws_acm_certificate_validation.main[0].certificate_arn : ""
  )
}

resource "aws_lb" "main" {
  name               = substr(local.name, 0, 32)
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb.id]
  subnets            = aws_subnet.public[*].id

  enable_deletion_protection = var.db_deletion_protection
  drop_invalid_header_fields = true
}

resource "aws_lb_target_group" "backend" {
  name        = substr("${local.name}-backend", 0, 32)
  port        = 8080
  protocol    = "HTTP"
  vpc_id      = aws_vpc.main.id
  target_type = "ip"

  health_check {
    enabled             = true
    path                = "/up"
    matcher             = "200"
    interval            = 30
    timeout             = 5
    healthy_threshold   = 2
    unhealthy_threshold = 3
  }
}

resource "aws_lb_target_group" "frontend" {
  name        = substr("${local.name}-frontend", 0, 32)
  port        = 8080
  protocol    = "HTTP"
  vpc_id      = aws_vpc.main.id
  target_type = "ip"

  health_check {
    path    = "/healthz"
    matcher = "200"
  }
}

resource "aws_lb_target_group" "public_frontend" {
  name        = substr("${local.name}-public", 0, 32)
  port        = 8080
  protocol    = "HTTP"
  vpc_id      = aws_vpc.main.id
  target_type = "ip"

  health_check {
    path    = "/healthz"
    matcher = "200"
  }
}

resource "aws_acm_certificate" "main" {
  count = local.create_certificate ? 1 : 0

  domain_name               = var.app_domain
  subject_alternative_names = compact([var.public_domain])
  validation_method         = "DNS"
  lifecycle { create_before_destroy = true }
}

resource "aws_route53_record" "certificate_validation" {
  for_each = local.create_certificate ? {
    for option in aws_acm_certificate.main[0].domain_validation_options : option.domain_name => {
      name   = option.resource_record_name
      record = option.resource_record_value
      type   = option.resource_record_type
    }
  } : {}

  zone_id = var.hosted_zone_id
  name    = each.value.name
  type    = each.value.type
  ttl     = 60
  records = [each.value.record]
}

resource "aws_acm_certificate_validation" "main" {
  count = local.create_certificate ? 1 : 0

  certificate_arn         = aws_acm_certificate.main[0].arn
  validation_record_fqdns = [for record in aws_route53_record.certificate_validation : record.fqdn]
}

resource "aws_lb_listener" "http" {
  load_balancer_arn = aws_lb.main.arn
  port              = 80
  protocol          = "HTTP"

  dynamic "default_action" {
    for_each = local.use_https ? [1] : []
    content {
      type = "redirect"
      redirect {
        port        = "443"
        protocol    = "HTTPS"
        status_code = "HTTP_301"
      }
    }
  }

  dynamic "default_action" {
    for_each = local.use_https ? [] : [1]
    content {
      type             = "forward"
      target_group_arn = aws_lb_target_group.frontend.arn
    }
  }
}

resource "aws_lb_listener" "https" {
  count = local.use_https ? 1 : 0

  load_balancer_arn = aws_lb.main.arn
  port              = 443
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS13-1-2-2021-06"
  certificate_arn   = local.active_certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.frontend.arn
  }
}

resource "aws_lb_listener_rule" "api" {
  listener_arn = local.active_listener_arn
  priority     = 10

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.backend.arn
  }

  condition {
    path_pattern { values = ["/api/*", "/up"] }
  }
}

resource "aws_lb_listener_rule" "public_frontend" {
  listener_arn = local.active_listener_arn
  priority     = 20

  action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.public_frontend.arn
  }

  dynamic "condition" {
    for_each = var.public_domain != "" ? [1] : []
    content {
      host_header { values = [var.public_domain] }
    }
  }

  dynamic "condition" {
    for_each = var.public_domain == "" ? [1] : []
    content {
      path_pattern { values = ["/__public/*"] }
    }
  }
}

resource "aws_route53_record" "app" {
  count = var.hosted_zone_id != "" && var.app_domain != "" ? 1 : 0

  zone_id = var.hosted_zone_id
  name    = var.app_domain
  type    = "A"
  alias {
    name                   = aws_lb.main.dns_name
    zone_id                = aws_lb.main.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "public" {
  count = var.hosted_zone_id != "" && var.public_domain != "" ? 1 : 0

  zone_id = var.hosted_zone_id
  name    = var.public_domain
  type    = "A"
  alias {
    name                   = aws_lb.main.dns_name
    zone_id                = aws_lb.main.zone_id
    evaluate_target_health = true
  }
}
