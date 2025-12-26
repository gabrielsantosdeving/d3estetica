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
                $stmt = $conn->prepare("SELECT v.*, p.nome as plano_nome, p.tipo as plano_tipo FROM vips v LEFT JOIN planos_vip p ON v.plano_id = p.id WHERE v.id = ?");
                $stmt->execute([$id]);
                $vip = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($vip) {
                    echo json_encode(['success' => true, 'data' => $vip]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Cliente VIP não encontrado']);
                }
            } else {
                // Verificar se é admin autenticado
                $isAdmin = isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
                
                // Apenas admin pode ver lista de VIPs
                if (!$isAdmin) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
                    exit();
                }
                
                $stmt = $conn->query("SELECT v.*, p.nome as plano_nome, p.tipo as plano_tipo FROM vips v LEFT JOIN planos_vip p ON v.plano_id = p.id ORDER BY v.created_at DESC");
                $vips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!is_array($vips)) {
                    $vips = [];
                }
                
                echo json_encode(['success' => true, 'data' => $vips]);
            }
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
    error_log('Erro em vips.php: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
    error_log('Erro em vips.php: ' . $e->getMessage());
}
?>

