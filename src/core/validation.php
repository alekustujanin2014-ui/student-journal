<?php
// core/validation.php

function validate_required(string $value): bool
{
    return !empty(trim($value));
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_min_length(string $value, int $min): bool
{
    return mb_strlen(trim($value)) >= $min;
}

function validate_max_length(string $value, int $max): bool
{
    return mb_strlen(trim($value)) <= $max;
}

function validate_between_length(string $value, int $min, int $max): bool
{
    $len = mb_strlen(trim($value));
    return $len >= $min && $len <= $max;
}

function validate_password(string $password): array
{
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Пароль должен быть минимум 8 символов';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Пароль должен содержать заглавные буквы';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Пароль должен содержать строчные буквы';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Пароль должен содержать цифры';
    }
    
    return $errors;
}

function validate_match(string $value, string $pattern): bool
{
    return preg_match($pattern, $value) === 1;
}

function validate_in_array(mixed $value, array $array): bool
{
    return in_array($value, $array, true);
}

function validate_unique(PDO $pdo, string $table, string $column, mixed $value, $ignore_id = null): bool
{
    $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = :value";
    $params = [':value' => $value];
    
    if ($ignore_id !== null) {
        $sql .= " AND id != :ignore_id";
        $params[':ignore_id'] = $ignore_id;
    }
    
    $result = db_fetch_one($pdo, $sql, $params);
    return $result['count'] === 0;
}

function validate_data(array $data, array $rules): array
{
    $errors = [];
    
    foreach ($rules as $field => $field_rules) {
        $value = $data[$field] ?? null;
        $rules_list = explode('|', $field_rules);
        
        foreach ($rules_list as $rule) {
            $params = [];
            
            if (strpos($rule, ':') !== false) {
                list($rule, $param_str) = explode(':', $rule, 2);
                $params = explode(',', $param_str);
            }
            
            $validator = "validate_$rule";
            
            if (!function_exists($validator)) {
                continue;
            }
            
            $args = array_merge([$value], $params);
            $result = call_user_func_array($validator, $args);
            
            if (!$result) {
                $errors[$field][] = get_error_message($field, $rule, $params);
            }
        }
    }
    
    return $errors;
}

function get_error_message(string $field, string $rule, array $params = []): string
{
    $messages = [
        'required' => 'Поле обязательно для заполнения',
        'email' => 'Введите корректный email',
        'min_length' => 'Минимальная длина ' . ($params[0] ?? 'не указана') . ' символов',
        'max_length' => 'Максимальная длина ' . ($params[0] ?? 'не указана') . ' символов',
        'between_length' => 'Длина должна быть от ' . ($params[0] ?? '?') . ' до ' . ($params[1] ?? '?') . ' символов',
        'match' => 'Неверный формат поля',
        'in_array' => 'Недопустимое значение',
        'unique' => 'Такое значение уже существует'
    ];
    
    return $messages[$rule] ?? 'Ошибка валидации';
}

