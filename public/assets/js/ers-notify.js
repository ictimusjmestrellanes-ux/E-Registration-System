/* ERS pending toast: queue a notification before redirect/reload so it
 * survives navigation (AJAX flows like import/transfer/tag/undo).
 * Stored in localStorage, flushed by the master layout on next page load
 * via the shared `Message` (imessage) toast. */
(function () {
    const KEY = 'ers_pending_toast';

    function normalizeType(type) {
        if (type === 'error' || type === 'danger') return 'fail';
        if (type === 'success' || type === 'fail' || type === 'warning' || type === 'info') return type;
        return 'success';
    }

    window.ErsNotify = {
        queue(message, type) {
            if (!message) return;
            try {
                localStorage.setItem(KEY, JSON.stringify({
                    message: String(message),
                    type: normalizeType(type),
                }));
            } catch (e) {
                /* storage unavailable: ignore */
            }
        },
        flush(position, timeout) {
            let pending = null;
            try {
                const raw = localStorage.getItem(KEY);
                if (!raw) return false;
                pending = JSON.parse(raw);
                localStorage.removeItem(KEY);
            } catch (e) {
                try {
                    localStorage.removeItem(KEY);
                } catch (ignored) {}
                return false;
            }
            if (!pending || !pending.message) return false;
            if (typeof Message === 'undefined') return false;
            new Message('imessage').show(
                pending.message,
                normalizeType(pending.type),
                position || 'top-center',
                timeout || 5000
            );
            return true;
        },
    };
})();
