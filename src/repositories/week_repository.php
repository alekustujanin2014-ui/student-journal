<?php
// repositories/week_repository.php

/**
 * Получить информацию о текущей неделе
 */

function get_current_week_info(): array
{
    $now = new DateTime();
    
    $week_start = clone $now;
    $week_start->modify('monday this week');
    
    $week_end = clone $week_start;
    $week_end->modify('sunday this week');
    
    $week_number = (int)$week_start->format('W');
    $is_even = ($week_number % 2 == 0);
    
    return [
        'start' => $week_start->format('d.m.Y'),
        'end' => $week_end->format('d.m.Y'),
        'start_date' => $week_start->format('Y-m-d'),
        'end_date' => $week_end->format('Y-m-d'),
        'week_number' => $week_number,
        'year' => $week_start->format('Y'),
        'is_even' => $is_even,
        'offset' => 0
    ];
}

/**
 * Получить неделю по смещению
 */
function get_week_by_offset(int $offset): array
{
    $now = new DateTime();
    $now->modify(($offset * 7) . ' days');
    
    $week_start = clone $now;
    $week_start->modify('monday this week');
    
    $week_end = clone $week_start;
    $week_end->modify('sunday this week');
    
    $week_number = (int)$week_start->format('W');
    $is_even = ($week_number % 2 == 0);
    
    return [
        'start' => $week_start->format('d.m.Y'),
        'end' => $week_end->format('d.m.Y'),
        'start_date' => $week_start->format('Y-m-d'),
        'end_date' => $week_end->format('Y-m-d'),
        'week_number' => $week_number,
        'year' => $week_start->format('Y'),
        'is_even' => $is_even,
        'offset' => $offset
    ];
}