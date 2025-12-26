<?php
/**
 * ============================================
 * PAINEL ADMINISTRATIVO - VERIFICAÇÃO DE ACESSO
 * ============================================
 * 
 * Verifica se o usuário está autenticado como administrador.
 * Redireciona para login se não estiver autenticado.
 * Inclui o HTML do painel se estiver autenticado.
 * 
 * @package D3Estetica
 * @file index.php
 * @version 1.0
 */

session_start();

// Verificar se está autenticado como admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    // Verificar se há cookie de "lembrar-me"
    if (isset($_COOKIE['remember_token'])) {
        // Usar caminho absoluto baseado no document root para compatibilidade com Hostinger
        $configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
        if (!file_exists($configPath)) {
            // Fallback: tentar caminho relativo
            $configPath = __DIR__ . '/../config/database.php';
        }
        require_once $configPath;
        
        try {
            $conn = getDB();
            $token = $_COOKIE['remember_token'];
            
            // Buscar token no banco e verificar
            $stmt = $conn->prepare("
                SELECT at.admin_id, at.token_hash, a.id, a.nome, a.email, a.status 
                FROM admin_tokens at
                INNER JOIN administradores a ON at.admin_id = a.id
                WHERE at.expires_at > NOW() AND a.status = 'ativo'
                ORDER BY at.created_at DESC
            ");
            $stmt->execute();
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Verificar se algum token corresponde
            foreach ($tokens as $token_row) {
                if (password_verify($token, $token_row['token_hash'])) {
                    // Token válido - restaurar sessão
                    $_SESSION['user_id'] = $token_row['id'];
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['user_name'] = $token_row['nome'];
                    $_SESSION['user_email'] = $token_row['email'];
                    // Sessão restaurada, continuar
                    break;
                }
            }
        } catch (Exception $e) {
            error_log('Erro ao verificar token: ' . $e->getMessage());
        }
    }
    
    // Se ainda não estiver autenticado após verificar token, redirecionar
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        header('Location: /backend/admin/login.html');
        exit();
    }
}

// Buscar dados atualizados do administrador do banco
try {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT id, nome, email, cpf, status FROM administradores WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminData) {
        // Atualizar sessão com dados do banco
        $_SESSION['user_name'] = $adminData['nome'];
        $_SESSION['user_email'] = $adminData['email'];
        $_SESSION['user_cpf'] = $adminData['cpf'] ?? '';
    }
} catch (Exception $e) {
    error_log('Erro ao buscar dados do admin: ' . $e->getMessage());
}

// Incluir o HTML do painel
include 'index.html';
?>

