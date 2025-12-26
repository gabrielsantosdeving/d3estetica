<?php
/**
 * API para verificar conexão com banco de dados
 * Usado pelo painel para verificar se o banco está acessível
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
            'message' => 'Erro fatal ao verificar conexão com banco de dados',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
});

// Limpar buffer antes de iniciar sessão
ob_clean();

// Iniciar sessão ANTES de qualquer header ou output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Agora podemos enviar os headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Usar caminho absoluto baseado no document root para compatibilidade com Hostinger
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
    if (!file_exists($configPath)) {
        $configPath = __DIR__ . '/../config/database.php';
    }
    
    if (!file_exists($configPath)) {
        throw new Exception('Arquivo de configuração do banco de dados não encontrado');
    }
    
    require_once $configPath;
    
    // Tentar conectar
    $conn = getDB();
    
    if (!$conn) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    // Testar query simples
    $stmt = $conn->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['test'])) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Conexão com banco de dados OK',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Falha ao executar query de teste');
    }
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao conectar com banco de dados: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'error_type' => 'PDOException'
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro PDO em check-db-connection.php: ' . $e->getMessage());
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao conectar com banco de dados: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'error_type' => 'Exception'
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro em check-db-connection.php: ' . $e->getMessage());
}
