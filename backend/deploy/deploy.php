#!/usr/bin/env php
<?php
/**
 * ============================================
 * SCRIPT DE DEPLOY - LINHA DE COMANDO
 * ============================================
 * 
 * Uso: php deploy.php [--dry-run] [--no-backup]
 * 
 * --dry-run: Apenas simula o deploy sem fazer upload
 * --no-backup: Não cria backup antes do deploy
 */

// Carregar configuração
$configFile = __DIR__ . '/deploy-config.php';
if (!file_exists($configFile)) {
    die("ERRO: Arquivo de configuração não encontrado: $configFile\n");
}

$config = require $configFile;
require_once __DIR__ . '/deploy-functions.php';

// Verificar argumentos
$dryRun = in_array('--dry-run', $argv);
$noBackup = in_array('--no-backup', $argv);

echo "========================================\n";
echo "  SISTEMA DE DEPLOY AUTOMÁTICO\n";
echo "========================================\n\n";

if ($dryRun) {
    echo "⚠️  MODO SIMULAÇÃO (dry-run) - Nenhum arquivo será enviado\n\n";
}

// Validar configuração
if (empty($config['ftp']['host']) || empty($config['ftp']['username'])) {
    die("ERRO: Configure o arquivo deploy-config.php com suas credenciais FTP\n");
}

$localPath = $config['paths']['local'];
$remotePath = $config['paths']['remote'];

if (!is_dir($localPath)) {
    die("ERRO: Pasta local não encontrada: $localPath\n");
}

echo "📁 Pasta local: $localPath\n";
echo "🌐 Pasta remota: $remotePath\n";
echo "🚫 Arquivos ignorados: " . count($config['exclude']) . " padrões\n\n";

// Listar arquivos
echo "📋 Listando arquivos...\n";
$files = get_files_recursive($localPath, $config['exclude'], $localPath);
$totalFiles = count($files);
$totalSize = 0;

foreach ($files as $file) {
    $totalSize += $file['size'];
}

echo "✅ Encontrados $totalFiles arquivos (" . format_bytes($totalSize) . ")\n\n";

if ($totalFiles === 0) {
    die("⚠️  Nenhum arquivo para enviar\n");
}

// Conectar ao FTP
if (!$dryRun) {
    echo "🔌 Conectando ao servidor FTP...\n";
    try {
        $conn = ftp_connect_server($config);
        ftp_login_server($conn, $config);
        echo "✅ Conectado com sucesso\n\n";
    } catch (Exception $e) {
        die("❌ ERRO: " . $e->getMessage() . "\n");
    }
    
    // Criar backup
    if (!$noBackup && $config['backup']['enabled']) {
        echo "💾 Criando backup do servidor remoto...\n";
        try {
            $backupPath = create_backup($conn, $config);
            if ($backupPath) {
                echo "✅ Backup criado: " . basename($backupPath) . "\n\n";
            }
        } catch (Exception $e) {
            echo "⚠️  Aviso: Falha ao criar backup: " . $e->getMessage() . "\n";
            echo "   Continuando com o deploy...\n\n";
        }
    }
}

// Fazer upload
echo "📤 Iniciando upload...\n\n";

$uploaded = 0;
$failed = 0;
$skipped = 0;

foreach ($files as $index => $file) {
    $relativePath = $file['relative'];
    $remoteFile = $remotePath . '/' . $relativePath;
    $progress = round((($index + 1) / $totalFiles) * 100);
    
    echo sprintf("[%3d%%] %s", $progress, $relativePath);
    
    if ($dryRun) {
        echo " [SIMULADO]\n";
        $skipped++;
        continue;
    }
    
    try {
        $mode = get_ftp_mode($file['local']);
        ftp_upload_file($conn, $file['local'], $remoteFile, $mode);
        echo " ✅\n";
        $uploaded++;
        deploy_log("Upload: $relativePath");
    } catch (Exception $e) {
        echo " ❌ ERRO: " . $e->getMessage() . "\n";
        $failed++;
        deploy_log("ERRO ao fazer upload de $relativePath: " . $e->getMessage(), 'ERROR');
    }
}

// Limpar backups antigos
if (!$dryRun && $config['backup']['enabled']) {
    echo "\n🧹 Limpando backups antigos...\n";
    cleanup_old_backups($config);
    echo "✅ Limpeza concluída\n";
}

// Fechar conexão
if (!$dryRun) {
    @ftp_close($conn);
}

// Resumo
echo "\n========================================\n";
echo "  RESUMO DO DEPLOY\n";
echo "========================================\n";
echo "Total de arquivos: $totalFiles\n";
echo "Enviados: $uploaded\n";
echo "Falhas: $failed\n";
echo "Ignorados: $skipped\n";

if ($dryRun) {
    echo "\n⚠️  Este foi um teste. Nenhum arquivo foi realmente enviado.\n";
    echo "   Execute sem --dry-run para fazer o deploy real.\n";
} else {
    if ($failed === 0) {
        echo "\n✅ Deploy concluído com sucesso!\n";
    } else {
        echo "\n⚠️  Deploy concluído com $failed erro(s).\n";
        echo "   Verifique o log para mais detalhes.\n";
    }
}

echo "\n";

/**
 * Formata bytes para formato legível
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

