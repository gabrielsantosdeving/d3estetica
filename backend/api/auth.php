<?php
/**
 * ============================================
 * API DE AUTENTICAÇÃO - D3 ESTÉTICA
 * ============================================
 * 
 * Endpoints:
 * - login_usuario: Login de usuário comum (email e senha)
 * - register_usuario: Cadastro de novo usuário (nome, email, senha)
 * - login: Login genérico
 * - logout: Logout
 * - check: Verificar se usuário está autenticado
 * 
 * @package D3Estetica
 * @file backend/api/auth.php
 * @version 1.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/database.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obter ação da requisição
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
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
    
    switch ($action) {
        case 'login_usuario':
            // Login de usuário comum
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $senha = $data['senha'] ?? '';
            
            if (empty($email) || empty($senha)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email e senha são obrigatórios'
                ]);
                exit();
            }
            
            // Buscar usuário na tabela usuarios
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_type'] = 'usuario';
                $_SESSION['user_name'] = $usuario['nome'];
                $_SESSION['user_email'] = $usuario['email'];
                
                // Retornar dados do usuário (sem senha)
                unset($usuario['senha']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login realizado com sucesso',
                    'user' => [
                        'id' => $usuario['id'],
                        'nome' => $usuario['nome'],
                        'email' => $usuario['email'],
                        'tipo' => 'usuario'
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email ou senha incorretos'
                ]);
            }
            break;
            
        case 'register_usuario':
            // Cadastro de novo usuário
            $data = json_decode(file_get_contents('php://input'), true);
            $nome = trim($data['nome'] ?? '');
            $email = trim($data['email'] ?? '');
            $senha = $data['senha'] ?? '';
            
            if (empty($nome) || empty($email) || empty($senha)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Todos os campos são obrigatórios'
                ]);
                exit();
            }
            
            // Validar email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email inválido'
                ]);
                exit();
            }
            
            // Validar senha (mínimo 6 caracteres)
            if (strlen($senha) < 6) {
                echo json_encode([
                    'success' => false,
                    'message' => 'A senha deve ter pelo menos 6 caracteres'
                ]);
                exit();
            }
            
            // Verificar se email já existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Este email já está cadastrado'
                ]);
                exit();
            }
            
            // Criar hash da senha
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            
            // Inserir novo usuário
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, status) VALUES (?, ?, ?, 'ativo')");
            $stmt->execute([$nome, $email, $senhaHash]);
            
            $userId = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cadastro realizado com sucesso',
                'user' => [
                    'id' => $userId,
                    'nome' => $nome,
                    'email' => $email,
                    'tipo' => 'usuario'
                ]
            ]);
            break;
            
        case 'login':
            // Login genérico (legado)
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $senha = $data['senha'] ?? '';
            
            if (empty($email) || empty($senha)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email e senha são obrigatórios'
                ]);
                exit();
            }
            
            // Buscar na tabela usuarios
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_type'] = 'usuario';
                $_SESSION['user_name'] = $usuario['nome'];
                $_SESSION['user_email'] = $usuario['email'];
                
                unset($usuario['senha']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login realizado com sucesso',
                    'user' => [
                        'id' => $usuario['id'],
                        'nome' => $usuario['nome'],
                        'email' => $usuario['email'],
                        'tipo' => 'usuario'
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Credenciais inválidas'
                ]);
            }
            break;
            
        case 'login_admin':
            // Login de administrador pela API (compatível com backend/admin/login-process.php)
            $data = json_decode(file_get_contents('php://input'), true);
            $nome = trim($data['nome'] ?? '');
            $email = trim($data['email'] ?? '');
            $cpf = preg_replace('/\D/', '', $data['cpf'] ?? '');
            $senha = $data['senha'] ?? '';
            $codigoSeguranca = trim($data['codigo_seguranca'] ?? '');

            if (empty($nome) || empty($email) || empty($cpf) || empty($senha) || empty($codigoSeguranca)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Preencha todos os campos obrigatórios'
                ]);
                exit();
            }

            if (strlen($cpf) !== 11 || !ctype_digit($cpf)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'CPF inválido'
                ]);
                exit();
            }

            if (!preg_match('/@vellure\.cloud$/i', $email)) {
                error_log("Login Admin API - Email não autorizado: $email");
                echo json_encode([
                    'success' => false,
                    'message' => 'Apenas emails @velure.cloud podem acessar o painel administrativo'
                ]);
                exit();
            }

            $stmt = $conn->prepare("SELECT * FROM administradores WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                $cpfBanco = preg_replace('/\D/', '', $admin['cpf'] ?? '');
                if ($cpfBanco !== $cpf) {
                    error_log("Login Admin API - CPF não corresponde. Recebido: $cpf, Banco: {$admin['cpf']}");
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email, CPF ou senha incorretos'
                    ]);
                    exit();
                }
            }

            if (!$admin) {
                error_log("Login Admin API - Administrador não encontrado para email: $email");
                echo json_encode([
                    'success' => false,
                    'message' => 'Email ou senha incorretos'
                ]);
                exit();
            }

            $status = trim(strtolower($admin['status'] ?? ''));
            if ($status !== 'ativo') {
                error_log("Login Admin API - Status não está ativo. Status atual: '{$admin['status']}'");
                echo json_encode([
                    'success' => false,
                    'message' => 'Sua conta está inativa. Entre em contato com o suporte.'
                ]);
                exit();
            }

            $senhaBanco = $admin['senha'] ?? '';
            if (empty($senhaBanco)) {
                error_log("Login Admin API - Senha vazia no banco para email: $email");
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro na configuração da conta. Entre em contato com o suporte.'
                ]);
                exit();
            }

            $senhaValida = false;
            if (password_verify($senha, $senhaBanco)) {
                $senhaValida = true;
            } elseif ($senha === $senhaBanco) {
                $senhaValida = true;
                $novoHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmtUpdate = $conn->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                $stmtUpdate->execute([$novoHash, $admin['id']]);
            }

            if (!$senhaValida) {
                error_log("Login Admin API - Senha incorreta para email: $email");
                echo json_encode([
                    'success' => false,
                    'message' => 'Email ou senha incorretos'
                ]);
                exit();
            }

            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['user_name'] = $admin['nome'];
            $_SESSION['user_email'] = $admin['email'];

            echo json_encode([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'user' => [
                    'id' => (int)$admin['id'],
                    'nome' => $admin['nome'] ?? '',
                    'email' => $admin['email'] ?? '',
                    'tipo' => 'admin'
                ]
            ]);
            break;

        case 'login_admin_simples':
            // Login de administrador simplificado (email e senha)
            $data = json_decode(file_get_contents('php://input'), true);
            $email = trim($data['email'] ?? '');
            $senha = $data['senha'] ?? '';

            if (empty($email) || empty($senha)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email e senha são obrigatórios'
                ]);
                exit();
            }

            if (!preg_match('/@vellure\.cloud$/i', $email)) {
                error_log("Login Admin Simples API - Email não autorizado: $email");
                echo json_encode([
                    'success' => false,
                    'message' => 'Apenas emails @velure.cloud podem acessar o painel administrativo'
                ]);
                exit();
            }

            $stmt = $conn->prepare("SELECT * FROM administradores WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                error_log("Login Admin Simples API - Administrador não encontrado para email: $email");
                echo json_encode([
                    'success' => false,
                    'message' => 'Email ou senha incorretos'
                ]);
                exit();
            }

            $status = trim(strtolower($admin['status'] ?? ''));
            if ($status !== 'ativo') {
                error_log("Login Admin Simples API - Status não está ativo. Status atual: '{$admin['status']}'");
                echo json_encode([
                    'success' => false,
                    'message' => 'Sua conta está inativa. Entre em contato com o suporte.'
                ]);
                exit();
            }

            $senhaBanco = $admin['senha'] ?? '';
            if (empty($senhaBanco)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro na configuração da conta. Entre em contato com o suporte.'
                ]);
                exit();
            }

            $senhaValida = false;
            if (password_verify($senha, $senhaBanco)) {
                $senhaValida = true;
            } elseif ($senha === $senhaBanco) {
                $senhaValida = true;
                $novoHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmtUpdate = $conn->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
                $stmtUpdate->execute([$novoHash, $admin['id']]);
            }

            if (!$senhaValida) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email ou senha incorretos'
                ]);
                exit();
            }

            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['user_name'] = $admin['nome'];
            $_SESSION['user_email'] = $admin['email'];

            echo json_encode([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'user' => [
                    'id' => (int)$admin['id'],
                    'nome' => $admin['nome'] ?? '',
                    'email' => $admin['email'] ?? '',
                    'tipo' => 'admin'
                ]
            ]);
            break;

        case 'logout':
            // Logout
            session_destroy();
            echo json_encode([
                'success' => true,
                'message' => 'Logout realizado com sucesso'
            ]);
            break;
            
        case 'check':
            // Verificar autenticação (suporta usuário comum e administrador)
            if (isset($_SESSION['user_id'])) {
                $userType = $_SESSION['user_type'] ?? 'usuario';

                if ($userType === 'admin') {
                    // Buscar dados do administrador
                    $stmt = $conn->prepare("SELECT id, nome, email, status FROM administradores WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($admin && $admin['status'] === 'ativo') {
                        echo json_encode([
                            'success' => true,
                            'user' => [
                                'id' => $admin['id'],
                                'nome' => $admin['nome'],
                                'email' => $admin['email'],
                                'tipo' => 'admin'
                            ]
                        ]);
                    } else {
                        session_destroy();
                        echo json_encode([
                            'success' => false,
                            'message' => 'Usuário não encontrado ou inativo'
                        ]);
                    }
                } else {
                    // Usuário comum (tabela usuarios)
                    $stmt = $conn->prepare("SELECT id, nome, email, status FROM usuarios WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($usuario && $usuario['status'] === 'ativo') {
                        echo json_encode([
                            'success' => true,
                            'user' => [
                                'id' => $usuario['id'],
                                'nome' => $usuario['nome'],
                                'email' => $usuario['email'],
                                'tipo' => $userType
                            ]
                        ]);
                    } else {
                        session_destroy();
                        echo json_encode([
                            'success' => false,
                            'message' => 'Usuário não encontrado ou inativo'
                        ]);
                    }
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Não autenticado'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ação não especificada'
            ]);
            break;
    }
} catch (PDOException $e) {
    error_log('Erro na API de autenticação: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição'
    ]);
} catch (Exception $e) {
    error_log('Erro na API de autenticação: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição'
    ]);
}
?>

