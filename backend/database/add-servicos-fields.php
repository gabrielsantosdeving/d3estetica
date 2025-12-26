<?php
/**
 * Script para adicionar campos necessários na tabela servicos
 * Execute este arquivo uma vez para adicionar os campos: criado_por, created_at, updated_at
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $conn = getDB();
    
    echo "<h1>Adicionando campos à tabela servicos</h1>";
    echo "<p>Conectado ao banco de dados com sucesso!</p>";
    
    // Verificar se a coluna criado_por existe
    $stmt = $conn->query("SHOW COLUMNS FROM servicos LIKE 'criado_por'");
    $criadoPorExists = $stmt->rowCount() > 0;
    
    if (!$criadoPorExists) {
        echo "<p>Adicionando coluna criado_por...</p>";
        $conn->exec("ALTER TABLE servicos ADD COLUMN criado_por INT NULL");
        echo "<p style='color: green;'>✓ Coluna criado_por adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Coluna criado_por já existe.</p>";
    }
    
    // Verificar se a coluna created_at existe
    $stmt = $conn->query("SHOW COLUMNS FROM servicos LIKE 'created_at'");
    $createdAtExists = $stmt->rowCount() > 0;
    
    if (!$createdAtExists) {
        echo "<p>Adicionando coluna created_at...</p>";
        $conn->exec("ALTER TABLE servicos ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "<p style='color: green;'>✓ Coluna created_at adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Coluna created_at já existe.</p>";
    }
    
    // Verificar se a coluna updated_at existe
    $stmt = $conn->query("SHOW COLUMNS FROM servicos LIKE 'updated_at'");
    $updatedAtExists = $stmt->rowCount() > 0;
    
    if (!$updatedAtExists) {
        echo "<p>Adicionando coluna updated_at...</p>";
        $conn->exec("ALTER TABLE servicos ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "<p style='color: green;'>✓ Coluna updated_at adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Coluna updated_at já existe.</p>";
    }
    
    // Verificar se a coluna o_que_esta_incluso existe
    $stmt = $conn->query("SHOW COLUMNS FROM servicos LIKE 'o_que_esta_incluso'");
    $inclusoExists = $stmt->rowCount() > 0;
    
    if (!$inclusoExists) {
        echo "<p>Adicionando coluna o_que_esta_incluso...</p>";
        $conn->exec("ALTER TABLE servicos ADD COLUMN o_que_esta_incluso TEXT NULL");
        echo "<p style='color: green;'>✓ Coluna o_que_esta_incluso adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Coluna o_que_esta_incluso já existe.</p>";
    }
    
    // Tentar adicionar foreign key (pode falhar se já existir ou se a tabela usuarios não existir)
    try {
        $stmt = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                              WHERE TABLE_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'servicos' 
                              AND CONSTRAINT_NAME = 'fk_servicos_criado_por'");
        $fkExists = $stmt->rowCount() > 0;
        
        if (!$fkExists) {
            // Verificar se a tabela usuarios existe
            $stmt = $conn->query("SHOW TABLES LIKE 'usuarios'");
            $usuariosExists = $stmt->rowCount() > 0;
            
            if ($usuariosExists) {
                echo "<p>Adicionando foreign key fk_servicos_criado_por...</p>";
                $conn->exec("ALTER TABLE servicos 
                            ADD CONSTRAINT fk_servicos_criado_por 
                            FOREIGN KEY (criado_por) REFERENCES usuarios(id) 
                            ON DELETE SET NULL");
                echo "<p style='color: green;'>✓ Foreign key adicionada com sucesso!</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Tabela usuarios não encontrada. Foreign key não foi adicionada.</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ Foreign key já existe.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ Erro ao adicionar foreign key (pode já existir): " . $e->getMessage() . "</p>";
    }
    
    echo "<h2 style='color: green;'>✓ Processo concluído!</h2>";
    echo "<p><a href='/backend/admin/index.php'>Voltar ao painel administrativo</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Erro:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Verifique as configurações do banco de dados em backend/config/database.php</p>";
}
?>

