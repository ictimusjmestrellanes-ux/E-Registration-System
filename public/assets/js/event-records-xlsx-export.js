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
        bar.style.width = '0%';
        progress.setAttribute('aria-valuemin', '0');
        progress.setAttribute('aria-valuemax', '100');
        progress.setAttribute('aria-valuenow', '0');
        help.textContent = 'Large exports are prepared in smaller batches. Keep this page open.';

        const started = Date.now();
        const updateElapsed = () => {
            const seconds = Math.floor((Date.now() - started) / 1000);
            elapsed.textContent = `Elapsed: ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        };
        updateElapsed();
        const timer = setInterval(updateElapsed, 1000);
        window.addEventListener('beforeunload', warnBeforeLeaving);
        modal.show();

        try {
            const postJson = async (url) => {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (response.redirected || response.status === 401 || response.status === 419) {
                    throw new Error('Your session expired. Refresh the page and sign in before exporting again.');
                }
                if (!response.ok) {
                    const error = new Error(`XLSX export failed (HTTP ${response.status}). Please try again.`);
                    error.retryable = [409, 502, 503, 504].includes(response.status);
                    throw error;
                }
                return response.json();
            };

            let state = await postJson(button.href);
            const exportBase = new URL(button.href, window.location.href);
            exportBase.search = '';
            exportBase.hash = '';
            const stepUrl = `${exportBase.href}/${encodeURIComponent(state.token)}/step`;
            const fileUrl = `${exportBase.href}/${encodeURIComponent(state.token)}/download`;
            const showProgress = () => {
                const percent = state.ready ? 100 : Math.min(99, Math.floor(state.completed / Math.max(1, state.total) * 100));
                bar.style.width = `${percent}%`;
                progress.setAttribute('aria-valuenow', String(percent));
                status.textContent = state.completed === state.total
                    ? 'All records prepared. Packaging your XLSX file…'
                    : `Writing records to the XLSX file… ${state.completed.toLocaleString()} of ${state.total.toLocaleString()} (${percent}%)`;
            };
            showProgress();

            while (!state.ready) {
                for (let attempt = 0; ; attempt++) {
                    try {
                        state = await postJson(stepUrl);
                        break;
                    } catch (error) {
                        if (attempt >= 3 || !(error.retryable || error instanceof TypeError)) throw error;
                        help.textContent = 'Reconnecting to your export…';
                        await new Promise(resolve => setTimeout(resolve, 2000 * (attempt + 1)));
                    }
                }
                showProgress();
                help.textContent = 'Keep this page open. Your XLSX file will download when it is ready.';
            }

            status.textContent = 'XLSX generated. Receiving the download…';
            const response = await fetch(fileUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
            });
            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error('Your session expired. Refresh the page and sign in before exporting again.');
            }
            const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
            if (!response.ok || !contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                throw new Error(`XLSX download failed (HTTP ${response.status}). Please try again.`);
            }

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
