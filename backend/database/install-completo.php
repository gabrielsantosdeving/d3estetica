<?php
/**
 * ============================================
 * INSTALADOR COMPLETO DO BANCO DE DADOS
 * ============================================
 * 
 * Script para instalação completa do banco de dados D3 Estética
 * Execute este arquivo uma vez para configurar tudo
 * 
 * @package D3Estetica
 * @file install-completo.php
 * @version 1.0
 */

// Configurações do banco de dados
// IMPORTANTE: Configure estas credenciais antes de executar
$db_config = [
    'host' => 'localhost',
    'name' => 'u863732122_d3esteticaa', // Altere para o nome do seu banco
    'user' => 'u863732122_admind3',      // Altere para o usuário do banco
    'pass' => 'Da272204@',               // Altere para a senha do banco
    'charset' => 'utf8mb4'
];

// Ler o arquivo SQL
$sql_file = __DIR__ . '/d3estetica-completo.sql';
if (!file_exists($sql_file)) {
    die("Erro: Arquivo d3estetica-completo.sql não encontrado!");
}

$sql_content = file_get_contents($sql_file);

// Conectar ao MySQL (sem especificar o banco primeiro)
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};charset={$db_config['charset']}",
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Instalação do Banco de Dados - D3 Estética</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 900px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 { color: #3d462f; }
            h2 { color: #555; margin-top: 30px; }
            .success { color: #28a745; font-weight: bold; }
            .error { color: #dc3545; font-weight: bold; }
            .warning { color: #ffc107; font-weight: bold; }
            .info { color: #17a2b8; }
            code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
            ul { line-height: 1.8; }
            .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #3d462f; }
        </style>
    </head>
    <body>
    <div class='container'>";
    
    echo "<h1>🚀 Instalação do Banco de Dados D3 Estética</h1>";
    echo "<p class='success'>✓ Conectado ao MySQL com sucesso!</p>";
    
    // Criar banco de dados se não existir
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p class='success'>✓ Banco de dados '{$db_config['name']}' verificado/criado com sucesso!</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro ao criar banco de dados: " . $e->getMessage() . "</p>";
        exit;
    }
    
    // Selecionar o banco de dados
    $pdo->exec("USE `{$db_config['name']}`");
    echo "<p class='success'>✓ Banco de dados selecionado!</p>";
    
    // Dividir o SQL em comandos individuais
    // Remover comentários de bloco e processar
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
    
    $commands = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($cmd) {
            return !empty($cmd) && 
                   !preg_match('/^\s*--/', $cmd) &&
                   !preg_match('/^\s*SET\s+/i', $cmd);
        }
    );
    
    $executed = 0;
    $errors = [];
    $tables_created = [];
    
    echo "<h2>📋 Executando comandos SQL...</h2>";
    
    foreach ($commands as $command) {
        // Remover comentários de linha
        $command = preg_replace('/--.*$/m', '', $command);
        
        // Pular comandos vazios
        if (empty(trim($command))) {
            continue;
        }
        
        // Extrair nome da tabela se for CREATE TABLE
        $table_name = null;
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $command, $matches)) {
            $table_name = $matches[1];
        }
        
        try {
            $pdo->exec($command);
            $executed++;
            
            if ($table_name) {
                $tables_created[] = $table_name;
            }
        } catch (PDOException $e) {
            // Ignorar erros de "já existe" ou "duplicado"
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate') === false &&
                strpos($e->getMessage(), 'Duplicate key') === false) {
                $errors[] = [
                    'command' => substr($command, 0, 150) . '...',
                    'error' => $e->getMessage(),
                    'table' => $table_name
                ];
            } else {
                // Comando já executado, contar como sucesso
                $executed++;
                if ($table_name && !in_array($table_name, $tables_created)) {
                    $tables_created[] = $table_name;
                }
            }
        }
    }
    
    echo "<h2>✅ Instalação Concluída!</h2>";
    echo "<p><strong>Comandos executados:</strong> $executed</p>";
    
    if (count($tables_created) > 0) {
        echo "<p class='success'><strong>Tabelas criadas/verificadas:</strong> " . count($tables_created) . "</p>";
        echo "<ul>";
        foreach ($tables_created as $table) {
            echo "<li><code>$table</code></li>";
        }
        echo "</ul>";
    }
    
    if (!empty($errors)) {
        echo "<h2 class='warning'>⚠️ Avisos encontrados:</h2>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>";
            if ($error['table']) {
                echo "<strong>Tabela:</strong> <code>{$error['table']}</code><br>";
            }
            echo "<strong>Erro:</strong> {$error['error']}<br>";
            echo "<strong>Comando:</strong> <code>" . htmlspecialchars($error['command']) . "</code>";
            echo "</li>";
        }
        echo "</ul>";
    }
    
    // Verificar se o admin padrão foi criado
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM administradores WHERE email = 'admin@d3estetica.com.br'");
        $result = $stmt->fetch();
        if ($result['total'] > 0) {
            echo "<div class='step'>";
            echo "<h3>👤 Administrador Padrão</h3>";
            echo "<p class='success'>✓ Administrador padrão criado com sucesso!</p>";
            echo "<ul>";
            echo "<li><strong>Email:</strong> <code>admin@d3estetica.com.br</code></li>";
            echo "<li><strong>Senha:</strong> <code>admin123</code></li>";
            echo "<li class='warning'><strong>⚠️ IMPORTANTE:</strong> Altere a senha após o primeiro login!</li>";
            echo "</ul>";
            echo "</div>";
        }
    } catch (PDOException $e) {
        echo "<p class='warning'>⚠️ Não foi possível verificar o administrador padrão: " . $e->getMessage() . "</p>";
    }
    
    echo "<div class='step'>";
    echo "<h3>📝 Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li>Configure as credenciais do banco em <code>backend/config/database.php</code></li>";
    echo "<li>Acesse o painel administrativo: <a href='/backend/admin/index.php' target='_blank'>/backend/admin/index.php</a></li>";
    echo "<li>Faça login com as credenciais padrão acima</li>";
    echo "<li><strong>IMPORTANTE:</strong> Altere a senha do administrador após o primeiro login!</li>";
    echo "<li>Comece a adicionar serviços, promoções e conteúdo através do painel</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>📊 Estrutura do Banco de Dados</h3>";
    echo "<p>O banco de dados foi criado com <strong>" . count($tables_created) . " tabelas</strong>:</p>";
    echo "<ul>";
    echo "<li><strong>Usuários:</strong> administradores, usuarios, clientes, doutoras</li>";
    echo "<li><strong>Serviços:</strong> servicos, servico_valores, promocoes</li>";
    echo "<li><strong>Agendamentos:</strong> agendamentos, anamneses</li>";
    echo "<li><strong>VIP:</strong> planos_vip, vips</li>";
    echo "<li><strong>Suporte:</strong> tickets, mensagens_chat</li>";
    echo "<li><strong>Conteúdo:</strong> blog_posts</li>";
            echo "<li><strong>RH:</strong> candidaturas, vagas</li>";
            echo "<li><strong>Pagamentos:</strong> pedidos (Mercado Pago)</li>";
            echo "<li><strong>Segurança:</strong> admin_tokens</li>";
            echo "<li><strong>Auxiliares:</strong> bairros_uberlandia</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</div></body></html>";
    
} catch (PDOException $e) {
    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <title>Erro na Instalação</title>
        <style>
            body { font-family: Arial; padding: 50px; background: #f5f5f5; }
            .error-box { background: white; padding: 30px; border-radius: 10px; border-left: 5px solid #dc3545; }
            h1 { color: #dc3545; }
        </style>
    </head>
    <body>
    <div class='error-box'>
        <h1>❌ Erro na Conexão</h1>
        <p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Verifique as configurações no início do arquivo <code>install-completo.php</code></p>
        <p><strong>Configurações atuais:</strong></p>
        <ul>
            <li>Host: <code>{$db_config['host']}</code></li>
            <li>Banco: <code>{$db_config['name']}</code></li>
            <li>Usuário: <code>{$db_config['user']}</code></li>
        </ul>
    </div>
    </body>
    </html>";
}
?>

