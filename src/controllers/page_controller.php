<?php
// controllers/page_controller.php

function page_show_login(array $ctx): void {

    if (session_is_logged_in($ctx['session'])) {
        redirect('/profile');
        return;
    }

    $data = [
        'title' => 'Вход',
        'flash' => session_get_flash($ctx['session']),
        'old' => session_get_old_input($ctx['session'])
    ];

    render_template($ctx['view_renderer'], 'auth/login.twig', $data);
}

function page_show_home(array $ctx): void {
    // Проверяем авторизацию
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }
    if(session_get($ctx['session'], 'user.role') === 'admin'){
        error_not_found($ctx);
        return;
    }
        
    $user_id = session_get_user_id($ctx['session']);
    $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);

    if (!$user) {
        session_logout($ctx['session']);
        session_flash($ctx['session'], 'error', 'Сессия истекла');
        redirect('/login');
        return;
    }
    // Получаем все новости по университету/колледжу
    $news = get_news_by_university($ctx['db'], $user['university_id'], $ctx['logger']);
    
    $data = [
        'title' => 'Главная',
        'page_title' => 'Главная страница',
        'custom_style_name' => 'home',
        'custom_js_name' => 'home',
        'icon' => 'fas fa-home',
        'active' => 'home',
        'user' => $user,
        'news' => $news,
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session'])
    ];

    render_template($ctx['view_renderer'], 'home/show.twig', $data);
}

function page_show_schedule(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if (session_get($ctx['session'], 'user.role') === 'admin') {
        error_not_found($ctx);
        return;
    }

    $user_id = session_get_user_id($ctx['session']);
    $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);

    // Получаем информацию о текущей неделе
    $current_week = get_current_week_info();
    
    // Получаем расписание для группы - используем $current_week['start_date']
    $schedule = get_schedule_for_group($ctx['db'], (int)$user['group_id'], $current_week['start_date'], $ctx['logger']);
    $exams = get_exams_by_group($ctx['db'], (int)$user['group_id'], $ctx['logger']);

    $data = [
        'title' => 'Расписание',
        'page_title' => 'Расписание занятий',
        'custom_style_name' => 'schedule',
        'custom_js_name' => 'schedule',
        'icon' => 'fas fa-calendar-alt',
        'active' => 'schedule',
        'user' => $user,
        'schedule' => $schedule,
        'exams' => $exams,
        'current_week' => $current_week,
        'flash' => session_get_flash($ctx['session'])
    ];
    
    render_template($ctx['view_renderer'], 'schedule/show.twig', $data);
}
function page_show_student_tasks(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if(session_get($ctx['session'], 'user.role') === 'admin'){
        error_not_found($ctx);
        return;
    }

    $user_id = session_get_user_id($ctx['session']);
    $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);
    
    $data = [
        'title' => 'Домашние задания',
        'page_title' => 'Домашние задания',
        'custom_style_name' => 'tasks',
        'custom_js_name' => 'tasks',
        'icon' => 'fas fa-tasks',
        'active' => 'tasks',
        'user' => $user,
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session'])
    ];
    
    render_template($ctx['view_renderer'], 'tasks/show.twig', $data);
}

function page_show_profile(array $ctx) : void {
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if(session_get($ctx['session'], 'user.role') === 'admin'){
        error_not_found($ctx);
        return;
    }

    $user_id = session_get_user_id($ctx['session']);
    $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);

    if (!$user) {
        session_logout($ctx['session']);
        session_flash($ctx['session'], 'error', 'Сессия истекла');
        redirect('/login');
        return;
    }

    $stats = get_user_homework_stats($ctx['db'], $user_id, $ctx['logger']);
    $attendance = get_attendance_stats_by_user($ctx['db'], $user_id, $ctx['logger']);
    // Обновляем сессию
    session_set_user($ctx['session'], $user);
    // Получаем интересы из сессии
    $data = [
        'title' => 'Мой профиль',
        'page_title' => 'Профиль пользователя',
        'custom_style_name' => 'profile',
        'icon' => 'fas fa-user',
        'active' => 'profile',
        'user' => $user,
        'stats' => $stats,
        'attendance' => $attendance,
        'flash' => session_get_flash($ctx['session'])
    ];
    
    render_template($ctx['view_renderer'], 'profile/show.twig', $data);
}

function page_show_admin_index(array $ctx) : void {
    
    if(!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin'){
        error_not_found($ctx);
        return;
    }

    $user_id = session_get_user_id($ctx['session']);
    $user = user_find_by_id($ctx['db'], $user_id, $ctx['logger']);

    if (!$user) {
        session_logout($ctx['session']);
        session_flash($ctx['session'], 'error', 'Сессия истекла');
        redirect('/login');
        return;
    }

    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $users = get_all_users_paginated($ctx['db'], $limit, $offset, $ctx['logger']);
    $total_users = get_total_users($ctx['db'], $ctx['logger']);
    $universities = get_all_universities($ctx['db'], $ctx['logger']);

    $total_pages = ceil($total_users / $limit);
        
    $data = [
        'page_title' => 'Управление пользователями',
        'users' => $users,
        'current_page' => $page,
        'universities' => $universities,
        'custom_js_name' => 'admin_index',
        'total_pages' => $total_pages,
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session'])
    ];

    render_template($ctx['view_renderer'], 'admin/index/show.twig', $data);

}

function page_show_admin_news(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }
    
    // Получаем список новостей для админки
    $news_list = get_all_news_admin($ctx['db'], $ctx['logger']);
    
    // Получаем список всех университетов для фильтра и выбора
    $universities = get_all_universities($ctx['db'], $ctx['logger']);
    
    // Получаем статистику по новостям
    $total_news = get_news_count($ctx['db'], null, true, $ctx['logger']);
    // $published_news = count(array_filter($news_list, fn($n) => $n['published'] == 1));
    // $total_views = array_sum(array_column($news_list, 'views'));
    
    $data = [
        'page_title' => 'Управление новостями',
        'page_icon' => 'fas fa-newspaper',
        'active' => 'admin_news',
        'custom_js_name' => 'admin_news',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        
        // Данные для таблицы
        'news_list' => $news_list,
        'universities' => $universities,
        
        // Статистика
        'total_news' => $total_news,
        // 'published_news' => $published_news,
        // 'total_views' => $total_views,
        // 'unpublished_news' => $total_news - $published_news
    ];

    render_template($ctx['view_renderer'], 'admin/news/show.twig', $data);
}

function page_show_admin_subjects(array $ctx) : void {
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }
    $subjects = get_all_subjects($ctx['db'], $ctx['logger']);
    $total_subjects = count($subjects);

    $data = [
        'page_title' => 'Управление предметами',
        'page_icon' => 'fas fa-newspaper',
        'custom_js_name' => 'admin_subjects',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        
        // Данные для таблицы
        'subjects' => $subjects,
        'total_subjects' => $total_subjects
    ];

    render_template($ctx['view_renderer'], 'admin/subjects/show.twig', $data);
}

function page_show_admin_teachers(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }
    
    $data = [
        'page_title' => 'Управление преподавателями',
        'custom_js_name' => 'admin_teachers',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        'teachers' => get_all_teachers($ctx['db'], $ctx['logger'], 20, 0),
        'total_teachers' => get_total_teachers_count($ctx['db'], $ctx['logger'])
    ];

    render_template($ctx['view_renderer'], 'admin/teachers/show.twig', $data);
}

function page_show_admin_rooms(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }
    
    $data = [
        'page_title' => 'Управление аудиториями',
        'custom_js_name' => 'admin_rooms',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        'rooms' => get_all_rooms($ctx['db'], $ctx['logger']),
        'total_rooms' => get_total_rooms_count($ctx['db'], $ctx['logger'])
    ];
    
    render_template($ctx['view_renderer'], 'admin/rooms/show.twig', $data);
}


function page_show_admin_schedule(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }

    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }

    $data = [
        'page_title' => 'Управление расписанием',
        'custom_js_name' => 'admin_schedule',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        'groups' => get_schedule_groups($ctx['db'], $ctx['logger']),
        'subjects' => get_all_subjects($ctx['db'], $ctx['logger']),
        'teachers' => get_all_teachers($ctx['db'], $ctx['logger']),
        'rooms' => get_all_rooms($ctx['db'], $ctx['logger'])
    ];
    
    render_template($ctx['view_renderer'], 'admin/schedule/show.twig', $data);
}

function page_show_admin_exams(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }
    
    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }

    $data = [
        'page_title' => 'Управление экзаменами',
        'custom_js_name' => 'admin_exams',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        'groups' => get_schedule_groups($ctx['db'], $ctx['logger']),
        'subjects' => get_all_subjects($ctx['db'], $ctx['logger']),
        'teachers' => get_all_teachers($ctx['db'], $ctx['logger']),
        'rooms' => get_all_rooms($ctx['db'], $ctx['logger']),
        'exams' => get_all_exams($ctx['db'], $ctx['logger'])
    ];
    
    render_template($ctx['view_renderer'], 'admin/exam/show.twig', $data);
}

function page_show_admin_homework(array $ctx): void
{
    if (!session_is_logged_in($ctx['session'])) {
        session_flash($ctx['session'], 'error', 'Пожалуйста, войдите в систему');
        redirect('/login');
        return;
    }
    
    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }
    
    $data = [
        'page_title' => 'Управление домашними заданиями',
        'custom_js_name' => 'admin_homework',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        'groups' => get_schedule_groups($ctx['db'], $ctx['logger']),
        'subjects' => get_all_subjects($ctx['db'], $ctx['logger']),
        'teachers' => get_all_teachers($ctx['db'], $ctx['logger'])
    ];
    
    render_template($ctx['view_renderer'], 'admin/homework/show.twig', $data);
}

function page_show_admin_attendance(array $ctx): void
{
    if (!session_is_logged_in($ctx['session']) || session_get($ctx['session'], 'user.role') !== 'admin') {
        redirect('/login');
        return;
    }
    
    if (session_is_logged_in($ctx['session']) && session_get($ctx['session'], 'user.role') !== 'admin') {
        error_not_found($ctx);
        return;
    }
    
    $data = [
        'page_title' => 'Управление посещаемостью',
        'custom_js_name' => 'admin_attendance',
        'csrf_token' => generate_csrf_token($ctx['session']),
        'flash' => session_get_flash($ctx['session']),
        'groups' => get_schedule_groups($ctx['db'], $ctx['logger'])
    ];
    
    render_template($ctx['view_renderer'], 'admin/attendance/show.twig', $data);
}