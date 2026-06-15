<?php
// core/view.php

function create_view_renderer(string $templates_path, array $options = []): array
{
    $default_options = [
        'cache' => ROOT_PATH . '/cache/twig',
        'debug' => true,
        'auto_reload' => true,
        'strict_variables' => false
    ];
    
    $options = array_merge($default_options, $options);
    
    if (!empty($options['cache']) && !is_dir($options['cache'])) {
        mkdir($options['cache'], 0777, true);
    }
    
    $loader = new \Twig\Loader\FilesystemLoader($templates_path);
    $twig = new \Twig\Environment($loader, $options);
    
    if ($options['debug']) {
        $twig->addExtension(new \Twig\Extension\DebugExtension());
    }
    
    return [
        'twig' => $twig,
        'path' => $templates_path,
        'options' => $options
    ];
}

function load_template(array $renderer, string $template)
{
    return $renderer['twig']->load($template);
}

function add_global(array &$renderer, string $name, mixed $value): void
{
    $renderer['twig']->addGlobal($name, $value);
}

function add_function(array &$renderer, string $name, callable $callback): void
{
    $function = new \Twig\TwigFunction($name, $callback);
    $renderer['twig']->addFunction($function);
}

function add_filter(array &$renderer, string $name, callable $callback): void
{
    $filter = new \Twig\TwigFilter($name, $callback);
    $renderer['twig']->addFilter($filter);
}


function template_exists(array $renderer, string $template): bool
{
    // Проверяем, что renderer существует и имеет нужную структуру
    if (!$renderer || !is_array($renderer)) {
        return false;
    }
    
    if (isset($renderer['twig']) && $renderer['twig']) {
        return $renderer['twig']->getLoader()->exists($template);
    }
    
    return file_exists($renderer['path'] . '/' . $template);
}

function render_template(array $renderer, string $template, array $data = []): void 
{
    if (!$renderer || !is_array($renderer)) {
        echo "<h1>Template Error</h1><p>View renderer not available</p>";
    }
    elseif (isset($renderer['twig']) && $renderer['twig']) {
        try {
            echo $renderer['twig']->render($template, $data);
        } catch (Exception $e) {
            echo "<h1>Template Error</h1><p>" . $e->getMessage() . "</p>";
        }
    }
    else
        echo "<h1>Template not found</h1><p>Please install Twig</p>";
}