<?php
/**
 * ============================================
 * INSTALADOR DO BANCO DE DADOS
 * ============================================
 * 
 * Script para instalação rápida do banco de dados
 * Execute este arquivo uma vez para configurar tudo
 * 
 * @package D3Estetica
 * @file install.php
 * @version 1.0
 */

// Configurações do banco de dados
// Configurado para Hostinger
$db_config = [
    'host' => 'localhost',
    'name' => 'u863732122_d3esteticaa',
    'user' => 'u863732122_admind3',
    'pass' => 'Da272204@',
    'charset' => 'utf8mb4'
];

// Ler o arquivo SQL
$sql_file = __DIR__ . '/data.sql';
if (!file_exists($sql_file)) {
    die("Erro: Arquivo data.sql não encontrado!");
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
    
    echo "<h1>Instalação do Banco de Dados D3 Estética</h1>";
    echo "<p>Conectado ao MySQL com sucesso!</p>";
    
    // Dividir o SQL em comandos individuais
    $commands = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($cmd) {
            return !empty($cmd) && 
                   !preg_match('/^\s*--/', $cmd) && 
                   !preg_match('/^\s*\/\*/', $cmd);
        }
    );
    
    $executed = 0;
    $errors = [];
    
    foreach ($commands as $command) {
        // Remover comentários de linha
        $command = preg_replace('/--.*$/m', '', $command);
        
        // Pular comandos vazios
        if (empty(trim($command))) {
            continue;
        }
        
        try {
            $pdo->exec($command);
            $executed++;
        } catch (PDOException $e) {
            // Ignorar erros de "já existe" ou "duplicado"
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate') === false) {
                $errors[] = [
                    'command' => substr($command, 0, 100) . '...',
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    echo "<h2>Instalação Concluída!</h2>";
    echo "<p><strong>Comandos executados:</strong> $executed</p>";
    
    if (!empty($errors)) {
        echo "<h3>Erros encontrados:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li><strong>Comando:</strong> {$error['command']}<br>";
            echo "<strong>Erro:</strong> {$error['error']}</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3>Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li>Configure as credenciais do banco em <code>backend/config/config.php</code></li>";
    echo "<li>Acesse o painel administrativo: <a href='/backend/admin/index.php'>/backend/admin/index.php</a></li>";
    echo "<li>Faça login com:<br>";
    echo "   <strong>Email:</strong> admin@d3estetica.com.br<br>";
    echo "   <strong>Senha:</strong> admin123</li>";
    echo "<li><strong>IMPORTANTE:</strong> Altere a senha do administrador após o primeiro login!</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    die("<h1>Erro na Conexão</h1><p>Erro: " . $e->getMessage() . "</p><p>Verifique as configurações no início deste arquivo.</p>");
}
?>

