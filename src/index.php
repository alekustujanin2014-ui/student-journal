<?php
// index.php - Главный входной файл

// ============================================
// 1. Настройка окружения
// ============================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('ROOT_PATH', __DIR__);
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// ============================================
// 2. Автозагрузка и подключение файлов
// ============================================
require_once ROOT_PATH . '/vendor/autoload.php';

// Ядро системы
require_once ROOT_PATH . '/core/helpers.php';
require_once ROOT_PATH . '/core/logger.php';
require_once ROOT_PATH . '/core/session.php';
require_once ROOT_PATH . '/core/database.php';
require_once ROOT_PATH . '/core/router.php';
require_once ROOT_PATH . '/core/view.php';
require_once ROOT_PATH . '/core/validation.php';
require_once ROOT_PATH . '/core/config.php';
require_once ROOT_PATH . '/core/captcha.php';

// Репозитории
require_once ROOT_PATH . '/repositories/user_repository.php';
require_once ROOT_PATH . '/repositories/news_repository.php';
require_once ROOT_PATH . '/repositories/schedule_repository.php';
require_once ROOT_PATH . '/repositories/week_repository.php';
require_once ROOT_PATH . '/repositories/help_repository.php';
require_once ROOT_PATH . '/repositories/subjects_repository.php';
require_once ROOT_PATH . '/repositories/teachers_repository.php';
require_once ROOT_PATH . '/repositories/rooms_repository.php';
require_once ROOT_PATH . '/repositories/exam_repository.php';
require_once ROOT_PATH . '/repositories/homework_repository.php';
require_once ROOT_PATH . '/repositories/attendance_repository.php';

// Контроллеры (функции)
require_once ROOT_PATH . '/controllers/auth_controller.php';
require_once ROOT_PATH . '/controllers/error_controller.php';
require_once ROOT_PATH . '/controllers/api_controller.php';
require_once ROOT_PATH . '/controllers/page_controller.php';


// ============================================
// 3. Загрузка конфигурации
// ============================================
$config = load_configuration(ROOT_PATH . '/config/app.conf');
$db_config = load_configuration(ROOT_PATH . '/config/database.conf');

// ============================================
// 4. Инициализация компонентов
// ============================================

// Логгер
$logger = create_logger(
    ROOT_PATH . '/logs',
    $config['log_level'] ?? 'INFO',
    $config['log_max_files'] ?? 30
);
log_info($logger, 'Application started', [
    'env' => APP_ENV,
    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
]);

// Сессия
$session = create_session([
    'name' => $config['session_name'] ?? 'app_session',
    'lifetime' => ($config['session_lifetime'] ?? 2) * 3600,
    'secure' => APP_ENV === 'production',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// База данных
try {
    $db = create_database_connection($db_config, $logger);
    log_info($logger, 'Database connected');
} catch (Exception $e) {
    log_error($logger, 'Database connection failed', ['error' => $e->getMessage()]);
    $db = null;
    error_server_error(null, $e);
}

// View рендерер
$view_renderer = create_view_renderer(
    ROOT_PATH . '/templates',
    [
        'cache' => ROOT_PATH . '/cache/twig',
        'debug' => APP_ENV === 'development',
        'auto_reload' => APP_ENV === 'development'
    ]
);

// Добавляем глобальные переменные для шаблонов
add_global($view_renderer, 'app_name', $config['app_name'] ?? 'My App');
add_global($view_renderer, 'app_version', $config['version'] ?? '1.0.0');
add_global($view_renderer, 'current_year', date('Y'));
add_global($view_renderer, 'is_production', APP_ENV === 'production');

// ============================================
// 5. CSRF защита
// ============================================
$csrf_token = generate_csrf_token($session);
add_global($view_renderer, 'csrf_token', $csrf_token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($session, $token)) {
        log_error($logger, 'CSRF validation failed');
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

// ============================================
// 6. Создание роутера и регистрация маршрутов
// ============================================
$router = create_router($logger, $view_renderer, $db, $session);

// Главная
$router = add_route($router, 'GET', '/', 'page.show_home');

// Расписание
$router = add_route($router, 'GET', '/schedule', 'page.show_schedule');

// Авторизация
$router = add_route($router, 'GET', '/login', 'page.show_login'); 
$router = add_route($router, 'POST', '/login', 'auth.login');
$router = add_route($router, 'GET', '/logout', 'auth.logout');

// Восстановление пароля
$router = add_route($router, 'GET', '/forgot', 'auth.show_forgot_form');
$router = add_route($router, 'POST', '/forgot', 'auth.forgot');
$router = add_route($router, 'GET', '/reset/{token}', 'auth.show_reset_form');
$router = add_route($router, 'POST', '/reset/{token}', 'auth.reset');
// Домашние задания 
$router = add_route($router, 'GET', '/tasks', 'page.show_student_tasks');
// Профиль
$router = add_route($router, 'GET', '/profile', 'page.show_profile');

$router = add_route($router, 'GET', '/profile/edit', 'profile.edit_form');
$router = add_route($router, 'POST', '/profile/edit', 'profile.update');

// API маршруты

// Откладка
$router = add_route($router, 'GET', '/api/health', 'api.health');
$router = add_route($router, 'GET', '/api/users', 'api.get_users');
$router = add_route($router, 'GET', '/api/user/{id}', 'api.get_user');


// Задания
$router = add_route($router, 'POST', '/api/task/{id}', 'api.task_complete');

// Расписание
$router = add_route($router, 'GET', '/api/schedule/week', 'api.schedule_week');

// Новости
$router = add_route($router, 'GET', '/api/news/latest', 'api.news_list');
$router = add_route($router, 'GET', '/api/news/{id}', 'api.news_details');

// Экзамены
$router = add_route($router, 'GET', '/api/exams/list', 'api.exams_list');

// ДЗ
$router = add_route($router, 'GET', '/api/homework/list', 'api.student_homework_list');
$router = add_route($router, 'POST', '/api/homework/{id}/submit', 'api.student_homework_submit');

// Профиль
$router = add_route($router, 'GET', '/api/user/stats', 'api_user_stats');
$router = add_route($router, 'GET', '/api/user/attendance/stats', 'api.user_attendance_stats');

// Админка
$router = add_route($router, 'GET', '/api/admin/stats', 'api.admin_stats');
$router = add_route($router, 'GET', '/api/admin/users', 'api.admin_users');
$router = add_route($router, 'POST', '/api/admin/user/create', 'api.admin_user_create');
$router = add_route($router, 'POST', '/api/admin/user/{id}/update', 'api.admin_user_update');
$router = add_route($router, 'POST', '/api/admin/user/{id}/delete', 'api.admin_user_delete');
$router = add_route($router, 'POST', '/api/admin/user/{id}/delete', 'api.admin_user_delete');

// Админка новостей
$router = add_route($router, 'GET', '/api/admin/news', 'api.admin_news_list');
$router = add_route($router, 'GET', '/api/admin/news/{id}', 'api.admin_news_detail');
$router = add_route($router, 'POST', '/api/admin/news/create', 'api.admin_news_create');
$router = add_route($router, 'POST', '/api/admin/news/{id}/update', 'api.admin_news_update');
$router = add_route($router, 'POST', '/api/admin/news/{id}/delete', 'api.admin_news_delete');

$router = add_route($router, 'GET', '/api/faculties', 'api.get_faculties');
$router = add_route($router, 'GET', '/api/groups', 'api.get_groups');

// Предметы
$router = add_route($router, 'GET', '/api/admin/subjects', 'api.admin_subjects_list');
$router = add_route($router, 'GET', '/api/admin/subjects/{id}', 'api.admin_subjects_get');
$router = add_route($router, 'POST', '/api/admin/subjects/create', 'api.admin_subjects_create');
$router = add_route($router, 'POST', '/api/admin/subjects/{id}/update', 'api.admin_subjects_update');
$router = add_route($router, 'POST', '/api/admin/subjects/{id}/delete', 'api.admin_subjects_delete');

// Преподаватели
$router = add_route($router, 'GET', '/api/admin/teachers', 'api.admin_teachers_list');
$router = add_route($router, 'GET', '/api/admin/teachers/{id}', 'api.admin_teachers_get');
$router = add_route($router, 'POST', '/api/admin/teachers/create', 'api.admin_teachers_create');
$router = add_route($router, 'POST', '/api/admin/teachers/{id}/update', 'api.admin_teachers_update');
$router = add_route($router, 'POST', '/api/admin/teachers/{id}/delete', 'api.admin_teachers_delete');

// Аудитории
$router = add_route($router, 'GET', '/api/admin/rooms', 'api.admin_rooms_list');
$router = add_route($router, 'GET', '/api/admin/rooms/{id}', 'api.admin_rooms_get');
$router = add_route($router, 'POST', '/api/admin/rooms/create', 'api.admin_rooms_create');
$router = add_route($router, 'POST', '/api/admin/rooms/{id}/update', 'api.admin_rooms_update');
$router = add_route($router, 'POST', '/api/admin/rooms/{id}/delete', 'api.admin_rooms_delete');

// Расписание
$router = add_route($router, 'GET', '/api/admin/schedule/{group_id}/list', 'api.admin_schedule_list');
$router = add_route($router, 'GET', '/api/admin/schedule/{id}', 'api.admin_schedule_get');
$router = add_route($router, 'POST', '/api/admin/schedule/create', 'api.admin_schedule_create');
$router = add_route($router, 'POST', '/api/admin/schedule/{id}/update', 'api.admin_schedule_update');
$router = add_route($router, 'POST', '/api/admin/schedule/{id}/delete', 'api.admin_schedule_delete');
$router = add_route($router, 'GET', '/api/admin/schedule/groups', 'api.admin_schedule_groups');
$router = add_route($router, 'GET', '/api/admin/schedule/groups/{group_id}/week', 'api.admin_schedule_group_week');

// Экзамены
$router = add_route($router, 'GET', '/api/admin/exams', 'api.admin_exams_list');
$router = add_route($router, 'GET', '/api/admin/exams/{id}', 'api.admin_exams_get');
$router = add_route($router, 'POST', '/api/admin/exams/create', 'api.admin_exams_create');
$router = add_route($router, 'POST', '/api/admin/exams/{id}/update', 'api.admin_exams_update');
$router = add_route($router, 'POST', '/api/admin/exams/{id}/delete', 'api.admin_exams_delete');

// Админка домашних заданий
$router = add_route($router, 'GET', '/api/admin/homework', 'api.admin_homework_list');
$router = add_route($router, 'GET', '/api/admin/homework/{id}', 'api.admin_homework_get');
$router = add_route($router, 'POST', '/api/admin/homework/create', 'api.admin_homework_create');
$router = add_route($router, 'POST', '/api/admin/homework/{id}/update', 'api.admin_homework_update');
$router = add_route($router, 'POST', '/api/admin/homework/{id}/delete', 'api.admin_homework_delete');
$router = add_route($router, 'GET', '/api/admin/homework/{id}/submissions', 'api.admin_homework_submissions');
$router = add_route($router, 'POST', '/api/admin/homework/{id}/grade', 'api.admin_homework_grade');

// Посещаемость
$router = add_route($router, 'POST', '/api/admin/attendance/save', 'api.admin_attendance_save');
$router = add_route($router, 'GET', '/api/admin/attendance/group', 'api.admin_attendance_group_date');
$router = add_route($router, 'GET', '/api/admin/attendance/stats', 'api.admin_attendance_stats');

// Статические файлы
$router = add_route($router, 'GET', '/css/{file}', 'api.static_css');
$router = add_route($router, 'GET', '/js/{file}', 'api.static_js');
$router = add_route($router, 'GET', '/images/{file}', 'api.static_image');

// Админ-панель
$router = add_route($router, 'GET', '/admin', 'page.show_admin_index');
$router = add_route($router, 'GET', '/admin/news', 'page.show_admin_news');
$router = add_route($router, 'GET', '/admin/subjects', 'page.show_admin_subjects');
$router = add_route($router, 'GET', '/admin/teachers', 'page.show_admin_teachers');
$router = add_route($router, 'GET', '/admin/rooms', 'page.show_admin_rooms');
$router = add_route($router, 'GET', '/admin/schedule', 'page.show_admin_schedule');
$router = add_route($router, 'GET', '/admin/exams', 'page.show_admin_exams');
$router = add_route($router, 'GET', '/admin/homeworks', 'page.show_admin_homework');
$router = add_route($router, 'GET', '/admin/attendance', 'page.show_admin_attendance');

// Список маршрутов 
$router = add_route($router, 'GET', '/routes', function() use ($router) {
    debug_routes($router);
});


// ============================================
// 7. Запуск приложения
// ============================================
try {
    dispatch($router, $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
} catch (Exception $e) {
    log_error($logger, 'Uncaught exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    http_response_code(500);

    if (function_exists('error_server_error')) {

        error_server_error([
        'view_renderer' => $view_renderer,
        'logger' => $logger,
        'db' =>  $db,
        'session' => &$session
        ], $e);

    } else {
        echo "500 Internal Server Error";
    }
}

// ============================================
// 8. Завершение
// ============================================
session_keep_flash($session);
log_info($logger, 'Request completed', [
    'memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
    'time' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2) . ' ms'
]);

session_clear_flash($session);