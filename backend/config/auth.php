<?php
/**
 * ============================================
 * ARQUIVO DE AUTENTICAÇÃO E AUTORIZAÇÃO
 * ============================================
 * 
 * Este arquivo contém funções para verificar autenticação e autorização de usuários.
 * Utiliza sessões PHP para gerenciar o estado de login dos usuários.
 * 
 * @package D3Estetica
 * @author Sistema D3 Estética
 * @version 1.0
 */

/**
 * Verifica se o usuário está autenticado
 * 
 * Verifica se existe uma sessão ativa com user_id.
 * Se não estiver autenticado, retorna erro 401 (Unauthorized) e encerra a execução.
 * 
 * @return void Encerra a execução se não estiver autenticado
 */
function checkAuth() {
    // Iniciar ou retomar sessão apenas se não estiver iniciada
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar nome da sessão para garantir consistência
        if (session_name() !== 'PHPSESSID') {
            session_name('PHPSESSID');
        }
        session_start();
    }
    
    // Verificar se o usuário está logado
    if (!isset($_SESSION['user_id'])) {
        // Tentar restaurar sessão via cookie "lembrar-me"
        if (isset($_COOKIE['remember_token'])) {
            try {
                require_once dirname(__FILE__) . '/database.php';
                $conn = getDB();
                $token = $_COOKIE['remember_token'];
                
                // Buscar token no banco
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
                        return; // Sessão restaurada, continuar
                    }
                }
            } catch (Exception $e) {
                error_log('Erro ao verificar token em checkAuth: ' . $e->getMessage());
            }
        }
        
        // Se ainda não estiver autenticado, retornar erro
        http_response_code(401); // Unauthorized
        echo json_encode([
            'success' => false, 
            'message' => 'Não autenticado'
        ]);
        exit();
    }
}

/**
 * Verifica se o usuário é administrador
 * 
 * Verifica se o usuário está autenticado E se é do tipo 'admin'.
 * Se não for admin, retorna erro 403 (Forbidden) e encerra a execução.
 * 
 * IMPORTANTE: Esta função também verifica autenticação, então não é necessário
 * chamar checkAuth() antes dela.
 * 
 * @return void Encerra a execução se não for administrador
 */
function checkAdmin() {
    // Verificar autenticação primeiro (já verifica cookie)
    checkAuth();
    
    // Verificar se é admin
    if ($_SESSION['user_type'] !== 'admin') {
        http_response_code(403); // Forbidden
        echo json_encode([
            'success' => false, 
            'message' => 'Acesso negado'
        ]);
        exit();
    }
}
?>

