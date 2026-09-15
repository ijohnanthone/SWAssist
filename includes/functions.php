<?php

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Your form session expired. Please try again.');
    }
    // Rotate the token after successful use to prevent replay
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result|bool
{
    $statement = $conn->prepare($sql);
    if (!$statement) {
        error_log('SWAssist query preparation failed: ' . $conn->error);
        throw new RuntimeException('Database operation failed.');
    }
    if ($types !== '') {
        $statement->bind_param($types, ...$params);
    }
    if (!$statement->execute()) {
        error_log('SWAssist query execution failed: ' . $statement->error);
        throw new RuntimeException('Database operation failed.');
    }
    return $statement->get_result();
}

function can_access_case(mysqli $conn, int $caseId, int $userId, string $role): bool
{
    if ($role === 'admin') {
        return false;
    }
    if ($role === 'supervisor') {
        return (bool) query($conn, 'SELECT id FROM cases WHERE id = ?', 'i', [$caseId])->fetch_assoc();
    }
    return (bool) query($conn, 'SELECT id FROM cases WHERE id = ? AND assigned_student_id = ?', 'ii', [$caseId, $userId])->fetch_assoc();
}

function page_title(string $title): string
{
    return $title . ' | SWAssist';
}

function optional_decimal(mixed $value): ?string
{
    $value = trim((string) $value);
    return $value !== '' && is_numeric($value) ? $value : null;
}

function validate_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one digit.';
    }
    return null;
}