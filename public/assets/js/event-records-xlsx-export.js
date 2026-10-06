document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('recordExportXlsxBtn');
    const modalElement = document.getElementById('recordXlsxProgressModal');
    if (!button || !modalElement) return;

    const status = document.getElementById('recordXlsxProgressStatus');
    const progress = document.getElementById('recordXlsxProgressBar');
    const help = document.getElementById('recordXlsxProgressHelp');
    const elapsed = document.getElementById('recordXlsxProgressElapsed');
    const close = document.getElementById('recordXlsxProgressClose');
    const download = document.getElementById('recordXlsxDownload');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    let busy = false;
    let downloadUrl = null;

    const warnBeforeLeaving = (event) => {
        if (!busy) return;
        event.preventDefault();
        event.returnValue = '';
    };

    const filenameFrom = (disposition) => {
        const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
        if (encoded) {
            try {
                return decodeURIComponent(encoded);
            } catch (_) {
                // Fall through to the ordinary filename form.
            }
        }
        return disposition.match(/filename="?([^";\\/]+)"?/i)?.[1] || 'event-records.xlsx';
    };

    const newOperationId = () => {
        if (typeof globalThis.crypto?.randomUUID === 'function') return globalThis.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
            const random = Math.floor(Math.random() * 16);
            const value = character === 'x' ? random : (random & 0x3) | 0x8;
            return value.toString(16);
        });
    };

    button.addEventListener('click', async (event) => {
        // Preserve open-in-new-tab and the normal link fallback when modified.
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (busy) return;

        busy = true;
        button.setAttribute('aria-disabled', 'true');
        button.setAttribute('aria-busy', 'true');
        button.classList.add('disabled');
        close.disabled = true;
        download.classList.add('d-none');
        download.removeAttribute('href');
        if (downloadUrl) URL.revokeObjectURL(downloadUrl);
        downloadUrl = null;
        status.textContent = 'Preparing your filtered records in alphabetical order…';
        status.classList.remove('text-danger');
        progress.classList.remove('d-none');
        const bar = progress.querySelector('.progress-bar');
        bar.classList.remove('w-100');
        bar.style.width = '5%';
        progress.setAttribute('aria-valuemin', '0');
        progress.setAttribute('aria-valuemax', '100');
        progress.setAttribute('aria-valuenow', '5');
        help.textContent = 'Large exports may take a few minutes. Keep this page open.';

        const started = Date.now();
        const updateElapsed = () => {
            const seconds = Math.floor((Date.now() - started) / 1000);
            elapsed.textContent = `Elapsed: ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        };
        updateElapsed();
        const timer = setInterval(updateElapsed, 1000);
        window.addEventListener('beforeunload', warnBeforeLeaving);
        modal.show();

        const operationId = newOperationId();
        const exportUrl = new URL(button.href, window.location.href);
        exportUrl.searchParams.set('export_operation_id', operationId);
        const progressUrl = button.dataset.progressUrl.replace('__operation__', encodeURIComponent(operationId));
        let progressRequestBusy = false;
        const updateProgress = async () => {
            if (progressRequestBusy || !busy) return;
            progressRequestBusy = true;
            try {
                const response = await fetch(progressUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) return;
                const state = await response.json();
                const total = Math.max(0, Number(state.total) || 0);
                const completed = Math.min(total, Math.max(0, Number(state.completed) || 0));
                const percent = state.state === 'complete'
                    ? 100
                    : (total > 0 ? Math.min(99, Math.floor(completed / total * 100)) : 5);
                bar.style.width = `${percent}%`;
                progress.setAttribute('aria-valuenow', String(percent));
                status.textContent = total > 0 && state.state === 'working'
                    ? `${state.message} ${completed.toLocaleString()} of ${total.toLocaleString()} (${percent}%)`
                    : state.message;
            } catch (_) {
                // The download request remains authoritative; a missed poll is harmless.
            } finally {
                progressRequestBusy = false;
            }
        };
        const progressTimer = setInterval(updateProgress, 500);

        try {
            const response = await fetch(exportUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                },
            });
            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error('Your session expired. Refresh the page and sign in before exporting again.');
            }
            const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
            if (!response.ok || !contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                throw new Error(`XLSX export failed (HTTP ${response.status}). Please try again.`);
            }

            bar.style.width = '100%';
            progress.setAttribute('aria-valuenow', '100');
            status.textContent = 'XLSX generated. Receiving the download…';
            const blob = await response.blob();
            if (blob.size < 4 || await blob.slice(0, 2).text() !== 'PK') {
                throw new Error('The XLSX download was incomplete. Please try again.');
            }

            downloadUrl = URL.createObjectURL(blob);
            download.href = downloadUrl;
            download.download = filenameFrom(response.headers.get('Content-Disposition') || '');
            download.classList.remove('d-none');
            download.click();
            status.textContent = 'Your XLSX file is ready.';
            help.textContent = 'The download has started. If it does not appear, click Download XLSX below.';
        } catch (error) {
            status.textContent = 'XLSX export could not be completed.';
            status.classList.add('text-danger');
            help.textContent = error instanceof TypeError
                ? 'The connection was interrupted. Check your connection and try Export XLSX again.'
                : error.message;
        } finally {
            clearInterval(timer);
            clearInterval(progressTimer);
            updateElapsed();
            busy = false;
            progress.classList.add('d-none');
            close.disabled = false;
            button.removeAttribute('aria-disabled');
            button.removeAttribute('aria-busy');
            button.classList.remove('disabled');
            window.removeEventListener('beforeunload', warnBeforeLeaving);
        }
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
        if (busy || !downloadUrl) return;
        URL.revokeObjectURL(downloadUrl);
        downloadUrl = null;
        download.removeAttribute('href');
    });
});
