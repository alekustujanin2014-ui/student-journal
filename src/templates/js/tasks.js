// import { showAlert, escapeHtml } from './helper.js';

// let csrfToken = window.csrfToken;

// $(document).ready(function() {
//     // Функция загрузки заданий
//     function loadHomework() {
//         $('#homework-container').html('<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Загрузка заданий...</div>');
        
//         $.ajax({
//             url: '/api/homework/list',
//             method: 'GET',
//             dataType: 'json',
//             success: function(data) {
//                 if (data.success) {
//                     renderHomework(data.data);
//                 } else {
//                     $('#homework-container').html('<div class="empty-state"><i class="fas fa-tasks"></i><p>Нет домашних заданий</p></div>');
//                 }
//             },
//             error: function() {
//                 $('#homework-container').html('<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Ошибка загрузки заданий</p></div>');
//                 showAlert('Ошибка соединения', 'error');
//             }
//         });
//     }

//     // Функция отображения заданий (карточки)
//     function renderHomework(homework) {
//         const container = $('#homework-container');
//         container.empty();
        
//         if (!homework || homework.length === 0) {
//             container.html('<div class="empty-state"><i class="fas fa-tasks"></i><p>Нет домашних заданий</p></div>');
//             return;
//         }
        
//         homework.forEach(hw => {
//             const submission = hw.submission || {};
            
//             let statusClass = 'status-not-submitted';
//             let statusText = 'Не выполнено';
            
//             if (submission && submission.status) {
//                 switch(submission.status) {
//                     case 'pending':
//                         statusClass = 'status-pending';
//                         statusText = 'На проверке';
//                         break;
//                     case 'approved':
//                         statusClass = 'status-approved';
//                         statusText = `Принято (${submission.score}/${hw.max_score})`;
//                         break;
//                     case 'rejected':
//                         statusClass = 'status-rejected';
//                         statusText = 'Отклонено';
//                         break;
//                 }
//             }
            
//             let submitHtml = '';
//             if (submission && submission.status === 'approved') {
//                 submitHtml = `
//                     <div class="submission-info">
//                         <i class="fas fa-check-circle"></i> Работа проверена
//                     </div>
//                     <button class="view-result-btn" data-id="${hw.id}" data-score="${submission.score}" data-status="${submission.status}" data-comment="${escapeHtml(submission.teacher_comment || '')}">
//                         <i class="fas fa-chart-line"></i> Посмотреть результат
//                     </button>
//                 `;
//             } else if (submission && submission.status === 'pending') {
//                 submitHtml = `
//                     <div class="submission-info">
//                         <i class="fas fa-clock"></i> Работа отправлена на проверку
//                     </div>
//                 `;
//             } else {
//                 submitHtml = `
//                     <button class="submit-btn" data-id="${hw.id}" data-title="${escapeHtml(hw.title)}" data-deadline="${hw.deadline || 'Не указан'}">
//                         <i class="fas fa-upload"></i> Отправить работу
//                     </button>
//                 `;
//             }
            
//             const card = `
//                 <div class="homework-card" data-id="${hw.id}">
//                     <div class="homework-header">
//                         <div class="homework-title">
//                             <i class="fas fa-book"></i> ${escapeHtml(hw.subject_name)}
//                         </div>
//                         <span class="homework-status ${statusClass}">${statusText}</span>
//                     </div>
//                     <div class="homework-body">
//                         <div class="homework-description">
//                             <strong>${escapeHtml(hw.title)}</strong>
//                             ${hw.description ? `<p>${escapeHtml(hw.description)}</p>` : ''}
//                         </div>
//                         <div class="homework-meta">
//                             <div><i class="fas fa-calendar-alt"></i> Дедлайн: ${hw.deadline || 'Не указан'}</div>
//                             <div><i class="fas fa-star"></i> Макс. балл: ${hw.max_score}</div>
//                         </div>
//                         ${hw.file_path ? `
//                         <div class="homework-attachment">
//                             <a href="${hw.file_path}" target="_blank">
//                                 <i class="fas fa-paperclip"></i> Скачать задание
//                             </a>
//                         </div>
//                         ` : ''}
//                     </div>
//                     <div class="homework-submit">
//                         ${submitHtml}
//                     </div>
//                 </div>
//             `;
//             container.append(card);
//         });
        
//         // Обработчики кнопок
//         $('.submit-btn').off('click').on('click', function() {
//             const id = $(this).data('id');
//             const title = $(this).data('title');
//             const deadline = $(this).data('deadline');
//             openSubmitModal(id, title, deadline);
//         });
        
//         $('.view-result-btn').off('click').on('click', function() {
//             const score = $(this).data('score');
//             const status = $(this).data('status');
//             const comment = $(this).data('comment');
//             openResultModal(score, status, comment);
//         });
//     }

//     // Открытие модального окна отправки
//     function openSubmitModal(id, title, deadline) {
//         $('#submit-homework-id').val(id);
//         $('#submit-homework-title').text(title);
//         $('#submit-homework-deadline').text(`Дедлайн: ${deadline}`);
//         $('#submit-comment').val('');
//         $('#submit-file').val('');
//         $('#submitModal').fadeIn(200).css('display', 'flex');
//         $('body').css('overflow', 'hidden');
//     }

//     // Закрытие модального окна отправки
//     function closeSubmitModal() {
//         $('#submitModal').fadeOut(200);
//         $('body').css('overflow', '');
//     }

//     // Функция открытия модального окна результата
//     function openResultModal(score, status, comment) {
//         const isApproved = status === 'approved';
//         const statusText = isApproved ? '✅ Работа принята' : '❌ Работа отклонена';
//         const statusClass = isApproved ? 'approved' : 'rejected';
        
//         // Сброс и показ
//         $('#result-score-value').text('0');
//         $('#result-status').removeClass('approved rejected').addClass(statusClass).html(`<i class="fas ${isApproved ? 'fa-check-circle' : 'fa-times-circle'}"></i> ${statusText}`);
//         $('#result-comment-text').html(comment ? escapeHtml(comment) : '<em>Нет комментария</em>');
        
//         // Показываем модальное окно
//         $('#resultModal').fadeIn(200).css('display', 'flex');
//         $('body').css('overflow', 'hidden');
        
//         // Анимация счета (если оценка есть)
//         if (score && score > 0) {
//             let current = 0;
//             const target = parseInt(score);
//             const duration = 1000;
//             const step = Math.ceil(target / (duration / 20));
            
//             const timer = setInterval(function() {
//                 current += step;
//                 if (current >= target) {
//                     current = target;
//                     clearInterval(timer);
//                 }
//                 $('#result-score-value').text(current);
//             }, 20);
//         } else if (score === 0) {
//             $('#result-score-value').text('0');
//         } else {
//             $('#result-score-value').text('—');
//         }
//     }

//     // Функция закрытия модального окна результата
//     function closeResultModal() {
//         $('#resultModal').fadeOut(200);
//         $('body').css('overflow', '');
//     }

//     // Отправка работы
//     $('#submit-form').on('submit', function(e) {
//         e.preventDefault();
        
//         const homeworkId = $('#submit-homework-id').val();
//         const comment = $('#submit-comment').val();
//         const file = $('#submit-file')[0].files[0];
        
//         if (!file) {
//             showAlert('Выберите файл для загрузки', 'error');
//             return;
//         }
        
//         if (file.size > 10 * 1024 * 1024) {
//             showAlert('Файл слишком большой. Максимальный размер 10MB', 'error');
//             return;
//         }
        
//         const formData = new FormData();
//         formData.append('comment', comment);
//         formData.append('file', file);
        
//         $.ajax({
//             url: `/api/homework/${homeworkId}/submit`,
//             method: 'POST',
//             headers: { 'X-CSRF-Token': csrfToken },
//             data: formData,
//             processData: false,
//             contentType: false,
//             success: function(response) {
//                 if (response.success) {
//                     showAlert('Работа успешно отправлена на проверку', 'success');
//                     closeSubmitModal();
//                     loadHomework();
//                 } else {
//                     showAlert(response.error || 'Ошибка отправки', 'error');
//                 }
//             },
//             error: function(xhr) {
//                 let errorMsg = 'Ошибка отправки';
//                 try {
//                     const response = JSON.parse(xhr.responseText);
//                     errorMsg = response.error || errorMsg;
//                 } catch(e) {}
//                 showAlert(errorMsg, 'error');
//             }
//         });
//     });

//     // Обновление заданий по кнопке
//     $('#refresh-homework').on('click', function() {
//         const $btn = $(this);
//         $btn.addClass('loading').prop('disabled', true);
//         const originalText = $btn.html();
//         $btn.html('<i class="fas fa-spinner fa-spin"></i> Обновление...');
        
//         loadHomework();
        
//         setTimeout(function() {
//             $btn.removeClass('loading').prop('disabled', false);
//             $btn.html(originalText);
//         }, 1000);
//     });
    
//     // Закрытие модальных окон отправки
//     $('.modal-close, .modal-close-btn').on('click', function() {
//         closeSubmitModal();
//     });
    
//     // Закрытие модального окна результата
//     $('.result-modal-close, .result-close-btn').on('click', function() {
//         closeResultModal();
//     });
    
//     // Закрытие по клику вне модального окна
//     $(window).on('click', function(e) {
//         if ($(e.target).is('#submitModal')) {
//             closeSubmitModal();
//         }
//         if ($(e.target).is('#resultModal')) {
//             closeResultModal();
//         }
//     });
    
//     // Закрытие по Escape
//     $(document).on('keydown', function(e) {
//         if (e.key === 'Escape') {
//             if ($('#submitModal').is(':visible')) {
//                 closeSubmitModal();
//             }
//             if ($('#resultModal').is(':visible')) {
//                 closeResultModal();
//             }
//         }
//     });
    
//     // Загружаем задания
//     loadHomework();
// });

import { showAlert, escapeHtml } from './helper.js';

let csrfToken = window.csrfToken;
let allHomework = []; // Храним все задания для фильтрации по статусу
let currentSearch = '';

$(document).ready(function() {
    // Функция загрузки заданий с поиском
    function loadHomework(search = '') {
        currentSearch = search;
        $('#homework-container').html('<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Загрузка заданий...</div>');
        
        let url = '/api/homework/list';
        if (search && search.trim() !== '') {
            url += `?search=${encodeURIComponent(search)}`;
        }
        
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    allHomework = data.data;
                    applyStatusFilter();
                    
                    // Показываем информацию о поиске
                    if (data.search) {
                        $('.section-title').find('.search-info').remove();
                        $('.section-title').append(`<span class="search-info" style="font-size: 0.8rem; color: #666; margin-left: 10px;">Результаты поиска: "${escapeHtml(data.search)}"</span>`);
                    } else {
                        $('.section-title').find('.search-info').remove();
                    }
                } else {
                    $('#homework-container').html('<div class="empty-state"><i class="fas fa-tasks"></i><p>Нет домашних заданий</p></div>');
                }
            },
            error: function() {
                $('#homework-container').html('<div class="empty-state"><i class="fas fa-exclamation-circle"></i><p>Ошибка загрузки заданий</p></div>');
                showAlert('Ошибка соединения', 'error');
            }
        });
    }

    // Применение фильтра по статусу (клиентский)
    function applyStatusFilter() {
        const statusFilter = $('#status-filter').val();
        
        let filtered = [...allHomework];
        
        // Фильтр по статусу
        if (statusFilter !== 'all') {
            filtered = filtered.filter(hw => {
                const submission = hw.submission || {};
                let status = 'not_submitted';
                if (submission.status) {
                    status = submission.status;
                }
                return status === statusFilter;
            });
        }
        
        renderHomework(filtered);
        
        // Показываем количество найденных
        const resultText = filtered.length !== allHomework.length 
            ? ` (показано ${filtered.length} из ${allHomework.length})` 
            : ` (${allHomework.length})`;
        
        $('.section-title').find('.result-count').remove();
        $('.section-title').append(`<span class="result-count" style="font-size: 0.8rem; color: #666;">${resultText}</span>`);
    }

    // Функция отображения заданий (карточки)
    function renderHomework(homework) {
        const container = $('#homework-container');
        container.empty();
        
        if (!homework || homework.length === 0) {
            const searchTerm = $('#search-homework').val();
            let message = 'Нет домашних заданий';
            if (searchTerm) {
                message = `По запросу "${escapeHtml(searchTerm)}" ничего не найдено`;
            } else if ($('#status-filter').val() !== 'all') {
                message = 'Нет заданий с выбранным статусом';
            }
            container.html(`<div class="empty-state"><i class="fas fa-tasks"></i><p>${message}</p></div>`);
            return;
        }
        
        homework.forEach(hw => {
            const submission = hw.submission || {};
            
            let statusClass = 'status-not-submitted';
            let statusText = 'Не выполнено';
            
            if (submission && submission.status) {
                switch(submission.status) {
                    case 'pending':
                        statusClass = 'status-pending';
                        statusText = 'На проверке';
                        break;
                    case 'approved':
                        statusClass = 'status-approved';
                        statusText = `Принято (${submission.score}/${hw.max_score})`;
                        break;
                    case 'rejected':
                        statusClass = 'status-rejected';
                        statusText = 'Отклонено';
                        break;
                }
            }
            
            let submitHtml = '';
            if (submission && submission.status === 'approved') {
                submitHtml = `
                    <div class="submission-info">
                        <i class="fas fa-check-circle"></i> Работа проверена
                    </div>
                    <button class="view-result-btn" data-id="${hw.id}" data-score="${submission.score}" data-status="${submission.status}" data-comment="${escapeHtml(submission.teacher_comment || '')}">
                        <i class="fas fa-chart-line"></i> Посмотреть результат
                    </button>
                `;
            } else if (submission && submission.status === 'pending') {
                submitHtml = `
                    <div class="submission-info">
                        <i class="fas fa-clock"></i> Работа отправлена на проверку
                    </div>
                `;
            } else {
                submitHtml = `
                    <button class="submit-btn" data-id="${hw.id}" data-title="${escapeHtml(hw.title)}" data-deadline="${hw.deadline || 'Не указан'}">
                        <i class="fas fa-upload"></i> Отправить работу
                    </button>
                `;
            }
            
            const card = `
                <div class="homework-card" data-id="${hw.id}">
                    <div class="homework-header">
                        <div class="homework-title">
                            <i class="fas fa-book"></i> ${escapeHtml(hw.subject_name)}
                        </div>
                        <span class="homework-status ${statusClass}">${statusText}</span>
                    </div>
                    <div class="homework-body">
                        <div class="homework-description">
                            <strong>${escapeHtml(hw.title)}</strong>
                            ${hw.description ? `<p>${escapeHtml(hw.description)}</p>` : ''}
                        </div>
                        <div class="homework-meta">
                            <div><i class="fas fa-calendar-alt"></i> Дедлайн: ${hw.deadline || 'Не указан'}</div>
                            <div><i class="fas fa-star"></i> Макс. балл: ${hw.max_score}</div>
                        </div>
                        ${hw.file_path ? `
                        <div class="homework-attachment">
                            <a href="${hw.file_path}" target="_blank">
                                <i class="fas fa-paperclip"></i> Скачать задание
                            </a>
                        </div>
                        ` : ''}
                    </div>
                    <div class="homework-submit">
                        ${submitHtml}
                    </div>
                </div>
            `;
            container.append(card);
        });
        
        // Обработчики кнопок
        $('.submit-btn').off('click').on('click', function() {
            const id = $(this).data('id');
            const title = $(this).data('title');
            const deadline = $(this).data('deadline');
            openSubmitModal(id, title, deadline);
        });
        
        $('.view-result-btn').off('click').on('click', function() {
            const score = $(this).data('score');
            const status = $(this).data('status');
            const comment = $(this).data('comment');
            openResultModal(score, status, comment);
        });
    }

    // Открытие модального окна отправки
    function openSubmitModal(id, title, deadline) {
        $('#submit-homework-id').val(id);
        $('#submit-homework-title').text(title);
        $('#submit-homework-deadline').text(`Дедлайн: ${deadline}`);
        $('#submit-comment').val('');
        $('#submit-file').val('');
        $('#submitModal').fadeIn(200).css('display', 'flex');
        $('body').css('overflow', 'hidden');
    }

    function closeSubmitModal() {
        $('#submitModal').fadeOut(200);
        $('body').css('overflow', '');
    }

    function openResultModal(score, status, comment) {
        const isApproved = status === 'approved';
        const statusText = isApproved ? '✅ Работа принята' : '❌ Работа отклонена';
        const statusClass = isApproved ? 'approved' : 'rejected';
        
        $('#result-score-value').text('0');
        $('#result-status').removeClass('approved rejected').addClass(statusClass).html(`<i class="fas ${isApproved ? 'fa-check-circle' : 'fa-times-circle'}"></i> ${statusText}`);
        $('#result-comment-text').html(comment ? escapeHtml(comment) : '<em>Нет комментария</em>');
        
        $('#resultModal').fadeIn(200).css('display', 'flex');
        $('body').css('overflow', 'hidden');
        
        if (score && score > 0) {
            let current = 0;
            const target = parseInt(score);
            const duration = 1000;
            const step = Math.ceil(target / (duration / 20));
            
            const timer = setInterval(function() {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                $('#result-score-value').text(current);
            }, 20);
        } else if (score === 0) {
            $('#result-score-value').text('0');
        } else {
            $('#result-score-value').text('—');
        }
    }

    function closeResultModal() {
        $('#resultModal').fadeOut(200);
        $('body').css('overflow', '');
    }

    // Отправка работы
    $('#submit-form').on('submit', function(e) {
        e.preventDefault();
        
        const homeworkId = $('#submit-homework-id').val();
        const comment = $('#submit-comment').val();
        const file = $('#submit-file')[0].files[0];
        
        if (!file) {
            showAlert('Выберите файл для загрузки', 'error');
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) {
            showAlert('Файл слишком большой. Максимальный размер 10MB', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('comment', comment);
        formData.append('file', file);
        
        $.ajax({
            url: `/api/homework/${homeworkId}/submit`,
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showAlert('Работа успешно отправлена на проверку', 'success');
                    closeSubmitModal();
                    loadHomework(currentSearch);
                } else {
                    showAlert(response.error || 'Ошибка отправки', 'error');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Ошибка отправки';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                } catch(e) {}
                showAlert(errorMsg, 'error');
            }
        });
    });

    // Обработчик поиска с debounce
    let searchTimeout;
    $('#search-homework').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            const search = $('#search-homework').val();
            loadHomework(search);
        }, 500);
    });
    
    // Фильтр по статусу (клиентский)
    $('#status-filter').on('change', function() {
        applyStatusFilter();
    });
    
    // Сброс фильтров
    $('#reset-filters').on('click', function() {
        $('#search-homework').val('');
        $('#status-filter').val('all');
        loadHomework('');
    });

    // Обновление заданий по кнопке
    $('#refresh-homework').on('click', function() {
        const $btn = $(this);
        $btn.addClass('loading').prop('disabled', true);
        const originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Обновление...');
        
        loadHomework(currentSearch);
        
        setTimeout(function() {
            $btn.removeClass('loading').prop('disabled', false);
            $btn.html(originalText);
        }, 1000);
    });
    
    // Закрытие модальных окон
    $('.modal-close, .modal-close-btn').on('click', function() {
        closeSubmitModal();
    });
    
    $('.result-modal-close, .result-close-btn').on('click', function() {
        closeResultModal();
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is('#submitModal')) {
            closeSubmitModal();
        }
        if ($(e.target).is('#resultModal')) {
            closeResultModal();
        }
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            if ($('#submitModal').is(':visible')) {
                closeSubmitModal();
            }
            if ($('#resultModal').is(':visible')) {
                closeResultModal();
            }
        }
    });
    
    // Загружаем задания
    loadHomework('');
});