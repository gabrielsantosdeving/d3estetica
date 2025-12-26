<?php
/**
 * ============================================
 * PAINEL DA DOUTORA - VERIFICAÇÃO DE ACESSO
 * ============================================
 * 
 * Verifica se o usuário está autenticado como doutora.
 * Redireciona para login se não estiver autenticado.
 * 
 * @package D3Estetica
 * @file index.php
 * @version 1.0
 */

session_start();

// Verificar se está autenticado como doutora
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'doutora') {
    header('Location: /frontend/html/login.html?type=doutora');
    exit();
}

// Incluir o HTML do painel
include 'index.html';
?>

