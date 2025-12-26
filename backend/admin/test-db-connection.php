<?php
/**
 * Script de teste para verificar conexão e dados do administrador
 * Acesse: /backend/admin/test-db-connection.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Usar caminho absoluto baseado no document root para compatibilidade com Hostinger
$configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
if (!file_exists($configPath)) {
    // Fallback: tentar caminho relativo
    $configPath = __DIR__ . '/../config/database.php';
}
require_once $configPath;

echo "<h1>Teste de Conexão e Dados - Login Admin</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } .success { color: green; } .error { color: red; } table { border-collapse: collapse; margin: 10px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    // Teste 1: Conexão
    echo "<h2>1. Teste de Conexão</h2>";
    $conn = getDB();
    echo "<p class='success'>✓ Conexão estabelecida com sucesso!</p>";
    
    // Teste 2: Verificar tabela
    echo "<h2>2. Verificar Tabela administradores</h2>";
    $stmt = $conn->query("SHOW TABLES LIKE 'administradores'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✓ Tabela 'administradores' existe!</p>";
    } else {
        echo "<p class='error'>✗ Tabela 'administradores' NÃO existe!</p>";
        exit;
    }
    
    // Teste 3: Listar todos os administradores
    echo "<h2>3. Administradores no Banco</h2>";
    $stmt = $conn->query("SELECT id, nome, email, status, codigo_seguranca, created_at FROM administradores");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($admins) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Status</th><th>Código 2FA</th><th>Criado em</th></tr>";
        foreach ($admins as $admin) {
            echo "<tr>";
            echo "<td>{$admin['id']}</td>";
            echo "<td>{$admin['nome']}</td>";
            echo "<td>{$admin['email']}</td>";
            echo "<td>{$admin['status']}</td>";
            echo "<td><strong>{$admin['codigo_seguranca']}</strong></td>";
            echo "<td>{$admin['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>✗ Nenhum administrador encontrado no banco!</p>";
    }
    
    // Teste 4: Verificar administrador padrão
    echo "<h2>4. Verificar Administrador Padrão</h2>";
    $email_teste = 'admin@d3estetica.com.br';
    $stmt = $conn->prepare("SELECT * FROM administradores WHERE email = ?");
    $stmt->execute([$email_teste]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<p class='success'>✓ Administrador encontrado!</p>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>ID</td><td>{$admin['id']}</td></tr>";
        echo "<tr><td>Nome</td><td>{$admin['nome']}</td></tr>";
        echo "<tr><td>Email</td><td>{$admin['email']}</td></tr>";
        echo "<tr><td>Status</td><td><strong>{$admin['status']}</strong></td></tr>";
        echo "<tr><td>Código 2FA</td><td><strong>{$admin['codigo_seguranca']}</strong></td></tr>";
        echo "<tr><td>Senha (Hash)</td><td>" . substr($admin['senha'], 0, 30) . "...</td></tr>";
        echo "</table>";
        
        // Teste 5: Verificar senha
        echo "<h2>5. Teste de Validação de Senha</h2>";
        $senha_teste = 'admin123';
        if (password_verify($senha_teste, $admin['senha'])) {
            echo "<p class='success'>✓ Senha 'admin123' está CORRETA!</p>";
        } else {
            echo "<p class='error'>✗ Senha 'admin123' está INCORRETA!</p>";
            echo "<p>Hash no banco: " . substr($admin['senha'], 0, 30) . "...</p>";
        }
        
        // Teste 6: Verificar código 2FA
        echo "<h2>6. Teste de Código 2FA</h2>";
        $codigo_2fa_esperado = '272204';
        $codigo_banco = trim($admin['codigo_seguranca'] ?? '');
        
        echo "<p>Código esperado: <strong>$codigo_2fa_esperado</strong></p>";
        echo "<p>Código no banco: <strong>'$codigo_banco'</strong></p>";
        
        if ($codigo_banco === $codigo_2fa_esperado) {
            echo "<p class='success'>✓ Código 2FA está CORRETO!</p>";
        } else {
            echo "<p class='error'>✗ Código 2FA está INCORRETO!</p>";
            echo "<p>Para corrigir, execute:</p>";
            echo "<pre>UPDATE administradores SET codigo_seguranca = '272204' WHERE email = 'admin@d3estetica.com.br';</pre>";
        }
        
    } else {
        echo "<p class='error'>✗ Administrador '$email_teste' NÃO encontrado!</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

