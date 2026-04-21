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
    <title>Register - Trade App PH</title>
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body>
    <div class="container auth-page">
        <section class="card auth-card">
            <h1>Create Account</h1>
            <p class="auth-help">Create an account first, then start tracking your trades.</p>
            <form id="registerForm">
                <label for="registerName">Name</label>
                <input id="registerName" type="text" required>
                <label for="registerEmail">Email</label>
                <input id="registerEmail" type="email" required>
                <label for="registerPassword">Password</label>
                <input id="registerPassword" type="password" minlength="6" required>
                <button type="submit">Register</button>
            </form>
            <p class="auth-help">Already have an account? <a href="./login.php">Login here</a></p>
        </section>
    </div>
    <script>
        const AUTH_URL = "../api/auth.php";
        const form = document.getElementById("registerForm");

        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const payload = {
                name: document.getElementById("registerName").value.trim(),
                email: document.getElementById("registerEmail").value.trim(),
                password: document.getElementById("registerPassword").value
            };

            const response = await fetch(`${AUTH_URL}?action=register`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                alert(data.error || "Registration failed.");
                return;
            }
            window.location.href = "./index.php";
        });
    </script>
</body>
</html>
