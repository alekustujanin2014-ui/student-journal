<?php
// controllers/api_controller.php

function api_health(array $ctx): void
{
    header('Content-Type: application/json');
    
    echo json_encode([
        'status' => 'ok',
        'time' => date('Y-m-d H:i:s'),
        'database' => $ctx['db'] ? 'connected' : 'disconnected',
        'session' => session_is_logged_in($ctx['session'])
    ]);
}

function api_get_users(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $limit = (int) ($_GET['limit'] ?? 100);
    $offset = (int) ($_GET['offset'] ?? 0);
    
    $users = user_get_all($ctx['db'], $limit, $offset, $ctx['logger']);
    $total = user_count($ctx['db'], $ctx['logger']);
    
    echo json_encode([
        'data' => $users,
        'meta' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($users)
        ]
    ]);
}

function api_get_user(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $user = user_find_by_id($ctx['db'], $id, $ctx['logger']);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    
    unset($user['password_hash']);
    echo json_encode(['data' => $user]);
}

function api_get_faculties(array $ctx): void
{
    header('Content-Type: application/json');
    
    $university_id = (int)($_GET['university_id'] ?? 0);
    
    if (!$university_id) {
        echo json_encode(['success' => false, 'error' => 'University ID required']);
        return;
    }
    
    $faculties = get_faculties_by_university($ctx['db'], $university_id, $ctx['logger']);
    echo json_encode(['success' => true, 'data' => $faculties]);
}

/**
 * GET /api/groups?faculty_id=1
 */
function api_get_groups(array $ctx): void
{
    header('Content-Type: application/json');
    
    $faculty_id = (int)($_GET['faculty_id'] ?? 0);
    
    if (!$faculty_id) {
        echo json_encode(['success' => false, 'error' => 'Faculty ID required']);
        return;
    }
    
    $groups = get_groups_by_faculty($ctx['db'], $faculty_id, $ctx['logger']);
    echo json_encode(['success' => true, 'data' => $groups]);
}

function api_user_stats(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $user_id = session_get_user_id($ctx['session']);
    $stats = get_user_homework_stats($ctx['db'], $user_id, $ctx['logger']);
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function api_static_css(array $ctx, string $file): void
{
    $path = ROOT_PATH . "/templates/css/$file";
    
    if (file_exists($path)) {
        header('Content-Type: text/css');
        readfile($path);
    } else {
        dump('mama');
        handle_route_error($ctx, 404, 'GET', "/css/$file");
    }
}

function api_static_js(array $ctx, string $file): void
{
    $path = ROOT_PATH . "/templates/js/$file";
    
    if (file_exists($path)) {
        header('Content-Type: application/javascript');
        readfile($path);
    } else {
        handle_route_error($ctx, 404, 'GET', "/js/$file");
    }
}

function api_static_image(array $ctx, string $file): void
{
    $path = ROOT_PATH . "/templates/images/$file";
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    
    $mime_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml'
    ];
    
    if (file_exists($path) && isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
        readfile($path);
    } else {
        handle_route_error($ctx, 404, 'GET', "/images/$file");
    }
}


function api_news_list(array $ctx) : void 
{
    header('Content-Type: application/json');
    
    try {

        if (!session_is_logged_in($ctx['session'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        // Получаем университет пользователя из сессии
        $user_id = session_get_user_id($ctx['session']);
        $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);
        $news = get_news_by_university($ctx['db'], $user['university_id'], $ctx['logger']);
        
        if (empty($news)) {
            http_response_code(404);
            echo json_encode(['success' => true, 'error' => 'News not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $news
        ]);
        
    } catch (Exception $e) {
        log_error($ctx['logger'], "API get news error", ['error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
}

function api_news_details(array $ctx, int $id): void
{
    header('Content-Type: application/json');

    try {
        $news = get_news_by_id($ctx['db'], $id, $ctx['logger']);
        
        if (!$news) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'News not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $news
        ]);
        
    } catch (Exception $e) {
        log_error($ctx['logger'], "API get news error", ['error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
}
function api_schedule_week(array $ctx): void
{
    header('Content-Type: application/json');

    try {

        if (!session_is_logged_in($ctx['session'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }
        
        $offset = (int)($_GET['offset'] ?? 0);
        $week = get_week_by_offset($offset);
        
        $user_id = session_get_user_id($ctx['session']);
        $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);

        $schedule = get_schedule_for_group($ctx['db'], (int)$user['group_id'], $week['start_date'], $ctx['logger']);
    
        echo json_encode([
                'success' => true,
                'week_display' => $week['start'] . ' - ' . $week['end'],
                'offset' => $offset,
                'schedule' => $schedule,
        ]);

    } catch (Exception $e) {
        log_error($ctx['logger'], "API get chedule for week error", ['error' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
}

function api_exams_list(array $ctx): void
{
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    if (!session_is_logged_in($ctx['session'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $user_id = session_get_user_id($ctx['session']);
    $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);
    
    $exams = get_exams_by_group($ctx['db'], $user['group_id'], $ctx['logger']);

    if (!$exams) {
        echo json_encode(['success' => false, 'error' => 'Группа не найдена']);
        return;
    }

    echo json_encode([
        'success' => true,
        'exams' => $exams
    ]);
}

// function api_student_homework_list(array $ctx): void
// {
//     header('Content-Type: application/json');
    
//     if (!session_is_logged_in($ctx['session'])) {
//         echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//         return;
//     }
    
//     $user_id = session_get_user_id($ctx['session']);
//     $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);
    
//     $homework = get_homework_by_group($ctx['db'], (int)$user['group_id'], $ctx['logger']);
    
//     // Добавляем информацию о отправке для каждого задания
//     foreach ($homework as &$hw) {
//         $submission = get_submission_by_homework_and_user($ctx['db'], $hw['id'], $user_id, $ctx['logger']);
//         $hw['submission'] = $submission;
//     }
    
//     echo json_encode(['success' => true, 'data' => $homework]);
// }

function api_student_homework_list(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $user_id = session_get_user_id($ctx['session']);
    $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);
    $group_id = $user['group_id'];
    
    $search = $_GET['search'] ?? '';
    $limit = 100; // Получаем все задания
    $offset = 0;
    
    if (!empty($search)) {
        $homework = search_homework($ctx['db'], $search, $group_id, $ctx['logger'], $limit, $offset);
    } else {
        $homework = get_homework_by_group($ctx['db'], $group_id, $ctx['logger']);
    }
    
    // Добавляем информацию о отправке для каждого задания
    foreach ($homework as &$hw) {
        $submission = get_submission_by_homework_and_user($ctx['db'], $hw['id'], $user_id, $ctx['logger']);
        $hw['submission'] = $submission;
    }
    
    echo json_encode(['success' => true, 'data' => $homework, 'search' => $search]);
}

function api_student_homework_submit(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $user_id = session_get_user_id($ctx['session']);
    
    // Проверяем, существует ли задание
    $homework = get_homework_by_id($ctx['db'], $id, $ctx['logger']);
    if (!$homework) {
        echo json_encode(['success' => false, 'error' => 'Homework not found']);
        return;
    }
    
    // Проверяем дедлайн
    if ($homework['deadline'] && strtotime($homework['deadline']) < time()) {
        echo json_encode(['success' => false, 'error' => 'Дедлайн уже прошел']);
        return;
    }
    
    // Обработка загрузки файла
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Файл не загружен']);
        return;
    }
    
    $upload_dir = ROOT_PATH . '/uploads/homework/submissions/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    $filename = 'hw_' . $id . '_user_' . $user_id . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
        $comment = $_POST['comment'] ?? '';
        $result = submit_homework($ctx['db'], $id, $user_id, '/uploads/homework/submissions/' . $filename, $comment, $ctx['logger']);
        
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл']);
    }
}

function api_user_attendance_stats(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $user_id = session_get_user_id($ctx['session']);
    $stats = get_attendance_stats_by_user($ctx['db'], $user_id, $ctx['logger']);
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

function api_admin_stats(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    // Проверка прав администратора (опционально)
    $user = session_get($ctx['session'], 'user');
    $is_admin = ($user['role'] ?? 'user') === 'admin';
    
    echo json_encode([
        'success' => true,
        'total_users' => get_total_users($ctx['db'], $ctx['logger']),
        'is_admin' => $is_admin
    ]);
}

function api_admin_users(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)(($page - 1) * $limit);
    $search = $_GET['search'] ?? '';
    
    if ($search) {
        $users = search_users($ctx['db'], $search, $limit, $offset, $ctx['logger']);
        $total_users = count($users);
    } else {
        $users = get_all_users_paginated($ctx['db'], $limit, $offset, $ctx['logger']);
        $total_users = count($users);
    }
    
    $total_pages = ceil($total_users / $limit);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'offset' => $offset,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_users' => $total_users,
    ]);
}

function api_admin_user_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $data = [
        'name' => trim($input['name'] ?? ''),
        'email' => trim($input['email'] ?? ''),
        'university_id' => trim($input['university_id'] ?? ''),
        'faculty_id' => trim($input['faculty_id'] ?? ''),
        'group_id' => trim($input['group_id'] ?? ''),
        'phone' => trim($input['phone'] ?? ''),
        'city' => trim($input['city'] ?? '')
    ];
    
    if (!empty($input['password'])) {
        if (strlen($input['password']) < 6) {
            echo json_encode(['success' => false, 'error' => 'Пароль должен быть минимум 6 символов']);
            return;
        }
        $data['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    $result = update_user_by_admin($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}
function api_admin_user_create(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $data = [
        'name' => trim($input['name'] ?? ''),
        'email' => trim($input['email'] ?? ''),
        'password' => $input['password'] ?? '',
        'university_id' => trim($input['university_id'] ?? ''),
        'faculty_id' => trim($input['faculty_id'] ?? ''),
        'group_id' => trim($input['group_id'] ?? ''),
        'phone' => trim($input['phone'] ?? ''),
        'city' => trim($input['city'] ?? '')
    ];
    // Валидация
    if (empty($data['name'])) {
        echo json_encode(['success' => false, 'error' => 'Имя обязательно']);
        return;
    }
    
    if (empty($data['email'])) {
        echo json_encode(['success' => false, 'error' => 'Email обязателен']);
        return;
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Неверный email']);
        return;
    }
    
    if (empty($data['password'])) {
        echo json_encode(['success' => false, 'error' => 'Пароль обязателен']);
        return;
    }
    
    if (strlen($data['password']) < 6) {
        echo json_encode(['success' => false, 'error' => 'Пароль минимум 6 символов']);
        return;
    }
    
    if (user_exists($ctx['db'], $data['email'], $ctx['logger'])) {
        echo json_encode(['success' => false, 'error' => 'Email уже зарегистрирован']);
        return;
    }
    
    $user_id = create_user_by_admin($ctx['db'], $data, $ctx['logger']);
    
    if ($user_id) {
        echo json_encode(['success' => true, 'user_id' => $user_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при создании']);
    }
}

function api_admin_user_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $current_user = session_get($ctx['session'], 'user');
    
    // Нельзя удалить самого себя
    if ($current_user && isset($current_user['id']) && $id === $current_user['id']) {
        echo json_encode(['success' => false, 'error' => 'Нельзя удалить свою учетную запись']);
        return;
    }
                
    $result = user_delete($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_news_list(array $ctx): void
{
    header('Content-Type: application/json');
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $search = $_GET['search'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)(($page - 1) * $limit);
    $search = $_GET['search'] ?? '';
    $university_id = (int)($_GET['university_id'] ?? 0);

    if($search){
        $news = search_news($ctx['db'], $search, $university_id ?: null, $ctx['logger']);
        $total_users = count($news);
    }
    else {
        $news = get_all_news_admin($ctx['db'], $ctx['logger'], $university_id ?: null);
        $total_users = count($news);
    }
    
    echo json_encode(['success' => true, 'data' => $news, 'total_news' => $total_users]);
}

function api_admin_news_detail(array $ctx, int $id): void
{
    header('Content-Type: application/json');

    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $news = get_news_by_id_admin($ctx['db'], $id, $ctx['logger']);
    
    if (!$news) {
        echo json_encode(['success' => false, 'error' => 'News not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $news]);
}

function api_admin_news_create(array $ctx): void
{
    header('Content-Type: application/json');

    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['title']) || empty($data['content']) || empty($data['university_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $news_id = create_news($ctx['db'], $data, $ctx['logger']);
    
    if ($news_id) {
        echo json_encode(['success' => true, 'id' => $news_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create news']);
    }
}

function api_admin_news_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = update_news($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_news_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $result = delete_news($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_subjects_list(array $ctx): void
{
    header('Content-Type: application/json');

    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $search = $_GET['search'] ?? '';
    $subjects = get_all_subjects($ctx['db'], $ctx['logger']);
    
    if (!empty($search)) {
        $subjects = search_subjects($ctx['db'], $search, $ctx['logger']);
        $total_subjects = count($subjects);

    }else
        $total_subjects = count($subjects);
    
    echo json_encode(['success' => true, 'data' => $subjects, 'total_subjects' => $total_subjects]);
}

function api_admin_subjects_get(array $ctx, int $id): void
{
    header('Content-Type: application/json');

    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $subject = get_subject_by_id($ctx['db'], $id, $ctx['logger']);
    
    if (!$subject) {
        echo json_encode(['success' => false, 'error' => 'Subject not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $subject]);
}

function api_admin_subjects_create(array $ctx): void
{
    header('Content-Type: application/json');

    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name'])) {
        echo json_encode(['success' => false, 'error' => 'Name is required']);
        return;
    }
    
    $id = create_subject($ctx['db'], $data, $ctx['logger']);
    
    echo json_encode(['success' => $id !== null, 'id' => $id]);
}

function api_admin_subjects_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');

    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = update_subject($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_subjects_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');

    if (!session_is_logged_in($ctx['session'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $result = delete_subject($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_teachers_list(array $ctx): void
{
    header('Content-Type: application/json');
    
    $search = trim($_GET['search'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    if (!empty($search)) {
        $teachers = search_teachers($ctx['db'], $search, $ctx['logger'], $limit, $offset);
        $total = count_search_teachers($ctx['db'], $search, $ctx['logger']);
    } else {
        $teachers = get_all_teachers($ctx['db'], $ctx['logger'], $limit, $offset);
        $total = get_total_teachers_count($ctx['db'], $ctx['logger']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $teachers,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);
}

function api_admin_teachers_get(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $teacher = get_teacher_by_id($ctx['db'], $id, $ctx['logger']);
    
    if (!$teacher) {
        echo json_encode(['success' => false, 'error' => 'Teacher not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $teacher]);
}

function api_admin_teachers_create(array $ctx): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['last_name'])) {
        echo json_encode(['success' => false, 'error' => 'Last name are required']);
        return;
    }
    
    $id = create_teacher($ctx['db'], $data, $ctx['logger']);
    
    echo json_encode(['success' => $id !== null, 'id' => $id]);
}

function api_admin_teachers_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = update_teacher($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_teachers_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $result = delete_teacher($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_rooms_list(array $ctx): void
{
    header('Content-Type: application/json');
    
    $search = trim($_GET['search'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    if (!empty($search)) {
        $rooms = search_rooms($ctx['db'], $search, $ctx['logger'], $limit, $offset);
        $total = count_search_rooms($ctx['db'], $search, $ctx['logger']);
    } else {
        $rooms = get_all_rooms($ctx['db'], $ctx['logger']);
        $total = get_total_rooms_count($ctx['db'], $ctx['logger']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $rooms,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);
}

function api_admin_rooms_get(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $room = get_room_by_id($ctx['db'], $id, $ctx['logger']);
    
    if (!$room) {
        echo json_encode(['success' => false, 'error' => 'Room not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $room]);
}

function api_admin_rooms_create(array $ctx): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['number'])) {
        echo json_encode(['success' => false, 'error' => 'Room number is required']);
        return;
    }
    
    $id = create_room($ctx['db'], $data, $ctx['logger']);
    
    echo json_encode(['success' => $id !== null, 'id' => $id]);
}

function api_admin_rooms_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = update_room($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_rooms_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $result = delete_room($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_schedule_list(array $ctx, int $group_id): void
{
    header('Content-Type: application/json');
    
    $schedule = get_schedule_by_group($ctx['db'], $group_id, $ctx['logger']);
    
    echo json_encode(['success' => true, 'data' => $schedule]);
}

function api_admin_schedule_get(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $item = get_schedule_item_by_id($ctx['db'], $id, $ctx['logger']);
    
    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Schedule item not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $item]);
}

function api_admin_schedule_create(array $ctx): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['group_id']) || empty($data['subject_id']) || empty($data['day_of_week']) || empty($data['lesson_number'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $id = create_schedule_item($ctx['db'], $data, $ctx['logger']);
    
    echo json_encode(['success' => $id !== null, 'id' => $id]);
}

function api_admin_schedule_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = update_schedule_item($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_schedule_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $result = delete_schedule_item($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_schedule_groups(array $ctx): void
{
    header('Content-Type: application/json');
    
    $groups = get_schedule_groups($ctx['db'], $ctx['logger']);
    
    echo json_encode(['success' => true, 'data' => $groups]);
}

function api_admin_schedule_group_week(array $ctx, int $group_id): void
{
    header('Content-Type: application/json');
    
    $offset = (int)($_GET['offset'] ?? 0);

    $week = get_week_by_offset($offset);
    
    $schedule = get_schedule_for_group($ctx['db'], $group_id, $week['start_date'], $ctx['logger']);
    
    echo json_encode([
        'success' => true,
        'schedule' => $schedule,
        'week_start' => $week['start_date'],
        'week_end' => $week['end_date'],
        'week_display' => $week['start'] . ' - ' . $week['end'],
        'is_even' => $week['is_even'],
        'offset' => $offset
    ]);
}

function api_admin_exams_list(array $ctx): void
{
    header('Content-Type: application/json');
    
    $group_id = (int)($_GET['group_id'] ?? 0);
    $search = trim($_GET['search'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    if (!empty($search)) {
        $exams = search_exams($ctx['db'], $search, $group_id ?: null, $ctx['logger'], $limit, $offset);
        $total = count_search_exams($ctx['db'], $search, $group_id ?: null, $ctx['logger']);
    } else if ($group_id) {
        $exams = get_exams_by_group($ctx['db'], $group_id, $ctx['logger']);
        $total = count($exams);
    } else {
        $exams = get_all_exams($ctx['db'], $ctx['logger'], $limit, $offset);
        $total = get_exams_count($ctx['db'], $ctx['logger']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $exams,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit),
        'search' => $search
    ]);
}

function api_admin_exams_get(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $exam = get_exam_by_id($ctx['db'], $id, $ctx['logger']);
    
    if (!$exam) {
        echo json_encode(['success' => false, 'error' => 'Exam not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $exam]);
}

function api_admin_exams_create(array $ctx): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['group_id']) || empty($data['subject_id']) || empty($data['exam_date'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $id = create_exam($ctx['db'], $data, $ctx['logger']);
    
    echo json_encode(['success' => $id !== null, 'id' => $id]);
}

function api_admin_exams_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = update_exam($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_exams_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $result = delete_exam($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_homework_list(array $ctx): void
{
    header('Content-Type: application/json');
    
    $group_id = (int)($_GET['group_id'] ?? 0);
    $search = trim($_GET['search'] ?? '');
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    if (!empty($search)) {
        $homework = search_homework($ctx['db'], $search, $group_id ?: null, $ctx['logger'], $limit, $offset);
        $total = count_search_homework($ctx['db'], $search, $group_id ?: null, $ctx['logger']);
    } else if ($group_id) {
        $homework = get_homework_by_group($ctx['db'], $group_id, $ctx['logger']);
        $total = count($homework);
    } else {
        $homework = get_all_homework($ctx['db'], $ctx['logger'], $limit, $offset);
        $total = count($homework);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $homework,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit),
        'search' => $search
    ]);
}

function api_admin_homework_get(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $homework = get_homework_by_id($ctx['db'], $id, $ctx['logger']);
    
    if (!$homework) {
        echo json_encode(['success' => false, 'error' => 'Homework not found']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $homework]);
}

function api_admin_homework_create(array $ctx): void
{
    header('Content-Type: application/json');
    
    $data = [
        'group_id' => $_POST['group_id'] ?? 0,
        'subject_id' => $_POST['subject_id'] ?? 0,
        'teacher_id' => $_POST['teacher_id'] ?? null,
        'title' => $_POST['title'] ??  '',
        'description' => $_POST['description'] ?? null,
        'deadline' => $_POST['deadline'] ?? null,
        'max_score' => $_POST['max_score'] ?? 100
    ];
    
    if (empty($data['group_id']) || empty($data['subject_id']) || empty($data['title'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Обработка загрузки файла
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = ROOT_PATH . '/uploads/homework/tasks/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $filename = 'task_' . time() . '_' . uniqid() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
            $data['file_path'] = '/uploads/homework/tasks/' . $filename;
        }
    }
    
    $id = create_homework($ctx['db'], $data, $ctx['logger']);
    
    echo json_encode(['success' => $id !== null, 'id' => $id]);
}

function api_admin_homework_update(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    // Получаем текущее задание
    $current = get_homework_by_id($ctx['db'], $id, $ctx['logger']);
    
    $data = [
        'subject_id' => $_POST['subject_id'] ?? 0,
        'teacher_id' => $_POST['teacher_id'] ?? null,
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? null,
        'deadline' => $_POST['deadline'] ?? null,
        'max_score' => $_POST['max_score'] ?? 100
    ];
    
    // Обработка загрузки нового файла
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Удаляем старый файл
        if ($current && $current['file_path']) {
            $old_file = __DIR__ . '/../..' . $current['file_path'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        
        $upload_dir = __DIR__ . '/../../uploads/homework/tasks/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $filename = 'task_' . time() . '_' . uniqid() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
            $data['file_path'] = '/uploads/homework/tasks/' . $filename;
        }
    }
    
    // Если файл нужно удалить
    if (isset($_POST['remove_file']) && $_POST['remove_file'] == '1') {
        if ($current && $current['file_path']) {
            $old_file = __DIR__ . '/../..' . $current['file_path'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        $data['file_path'] = null;
    }
    
    $result = update_homework($ctx['db'], $id, $data, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_homework_delete(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $result = delete_homework($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

// Submissions
function api_admin_homework_submissions(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $submissions = get_submissions_by_homework($ctx['db'], $id, $ctx['logger']);
    
    echo json_encode(['success' => true, 'data' => $submissions]);
}

function api_admin_homework_grade(array $ctx, int $id): void
{
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = grade_submission($ctx['db'], $id, $data['score'], $data['status'], $data['teacher_comment'] ?? '', $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

// function api_admin_attendance_group_date(array $ctx): void
// {
//     header('Content-Type: application/json');
    

//     $group_id = (int)($_GET['group_id'] ?? 0);
//     $date = $_GET['date'] ?? date('Y-m-d');

//     if (!$group_id) {
//         log_info($ctx['logger'], 'Group ID is empty');
//         echo json_encode(['success' => false, 'error' => 'Group ID required']);
//         return;
//     }

//     $attendance = get_attendance_by_group_and_date($ctx['db'], $group_id, $date, $ctx['logger']);

//     echo json_encode([
//         'success' => true, 
//         'data' => $attendance]);

// }
function api_admin_attendance_group_date(array $ctx): void
{
    header('Content-Type: application/json');
    
    $group_id = (int)($_GET['group_id'] ?? 0);
    $date = $_GET['date'] ?? date('Y-m-d');
    
    log_info($ctx['logger'], 'Attendance API called', [
        'group_id' => $group_id,
        'date' => $date
    ]);
    
    if (!$group_id) {
        log_info($ctx['logger'], 'Group ID is empty');
        echo json_encode(['success' => false, 'error' => 'Group ID required']);
        return;
    }
    
    try {
        $attendance = get_attendance_by_group_and_date($ctx['db'], $group_id, $date, $ctx['logger']);
        
        echo json_encode([
            'success' => true, 
            'data' => $attendance
        ]);
    } catch (Exception $e) {
        log_error($ctx['logger'], 'Attendance API error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
}
function api_admin_attendance_save(array $ctx): void
{
    header('Content-Type: application/json');
    
    if (!session_is_logged_in($ctx['session']) || session_get($ctx['session'], 'user.role') !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $marked_by = session_get_user_id($ctx['session']);
    
    $result = bulk_set_attendance($ctx['db'], $data, $marked_by, $ctx['logger']);
    
    echo json_encode(['success' => $result]);
}

function api_admin_attendance_stats(array $ctx): void
{
    header('Content-Type: application/json');
    
    $group_id = (int)($_GET['group_id'] ?? 0);
    
    if (!$group_id) {
        echo json_encode(['success' => false, 'error' => 'Group ID required']);
        return;
    }
    
    $stats = get_group_attendance_stats($ctx['db'], $group_id, $ctx['logger']);
    
    echo json_encode(['success' => true, 'data' => $stats]);
}
