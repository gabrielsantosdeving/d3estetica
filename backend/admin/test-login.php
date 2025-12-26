<?php
/**
 * Script de teste para verificar login do admin
 * Execute este arquivo no navegador para diagnosticar problemas
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

echo "<h1>Teste de Login Admin - D3 Estética</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } .success { color: green; } .error { color: red; } table { border-collapse: collapse; margin: 10px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    // Teste 1: Conexão
    echo "<h2>1. Teste de Conexão</h2>";
    $conn = getDB();
    echo "<p class='success'>✓ Conexão estabelecida com sucesso!</p>";
    
    // Teste 2: Verificar tabela administradores
    echo "<h2>2. Verificar Tabela administradores</h2>";
    $stmt = $conn->query("SHOW TABLES LIKE 'administradores'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✓ Tabela 'administradores' existe!</p>";
    } else {
        echo "<p class='error'>✗ Tabela 'administradores' NÃO existe!</p>";
        exit;
    }
    
    // Teste 3: Verificar administrador padrão
    echo "<h2>3. Verificar Administrador Padrão</h2>";
    $stmt = $conn->prepare("SELECT * FROM administradores WHERE email = ?");
    $stmt->execute(['admin@d3estetica.com.br']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "<p class='success'>✓ Administrador encontrado!</p>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>ID</td><td>{$admin['id']}</td></tr>";
        echo "<tr><td>Nome</td><td>{$admin['nome']}</td></tr>";
        echo "<tr><td>Email</td><td>{$admin['email']}</td></tr>";
        echo "<tr><td>Status</td><td>{$admin['status']}</td></tr>";
        echo "<tr><td>Código Segurança (2FA)</td><td><strong>{$admin['codigo_seguranca']}</strong></td></tr>";
        echo "<tr><td>Senha (Hash)</td><td>" . substr($admin['senha'], 0, 20) . "...</td></tr>";
        echo "</table>";
        
        // Teste 4: Verificar senha
        echo "<h2>4. Teste de Validação de Senha</h2>";
        $senha_teste = 'admin123';
        if (password_verify($senha_teste, $admin['senha'])) {
            echo "<p class='success'>✓ Senha 'admin123' está CORRETA!</p>";
        } else {
            echo "<p class='error'>✗ Senha 'admin123' está INCORRETA!</p>";
        }
        
        // Teste 5: Verificar código 2FA
        echo "<h2>5. Teste de Código 2FA</h2>";
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
        
        // Teste 6: Simular login completo
        echo "<h2>6. Simulação de Login Completo</h2>";
        $email_teste = 'admin@d3estetica.com.br';
        $senha_teste = 'admin123';
        $codigo_2fa_teste = '272204';
        
        $stmt = $conn->prepare("SELECT * FROM administradores WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email_teste]);
        $admin_teste = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin_teste && password_verify($senha_teste, $admin_teste['senha'])) {
            $codigo_banco_teste = trim($admin_teste['codigo_seguranca'] ?? '');
            if ($codigo_banco_teste === trim($codigo_2fa_teste)) {
                echo "<p class='success'>✓ Login completo seria BEM-SUCEDIDO!</p>";
            } else {
                echo "<p class='error'>✗ Login falharia: Código 2FA não confere</p>";
            }
        } else {
            echo "<p class='error'>✗ Login falharia: Email ou senha incorretos</p>";
        }
        
    } else {
        echo "<p class='error'>✗ Administrador 'admin@d3estetica.com.br' NÃO encontrado!</p>";
        echo "<p>Para criar, execute o script SQL: backend/database/database_completo.sql</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

