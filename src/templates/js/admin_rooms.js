import { showAlert, escapeHtml } from './helper.js';

let currentDeleteId = null;
let currentEditId = null;
let csrfToken = window.csrfToken;
let currentSearchQuery = '';

// Загрузка аудиторий
function loadRooms(search) {
    currentSearchQuery = search;
    let url = '/api/admin/rooms';
    if (search && search.trim() !== '') {
        url += `?search=${encodeURIComponent(search)}`;
        $('#reset-search-btn').show();
    } else {
        $('#reset-search-btn').hide();
    }
    
    $.get(url, function(data) {
        if (data.success) {
            renderRoomsTable(data.data);
            $('#total-rooms').text(data.total);
        } else {
            showAlert('Ошибка загрузки аудиторий', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Отображение таблицы аудиторий
function renderRoomsTable(rooms) {
    const tbody = $('#rooms-table-body');
    tbody.empty();
    
    if (!rooms || rooms.length === 0) {
        let message = 'Аудитории не найдены';
        if (currentSearchQuery) {
            message = `По запросу "${escapeHtml(currentSearchQuery)}" аудитории не найдены`;
        }
        tbody.html(`<tr><td colspan="8" style="text-align: center;">${message}<\/td><\/tr>`);
        return;
    }
    
    rooms.forEach(room => {
        let number = escapeHtml(room.number);
        let building = escapeHtml(room.building || '—');
        
        if (currentSearchQuery) {
            const regex = new RegExp(`(${escapeRegex(currentSearchQuery)})`, 'gi');
            number = number.replace(regex, '<mark style="background: yellow;">$1</mark>');
            building = building.replace(regex, '<mark style="background: yellow;">$1</mark>');
        }
        
        let typeText = '';
        switch(room.type) {
            case 'lecture': typeText = 'Лекционная'; break;
            case 'lab': typeText = 'Лаборатория'; break;
            case 'practice': typeText = 'Практическая'; break;
            case 'computer': typeText = 'Компьютерная'; break;
            default: typeText = room.type;
        }
        
        let equipment = '';
        if (room.has_computer) equipment += '🖥️ ';
        if (room.has_projector) equipment += '📽️ ';
        if (room.has_board) equipment += '📋 ';
        if (!equipment) equipment = '—';
        
        const row = `
            <tr data-id="${room.id}">
                <td>${room.id}<\/td>
                <td>${number}<\/td>
                <td>${building}<\/td>
                <td>${room.capacity}<\/td>
                <td>${typeText}<\/td>
                <td>${equipment}<\/td>
                <td>${room.is_active ? 'Да' : 'Нет'}<\/td>
                <td>
                    <button class="edit-room-btn" data-id="${room.id}">Редактировать<\/button>
                    <button class="delete-room-btn" data-id="${room.id}">Удалить<\/button>
                  <\/td>
              <\/tr>
        `;
        tbody.append(row);
    });
}

// Экранирование для regex
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// ========== СОЗДАНИЕ АУДИТОРИИ ==========
function openCreateModal() {
    $('#create-number').val('');
    $('#create-building').val('');
    $('#create-capacity').val(0);
    $('#create-type').val('lecture');
    $('#create-description').val('');
    $('#create-has-computer').prop('checked', false);
    $('#create-has-projector').prop('checked', false);
    $('#create-has-board').prop('checked', true);
    $('#create-active').prop('checked', true);
    $('#create-modal').show();
}

function closeCreateModal() {
    $('#create-modal').hide();
}

$('#create-room-form').on('submit', function(e) {
    e.preventDefault();
    
    const number = $('#create-number').val().trim();
    if (!number) {
        showAlert('Введите номер аудитории', 'error');
        return;
    }
    
    const data = {
        number: number,
        building: $('#create-building').val(),
        capacity: parseInt($('#create-capacity').val()) || 0,
        type: $('#create-type').val(),
        description: $('#create-description').val(),
        has_computer: $('#create-has-computer').is(':checked') ? 1 : 0,
        has_projector: $('#create-has-projector').is(':checked') ? 1 : 0,
        has_board: $('#create-has-board').is(':checked') ? 1 : 0,
        is_active: $('#create-active').is(':checked') ? 1 : 0
    };
    
    $.ajax({
        url: '/api/admin/rooms/create',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert('Аудитория создана', 'success');
                closeCreateModal();
                loadRooms();
            } else {
                showAlert(response.error || 'Ошибка создания', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// ========== РЕДАКТИРОВАНИЕ АУДИТОРИИ ==========
function openEditModal(id) {
    currentEditId = id;
    
    $.ajax({
        url: `/api/admin/rooms/${id}`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.data) {
                const room = response.data;
                $('#edit-room-id').val(room.id);
                $('#edit-number').val(room.number);
                $('#edit-building').val(room.building || '');
                $('#edit-capacity').val(room.capacity);
                $('#edit-type').val(room.type);
                $('#edit-description').val(room.description || '');
                $('#edit-has-computer').prop('checked', room.has_computer == 1);
                $('#edit-has-projector').prop('checked', room.has_projector == 1);
                $('#edit-has-board').prop('checked', room.has_board == 1);
                $('#edit-active').prop('checked', room.is_active == 1);
                $('#edit-modal').show();
            } else {
                showAlert('Ошибка загрузки аудитории', 'error');
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

$('#edit-room-form').on('submit', function(e) {
    e.preventDefault();
    
    const number = $('#edit-number').val().trim();
    if (!number) {
        showAlert('Введите номер аудитории', 'error');
        return;
    }
    
    const data = {
        number: number,
        building: $('#edit-building').val(),
        capacity: parseInt($('#edit-capacity').val()) || 0,
        type: $('#edit-type').val(),
        description: $('#edit-description').val(),
        has_computer: $('#edit-has-computer').is(':checked') ? 1 : 0,
        has_projector: $('#edit-has-projector').is(':checked') ? 1 : 0,
        has_board: $('#edit-has-board').is(':checked') ? 1 : 0,
        is_active: $('#edit-active').is(':checked') ? 1 : 0
    };
    
    $.ajax({
        url: `/api/admin/rooms/${currentEditId}/update`,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert('Аудитория обновлена', 'success');
                closeEditModal();
                loadRooms();
            } else {
                showAlert(response.error || 'Ошибка обновления', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// ========== УДАЛЕНИЕ АУДИТОРИИ ==========
function deleteRoom(id, name) {
    currentDeleteId = id;
    $('#delete-room-name').text(name);
    $('#delete-modal').show();
}

function closeDeleteModal() {
    $('#delete-modal').hide();
    currentDeleteId = null;
}

$('#confirm-delete').on('click', function() {
    if (currentDeleteId) {
        $.ajax({
            url: `/api/admin/rooms/${currentDeleteId}/delete`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            success: function(data) {
                if (data.success) {
                    showAlert('Аудитория удалена', 'success');
                    closeDeleteModal();
                    loadRooms();
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

// ========== ПОИСК АУДИТОРИЙ ==========
let searchTimeout;
$('#search-room').on('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        const search = $('#search-room').val();
        if (search.length > 2 || search.length === 0) {
            loadRooms(search);
        }
    }, 500);
});

$('#reset-search-btn').on('click', function() {
    $('#search-room').val('');
    loadRooms();
});

// ========== ОБРАБОТЧИКИ СОБЫТИЙ ==========
$(document).ready(function() {
    loadRooms();
    
    // Создание
    $('#create-room-btn').on('click', openCreateModal);
    $('#close-create-modal').on('click', closeCreateModal);
    
    // Редактирование
    $(document).on('click', '.edit-room-btn', function() {
        const id = $(this).data('id');
        openEditModal(id);
    });
    $('#close-edit-modal').on('click', closeEditModal);
    
    // Удаление
    $(document).on('click', '.delete-room-btn', function() {
        const id = $(this).data('id');
        const name = $(this).closest('tr').find('td:eq(1)').text();
        deleteRoom(id, name);
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