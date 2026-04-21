<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function getAuthUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function requireAuthUserId(): int
{
    $userId = getAuthUserId();
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please log in first.']);
        exit;
    }
    return $userId;
}
