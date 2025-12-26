<?php
/**
 * ============================================
 * CORREÇÃO DE CPF NA TABELA ADMINISTRADORES
 * ============================================
 * 
 * Este script garante que todos os CPFs na tabela administradores
 * estejam sem formatação (apenas números)
 * 
 * Execute este script uma vez se houver CPFs formatados no banco
 */

require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Correção de CPF - Administradores</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Correção de CPF - Administradores</h1>";

try {
    $conn = getDB();
    
    // Buscar todos os administradores
    $stmt = $conn->query("SELECT id, nome, email, cpf FROM administradores");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>Encontrados " . count($admins) . " administrador(es)</div>";
    
    $updated = 0;
    foreach ($admins as $admin) {
        $cpfOriginal = $admin['cpf'];
        $cpfLimpo = preg_replace('/\D/', '', $cpfOriginal);
        
        // Se o CPF estava formatado, atualizar
        if ($cpfOriginal !== $cpfLimpo && strlen($cpfLimpo) === 11) {
            $stmt = $conn->prepare("UPDATE administradores SET cpf = ? WHERE id = ?");
            $stmt->execute([$cpfLimpo, $admin['id']]);
            echo "<div class='success'>✓ CPF corrigido para {$admin['nome']} ({$admin['email']}): {$cpfOriginal} → {$cpfLimpo}</div>";
            $updated++;
        } else {
            echo "<div class='info'>- CPF já está correto para {$admin['nome']}: {$cpfOriginal}</div>";
        }
    }
    
    if ($updated > 0) {
        echo "<div class='success'><strong>Correção concluída! {$updated} registro(s) atualizado(s).</strong></div>";
    } else {
        echo "<div class='info'><strong>Nenhuma correção necessária. Todos os CPFs estão no formato correto.</strong></div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>

