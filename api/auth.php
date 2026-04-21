<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

function respondAuth(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function parseAuthJson(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        respondAuth(['error' => 'Invalid JSON body.'], 400);
    }
    return $decoded;
}

try {
    $pdo = getDbConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = trim((string)($_GET['action'] ?? ''));

    if ($method === 'GET' && $action === 'me') {
        $userId = getAuthUserId();
        if ($userId <= 0) {
            respondAuth(['authenticated' => false, 'user' => null]);
        }

        $stmt = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            session_destroy();
            respondAuth(['authenticated' => false, 'user' => null]);
        }

        respondAuth(['authenticated' => true, 'user' => $user]);
    }

    if ($method === 'POST' && $action === 'register') {
        $payload = parseAuthJson();
        $name = trim((string)($payload['name'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $password = (string)($payload['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            respondAuth(['error' => 'Name, email, and password are required.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respondAuth(['error' => 'Invalid email address.'], 422);
        }
        if (strlen($password) < 6) {
            respondAuth(['error' => 'Password must be at least 6 characters.'], 422);
        }

        $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $existsStmt->execute(['email' => $email]);
        if ($existsStmt->fetch()) {
            respondAuth(['error' => 'Email is already registered.'], 409);
        }

        $insertStmt = $pdo->prepare('
            INSERT INTO users (name, email, password_hash)
            VALUES (:name, :email, :password_hash)
        ');
        $insertStmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $_SESSION['user_id'] = (int)$pdo->lastInsertId();
        respondAuth(['message' => 'Registration successful.']);
    }

    if ($method === 'POST' && $action === 'login') {
        $payload = parseAuthJson();
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $password = (string)($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            respondAuth(['error' => 'Email and password are required.'], 422);
        }

        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            respondAuth(['error' => 'Invalid email or password.'], 401);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        respondAuth(['message' => 'Login successful.']);
    }

    if ($method === 'POST' && $action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        respondAuth(['message' => 'Logged out.']);
    }

    respondAuth(['error' => 'Not found.'], 404);
} catch (Throwable $error) {
    respondAuth(['error' => $error->getMessage()], 500);
}
