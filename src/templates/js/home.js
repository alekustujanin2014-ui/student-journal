
import { showAlert, escapeHtml } from './helper.js';

$(document).ready(function () {

    const modal = $('#newsModal');
    const modalTitle = $('#modal-title');
    const modalMeta = $('#modal-meta');
    const modalText = $('#modal-text');

    // Функция закрытия модального окна
    function closeModal() {
        modal.fadeOut(200);
        $('body').css('overflow', '');
    }

    // Функция открытия модального окна с загрузкой новости
    function openNewsModal(newsId) {
        modal.fadeIn(200);
        modal.css('display', 'flex');
        $('body').css('overflow', 'hidden');

        // Показываем загрузку
        modalTitle.text('Загрузка...');
        modalMeta.empty();
        modalText.html('<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Загрузка новости...</div>');
        
        // Загружаем новость через API
        $.ajax({
            url: '/api/news/' + newsId,
            method: 'GET',
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    const news = data.data;

                    modalTitle.text(news.title);

                    const publishedDate = new Date(news.published_at);
                    const formattedDate = publishedDate.toLocaleDateString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    let metaHtml = `<span><i class="fas fa-calendar-alt"></i> ${formattedDate}</span>`;
                    if (news.author_name) {
                        metaHtml += `<span><i class="fas fa-user"></i> ${news.author_name}</span>`;
                    }
                    metaHtml += `<span><i class="fas fa-eye"></i> ${news.views || 0} просмотров</span>`;

                    modalMeta.html(metaHtml);
                    modalText.html(news.content.replace(/\n/g, '<br>'));
                } else {
                    throw new Error(data.error || 'Ошибка загрузки');
                }
            },
            error: function (xhr, status, error) {
                let errorMsg = 'Новость не найдена';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                modalText.html(`
                    <div style="text-align: center; padding: 40px; color: #dc3545;">
                        <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                        <p>${errorMsg}</p>
                    </div>
                `);
            }
        });
    }

    // Навешиваем обработчики на кнопки "Подробнее"
    $(document).on('click', '.news-link-btn', function (e) {
        e.preventDefault();
        const newsId = $(this).data('id');
        openNewsModal(newsId);
    });

    // Закрытие модального окна
    $('.modal-close, .modal-close-btn').on('click', closeModal);

    // Закрытие по клику вне модального окна
    $(window).on('click', function (e) {
        if ($(e.target).is(modal)) {
            closeModal();
        }
    });

    // Закрытие по Escape
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && modal.is(':visible')) {
            closeModal();
        }
    });

    function updateNewsList(news) {
        const newsGrid = $('.news-grid');
        newsGrid.empty();

        if (!newsGrid.length) {
            newsGrid.html(`<div class="news-card">
                <div class="news-date">—</div>
                <div class="news-title">Новостей пока нет</div>
                <div class="news-excerpt">Следите за обновлениями</div>
            </div>`);
        }

        news.forEach(item => {
            const date = new Date(item.published_at);
            const formattedDate = `${date.getDate().toString().padStart(2, '0')}.${(date.getMonth() + 1).toString().padStart(2, '0')}.${date.getFullYear()}`;

            newsGrid.append(`<div class="news-card">

                <div class="news-date">${escapeHtml(formattedDate)}</div>
                <div class="news-title">${escapeHtml(item.title)}</div>
                <div class="news-excerpt">${escapeHtml(item.excerpt || (new Array(item.content).slice(0, 100)))}</div>
                <div class="news-stats">
                    <i class="fas fa-eye"></i> ${escapeHtml(item.views) || 0} просмотров
                </div>
                <button class="news-link-btn" data-id="${escapeHtml(item.id)}">Подробнее →</button>
            </div>`);
        });
        return;
    }

    // Обновление новостей
    const refreshBtn = $('#refresh-news');
    if (refreshBtn.length) {
        refreshBtn.on('click', function () {
            const $btn = $(this);
            $btn.addClass('loading').prop('disabled', true);
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Обновление...');

            $.ajax({
                url: '/api/news/latest',
                method: 'GET',
                dataType: 'json',
                success: function (data) {
                    if (data.success) {
                        updateNewsList(data.data);
                    } else {
                        showAlert(data.error || 'Ошибка загрузки новостей', 'error');
                    }
                },
                error: function (data) {
                    showAlert(data.error || 'Ошибка соединения', 'error');
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

});

