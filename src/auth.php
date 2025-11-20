<?php
declare(strict_types=1);

session_start();

function login(string $email, string $password): bool {
    $json = file_get_contents(__DIR__ . '/data.json');
    $users = json_decode($json, true);

    foreach ($users as $user) {
        if ($user['email'] === $email && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'user' // Default to user if role is missing
            ];
            return true;
        }
    }
    return false;
}

function logout(): void {
    session_unset();
    session_destroy();
}

function require_role(string $role): void {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        header('Location: /public/Login.php');
        exit;
    }
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function get_user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}
