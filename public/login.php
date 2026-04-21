<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

if (getAuthUserId() > 0) {
    header('Location: ./index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Trade App PH</title>
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
    <div class="container auth-page">
        <section class="card auth-card">
            <h1>Login</h1>
            <p class="auth-help">Use your account to access your trade dashboard.</p>
            <form id="loginForm">
                <label for="loginEmail">Email</label>
                <input id="loginEmail" type="email" required>
                <label for="loginPassword">Password</label>
                <input id="loginPassword" type="password" required>
                <button type="submit">Login</button>
            </form>
            <p class="auth-help">No account yet? <a href="./register.php">Create account</a></p>
        </section>
    </div>
    <script>
        const AUTH_URL = "../api/auth.php";
        const form = document.getElementById("loginForm");

        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const payload = {
                email: document.getElementById("loginEmail").value.trim(),
                password: document.getElementById("loginPassword").value
            };

            const response = await fetch(`${AUTH_URL}?action=login`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                alert(data.error || "Login failed.");
                return;
            }
            window.location.href = "./index.php";
        });
    </script>
</body>
</html>
