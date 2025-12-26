<?php
/**
 * ============================================
 * API - CANDIDATURAS (TRABALHE CONOSCO)
 * ============================================
 * 
 * Gerencia candidaturas para trabalhar conosco
 * 
 * @package D3Estetica
 * @file candidaturas.php
 * @version 1.0
 */

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
            // Verificar se é admin para ver todas as candidaturas
            session_start();
            $isAdmin = isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
            
            $id = $_GET['id'] ?? null;
            $status = $_GET['status'] ?? null;
            
            if ($id) {
                // Buscar candidatura específica
                $stmt = $conn->prepare("SELECT * FROM candidaturas WHERE id = ?");
                $stmt->execute([$id]);
                $candidatura = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($candidatura) {
                    echo json_encode(['success' => true, 'data' => $candidatura]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Candidatura não encontrada']);
                }
            } else {
                // Listar candidaturas
                // Se não for admin, não pode ver candidaturas
                if (!$isAdmin) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
                    break;
                }
                
                $query = "SELECT * FROM candidaturas";
                $params = [];
                
                if ($status) {
                    $query .= " WHERE status = ?";
                    $params[] = $status;
                }
                
                $query .= " ORDER BY created_at DESC";
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                $candidaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!is_array($candidaturas)) {
                    $candidaturas = [];
                }
                
                echo json_encode(['success' => true, 'data' => $candidaturas]);
            }
            break;
            
        case 'POST':
            // Criar nova candidatura (público)
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validar campos obrigatórios
            if (empty($data['nome']) || empty($data['email']) || empty($data['telefone']) || empty($data['vaga'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: nome, email, telefone, vaga']);
                break;
            }
            
            // Validar email
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email inválido']);
                break;
            }
            
            // Validar tamanho dos campos
            if (strlen(trim($data['nome'])) > 255) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 255 caracteres)']);
                break;
            }
            
            if (strlen(trim($data['telefone'])) > 20) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Telefone muito longo']);
                break;
            }
            
            // Processar upload de certificado se houver
            $curriculoPath = null;
            if (!empty($data['certificado']) && $data['certificado'] !== 'null') {
                // Se for base64, salvar arquivo
                if (strpos($data['certificado'], 'data:') === 0) {
                    // Decodificar base64
                    $fileData = explode(',', $data['certificado'], 2);
                    if (count($fileData) !== 2) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Formato de arquivo inválido']);
                        exit();
                    }
                    
                    $fileInfo = explode(';', $fileData[0]);
                    $mimeType = str_replace('data:', '', $fileInfo[0]);
                    
                    // Validar tipo MIME permitido
                    $allowedMimeTypes = array_merge(ALLOWED_CV_TYPES, ALLOWED_IMAGE_TYPES);
                    if (!in_array($mimeType, $allowedMimeTypes)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido']);
                        exit();
                    }
                    
                    // Decodificar e validar tamanho
                    $fileContent = base64_decode($fileData[1], true);
                    if ($fileContent === false) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Erro ao decodificar arquivo']);
                        exit();
                    }
                    
                    if (strlen($fileContent) > MAX_UPLOAD_SIZE) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 5MB']);
                        exit();
                    }
                    
                    // Validar tipo MIME real do conteúdo
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $realMimeType = finfo_buffer($finfo, $fileContent);
                    finfo_close($finfo);
                    
                    if (!in_array($realMimeType, $allowedMimeTypes)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não corresponde ao declarado']);
                        exit();
                    }
                    
                    // Determinar extensão baseada no MIME type real
                    $extension = '';
                    if ($realMimeType === 'application/pdf') {
                        $extension = '.pdf';
                    } elseif ($realMimeType === 'image/jpeg') {
                        $extension = '.jpg';
                    } elseif ($realMimeType === 'image/png') {
                        $extension = '.png';
                    } elseif ($realMimeType === 'image/gif') {
                        $extension = '.gif';
                    } elseif ($realMimeType === 'image/webp') {
                        $extension = '.webp';
                    } else {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não suportado']);
                        exit();
                    }
                    
                    $fileName = 'candidatura_' . time() . '_' . uniqid() . $extension;
                    $uploadDir = dirname(__DIR__) . '/uploads/candidaturas/';
                    
                    // Criar diretório se não existir
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $filePath = $uploadDir . $fileName;
                    
                    // Validar que não há path traversal
                    $realPath = realpath($uploadDir);
                    $realFilePath = realpath(dirname($filePath));
                    if ($realFilePath === false || strpos($realFilePath, $realPath) !== 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Caminho de arquivo inválido']);
                        exit();
                    }
                    
                    if (file_put_contents($filePath, $fileContent) === false) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo']);
                        exit();
                    }
                    
                    $curriculoPath = '/backend/uploads/candidaturas/' . $fileName;
                } else {
                    // Se for apenas nome do arquivo, validar que não há path traversal
                    $fileName = basename($data['certificado']);
                    if ($fileName !== $data['certificado']) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Nome de arquivo inválido']);
                        exit();
                    }
                    $curriculoPath = '/backend/uploads/candidaturas/' . $fileName;
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO candidaturas (nome, email, telefone, vaga, mensagem, curriculo_path, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($data['nome']),
                $email,
                trim($data['telefone']),
                trim($data['vaga']),
                isset($data['mensagem']) ? trim($data['mensagem']) : null,
                $curriculoPath,
                'pendente'
            ]);
            
            $id = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'id' => $id,
                'message' => 'Candidatura enviada com sucesso!'
            ]);
            break;
            
        case 'PUT':
            // Atualizar candidatura (apenas admin)
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
            
            $stmt = $conn->prepare("UPDATE candidaturas SET nome = ?, email = ?, telefone = ?, vaga = ?, mensagem = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $data['nome'],
                $data['email'],
                $data['telefone'],
                $data['vaga'],
                $data['mensagem'] ?? null,
                $data['status'] ?? 'pendente',
                $id
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            // Deletar candidatura (apenas admin)
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
            
            // Buscar caminho do arquivo antes de deletar
            $stmt = $conn->prepare("SELECT curriculo_path FROM candidaturas WHERE id = ?");
            $stmt->execute([$id]);
            $candidatura = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($candidatura && $candidatura['curriculo_path']) {
                $filePath = dirname(__DIR__) . $candidatura['curriculo_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM candidaturas WHERE id = ?");
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
    error_log('Erro em candidaturas.php: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
    error_log('Erro em candidaturas.php: ' . $e->getMessage());
}
?>

