<?php
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
unset($_SESSION['is_admin']);
header('Location: login.php');
exit;
