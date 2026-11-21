<?php
declare(strict_types=1);

namespace App\Config;

class AppConfig
{
    private static array $env = [];

    public static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Strip quotes if present
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                self::$env[$key] = $val;
                $_ENV[$key] = $val;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$env[$key] = $value;
        $_ENV[$key] = $value;
    }

    public static function getDbDriver(): string
    {
        return strtolower((string) self::get('DB_CONNECTION', 'sqlite'));
    }

    public static function getGeminiApiKey(): ?string
    {
        $key = (string) self::get('GEMINI_API_KEY', '');
        return $key !== '' ? $key : null;
    }

    public static function getGeminiModel(): string
    {
        return (string) self::get('GEMINI_MODEL', 'gemini-1.5-flash');
    }
}
