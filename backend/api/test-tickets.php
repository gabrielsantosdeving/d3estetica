<?php
/**
 * Script de teste para verificar se a API de tickets está funcionando
 * Acesse: /backend/api/test-tickets.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/auth/auth-functions.php';

echo "<h1>Teste de API de Tickets</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } .success { color: green; } .error { color: red; } table { border-collapse: collapse; margin: 10px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    // Teste 1: Conexão com banco
    echo "<h2>1. Teste de Conexão</h2>";
    $conn = getDB();
    echo "<p class='success'>✓ Conexão estabelecida com sucesso!</p>";
    
    // Teste 2: Verificar tabelas
    echo "<h2>2. Verificar Tabelas</h2>";
    $tables = ['tickets', 'mensagens_chat', 'administradores', 'admin_tokens'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='success'>✓ Tabela '$table' existe!</p>";
        } else {
            echo "<p class='error'>✗ Tabela '$table' NÃO existe!</p>";
        }
    }
    
    // Teste 3: Verificar sessão
    echo "<h2>3. Verificar Sessão</h2>";
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id'])) {
        echo "<p class='success'>✓ Sessão ativa! User ID: " . $_SESSION['user_id'] . "</p>";
        echo "<p class='success'>✓ Tipo: " . ($_SESSION['user_type'] ?? 'não definido') . "</p>";
        echo "<p class='success'>✓ Nome: " . ($_SESSION['user_name'] ?? 'não definido') . "</p>";
    } else {
        echo "<p class='error'>✗ Sessão não ativa</p>";
        
        // Verificar cookie
        if (isset($_COOKIE['remember_token'])) {
            echo "<p>Cookie 'remember_token' encontrado. Tentando restaurar sessão...</p>";
            $token = $_COOKIE['remember_token'];
            
            $stmt = $conn->prepare("
                SELECT at.admin_id, at.token_hash, a.id, a.nome, a.email, a.status 
                FROM admin_tokens at
                INNER JOIN administradores a ON at.admin_id = a.id
                WHERE at.expires_at > NOW() AND a.status = 'ativo'
                ORDER BY at.created_at DESC
            ");
            $stmt->execute();
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($tokens) > 0) {
                echo "<p>Encontrados " . count($tokens) . " tokens válidos no banco.</p>";
                $found = false;
                foreach ($tokens as $token_row) {
                    if (password_verify($token, $token_row['token_hash'])) {
                        echo "<p class='success'>✓ Token válido encontrado! Admin ID: " . $token_row['id'] . "</p>";
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    echo "<p class='error'>✗ Nenhum token corresponde ao cookie</p>";
                }
            } else {
                echo "<p class='error'>✗ Nenhum token válido encontrado no banco</p>";
            }
        } else {
            echo "<p class='error'>✗ Cookie 'remember_token' não encontrado</p>";
        }
    }
    
    // Teste 4: Contar tickets
    echo "<h2>4. Contar Tickets</h2>";
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tickets");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total de tickets: " . $result['total'] . "</p>";
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tickets WHERE status = 'aberto'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Tickets abertos: " . $result['total'] . "</p>";
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tickets WHERE status = 'fechado'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Tickets fechados: " . $result['total'] . "</p>";
    
    // Teste 5: Listar tickets
    echo "<h2>5. Listar Tickets</h2>";
    $stmt = $conn->query("SELECT * FROM tickets ORDER BY created_at DESC LIMIT 10");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($tickets) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Cliente</th><th>Email</th><th>Status</th><th>Criado em</th></tr>";
        foreach ($tickets as $ticket) {
            echo "<tr>";
            echo "<td>" . $ticket['id'] . "</td>";
            echo "<td>" . htmlspecialchars($ticket['cliente_nome'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($ticket['cliente_email'] ?? 'N/A') . "</td>";
            echo "<td>" . $ticket['status'] . "</td>";
            echo "<td>" . $ticket['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum ticket encontrado.</p>";
    }
    
    // Teste 6: Testar autenticação
    echo "<h2>6. Testar Autenticação</h2>";
    try {
        checkAuth();
        echo "<p class='success'>✓ checkAuth() passou</p>";
        
        checkAdmin();
        echo "<p class='success'>✓ checkAdmin() passou</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Erro na autenticação: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>7. Testar Endpoint</h2>";
    echo "<p><a href='tickets.php' target='_blank'>Testar tickets.php diretamente</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

