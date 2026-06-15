import { showAlert, escapeHtml, escapeRegex} from './helper.js';

let currentDeleteId = null;
let currentEditId = null;
let csrfToken = window.csrfToken;
let currentSearchQuery = '';
// Загрузка новостей
function loadNews(search) {
    currentSearchQuery = search;
    const universityId = $('#filter-university').val();
    
    let url = '/api/admin/news?';
    if (universityId) {
        url += `university_id=${universityId}`;
    }

    if (search) {
        url += `&search=${encodeURIComponent(search)}`;
        $('#reset-search').show();
    } else {
        $('#reset-search').hide();
    }

    console.log(url);

    $.get(url, function(data) {
        if (data.success) {
            console.log(data);
            renderNewsTable(data.data);
            $('#total-news').text(data.total_news);
        } else {
            showAlert('Ошибка загрузки новостей', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Отображение таблицы новостей
function renderNewsTable(newsList) {
    const tbody = $('#news-table-body');
    tbody.empty();
    
    if (!newsList || newsList.length === 0) {
        tbody.html('<tr><td colspan="7" style="text-align: center;">Новости не найдены</td></tr>');
        return;
    }
    
    newsList.forEach(news => {
        let title = escapeHtml(news.title);

        if (currentSearchQuery) {
            const regex = new RegExp(`(${escapeRegex(currentSearchQuery)})`, 'gi');
            console.log(regex);
            title = title.replace(regex, '<mark style="background: yellow;">$1</mark>');
        }
        
        const row = `
            <tr data-id="${news.id}">
                <td>${news.id}</td>
                <td>${escapeHtml(news.title)}</td>
                <td>${escapeHtml(news.university_name || '—')}</td>
                <td>${news.views || 0}</td>
                <td>
                    ${news.published ? '<span style="color: green;">✓ Да</span>' : '<span style="color: red;">✗ Нет</span>'}
                 </td>
                <td>${news.formatted_date || news.created_at}</td>
                <td>
                    <button class="edit-news-btn" data-id="${news.id}">Редактировать</button>
                    <button class="delete-news-btn" data-id="${news.id}">Удалить</button>
                 </td>
             </tr>
        `;
        tbody.append(row);
    });
}

// Открытие модального окна создания
function openCreateModal() {
    currentEditId = null;
    $('#news-modal-title').text('Добавление новости');
    $('#news-id').val('');
    $('#news-university').val('');
    $('#news-title').val('');
    $('#news-excerpt').val('');
    $('#news-content').val('');
    $('#news-image').val('');
    $('#news-published').prop('checked', true);
    $('#image-preview').hide();
    $('#news-modal').show();
}

// Открытие модального окна редактирования
function openEditModal(id) {
    currentEditId = id;
    
    $.get(`/api/admin/news/${id}`, function(data) {
        if (data.success && data.data) {
            const news = data.data;
            $('#news-modal-title').text('Редактирование новости');
            $('#news-id').val(news.id);
            $('#news-university').val(news.university_id);
            $('#news-title').val(news.title);
            $('#news-excerpt').val(news.excerpt || '');
            $('#news-content').val(news.content);
            $('#news-image').val(news.image || '');
            $('#news-published').prop('checked', news.published == 1);
            
            if (news.image) {
                $('#preview-img').attr('src', news.image);
                $('#image-preview').show();
            } else {
                $('#image-preview').hide();
            }
            
            $('#news-modal').show();
        } else {
            showAlert('Ошибка загрузки новости', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

// Закрытие модального окна
function closeNewsModal() {
    $('#news-modal').hide();
    currentEditId = null;
}

// Сохранение новости
$('#news-form').on('submit', function(e) {
    e.preventDefault();
    
    const data = {
        university_id: $('#news-university').val(),
        title: $('#news-title').val(),
        excerpt: $('#news-excerpt').val(),
        content: $('#news-content').val(),
        image: $('#news-image').val(),
        published: $('#news-published').is(':checked') ? 1 : 0
    };
    
    if (!data.university_id) {
        showAlert('Выберите университет', 'error');
        return;
    }
    
    if (!data.title || !data.content) {
        showAlert('Заполните заголовок и содержание', 'error');
        return;
    }
    
    let url = '/api/admin/news/create';
    let method = 'POST';
    
    if (currentEditId) {
        url = `/api/admin/news/${currentEditId}/update`;
        method = 'POST';
    }
    
    $.ajax({
        url: url,
        method: method,
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert(currentEditId ? 'Новость обновлена' : 'Новость создана', 'success');
                closeNewsModal();
                loadNews();
            } else {
                showAlert(response.error || 'Ошибка сохранения', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

// Удаление новости
function deleteNews(id, title) {
    currentDeleteId = id;
    $('#delete-news-title').text(title);
    $('#delete-modal').show();
}

function closeDeleteModal() {
    $('#delete-modal').hide();
    currentDeleteId = null;
}

$('#confirm-delete-news').on('click', function() {
    if (currentDeleteId) {
        $.ajax({
            url: `/api/admin/news/${currentDeleteId}/delete`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            success: function(data) {
                if (data.success) {
                    showAlert('Новость удалена', 'success');
                    closeDeleteModal();
                    loadNews();
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

// Предпросмотр изображения
$('#news-image').on('input', function() {
    const url = $(this).val();
    if (url) {
        $('#preview-img').attr('src', url);
        $('#image-preview').show();
    } else {
        $('#image-preview').hide();
    }
});
//#search-news

//Поиск новостей
let searchTimeout;
$('#search-news').on('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        const search = $('#search-news').val();
        if (search.length > 2 || search.length === 0) {
            loadNews(search);
        }
    }, 500);
});

$('#reset-search-btn').on('click', function() {
    $('#search-news').val('');
    loadNews();
});


// Обработчики событий
$(document).ready(function() {
    loadNews();
    
    $('#create-news-btn').on('click', openCreateModal);
    $('#close-news-modal-btn').on('click', closeNewsModal);
    $('#close-delete-modal-btn').on('click', closeDeleteModal);
    
    $(document).on('click', '.edit-news-btn', function() {
        const id = $(this).data('id');
        openEditModal(id);
    });
    
    $(document).on('click', '.delete-news-btn', function() {
        const id = $(this).data('id');
        const title = $(this).closest('tr').find('td:eq(1)').text();
        deleteNews(id, title);
    });
    
    $('#filter-university').on('change', function() {
        loadNews();
    });
    
    // Закрытие модального окна по клику вне
    $(window).on('click', function(e) {
        if ($(e.target).is('#news-modal')) {
            closeNewsModal();
        }
        if ($(e.target).is('#delete-modal')) {
            closeDeleteModal();
        }
    });
});