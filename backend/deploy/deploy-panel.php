<?php
/**
 * ============================================
 * PAINEL WEB DE DEPLOY
 * ============================================
 * 
 * Interface web para executar deploy via navegador
 * Integrado ao painel administrativo
 */

session_start();

// Verificar autenticação admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../admin/login.php');
    exit();
}

// Carregar configuração
$configFile = __DIR__ . '/deploy-config.php';
if (!file_exists($configFile)) {
    die("ERRO: Arquivo de configuração não encontrado");
}

$config = require $configFile;
require_once __DIR__ . '/deploy-functions.php';

$error = '';
$success = '';
$deployResult = null;
$dryRun = false;

// Processar ação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['token'] ?? '';
    
    // Verificar token de segurança
    if ($token !== $config['security']['token']) {
        $error = 'Token de segurança inválido';
    } else {
        // Verificar IP se configurado
        if (!empty($config['security']['allowed_ips'])) {
            $userIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!in_array($userIp, $config['security']['allowed_ips'])) {
                $error = 'Seu IP não está autorizado';
            }
        }
        
        if (!$error) {
            if ($action === 'simulate') {
                $dryRun = true;
                $deployResult = execute_deploy($config, true);
            } elseif ($action === 'deploy') {
                $deployResult = execute_deploy($config, false);
                if ($deployResult['success']) {
                    $success = 'Deploy executado com sucesso!';
                } else {
                    $error = 'Erro ao executar deploy: ' . $deployResult['error'];
                }
            }
        }
    }
}

// Função para executar deploy
function execute_deploy($config, $dryRun = false) {
    $result = [
        'success' => false,
        'error' => '',
        'files' => [],
        'uploaded' => 0,
        'failed' => 0,
        'total' => 0,
        'totalSize' => 0,
    ];
    
    try {
        $localPath = $config['paths']['local'];
        $remotePath = $config['paths']['remote'];
        
        if (!is_dir($localPath)) {
            throw new Exception("Pasta local não encontrada: $localPath");
        }
        
        // Listar arquivos
        $files = get_files_recursive($localPath, $config['exclude'], $localPath);
        $result['total'] = count($files);
        
        foreach ($files as $file) {
            $result['totalSize'] += $file['size'];
            $result['files'][] = [
                'path' => $file['relative'],
                'size' => $file['size'],
            ];
        }
        
        if ($dryRun) {
            $result['success'] = true;
            return $result;
        }
        
        // Conectar ao FTP
        $conn = ftp_connect_server($config);
        ftp_login_server($conn, $config);
        
        // Criar backup
        if ($config['backup']['enabled']) {
            try {
                $backupPath = create_backup($conn, $config);
                $result['backup'] = $backupPath ? basename($backupPath) : null;
            } catch (Exception $e) {
                deploy_log("Aviso: Falha ao criar backup: " . $e->getMessage(), 'WARNING');
            }
        }
        
        // Fazer upload
        $uploaded = 0;
        $failed = 0;
        
        foreach ($files as $file) {
            $remoteFile = $remotePath . '/' . $file['relative'];
            
            try {
                $mode = get_ftp_mode($file['local']);
                ftp_upload_file($conn, $file['local'], $remoteFile, $mode);
                $uploaded++;
            } catch (Exception $e) {
                $failed++;
                deploy_log("ERRO ao fazer upload de {$file['relative']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        // Limpar backups antigos
        if ($config['backup']['enabled']) {
            cleanup_old_backups($config);
        }
        
        @ftp_close($conn);
        
        $result['success'] = true;
        $result['uploaded'] = $uploaded;
        $result['failed'] = $failed;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        deploy_log("ERRO no deploy: " . $e->getMessage(), 'ERROR');
    }
    
    return $result;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy Automático - Painel Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .result-box {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .file-list {
            max-height: 400px;
            overflow-y: auto;
            background: white;
            border-radius: 6px;
            padding: 15px;
        }
        
        .file-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-size {
            color: #666;
            font-size: 12px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../admin/index.html" class="back-link">
            <i class="bi bi-arrow-left"></i> Voltar ao Painel
        </a>
        
        <h1>
            <i class="bi bi-cloud-upload"></i>
            Sistema de Deploy Automático
        </h1>
        <p class="subtitle">Envie atualizações do site para a Hostinger via FTP</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($dryRun && $deployResult): ?>
            <div class="alert alert-warning">
                <i class="bi bi-info-circle"></i>
                <strong>Modo Simulação:</strong> Nenhum arquivo foi realmente enviado. Esta é apenas uma prévia.
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="token">
                    <i class="bi bi-shield-lock"></i> Token de Segurança
                </label>
                <input type="password" id="token" name="token" required 
                       placeholder="Digite o token de segurança configurado">
                <small style="color: #666; display: block; margin-top: 5px;">
                    Token configurado em: deploy-config.php
                </small>
            </div>
            
            <div class="btn-group">
                <button type="submit" name="action" value="simulate" class="btn btn-secondary">
                    <i class="bi bi-eye"></i> Simular Deploy (Dry-Run)
                </button>
                <button type="submit" name="action" value="deploy" class="btn btn-danger" 
                        onclick="return confirm('Tem certeza que deseja executar o deploy? Isso irá atualizar todos os arquivos no servidor.');">
                    <i class="bi bi-cloud-upload"></i> Executar Deploy Agora
                </button>
            </div>
        </form>
        
        <?php if ($deployResult): ?>
            <div class="result-box">
                <h2 style="margin-bottom: 20px;">
                    <i class="bi bi-list-check"></i> Resultado do Deploy
                </h2>
                
                <div class="result-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $deployResult['total']; ?></div>
                        <div class="stat-label">Total de Arquivos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo format_bytes($deployResult['totalSize']); ?></div>
                        <div class="stat-label">Tamanho Total</div>
                    </div>
                    <?php if (!$dryRun): ?>
                        <div class="stat-item">
                            <div class="stat-value" style="color: #28a745;"><?php echo $deployResult['uploaded']; ?></div>
                            <div class="stat-label">Enviados</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: #dc3545;"><?php echo $deployResult['failed']; ?></div>
                            <div class="stat-label">Falhas</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($deployResult['backup']) && $deployResult['backup']): ?>
                    <div class="alert alert-success" style="margin-top: 15px;">
                        <i class="bi bi-archive"></i>
                        <strong>Backup criado:</strong> <?php echo htmlspecialchars($deployResult['backup']); ?>
                    </div>
                <?php endif; ?>
                
                <h3 style="margin-top: 20px; margin-bottom: 10px;">Arquivos:</h3>
                <div class="file-list">
                    <?php foreach ($deployResult['files'] as $file): ?>
                        <div class="file-item">
                            <span><i class="bi bi-file"></i> <?php echo htmlspecialchars($file['path']); ?></span>
                            <span class="file-size"><?php echo format_bytes($file['size']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    ?>
</body>
</html>

