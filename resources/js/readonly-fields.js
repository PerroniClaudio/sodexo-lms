// Evidenzia i campi readonly con il cursore di errore e tooltip
window.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[readonly], select[readonly], textarea[readonly]').forEach(function (el) {
        el.classList.add('cursor-not-allowed');
        el.setAttribute('title', 'Non modificabile');
    });
});
