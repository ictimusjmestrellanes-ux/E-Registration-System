document.addEventListener('DOMContentLoaded', () => {
    const dropdown = document.getElementById('notificationDropdown');
    const form = document.getElementById('markAllNotificationsReadForm');
    if (!dropdown || !form) return;

    const button = form.querySelector('button[type="submit"]');
    const countLabel = document.getElementById('notificationUnreadLabel');
    const bellBadge = document.getElementById('notificationUnreadBadge');
    const notificationList = document.getElementById('notificationList');
    const dropdownToggle = document.getElementById('page-header-notifications-dropdown');
    const sound = new Audio(dropdown.dataset.soundUrl);
    sound.preload = 'auto';

    const latestKey = `ers-notification-latest-${dropdown.dataset.userId}`;
    let latestId = Number(dropdown.dataset.latestId) || 0;
    let renderedNotificationIds = Array.from(notificationList?.querySelectorAll('[data-notification-id]') || [])
        .map((item) => String(item.dataset.notificationId))
        .join(',');
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

    function notificationAppearance(action) {
        const appearances = {
            events_imported: ['ri-upload-2-line', 'bg-success-subtle text-success', 'Import done'],
            event_transferred: ['ri-exchange-line', 'bg-success-subtle text-success', 'Transfer'],
            events_transfer_selected: ['ri-exchange-line', 'bg-success-subtle text-success', 'Transfer selected'],
            event_force_created: ['ri-user-add-line', 'bg-info-subtle text-info', 'Force create client'],
            events_force_created_all: ['ri-user-add-line', 'bg-info-subtle text-info', 'Force create all'],
            duplicate_event_clients_merged: ['ri-git-merge-line', 'bg-primary-subtle text-primary', 'Clients merged'],
            event_status_tagged: ['ri-price-tag-3-line', 'bg-info-subtle text-info', 'Tag update'],
            events_status_tagged: ['ri-price-tag-3-line', 'bg-info-subtle text-info', 'Tag update'],
            event_deleted: ['ri-delete-bin-line', 'bg-danger-subtle text-danger', 'Deleted'],
            events_bulk_deleted: ['ri-delete-bin-line', 'bg-danger-subtle text-danger', 'Deleted selected'],
            event_transfer_undone: ['ri-arrow-go-back-line', 'bg-warning-subtle text-warning', 'Undo transfer'],
            events_transfer_undone: ['ri-arrow-go-back-line', 'bg-warning-subtle text-warning', 'Undo transfer'],
        };

        return appearances[String(action).toLowerCase()]
            || ['ri-notification-2-line', 'bg-primary-subtle text-primary', String(action).replaceAll('_', ' ')];
    }

    function createNotificationElement(notification) {
        const [icon, colorClass, label] = notificationAppearance(notification.action);
        const item = document.createElement('div');
        item.className = 'text-reset notification-item d-block dropdown-item position-relative';
        item.dataset.notificationId = notification.id;

        const row = document.createElement('div');
        row.className = 'd-flex';

        const avatar = document.createElement('span');
        avatar.className = 'avatar-xs flex-shrink-0 me-3';
        const avatarTitle = document.createElement('span');
        avatarTitle.className = `avatar-title rounded-circle fs-16 ${colorClass}`;
        const iconElement = document.createElement('i');
        iconElement.className = icon;
        avatarTitle.append(iconElement);
        avatar.append(avatarTitle);

        const content = document.createElement('div');
        content.className = 'flex-grow-1';
        const badge = document.createElement('span');
        badge.className = `badge rounded-pill ${colorClass} mb-1`;
        badge.textContent = label;
        const description = document.createElement('h6');
        description.className = 'mt-0 mb-1 fs-13 fw-semibold';
        description.textContent = notification.description || '';
        const meta = document.createElement('p');
        meta.className = 'mb-1 fs-11 fw-medium text-uppercase text-muted';
        meta.append(document.createTextNode(notification.user_name || 'System'));
        const separator = document.createElement('span');
        separator.className = 'mx-1';
        separator.textContent = '─୨ৎ─';
        meta.append(separator, document.createTextNode(notification.time_ago || 'just now'));

        content.append(badge, description, meta);
        row.append(avatar, content);
        item.append(row);
        return item;
    }

    function renderNotifications(notifications) {
        if (!notificationList || !Array.isArray(notifications)) return;
        const nextNotificationIds = notifications.map((notification) => String(notification.id)).join(',');
        if (nextNotificationIds === renderedNotificationIds) return;

        const fragment = document.createDocumentFragment();
        if (notifications.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'text-center py-4';
            empty.dataset.notificationEmpty = '';
            empty.innerHTML = '<i class="ri-notification-off-line fs-24 text-muted"></i><p class="text-muted mt-2 mb-0">No import, transfer, tag, delete, or undo updates yet.</p>';
            fragment.append(empty);
        } else {
            notifications.forEach((notification) => fragment.append(createNotificationElement(notification)));
        }

        notificationList.replaceChildren(fragment);
        renderedNotificationIds = nextNotificationIds;
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
            renderNotifications(state.notifications);
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

    // Keep the open dropdown feeling live, while using a lighter background
    // poll when the user is doing something else on the page.
    setInterval(() => {
        const isOpen = dropdownToggle?.getAttribute('aria-expanded') === 'true';
        if (!isOpen) checkForNotifications();
    }, 20000);
    setInterval(() => {
        const isOpen = dropdownToggle?.getAttribute('aria-expanded') === 'true';
        if (isOpen) checkForNotifications();
    }, 5000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkForNotifications();
    });
    dropdownToggle?.addEventListener('shown.bs.dropdown', checkForNotifications);
});
