<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
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
    
    // Verificar se a conexão está ativa
    if (!$conn) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    // Testar conexão com query simples
    $conn->query("SELECT 1");
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Erro PDO em tickets.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados. Verifique as configurações.',
        'error_code' => $e->getCode()
    ]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em tickets.php: ' . $e->getMessage());
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
            $action = $_GET['action'] ?? null;
            
            if ($action === 'mensagens' && $id) {
                // Buscar mensagens de um ticket - permitir sem autenticação
                try {
                    $stmt = $conn->prepare("SELECT * FROM mensagens_chat WHERE ticket_id = ? ORDER BY created_at ASC");
                    $stmt->execute([$id]);
                    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!is_array($mensagens)) {
                        $mensagens = [];
                    }
                    
                    // Marcar mensagens como lidas apenas se for admin
                    try {
                        checkAuth();
                        checkAdmin();
                        $updateStmt = $conn->prepare("UPDATE mensagens_chat SET lida = TRUE WHERE ticket_id = ? AND tipo = 'user'");
                        $updateStmt->execute([$id]);
                    } catch (Exception $e) {
                        // Não é admin, não marcar como lida - continuar normalmente
                    }
                    
                    echo json_encode(['success' => true, 'data' => $mensagens]);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao buscar mensagens: ' . $e->getMessage()
                    ]);
                    exit();
                }
            } elseif ($id) {
                // Validar ID
                if (!is_numeric($id) || $id <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    break;
                }
                
                $id = intval($id);
                
                // Buscar ticket específico
                try {
                    $stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
                    $stmt->execute([$id]);
                    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($ticket) {
                        // Buscar mensagens
                        $msgStmt = $conn->prepare("SELECT * FROM mensagens_chat WHERE ticket_id = ? ORDER BY created_at ASC");
                        $msgStmt->execute([$id]);
                        $ticket['mensagens'] = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!is_array($ticket['mensagens'])) {
                            $ticket['mensagens'] = [];
                        }
                        
                        echo json_encode(['success' => true, 'data' => $ticket]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Ticket não encontrado']);
                    }
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao buscar ticket: ' . $e->getMessage()
                    ]);
                    exit();
                }
            } else {
                // Listar todos os tickets - requer autenticação admin
                // Verificar autenticação sem usar try/catch (checkAuth já faz exit)
                if (!isset($_SESSION)) {
                    session_start();
                }
                
                if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
                    // Tentar restaurar via cookie
                    if (isset($_COOKIE['remember_token'])) {
                        require_once dirname(__DIR__) . '/config/database.php';
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
                        
                        $sessionRestored = false;
                        foreach ($tokens as $token_row) {
                            if (password_verify($token, $token_row['token_hash'])) {
                                $_SESSION['user_id'] = $token_row['id'];
                                $_SESSION['user_type'] = 'admin';
                                $_SESSION['user_name'] = $token_row['nome'];
                                $_SESSION['user_email'] = $token_row['email'];
                                $sessionRestored = true;
                                break;
                            }
                        }
                        
                        if (!$sessionRestored) {
                            http_response_code(401);
                            echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
                            exit();
                        }
                    } else {
                        http_response_code(401);
                        echo json_encode(['success' => false, 'message' => 'Não autenticado. Faça login novamente.']);
                        exit();
                    }
                }
                
                $status = $_GET['status'] ?? null;
                
                try {
                    // Construir query de forma segura
                    $query = "SELECT id, status, cliente_id, cliente_nome, cliente_email, created_at, updated_at FROM tickets";
                    $params = [];
                    
                    if ($status && $status !== 'all') {
                        $query .= " WHERE status = ?";
                        $params[] = $status;
                    }
                    
                    // Usar COALESCE para lidar com updated_at NULL
                    $query .= " ORDER BY COALESCE(updated_at, created_at) DESC, created_at DESC";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Garantir que retorna array mesmo se vazio
                    if (!is_array($tickets)) {
                        $tickets = [];
                    }
                    
                    // Formatar dados para garantir consistência
                    foreach ($tickets as &$ticket) {
                        if (!isset($ticket['updated_at'])) {
                            $ticket['updated_at'] = $ticket['created_at'];
                        }
                    }
                    unset($ticket); // Limpar referência
                    
                    echo json_encode(['success' => true, 'data' => $tickets]);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao buscar tickets: ' . $e->getMessage(),
                        'code' => $e->getCode(),
                        'sqlstate' => $e->errorInfo[0] ?? null
                    ]);
                    exit();
                }
            }
            break;
            
        case 'POST':
            $action = $_GET['action'] ?? null;
            
            if ($action === 'mensagem') {
                // Enviar mensagem - permitir sem autenticação para clientes
                $data = json_decode(file_get_contents('php://input'), true);
                $ticketId = $data['ticket_id'] ?? null;
                $mensagem = trim($data['mensagem'] ?? '');
                $tipo = $data['tipo'] ?? 'user'; // admin ou user
                $nome = $data['nome'] ?? ($tipo === 'admin' ? 'Administrador' : 'Cliente');
                
                if (!$ticketId || !$mensagem) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                    break;
                }
                
                // Validar ID do ticket
                if (!is_numeric($ticketId) || $ticketId <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID do ticket inválido']);
                    break;
                }
                
                $ticketId = intval($ticketId);
                
                // Validar tamanho da mensagem
                if (strlen($mensagem) > 5000) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Mensagem muito longa (máximo 5000 caracteres)']);
                    break;
                }
                
                // Validar tipo
                if (!in_array($tipo, ['admin', 'user'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Tipo de mensagem inválido']);
                    break;
                }
                
                // Validar tamanho do nome
                if (strlen($nome) > 255) {
                    $nome = substr($nome, 0, 255);
                }
                
                // Verificar se ticket existe
                $stmt = $conn->prepare("SELECT id, status FROM tickets WHERE id = ?");
                $stmt->execute([$ticketId]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$ticket) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Ticket não encontrado']);
                    break;
                }
                
                // Se for admin, verificar autenticação
                if ($tipo === 'admin') {
                    checkAuth();
                    checkAdmin();
                }
                
                // Verificar se ticket está fechado
                if ($ticket['status'] === 'fechado' && $tipo === 'user') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Este ticket está fechado']);
                    break;
                }
                
                $stmt = $conn->prepare("INSERT INTO mensagens_chat (ticket_id, tipo, nome, mensagem) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ticketId, $tipo, $nome, $mensagem]);
                
                // Atualizar timestamp do ticket
                $conn->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticketId]);
                
                echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            } else {
                // Criar novo ticket - permitir sem autenticação (cliente)
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (empty($data)) {
                    // Tentar ler de FormData
                    $data = [
                        'cliente_id' => $_POST['cliente_id'] ?? null,
                        'cliente_nome' => $_POST['cliente_nome'] ?? 'Cliente',
                        'cliente_email' => $_POST['cliente_email'] ?? null,
                        'mensagem' => $_POST['mensagem'] ?? null
                    ];
                }
                
                // Validar nome do cliente
                $clienteNome = trim($data['cliente_nome'] ?? 'Cliente');
                if (strlen($clienteNome) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 255 caracteres)']);
                    break;
                }
                
                // Validar email se fornecido
                $clienteEmail = null;
                if (!empty($data['cliente_email'])) {
                    $clienteEmail = trim($data['cliente_email']);
                    if (!filter_var($clienteEmail, FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Email inválido']);
                        break;
                    }
                }
                
                // Validar cliente_id se fornecido
                $clienteId = null;
                if (isset($data['cliente_id']) && $data['cliente_id']) {
                    if (!is_numeric($data['cliente_id']) || $data['cliente_id'] <= 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'ID do cliente inválido']);
                        break;
                    }
                    $clienteId = intval($data['cliente_id']);
                }
                
                // Validar mensagem se fornecida
                $mensagem = null;
                if (!empty($data['mensagem'])) {
                    $mensagem = trim($data['mensagem']);
                    if (strlen($mensagem) > 5000) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Mensagem muito longa (máximo 5000 caracteres)']);
                        break;
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO tickets (cliente_id, cliente_nome, cliente_email, status) VALUES (?, ?, ?, 'aberto')");
                $stmt->execute([
                    $clienteId,
                    $clienteNome,
                    $clienteEmail
                ]);
                
                $ticketId = $conn->lastInsertId();
                
                // Criar primeira mensagem se fornecida
                if ($mensagem) {
                    $stmt = $conn->prepare("INSERT INTO mensagens_chat (ticket_id, tipo, nome, mensagem) VALUES (?, 'user', ?, ?)");
                    $stmt->execute([$ticketId, $clienteNome, $mensagem]);
                }
                
                echo json_encode(['success' => true, 'id' => $ticketId, 'ticket' => ['id' => $ticketId]]);
            }
            break;
            
        case 'PUT':
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            $action = $_GET['action'] ?? null;
            
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
            
            if ($action === 'close') {
                try {
                    $stmt = $conn->prepare("UPDATE tickets SET status = 'fechado', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Ticket fechado com sucesso']);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Ticket não encontrado']);
                    }
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Erro ao fechar ticket: ' . $e->getMessage()]);
                }
            } elseif ($action === 'open') {
                $stmt = $conn->prepare("UPDATE tickets SET status = 'aberto' WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            }
            break;
            
        case 'DELETE':
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            $action = $_GET['action'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            if ($action === 'delete' || !$action) {
                // Deletar mensagens primeiro (devido à foreign key)
                $stmt = $conn->prepare("DELETE FROM mensagens_chat WHERE ticket_id = ?");
                $stmt->execute([$id]);
                
                // Deletar ticket
                $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Ticket deletado com sucesso']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            }
            break;
            
        case 'PATCH':
            // Permitir cliente fechar ticket (sem autenticação admin)
            $id = $_GET['id'] ?? null;
            $action = $_GET['action'] ?? null;
            
            if (!$id || $action !== 'close') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação inválida']);
                break;
            }
            
            // Cliente pode fechar ticket sem autenticação
            $stmt = $conn->prepare("UPDATE tickets SET status = 'fechado' WHERE id = ?");
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>

