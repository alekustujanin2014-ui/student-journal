<?php
// core/helpers.php

function dd(mixed ...$vars): void
{
    foreach ($vars as $var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
    die();
}

function dump(mixed ...$vars): void
{
    foreach ($vars as $var) {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
    }
}

function redirect(string $url, int $status = 302): void
{
    if (!headers_sent()) {
        header("Location: $url", true, $status);
        exit();
    }
    
    echo "<script>window.location.href='$url';</script>";
    exit();
}

function base_url(string $path = ''): string
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host" . ($path ? '/' . ltrim($path, '/') : '');
}

function asset(string $path): string
{
    return base_url('public/' . ltrim($path, '/'));
}

function old(string $key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function csrf_field(): string
{
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
}

function str_slug(string $string): string
{
    $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

function str_random(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

function array_get(array $array, string $key, $default = null)
{
    $keys = explode('.', $key);
    $value = $array;
    
    foreach ($keys as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    
    return $value;
}
function array_set(array &$array, string $key, mixed $value): void
{
    $keys = explode('.', $key);
    $ref = &$array;
    
    foreach ($keys as $i => $segment) {
        if ($i === count($keys) - 1) {
            $ref[$segment] = $value;
        } else {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
    }
}
function array_has(array $array, string $key): bool
{
    return array_get($array, $key) !== null;
}
