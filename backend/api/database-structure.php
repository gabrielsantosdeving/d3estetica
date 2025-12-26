<?php
/**
 * API para obter estrutura do banco de dados
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Apenas admin pode ver estrutura do banco
checkAuth();
checkAdmin();

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
    
    // Obter todas as tabelas
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $structure = [];
    
    foreach ($tables as $table) {
        // Obter estrutura da tabela
        $stmt = $conn->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obter índices
        $stmt = $conn->query("SHOW INDEX FROM `$table`");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obter foreign keys
        $stmt = $conn->query("
            SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$table'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar registros
        $stmt = $conn->query("SELECT COUNT(*) as total FROM `$table`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $structure[$table] = [
            'columns' => $columns,
            'indexes' => $indexes,
            'foreign_keys' => $foreignKeys,
            'row_count' => (int)$count
        ];
    }
    
    // Obter nome do banco de dados
    $stmt = $conn->query("SELECT DATABASE() as db");
    $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db'];
    
    echo json_encode([
        'success' => true,
        'database' => $dbName,
        'tables' => $structure,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

