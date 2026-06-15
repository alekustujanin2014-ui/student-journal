<?php
// core/session.php

function create_session(array $config = []): array
{
    $defaults = [
        'name' => 'app_session',
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    
    $config = array_merge($defaults, $config);
    
    session_set_cookie_params([
        'lifetime' => $config['lifetime'],
        'path' => $config['path'],
        'domain' => $config['domain'],
        'secure' => $config['secure'],
        'httponly' => $config['httponly'],
        'samesite' => $config['samesite']
    ]);
    
    session_name($config['name']);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return [
        'id' => session_id(),
        'name' => session_name(),
        'started' => true,
        'data' => &$_SESSION
    ];
}

function session_get(array &$session, string $key, $default = null)
{
    $keys = explode('.', $key);
    $data = $session['data'];
    
    foreach ($keys as $segment) {
        if (!is_array($data) || !array_key_exists($segment, $data)) {
            return $default;
        }
        $data = $data[$segment];
    }
    
    return $data;
}

function session_set(array &$session, string $key, mixed $value): void
{
    $keys = explode('.', $key);
    $ref = &$session['data'];
    
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

function session_has(array &$session, string $key): bool
{
    return session_get($session, $key) !== null;
}

function session_remove(array &$session, string $key): void
{
    $keys = explode('.', $key);
    $lastKey = array_pop($keys);
    $ref = &$session['data'];
    
    foreach ($keys as $segment) {
        if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
            return;
        }
        $ref = &$ref[$segment];
    }
    
    unset($ref[$lastKey]);
}

function session_all(array $session): array
{
    return $session['data'];
}

function session_clear(array &$session): void
{
    $session['data'] = [];
}

function session_regenerate(array &$session, bool $delete_old = true): void
{
    session_regenerate_id($delete_old);
    $session['id'] = session_id();
}

function session_destroy_session(array &$session): void
{
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
    
    session_destroy();
    $session['started'] = false;
    $session['data'] = [];
}

function session_flash(array &$session, string $key, $value = null)
{
    if ($value !== null) {
        session_set($session, "flash.$key", $value);
        session_set($session, "flash.old.$key", $value);
        return;
    }
    
    $result = session_get($session, "flash.$key");
    session_remove($session, "flash.$key");
    return $result;
}

function session_keep_flash(array &$session, array $keys = []): void
{
    $flash = session_get($session, 'flash.old', []);
    
    if (empty($keys)) {
        $keys = array_keys($flash);
    }
    
    foreach ($keys as $key) {
        if (isset($flash[$key])) {
            session_set($session, "flash.$key", $flash[$key]);
        }
    }
}

function session_get_flash(array &$session): array
{
    $flash = session_get($session, 'flash', []);
    // session_remove($session, 'flash');
    // session_remove($session, 'flash.old');
    return $flash;
}
function session_clear_flash(array &$session): void
{
    session_remove($session, 'flash');
    session_remove($session, 'flash.old');
}
function session_set_user(array &$session, array $user): void
{
    session_set($session, 'user.id', $user['id']);
    session_set($session, 'user.email', $user['email']);
    session_set($session, 'user.name', $user['name'] ?? '');
    session_set($session, 'user.role', $user['role'] ?? 'user');
    session_set($session, 'user.group_name', $user['group_name'] ?? '');

    session_set($session, 'user.logged_in', true);
    session_set($session, 'user.login_time', time());
}

function session_get_user(array $session): ?array
{
    return session_get($session, 'user');
}

function session_get_user_id(array $session): ?int
{
    return session_get($session, 'user.id');
}

function session_is_logged_in(array $session): bool
{
    return session_get($session, 'user.logged_in', false);
}

function session_logout(array &$session): void
{
    session_remove($session, 'user');
}

function session_set_old_input(array &$session, array $input): void
{
    session_set($session, 'old', $input);
}

function session_get_old_input(array &$session): array
{
    return session_get($session, 'old', []);
}

function generate_csrf_token(array &$session): string
{
    if (!session_has($session, 'csrf_token')) {
        $token = bin2hex(random_bytes(32));
        session_set($session, 'csrf_token', $token);
    }
    
    return session_get($session, 'csrf_token');
}

function verify_csrf_token(array &$session, string $token): bool
{
    $stored = session_get($session, 'csrf_token');
    return hash_equals($stored, $token);
}
