<?php
/**
 * Verificar sessão do administrador para exibir no frontend
 */

// Iniciar buffer de saída para evitar qualquer output antes do JSON
ob_start();

// Garantir que sempre retornamos JSON, mesmo em caso de erro fatal
register_shutdown_function(function() {
    // Limpar qualquer output anterior
    ob_clean();
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'authenticated' => false,
            'success' => false,
            'error' => true,
            'message' => 'Erro fatal ao verificar sessão',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
});

// Limpar buffer antes de iniciar sessão
ob_clean();

// Iniciar sessão ANTES de qualquer header ou output
if (session_status() === PHP_SESSION_NONE) {
    if (session_name() !== 'PHPSESSID') {
        session_name('PHPSESSID');
    }
    session_start();
}

// Agora podemos enviar os headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Verificar se está autenticado como admin
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        ob_clean();
        echo json_encode([
            'authenticated' => true,
            'success' => true,
            'user_type' => 'admin',
            'user' => [
                'id' => $_SESSION['user_id'],
                'nome' => $_SESSION['user_name'] ?? '',
                'email' => $_SESSION['user_email'] ?? '',
                'tipo' => 'admin'
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Tentar restaurar sessão via cookie "lembrar-me"
        if (isset($_COOKIE['remember_token'])) {
            try {
                $configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
                if (!file_exists($configPath)) {
                    $configPath = __DIR__ . '/../config/database.php';
                }
                
                if (file_exists($configPath)) {
                    require_once $configPath;
                    $conn = getDB();
                    $token = $_COOKIE['remember_token'];
                    
                    $stmt = $conn->prepare("
                        SELECT at.admin_id, at.token_hash, a.id, a.nome, a.email, a.status 
                        FROM admin_tokens at
                        INNER JOIN administradores a ON at.admin_id = a.id
                        WHERE at.expires_at > NOW() AND a.status = 'ativo'
                        ORDER BY at.created_at DESC
                    ");
                    $stmt->execute();
                    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($tokens as $token_row) {
                        if (password_verify($token, $token_row['token_hash'])) {
                            $_SESSION['user_id'] = $token_row['id'];
                            $_SESSION['user_type'] = 'admin';
                            $_SESSION['user_name'] = $token_row['nome'];
                            $_SESSION['user_email'] = $token_row['email'];
                            
                            ob_clean();
                            echo json_encode([
                                'authenticated' => true,
                                'success' => true,
                                'user_type' => 'admin',
                                'user' => [
                                    'id' => $_SESSION['user_id'],
                                    'nome' => $_SESSION['user_name'] ?? '',
                                    'email' => $_SESSION['user_email'] ?? '',
                                    'tipo' => 'admin'
                                ]
                            ], JSON_UNESCAPED_UNICODE);
                            exit();
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Erro ao verificar token em check-admin-session: ' . $e->getMessage());
            }
        }
        
        ob_clean();
        echo json_encode([
            'authenticated' => false,
            'success' => false,
            'error' => false,
            'user_type' => null,
            'message' => 'Não autenticado'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'success' => false,
        'error' => true,
        'message' => 'Erro ao verificar sessão: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro em check-admin-session.php: ' . $e->getMessage());
}
