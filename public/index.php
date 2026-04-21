<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$target = getAuthUserId() > 0 ? './dashboard.php' : './login.php';
header('Location: ' . $target);
exit;
