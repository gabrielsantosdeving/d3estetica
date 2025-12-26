<?php
/**
 * ============================================
 * API - PERFIL DO ADMINISTRADOR
 * ============================================
 * 
 * Gerencia dados do perfil do administrador
 * 
 * @package D3Estetica
 * @file admin-profile.php
 * @version 1.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/auth/auth-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Obter conexão com tratamento de erro
try {
    $conn = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage()
    ]);
    exit();
}

try {
    // Verificar autenticação
    checkAuth();
    checkAdmin();
    
    switch ($method) {
        case 'GET':
            // Buscar dados do administrador logado
            $adminId = $_SESSION['user_id'];
            
            $stmt = $conn->prepare("SELECT id, nome, email, cpf, status, created_at, updated_at FROM administradores WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                echo json_encode([
                    'success' => true,
                    'data' => $admin
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Administrador não encontrado'
                ]);
            }
            break;
            
        case 'PUT':
            // Atualizar dados do administrador
            $data = json_decode(file_get_contents('php://input'), true);
            $adminId = $_SESSION['user_id'];
            
            if (empty($data)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhum dado fornecido'
                ]);
                exit();
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['nome'])) {
                $nome = trim($data['nome']);
                if (strlen($nome) > 255) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Nome muito longo (máximo 255 caracteres)'
                    ]);
                    exit();
                }
                $updates[] = "nome = ?";
                $params[] = $nome;
            }
            
            if (isset($data['email'])) {
                $email = trim($data['email']);
                // Validar email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email inválido'
                    ]);
                    exit();
                }
                
                // Verificar se email já existe em outro admin
                $stmt = $conn->prepare("SELECT id FROM administradores WHERE email = ? AND id != ?");
                $stmt->execute([$email, $adminId]);
                if ($stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Este email já está em uso'
                    ]);
                    exit();
                }
                $updates[] = "email = ?";
                $params[] = $email;
            }
            
            if (isset($data['senha_atual']) && isset($data['senha_nova'])) {
                // Validar senha nova
                if (strlen($data['senha_nova']) < 6) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'A nova senha deve ter pelo menos 6 caracteres'
                    ]);
                    exit();
                }
                
                // Verificar senha atual
                $stmt = $conn->prepare("SELECT senha FROM administradores WHERE id = ?");
                $stmt->execute([$adminId]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$admin || !password_verify($data['senha_atual'], $admin['senha'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Senha atual incorreta'
                    ]);
                    exit();
                }
                
                $updates[] = "senha = ?";
                $params[] = password_hash($data['senha_nova'], PASSWORD_DEFAULT);
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhum dado para atualizar'
                ]);
                exit();
            }
            
            $params[] = $adminId;
            $query = "UPDATE administradores SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Atualizar sessão
            if (isset($data['nome'])) {
                $_SESSION['user_name'] = $data['nome'];
            }
            if (isset($data['email'])) {
                $_SESSION['user_email'] = $data['email'];
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método não permitido'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log('Erro em admin-profile.php: ' . $e->getMessage());
}
?>

