import { showAlert, escapeHtml } from './helper.js';

let currentDeleteId = null;
let currentEditId = null;
let csrfToken = window.csrfToken;
let currentSearchQuery = '';

// Загрузка преподавателей
function loadTeachers(search) {
    currentSearchQuery = search;
    let url = '/api/admin/teachers';
    if (search && search.trim() !== '') {
        url += `?search=${encodeURIComponent(search)}`;
        $('#reset-search-btn').show();
    } else {
        $('#reset-search-btn').hide();
    }
    
    $.get(url, function(data) {
        if (data.success) {
            renderTeachersTable(data.data);
            $('#total-teachers').text(data.total);
        } else {
            showAlert('Ошибка загрузки преподавателей', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Отображение таблицы преподавателей
function renderTeachersTable(teachers) {
    const tbody = $('#teachers-table-body');
    tbody.empty();
    
    if (!teachers || teachers.length === 0) {
        let message = 'Преподаватели не найдены';
        if (currentSearchQuery) {
            message = `По запросу "${escapeHtml(currentSearchQuery)}" преподаватели не найдены`;
        }
        tbody.html(`<tr><td colspan="8" style="text-align: center;">${message}</td></tr>`);
        return;
    }
    
    teachers.forEach(teacher => {
        let fullName = escapeHtml(`${teacher.last_name} ${teacher.name} ${teacher.patronymic || ''}`);
        if (currentSearchQuery) {
            const regex = new RegExp(`(${escapeRegex(currentSearchQuery)})`, 'gi');
            fullName = fullName.replace(regex, '<mark style="background: yellow;">$1</mark>');
        }
        
        const row = `
            <tr data-id="${teacher.id}">
                <td>${teacher.id}</td>
                <td>${escapeHtml(teacher.name)}</td>
                <td>${escapeHtml(teacher.last_name)}</td>
                <td>${escapeHtml(teacher.patronymic || '—')}</td>
                <td>${escapeHtml(teacher.email || '—')}</td>
                <td>${escapeHtml(teacher.phone || '—')}</td>
                <td>${teacher.created_at ? new Date(teacher.created_at).toLocaleDateString('ru-RU') : '—'}</td>
                <td>
                    <button class="edit-teacher-btn" data-id="${teacher.id}">Редактировать</button>
                    <button class="delete-teacher-btn" data-id="${teacher.id}">Удалить</button>
                  </td>
              </tr>
        `;
        tbody.append(row);
    });
}

// Экранирование для regex
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// ========== СОЗДАНИЕ ПРЕПОДАВАТЕЛЯ ==========
function openCreateModal() {
    $('#create-name').val('');
    $('#create-last-name').val('');
    $('#create-patronymic').val('');
    $('#create-email').val('');
    $('#create-phone').val('');
    $('#create-modal').show();
}

function closeCreateModal() {
    $('#create-modal').hide();
}

$('#create-teacher-form').on('submit', function(e) {
    e.preventDefault();
    
    const lastName = $('#create-last-name').val().trim();
    const firstName = $('#create-name').val().trim();
    
    if (!lastName || !firstName) {
        showAlert('Введите имя и фамилию преподавателя', 'error');
        return;
    }
    
    const data = {
        name: $('#create-name').val(),
        last_name: $('#create-last-name').val(),
        patronymic: $('#create-patronymic').val(),
        email: $('#create-email').val(),
        phone: $('#create-phone').val()
    };
    
    $.ajax({
        url: '/api/admin/teachers/create',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert('Преподаватель создан', 'success');
                closeCreateModal();
                loadTeachers();
            } else {
                showAlert(response.error || 'Ошибка создания', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// ========== РЕДАКТИРОВАНИЕ ПРЕПОДАВАТЕЛЯ ==========
function openEditModal(id) {
    currentEditId = id;
    
    $.ajax({
        url: `/api/admin/teachers/${id}`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.data) {
                const teacher = response.data;
                $('#edit-teacher-id').val(teacher.id);
                $('#edit-name').val(teacher.name);
                $('#edit-last-name').val(teacher.last_name);
                $('#edit-patronymic').val(teacher.patronymic || '');
                $('#edit-email').val(teacher.email || '');
                $('#edit-phone').val(teacher.phone || '');
                $('#edit-modal').show();
            } else {
                showAlert('Ошибка загрузки преподавателя', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
}

function closeEditModal() {
    $('#edit-modal').hide();
    currentEditId = null;
}

$('#edit-teacher-form').on('submit', function(e) {
    e.preventDefault();
    
    const lastName = $('#edit-last-name').val().trim();
    const firstName = $('#edit-name').val().trim();
    
    if (!lastName || !firstName) {
        showAlert('Введите имя и фамилию преподавателя', 'error');
        return;
    }
    
    const data = {
        name: $('#edit-name').val(),
        last_name: $('#edit-last-name').val(),
        patronymic: $('#edit-patronymic').val(),
        email: $('#edit-email').val(),
        phone: $('#edit-phone').val()
    };
    
    $.ajax({
        url: `/api/admin/teachers/${currentEditId}/update`,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert('Преподаватель обновлен', 'success');
                closeEditModal();
                loadTeachers();
            } else {
                showAlert(response.error || 'Ошибка обновления', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// ========== УДАЛЕНИЕ ПРЕПОДАВАТЕЛЯ ==========
function deleteTeacher(id, name) {
    currentDeleteId = id;
    $('#delete-teacher-name').text(name);
    $('#delete-modal').show();
}

function closeDeleteModal() {
    $('#delete-modal').hide();
    currentDeleteId = null;
}

$('#confirm-delete').on('click', function() {
    if (currentDeleteId) {
        $.ajax({
            url: `/api/admin/teachers/${currentDeleteId}/delete`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            success: function(data) {
                if (data.success) {
                    showAlert('Преподаватель удален', 'success');
                    closeDeleteModal();
                    loadTeachers();
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

// ========== ПОИСК ПРЕПОДАВАТЕЛЕЙ ==========
let searchTimeout;
$('#search-teacher').on('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        const search = $('#search-teacher').val();
        if (search.length > 2 || search.length === 0) {
            loadTeachers(search);
        }
    }, 500);
});

$('#reset-search-btn').on('click', function() {
    $('#search-teacher').val('');
    loadTeachers();
});

// ========== ОБРАБОТЧИКИ СОБЫТИЙ ==========
$(document).ready(function() {
    loadTeachers();
    
    // Создание
    $('#create-teacher-btn').on('click', openCreateModal);
    $('#close-create-modal').on('click', closeCreateModal);
    
    // Редактирование
    $(document).on('click', '.edit-teacher-btn', function() {
        const id = $(this).data('id');
        openEditModal(id);
    });
    $('#close-edit-modal').on('click', closeEditModal);
    
    // Удаление
    $(document).on('click', '.delete-teacher-btn', function() {
        const id = $(this).data('id');
        const name = $(this).closest('tr').find('td:eq(1)').text() + ' ' + $(this).closest('tr').find('td:eq(2)').text();
        deleteTeacher(id, name);
    });
    $('#close-delete-modal').on('click', closeDeleteModal);
    
    // Закрытие по клику вне модального окна
    $(window).on('click', function(e) {
        if ($(e.target).is('#create-modal')) {
            closeCreateModal();
        }
        if ($(e.target).is('#edit-modal')) {
            closeEditModal();
        }
        if ($(e.target).is('#delete-modal')) {
            closeDeleteModal();
        }
    });
});