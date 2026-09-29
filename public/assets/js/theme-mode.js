(function () {
    'use strict';

    var storageKey = 'ers-color-mode';
    var root = document.documentElement;

    function getSavedMode() {
        try {
            var savedMode = localStorage.getItem(storageKey);
            return savedMode === 'dark' || savedMode === 'light' ? savedMode : 'light';
        } catch (error) {
            return 'light';
        }
    }

    function syncThemeSession(mode) {
        try {
            sessionStorage.setItem('data-bs-theme', mode);

            var defaults = sessionStorage.getItem('defaultAttribute');
            if (!defaults) {
                return;
            }

            var attributes = JSON.parse(defaults);
            attributes['data-bs-theme'] = mode;
            sessionStorage.setItem('defaultAttribute', JSON.stringify(attributes));
        } catch (error) {
            // Storage can be unavailable in privacy-restricted browser contexts.
        }
    }

    function updateControls(mode) {
        var isDark = mode === 'dark';

        document.querySelectorAll('[data-color-mode-toggle]').forEach(function (button) {
            var actionLabel = isDark ? 'Switch to light mode' : 'Switch to night mode';
            var icon = button.querySelector('[data-color-mode-icon]');

            button.setAttribute('aria-label', actionLabel);
            button.setAttribute('aria-pressed', String(isDark));
            button.setAttribute('title', actionLabel);

            if (icon) {
                icon.classList.toggle('bx-moon', !isDark);
                icon.classList.toggle('bx-sun', isDark);
            }
        });
    }

    function applyMode(mode, savePreference) {
        root.setAttribute('data-bs-theme', mode);
        root.style.colorScheme = mode;
        syncThemeSession(mode);

        if (savePreference) {
            try {
                localStorage.setItem(storageKey, mode);
            } catch (error) {
                // The active page still changes theme when persistence is blocked.
            }
        }

        updateControls(mode);
        window.dispatchEvent(new CustomEvent('ers:color-mode-changed', {
            detail: { mode: mode }
        }));
    }

    var initialMode = getSavedMode();
    root.setAttribute('data-bs-theme', initialMode);
    root.style.colorScheme = initialMode;
    syncThemeSession(initialMode);

    document.addEventListener('DOMContentLoaded', function () {
        updateControls(root.getAttribute('data-bs-theme') || initialMode);
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-color-mode-toggle]');
        if (!button) {
            return;
        }

        var nextMode = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        applyMode(nextMode, true);
    });

    window.addEventListener('storage', function (event) {
        if (event.key === storageKey && (event.newValue === 'dark' || event.newValue === 'light')) {
            applyMode(event.newValue, false);
        }
    });
}());
