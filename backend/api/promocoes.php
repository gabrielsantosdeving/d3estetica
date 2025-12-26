<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            
            if ($id) {
                $stmt = $conn->prepare("SELECT * FROM promocoes WHERE id = ?");
                $stmt->execute([$id]);
                $promocao = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($promocao) {
                    echo json_encode(['success' => true, 'data' => $promocao]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Promoção não encontrada']);
                }
            } else {
                $stmt = $conn->query("SELECT * FROM promocoes ORDER BY created_at DESC");
                $promocoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!is_array($promocoes)) {
                    $promocoes = [];
                }
                
                echo json_encode(['success' => true, 'data' => $promocoes]);
            }
            break;
            
        case 'POST':
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $stmt = $conn->prepare("INSERT INTO promocoes (titulo, descricao, desconto, validade, status, imagem_path) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['titulo'],
                $data['descricao'],
                $data['desconto'] ?? 0,
                $data['validade'] ?? null,
                $data['status'] ?? 'ativa',
                $data['imagem_path'] ?? null
            ]);
            
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;
            
        case 'PUT':
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            // Validar ID (deve ser numérico)
            if (!is_numeric($id) || $id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                break;
            }
            
            $id = intval($id);
            
            // Validar desconto
            $desconto = floatval($data['desconto'] ?? 0);
            if ($desconto < 0 || $desconto > 100) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Desconto deve estar entre 0 e 100']);
                break;
            }
            
            $stmt = $conn->prepare("UPDATE promocoes SET titulo = ?, descricao = ?, desconto = ?, validade = ?, status = ?, imagem_path = ? WHERE id = ?");
            $stmt->execute([
                trim($data['titulo'] ?? ''),
                isset($data['descricao']) ? trim($data['descricao']) : '',
                $desconto,
                isset($data['validade']) && $data['validade'] !== '' ? $data['validade'] : null,
                $data['status'] ?? 'ativa',
                isset($data['imagem_path']) ? trim($data['imagem_path']) : null,
                $id
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            // Validar ID (deve ser numérico)
            if (!is_numeric($id) || $id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                break;
            }
            
            $id = intval($id);
            
            $stmt = $conn->prepare("DELETE FROM promocoes WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no banco de dados: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
    error_log('Erro em promocoes.php: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
    error_log('Erro em promocoes.php: ' . $e->getMessage());
}
?>

