<?php
/**
 * ============================================
 * PROCESSAMENTO DE LOGIN - ADMINISTRADOR
 * ============================================
 * 
 * Backend PHP que processa o login do administrador.
 * Valida credenciais no banco de dados e retorna JSON.
 * 
 * @package D3Estetica
 * @file auth/login-process.php
 * @version 1.0
 */

header('Content-Type: application/json');

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já estiver logado como admin, retornar sucesso
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    echo json_encode([
        'success' => true,
        'message' => 'Já está logado',
        'redirect' => '/backend/admin/index.html'
    ]);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit();
}

// Obter dados do POST
$email = trim($_POST['email'] ?? '');
$cpf = trim($_POST['cpf'] ?? '');
$senha = trim($_POST['senha'] ?? '');
$remember = isset($_POST['remember']) && $_POST['remember'] === '1';

// Remover formatação do CPF
$cpf = preg_replace('/\D/', '', $cpf);

// Validar campos obrigatórios
if (empty($email) || empty($cpf) || empty($senha)) {
    echo json_encode([
        'success' => false,
        'message' => 'Preencha todos os campos obrigatórios'
    ]);
    exit();
}

// Validar formato do CPF (deve ter 11 dígitos)
if (strlen($cpf) !== 11 || !ctype_digit($cpf)) {
    echo json_encode([
        'success' => false,
        'message' => 'CPF inválido'
    ]);
    exit();
}

// Validar se o email termina com @d3estetica.com.br
if (!preg_match('/@d3estetica\.com\.br$/i', $email)) {
    error_log("Login Admin - Email não autorizado: $email");
    echo json_encode([
        'success' => false,
        'message' => 'Apenas emails @d3estetica.com.br podem acessar o painel administrativo'
    ]);
    exit();
}

try {
    // Conectar ao banco de dados
    require_once dirname(__DIR__) . '/config/database.php';
    
    // Verificar se a função getDB existe
    if (!function_exists('getDB')) {
        error_log('ERRO: Função getDB não encontrada');
        echo json_encode([
            'success' => false,
            'message' => 'Erro de configuração do banco de dados'
        ]);
        exit();
    }
    
    $conn = getDB();
    
    // Verificar se a conexão foi estabelecida
    if (!$conn) {
        error_log('ERRO: Falha ao conectar ao banco de dados');
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao conectar com o banco de dados'
        ]);
        exit();
    }
    
    // Buscar administrador no banco de dados (verificar email primeiro)
    $stmt = $conn->prepare("SELECT * FROM administradores WHERE email = ?");
    
    if (!$stmt) {
        error_log('ERRO: Falha ao preparar query - ' . print_r($conn->errorInfo(), true));
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao consultar banco de dados'
        ]);
        exit();
    }
    
    $stmt->execute([trim($email)]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar CPF (pode estar com ou sem formatação no banco)
    if ($admin) {
        $cpf_banco = preg_replace('/\D/', '', $admin['cpf'] ?? '');
        if ($cpf_banco !== $cpf) {
            error_log("Login Admin - CPF não corresponde. Recebido: $cpf, Banco: {$admin['cpf']}");
            echo json_encode([
                'success' => false,
                'message' => 'Email, CPF ou senha incorretos'
            ]);
            exit();
        }
    }
    
    // Log para debug
    error_log("Login Admin - Email recebido: '$email', Admin encontrado: " . ($admin ? 'SIM' : 'NÃO'));
    if ($admin) {
        error_log("Login Admin - Email no banco: '{$admin['email']}', Status: '{$admin['status']}'");
    }
    
    // Verificar se o administrador existe
    if (!$admin) {
        error_log("Login Admin - Administrador não encontrado para email: $email");
        echo json_encode([
            'success' => false,
            'message' => 'Email ou senha incorretos'
        ]);
        exit();
    }
    
    // Verificar se o status está ativo (case-insensitive)
    $status = trim(strtolower($admin['status'] ?? ''));
    if ($status !== 'ativo') {
        error_log("Login Admin - Status não está ativo. Status atual: '{$admin['status']}'");
        echo json_encode([
            'success' => false,
            'message' => 'Sua conta está inativa. Entre em contato com o suporte.'
        ]);
        exit();
    }
    
    // Verificar se a senha está correta
    $senha_banco = $admin['senha'] ?? '';
    if (empty($senha_banco)) {
        error_log("Login Admin - Senha vazia no banco para email: $email");
        echo json_encode([
            'success' => false,
            'message' => 'Erro na configuração da conta. Entre em contato com o suporte.'
        ]);
        exit();
    }
    
    // Verificar senha: pode ser hash (password_hash) ou texto plano
    $senha_valida = false;
    
    // Primeiro tenta verificar como hash
    if (password_verify($senha, $senha_banco)) {
        $senha_valida = true;
    } 
    // Se não for hash, verifica como texto plano (compatibilidade com dados antigos)
    elseif ($senha === $senha_banco) {
        $senha_valida = true;
        // Se a senha estava em texto plano, converter para hash e atualizar no banco
        $novo_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt_update = $conn->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
        $stmt_update->execute([$novo_hash, $admin['id']]);
        error_log("Login Admin - Senha convertida de texto plano para hash para email: $email");
    }
    
    if (!$senha_valida) {
        error_log("Login Admin - Senha incorreta para email: $email");
        echo json_encode([
            'success' => false,
            'message' => 'Email ou senha incorretos'
        ]);
        exit();
    }
    
    // Todas as validações passaram - Login bem-sucedido
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_type'] = 'admin';
    $_SESSION['user_name'] = $admin['nome'];
    $_SESSION['user_email'] = $admin['email'];
    
    // Se "lembrar-me" estiver marcado, definir cookie e salvar token no banco
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $token_hash = password_hash($token, PASSWORD_DEFAULT);
        
        // Salvar token no banco (criar tabela se não existir)
        try {
            // Criar tabela sem FOREIGN KEY para evitar problemas
            $conn->exec("CREATE TABLE IF NOT EXISTS admin_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token_hash(64)),
                INDEX idx_admin (admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Limpar tokens expirados
            $conn->exec("DELETE FROM admin_tokens WHERE expires_at < NOW()");
            
            // Inserir novo token
            $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 dias
            $stmt_token = $conn->prepare("INSERT INTO admin_tokens (admin_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmt_token->execute([$admin['id'], $token_hash, $expires_at]);
            
            setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true); // 30 dias, httponly
        } catch (Exception $e) {
            error_log('Erro ao salvar token: ' . $e->getMessage());
            // Continuar mesmo se falhar ao salvar token - apenas definir cookie
            setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
        }
    }
    
    // Retornar sucesso com dados do admin
    $response = [
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'redirect' => '/backend/admin/index.html',
        'user' => [
            'id' => (int)$admin['id'],
            'nome' => $admin['nome'] ?? '',
            'email' => $admin['email'] ?? '',
            'tipo' => 'admin'
        ]
    ];
    
    error_log("Login Admin - Retornando sucesso para email: $email, Nome: {$admin['nome']}");
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Erro PDO no login admin: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Você não é um administrador'
    ]);
} catch (Exception $e) {
    error_log('Erro no login admin: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Você não é um administrador'
    ]);
}
?>
