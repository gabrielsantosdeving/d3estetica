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

// Blog pode ser acessado sem autenticação para exibição pública
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
                $stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ? AND status = 'publicado'");
                $stmt->execute([$id]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($post) {
                    // Incrementar visualizações
                    $conn->prepare("UPDATE blog_posts SET visualizacoes = visualizacoes + 1 WHERE id = ?")->execute([$id]);
                    echo json_encode(['success' => true, 'data' => $post]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Post não encontrado']);
                }
            } else {
                // Verificar se é admin autenticado
                $isAdmin = isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
                
                // Admin pode ver todos os posts (publicados e rascunhos)
                // Público vê apenas publicados
                if ($isAdmin) {
                    $stmt = $conn->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
                } else {
                    $stmt = $conn->query("SELECT * FROM blog_posts WHERE status = 'publicado' ORDER BY created_at DESC");
                }
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!is_array($posts)) {
                    $posts = [];
                }
                
                echo json_encode(['success' => true, 'data' => $posts]);
            }
            break;
            
        case 'POST':
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data) || empty($data['titulo']) || empty($data['conteudo'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: titulo, conteudo']);
                break;
            }
            
            // Validar tamanho dos campos
            if (strlen(trim($data['titulo'])) > 255) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Título muito longo (máximo 255 caracteres)']);
                break;
            }
            
            // Validar status
            $status = $data['status'] ?? 'publicado';
            $allowedStatuses = ['publicado', 'rascunho', 'arquivado'];
            if (!in_array($status, $allowedStatuses)) {
                $status = 'publicado';
            }
            
            try {
                $stmt = $conn->prepare("INSERT INTO blog_posts (titulo, conteudo, resumo, imagem_path, autor, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    trim($data['titulo']),
                    trim($data['conteudo']),
                    isset($data['resumo']) ? trim($data['resumo']) : '',
                    isset($data['imagem_path']) ? trim($data['imagem_path']) : '',
                    isset($data['autor']) ? trim($data['autor']) : 'D3 Estética',
                    $status
                ]);
                
                echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao criar post: ' . $e->getMessage()]);
            }
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
            
            if (empty($data['titulo']) || empty($data['conteudo'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: titulo, conteudo']);
                break;
            }
            
            try {
                $stmt = $conn->prepare("UPDATE blog_posts SET titulo = ?, conteudo = ?, resumo = ?, imagem_path = ?, autor = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    trim($data['titulo']),
                    trim($data['conteudo']),
                    $data['resumo'] ?? '',
                    $data['imagem_path'] ?? '',
                    $data['autor'] ?? 'D3 Estética',
                    $data['status'] ?? 'publicado',
                    $id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Post atualizado com sucesso']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Post não encontrado']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar post: ' . $e->getMessage()]);
            }
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
            
            try {
                $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Post deletado com sucesso']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Post não encontrado']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao deletar post: ' . $e->getMessage()]);
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
    error_log('Erro em blog.php: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
    error_log('Erro em blog.php: ' . $e->getMessage());
}
?>



