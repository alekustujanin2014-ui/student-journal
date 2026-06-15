import { showAlert, escapeHtml } from './helper.js';

let currentDeleteId = null;
let currentEditId = null;
let csrfToken = window.csrfToken;

// Загрузка факультетов по университету
function loadFaculties(universityId, selectElement, selectedId = null) {
    if (!universityId) {
        $(selectElement).html('<option value="">Сначала выберите университет</option>');
        return;
    }
    
    $.get(`/api/faculties?university_id=${universityId}`, function(data) {
        if (data.success && data.data.length > 0) {
            let options = '<option value="">Выберите факультет</option>';
            data.data.forEach(faculty => {
                const selected = selectedId == faculty.id ? 'selected' : '';
                options += `<option value="${faculty.id}" ${selected}>${escapeHtml(faculty.name)}</option>`;
            });
            $(selectElement).html(options);
        } else {
            $(selectElement).html('<option value="">Нет факультетов</option>');
        }
    }).fail(function() {
        $(selectElement).html('<option value="">Ошибка загрузки</option>');
    });
}

// Загрузка групп по факультету
function loadGroups(facultyId, selectElement, selectedId = null) {
    if (!facultyId) {
        $(selectElement).html('<option value="">Сначала выберите факультет</option>');
        return;
    }
    
    $.get(`/api/groups?faculty_id=${facultyId}`, function(data) {
        if (data.success && data.data.length > 0) {
            let options = '<option value="">Выберите группу</option>';
            data.data.forEach(group => {
                const selected = selectedId == group.id ? 'selected' : '';
                options += `<option value="${group.id}" ${selected}>${escapeHtml(group.name)} (${group.course} курс)</option>`;
            });
            $(selectElement).html(options);
        } else {
            $(selectElement).html('<option value="">Нет групп</option>');
        }
    }).fail(function() {
        $(selectElement).html('<option value="">Ошибка загрузки</option>');
    });
}

function openEditModal(id) {
    currentEditId = id;
    $.get(`/api/user/${id}`, function(data) {
        if (data.data) {
            const user = data.data;
            $('#edit-name').val(user.name);
            $('#edit-email').val(user.email);
            $('#edit-phone').val(user.phone || '');
            $('#edit-city').val(user.city || '');
            $('#edit-password').val('');
            
            // Устанавливаем университет
            if (user.university_id) {
                $('#edit-university').val(user.university_id);
                loadFaculties(user.university_id, '#edit-faculty', user.faculty_id);
                
                if (user.faculty_id) {
                    setTimeout(() => {
                        loadGroups(user.faculty_id, '#edit-group', user.group_id);
                    }, 300);
                }
            } else {
                $('#edit-university').val('');
                $('#edit-faculty').html('<option value="">Сначала выберите университет</option>');
                $('#edit-group').html('<option value="">Сначала выберите факультет</option>');
            }
            
            $('#edit-modal').show();
        }
    }).fail(function(xhr) {
        if (xhr.status === 403) {
            showAlert('Доступ запрещен. Сессия истекла.', 'error');
            setTimeout(() => window.location.href = '/login', 2000);
        } else {
            showAlert('Ошибка загрузки данных пользователя', 'error');
        }
    });
}

function closeEditModal() {
    $('#edit-modal').hide();
    currentEditId = null;
}

function openCreateModal() {
    $('#create-name').val('');
    $('#create-email').val('');
    $('#create-password').val('');
    $('#create-phone').val('');
    $('#create-city').val('');
    $('#create-university').val('');
    $('#create-faculty').html('<option value="">Сначала выберите университет</option>');
    $('#create-group').html('<option value="">Сначала выберите факультет</option>');
    $('#create-modal').show();
}

function closeCreateModal() {
    $('#create-modal').hide();
}

function deleteUser(id) {
    currentDeleteId = id;
    $('#delete-user-name').text($(`tr[data-id="${id}"] td:eq(1)`).text());
    $('#delete-modal').show();
}

function closeDeleteModal() {
    $('#delete-modal').hide();
    currentDeleteId = null;
}

function loadUsers(page, search) {
    let url = `/api/admin/users?page=${page}&limit=20`;
    if (search) {
        url += `&search=${encodeURIComponent(search)}`;
        $('#reset-search').show();
    } else {
        $('#reset-search').hide();
    }
    $.get(url, function(data) {
        if (data.success) {
            renderUsersTable(data.users);
            renderPagination(data.current_page, data.total_pages, search);
            $('#total-users').text(data.total_users);
        }
    });
}

function renderUsersTable(users) {
    const tbody = $('#users-table-body');
    tbody.empty();
    
    if (users.length === 0) {
        tbody.html('<tr><td colspan="10" style="text-align: center;">Пользователи не найдены</td></tr>');
        return;
    }

    users.forEach(user => {
        let date = new Date(user.created_at);
        let day = date.getDate().toString().padStart(2, '0');
        let month = (date.getMonth() + 1).toString().padStart(2, '0');
        let year = date.getFullYear();
        let created_at = `${day}.${month}.${year}`;

        const row = `
            <tr data-id="${user.id}">
                <td>${user.id}</td>
                <td>${escapeHtml(user.name)}</td>
                <td>${escapeHtml(user.email)}</td>
                <td>${escapeHtml(user.university || '—')}</td>
                <td>${escapeHtml(user.faculty || '—')}</td>
                <td>${escapeHtml(user.group_name || '—')}</td>
                <td>${escapeHtml(user.phone || '—')}</td>
                <td>${escapeHtml(user.city || '—')}</td>
                <td>${escapeHtml(created_at || '—')}</td>
                <td>
                    <button class="edit-user-btn" data-id="${user.id}">Редактировать</button>
                    <button class="delete-user-btn" data-id="${user.id}">Удалить</button>
                 </td>
             </tr>
        `;
        tbody.append(row);
    });
}

function renderPagination(currentPage, totalPages, search) {
    const pagination = $('.pagination');
    if (!pagination.length) {
        $('table').after('<div class="pagination"></div>');
    }
    
    const $pagination = $('.pagination');
    $pagination.empty();
    
    if (totalPages <= 1) return;
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            $pagination.append(`<strong style="margin: 0 5px;">${i}</strong>`);
        } else {
            const url = search ? `?page=${i}&search=${encodeURIComponent(search)}` : `?page=${i}`;
            $pagination.append(`<a href="${url}" style="margin: 0 5px;">${i}</a>`);
        }
    }
}

$(document).ready(function() {
    // Обработчики для выпадающих списков в модальном окне редактирования
    $('#edit-university').on('change', function() {
        const universityId = $(this).val();
        loadFaculties(universityId, '#edit-faculty');
        $('#edit-group').html('<option value="">Сначала выберите факультет</option>');
    });
    
    $('#edit-faculty').on('change', function() {
        const facultyId = $(this).val();
        loadGroups(facultyId, '#edit-group');
    });
    
    // Обработчики для выпадающих списков в модальном окне создания
    $('#create-university').on('change', function() {
        const universityId = $(this).val();
        loadFaculties(universityId, '#create-faculty');
        $('#create-group').html('<option value="">Сначала выберите факультет</option>');
    });
    
    $('#create-faculty').on('change', function() {
        const facultyId = $(this).val();
        loadGroups(facultyId, '#create-group');
    });
    
    // Редактирование пользователя
    $('#edit-user-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!currentEditId) {
            showAlert('ID пользователя не найден', 'error');
            return;
        }
        
        const data = {
            name: $('#edit-name').val(),
            email: $('#edit-email').val(),
            password: $('#edit-password').val(),
            university_id: $('#edit-university').val(),
            faculty_id: $('#edit-faculty').val(),
            group_id: $('#edit-group').val(),
            phone: $('#edit-phone').val(),
            city: $('#edit-city').val()
        };
        console.log(data);
        $.ajax({
            url: `/api/admin/user/${currentEditId}/update`,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': csrfToken },
            data: JSON.stringify(data),
            success: function(response) {
                if (response.success) {
                    showAlert('Пользователь успешно обновлен', 'success');
                    closeEditModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(response.error || 'Ошибка при обновлении', 'error');
                }
            },
            error: function() {
                showAlert('Ошибка при обновлении', 'error');
            }
        });
    });
    
    // Создание пользователя
    $('#create-user-form').on('submit', function(e) {
        e.preventDefault();
        
        const data = {
            name: $('#create-name').val(),
            email: $('#create-email').val(),
            password: $('#create-password').val(),
            university_id: $('#create-university').val(),
            faculty_id: $('#create-faculty').val(),
            group_id: $('#create-group').val(),
            phone: $('#create-phone').val(),
            city: $('#create-city').val()
        };
        console.log(data);

        $.ajax({
            url: '/api/admin/user/create',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': csrfToken },
            data: JSON.stringify(data),
            success: function(response) {
                if (response.success) {
                    showAlert('Пользователь успешно создан', 'success');
                    closeCreateModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    closeCreateModal();
                    showAlert(response.error || 'Ошибка при создании', 'error');
                }
            },
            error: function() {
                showAlert('Ошибка при создании', 'error');
            }
        });
    });

    // Удаление пользователя
    $(document).on('click', '#confirm-delete', function() {
        if (currentDeleteId) {
            $.ajax({
                url: `/api/admin/user/${currentDeleteId}/delete`,
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                success: function(data) {
                    if (data.success) {
                        showAlert('Пользователь успешно удален', 'success');
                        closeDeleteModal();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.error || 'Ошибка при удалении', 'error');
                        closeDeleteModal();
                    }
                },
                error: function() {
                    showAlert('Ошибка при удалении', 'error');
                    closeDeleteModal();
                }
            });
        }
    });

    // Поиск
    let searchTimeout;
    $('#search-input').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            const search = $('#search-input').val();
            if (search.length > 2 || search.length === 0) {
                loadUsers(1, search);
            }
        }, 500);
    });

    $('#reset-search').on('click', function() {
        $('#search-input').val('');
        loadUsers(1, '');
    });

    // Обработчики кнопок
    $(document).on('click', '.edit-user-btn', function() {
        const userId = $(this).data('id');
        openEditModal(userId);
    });

    $(document).on('click', '.delete-user-btn', function() {
        const userId = $(this).data('id');
        deleteUser(userId);
    });

    $('#close-edit-user-btn').on('click', function() {
        closeEditModal();
    });

    $('#create-modal-btn').on('click', function() {
        openCreateModal();
    });

    $('#close-create-modal-btn').on('click', function() {
        closeCreateModal();
    });

    $('#close-delete-user-btn').on('click', function() {
        closeDeleteModal();
    });
});