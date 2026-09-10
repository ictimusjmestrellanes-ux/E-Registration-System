document.addEventListener('DOMContentLoaded', () => {
    const chartIds = [
        'clientTrendChart', 'transactionTrendChart', 'transactionDateChart',
        'caravanTrendChart', 'clientCategoryChart', 'serviceCategoryChart',
    ];

    chartIds.forEach((id) => {
        const canvas = document.getElementById(id);
        const card = canvas?.closest('.card') ||
            (id === 'serviceCategoryChart' ? document.getElementById('serviceCategoryCard') : null);
        const header = card?.querySelector('.card-header');
        if (!header) return;

        // Keep a plain title and subtitle together when adding the header action.
        if (header.firstElementChild?.matches('h5')) {
            const heading = document.createElement('div');
            heading.append(...header.children);
            header.append(heading);
        }
        header.classList.add('d-flex', 'flex-wrap', 'gap-2', 'align-items-center', 'justify-content-between');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-primary ms-auto';
        button.innerHTML = '<i class="ri-external-link-line me-1" aria-hidden="true"></i>Export';
        button.setAttribute('aria-label', `Export ${header.querySelector('h5').textContent.trim()} (opens preview in a new tab)`);
        header.append(button);

        button.addEventListener('click', async () => {
            if (canvas?.getAttribute('aria-busy') === 'true') {
                window.alert('Please wait for the chart to finish updating, then export again.');
                return;
            }

            const preview = window.open('', '_blank');
            if (!preview) {
                window.alert('Please allow pop-ups for this site to open the chart preview.');
                return;
            }
            preview.opener = null;
            preview.document.body.textContent = 'Preparing chart preview…';
            let imageUrl = null;
            let exportChart = null;
            let exportCanvas = null;
            try {
                let chart = canvas && window.Chart?.getChart(canvas);
                let sourceCanvas = canvas;
                let hasData = canvas && !canvas.classList.contains('d-none');
                if (id === 'clientCategoryChart' && chart) {
                    const url = new URL(canvas.dataset.exportUrl, window.location.origin);
                    const filters = new URL(window.location.href).searchParams;
                    filters.forEach((value, key) => {
                        if (/^distribution_(category|type)(\[\d*\])?$/.test(key)) {
                            url.searchParams.append(key, value);
                        }
                    });
                    url.searchParams.set('export', '1');
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) throw new Error('Export data could not be loaded.');
                    hasData = payload.labels.length > 0;
                    if (hasData) {
                        exportCanvas = document.createElement('canvas');
                        exportCanvas.width = 1400;
                        exportCanvas.height = Math.max(400, payload.labels.length * 60 + 80);
                        exportCanvas.style.cssText = 'position:fixed;left:-20000px;top:0;pointer-events:none';
                        document.body.append(exportCanvas);
                        const original = chart.config;
                        const colors = original.data.datasets[0].backgroundColor;
                        exportChart = new Chart(exportCanvas, {
                            type: original.type,
                            plugins: original.plugins,
                            data: {
                                labels: payload.labels,
                                datasets: [{ ...original.data.datasets[0], data: payload.data,
                                    backgroundColor: payload.labels.map((_, index) => Array.isArray(colors) ? colors[index % colors.length] : colors) }],
                            },
                            options: { ...original.options, responsive: false, animation: false,
                                devicePixelRatio: 2, layout: { padding: { right: 160 } } },
                        });
                        chart = exportChart;
                        sourceCanvas = exportCanvas;
                    }
                }
                if (chart && hasData && sourceCanvas.width && sourceCanvas.height) {
                    // Finish animations before taking a snapshot, preserving legend visibility.
                    chart.stop();
                    chart.update('none');
                    const image = document.createElement('canvas');
                    image.width = sourceCanvas.width;
                    image.height = sourceCanvas.height;
                    const context = image.getContext('2d');
                    context.fillStyle = '#ffffff';
                    context.fillRect(0, 0, image.width, image.height);
                    context.drawImage(sourceCanvas, 0, 0);
                    imageUrl = image.toDataURL('image/png');
                }
            } catch (error) {
                if (!preview.closed) preview.document.body.textContent = 'The chart could not be exported. Close this tab and try again from the dashboard.';
                return;
            } finally {
                exportChart?.destroy();
                exportCanvas?.remove();
            }

            if (preview.closed) return;
            const doc = preview.document;
            doc.open();
            doc.write(`<!doctype html><html lang="en"><head><meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Chart export preview</title><style>
                *{box-sizing:border-box}body{margin:0;background:#f3f5f9;color:#212529;font:16px system-ui,sans-serif}
                main{max-width:1200px;margin:32px auto;padding:32px;background:white;border-radius:12px}
                nav{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px}
                button,a{font:inherit;border:1px solid #405189;border-radius:6px;padding:10px 16px;cursor:pointer;text-decoration:none;background:white;color:#405189}
                a{background:#405189;color:white}h1{font-size:24px;margin:0 0 12px}p{color:#606b78;white-space:pre-line}
                img{display:block;width:100%;height:auto;margin-top:28px}#empty{padding:60px 20px;text-align:center;border:1px dashed #ccd2dc}
                @media(max-width:600px){main{margin:12px;padding:20px}}
                @media print{@page{size:landscape;margin:12mm}body{background:white}main{margin:0;padding:0;max-width:none}nav{display:none}img{max-height:70vh;object-fit:contain}h1{font-size:20px}}
                </style></head><body><main><nav></nav><h1></h1><p id="description"></p>
                <p id="generated"></p><div id="chart"></div></main></body></html>`);
            doc.close();

            const title = header.querySelector('h5').textContent.trim();
            doc.title = `${title} — Export preview`;
            doc.querySelector('h1').textContent = title;
            doc.getElementById('description').textContent = header.querySelector('p')?.textContent.trim() || '';
            doc.getElementById('generated').textContent = `Captured ${new Date().toLocaleString()} • ${id === 'clientCategoryChart' ? 'All client categories for the selected filters' : 'Current dashboard view'}`;
            const nav = doc.querySelector('nav');
            const print = doc.createElement('button');
            print.textContent = 'Print / Save as PDF';
            print.addEventListener('click', () => preview.print());
            nav.append(print);
            const close = doc.createElement('button');
            close.textContent = 'Close preview';
            close.addEventListener('click', () => preview.close());
            nav.append(close);

            if (imageUrl) {
                const image = doc.createElement('img');
                image.alt = title;
                print.disabled = true;
                image.onload = () => { print.disabled = false; };
                image.src = imageUrl;
                doc.getElementById('chart').append(image);
                const download = doc.createElement('a');
                download.textContent = 'Download PNG';
                download.href = imageUrl;
                download.download = `${id.replace(/Chart$/, '').replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()}.png`;
                nav.prepend(download);
            } else {
                const empty = doc.createElement('p');
                empty.id = 'empty';
                empty.textContent = 'No chart data is available for the current selection.';
                doc.getElementById('chart').append(empty);
            }
        });
    });
});
