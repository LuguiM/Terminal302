variable "aws_region" {
  description = "Region AWS de despliegue."
  type        = string
  default     = "us-east-1"
}

variable "project_name" {
  type    = string
  default = "terminal302"
}

variable "environment" {
  type    = string
  default = "production"
}

variable "app_domain" {
  description = "Hostname del frontend privado; vacio permite preparar sin DNS."
  type        = string
  default     = ""
}

variable "public_domain" {
  description = "Hostname del frontend publico."
  type        = string
  default     = ""
}

variable "hosted_zone_id" {
  description = "Hosted Zone de Route 53 existente; vacio omite records y validacion DNS."
  type        = string
  default     = ""
}

variable "certificate_arn" {
  description = "Certificado ACM existente. Si queda vacio, Terraform puede crearlo cuando hay dominios y hosted_zone_id."
  type        = string
  default     = ""
}

variable "enable_waf" {
  type    = bool
  default = true
}

variable "enable_nat_gateway" {
  description = "Necesario para que las tareas privadas lleguen a ECR/Secrets/Lambda/SMTP. Genera costo por hora y datos."
  type        = bool
  default     = true
}

variable "app_secret_arn" {
  description = "ARN de un secreto JSON existente con APP_KEY, LAMBDA_INTERNAL_TOKEN e INITIAL_ADMIN_PASSWORD."
  type        = string
  sensitive   = true
}

variable "db_name" {
  type    = string
  default = "terminal302"
}

variable "db_username" {
  type    = string
  default = "terminal302_admin"
}

variable "initial_admin_email" {
  description = "Email real del administrador inicial."
  type        = string
}

variable "mail_mailer" {
  type    = string
  default = "log"
}

variable "mail_host" {
  type    = string
  default = ""
}

variable "mail_port" {
  type    = number
  default = 587
}

variable "mail_scheme" {
  type    = string
  default = "tls"
}

variable "mail_from_address" {
  type    = string
  default = ""
}

variable "db_instance_class" {
  type    = string
  default = "db.t4g.micro"
}

variable "db_allocated_storage" {
  type    = number
  default = 20
}

variable "db_deletion_protection" {
  type    = bool
  default = true
}

variable "backend_image_tag" {
  type    = string
  default = "latest"
}

variable "frontend_image_tag" {
  type    = string
  default = "latest"
}

variable "public_frontend_image_tag" {
  type    = string
  default = "latest"
}

variable "backend_desired_count" {
  description = "Mantener en 0 durante el primer apply, antes de publicar imagenes y secretos."
  type        = number
  default     = 0
}

variable "frontend_desired_count" {
  type    = number
  default = 0
}

variable "public_frontend_desired_count" {
  type    = number
  default = 0
}

variable "enable_ticket_validation_lambda" {
  type    = bool
  default = true
}

variable "log_retention_days" {
  type    = number
  default = 14
}

variable "budget_alert_email" {
  description = "Email para AWS Budgets; vacio omite el presupuesto."
  type        = string
  default     = ""
}

variable "monthly_budget_usd" {
  type    = number
  default = 25
}
