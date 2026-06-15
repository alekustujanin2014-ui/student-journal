<?php
// core/config.php

function load_configuration(string $path): array
{

    if (!file_exists($path)) {
        throw new RuntimeException("Configuration file not found: $path");
    }

    $config = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Пропускаем комментарии
        if (strpos($line, '#') === 0 || strpos($line, ';') === 0) {
            continue;
        }
        
        // Парсим KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Убираем кавычки
            $value = trim($value, '"\'');
            
            // Преобразуем типы
            if (strtolower($value) === 'true') {
                $value = true;
            } elseif (strtolower($value) === 'false') {
                $value = false;
            } elseif (is_numeric($value)) {
                $value += 0;
            }
            
            $config[$key] = $value;
        }
    }
    
    return $config;
}

function config_get(string $path, string $key, $default = null)
{
    static $config = null;
    
    if ($config === null) {
        $config = load_configuration(ROOT_PATH . ($path !== '' ) ?  $path : '/config/app.conf');
    }
    
    return array_get($config, $key, $default);
}