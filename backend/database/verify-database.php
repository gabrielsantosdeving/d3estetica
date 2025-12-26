<?php
/**
 * ============================================
 * VERIFICAÇÃO E ORGANIZAÇÃO DO BANCO DE DADOS
 * ============================================
 * 
 * Este script verifica e corrige a estrutura do banco de dados
 * Execute este arquivo uma vez após a instalação
 * 
 * @package D3Estetica
 * @file verify-database.php
 * @version 1.0
 */

require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verificação do Banco de Dados</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificação do Banco de Dados D3 Estética</h1>";

try {
    $conn = getDB();
    echo "<div class='success'>✓ Conexão com o banco de dados estabelecida com sucesso!</div>";
    
    // Lista de tabelas esperadas
    $tabelasEsperadas = [
        'usuarios',
        'clientes',
        'doutoras',
        'administradores',
        'agendamentos',
        'candidaturas',
        'promocoes',
        'servicos',
        'planos_vip',
        'vips',
        'anamneses',
        'tickets',
        'mensagens_chat',
        'blog_posts',
        'vagas',
        'pedidos'
    ];
    
    echo "<h2>Tabelas do Banco de Dados</h2>";
    echo "<table>";
    echo "<tr><th>Tabela</th><th>Status</th><th>Registros</th></tr>";
    
    $tabelasExistentes = [];
    $tabelasFaltando = [];
    
    foreach ($tabelasEsperadas as $tabela) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$tabela'");
            $existe = $stmt->rowCount() > 0;
            
            if ($existe) {
                $stmt = $conn->query("SELECT COUNT(*) as total FROM $tabela");
                $result = $stmt->fetch();
                $total = $result['total'];
                
                echo "<tr>";
                echo "<td><strong>$tabela</strong></td>";
                echo "<td><span style='color: green;'>✓ Existe</span></td>";
                echo "<td>$total registro(s)</td>";
                echo "</tr>";
                
                $tabelasExistentes[] = $tabela;
            } else {
                echo "<tr>";
                echo "<td><strong>$tabela</strong></td>";
                echo "<td><span style='color: red;'>✗ Não existe</span></td>";
                echo "<td>-</td>";
                echo "</tr>";
                
                $tabelasFaltando[] = $tabela;
            }
        } catch (Exception $e) {
            echo "<tr>";
            echo "<td><strong>$tabela</strong></td>";
            echo "<td><span style='color: red;'>✗ Erro: " . htmlspecialchars($e->getMessage()) . "</span></td>";
            echo "<td>-</td>";
            echo "</tr>";
            $tabelasFaltando[] = $tabela;
        }
    }
    
    echo "</table>";
    
    // Verificar estrutura de tabelas importantes
    echo "<h2>Verificação de Estrutura</h2>";
    
    // Verificar tabela agendamentos
    if (in_array('agendamentos', $tabelasExistentes)) {
        try {
            $stmt = $conn->query("DESCRIBE agendamentos");
            $campos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $camposEsperados = ['id', 'nome', 'telefone', 'email', 'regiao', 'status', 'observacoes', 'created_at', 'updated_at'];
            $camposFaltando = array_diff($camposEsperados, $campos);
            
            if (empty($camposFaltando)) {
                echo "<div class='success'>✓ Tabela 'agendamentos' está correta</div>";
            } else {
                echo "<div class='warning'>⚠ Tabela 'agendamentos' está faltando campos: " . implode(', ', $camposFaltando) . "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Erro ao verificar estrutura de 'agendamentos': " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // Verificar tabela servicos
    if (in_array('servicos', $tabelasExistentes)) {
        try {
            $stmt = $conn->query("DESCRIBE servicos");
            $campos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $camposEsperados = ['id', 'nome', 'categoria', 'descricao', 'preco', 'preco_original', 'imagem_path', 'status', 'vendidos', 'created_at', 'updated_at'];
            $camposFaltando = array_diff($camposEsperados, $campos);
            
            if (empty($camposFaltando)) {
                echo "<div class='success'>✓ Tabela 'servicos' está correta</div>";
            } else {
                echo "<div class='warning'>⚠ Tabela 'servicos' está faltando campos: " . implode(', ', $camposFaltando) . "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Erro ao verificar estrutura de 'servicos': " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // Verificar tabela candidaturas
    if (in_array('candidaturas', $tabelasExistentes)) {
        try {
            $stmt = $conn->query("DESCRIBE candidaturas");
            $campos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $camposEsperados = ['id', 'nome', 'email', 'telefone', 'vaga', 'mensagem', 'curriculo_path', 'status', 'created_at', 'updated_at'];
            $camposFaltando = array_diff($camposEsperados, $campos);
            
            if (empty($camposFaltando)) {
                echo "<div class='success'>✓ Tabela 'candidaturas' está correta</div>";
            } else {
                echo "<div class='warning'>⚠ Tabela 'candidaturas' está faltando campos: " . implode(', ', $camposFaltando) . "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Erro ao verificar estrutura de 'candidaturas': " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // Resumo
    echo "<h2>Resumo</h2>";
    $totalTabelas = count($tabelasEsperadas);
    $totalExistentes = count($tabelasExistentes);
    $totalFaltando = count($tabelasFaltando);
    
    if ($totalFaltando === 0) {
        echo "<div class='success'>✓ Todas as $totalTabelas tabelas estão presentes no banco de dados!</div>";
    } else {
        echo "<div class='warning'>⚠ $totalExistentes de $totalTabelas tabelas existem. $totalFaltando tabela(s) faltando.</div>";
        echo "<div class='info'>💡 Execute o script database.sql para criar as tabelas faltantes.</div>";
    }
    
    // Verificar dados iniciais
    echo "<h2>Dados Iniciais</h2>";
    
    // Verificar administrador padrão
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM administradores");
        $result = $stmt->fetch();
        if ($result['total'] > 0) {
            echo "<div class='success'>✓ Administrador(es) cadastrado(s): {$result['total']}</div>";
        } else {
            echo "<div class='warning'>⚠ Nenhum administrador cadastrado. Execute o script database.sql para criar o admin padrão.</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Erro ao verificar administradores: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Verificar serviços
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM servicos");
        $result = $stmt->fetch();
        echo "<div class='info'>📊 Serviços cadastrados: {$result['total']}</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Erro ao verificar serviços: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<div class='info' style='margin-top: 30px;'>
        <strong>Próximos passos:</strong><br>
        1. Se houver tabelas faltando, execute o script database.sql<br>
        2. Verifique as configurações em backend/config/config.php<br>
        3. Teste o sistema acessando o painel administrativo
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Erro ao conectar ao banco de dados: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>Verifique as configurações em backend/config/config.php</div>";
}

echo "</div></body></html>";
?>

