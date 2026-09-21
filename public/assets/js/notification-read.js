document.addEventListener('DOMContentLoaded', () => {
    const dropdown = document.getElementById('notificationDropdown');
    const form = document.getElementById('markAllNotificationsReadForm');
    if (!dropdown || !form) return;

    const button = form.querySelector('button[type="submit"]');
    const countLabel = document.getElementById('notificationUnreadLabel');
    const bellBadge = document.getElementById('notificationUnreadBadge');
    const sound = new Audio(dropdown.dataset.soundUrl);
    sound.preload = 'auto';

    const latestKey = `ers-notification-latest-${dropdown.dataset.userId}`;
    let latestId = Number(dropdown.dataset.latestId) || 0;
    let requestVersion = 0;
    let polling = false;

    function playRingtone() {
        sound.currentTime = 0;
        sound.play().catch(() => {
            // Browsers can block sound until the user interacts with the page.
        });
    }

    function rememberLatest(id) {
        try {
            sessionStorage.setItem(latestKey, String(id));
        } catch (error) {
            // Private browsing may disable session storage.
        }
    }

    try {
        const previousId = Number(sessionStorage.getItem(latestKey));
        if (previousId > 0 && latestId > previousId && Number(dropdown.dataset.unreadCount) > 0) {
            playRingtone();
        }
    } catch (error) {
        // The live check below still works if session storage is unavailable.
    }
    rememberLatest(latestId);

    function updateCount(count) {
        const unreadCount = Math.max(0, Number(count) || 0);
        const displayCount = unreadCount > 99 ? '99+' : String(unreadCount);
        if (countLabel) countLabel.textContent = `${displayCount} New`;
        if (bellBadge) {
            if (bellBadge.firstChild) bellBadge.firstChild.textContent = displayCount;
            bellBadge.classList.toggle('d-none', unreadCount === 0);
        }
        form.classList.toggle('d-none', unreadCount === 0);
    }

    async function checkForNotifications() {
        if (polling || button.disabled || document.visibilityState === 'hidden') return;
        polling = true;
        const version = requestVersion;
        try {
            const response = await fetch(dropdown.dataset.stateUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            const state = await response.json();
            if (version !== requestVersion) return;

            const nextId = Number(state.latest_id) || 0;
            const unreadCount = Number(state.unread_count) || 0;
            if (nextId > latestId) {
                latestId = nextId;
                rememberLatest(latestId);
                if (unreadCount > 0) playRingtone();
            }
            updateCount(unreadCount);
        } catch (error) {
            // Leave the current count in place until the next check.
        } finally {
            polling = false;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (button.disabled) return;
        requestVersion++;
        button.disabled = true;
        form.querySelector('[data-notification-error]')?.remove();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Request failed');

            const result = await response.json();
            if (!result.success) throw new Error('Request failed');
            updateCount(result.unread_count);
        } catch (error) {
            const message = document.createElement('div');
            message.className = 'text-danger small mt-1';
            message.dataset.notificationError = '';
            message.textContent = 'Could not mark notifications as read. Please try again.';
            form.appendChild(message);
        } finally {
            button.disabled = false;
        }
    });

    setInterval(checkForNotifications, 20000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkForNotifications();
    });
    document.getElementById('page-header-notifications-dropdown')
        ?.addEventListener('shown.bs.dropdown', checkForNotifications);
});
