<?php
function generate_captcha(): array
{
    // Генерируем случайное число от 1000 до 9999
    $code = rand(1000, 9999);
    
    // Сохраняем в сессию
    $_SESSION['captcha_code'] = $code;
    
    return [
        'code' => $code,
        'image' => render_captcha_image($code)
    ];
}

function render_captcha_image(int $code): string
{
    // Разбираем число на цифры
    $digits = str_split((string)$code);
    
    // Создаем HTML с искаженными цифрами
    $html = '<div class="captcha-image">';
    foreach ($digits as $index => $digit) {
        $rotation = [-5, 3, -2, 4][$index % 4];
        $html .= "<span class='captcha-char' style='transform: rotate({$rotation}deg); display: inline-block;'>$digit</span>";
    }
    $html .= '</div>';
    
    return $html;
}

function verify_captcha(array $session, string $user_input): bool
{
    $stored = session_get($session, 'captcha_code');
    session_remove($session, 'captcha_code');
    
    return !empty($stored) && (string)$stored === (string)$user_input;
}