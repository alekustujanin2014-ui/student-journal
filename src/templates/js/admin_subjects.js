import { showAlert, escapeHtml, escapeRegex} from './helper.js';

let currentDeleteId = null;
let currentEditId = null;
let csrfToken = window.csrfToken;
let currentSearchQuery = '';

// Загрузка предметов
function loadSubjects(search) {
    currentSearchQuery = search;
    let url = '/api/admin/subjects';
    if (search && search.trim() !== '') {
        url += `?search=${encodeURIComponent(search)}`;
        $('#reset-search-btn').show();
    } else {
        $('#reset-search-btn').hide();
    }
    
    $.get(url, function(data) {
        if (data.success) {
            renderSubjectsTable(data.data);
            $('#total-subjects').text(data.total_subjects);
        } else {
            showAlert('Ошибка загрузки предметов', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Отображение таблицы предметов
function renderSubjectsTable(subjects) {
    const tbody = $('#subjects-table-body');
    tbody.empty();
    
    if (!subjects || subjects.length === 0) {
        let message = 'Предметы не найдены';
        if (currentSearchQuery) {
            message = `По запросу "${escapeHtml(currentSearchQuery)}" предметы не найдены`;
        }
        tbody.html(`<tr><td colspan="8" style="text-align: center;">${message}</td></tr>`);
        return;
    }
    
    subjects.forEach(subject => {
        let name = escapeHtml(subject.name);
        if (currentSearchQuery) {
            const regex = new RegExp(`(${escapeRegex(currentSearchQuery)})`, 'gi');
            name = name.replace(regex, '<mark style="background: yellow;">$1</mark>');
        }
        
        const row = `
            <tr data-id="${subject.id}">
                <td>${subject.id}</td>
                <td>${escapeHtml(subject.code || '—')}</td>
                <td><strong>${name}</strong><br><small>${escapeHtml(subject.description || '').substring(0, 50)}</small></td>
                <td>${escapeHtml(subject.short_name || '—')}</td>
                <td>${subject.hours}</td>
                <td>${subject.semester}</td>
                <td>${subject.is_active ? 'Да' : 'Нет'}</td>
                <td>
                    <button class="edit-subject-btn" data-id="${subject.id}">Редактировать</button>
                    <button class="delete-subject-btn" data-id="${subject.id}">Удалить</button>
                  </td>
              </tr>
        `;
        tbody.append(row);
    });
}


// ========== СОЗДАНИЕ ПРЕДМЕТА ==========
function openCreateModal() {
    $('#create-subject-code').val('');
    $('#create-subject-name').val('');
    $('#create-subject-short-name').val('');
    $('#create-subject-description').val('');
    $('#create-subject-hours').val(0);
    $('#create-subject-semester').val(1);
    $('#create-subject-specialty').val('');
    $('#create-subject-active').prop('checked', true);
    $('#create-modal').show();
}

function closeCreateModal() {
    $('#create-modal').hide();
}

$('#create-subject-form').on('submit', function(e) {
    e.preventDefault();
    
    const name = $('#create-subject-name').val().trim();
    if (!name) {
        showAlert('Введите название предмета', 'error');
        return;
    }
    
    const data = {
        code: $('#create-subject-code').val(),
        name: name,
        short_name: $('#create-subject-short-name').val(),
        description: $('#create-subject-description').val(),
        hours: parseInt($('#create-subject-hours').val()) || 0,
        semester: parseInt($('#create-subject-semester').val()),
        specialty: $('#create-subject-specialty').val(),
        is_active: $('#create-subject-active').is(':checked') ? 1 : 0
    };
    
    $.ajax({
        url: '/api/admin/subjects/create',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert('Предмет создан', 'success');
                closeCreateModal();
                loadSubjects();
            } else {
                showAlert(response.error || 'Ошибка создания', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// ========== РЕДАКТИРОВАНИЕ ПРЕДМЕТА ==========
function openEditModal(id) {
    currentEditId = id;
    
    $.ajax({
        url: `/api/admin/subjects/${id}`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.data) {
                const subject = response.data;
                $('#edit-subject-id').val(subject.id);
                $('#edit-subject-code').val(subject.code || '');
                $('#edit-subject-name').val(subject.name);
                $('#edit-subject-short-name').val(subject.short_name || '');
                $('#edit-subject-description').val(subject.description || '');
                $('#edit-subject-hours').val(subject.hours);
                $('#edit-subject-semester').val(subject.semester);
                $('#edit-subject-specialty').val(subject.specialty || '');
                $('#edit-subject-active').prop('checked', subject.is_active == 1);
                $('#edit-modal').show();
            } else {
                showAlert('Ошибка загрузки предмета', 'error');
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

$('#edit-subject-form').on('submit', function(e) {
    e.preventDefault();
    
    const name = $('#edit-subject-name').val().trim();
    if (!name) {
        showAlert('Введите название предмета', 'error');
        return;
    }
    
    const data = {
        code: $('#edit-subject-code').val(),
        name: name,
        short_name: $('#edit-subject-short-name').val(),
        description: $('#edit-subject-description').val(),
        hours: parseInt($('#edit-subject-hours').val()) || 0,
        semester: parseInt($('#edit-subject-semester').val()),
        specialty: $('#edit-subject-specialty').val(),
        is_active: $('#edit-subject-active').is(':checked') ? 1 : 0
    };
    
    $.ajax({
        url: `/api/admin/subjects/${currentEditId}/update`,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert('Предмет обновлен', 'success');
                closeEditModal();
                loadSubjects();
            } else {
                showAlert(response.error || 'Ошибка обновления', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// ========== УДАЛЕНИЕ ПРЕДМЕТА ==========
function deleteSubject(id, name) {
    currentDeleteId = id;
    $('#delete-item-name').text(name);
    $('#delete-modal').show();
}

function closeDeleteModal() {
    $('#delete-modal').hide();
    currentDeleteId = null;
}

$('#confirm-delete').on('click', function() {
    if (currentDeleteId) {
        $.ajax({
            url: `/api/admin/subjects/${currentDeleteId}/delete`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            success: function(data) {
                if (data.success) {
                    showAlert('Предмет удален', 'success');
                    closeDeleteModal();
                    loadSubjects();
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

//Поиск предметов
let searchTimeout;
$('#search-subject').on('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        const search = $('#search-subject').val();
        if (search.length > 2 || search.length === 0) {
            loadSubjects(search);
        }
    }, 500);
});

$('#reset-search-btn').on('click', function() {
        $('#search-subject').val('');
        loadSubjects();
});

// ========== ОБРАБОТЧИКИ СОБЫТИЙ ==========
$(document).ready(function() {
    loadSubjects();
    
    // Создание
    $('#create-subject-btn').on('click', openCreateModal);
    $('#close-create-modal').on('click', closeCreateModal);
    
    // Редактирование
    $(document).on('click', '.edit-subject-btn', function() {
        const id = $(this).data('id');
        openEditModal(id);
    });
    $('#close-edit-modal').on('click', closeEditModal);
    
    // Удаление
    $(document).on('click', '.delete-subject-btn', function() {
        const id = $(this).data('id');
        const name = $(this).closest('tr').find('td:eq(2) strong').text();
        deleteSubject(id, name);
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