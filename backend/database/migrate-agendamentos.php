<?php
/**
 * Script de migração para corrigir a tabela agendamentos
 * Execute este arquivo via navegador ou linha de comando
 */

require_once dirname(__DIR__) . '/config/database.php';

try {
    $conn = getDB();
    
    echo "Iniciando migração da tabela agendamentos...\n\n";
    
    // Verificar se a coluna 'nome' existe
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'nome'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'nome'...\n";
        $conn->exec("ALTER TABLE agendamentos ADD COLUMN `nome` VARCHAR(255) NOT NULL COMMENT 'Nome do cliente' AFTER `cliente_id`");
        echo "✓ Coluna 'nome' adicionada\n";
    } else {
        echo "✓ Coluna 'nome' já existe\n";
    }
    
    // Verificar se a coluna 'email' existe
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'email'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'email'...\n";
        $conn->exec("ALTER TABLE agendamentos ADD COLUMN `email` VARCHAR(255) NOT NULL COMMENT 'Email do cliente' AFTER `nome`");
        echo "✓ Coluna 'email' adicionada\n";
    } else {
        echo "✓ Coluna 'email' já existe\n";
    }
    
    // Verificar se a coluna 'telefone' existe
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'telefone'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'telefone'...\n";
        $conn->exec("ALTER TABLE agendamentos ADD COLUMN `telefone` VARCHAR(20) DEFAULT NULL COMMENT 'Telefone do cliente' AFTER `email`");
        echo "✓ Coluna 'telefone' adicionada\n";
    } else {
        echo "✓ Coluna 'telefone' já existe\n";
    }
    
    // Verificar se a coluna 'regiao' existe
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'regiao'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'regiao'...\n";
        $conn->exec("ALTER TABLE agendamentos ADD COLUMN `regiao` VARCHAR(255) DEFAULT NULL COMMENT 'Região do cliente' AFTER `telefone`");
        echo "✓ Coluna 'regiao' adicionada\n";
    } else {
        echo "✓ Coluna 'regiao' já existe\n";
    }
    
    // Verificar se a coluna 'bairro' existe
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'bairro'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'bairro'...\n";
        $conn->exec("ALTER TABLE agendamentos ADD COLUMN `bairro` VARCHAR(255) DEFAULT NULL COMMENT 'Bairro do cliente' AFTER `regiao`");
        echo "✓ Coluna 'bairro' adicionada\n";
    } else {
        echo "✓ Coluna 'bairro' já existe\n";
    }
    
    // Migrar dados das colunas antigas para as novas (se existirem)
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'cliente_nome'");
    if ($stmt->rowCount() > 0) {
        echo "Migrando dados de cliente_nome para nome...\n";
        $conn->exec("UPDATE agendamentos SET nome = cliente_nome WHERE cliente_nome IS NOT NULL AND (nome IS NULL OR nome = '')");
        echo "✓ Dados migrados\n";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'cliente_email'");
    if ($stmt->rowCount() > 0) {
        echo "Migrando dados de cliente_email para email...\n";
        $conn->exec("UPDATE agendamentos SET email = cliente_email WHERE cliente_email IS NOT NULL AND (email IS NULL OR email = '')");
        echo "✓ Dados migrados\n";
    }
    
    $stmt = $conn->query("SHOW COLUMNS FROM agendamentos LIKE 'cliente_telefone'");
    if ($stmt->rowCount() > 0) {
        echo "Migrando dados de cliente_telefone para telefone...\n";
        $conn->exec("UPDATE agendamentos SET telefone = cliente_telefone WHERE cliente_telefone IS NOT NULL AND telefone IS NULL");
        echo "✓ Dados migrados\n";
    }
    
    // Alterar data_agendamento e hora_agendamento para permitir NULL
    echo "Alterando data_agendamento e hora_agendamento para permitir NULL...\n";
    try {
        $conn->exec("ALTER TABLE agendamentos MODIFY COLUMN `data_agendamento` DATE DEFAULT NULL COMMENT 'Data do agendamento (pode ser NULL)'");
        echo "✓ data_agendamento alterado\n";
    } catch (PDOException $e) {
        echo "⚠ data_agendamento: " . $e->getMessage() . "\n";
    }
    
    try {
        $conn->exec("ALTER TABLE agendamentos MODIFY COLUMN `hora_agendamento` TIME DEFAULT NULL COMMENT 'Hora do agendamento (pode ser NULL)'");
        echo "✓ hora_agendamento alterado\n";
    } catch (PDOException $e) {
        echo "⚠ hora_agendamento: " . $e->getMessage() . "\n";
    }
    
    // Adicionar índices
    echo "Adicionando índices...\n";
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_email ON agendamentos (email)");
        echo "✓ Índice idx_email criado\n";
    } catch (PDOException $e) {
        echo "⚠ idx_email: " . $e->getMessage() . "\n";
    }
    
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_regiao ON agendamentos (regiao)");
        echo "✓ Índice idx_regiao criado\n";
    } catch (PDOException $e) {
        echo "⚠ idx_regiao: " . $e->getMessage() . "\n";
    }
    
    // Corrigir tabela planos_vip
    echo "\nCorrigindo tabela planos_vip...\n";
    
    // Verificar se a coluna 'desconto_percentual' existe
    $stmt = $conn->query("SHOW COLUMNS FROM planos_vip LIKE 'desconto_percentual'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'desconto_percentual'...\n";
        $conn->exec("ALTER TABLE planos_vip ADD COLUMN `desconto_percentual` DECIMAL(5,2) DEFAULT 0 COMMENT 'Desconto percentual do plano' AFTER `preco`");
        echo "✓ Coluna 'desconto_percentual' adicionada\n";
    } else {
        echo "✓ Coluna 'desconto_percentual' já existe\n";
    }
    
    // Verificar se a coluna 'destaque' existe
    $stmt = $conn->query("SHOW COLUMNS FROM planos_vip LIKE 'destaque'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna 'destaque'...\n";
        $conn->exec("ALTER TABLE planos_vip ADD COLUMN `destaque` TINYINT(1) DEFAULT 0 COMMENT 'Se o plano deve ser destacado' AFTER `beneficios`");
        echo "✓ Coluna 'destaque' adicionada\n";
    } else {
        echo "✓ Coluna 'destaque' já existe\n";
    }
    
    // Adicionar índices em planos_vip
    echo "Adicionando índices em planos_vip...\n";
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_tipo ON planos_vip (tipo)");
        echo "✓ Índice idx_tipo criado\n";
    } catch (PDOException $e) {
        echo "⚠ idx_tipo: " . $e->getMessage() . "\n";
    }
    
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_destaque ON planos_vip (destaque)");
        echo "✓ Índice idx_destaque criado\n";
    } catch (PDOException $e) {
        echo "⚠ idx_destaque: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Migração concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro durante a migração: " . $e->getMessage() . "\n";
    exit(1);
}

