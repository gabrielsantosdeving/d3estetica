<?php
/**
 * API para buscar bairros de Uberlândia
 * Retorna lista de bairros para autocomplete
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Conectar ao banco de dados
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
    if (!file_exists($configPath)) {
        $configPath = __DIR__ . '/../config/database.php';
    }
    require_once $configPath;
    
    if (!function_exists('getDB')) {
        throw new Exception('Função getDB não encontrada');
    }
    
    $conn = getDB();
    
    if (!$conn) {
        throw new Exception('Erro ao conectar ao banco de dados');
    }
    
    // Buscar termo de busca (se fornecido)
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if (empty($search)) {
        // Retornar todos os bairros
        $stmt = $conn->prepare("SELECT nome, zona FROM bairros_uberlandia ORDER BY nome ASC");
        $stmt->execute();
    } else {
        // Buscar bairros que começam com o termo
        $searchTerm = $search . '%';
        $stmt = $conn->prepare("SELECT nome, zona FROM bairros_uberlandia WHERE nome LIKE ? ORDER BY nome ASC LIMIT 20");
        $stmt->execute([$searchTerm]);
    }
    
    $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $bairros
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar bairros: ' . $e->getMessage()
    ]);
}
?>

