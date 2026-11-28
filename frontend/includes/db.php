<?php
function getDatabaseSetting(string $name): string
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    $envFile = dirname(__DIR__, 2) . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $matches)
                && $matches[1] === $name) {
                $value = $matches[2];
                if (strlen($value) >= 2
                    && (($value[0] === '"' && str_ends_with($value, '"'))
                        || ($value[0] === "'" && str_ends_with($value, "'")))) {
                    return substr($value, 1, -1);
                }

                return preg_replace('/\s+#.*$/', '', $value);
            }
        }
    }

    throw new RuntimeException("Missing {$name}; set it in the repository .env file or process environment.");
}

// Opens a connection to the database and hands it back.
function getDb(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getDatabaseSetting('MYSQL_HOST'),
        getDatabaseSetting('MYSQL_PORT'),
        getDatabaseSetting('MYSQL_DATABASE')
    );

    return new PDO($dsn, getDatabaseSetting('MYSQL_USER'), getDatabaseSetting('MYSQL_PASSWORD'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
