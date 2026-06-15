import { showAlert, escapeHtml, escapeRegex} from './helper.js';

let csrfToken = window.csrfToken;
let currentEditId = null;
let currentDeleteId = null;
let currentPage = 1;
let currentSearch = '';
let currentGroupId = '';

function loadExams(page = 1) {
    currentPage = page;
    const groupId = $('#group-filter').val();
    const search = $('#search-exam').val();
    
    currentGroupId = groupId;
    currentSearch = search;
    
    let url = `/api/admin/exams?page=${page}&limit=20`;
    if (groupId) {
        url += `&group_id=${groupId}`;
    }
    if (search && search.trim() !== '') {
        url += `&search=${encodeURIComponent(search)}`;
        $('#reset-search-btn').show();
    } else {
        $('#reset-search-btn').hide();
    }
    console.log(url);
    
    $.get(url, function(data) {
        if (data.success) {
            renderExamsTable(data.data);
            renderPagination(data);
            $('#total-exams').text(data.total);
            
            if (search) {
                $('#total-exams').parent().append(` <span style="color: #666;">(результатов: ${data.total})</span>`);
            }
        } else {
            showAlert('Ошибка загрузки экзаменов', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

function renderExamsTable(exams) {
    const tbody = $('#exams-table-body');
    tbody.empty();
    
    if (!exams || exams.length === 0) {
        let message = 'Экзамены не найдены';
        if (currentSearch) {
            message = `По запросу "${escapeHtml(currentSearch)}" экзамены не найдены`;
        }
        tbody.html(`<tr><td colspan="9" style="text-align: center;">${message}<\/td><\/tr>`);
        return;
    }
    
    exams.forEach(exam => {
        // Подсветка найденного текста
        let subjectName = escapeHtml(exam.subject_name);
        let groupName = escapeHtml(exam.group_name);
        let teacherName = exam.teacher_last_name ? escapeHtml(exam.teacher_last_name) : '—';
        
        if (currentSearch) {
            const regex = new RegExp(`(${escapeRegex(currentSearch)})`, 'gi');
            subjectName = subjectName.replace(regex, '<mark style="background: yellow;">$1</mark>');
            groupName = groupName.replace(regex, '<mark style="background: yellow;">$1</mark>');
            teacherName = teacherName.replace(regex, '<mark style="background: yellow;">$1</mark>');
        }
        
        const row = `
            <tr data-id="${exam.id}">
                <td>${exam.id}<\/td>
                <td>${groupName}<\/td>
                <td>${subjectName}${exam.subject_short ? ` (${escapeHtml(exam.subject_short)})` : ''}<\/td>
                <td>${exam.exam_date}<\/td>
                <td>${exam.exam_time || '—'}<\/td>
                <td>${exam.exam_type === 'exam' ? 'Экзамен' : 'Зачет'}<\/td>
                <td>${teacherName}<\/td>
                <td>${exam.room_number ? `ауд. ${escapeHtml(exam.room_number)}` : '—'}<\/td>
                <td>
                    <button class="edit-exam-btn" data-id="${exam.id}">Редактировать<\/button>
                    <button class="delete-exam-btn" data-id="${exam.id}">Удалить<\/button>
                  <\/td>
              <\/tr>
        `;
        tbody.append(row);
    });
}

function renderPagination(data) {
    const pagination = $('#pagination');
    pagination.empty();
    
    if (data.total_pages <= 1) return;
    
    if (data.page > 1) {
        pagination.append(`<button class="page-btn" data-page="1">« Первая</button> `);
        pagination.append(`<button class="page-btn" data-page="${data.page - 1}">‹ Предыдущая</button> `);
    }
    
    let start = Math.max(1, data.page - 2);
    let end = Math.min(data.total_pages, data.page + 2);
    
    if (start > 1) {
        pagination.append(`<span> ... </span> `);
    }
    
    for (let i = start; i <= end; i++) {
        if (i === data.page) {
            pagination.append(`<strong style="margin: 0 5px;">${i}</strong> `);
        } else {
            pagination.append(`<button class="page-btn" data-page="${i}" style="margin: 0 5px;">${i}</button> `);
        }
    }
    
    if (end < data.total_pages) {
        pagination.append(`<span> ... </span> `);
    }
    
    if (data.page < data.total_pages) {
        pagination.append(`<button class="page-btn" data-page="${data.page + 1}">Следующая ›</button> `);
        pagination.append(`<button class="page-btn" data-page="${data.total_pages}">Последняя »</button>`);
    }
}


function openCreateModal() {
    currentEditId = null;
    $('#exam-modal-title').text('Добавление экзамена');
    $('#exam-id').val('');
    $('#exam-group').val('');
    $('#exam-subject').val('');
    $('#exam-teacher').val('');
    $('#exam-room').val('');
    $('#exam-date').val('');
    $('#exam-time').val('');
    $('#exam-type').val('exam');
    $('#exam-description').val('');
    $('#exam-modal').show();
}

function openEditModal(id) {
    currentEditId = id;
    
    $.get(`/api/admin/exams/${id}`, function(data) {
        if (data.success && data.data) {
            const exam = data.data;
            $('#exam-modal-title').text('Редактирование экзамена');
            $('#exam-id').val(exam.id);
            $('#exam-group').val(exam.group_id);
            $('#exam-subject').val(exam.subject_id);
            $('#exam-teacher').val(exam.teacher_id || '');
            $('#exam-room').val(exam.room_id || '');
            $('#exam-date').val(exam.exam_date);
            $('#exam-time').val(exam.exam_time || '');
            $('#exam-type').val(exam.exam_type);
            $('#exam-description').val(exam.description || '');
            $('#exam-modal').show();
        }
    });
}

function closeExamModal() {
    $('#exam-modal').hide();
}

$('#exam-form').on('submit', function(e) {
    e.preventDefault();
    
    const data = {
        group_id: $('#exam-group').val(),
        subject_id: $('#exam-subject').val(),
        teacher_id: $('#exam-teacher').val(),
        room_id: $('#exam-room').val(),
        exam_date: $('#exam-date').val(),
        exam_time: $('#exam-time').val(),
        exam_type: $('#exam-type').val(),
        description: $('#exam-description').val()
    };
    
    if (!data.group_id || !data.subject_id || !data.exam_date) {
        showAlert('Заполните обязательные поля', 'error');
        return;
    }
    
    let url = '/api/admin/exams/create';
    if (currentEditId) {
        url = `/api/admin/exams/${currentEditId}/update`;
    }
    
    $.ajax({
        url: url,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                showAlert(currentEditId ? 'Экзамен обновлен' : 'Экзамен добавлен', 'success');
                closeExamModal();
                loadExams(currentPage);
            } else {
                showAlert(response.error || 'Ошибка сохранения', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
});

function deleteExam(id) {
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
            url: `/api/admin/exams/${currentDeleteId}/delete`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            success: function(data) {
                if (data.success) {
                    showAlert('Экзамен удален', 'success');
                    closeDeleteModal();
                    loadExams(currentPage);
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

function resetSearch() {
    $('#search-exam').val('');
    currentSearch = '';
    loadExams(1);
}

$(document).ready(function() {
    loadExams();
    
    $('#group-filter').on('change', function() {
        loadExams(1);
    });
    
    $('#search-btn').on('click', function() {
        loadExams(1);
    });
    
    $('#search-exam').on('keypress', function(e) {
        if (e.which === 13) {
            loadExams(1);
        }
    });
    
    $('#reset-search-btn').on('click', function() {
        $('#search-exam').val('');
        loadExams(1);
    });
    
    $('#create-exam-btn').on('click', openCreateModal);
    $('#close-exam-modal').on('click', closeExamModal);
    $('#close-delete-modal').on('click', closeDeleteModal);
    
    $(document).on('click', '.edit-exam-btn', function() {
        openEditModal($(this).data('id'));
    });
    
    $(document).on('click', '.delete-exam-btn', function() {
        deleteExam($(this).data('id'));
    });
    
    $(document).on('click', '.page-btn', function() {
        loadExams($(this).data('page'));
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is('#exam-modal')) {
            closeExamModal();
        }
        if ($(e.target).is('#delete-modal')) {
            closeDeleteModal();
        }
    });
});