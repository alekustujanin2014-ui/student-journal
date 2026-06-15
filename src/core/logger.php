<?php
// core/logger.php

const LOG_LEVELS = [
    'DEBUG' => 0,
    'INFO' => 1,
    'WARNING' => 2,
    'ERROR' => 3,
    'CRITICAL' => 4
];

function create_logger(string $log_dir, string $level = 'INFO', int $max_files = 30): array
{
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $date = date('Y-m-d');
    $file = $log_dir . "/app_$date.log";
    
    // Очистка старых логов
    clean_old_logs($log_dir, $max_files);
    
    return [
        'dir' => $log_dir,
        'file' => $file,
        'level' => LOG_LEVELS[$level] ?? LOG_LEVELS['INFO'],
        'max_files' => $max_files,
        'log_error_string' => null
    ];
}

function clean_old_logs(string $dir, int $keep): void
{
    $files = glob($dir . '/app_*.log');
    if (count($files) <= $keep) {
        return;
    }
    
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $to_delete = array_slice($files, 0, count($files) - $keep);
    foreach ($to_delete as $file) {
        unlink($file);
    }
}


function log_message(array $logger, string $message, string $level = 'INFO', array $context = []): void
{
    if (LOG_LEVELS[$level] < $logger['level']) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $pid = getmypid();
    $memory = round(memory_get_usage() / 1024 / 1024, 2);
    
    $context_str = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    $log_entry = "[$timestamp] [$pid] [$memory MB] [$level] $message$context_str" . PHP_EOL;

    file_put_contents($logger['file'], $log_entry, FILE_APPEND | LOCK_EX);
}

function log_debug(array $logger, string $message, array $context = []): void
{
    log_message($logger, $message, 'DEBUG', $context);
}

function log_info(array $logger, string $message, array $context = []): void
{
    log_message($logger, $message, 'INFO', $context);
}

function log_warning(array $logger, string $message, array $context = []): void
{
    log_message($logger, $message, 'WARNING', $context);
}

function log_error(array $logger, string $message, array $context = []): void
{
    log_message($logger, $message, 'ERROR', $context);
}

function log_critical(array $logger, string $message, array $context = []): void
{
    log_message($logger, $message, 'CRITICAL', $context);
}

function log_exception(array $logger, Exception $e, array $extra = []): void
{
    log_error($logger, $e->getMessage(), array_merge([
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ], $extra));
}

