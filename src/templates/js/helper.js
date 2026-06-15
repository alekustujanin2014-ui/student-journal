// Функция показа уведомлений
function showAlert(message, type, spawn = 'main-content') {
    let alertContainer = $('.alert-container');

    if (!alertContainer.length) {
        alertContainer = $('<div class="alert-container"></div>');
        $(`.${spawn}`).prepend(alertContainer);
    }
    alertContainer.empty();

    const alert = $(`
            <div class="alert alert-${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            </div>
        `);

    alertContainer.append(alert);

    setTimeout(() => {
        alert.fadeOut(300, () => alert.remove());
        setTimeout(() => { $('.alert-container').remove(); }, 500)
    }, 1000);

}
// Функция экранирования HTML
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
function goBack() {
    // Проверяем, есть ли предыдущая страница в истории
    if (document.referrer && document.referrer !== '') {
        window.history.back();
    } else {
        // Если нет истории, перенаправляем на главную
        window.location.href = '/';
    }
}
// Экранирование для regex
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
export { showAlert, escapeHtml, goBack, escapeRegex};