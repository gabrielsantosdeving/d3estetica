<?php
/**
 * ============================================
 * FUNÇÕES AUXILIARES DE DEPLOY
 * ============================================
 * 
 * Funções utilitárias para o sistema de deploy
 */

/**
 * Log de mensagens
 */
function deploy_log($message, $type = 'INFO') {
    global $config;
    
    if (!$config['logging']['enabled']) {
        return;
    }
    
    $logFile = $config['logging']['file'];
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Criar diretório se não existir
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    // Verificar tamanho do log e rotacionar se necessário
    if (file_exists($logFile) && filesize($logFile) > $config['logging']['max_size']) {
        $backupLog = $logFile . '.' . date('Y-m-d_His');
        @rename($logFile, $backupLog);
    }
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Verifica se um arquivo/pasta deve ser ignorado
 */
function should_exclude($path, $excludeList) {
    $relativePath = str_replace('\\', '/', $path);
    $basename = basename($relativePath);
    
    foreach ($excludeList as $pattern) {
        // Verificar nome exato
        if ($basename === $pattern) {
            return true;
        }
        
        // Verificar padrão de extensão (*.log)
        if (strpos($pattern, '*') === 0) {
            $ext = substr($pattern, 1);
            if (substr($basename, -strlen($ext)) === $ext) {
                return true;
            }
        }
        
        // Verificar se está no caminho
        if (strpos($relativePath, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Lista recursiva de arquivos
 */
function get_files_recursive($dir, $excludeList, $baseDir = null) {
    if ($baseDir === null) {
        $baseDir = $dir;
    }
    
    $files = [];
    
    if (!is_dir($dir)) {
        return $files;
    }
    
    $items = @scandir($dir);
    if ($items === false) {
        return $files;
    }
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $fullPath);
        
        // Verificar se deve ser ignorado
        if (should_exclude($fullPath, $excludeList)) {
            continue;
        }
        
        if (is_dir($fullPath)) {
            // Recursão para subpastas
            $subFiles = get_files_recursive($fullPath, $excludeList, $baseDir);
            $files = array_merge($files, $subFiles);
        } else {
            $files[] = [
                'local' => $fullPath,
                'relative' => str_replace('\\', '/', $relativePath),
                'size' => filesize($fullPath),
                'modified' => filemtime($fullPath),
            ];
        }
    }
    
    return $files;
}

/**
 * Conecta ao servidor FTP
 */
function ftp_connect_server($config) {
    $host = $config['ftp']['host'];
    $port = $config['ftp']['port'];
    $timeout = $config['ftp']['timeout'];
    $ssl = $config['ftp']['ssl'];
    
    deploy_log("Conectando ao servidor FTP: $host:$port");
    
    if ($ssl) {
        $conn = @ftp_ssl_connect($host, $port, $timeout);
    } else {
        $conn = @ftp_connect($host, $port, $timeout);
    }
    
    if (!$conn) {
        throw new Exception("Falha ao conectar ao servidor FTP: $host:$port");
    }
    
    return $conn;
}

/**
 * Faz login no FTP
 */
function ftp_login_server($conn, $config) {
    $username = $config['ftp']['username'];
    $password = $config['ftp']['password'];
    $passive = $config['ftp']['passive'];
    
    deploy_log("Fazendo login como: $username");
    
    if (!@ftp_login($conn, $username, $password)) {
        @ftp_close($conn);
        throw new Exception("Falha ao fazer login no FTP");
    }
    
    // Configurar modo passivo
    if ($passive) {
        @ftp_pasv($conn, true);
    }
    
    deploy_log("Login realizado com sucesso");
    
    return true;
}

/**
 * Cria diretório remoto recursivamente
 */
function ftp_mkdir_recursive($conn, $remoteDir) {
    $parts = explode('/', trim($remoteDir, '/'));
    $currentDir = '';
    
    foreach ($parts as $part) {
        if (empty($part)) continue;
        
        $currentDir .= '/' . $part;
        
        // Verificar se o diretório existe
        $contents = @ftp_nlist($conn, $currentDir);
        if ($contents === false) {
            // Criar diretório
            if (!@ftp_mkdir($conn, $currentDir)) {
                // Pode já existir, continuar
            }
        }
    }
    
    return true;
}

/**
 * Faz upload de arquivo
 */
function ftp_upload_file($conn, $localFile, $remoteFile, $mode = FTP_BINARY) {
    $remoteDir = dirname($remoteFile);
    
    // Criar diretório remoto se necessário
    if ($remoteDir !== '.' && $remoteDir !== '/') {
        ftp_mkdir_recursive($conn, $remoteDir);
    }
    
    // Fazer upload
    $result = @ftp_put($conn, $remoteFile, $localFile, $mode);
    
    if (!$result) {
        throw new Exception("Falha ao fazer upload de: $localFile");
    }
    
    return true;
}

/**
 * Determina modo FTP baseado na extensão
 */
function get_ftp_mode($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $textModes = ['txt', 'html', 'htm', 'css', 'js', 'php', 'xml', 'json', 'csv', 'md', 'yml', 'yaml'];
    
    if (in_array($ext, $textModes)) {
        return FTP_ASCII;
    }
    
    return FTP_BINARY;
}

/**
 * Cria backup do servidor remoto
 */
function create_backup($conn, $config, $version = null) {
    if (!$config['backup']['enabled']) {
        return null;
    }
    
    if ($version === null) {
        $version = date('Y-m-d_His');
    }
    
    $backupDir = $config['paths']['backup'];
    $remoteDir = $config['paths']['remote'];
    
    // Criar diretório de backup
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }
    
    $backupPath = $backupDir . DIRECTORY_SEPARATOR . 'backup_' . $version;
    
    deploy_log("Criando backup: $backupPath");
    
    // Listar arquivos remotos
    $files = ftp_list_files_recursive($conn, $remoteDir);
    
    // Criar estrutura de backup
    @mkdir($backupPath, 0755, true);
    
    $backedUp = 0;
    foreach ($files as $file) {
        $localBackupFile = $backupPath . DIRECTORY_SEPARATOR . $file;
        $localBackupDir = dirname($localBackupFile);
        
        if (!is_dir($localBackupDir)) {
            @mkdir($localBackupDir, 0755, true);
        }
        
        $remoteFile = $remoteDir . '/' . $file;
        if (@ftp_get($conn, $localBackupFile, $remoteFile, FTP_BINARY)) {
            $backedUp++;
        }
    }
    
    deploy_log("Backup criado: $backedUp arquivos");
    
    // Comprimir se habilitado
    if ($config['backup']['compress'] && class_exists('ZipArchive')) {
        $zipFile = $backupPath . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            add_directory_to_zip($backupPath, $zip, $backupPath);
            $zip->close();
            
            // Remover pasta não comprimida
            delete_directory($backupPath);
            
            deploy_log("Backup comprimido: $zipFile");
            return $zipFile;
        }
    }
    
    return $backupPath;
}

/**
 * Lista arquivos recursivamente do FTP
 */
function ftp_list_files_recursive($conn, $remoteDir) {
    $files = [];
    $items = @ftp_nlist($conn, $remoteDir);
    
    if ($items === false) {
        return $files;
    }
    
    foreach ($items as $item) {
        $basename = basename($item);
        if ($basename === '.' || $basename === '..') {
            continue;
        }
        
        $fullPath = $remoteDir . '/' . $basename;
        
        // Verificar se é diretório
        $currentDir = @ftp_pwd($conn);
        if (@ftp_chdir($conn, $fullPath)) {
            @ftp_chdir($conn, $currentDir);
            // É um diretório
            $subFiles = ftp_list_files_recursive($conn, $fullPath);
            foreach ($subFiles as $subFile) {
                $files[] = $basename . '/' . $subFile;
            }
        } else {
            // É um arquivo
            $files[] = $basename;
        }
    }
    
    return $files;
}

/**
 * Adiciona diretório ao ZIP
 */
function add_directory_to_zip($dir, $zip, $baseDir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
        
        if (is_dir($filePath)) {
            $zip->addEmptyDir($relativePath);
            add_directory_to_zip($filePath, $zip, $baseDir);
        } else {
            $zip->addFile($filePath, $relativePath);
        }
    }
}

/**
 * Deleta diretório recursivamente
 */
function delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($filePath)) {
            delete_directory($filePath);
        } else {
            @unlink($filePath);
        }
    }
    
    return @rmdir($dir);
}

/**
 * Limpa backups antigos
 */
function cleanup_old_backups($config) {
    $backupDir = $config['paths']['backup'];
    $maxVersions = $config['backup']['max_versions'];
    
    if (!is_dir($backupDir)) {
        return;
    }
    
    $backups = glob($backupDir . DIRECTORY_SEPARATOR . 'backup_*');
    
    // Ordenar por data de modificação (mais recente primeiro)
    usort($backups, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Remover backups excedentes
    if (count($backups) > $maxVersions) {
        $toRemove = array_slice($backups, $maxVersions);
        foreach ($toRemove as $backup) {
            if (is_file($backup)) {
                @unlink($backup);
            } elseif (is_dir($backup)) {
                delete_directory($backup);
            }
            deploy_log("Backup antigo removido: " . basename($backup));
        }
    }
}

