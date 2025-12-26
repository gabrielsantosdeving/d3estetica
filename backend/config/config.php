<?php
// ============================================
// ARQUIVO DE CONFIGURAÇÃO - D3 ESTÉTICA
// ============================================
// Configure aqui todas as credenciais do sistema

// ============================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// ============================================

// Host do banco de dados (geralmente 'localhost' na Hostinger)
define('DB_HOST', 'localhost');

// Nome do banco de dados
// IMPORTANTE: Use o nome completo do banco criado na Hostinger
define('DB_NAME', 'u863732122_d3esteticaa');

// Usuário do banco de dados
define('DB_USER', 'u863732122_admind3');

// Senha do banco de dados
define('DB_PASS', 'Da272204@');

// Charset (não alterar)
define('DB_CHARSET', 'utf8mb4');

// ============================================
// CONFIGURAÇÕES GERAIS
// ============================================

// URL base do site (ajuste conforme necessário)
define('BASE_URL', 'http://localhost');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// ============================================
// CONFIGURAÇÃO DE UPLOAD
// ============================================

// Pasta para uploads de arquivos
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Tamanho máximo de upload (em bytes) - 5MB
define('MAX_UPLOAD_SIZE', 5242880);

// Tipos de arquivo permitidos para currículos
define('ALLOWED_CV_TYPES', ['application/pdf']);

// Tipos de arquivo permitidos para imagens
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ============================================
// CONFIGURAÇÃO DE SEGURANÇA
// ============================================

// Tempo de expiração da sessão (em segundos) - 2 horas
define('SESSION_LIFETIME', 7200);

// Chave secreta para tokens (altere em produção)
define('SECRET_KEY', 'altere-esta-chave-em-producao-' . md5(__FILE__));

// ============================================
// CONFIGURAÇÃO MERCADO PAGO
// ============================================

// Access Token do Mercado Pago
// Obtenha em: https://www.mercadopago.com.br/developers/panel/credentials
// Use o token de TESTE para desenvolvimento
define('MERCADOPAGO_ACCESS_TOKEN', 'APP_USR-b8f5329d-eaf5-4f3c-b090-2d5664c24423');

// URLs de retorno do pagamento
define('MERCADOPAGO_SUCCESS_URL', BASE_URL . '/frontend/html/pagamento-sucesso.html');
define('MERCADOPAGO_PENDING_URL', BASE_URL . '/frontend/html/pagamento-pendente.html');
define('MERCADOPAGO_FAILURE_URL', BASE_URL . '/frontend/html/pagamento-falha.html');

// URL do webhook (para receber notificações de pagamento)
define('MERCADOPAGO_WEBHOOK_URL', BASE_URL . '/backend/api/mercadopago-webhook.php');
