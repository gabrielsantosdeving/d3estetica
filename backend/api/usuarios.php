<?php
/**
 * API de Usuários - D3 Estética
 * Gerencia CRUD de usuários, clientes, administradores e doutoras
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
            'success' => false,
            'error' => true,
            'message' => 'Erro fatal na API de usuários',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
});

// Limpar buffer antes de iniciar sessão
ob_clean();

// Função auxiliar para garantir JSON limpo
function outputJSON($data, $statusCode = 200) {
    ob_clean();
    if ($statusCode !== 200) {
        http_response_code($statusCode);
    }
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

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
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Carregar configurações e funções de autenticação
try {
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
    if (!file_exists($configPath)) {
        $configPath = __DIR__ . '/../config/database.php';
    }
    
    if (!file_exists($configPath)) {
        throw new Exception('Arquivo de configuração do banco de dados não encontrado');
    }
    
    require_once $configPath;
    
    $authPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/auth/auth-functions.php';
    if (!file_exists($authPath)) {
        $authPath = __DIR__ . '/../auth/auth-functions.php';
    }
    
    if (!file_exists($authPath)) {
        throw new Exception('Arquivo de autenticação não encontrado');
    }
    
    require_once $authPath;
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Erro ao carregar configurações: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro ao carregar arquivos em usuarios.php: ' . $e->getMessage());
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
    ob_clean();
    http_response_code(500);
    error_log('Erro PDO em usuarios.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados. Verifique as configurações.',
        'error_code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log('Erro em usuarios.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    switch ($method) {
        case 'GET':
            // Verificar autenticação - checkAuth() e checkAdmin() fazem exit() se não autenticado
            // Se chegou aqui, está autenticado como admin
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            $tipo = $_GET['tipo'] ?? 'all'; // all, usuarios, clientes, administradores, doutoras
            
            if ($id) {
                // Buscar em todas as tabelas
                $tables = ['usuarios', 'clientes', 'administradores', 'doutoras'];
                $user = null;
                $userType = null;
                
                // Validar ID
                if (!is_numeric($id) || $id <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    break;
                }
                
                $id = intval($id);
                
                // Validar tabelas permitidas (prevenir SQL injection)
                $allowedTables = ['usuarios', 'clientes', 'administradores', 'doutoras'];
                foreach ($allowedTables as $table) {
                    $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
                    $stmt->execute([$id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $user = $result;
                        $userType = $table;
                        break;
                    }
                }
                
                if ($user) {
                    unset($user['senha']); // Não retornar senha
                    $user['tipo'] = $userType;
                    echo json_encode(['success' => true, 'data' => $user]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                }
            } else {
                $allUsers = [];
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
                $order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';
                
                if ($tipo === 'all' || $tipo === 'usuarios') {
                    try {
                        $query = "SELECT id, nome, email, status, created_at, 'usuarios' as tipo FROM usuarios ORDER BY created_at $order";
                        if ($limit) {
                            $query .= " LIMIT " . intval($limit);
                        }
                        $stmt = $conn->query($query);
                        if ($stmt) {
                            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($usuarios as $u) {
                                $allUsers[] = $u;
                            }
                        }
                    } catch (PDOException $e) {
                        error_log('Erro ao buscar usuarios: ' . $e->getMessage());
                        // Continuar mesmo se houver erro em uma tabela
                    }
                }
                
                if ($tipo === 'all' || $tipo === 'clientes') {
                    try {
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'clientes'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            $query = "SELECT id, nome, email, status, created_at, 'clientes' as tipo FROM clientes ORDER BY created_at $order";
                            if ($limit) {
                                $query .= " LIMIT " . intval($limit);
                            }
                            $stmt = $conn->query($query);
                            if ($stmt) {
                                $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($clientes as $c) {
                                    $allUsers[] = $c;
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        error_log('Erro ao buscar clientes: ' . $e->getMessage() . ' | SQL State: ' . ($e->errorInfo[0] ?? 'N/A'));
                        // Continuar mesmo se houver erro em uma tabela
                    }
                }
                
                if ($tipo === 'all' || $tipo === 'administradores') {
                    try {
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'administradores'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            $query = "SELECT id, nome, email, cpf, status, created_at, 'administradores' as tipo FROM administradores ORDER BY created_at $order";
                            if ($limit) {
                                $query .= " LIMIT " . intval($limit);
                            }
                            $stmt = $conn->query($query);
                            if ($stmt) {
                                $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($admins as $a) {
                                    $allUsers[] = $a;
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        error_log('Erro ao buscar administradores: ' . $e->getMessage() . ' | SQL State: ' . ($e->errorInfo[0] ?? 'N/A'));
                        // Continuar mesmo se houver erro em uma tabela
                    }
                }
                
                if ($tipo === 'all' || $tipo === 'doutoras') {
                    try {
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'doutoras'");
                        if ($tableCheck && $tableCheck->rowCount() > 0) {
                            $query = "SELECT id, nome_completo as nome, nome_usuario, email, telefone, status, created_at, 'doutoras' as tipo FROM doutoras ORDER BY created_at $order";
                            if ($limit) {
                                $query .= " LIMIT " . intval($limit);
                            }
                            $stmt = $conn->query($query);
                            if ($stmt) {
                                $doutoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($doutoras as $d) {
                                    $allUsers[] = $d;
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        error_log('Erro ao buscar doutoras: ' . $e->getMessage() . ' | SQL State: ' . ($e->errorInfo[0] ?? 'N/A'));
                        // Continuar mesmo se houver erro em uma tabela
                    }
                }
                
                // Ordenar todos os usuários por data se não foi limitado por tipo
                if ($tipo === 'all') {
                    usort($allUsers, function($a, $b) use ($order) {
                        // Administradores primeiro
                        if ($a['tipo'] === 'administradores' && $b['tipo'] !== 'administradores') return -1;
                        if ($a['tipo'] !== 'administradores' && $b['tipo'] === 'administradores') return 1;
                        
                        // Depois ordenar por data
                        $timeA = strtotime($a['created_at'] ?? '1970-01-01');
                        $timeB = strtotime($b['created_at'] ?? '1970-01-01');
                        return $order === 'DESC' ? $timeB - $timeA : $timeA - $timeB;
                    });
                    
                    // Aplicar limit global se especificado
                    if ($limit) {
                        $allUsers = array_slice($allUsers, 0, intval($limit));
                    }
                }
                
                if (!is_array($allUsers)) {
                    $allUsers = [];
                }
                
                // Garantir que é um array
                if (!is_array($allUsers)) {
                    $allUsers = [];
                }
                
                // Log para debug
                error_log('API usuarios.php: Retornando ' . count($allUsers) . ' usuários do tipo: ' . $tipo);
                
                // Sempre retornar sucesso, mesmo se não houver usuários
                // Isso permite que o frontend mostre "Nenhum usuário encontrado" em vez de erro
                $response = [
                    'success' => true, 
                    'data' => $allUsers,
                    'total' => count($allUsers)
                ];
                
                if (count($allUsers) === 0) {
                    $response['message'] = 'Nenhum usuário encontrado';
                }
                
                ob_clean();
                echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            break;
            
        case 'POST':
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Dados não fornecidos']);
                break;
            }
            
            $tipo = $data['tipo'] ?? 'usuarios';
            
            try {
            if ($tipo === 'usuarios') {
                if (empty($data['nome']) || empty($data['email']) || empty($data['senha'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: nome, email, senha']);
                    break;
                }
                
                // Validar email
                $email = trim($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Email inválido']);
                    break;
                }
                
                // Validar senha
                $senha = $data['senha'];
                if (strlen($senha) < 6) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 6 caracteres']);
                    break;
                }
                
                // Verificar se email já existe
                $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado']);
                    break;
                }
                
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([trim($data['nome']), $email, $senhaHash, $data['status'] ?? 'ativo']);
                } elseif ($tipo === 'clientes') {
                    if (empty($data['nome']) || empty($data['email']) || empty($data['senha']) || empty($data['codigo_verificacao'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: nome, email, senha, codigo_verificacao']);
                        break;
                    }
                    $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO clientes (nome, email, senha, codigo_verificacao, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([trim($data['nome']), trim($data['email']), $senhaHash, trim($data['codigo_verificacao']), $data['status'] ?? 'ativo']);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Tipo de usuário inválido']);
                    break;
                }
                
                echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao criar usuário: ' . $e->getMessage()]);
            }
            break;
            
        case 'PUT':
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $tipo = $data['tipo'] ?? 'usuarios';
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            if ($tipo === 'usuarios') {
                // Validar email se fornecido
                if (isset($data['email'])) {
                    $email = trim($data['email']);
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Email inválido']);
                        break;
                    }
                    
                    // Verificar se email já existe em outro usuário
                    $stmtCheck = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    $stmtCheck->execute([$email, $id]);
                    if ($stmtCheck->fetch()) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Este email já está em uso']);
                        break;
                    }
                }
                
                if (isset($data['senha'])) {
                    // Validar senha
                    if (strlen($data['senha']) < 6) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 6 caracteres']);
                        break;
                    }
                    $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, status = ? WHERE id = ?");
                    $stmt->execute([
                        trim($data['nome'] ?? ''), 
                        isset($data['email']) ? trim($data['email']) : '', 
                        $senhaHash, 
                        $data['status'] ?? 'ativo', 
                        $id
                    ]);
                } else {
                    $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, status = ? WHERE id = ?");
                    $stmt->execute([
                        trim($data['nome'] ?? ''), 
                        isset($data['email']) ? trim($data['email']) : '', 
                        $data['status'] ?? 'ativo', 
                        $id
                    ]);
                }
            } elseif ($tipo === 'clientes') {
                if (isset($data['senha'])) {
                    $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE clientes SET nome = ?, email = ?, senha = ?, codigo_verificacao = ?, status = ? WHERE id = ?");
                    $stmt->execute([$data['nome'], $data['email'], $senhaHash, $data['codigo_verificacao'], $data['status'], $id]);
                } else {
                    $stmt = $conn->prepare("UPDATE clientes SET nome = ?, email = ?, codigo_verificacao = ?, status = ? WHERE id = ?");
                    $stmt->execute([$data['nome'], $data['email'], $data['codigo_verificacao'], $data['status'], $id]);
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            $tipo = $_GET['tipo'] ?? 'usuarios';
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            $table = $tipo;
            $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Erro PDO em usuarios.php: ' . $e->getMessage() . ' | Código: ' . $e->getCode() . ' | SQL State: ' . ($e->errorInfo[0] ?? 'N/A'));
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no banco de dados: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'sql_state' => $e->errorInfo[0] ?? null
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em usuarios.php: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage(),
        'type' => get_class($e)
    ], JSON_UNESCAPED_UNICODE);
}
?>

