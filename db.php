<?php

function env_value(string $key, string $default = ''): string
{
    // 1. Prioritize Azure environment variables
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }

    // 2. Fall back to local .env file (for local development)
    static $values;
    if ($values === null) {
        $values = [];
        $path = __DIR__ . '/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $values[trim($name)] = trim($value, " \t\"");
            }
        }
    }

    return $values[$key] ?? $default;
}

try {
    $conn = new mysqli(
        env_value('DB_HOST', 'localhost'),
        env_value('DB_USER', 'swassist_user'),
        env_value('DB_PASSWORD'),
        env_value('DB_NAME', 'swassist')
    );
} catch (mysqli_sql_exception $exception) {
    error_log('SWAssist database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The application is temporarily unavailable.');
}

$conn->set_charset('utf8mb4');