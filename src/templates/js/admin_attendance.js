import { showAlert, escapeHtml } from './helper.js';

let csrfToken = window.csrfToken;
let currentGroupId = null;
let currentDate = null;
let attendanceData = [];

function loadAttendance() {
    const groupId = $('#group-select').val();
    const date = $('#date-select').val();
    
    if (!groupId) {
        showAlert('Выберите группу', 'error');
        return;
    }
    
    currentGroupId = groupId;
    currentDate = date;
    
    $('#attendance-container').show();
    $('#attendance-table-body').html('<tr><td colspan="7"><div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Загрузка...</div></td></tr>');
    
    $.get(`/api/admin/attendance/group?group_id=${groupId}&date=${date}`, function(data) {
        console.log(data);
        if (data.success) {
            renderAttendanceTable(data.data);
        } else {
            showAlert('Ошибка загрузки посещаемости', 'error');
        }
    }).fail(function() {
        showAlert('Ошибка соединения', 'error');
    });
}

function renderAttendanceTable(attendance) {
    const tbody = $('#attendance-table-body');
    tbody.empty();
    
    if (!attendance || attendance.length === 0) {
        tbody.html('<tr><td colspan="7">Нет данных о посещаемости</td></tr>');
        return;
    }
    
    // Группируем по студентам
    const students = {};
    attendance.forEach(item => {
        if (!students[item.user_id]) {
            students[item.user_id] = {
                id: item.user_id,
                name: item.user_name,
                email: item.user_email,
                lessons: {}
            };
        }
        students[item.user_id].lessons[item.lesson_number] = {
            id: item.attendance_id,
            schedule_id: item.schedule_id,
            status: item.status || 'absent',
            comment: item.comment || ''
        };
    });
    
    for (let userId in students) {
        const student = students[userId];
        const row = `
            <tr data-user-id="${student.id}">
                <td class="student-name">${escapeHtml(student.name)}</td>
                <td>${escapeHtml(student.email)}</td>
                ${renderLessonCells(student.lessons, student.id)}
            </tr>
        `;
        tbody.append(row);
    }
    
    // Инициализируем выпадающие списки
    $('.status-select').on('change', function() {
        const userId = $(this).data('user-id');
        const lessonNum = $(this).data('lesson');
        const status = $(this).val();
        
        // Сохраняем в массив для последующей отправки
        const existingIndex = attendanceData.findIndex(a => a.user_id == userId && a.lesson_number == lessonNum);
        const scheduleId = $(this).data('schedule-id');
        
        const attendanceItem = {
            user_id: userId,
            schedule_id: scheduleId,
            date: currentDate,
            status: status,
            comment: ''
        };
        
        if (existingIndex >= 0) {
            attendanceData[existingIndex] = attendanceItem;
        } else {
            attendanceData.push(attendanceItem);
        }
    });
}

function renderLessonCells(lessons, userId) {
    let cells = '';
    for (let i = 1; i <= 5; i++) {
        const lesson = lessons[i] || { status: 'absent', schedule_id: 0 };
        const statusClass = {
            'present': 'status-present',
            'absent': 'status-absent',
            'late': 'status-late',
            'excused': 'status-excused'
        }[lesson.status] || 'status-absent';
        
        cells += `
            <td class="attendance-cell">
                <select class="status-select ${statusClass}" data-user-id="${userId}" data-lesson="${i}" data-schedule-id="${lesson.schedule_id}" style="width: 100px;">
                    <option value="present" ${lesson.status === 'present' ? 'selected' : ''}>✅ Присутствовал</option>
                    <option value="absent" ${lesson.status === 'absent' ? 'selected' : ''}>❌ Отсутствовал</option>
                    <option value="late" ${lesson.status === 'late' ? 'selected' : ''}>⏰ Опоздал</option>
                    <option value="excused" ${lesson.status === 'excused' ? 'selected' : ''}>📝 Уважительная</option>
                </select>
            </td>
        `;
    }
    return cells;
}

function saveAttendance() {
    if (attendanceData.length === 0) {
        showAlert('Нет изменений для сохранения', 'info');
        return;
    }
    
    $.ajax({
        url: '/api/admin/attendance/save',
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-Token': csrfToken },
        data: JSON.stringify(attendanceData),
        success: function(data) {
            if (data.success) {
                showAlert('Посещаемость сохранена', 'success');
                attendanceData = [];
                loadAttendance();
            } else {
                showAlert('Ошибка сохранения', 'error');
            }
        },
        error: function() {
            showAlert('Ошибка соединения', 'error');
        }
    });
}

$(document).ready(function() {
    $('#load-attendance-btn').on('click', loadAttendance);
    $('#save-attendance-btn').on('click', saveAttendance);
    
    $('#group-select').on('change', function() {
        if ($(this).val()) {
            loadAttendance();
        }
    });
});