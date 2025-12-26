<?php
/**
 * API de debug para usuários - versão simplificada para testar
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo json_encode([
    'debug' => true,
    'session_id' => session_id(),
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? null,
        'user_type' => $_SESSION['user_type'] ?? null,
        'user_name' => $_SESSION['user_name'] ?? null
    ],
    'cookie_token' => isset($_COOKIE['remember_token']) ? 'presente' : 'ausente'
]);

try {
    require_once dirname(__DIR__) . '/config/database.php';
    $conn = getDB();
    
    $allUsers = [];
    
    // Buscar de cada tabela sem autenticação (apenas para debug)
    try {
        $stmt = $conn->query("SELECT id, nome, email, status, created_at, 'usuarios' as tipo FROM usuarios LIMIT 5");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($usuarios as $u) {
            $allUsers[] = $u;
        }
    } catch (PDOException $e) {
        // Ignorar erro
    }
    
    try {
        $stmt = $conn->query("SELECT id, nome, email, cpf, status, created_at, 'administradores' as tipo FROM administradores LIMIT 5");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($admins as $a) {
            $allUsers[] = $a;
        }
    } catch (PDOException $e) {
        // Ignorar erro
    }
    
    try {
        $stmt = $conn->query("SELECT id, nome, email, status, created_at, 'clientes' as tipo FROM clientes LIMIT 5");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($clientes as $c) {
            $allUsers[] = $c;
        }
    } catch (PDOException $e) {
        // Ignorar erro
    }
    
    try {
        $stmt = $conn->query("SELECT id, nome_completo as nome, nome_usuario, email, telefone, status, created_at, 'doutoras' as tipo FROM doutoras LIMIT 5");
        $doutoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($doutoras as $d) {
            $allUsers[] = $d;
        }
    } catch (PDOException $e) {
        // Ignorar erro
    }
    
    echo json_encode([
        'debug' => true,
        'success' => true,
        'data' => $allUsers,
        'total' => count($allUsers),
        'session_id' => session_id(),
        'session_data' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_type' => $_SESSION['user_type'] ?? null
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'debug' => true,
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>


