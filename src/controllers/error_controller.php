<?php
// controllers/error_controller.php

function error_not_found(array $ctx): void
{
    http_response_code(404);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['REQUEST_URI'];

    log_error($ctx['logger'], "404 Not Found", [
        'method' => $method,
        'path' => $path,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    $data = [
        'code' => 404,
        'title' => "404 - Страница не найдена",
        'title_page' => 'Ошибка',
        'custom_style_name' => 'errors',
        'custom_js_name' => 'errors',
        'icon' => 'fas fa-times-circle',
        'errors_server' => [
            'title' => 'Страница не найдена',
            'message' => 'К сожалению, страница, которую вы ищете, не существует или была перемещена.<br>
            Проверьте правильность введенного адреса.'
        ],
        'debug' => 'true',
        'user' => session_get_user($ctx['session']),
        'errors_details' => [$method, $path]
    ];

    if (template_exists($ctx['view_renderer'], 'errors/show.twig'))
        render_template($ctx['view_renderer'], 'errors/show.twig', $data);
    else
        echo error_default_page($data);
}

function error_forbidden(array $ctx): void
{
    http_response_code(403);
    
    log_error($ctx['logger'], "403 Forbidden", [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $data = [
        'title' => '403 - Доступ запрещен',
        'code' => 403,
        'message' => 'Доступ запрещен'
    ];
    
    if (template_exists($ctx['view_renderer'], 'errors/403.twig'))
        render_template($ctx['view_renderer'], 'errors/403.twig', $data);
}

function error_server_error(array|null $ctx, mixed $value = null): void 
{
    http_response_code(500);
    
    // Проверяем наличие логгера
    if (isset($ctx['logger']) && $ctx['logger']) {
        log_error($ctx['logger'], "500 Internal Server Error");
    } else {
        // Если логгера нет, просто пишем в error_log
        error_log("500 Internal Server Error");
    }

    $data = [
        'code' => 500,
        'title_page' => 'Ошибка',
        'errors_server' => [
            'title' => 'Ошибка сервера',
            'message' => 'В данный момент есть неполадки на стороне сервера'
        ],
        'show_details' => 'true',
        'errors_details' => ["Uncaught exception" . $value->getMessage()],
        
        'custom_style_name' => 'errors',
        'custom_js_name' => 'errors',
        'icon' => 'fas fa-times-circle',
    ];

    if (template_exists($ctx['view_renderer'], 'errors/show.twig'))
        render_template($ctx['view_renderer'], 'errors/show.twig', $data);
    else
        echo error_default_page($data);
}

function error_default_page(array $data): string
{
    return <<<HTML
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <title>{$data['code']} - {$data['title_page']}</title>
    </head>
    <body>
        <div class="error-container">
            
            <a href="/">На главную</a>

            <footer class="app-footer">
            <p>© Студенческий журнал. Все права защищены.</p>
            </footer>
        </div>
    </body>
    </html>
    HTML;
}

