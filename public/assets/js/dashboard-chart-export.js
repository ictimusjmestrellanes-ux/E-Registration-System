document.addEventListener('DOMContentLoaded', () => {
    const chartIds = [
        'clientTrendChart', 'transactionTrendChart', 'transactionDateChart',
        'caravanTrendChart', 'clientCategoryChart', 'addressDistributionChart', 'serviceCategoryChart',
    ];

    const logChartExport = async (chartId, format) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(window.dashboardChartExportLogUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({ chart_id: chartId, format }),
        });
        if (!response.ok) throw new Error('The chart export could not be added to Activity Logs. Please try again.');
    };

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
        const exportMenu = document.createElement('div');
        exportMenu.className = 'dropdown ms-auto';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-primary dropdown-toggle';
        button.innerHTML = '<i class="ri-download-line me-1" aria-hidden="true"></i>Export';
        button.setAttribute('aria-label', `Export ${header.querySelector('h5').textContent.trim()}`);
        button.setAttribute('data-bs-toggle', 'dropdown');
        button.setAttribute('aria-expanded', 'false');
        const menu = document.createElement('div');
        menu.className = 'dropdown-menu dropdown-menu-end';
        const excelAction = document.createElement('button');
        excelAction.type = 'button';
        excelAction.className = 'dropdown-item';
        excelAction.textContent = 'Excel file (.xlsx)';
        const tableAction = document.createElement('button');
        tableAction.type = 'button';
        tableAction.className = 'dropdown-item';
        tableAction.textContent = 'Table format (.csv)';
        const previewAction = document.createElement('button');
        previewAction.type = 'button';
        previewAction.className = 'dropdown-item';
        previewAction.textContent = 'Chart preview (PNG / PDF)';
        menu.append(excelAction, tableAction, previewAction);
        exportMenu.append(button, menu);
        header.append(exportMenu);

        const getExportTable = async () => {
            const chart = canvas && window.Chart?.getChart(canvas);
            if (!chart) throw new Error('Chart data is unavailable.');

            let labels = chart.data.labels || [];
            let datasets = chart.data.datasets || [];
            let total = null;
            let payload = null;
            let isDistribution = false;
            if (['clientCategoryChart', 'addressDistributionChart'].includes(id)) {
                const url = new URL(canvas.dataset.exportUrl, window.location.origin);
                const filters = new URL(window.location.href).searchParams;
                filters.forEach((value, key) => {
                    const matchesFilter = id === 'clientCategoryChart'
                        ? /^distribution_(category|type)(\[\d*\])?$/.test(key)
                        : /^address_distribution_(type|category|status)(\[\d*\])?$/.test(key);
                    if (matchesFilter) url.searchParams.append(key, value);
                });
                url.searchParams.set('export', '1');
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                payload = await response.json();
                if (!response.ok || !payload.success) throw new Error('Export data could not be loaded.');
                labels = payload.labels || [];
                total = Number(payload.total) || 0;
                datasets = [{ label: 'Event Records', data: payload.data || [] }];
                isDistribution = true;
            }

            if (isDistribution) {
                if (id === 'addressDistributionChart') {
                    return {
                        rows: [['Address', 'Claimed Count', 'Pending / Unclaimed Count'], ...labels.map((label, index) => [
                            label,
                            Number((payload.claimed_counts || [])[index]) || 0,
                            Number((payload.pending_counts || [])[index]) || 0,
                        ])],
                        chartColumns: [1, 2],
                        chartType: chart.config.type,
                        horizontal: chart.options.indexAxis === 'y',
                        hasData: labels.length > 0,
                    };
                }
                const heading = 'Client Category';
                const countHeading = 'Event Records';
                return {
                    rows: [[heading, countHeading, 'Share (%)'], ...labels.map((label, index) => {
                        const count = Number(datasets[0].data[index]) || 0;
                        return [label, count, total ? Number((count / total * 100).toFixed(1)) : 0];
                    })],
                    chartColumns: [1],
                    chartType: chart.config.type,
                    horizontal: chart.options.indexAxis === 'y',
                    hasData: labels.length > 0,
                };
            }

            return {
                rows: [[ 'Category', ...datasets.map(dataset => dataset.label || 'Series') ],
                    ...labels.map((label, index) => [label, ...datasets.map(dataset => dataset.data?.[index] ?? '')])],
                chartColumns: datasets.map((_, index) => index + 1),
                chartType: chart.config.type,
                horizontal: chart.options.indexAxis === 'y',
                hasData: labels.length > 0,
            };
        };
        const xmlEscape = value => String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;'
        })[character]);
        const addExcelChart = async (xlsxBytes, table, chartTitle) => {
            if (!window.JSZip) throw new Error('Excel chart export is unavailable. Refresh the page and try again.');
            const zip = await JSZip.loadAsync(xlsxBytes);
            const sheetPath = 'xl/worksheets/sheet1.xml';
            let sheetXml = await zip.file(sheetPath).async('string');
            if (!sheetXml.includes('xmlns:r=')) {
                sheetXml = sheetXml.replace('<worksheet ', '<worksheet xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" ');
            }
            sheetXml = sheetXml.replace('</worksheet>', '<drawing r:id="rId1"/></worksheet>');
            zip.file(sheetPath, sheetXml);

            const contentTypes = await zip.file('[Content_Types].xml').async('string');
            zip.file('[Content_Types].xml', contentTypes.replace('</Types>',
                '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' +
                '<Override PartName="/xl/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/></Types>'));
            zip.file('xl/worksheets/_rels/sheet1.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>');
            zip.file('xl/drawings/_rels/drawing1.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart1.xml"/></Relationships>');
            zip.file('xl/drawings/drawing1.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
                '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
                '<xdr:oneCellAnchor><xdr:from><xdr:col>4</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>1</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:ext cx="9144000" cy="5486400"/>' +
                '<xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="2" name="Chart 1"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr><xdr:xfrm><a:off x="0" y="0"/><a:ext cx="9144000" cy="5486400"/></xdr:xfrm><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart r:id="rId1"/></a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>');

            const firstDataRow = 2;
            const lastDataRow = table.rows.length;
            const pointsXml = (column, numeric) => table.rows.slice(1).map((row, index) =>
                `<c:pt idx="${index}"><c:v>${xmlEscape(numeric ? (Number(row[column]) || 0) : row[0])}</c:v></c:pt>`).join('');
            const seriesXml = table.chartColumns.map((column, index) => {
                const header = table.rows[0][column] || `Series ${index + 1}`;
                const letter = String.fromCharCode(65 + column);
                const categoryCount = lastDataRow - firstDataRow + 1;
                return `<c:ser><c:idx val="${index}"/><c:order val="${index}"/><c:tx><c:strRef><c:f>'Chart Data'!$${letter}$1</c:f><c:strCache><c:ptCount val="1"/><c:pt idx="0"><c:v>${xmlEscape(header)}</c:v></c:pt></c:strCache></c:strRef></c:tx><c:cat><c:strRef><c:f>'Chart Data'!$A$${firstDataRow}:$A$${lastDataRow}</c:f><c:strCache><c:ptCount val="${categoryCount}"/>${pointsXml(0, false)}</c:strCache></c:strRef></c:cat><c:val><c:numRef><c:f>'Chart Data'!$${letter}$${firstDataRow}:$${letter}$${lastDataRow}</c:f><c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="${categoryCount}"/>${pointsXml(column, true)}</c:numCache></c:numRef></c:val></c:ser>`;
            }).join('');
            const isLine = table.chartType === 'line';
            const categoryAxisPosition = table.horizontal ? 'l' : 'b';
            const valueAxisPosition = table.horizontal ? 'b' : 'l';
            const chartXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
                '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><c:style val="10"/><c:chart>' +
                `<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="en-US"/><a:t>${xmlEscape(chartTitle)}</a:t></a:r><a:endParaRPr lang="en-US"/></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>` +
                `<c:plotArea><c:layout/><c:${isLine ? 'lineChart' : 'barChart'}>${isLine ? '<c:grouping val="standard"/>' : `<c:barDir val="${table.horizontal ? 'bar' : 'col'}"/><c:grouping val="clustered"/>`}` +
                (isLine ? seriesXml.replace(/<c:cat>/g, '<c:marker><c:symbol val="circle"/><c:size val="5"/></c:marker><c:cat>') : seriesXml) +
                `${isLine ? '<c:smooth val="0"/>' : '<c:gapWidth val="70"/><c:overlap val="0"/>'}<c:axId val="10"/><c:axId val="20"/></c:${isLine ? 'lineChart' : 'barChart'}>` +
                `<c:catAx><c:axId val="10"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="${categoryAxisPosition}"/><c:tickLblPos val="nextTo"/><c:crossAx val="20"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/></c:catAx>` +
                `<c:valAx><c:axId val="20"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="${valueAxisPosition}"/><c:majorGridlines/><c:numFmt formatCode="General" sourceLinked="1"/><c:tickLblPos val="nextTo"/><c:crossAx val="10"/><c:crosses val="autoZero"/><c:crossBetween val="between"/></c:valAx></c:plotArea>` +
                '<c:legend><c:legendPos val="b"/><c:layout/><c:overlay val="0"/></c:legend><c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart></c:chartSpace>';
            zip.file('xl/charts/chart1.xml', chartXml);
            return zip.generateAsync({ type: 'uint8array', compression: 'DEFLATE' });
        };
        const downloadBlob = (contents, type, extension) => {
            const blob = new Blob([contents], { type });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${id.replace(/Chart$/, '').replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase()}.${extension}`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        };
        const prepareTableExport = async (format) => {
            if (canvas?.getAttribute('aria-busy') === 'true') {
                window.alert('Please wait for the chart to finish updating, then export again.');
                return;
            }
            try {
                const table = await getExportTable();
                if (!table.hasData) {
                    window.alert('No chart data is available for export.');
                    return;
                }
                if (format === 'xlsx') {
                    if (!window.XLSX) throw new Error('Excel export is unavailable. Refresh the page and try again.');
                    const sheet = XLSX.utils.aoa_to_sheet(table.rows);
                    sheet['!cols'] = table.rows[0].map((heading, column) => ({
                        wch: Math.min(36, Math.max(14, ...table.rows.map(row => String(row[column] ?? '').length + 2)))
                    }));
                    sheet['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: table.rows.length - 1, c: table.rows[0].length - 1 } }) };
                    const workbook = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(workbook, sheet, 'Chart Data');
                    const xlsxBytes = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
                    const chartFile = await addExcelChart(xlsxBytes, table, header.querySelector('h5').textContent.trim());
                    await logChartExport(id, 'xlsx');
                    downloadBlob(chartFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx');
                } else {
                    const csv = table.rows.map(row => row.map(value => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
                    await logChartExport(id, 'csv');
                    downloadBlob('\uFEFF' + csv, 'text/csv;charset=utf-8', 'csv');
                }
            } catch (error) {
                window.alert(error.message || 'The chart data could not be exported.');
            }
        };
        excelAction.addEventListener('click', () => prepareTableExport('xlsx'));
        tableAction.addEventListener('click', () => prepareTableExport('csv'));

        previewAction.addEventListener('click', async () => {
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
                if (['clientCategoryChart', 'addressDistributionChart'].includes(id) && chart) {
                    const url = new URL(canvas.dataset.exportUrl, window.location.origin);
                    const filters = new URL(window.location.href).searchParams;
                    filters.forEach((value, key) => {
                        const matchesFilter = id === 'clientCategoryChart'
                            ? /^distribution_(category|type)(\[\d*\])?$/.test(key)
                            : /^address_distribution_(type|category|status)(\[\d*\])?$/.test(key);
                        if (matchesFilter) {
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
            try {
                if (imageUrl) await logChartExport(id, 'preview');
            } catch (error) {
                preview.document.body.textContent = error.message || 'The chart export could not be logged.';
                return;
            }
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
            doc.getElementById('generated').textContent = `Captured ${new Date().toLocaleString()} • ${id === 'clientCategoryChart' ? 'All client categories for the selected filters' : (id === 'addressDistributionChart' ? 'Listed barangays for the selected filters' : 'Current dashboard view')}`;
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
