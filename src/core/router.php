<?php
// core/router.php

function create_router(array $logger, array $view_renderer, $db = null, &$session = null): array 
{
    return [
        'routes' => [],
        'logger' => $logger,
        'view_renderer' => $view_renderer,
        'db' => $db,
        'session' => $session
    ];
}

function add_route(array $router, string $method, string $path, mixed $handler): array 
{
    $router['routes'][] = [
        'method' => strtoupper($method),
        'path' => $path,
        'handler' => $handler,
        'pattern' => compile_route_pattern($path)
    ];
    
    return $router;
}

function compile_route_pattern(string $path): string 
{
    $pattern = preg_replace('/\{([\w]+)\}/', '(?P<$1>[^/]+)', $path);
    return '#^' . $pattern . '$#';
}

function match_route(array $route, string $uri, mixed &$params): bool 
{
    if (preg_match($route['pattern'], $uri, $matches)) {
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        return true;
    }
    return false;
}

function find_route(array $router, string $uri, mixed &$params): ?array 
{
    foreach ($router['routes'] as $route) {
        if (match_route($route, $uri, $params)) {
            return $route;
        }
    }
    return null;
}

function dispatch(array $router, string $uri, string $method): void 
{
    $parsed = parse_url($uri);
    $path = $parsed['path'] ?? '/';
    $method = strtoupper($method);
    
    // Ищем маршрут
    $found_route = null;
    $found_params = [];
    foreach ($router['routes'] as $route) {

        // Проверяем метод
        if ($route['method'] !== $method) {
            continue;
        }

        // Проверяем путь
        if ($route['path'] === $path) {
            $found_route = $route;
            break;
        }
 
        // Проверяем с параметрами
        if (preg_match($route['pattern'], $path, $matches)) {
            $found_route = $route;
            $found_params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            break;
        }
    }
    
    if (!$found_route) {
        http_response_code(404);

        if(function_exists('error_not_found'))
            error_not_found(create_controller_context($router));
        return;
    }
    // Вызываем обработчик
    $handler = $found_route['handler'];
    
    if (is_string($handler)) {
        $parts = explode('.', $handler, 2);
        if (count($parts) === 2) {
            $function = "{$parts[0]}_{$parts[1]}";
            if (function_exists($function)) {
                $context = [
                    'view_renderer' => $router['view_renderer'],
                    'logger' => $router['logger'],
                    'db' => $router['db'],
                    'session' => &$router['session']
                ];
                call_user_func_array($function, array_merge([$context], $found_params));
                return;
            }
        }
    } elseif (is_callable($handler)) {
        call_user_func_array($handler, $found_params);
        return;
    }
    
}
function execute_handler(array $router, mixed $handler, array $params): void 
{
    if (is_string($handler)) {
        $parts = explode('.', $handler, 2);
        
        if (count($parts) === 2) {
            $controller = $parts[0];
            $action = $parts[1];
            $function = "{$controller}_{$action}";
            
            if (function_exists($function)) {
                $context = create_controller_context($router);
                call_user_func_array($function, array_merge([$context], $params));
                return;
            }
        }
    } elseif (is_callable($handler)) {
        call_user_func_array($handler, array_merge([$router], $params));
        return;
    }
    
    log_error($router['logger'], "Invalid handler", ['handler' => $handler]);
    handle_route_error($router, 500, 'INTERNAL', '/');
}

function create_controller_context(array $router): array 
{
    return [
        'view_renderer' => $router['view_renderer'] ?? null,
        'logger' => $router['logger'] ?? null,
        'db' => $router['db'] ?? null,
        'session' => &$router['session']
    ];
}

function handle_route_error(array $router, int $code, string $method, string $path, string $allowed = ''): void 
{
    log_error($router['logger'], "Route error", [
        'code' => $code,
        'method' => $method,
        'path' => $path,
        'allowed' => $allowed
    ]);
    
    http_response_code($code);

    render_template($router['view_renderer'], 'errors/show.twig');
}

function debug_routes(array $router): void
{
    echo "<h2>Зарегистрированные маршруты:</h2>";
    echo "<pre>";
    foreach ($router['routes'] as $route) {
        echo "{$route['method']} {$route['path']} -> " . (is_string($route['handler']) ? $route['handler'] : 'closure') . "\n";
    }
    echo "</pre>";
}