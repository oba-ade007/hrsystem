<?php
require_once '../src/middleware/AuthMiddleware.php';
AuthMiddleware::checkLogin();

// Logout action
session_destroy();
header('Location: login.php');
exit;
?>
