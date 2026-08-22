/* Notifications toast (BAIL MANAGER) — window.showToast(message, type, duration) */
(function () {
    var ICONS = {
        success: 'fa-circle-check',
        error:   'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info:    'fa-circle-info'
    };
    var DEFAULT_DURATION = 5000;

    function ensureStack() {
        var stack = document.getElementById('toastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'toastStack';
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    function showToast(message, type, duration) {
        type = ICONS[type] ? type : 'info';
        duration = duration || DEFAULT_DURATION;

        var stack = ensureStack();
        var el = document.createElement('div');
        el.className = 'bm-toast bm-toast-' + type;
        el.innerHTML =
            '<div class="bm-toast-icon"><i class="fa ' + ICONS[type] + '"></i></div>' +
            '<div class="bm-toast-body"></div>' +
            '<button type="button" class="bm-toast-close" aria-label="Fermer"><i class="fa fa-xmark"></i></button>' +
            '<div class="bm-toast-progress"></div>';
        el.querySelector('.bm-toast-body').textContent = message;
        stack.appendChild(el);

        requestAnimationFrame(function () { el.classList.add('show'); });

        var bar = el.querySelector('.bm-toast-progress');
        bar.style.animationDuration = duration + 'ms';

        var timer = setTimeout(function () { dismiss(); }, duration);

        el.addEventListener('mouseenter', function () {
            clearTimeout(timer);
            bar.style.animationPlayState = 'paused';
        });
        el.addEventListener('mouseleave', function () {
            timer = setTimeout(function () { dismiss(); }, duration);
            bar.style.animationPlayState = 'running';
        });
        el.querySelector('.bm-toast-close').addEventListener('click', function () {
            clearTimeout(timer);
            dismiss();
        });

        function dismiss() {
            el.classList.remove('show');
            el.classList.add('hide');
            el.addEventListener('transitionend', function () { el.remove(); }, { once: true });
        }
    }

    window.showToast = showToast;

    document.addEventListener('DOMContentLoaded', function () {
        if (Array.isArray(window.__bmFlashMessages)) {
            window.__bmFlashMessages.forEach(function (f) {
                showToast(f.message, f.type);
            });
        }
    });
})();
