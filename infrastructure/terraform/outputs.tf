output "alb_dns_name" {
  value = aws_lb.main.dns_name
}

output "application_urls" {
  value = {
    private = local.app_url
    public  = local.public_url
  }
}

output "ecr_repository_urls" {
  value = { for name, repository in aws_ecr_repository.app : name => repository.repository_url }
}

output "ecs_cluster_name" {
  value = aws_ecs_cluster.main.name
}

output "rds_endpoint" {
  value     = aws_db_instance.postgres.address
  sensitive = true
}

output "rds_master_secret_arn" {
  value     = aws_db_instance.postgres.master_user_secret[0].secret_arn
  sensitive = true
}

output "s3_bucket" {
  value = aws_s3_bucket.files.id
}

output "ticket_validation_url" {
  value = var.enable_ticket_validation_lambda ? "${trimsuffix(aws_apigatewayv2_stage.lambda[0].invoke_url, "/")}/tickets/verify" : null
}

output "migration_task_definition" {
  value = aws_ecs_task_definition.migration.family
}

output "app_subnet_ids" {
  value = aws_subnet.app[*].id
}

output "ecs_security_group_id" {
  value = aws_security_group.ecs.id
}
