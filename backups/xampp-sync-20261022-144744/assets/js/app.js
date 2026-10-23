document.addEventListener('DOMContentLoaded', function () {
    const flash = document.querySelector('.alert');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.3s ease';
            setTimeout(() => flash.remove(), 350);
        }, 4000);
    }
});
