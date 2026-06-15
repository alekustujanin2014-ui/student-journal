<?php
// controllers/auth_controller.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function auth_show_login_form(array $ctx): void
{
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

function auth_login(array $ctx): void
{
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $array_post = $_POST;
    
    // Валидация
    $errors = validate_data($_POST, [
        'email' => 'required|email',
        'password' => 'required'
    ]);
    
    if (!empty($errors)) {
        session_set_old_input($ctx['session'], $array_post);
        session_set($ctx['session'], 'errors', $errors);
        redirect('/login');
        return;
    }
    
    // Проверка пользователя
    $user = user_verify_password($ctx['db'], $email, $password, $ctx['logger']);
    
    if (!$user) {
        session_flash($ctx['session'], 'error', 'Неверный email или пароль');
        session_set_old_input($ctx['session'], $array_post);
        redirect('/login');
        return;
    }
    
    // Успешный вход
    session_set_user($ctx['session'], $user);
    user_update_last_login($ctx['db'], $user['id'], $ctx['logger']);

    log_info($ctx['logger'], "User logged in", ['user_id' => $user['id']]);

    if (session_get($ctx['session'], 'user.role') !== 'admin')
        redirect('/profile');
    else 
        redirect('/admin');
}

function auth_show_forgot_form(array $ctx): void
{
    $data = [
        'title' => 'Восстановление пароля',
        'flash' => session_get_flash($ctx['session']),
        'old' => session_get_old_input($ctx['session']),
        'csrf_token' => generate_csrf_token($ctx['session'])
    ];
    
    render_template($ctx['view_renderer'], 'auth/forgot.twig', $data);
}

function auth_forgot(array $ctx): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($ctx['session'], $token)) {
        session_flash($ctx['session'], 'error', 'Invalid CSRF token');
        redirect('/forgot');
        return;
    }
    
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        session_flash($ctx['session'], 'error', 'Введите email');
        redirect('/forgot');
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        session_flash($ctx['session'], 'error', 'Введите корректный email');
        redirect('/forgot');
        return;
    }
    
    $user = user_find_by_email($ctx['db'], $email, $ctx['logger']);
    
    if (!$user) {
        session_flash($ctx['session'], 'success', 'Если такой email существует, ссылка для сброса пароля будет отправлена');
        redirect('/forgot');
        return;
    }
    
    $reset_token = bin2hex(random_bytes(32));
    $token_hash = password_hash($reset_token, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Удаляем старую запись
    $stmt = $ctx['db']->prepare("DELETE FROM password_resets WHERE email = :email");
    $stmt->execute(['email' => $email]);
    
    // Вставляем новую
    $stmt = $ctx['db']->prepare("
        INSERT INTO password_resets (email, token, expires_at, created_at) 
        VALUES (:email, :token, :expires_at, NOW())
    ");
    
    $stmt->execute([
        'email' => $email,
        'token' => $token_hash,
        'expires_at' => $expires_at
    ]);
    
    $reset_link = base_url("/reset/{$reset_token}");
    
    // Отправляем email через PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Настройки сервера
        $mail->isSMTP();
        $mail->Host       = 'mailhog'; // или '127.0.0.1' если локально
        $mail->SMTPAuth   = false;
        $mail->Port       = 1025;
        
        // Отправитель и получатель
        $mail->setFrom('noreply@student-journal.ru', 'Студенческий журнал');
        $mail->addAddress($email, $user['name']);
        
        // Содержимое
        $mail->isHTML(true);
        $mail->Subject = 'Сброс пароля - Студенческий журнал';
        $mail->Body = "
            <html>
            <head>
                <title>Сброс пароля</title>
            </head>
            <body style='font-family: \"Courier New\", monospace; background: #f0e9db; padding: 40px;'>
                <div style='max-width: 600px; margin: 0 auto; background: #fff6e8; border: 3px solid #5e3a1c; padding: 30px;'>
                    <h2 style='color: #5e3a1c;'>Здравствуйте, {$user['name']}!</h2>
                    <p style='color: #5e3a1c;'>Вы запросили сброс пароля для учетной записи в Студенческом журнале.</p>
                    <p style='color: #5e3a1c;'>Для установки нового пароля перейдите по ссылке:</p>
                    <p style='margin: 20px 0;'>
                        <a href='{$reset_link}' style='display: inline-block; padding: 10px 20px; background: #8b6b4d; color: #fff6e8; text-decoration: none; border: 2px solid #5e3a1c;'>
                            Сбросить пароль
                        </a>
                    </p>
                    <p style='color: #8b6b4d; font-size: 0.9rem;'>Или скопируйте ссылку: <br><a href='{$reset_link}' style='color: #8b6b4d;'>{$reset_link}</a></p>
                    <p style='color: #8b6b4d; font-size: 0.9rem;'>Ссылка действительна в течение 1 часа.</p>
                    <hr style='border-color: #d4a373; margin: 20px 0;'>
                    <p style='color: #8b6b4d; font-size: 0.8rem;'>С уважением,<br>Редакция Студенческого журнала</p>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Здравствуйте, {$user['name']}!\n\nВы запросили сброс пароля.\n\nДля установки нового пароля перейдите по ссылке:\n{$reset_link}\n\nСсылка действительна в течение 1 часа.\n\nС уважением,\nРедакция Студенческого журнала";
        
        $mail->send();
        
        log_info($ctx['logger'], "Password reset email sent", ['email' => $email]);
        session_flash($ctx['session'], 'success', 'Ссылка для сброса пароля отправлена на ваш email');
        
    } catch (Exception $e) {
        log_error($ctx['logger'], "Mail error", ['error' => $mail->ErrorInfo]);
        session_flash($ctx['session'], 'error', 'Ошибка отправки email. Попробуйте позже.');
    }
    
    redirect('/forgot');
}
function auth_show_reset_form(array $ctx, string $token): void
{
    // Проверяем валидность токена
    $stmt = $ctx['db']->prepare("
        SELECT * FROM password_resets 
        WHERE expires_at > NOW() 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute();
    $reset = $stmt->fetch();
    
    $is_valid = false;
    if ($reset && password_verify($token, $reset['token'])) {
        $is_valid = true;
        session_set($ctx['session'], 'reset_email', $reset['email']);
    }
    
    if (!$is_valid) {
        session_flash($ctx['session'], 'error', 'Ссылка для сброса пароля недействительна или истекла');
        redirect('/forgot');
        return;
    }
    
    $data = [
        'title' => 'Сброс пароля',
        'token' => $token,
        'flash' => session_get_flash($ctx['session']),
        'csrf_token' => generate_csrf_token($ctx['session'])
    ];
    
    render_template($ctx['view_renderer'], 'auth/reset.twig', $data);
}
function auth_reset(array $ctx, string $token): void
{
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($ctx['session'], $csrf_token)) {
        session_flash($ctx['session'], 'error', 'Invalid CSRF token');
        redirect("/reset/{$token}");
        return;
    }
    
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Валидация
    $errors = [];
    
    if (empty($password)) {
        $errors['password'][] = 'Пароль обязателен для заполнения';
    } elseif (strlen($password) < 6) {
        $errors['password'][] = 'Пароль должен быть минимум 6 символов';
    }
    
    if ($password !== $password_confirm) {
        $errors['password_confirm'][] = 'Пароли не совпадают';
    }
    
    if (!empty($errors)) {
        session_set($ctx['session'], 'errors', $errors);
        redirect("/reset/{$token}");
        return;
    }
    
    // Проверяем токен
    $stmt = $ctx['db']->prepare("
        SELECT * FROM password_resets 
        WHERE expires_at > NOW() 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute();
    $reset = $stmt->fetch();
    
    if (!$reset || !password_verify($token, $reset['token'])) {
        session_flash($ctx['session'], 'error', 'Ссылка для сброса пароля недействительна или истекла');
        redirect('/forgot');
        return;
    }
    
    $email = $reset['email'];
    
    // Обновляем пароль
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $ctx['db']->prepare("UPDATE users SET password_hash = :password WHERE email = :email");
    $stmt->execute(['password' => $password_hash, 'email' => $email]);
    
    // Удаляем использованный токен
    $stmt = $ctx['db']->prepare("DELETE FROM password_resets WHERE email = :email");
    $stmt->execute(['email' => $email]);
    
    log_info($ctx['logger'], "Password reset successful", ['email' => $email]);
    
    session_flash($ctx['session'], 'success', 'Пароль успешно изменен. Теперь вы можете войти.');
    redirect('/login');
}
function auth_logout(array $ctx): void
{
    log_info($ctx['logger'], "User logged out", ['user_id' => session_get_user_id($ctx['session'])]);
    
    session_logout($ctx['session']);
    redirect('/login');
}