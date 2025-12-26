<?php
/**
 * ============================================
 * CONFIGURAR CÓDIGO 2FA PADRÃO - ADMINISTRADOR
 * ============================================
 * 
 * Este script configura o código 2FA padrão para o administrador.
 * Execute este script uma vez para definir o código padrão.
 * 
 * Código 2FA Padrão: 272204
 * 
 * @package D3Estetica
 * @file setup-2fa-default.php
 * @version 1.0
 */

header('Content-Type: application/json');

// Usar caminho absoluto baseado no document root para compatibilidade com Hostinger
$configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
if (!file_exists($configPath)) {
    // Fallback: tentar caminho relativo
    $configPath = __DIR__ . '/../config/database.php';
}
require_once $configPath;

// ============================================
// CÓDIGO 2FA PADRÃO
// ============================================
// Este é o código que o administrador deve digitar no login
// Armazenado no campo 'codigo_seguranca' da tabela 'administradores'
$codigo_2fa_padrao = '272204';

try {
    $conn = getDB();
    
    // Verificar se existe administrador
    $stmt = $conn->query("SELECT id, email, codigo_seguranca FROM administradores LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        // Atualizar código de segurança para o padrão em TODOS os administradores ativos
        $stmt = $conn->prepare("UPDATE administradores SET codigo_seguranca = ? WHERE status = 'ativo'");
        $stmt->execute([$codigo_2fa_padrao]);
        
        $updated = $stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'message' => "Código 2FA padrão configurado com sucesso para $updated administrador(es)!",
            'admin_email' => $admin['email'],
            'codigo_2fa' => $codigo_2fa_padrao,
            'instrucoes' => "Use o código '$codigo_2fa_padrao' no campo 'Código de Autenticação (2FA)' no login"
        ]);
    } else {
        // Criar administrador padrão se não existir
        $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO administradores (nome, email, cpf, senha, codigo_seguranca, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Administrador',
            'admin@d3estetica.com.br',
            '00000000000',
            $senhaHash,
            $codigo_2fa_padrao,
            'ativo'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Administrador criado com código 2FA padrão!',
            'admin_email' => 'admin@d3estetica.com.br',
            'codigo_2fa' => $codigo_2fa_padrao,
            'senha_padrao' => 'admin123',
            'instrucoes' => 'Use este código no login: ' . $codigo_2fa_padrao
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao configurar código 2FA: ' . $e->getMessage()
    ]);
}
?>

