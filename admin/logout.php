<?php
require_once __DIR__ . '/../includes/functions.php';
session_check();
log_activity('logout', 'auth', 'Usuário saiu');
session_destroy();
header('Location: login.php');
exit;
