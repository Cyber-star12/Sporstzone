<?php
require 'includes/auth.php';
setcookie('remember_token', '', time() - 3600, '/');
session_destroy();
header('Location: index.php');
exit;
?>