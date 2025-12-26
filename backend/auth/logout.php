<?php
/**
 * ============================================
 * LOGOUT - ADMINISTRADOR
 * ============================================
 * 
 * Encerra a sessão do administrador e redireciona para login.
 * 
 * @package D3Estetica
 * @file auth/logout.php
 * @version 1.0
 */

session_start();
session_destroy();

// Limpar cookie de "lembrar-me" se existir
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

header('Location: /backend/admin/login.html');
exit();
?>
