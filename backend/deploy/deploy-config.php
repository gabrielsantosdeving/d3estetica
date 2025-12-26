<?php
/**
 * ============================================
 * CONFIGURAÇÃO DE DEPLOY
 * ============================================
 * 
 * Configure aqui as credenciais FTP/SFTP da Hostinger
 * e outras opções de deploy.
 * 
 * IMPORTANTE: Mantenha este arquivo seguro e não o compartilhe!
 */

return [
    // ============================================
    // CONFIGURAÇÕES FTP/SFTP
    // ============================================
    'ftp' => [
        'host' => 'ftp.d3estetica.com.br',        // Host FTP da Hostinger
        'port' => 21,                              // Porta FTP (21 para FTP, 22 para SFTP)
        'username' => 'u123456789',                // Seu usuário FTP
        'password' => 'SUA_SENHA_FTP_AQUI',       // Sua senha FTP
        'timeout' => 30,                           // Timeout em segundos
        'passive' => true,                         // Modo passivo (recomendado)
        'ssl' => false,                           // Usar SSL/TLS (true para FTPS)
    ],
    
    // ============================================
    // CONFIGURAÇÕES DE PASTAS
    // ============================================
    'paths' => [
        'local' => dirname(__DIR__),               // Pasta local do projeto (raiz)
        'remote' => '/public_html',                 // Pasta remota na Hostinger
        'backup' => dirname(__DIR__) . '/backups', // Pasta para backups locais
    ],
    
    // ============================================
    // ARQUIVOS E PASTAS A IGNORAR
    // ============================================
    'exclude' => [
        // Pastas
        '.git',
        '.gitignore',
        '.svn',
        'node_modules',
        'vendor',
        'backups',
        'deploy',
        '.vscode',
        '.idea',
        '__pycache__',
        '.env',
        '.env.local',
        '.env.production',
        
        // Arquivos
        '.gitignore',
        '.gitattributes',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'yarn.lock',
        'README.md',
        'README.txt',
        'CHANGELOG.md',
        'LICENSE',
        '.DS_Store',
        'Thumbs.db',
        'desktop.ini',
        
        // Logs
        '*.log',
        'logs',
        'error_log',
        
        // Arquivos de configuração local
        'deploy-config.php',
        'deploy.php',
        'deploy-functions.php',
    ],
    
    // ============================================
    // CONFIGURAÇÕES DE BACKUP
    // ============================================
    'backup' => [
        'enabled' => true,                         // Habilitar backups
        'max_versions' => 10,                     // Máximo de versões antigas mantidas
        'compress' => true,                        // Comprimir backups (zip)
    ],
    
    // ============================================
    // CONFIGURAÇÕES DE SEGURANÇA
    // ============================================
    'security' => [
        'token' => 'ALTERE_ESTE_TOKEN_PARA_ALGO_SEGURO_123456', // Token para acesso web
        'allowed_ips' => [],                       // IPs permitidos (vazio = todos)
        'require_admin' => true,                   // Exigir login admin
    ],
    
    // ============================================
    // CONFIGURAÇÕES DE LOG
    // ============================================
    'logging' => [
        'enabled' => true,
        'file' => dirname(__DIR__) . '/deploy/deploy.log',
        'max_size' => 10485760,                   // 10MB
    ],
];

