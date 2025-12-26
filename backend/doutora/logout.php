<?php
/**
 * Logout da Doutora
 */
session_start();
session_destroy();
header('Location: /frontend/html/login.html?type=doutora');
exit();
?>

