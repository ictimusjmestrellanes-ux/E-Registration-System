document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('recordExportPdfBtn');
    const modalElement = document.getElementById('recordPdfProgressModal');
    if (!button || !modalElement) return;

    const status = document.getElementById('recordPdfProgressStatus');
    const progress = document.getElementById('recordPdfProgressBar');
    const help = document.getElementById('recordPdfProgressHelp');
    const elapsed = document.getElementById('recordPdfProgressElapsed');
    const close = document.getElementById('recordPdfProgressClose');
    const download = document.getElementById('recordPdfDownload');
    let busy = false;
    let downloadUrl = null;

    const warnBeforeLeaving = (event) => {
        if (!busy) return;
        event.preventDefault();
        event.returnValue = '';
    };

    const detailsElement = document.getElementById('recordPdfDetailsModal');
    const detailsForm = document.getElementById('recordPdfDetailsForm');
    button.addEventListener('click', (event) => {
        // Preserve normal open-in-new-tab behavior and the non-JavaScript link.
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (busy) return;
        bootstrap.Modal.getOrCreateInstance(detailsElement).show();
    });

    detailsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        busy = true;
        const formData = new FormData(detailsForm);
        const details = Object.fromEntries(formData);
        details.numbered_tranches = formData.getAll('numbered_tranches').map(Number);
        await new Promise(resolve => {
            detailsElement.addEventListener('hidden.bs.modal', resolve, { once: true });
            bootstrap.Modal.getOrCreateInstance(detailsElement).hide();
        });
        button.setAttribute('aria-disabled', 'true');
        button.setAttribute('aria-busy', 'true');
        button.classList.add('disabled');
        close.disabled = true;
        download.classList.add('d-none');
        if (downloadUrl) URL.revokeObjectURL(downloadUrl);
        downloadUrl = null;
        download.removeAttribute('href');
        status.textContent = 'Preparing your filtered records in alphabetical order…';
        status.classList.remove('text-danger');
        progress.classList.remove('d-none');
        const bar = progress.querySelector('.progress-bar');
        bar.classList.remove('w-100');
        bar.style.width = '0%';
        progress.setAttribute('aria-valuemin', '0');
        progress.setAttribute('aria-valuemax', '100');
        progress.setAttribute('aria-valuenow', '0');
        help.textContent = 'Large exports may take a few minutes. Keep this page open.';
        const started = Date.now();
        const updateElapsed = () => {
            const seconds = Math.floor((Date.now() - started) / 1000);
            elapsed.textContent = `Elapsed: ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        };
        updateElapsed();
        const timer = setInterval(updateElapsed, 1000);
        window.addEventListener('beforeunload', warnBeforeLeaving);
        bootstrap.Modal.getOrCreateInstance(modalElement).show();

        try {
            const postProgress = async (url, body) => {
                const result = await fetch(url, {
                    method: 'POST', credentials: 'same-origin',
                    body: body === undefined ? undefined : JSON.stringify(body),
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                if (result.status === 401 || result.status === 419 || result.redirected) {
                    throw new Error('Your session expired. Refresh the page and sign in before exporting again.');
                }
                if (!result.ok) {
                    const error = new Error(`PDF export failed (HTTP ${result.status}). Please try again.`);
                    error.retryable = [409, 502, 503, 504].includes(result.status);
                    throw error;
                }
                return result.json();
            };
            let state = await postProgress(button.href, details);
            const exportBase = new URL(button.href);
            exportBase.search = '';
            exportBase.hash = '';
            const stepUrl = `${exportBase.href}/${encodeURIComponent(state.token)}/step`;
            const fileUrl = `${exportBase.href}/${encodeURIComponent(state.token)}/download`;
            const showProgress = () => {
                const percent = state.ready ? 100 : Math.min(99, Math.floor(state.completed / Math.max(1, state.total) * 100));
                bar.style.width = `${percent}%`;
                progress.setAttribute('aria-valuenow', String(percent));
                status.textContent = state.completed === state.total
                    ? 'All records prepared. Combining your PDF…'
                    : `Preparing PDF: ${state.completed.toLocaleString()} of ${state.total.toLocaleString()} records (${percent}%)`;
            };
            showProgress();
            while (!state.ready) {
                for (let attempt = 0; ; attempt++) {
                    try {
                        state = await postProgress(stepUrl);
                        break;
                    } catch (error) {
                        if (attempt >= 3 || !(error.retryable || error instanceof TypeError)) throw error;
                        help.textContent = 'Reconnecting to your export…';
                        await new Promise(resolve => setTimeout(resolve, 2000 * (attempt + 1)));
                    }
                }
                showProgress();
                help.textContent = 'Keep this page open. Your PDF will download when all records are ready.';
            }
            const response = await fetch(fileUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/pdf' },
            });
            if (response.redirected || response.status === 401 || response.status === 419) {
                throw new Error('Your session may have expired. Refresh the page, sign in if needed, and try again.');
            }
            if (!response.ok) {
                throw new Error(`PDF export failed (HTTP ${response.status}). Please try again. If it keeps failing, contact your administrator.`);
            }
            if (!(response.headers.get('Content-Type') || '').toLowerCase().includes('application/pdf')) {
                throw new Error('The server did not return a PDF. Refresh the page and try again.');
            }
            status.textContent = 'PDF generated. Receiving the download…';
            const blob = await response.blob();
            if (!blob.size || await blob.slice(0, 5).text() !== '%PDF-') {
                throw new Error('The PDF download was incomplete. Please try again.');
            }
            const disposition = response.headers.get('Content-Disposition') || '';
            const filename = disposition.match(/filename="([^"\\/]+)"/i)?.[1] || 'event_records.pdf';
            downloadUrl = URL.createObjectURL(blob);
            download.href = downloadUrl;
            download.download = filename;
            download.classList.remove('d-none');
            download.click();
            status.textContent = 'Your PDF is ready.';
            help.textContent = 'The download has started. If it does not appear, click Download PDF below.';
        } catch (error) {
            status.textContent = 'PDF export could not be completed.';
            status.classList.add('text-danger');
            help.textContent = error instanceof TypeError
                ? 'The connection was interrupted. Check your connection and try Export PDF again.'
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
});
