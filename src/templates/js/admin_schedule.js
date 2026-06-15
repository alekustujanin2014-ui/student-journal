import { showAlert, escapeHtml } from './helper.js';

let csrfToken = window.csrfToken;
let currentGroupId = null;
let currentEditId = null;
let currentDeleteId = null;
let currentWeekOffset = 0;
let currentWeekStart = '';
let currentWeekEnd = '';
let currentIsEven = false;

const dayNames = {
    1: 'Понедельник',
    2: 'Вторник',
    3: 'Среда',
    4: 'Четверг',
    5: 'Пятница',
    6: 'Суббота'
};

// Загрузка расписания для группы
function loadSchedule(groupId, weekOffset = 0) {
    if (!groupId) return;
    
    currentGroupId = groupId;
    currentWeekOffset = weekOffset;
    
    $.get(`/api/admin/schedule/groups/${groupId}/week?offset=${weekOffset}`, function(data) {
        if (data.success) {
            currentWeekStart = data.week_start;
            currentWeekEnd = data.week_end;
            currentIsEven = data.is_even;
            
            $('#current-week').text(`${data.week_display} (${data.is_even ? 'Четная' : 'Нечетная'} неделя)`);
            $('#schedule-group-id').val(groupId);
            $('#schedule-week-start').val(data.week_start);
            $('#schedule-week-end').val(data.week_end);
            
            renderScheduleTable(data.schedule);
            $('#schedule-container').show();
        } else {
            showAlert('Ошибка загрузки расписания', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Отображение таблицы расписания
function renderScheduleTable(schedule) {
    const tbody = $('#schedule-table-body');
    tbody.empty();
    
    for (let day = 1; day <= 6; day++) {
        let row = `<tr><td><strong>${dayNames[day]}</strong></td>`;
        
        for (let lesson = 1; lesson <= 5; lesson++) {
            let cellContent = '';
            let lessonId = null;
            
            if (schedule[day] && schedule[day][lesson]) {
                const item = schedule[day][lesson];
                lessonId = item.id;
                cellContent = `
                    <div style="margin-bottom: 5px;">
                        <strong>${escapeHtml(item.subject_name)}</strong>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        ${item.teacher_last_name ? escapeHtml(item.teacher_last_name) + ' ' + (item.teacher_name ? item.teacher_name.charAt(0) + '.' : '') : '—'}
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        ауд. ${escapeHtml(item.room_number || '—')}
                    </div>
                    <div style="margin-top: 5px;">
                        <button class="edit-schedule-btn" data-id="${lessonId}" style="font-size: 11px; padding: 2px 5px; margin-right: 3px;">✏️</button>
                        <button class="delete-schedule-btn" data-id="${lessonId}" style="font-size: 11px; padding: 2px 5px;">🗑️</button>
                    </div>
                `;
            } else {
                cellContent = `<button class="add-schedule-btn" data-day="${day}" data-lesson="${lesson}" style="font-size: 11px; padding: 4px 8px;">+ Добавить</button>`;
            }
            
            row += `<td style="vertical-align: top; min-width: 150px;">${cellContent}</td>`;
        }
        
        row += '</tr>';
        tbody.append(row);
    }
}

// Открытие модального окна создания
function openCreateModal(day, lesson) {
    currentEditId = null;
    $('#schedule-modal-title').text('Добавление занятия');
    $('#schedule-id').val('');
    $('#schedule-day').val(day);
    $('#schedule-lesson').val(lesson);
    $('#schedule-subject').val('');
    $('#schedule-teacher').val('');
    $('#schedule-room').val('');
    $('#schedule-even-week').val('');
    $('#schedule-modal').show();
}

// Открытие модального окна редактирования
function openEditModal(id) {
    currentEditId = id;
    
    $.get(`/api/admin/schedule/${id}`, function(data) {
        if (data.success && data.data) {
            const item = data.data;
            $('#schedule-modal-title').text('Редактирование занятия');
            $('#schedule-id').val(item.id);
            $('#schedule-day').val(item.day_of_week);
            $('#schedule-lesson').val(item.lesson_number);
            $('#schedule-subject').val(item.subject_id);
            $('#schedule-teacher').val(item.teacher_id || '');
            $('#schedule-room').val(item.room_id || '');
            $('#schedule-even-week').val(item.is_even_week === null ? '' : item.is_even_week);
            $('#schedule-modal').show();
        }
    });
}

function closeScheduleModal() {
    $('#schedule-modal').hide();
}

// Сохранение занятия
$('#schedule-form').on('submit', function(e) {
    e.preventDefault();
    
    const data = {
        group_id: $('#schedule-group-id').val(),
        day_of_week: parseInt($('#schedule-day').val()),
        lesson_number: parseInt($('#schedule-lesson').val()),
        subject_id: $('#schedule-subject').val(),
        teacher_id: $('#schedule-teacher').val(),
        room_id: $('#schedule-room').val(),
        week_start: $('#schedule-week-start').val(),
        week_end: $('#schedule-week-end').val(),
        is_even_week: $('#schedule-even-week').val() === '' ? null : parseInt($('#schedule-even-week').val())
    };
    
    if (!data.subject_id) {
        showAlert('Выберите предмет', 'error');
        return;
    }
    
    let url = '/api/admin/schedule/create';
    if (currentEditId) {
        url = `/api/admin/schedule/${currentEditId}/update`;
    }
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert(currentEditId ? 'Занятие обновлено' : 'Занятие добавлено', 'success');
                closeScheduleModal();
                loadSchedule(currentGroupId, currentWeekOffset);
            } else {
                showAlert(response.error || 'Ошибка сохранения', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// Удаление занятия
function deleteScheduleItem(id) {
    currentDeleteId = id;
    $('#delete-modal').show();
}

function closeDeleteModal() {
    $('#delete-modal').hide();
    currentDeleteId = null;
}

$('#confirm-delete').on('click', function() {
    if (currentDeleteId) {
        $.ajax({
            url: `/api/admin/schedule/${currentDeleteId}/delete`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            success: function(data) {
                if (data.success) {
                    showAlert('Занятие удалено', 'success');
                    closeDeleteModal();
                    loadSchedule(currentGroupId, currentWeekOffset);
                } else {
                    showAlert(data.error || 'Ошибка удаления', 'error');
                    closeDeleteModal();
                }
            },
            error: function() {
                showAlert('Ошибка соединения', 'error');
                closeDeleteModal();
            }
        });
    }
});

// Навигация по неделям
$('#prev-week').on('click', function() {
    if (currentGroupId) {
        loadSchedule(currentGroupId, currentWeekOffset - 1);
    }
});

$('#next-week').on('click', function() {
    if (currentGroupId) {
        loadSchedule(currentGroupId, currentWeekOffset + 1);
    }
});

// Обработчики событий
$(document).ready(function() {
    $('#group-select').on('change', function() {
        const groupId = $(this).val();
        if (groupId) {
            loadSchedule(groupId, 0);
        } else {
            $('#schedule-container').hide();
        }
    });
    
    $(document).on('click', '.add-schedule-btn', function() {
        const day = $(this).data('day');
        const lesson = $(this).data('lesson');
        openCreateModal(day, lesson);
    });
    
    $(document).on('click', '.edit-schedule-btn', function() {
        const id = $(this).data('id');
        openEditModal(id);
    });
    
    $(document).on('click', '.delete-schedule-btn', function() {
        const id = $(this).data('id');
        deleteScheduleItem(id);
    });
    
    $('#add-schedule-btn').on('click', function() {
        openCreateModal(1, 1);
    });
    
    $('#close-schedule-modal').on('click', closeScheduleModal);
    $('#close-delete-modal').on('click', closeDeleteModal);
    
    // Закрытие по клику вне
    $(window).on('click', function(e) {
        if ($(e.target).is('#schedule-modal')) {
            closeScheduleModal();
        }
        if ($(e.target).is('#delete-modal')) {
            closeDeleteModal();
        }
    });
});