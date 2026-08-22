/* Gestion du mode sombre + couleur de menu personnalisable (BAIL MANAGER) */
(function () {
    var THEME_KEY = 'bm_theme';
    var COLOR_KEY = 'bm_menu_color';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
    }
    function applyMenuColor(color) {
        document.documentElement.style.setProperty('--menu-color', color);
    }

    // Conversions HSL <-> HEX pour la barre spectrum (teinte libre)
    function hslToHex(h, s, l) {
        s /= 100; l /= 100;
        var k = function (n) { return (n + h / 30) % 12; };
        var a = s * Math.min(l, 1 - l);
        var f = function (n) { return l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1))); };
        var toHex = function (x) { return Math.round(255 * x).toString(16).padStart(2, '0'); };
        return '#' + toHex(f(0)) + toHex(f(8)) + toHex(f(4));
    }
    function hexToHue(hex) {
        if (!hex) return 210;
        var r = parseInt(hex.substr(1, 2), 16) / 255;
        var g = parseInt(hex.substr(3, 2), 16) / 255;
        var b = parseInt(hex.substr(5, 2), 16) / 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min, h = 0;
        if (d !== 0) {
            if (max === r) h = ((g - b) / d) % 6;
            else if (max === g) h = (b - r) / d + 2;
            else h = (r - g) / d + 4;
            h *= 60;
            if (h < 0) h += 360;
        }
        return h;
    }

    // Applique immédiatement les préférences sauvegardées (limite le flash visuel)
    var savedTheme = localStorage.getItem(THEME_KEY) || 'light';
    var savedColor = localStorage.getItem(COLOR_KEY);
    applyTheme(savedTheme);
    if (savedColor) applyMenuColor(savedColor);

    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('themeToggleBtn');
        var toggleIcon = document.getElementById('themeToggleIcon');
        var colorInput = document.getElementById('menuColorInput');
        var swatches = document.querySelectorAll('.color-swatch');

        function updateToggleIcon() {
            if (!toggleIcon) return;
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            toggleIcon.className = isDark ? 'fa fa-sun' : 'fa fa-moon';
        }
        function updateActiveSwatch(color) {
            swatches.forEach(function (sw) {
                sw.classList.toggle('active', sw.dataset.color.toLowerCase() === (color || '').toLowerCase());
            });
        }

        updateToggleIcon();
        updateActiveSwatch(savedColor);

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                localStorage.setItem(THEME_KEY, next);
                updateToggleIcon();
            });
        }

        swatches.forEach(function (sw) {
            sw.addEventListener('click', function () {
                var color = sw.dataset.color;
                applyMenuColor(color);
                localStorage.setItem(COLOR_KEY, color);
                if (colorInput) colorInput.value = color;
                updateActiveSwatch(color);
            });
        });

        if (colorInput) {
            if (savedColor) colorInput.value = savedColor;
            colorInput.addEventListener('input', function () {
                applyMenuColor(colorInput.value);
                localStorage.setItem(COLOR_KEY, colorInput.value);
                updateActiveSwatch(colorInput.value);
                setHandleByHue(hexToHue(colorInput.value));
            });
        }

        // Barre spectrum — teinte libre, en plus des pastilles rapides
        var spectrumBar    = document.getElementById('spectrumBar');
        var spectrumHandle = document.getElementById('spectrumHandle');

        function setHandleByHue(hue) {
            if (spectrumHandle) spectrumHandle.style.left = (hue / 360 * 100) + '%';
        }
        function pickFromEvent(e) {
            var rect = spectrumBar.getBoundingClientRect();
            var clientX = e.touches ? e.touches[0].clientX : e.clientX;
            var x = Math.max(0, Math.min(rect.width, clientX - rect.left));
            var hue = (x / rect.width) * 360;
            var color = hslToHex(hue, 65, 32);
            applyMenuColor(color);
            localStorage.setItem(COLOR_KEY, color);
            if (colorInput) colorInput.value = color;
            updateActiveSwatch(color);
            setHandleByHue(hue);
        }
        if (spectrumBar) {
            setHandleByHue(hexToHue(savedColor || '#002147'));
            var dragging = false;
            spectrumBar.addEventListener('mousedown', function (e) { dragging = true; pickFromEvent(e); });
            window.addEventListener('mousemove', function (e) { if (dragging) pickFromEvent(e); });
            window.addEventListener('mouseup', function () { dragging = false; });
            spectrumBar.addEventListener('touchstart', pickFromEvent);
            spectrumBar.addEventListener('touchmove', pickFromEvent);
        }
    });
})();
