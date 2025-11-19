<?php
require_once __DIR__ . '/../config.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string $role): bool
{
    $user = current_user();
    return $user && $user['role'] === $role;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('Realize o login para continuar.', 'warning');
        redirect('index.php');
    }
}

function require_roles(array $roles): void
{
    require_login();

    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        flash('Você não possui permissao para acessar este recurso.', 'danger');
        redirect('dashboard.php');
    }
}

function login(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT id, name, email, password_hash, role, photo_path, signature_name, signature_title, signature_path FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'photo_path' => $user['photo_path'] ?? null,
            'signature_name' => $user['signature_name'] ?? null,
            'signature_title' => $user['signature_title'] ?? null,
            'signature_path' => $user['signature_path'] ?? null,
        ];
        return true;
    }

    return false;
}

function logout(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}
