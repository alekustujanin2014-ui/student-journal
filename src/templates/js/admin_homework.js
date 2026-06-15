import { showAlert, escapeHtml } from './helper.js';

let csrfToken = window.csrfToken;
let currentEditId = null;
let currentDeleteId = null;
let currentHomeworkId = null;
let currentFilePath = null;
let currentPage = 1;
let currentSearch = '';
let currentGroupId = '';

// ==================== ЗАГРУЗКА И ОТОБРАЖЕНИЕ ====================

// Загрузка списка заданий
function loadHomework(page = 1) {
    currentPage = page;
    const groupId = $('#group-filter').val();
    const search = $('#search-homework').val();
    
    currentGroupId = groupId;
    currentSearch = search;
    
    let url = `/api/admin/homework?page=${page}&limit=20`;
    if (groupId) {
        url += `&group_id=${groupId}`;
    }
    if (search && search.trim() !== '') {
        url += `&search=${encodeURIComponent(search)}`;
        $('#reset-search-btn').show();
    } else {
        $('#reset-search-btn').hide();
    }
    
    $.get(url, function(data) {
        if (data.success) {
            renderHomeworkTable(data.data);
            renderPagination(data);
            $('#total-homework').text(data.total);
            
            if (search) {
                $('#total-homework').parent().append(` <span style="color: #666;">(найдено: ${data.total})</span>`);
            }
        } else {
            showAlert('Ошибка загрузки заданий', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Отображение таблицы заданий
function renderHomeworkTable(homework) {
    const tbody = $('#homework-table-body');
    tbody.empty();
    
    if (!homework || homework.length === 0) {
        let message = 'Нет домашних заданий';
        if (currentSearch) {
            message = `По запросу "${escapeHtml(currentSearch)}" заданий не найдено`;
        }
        tbody.html(`<tr><td colspan="8" style="text-align: center;">${message}<\/td><\/tr>`);
        return;
    }
    
    homework.forEach(hw => {
        let title = escapeHtml(hw.title);
        let groupName = escapeHtml(hw.group_name);
        let subjectName = escapeHtml(hw.subject_name);
        let description = escapeHtml(hw.description || '').substring(0, 50);
        
        // Подсветка найденного текста
        if (currentSearch) {
            const regex = new RegExp(`(${escapeRegex(currentSearch)})`, 'gi');
            title = title.replace(regex, '<mark style="background: #ff0;">$1</mark>');
            groupName = groupName.replace(regex, '<mark style="background: #ff0;">$1</mark>');
            subjectName = subjectName.replace(regex, '<mark style="background: #ff0;">$1</mark>');
            description = description.replace(regex, '<mark style="background: #ff0;">$1</mark>');
        }
        
        const row = `
            <tr data-id="${hw.id}">
                <td>${hw.id}<\/td>
                <td><strong>${title}</strong><br><small>${description}</small><\/td>
                <td>${groupName}<\/td>
                <td>${subjectName}<\/td>
                <td>${hw.deadline || '—'}<\/td>
                <td>${hw.submissions_count || 0}<\/td>
                <td>${hw.avg_score ? Math.round(hw.avg_score) : '—'}<\/td>
                <td>
                    <button class="view-submissions-btn" data-id="${hw.id}" data-title="${escapeHtml(hw.title)}" style="margin-right: 5px; padding: 5px 10px; cursor: pointer;">📋 Проверить</button>
                    <button class="edit-homework-btn" data-id="${hw.id}" style="margin-right: 5px; padding: 5px 10px; cursor: pointer;">✏️ Ред.</button>
                    <button class="delete-homework-btn" data-id="${hw.id}" data-title="${escapeHtml(hw.title)}" style="padding: 5px 10px; cursor: pointer;">🗑️ Удалить</button>
                  <\/td>
              <\/tr>
        `;
        tbody.append(row);
    });
}

// Пагинация
function renderPagination(data) {
    const pagination = $('#pagination');
    pagination.empty();
    
    if (data.total_pages <= 1) return;
    
    if (data.page > 1) {
        pagination.append(`<button class="page-btn" data-page="1" style="padding: 5px 10px; margin: 0 3px; cursor: pointer;">« Первая</button> `);
        pagination.append(`<button class="page-btn" data-page="${data.page - 1}" style="padding: 5px 10px; margin: 0 3px; cursor: pointer;">‹ Предыдущая</button> `);
    }
    
    let start = Math.max(1, data.page - 2);
    let end = Math.min(data.total_pages, data.page + 2);
    
    if (start > 1) {
        pagination.append(`<span style="margin: 0 3px;"> ... </span> `);
    }
    
    for (let i = start; i <= end; i++) {
        if (i === data.page) {
            pagination.append(`<strong style="margin: 0 5px;">${i}</strong> `);
        } else {
            pagination.append(`<button class="page-btn" data-page="${i}" style="padding: 5px 10px; margin: 0 3px; cursor: pointer;">${i}</button> `);
        }
    }
    
    if (end < data.total_pages) {
        pagination.append(`<span style="margin: 0 3px;"> ... </span> `);
    }
    
    if (data.page < data.total_pages) {
        pagination.append(`<button class="page-btn" data-page="${data.page + 1}" style="padding: 5px 10px; margin: 0 3px; cursor: pointer;">Следующая ›</button> `);
        pagination.append(`<button class="page-btn" data-page="${data.total_pages}" style="padding: 5px 10px; margin: 0 3px; cursor: pointer;">Последняя »</button>`);
    }
}

// Экранирование для regex
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// ==================== СОЗДАНИЕ И РЕДАКТИРОВАНИЕ ====================

// Открытие модального окна создания
function openCreateModal() {
    currentEditId = null;
    currentFilePath = null;
    $('#homework-modal-title').text('Добавление задания');
    $('#homework-id').val('');
    $('#homework-group').val('');
    $('#homework-subject').val('');
    $('#homework-teacher').val('');
    $('#homework-title').val('');
    $('#homework-description').val('');
    $('#homework-deadline').val('');
    $('#homework-max-score').val(100);
    $('#homework-file').val('');
    $('#current-file-info').hide();
    $('#current-file-name').text('');
    $('#homework-modal').show();
}

// Открытие модального окна редактирования
function openEditModal(id) {
    currentEditId = id;
    
    $.get(`/api/admin/homework/${id}`, function(data) {
        if (data.success && data.data) {
            const hw = data.data;
            $('#homework-modal-title').text('Редактирование задания');
            $('#homework-id').val(hw.id);
            $('#homework-group').val(hw.group_id);
            $('#homework-subject').val(hw.subject_id);
            $('#homework-teacher').val(hw.teacher_id || '');
            $('#homework-title').val(hw.title);
            $('#homework-description').val(hw.description || '');
            $('#homework-deadline').val(hw.deadline || '');
            $('#homework-max-score').val(hw.max_score);
            $('#homework-file').val('');
            
            if (hw.file_path) {
                currentFilePath = hw.file_path;
                const fileName = hw.file_path.split('/').pop();
                $('#current-file-name').text(`Текущий файл: ${fileName}`);
                $('#current-file-info').show();
            } else {
                currentFilePath = null;
                $('#current-file-info').hide();
            }
            $('#homework-modal').show();
        } else {
            showAlert('Ошибка загрузки задания', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Закрытие модального окна задания
function closeHomeworkModal() {
    $('#homework-modal').hide();
    currentEditId = null;
    currentFilePath = null;
}

// Удаление файла
$('#remove-file-btn').on('click', function() {
    currentFilePath = null;
    $('#current-file-info').hide();
    $('#homework-file').val('');
    showAlert('Файл будет удален при сохранении', 'info');
});

// Сохранение задания (с файлом)
$('#homework-form').on('submit', function(e) {
    e.preventDefault();
    
    const groupId = $('#homework-group').val();
    const subjectId = $('#homework-subject').val();
    const title = $('#homework-title').val().trim();
    
    if (!groupId || !subjectId || !title) {
        showAlert('Заполните обязательные поля (Группа, Предмет, Название)', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('group_id', groupId);
    formData.append('subject_id', subjectId);
    formData.append('teacher_id', $('#homework-teacher').val());
    formData.append('title', title);
    formData.append('description', $('#homework-description').val());
    formData.append('deadline', $('#homework-deadline').val());
    formData.append('max_score', parseInt($('#homework-max-score').val()) || 100);
    
    // Если есть новый файл
    const file = $('#homework-file')[0].files[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            showAlert('Файл слишком большой. Максимальный размер 10MB', 'error');
            return;
        }
        formData.append('file', file);
    }
    
    // Если файл был удален
    if (currentFilePath === null && $('#current-file-info').is(':hidden') && $('#homework-file').val() === '') {
        formData.append('remove_file', '1');
    }
    
    let url = '/api/admin/homework/create';
    if (currentEditId) {
        url = `/api/admin/homework/${currentEditId}/update`;
    }
    
    $.ajax({
        url: url,
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                showAlert(currentEditId ? 'Задание обновлено' : 'Задание создано', 'success');
                closeHomeworkModal();
                loadHomework(1);
            } else {
                showAlert(response.error || 'Ошибка сохранения', 'error');
            }
        },
        error: function(xhr) {
            let errorMsg = 'Ошибка сохранения';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.error || errorMsg;
            } catch(e) {}
            showAlert(errorMsg, 'error');
        }
    });
});

// ==================== ПРОСМОТР ОТПРАВОК ====================

// Просмотр отправок
function viewSubmissions(id, title) {
    currentHomeworkId = id;
    $('#submissions-title').text(title);
    
    $.get(`/api/admin/homework/${id}/submissions`, function(data) {
        if (data.success) {
            renderSubmissions(data.data);
            $('#submissions-modal').show();
        } else {
            showAlert('Ошибка загрузки отправок', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Отображение отправок
function renderSubmissions(submissions) {
    const container = $('#submissions-list');
    container.empty();
    
    if (!submissions || submissions.length === 0) {
        container.html('<p style="text-align: center; padding: 40px;">Нет отправленных работ</p>');
        return;
    }
    
    submissions.forEach(sub => {
        const statusClass = {
            'pending': 'pending',
            'approved': 'approved',
            'rejected': 'rejected'
        }[sub.status] || '';
        
        const statusText = {
            'pending': 'На проверке',
            'approved': 'Принято',
            'rejected': 'Отклонено'
        }[sub.status] || sub.status;
        
        const statusColor = {
            'pending': '#856404',
            'approved': '#155724',
            'rejected': '#721c24'
        }[sub.status] || '#333';
        
        const item = `
            <div class="submission-item ${statusClass}" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: ${sub.status === 'pending' ? '#fff3cd' : sub.status === 'approved' ? '#d4edda' : '#f8d7da'}">
                <div><strong>${escapeHtml(sub.user_name)}</strong> (${escapeHtml(sub.user_email)})</div>
                <div>📅 Дата отправки: ${sub.submitted_at}</div>
                ${sub.file_path ? `<div class="submission-file">📎 <a href="${sub.file_path}" target="_blank" style="color: #007bff;">Скачать файл</a></div>` : ''}
                ${sub.comment ? `<div>💬 <em>Комментарий студента:</em> ${escapeHtml(sub.comment)}</div>` : ''}
                ${sub.score ? `<div>⭐ Оценка: <strong>${sub.score}</strong></div>` : ''}
                ${sub.teacher_comment ? `<div>📝 <em>Комментарий преподавателя:</em> ${escapeHtml(sub.teacher_comment)}</div>` : ''}
                <div style="margin-top: 10px;">
                    <button class="grade-btn" data-id="${sub.id}" data-name="${escapeHtml(sub.user_name)}" data-score="${sub.score || 0}" data-status="${sub.status}" data-comment="${escapeHtml(sub.teacher_comment || '')}" style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">⭐ Оценить</button>
                </div>
            </div>
        `;
        container.append(item);
    });
    
    $('.grade-btn').off('click').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const score = $(this).data('score');
        const status = $(this).data('status');
        const comment = $(this).data('comment');
        openGradeModal(id, name, score, status, comment);
    });
}

// ==================== ОЦЕНИВАНИЕ ====================

// Открытие модального окна оценивания
function openGradeModal(id, name, score, status, comment) {
    $('#grade-submission-id').val(id);
    $('#grade-student-name').text(name);
    $('#grade-score').val(score);
    $('#grade-status').val(status);
    $('#grade-comment').val(comment);
    $('#grade-modal').show();
}

// Закрытие модального окна оценивания
function closeGradeModal() {
    $('#grade-modal').hide();
}

// Сохранение оценки
$('#grade-form').on('submit', function(e) {
    e.preventDefault();
    
    const submissionId = $('#grade-submission-id').val();
    const data = {
        score: parseInt($('#grade-score').val()) || 0,
        status: $('#grade-status').val(),
        teacher_comment: $('#grade-comment').val()
    };
    
    $.ajax({
        url: `/api/admin/homework/${submissionId}/grade`,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert('Оценка сохранена', 'success');
                closeGradeModal();
                viewSubmissions(currentHomeworkId, $('#submissions-title').text());
                loadHomework(currentPage);
            } else {
                showAlert(response.error || 'Ошибка сохранения оценки', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// ==================== УДАЛЕНИЕ ====================

// Удаление задания
function deleteHomework(id, title) {
    currentDeleteId = id;
    $('#delete-homework-title').text(title);
    $('#delete-modal').show();
}

// Закрытие модального окна удаления
function closeDeleteModal() {
    $('#delete-modal').hide();
    currentDeleteId = null;
}

// Подтверждение удаления
$('#confirm-delete').on('click', function() {
    if (currentDeleteId) {
        $.ajax({
            url: `/api/admin/homework/${currentDeleteId}/delete`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            success: function(data) {
                if (data.success) {
                    showAlert('Задание удалено', 'success');
                    closeDeleteModal();
                    loadHomework(1);
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

// ==================== ЗАКРЫТИЕ МОДАЛЬНЫХ ОКОН ПО ESC ====================
$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        $('#homework-modal').hide();
        $('#submissions-modal').hide();
        $('#grade-modal').hide();
        $('#delete-modal').hide();
    }
});

// ==================== ОБРАБОТЧИКИ СОБЫТИЙ ====================
$(document).ready(function() {
    // Загрузка заданий
    loadHomework(1);
    
    // Фильтр по группе
    $('#group-filter').on('change', function() {
        loadHomework(1);
    });
    
    // Поиск
    $('#search-btn').on('click', function() {
        loadHomework(1);
    });
    
    $('#search-homework').on('keypress', function(e) {
        if (e.which === 13) {
            loadHomework(1);
        }
    });
    
    $('#reset-search-btn').on('click', function() {
        $('#search-homework').val('');
        currentSearch = '';
        loadHomework(1);
    });
    
    // Создание задания
    $('#create-homework-btn').on('click', openCreateModal);
    $('#close-homework-modal').on('click', closeHomeworkModal);
    
    // Просмотр отправок
    $(document).on('click', '.view-submissions-btn', function() {
        viewSubmissions($(this).data('id'), $(this).data('title'));
    });
    $('#close-submissions-modal').on('click', function() {
        $('#submissions-modal').hide();
    });
    
    // Редактирование
    $(document).on('click', '.edit-homework-btn', function() {
        openEditModal($(this).data('id'));
    });
    
    // Удаление
    $(document).on('click', '.delete-homework-btn', function() {
        deleteHomework($(this).data('id'), $(this).data('title'));
    });
    $('#close-delete-modal').on('click', closeDeleteModal);
    
    // Оценивание
    $('#close-grade-modal').on('click', closeGradeModal);
    
    // Пагинация
    $(document).on('click', '.page-btn', function() {
        loadHomework($(this).data('page'));
    });
    
    // Закрытие по клику вне модального окна
    $(window).on('click', function(e) {
        if ($(e.target).is('#homework-modal')) closeHomeworkModal();
        if ($(e.target).is('#submissions-modal')) $('#submissions-modal').hide();
        if ($(e.target).is('#grade-modal')) closeGradeModal();
        if ($(e.target).is('#delete-modal')) closeDeleteModal();
    });
});