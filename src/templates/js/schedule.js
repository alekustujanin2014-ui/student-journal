import { showAlert, escapeHtml } from './helper.js';
let weekOffset;
$(document).ready(function () {
    // Функция обновления расписания
    function updateScheduleTable(scheduleData, weekDisplay) {
    const lessonTimes = {
        1: '09:00 - 10:30',
        2: '10:45 - 12:15',
        3: '12:30 - 14:00',
        4: '14:15 - 15:45',
        5: '16:00 - 17:30'
    };

    let html = '';
    
    console.log('scheduleData received:', scheduleData);

    for (let lessonNum = 1; lessonNum <= 5; lessonNum++) {
        html += '<tr>';
        html += `<td class="lesson-time">${lessonTimes[lessonNum]}<\/td>`;

        for (let day = 1; day <= 6; day++) {
            let cellContent = '';
            let found = false;

            // Проверяем, есть ли данные для этого дня
            if (scheduleData[day] && typeof scheduleData[day] === 'object') {
                // Перебираем элементы дня (они могут быть в виде объекта или массива)
                for (let item of Object.values(scheduleData[day])) {
                    if (item.lesson_number == lessonNum) {
                        found = true;
                        cellContent = `
                            <div class="lesson-name">${escapeHtml(item.subject_name)}</div>
                            ${item.teacher_last_name ? `<div class="lesson-teacher">${escapeHtml(item.teacher_last_name)} ${item.teacher_name ? item.teacher_name.charAt(0) + '.' : ''}</div>` : ''}
                            ${item.room_number ? `<div class="lesson-room">ауд. ${escapeHtml(item.room_number)}</div>` : ''}
                        `;
                        break;
                    }
                }
            }

            html += `<td class="lesson-cell">${found ? cellContent : '—'}<\/td>`;
        }

        html += '<\/tr>';
    }

    $('#schedule-tbody').html(html);
    console.log('Generated HTML:', html);

    if (weekDisplay) {
        $('#current-week').text(weekDisplay);
    }
    }
    // Обновление расписания
    const refreshScheduleBtn = $('#refresh-schedule');
    if (refreshScheduleBtn.length) {
        refreshScheduleBtn.on('click', function () {
            const $btn = $(this);
            $btn.addClass('loading').prop('disabled', true);
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Обновление...');

            // Получаем текущую неделю
            const weekNav = $('.week-nav');
            let weekOffset = weekNav.length ? parseInt(weekNav.data('current-offset')) : 0;

            let alertContainer = $('.alert-container');

            if (!alertContainer.length) {
                alertContainer = $('<div class="alert-container"></div>');
                $('.main-content').prepend(alertContainer);
            }
            alertContainer.empty();

            $.ajax({
                url: '/api/schedule/week',
                method: 'GET',
                dataType: 'json',
                data: { offset: weekOffset },
                success: function (data) {
                    if (data.success) {
                        console.log(data);
                        updateScheduleTable(data.schedule, data.week_display);
                    } else {
                        showAlert(data.error || 'Ошибка обновления расписания', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error:', error);
                    showAlert('Ошибка соединения при обновлении расписания', 'error');
                },
                complete: function () {
                    setTimeout(function () {
                        $btn.removeClass('loading').prop('disabled', false);
                        $btn.html(originalText);
                    }, 1000)
                }
            });
        });
    }

    // Навигация по неделям
    const weekNav = $('.week-nav');
    weekOffset = weekNav.length ? parseInt($('.week-nav').data('current-offset')) : 0;
    
    const weekDisplay = $('#current-week');

    function loadWeek(offset) {
        const $btn = $('#prev-week, #next-week');
        $btn.prop('disabled', true);

        let alertContainer = $('.alert-container');
        if (!alertContainer.length) {
            alertContainer = $('<div class="alert-container"></div>');
            $('.main-content').prepend(alertContainer);
        }
        alertContainer.empty();

        $.ajax({
            url: '/api/schedule/week',
            method: 'GET',
            dataType: 'json',
            data: { offset: offset },
            success: function (data) {
                if (data.success) {
                    console.log(data);
                    updateScheduleTable(data.schedule, data.week_display);
                    weekOffset = data.offset;
                    weekNav.data('current-offset', weekOffset);
                } else {
                    showAlert(data.error || 'Ошибка загрузки недели', 'error', 'section');
                }
            },
            error: function (data) {
                showAlert('Ошибка соединения', 'error', 'section');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    }
    
    $('#prev-week').on('click', function () {
        loadWeek(weekOffset - 1);
    });

    $('#next-week').on('click', function () {
        loadWeek(weekOffset + 1);
    });


    const $refreshExamBtn = $('#refresh-exams');
    if ($refreshExamBtn.length) {
        $($refreshExamBtn).on('click', function () {
            const $btn = $(this);
            $btn.addClass('loading').prop('disabled', true);
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Обновление...');

            $.ajax({
                url: '/api/exams/list',
                method: 'GET',
                dataType: 'json',
                success: function (data) {
                    if (data.success) {
                        updateExamsTable(data.exams);
                    } else {
                        showAlert(data.error || 'Ошибка загрузки экзаменов', 'error', 'exams-section');
                    }
                },
                error: function () {
                    showAlert('Ошибка соединения', 'error', 'exams-section');
                },
                complete: function () {
                    setTimeout(function () {
                        $btn.removeClass('loading').prop('disabled', false);
                        $btn.html(originalText);
                    }, 1000)
                }
            });
        });
    }
    // Обновление таблицы экзаменов
    function updateExamsTable(exams) {
        if (!exams || exams.length === 0) {
            $('#exams-tbody').html(`
            <tr class="empty-row">
                <td colspan="7">
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>Нет запланированных экзаменов</p>
                    </div>
                </td>
            </tr>
        `);
            return;
        }

        let html = '';
        for (let exam of exams) {
            html += `
            <tr>
                <td class="exam-date">${formatDate(exam.exam_date)}</td>
                <td class="exam-time">${exam.exam_time ? exam.exam_time.substring(0, 5) : '—'}</td>
                <td class="exam-subject">
                    <strong>${escapeHtml(exam.subject_name)}</strong>
                    ${exam.subject_short ? `<span class="subject-short">(${escapeHtml(exam.subject_short)})</span>` : ''}
                </td>
                <td class="exam-type">
                    ${exam.exam_type === 'exam'
                    ? '<span class="badge badge-exam">Экзамен</span>'
                    : '<span class="badge badge-credit">Зачет</span>'}
                </td>
                <td class="exam-teacher">${formatTeacherName(exam)}</td>
                <td class="exam-room">${formatRoom(exam)}</td>
                <td class="exam-description">${escapeHtml(exam.description) || '—'}</td>
            </tr>
        `;
        }
        $('#exams-tbody').html(html);
    }

    // Форматирование даты
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('ru-RU');
    }

    // Форматирование имени преподавателя
    function formatTeacherName(exam) {
        if (!exam.teacher_name) return '—';
        let name = exam.teacher_last_name + ' ' + exam.teacher_name.charAt(0) + '. ';
        if (exam.teacher_patronymic) {
            name += exam.teacher_patronymic.charAt(0) + '.';
        }
        return escapeHtml(name);
    }

    // Форматирование аудитории
    function formatRoom(exam) {
        if (!exam.room_number) return '—';
        let room = `ауд. ${exam.room_number}`;
        if (exam.room_building) {
            room += ` <span class="building">(${escapeHtml(exam.room_building)})</span>`;
        }
        return room;
    }
});