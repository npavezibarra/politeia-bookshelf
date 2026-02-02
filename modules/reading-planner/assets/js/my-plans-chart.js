(function () {
    // Utility functions from reading-plan.js
    const STRINGS = window.PoliteiaReadingPlan ? (window.PoliteiaReadingPlan.strings || {}) : {};
    const t = (key, fallback) => (STRINGS && STRINGS[key]) ? STRINGS[key] : fallback;

    function renderChart(containerId, sessions, planId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.position = 'relative';

        if (!sessions || sessions.length === 0) return;

        // Data preparation
        const data = sessions.map((s, i) => ({
            day: i + 1,
            pages: parseInt(s.expectedPages || s.target_value || 0, 10),
            dateLabel: s.dateLabel || `Day ${i + 1}`
        }));

        const maxPages = Math.ceil(Math.max(...data.map(d => d.pages)) * 1.05);
        const minPages = Math.floor(Math.min(...data.map(d => d.pages)) * 0.95);
        const maxDays = data.length;

        // Dimensions
        const width = container.clientWidth || 300; // Fallback width
        const height = container.clientHeight || 250; // Fallback height
        const padding = { top: 20, right: 30, left: 40, bottom: 30 };
        const chartW = width - padding.left - padding.right;
        const chartH = height - padding.top - padding.bottom;

        // Scales
        const xScale = (day) => (day - 1) / (maxDays - 1) * chartW;
        const yScale = (p) => chartH - ((p - minPages) / (maxPages - minPages) * chartH);

        const svgNs = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(svgNs, 'svg');
        svg.setAttribute('class', 'chart-svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.style.width = '100%';
        svg.style.height = '100%';

        const g = document.createElementNS(svgNs, 'g');
        g.setAttribute('transform', `translate(${padding.left}, ${padding.top})`);
        svg.appendChild(g);

        // Y Axis Label
        const yLabel = document.createElementNS(svgNs, 'text');
        yLabel.setAttribute('x', -chartH / 2);
        yLabel.setAttribute('y', -30);
        yLabel.setAttribute('transform', 'rotate(-90)');
        yLabel.setAttribute('text-anchor', 'middle');
        yLabel.setAttribute('class', 'chart-axis-title');
        yLabel.style.fill = '#94a3b8';
        yLabel.style.fontSize = '11px';
        yLabel.style.fontWeight = '600';
        yLabel.style.fontFamily = 'Poppins, sans-serif';
        yLabel.style.textTransform = 'uppercase';
        yLabel.textContent = t('pages_label', 'Páginas');
        g.appendChild(yLabel);

        // X Axis Label
        const xLabel = document.createElementNS(svgNs, 'text');
        xLabel.setAttribute('x', chartW / 2);
        xLabel.setAttribute('y', chartH + 25);
        xLabel.setAttribute('text-anchor', 'middle');
        xLabel.setAttribute('class', 'chart-axis-title');
        xLabel.style.fill = '#94a3b8';
        xLabel.style.fontSize = '11px';
        xLabel.style.fontWeight = '600';
        xLabel.style.fontFamily = 'Poppins, sans-serif';
        xLabel.style.textTransform = 'uppercase';
        xLabel.textContent = t('day_label', 'Día');
        g.appendChild(xLabel);

        // Y Axis & Grid
        const yTicks = 5;
        for (let i = 0; i <= yTicks; i++) {
            const val = minPages + (i / yTicks) * (maxPages - minPages);
            const y = yScale(Math.round(val));

            // Grid line
            const line = document.createElementNS(svgNs, 'line');
            line.setAttribute('x1', 0);
            line.setAttribute('x2', chartW);
            line.setAttribute('y1', y);
            line.setAttribute('y2', y);
            line.setAttribute('class', 'chart-grid-line');
            line.style.stroke = '#2a2a2a';
            line.style.strokeDasharray = '4, 4';
            g.appendChild(line);

            // Text
            const text = document.createElementNS(svgNs, 'text');
            text.setAttribute('x', -10);
            text.setAttribute('y', y + 4);
            text.setAttribute('text-anchor', 'end');
            text.setAttribute('class', 'chart-axis-text');
            text.style.fill = '#64748b';
            text.style.fontSize = '10px';
            text.textContent = Math.round(val);
            g.appendChild(text);
        }

        // X Axis Labels
        const xStep = maxDays > 30 ? Math.ceil(maxDays / 10) : 1;
        for (let i = 0; i < maxDays; i += xStep) {
            const day = i + 1;
            const x = xScale(day);

            const tick = document.createElementNS(svgNs, 'line');
            tick.setAttribute('x1', x);
            tick.setAttribute('x2', x);
            tick.setAttribute('y1', chartH);
            tick.setAttribute('y2', chartH + 5);
            tick.setAttribute('stroke', '#334155');
            g.appendChild(tick);

            const text = document.createElementNS(svgNs, 'text');
            text.setAttribute('x', x);
            text.setAttribute('y', chartH + 15);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('class', 'chart-axis-text');
            text.style.fill = '#64748b';
            text.style.fontSize = '10px';
            text.textContent = day;
            g.appendChild(text);
        }

        // Line Path
        if (data.length > 0) {
            let pathD = `M ${xScale(data[0].day)} ${yScale(data[0].pages)}`;
            for (let i = 1; i < data.length; i++) {
                pathD += ` L ${xScale(data[i].day)} ${yScale(data[i].pages)}`;
            }

            const path = document.createElementNS(svgNs, 'path');
            path.setAttribute('d', pathD);
            path.setAttribute('class', 'chart-line-path');
            path.style.fill = 'none';
            path.style.stroke = '#FBBF24';
            path.style.strokeWidth = '2';
            path.style.strokeDasharray = '6, 4';
            g.appendChild(path);
        }

        // Tooltip
        let tooltip = container.querySelector('.chart-tooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'chart-tooltip';
            tooltip.style.position = 'absolute';
            tooltip.style.backgroundColor = '#0f172a';
            tooltip.style.padding = '8px 12px';
            tooltip.style.borderRadius = '6px';
            tooltip.style.border = '1px solid #334155';
            tooltip.style.pointerEvents = 'none';
            tooltip.style.opacity = '0';
            tooltip.style.transition = 'opacity 0.2s';
            tooltip.style.boxShadow = '0 4px 6px rgba(0,0,0,0.3)';
            tooltip.style.zIndex = '10';
            tooltip.style.transform = 'translate(-50%, -100%)';
            tooltip.style.marginTop = '-10px';
            container.appendChild(tooltip);
        }

        // Points
        data.forEach((d) => {
            const cx = xScale(d.day);
            const cy = yScale(d.pages);
            const circle = document.createElementNS(svgNs, 'circle');
            circle.setAttribute('cx', cx);
            circle.setAttribute('cy', cy);
            circle.setAttribute('r', 3);
            circle.setAttribute('class', 'chart-point');
            circle.style.fill = '#FBBF24';
            circle.style.stroke = '#1a1a1a';
            circle.style.strokeWidth = '1';
            circle.style.cursor = 'pointer';
            circle.style.transition = 'all 0.2s';

            circle.addEventListener('mouseenter', () => {
                circle.setAttribute('r', 5);
                circle.style.fill = '#FCD34D';
                circle.style.stroke = '#ffffff';
                circle.style.strokeWidth = '2';

                tooltip.innerHTML = `
                    <div style="font-size:10px;color:#94a3b8;font-weight:600;margin-bottom:2px">${d.dateLabel}</div>
                    <div style="font-size:12px;font-weight:700;color:#ffffff">${d.pages} ${t('pages_label', 'Páginas')}</div>
                `;

                const leftPos = cx + padding.left;
                const topPos = cy + padding.top;

                tooltip.style.left = `${leftPos}px`;
                tooltip.style.top = `${topPos}px`;
                tooltip.style.opacity = '1';
            });

            circle.addEventListener('mouseleave', () => {
                circle.setAttribute('r', 3);
                circle.style.fill = '#FBBF24';
                circle.style.stroke = '#1a1a1a';
                circle.style.strokeWidth = '1';
                tooltip.style.opacity = '0';
            });

            g.appendChild(circle);
        });

        container.appendChild(svg);
    }

    // Initialize all chart containers on the page
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize existing charts
        const containers = document.querySelectorAll('[data-role="plan-line-chart"]');
        containers.forEach(container => {
            try {
                const sessionsData = JSON.parse(container.dataset.sessions || '[]');
                const planId = container.dataset.planId;
                renderChart(container.id, sessionsData, planId);
            } catch (e) {
                console.error('Error parsing session data for chart', e);
            }
        });

        // Initialize Version 2 Charts
        initVer2Charts();
    });

    function initVer2Charts() {
        const canvases = document.querySelectorAll('[data-role="habit-chart-ver-2"]');
        canvases.forEach(canvas => {
            try {
                const planId = canvas.dataset.planId;
                const sessionsStr = canvas.dataset.sessions;
                const sessions = JSON.parse(sessionsStr || '[]');
                const startPages = parseInt(canvas.dataset.startPages || 15, 10);
                const endPages = parseInt(canvas.dataset.endPages || 28, 10);
                const duration = parseInt(canvas.dataset.duration || 48, 10);

                renderHabitChartVer2(canvas, sessions, startPages, endPages, duration);
            } catch (e) {
                console.error('Error initializing ver 2 chart', e);
            }
        });
    }

    function renderHabitChartVer2(canvas, sessions, startPages, endPages, duration) {
        const ctx = canvas.getContext('2d');
        const tooltip = document.getElementById(`tooltip-ver-2-${canvas.dataset.planId}`);
        const totalDays = duration > 0 ? duration : 48;

        // Process Data
        // We need an array of length totalDays
        // If sessions has data, map it.

        // Find today to determine "isActual"
        // In the PHP code, isActual was logic-based. Here we use the session status or date.
        // We can check if session has actual pages or status is accomplished/missed/partial

        // Helper to parse date string YYYY-MM-DD
        const todayFn = new Date();
        const todayStr = todayFn.toISOString().split('T')[0];

        // Prepare data array
        // Expected curve can be linear interpolation if not provided, but session items usually have expectedPages.

        // Sort sessions by date
        sessions.sort((a, b) => (a.date < b.date ? -1 : 1));

        const data = Array.from({ length: totalDays }, (_, i) => {
            const day = i + 1;
            let item = sessions[i]; // Assuming sessions are strictly ordered day 1 to N.
            // If sessions array is sparse or mapped differently, we might need to find by order or date.
            // sessions usually contains all days due to the PHP derivation logic.

            // Fallback expected pages logic if item missing
            let expected = Math.round(startPages + i * ((endPages - startPages) / (totalDays - 1)));

            let actual = 0;
            let isActual = false;
            let dateLabel = `Day ${day}`;

            if (item) {
                expected = parseInt(item.expectedPages || expected, 10);

                // Determine Actual Pages (Position in book)
                if (item.actual_end_page) {
                    actual = parseInt(item.actual_end_page, 10);
                } else if (item.actual_start_page) {
                    // If started but not finished/recorded end, use start? Or 0?
                    // Usually actual_end_page is set on completion. 
                    // If incomplete, maybe use previous known? 
                    // For now, let's trust actual_end_page.
                    actual = parseInt(item.actual_start_page, 10);
                }

                // Determine isActual (Is this a past/completed day?)
                // Use date comparison
                if (item.date && item.date <= todayStr) {
                    isActual = true;
                }
                if (item.status === 'accomplished' || item.status === 'partial' || item.status === 'missed') {
                    isActual = true;
                }

                // If missed, distinct display? For now showing actual (which might be 0 or stuck at start).
                // If the user missed, their position didn't change. 
                // We should probably carry forward the last actual page if we want a continuous line?
                // But this is a bar chart. A lower bar indicates "stuck". 

                if (item.date) dateLabel = item.date;
            }

            return {
                day,
                pages: isActual ? actual : expected,
                expectedPages: expected,
                actualPages: actual,
                isActual,
                dateLabel
            };
        });

        // Determine Y Axis bounds
        // User request: minimum number on Y axis should be the starting page minus 20%
        // Max Y should be final page number + 10%
        const maxY = Math.ceil(endPages * 1.1);

        // Ensure non-negative
        const minY = Math.max(0, Math.floor(startPages * 0.8));

        let chartBounds = { left: 40, right: 10, top: 20, bottom: 40 };

        function draw() {
            if (!canvas.parentNode) return;

            // High DPI handling
            const dpr = window.devicePixelRatio || 1;
            const rect = canvas.parentNode.getBoundingClientRect();

            // Avoid 0 dimensions
            if (rect.width === 0 || rect.height === 0) return;

            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;

            // We need to reset transform matrix before scaling?
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(dpr, dpr);

            const width = rect.width;
            const height = rect.height;
            const chartWidth = width - chartBounds.left - chartBounds.right;
            const chartHeight = height - chartBounds.top - chartBounds.bottom;

            ctx.clearRect(0, 0, width, height);

            // Draw Y Grid lines
            ctx.strokeStyle = '#e2e8f0';
            ctx.lineWidth = 1;
            ctx.setLineDash([5, 5]);
            ctx.fillStyle = '#94a3b8';
            ctx.font = '12px Poppins, sans-serif';
            ctx.textAlign = 'right';

            const yLines = 5;
            for (let i = 0; i <= yLines; i++) {
                const val = minY + (i * (maxY - minY) / yLines);
                const y = chartBounds.top + chartHeight - (i * chartHeight / yLines);

                ctx.beginPath();
                ctx.moveTo(chartBounds.left, y);
                ctx.lineTo(chartBounds.left + chartWidth, y);
                ctx.stroke();

                ctx.fillText(Math.round(val), chartBounds.left - 10, y + 4);
            }
            ctx.setLineDash([]);

            // Draw Bars
            const barWidth = (chartWidth / totalDays) * 0.8;
            const barGap = (chartWidth / totalDays) * 0.2;

            data.forEach((d, i) => {
                const x = chartBounds.left + i * (barWidth + barGap) + barGap / 2;

                // Clamp height to chart area
                // If d.pages < minY, logic implies barHeight < 0.
                // We want to show NO bar if value is below axis range?
                // Or show a minimal blip?
                // If actual pages is 0 (missing data) and minY is > 0, we shouldn't draw.
                // If actual pages is valid but < minY (weird regress), clamp to 0.

                let barHeight = ((d.pages - minY) / (maxY - minY)) * chartHeight;
                if (barHeight < 0) barHeight = 0;

                const y = chartBounds.top + chartHeight - barHeight;

                if (d.isActual) {
                    const gradient = ctx.createLinearGradient(x, y, x, y + barHeight);
                    gradient.addColorStop(0, '#E9D18A');
                    gradient.addColorStop(0.5, '#C79F32');
                    gradient.addColorStop(1, '#8A6B1E');
                    ctx.fillStyle = gradient;
                } else {
                    ctx.fillStyle = '#e0e0e0';
                }

                // Rounded top rect
                const radius = Math.min(4, barWidth / 2);
                if (barHeight > 0) {
                    ctx.beginPath();
                    ctx.moveTo(x + radius, y);
                    ctx.lineTo(x + barWidth - radius, y);
                    ctx.quadraticCurveTo(x + barWidth, y, x + barWidth, y + radius);
                    ctx.lineTo(x + barWidth, y + barHeight);
                    ctx.lineTo(x, y + barHeight);
                    ctx.lineTo(x, y + radius);
                    ctx.quadraticCurveTo(x, y, x + radius, y);
                    ctx.fill();
                }

                // Draw X-axis label for every 5th day roughly or start/end
                // User code: 1, 10, 20, 30, 40, 48
                if (d.day === 1 || d.day === 10 || d.day === 20 || d.day === 30 || d.day === 40 || d.day === totalDays) {
                    ctx.fillStyle = '#64748b';
                    ctx.font = '12px Poppins, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(d.day, x + barWidth / 2, chartBounds.top + chartHeight + 20);
                }
            });

            // Axis Labels
            ctx.fillStyle = '#94a3b8';
            ctx.font = 'bold 12px Poppins, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(t('day_label', 'Días'), chartBounds.left + chartWidth / 2, height - 5);

            ctx.save();
            ctx.translate(15, chartBounds.top + chartHeight / 2);
            ctx.rotate(-Math.PI / 2);
            ctx.fillText(t('pages_label', 'Páginas'), 0, 0);
            ctx.restore();
        }

        // Interaction
        const onMouseMove = (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const chartWidth = rect.width - chartBounds.left - chartBounds.right;
            // index based on X position considering bar widths
            // Simpler calculation mapping x to day index
            // x_in_chart = x - chartBounds.left
            // chartWidth represents totalDays items
            // index = floor(x_in_chart / (chartWidth / totalDays))

            const xInChart = x - chartBounds.left;
            const index = Math.floor((xInChart / chartWidth) * totalDays);

            if (index >= 0 && index < totalDays) {
                const d = data[index];
                if (d && tooltip) {
                    tooltip.style.display = 'block';
                    // Position tooltip relative to container (which is parent of canvas)
                    // e.clientX is global. tooltip is absolute in container.
                    // Actually simpler to set left/top based on mouse relative to canvas
                    tooltip.style.left = `${x + 10}px`;
                    tooltip.style.top = `${y - 40}px`;

                    tooltip.innerHTML = `
                        <div class="text-xs font-bold text-slate-800" style="font-size: 12px; font-weight: 700; color: #1e293b;">Day ${d.day}</div>
                        <div class="text-sm text-indigo-600 font-semibold" style="font-size: 14px; font-weight: 600; color: #4f46e5;">${d.pages} pages</div>
                        <div class="text-[10px] text-slate-400 uppercase" style="font-size: 10px; color: #94a3b8; text-transform: uppercase;">${d.isActual ? 'Actual' : 'Meta'}</div>
                    `;
                }
            } else {
                if (tooltip) tooltip.style.display = 'none';
            }
        };

        const onMouseLeave = () => {
            if (tooltip) tooltip.style.display = 'none';
        };

        canvas.removeEventListener('mousemove', canvas._ver2mousemove); // cleanup if re-init
        canvas.removeEventListener('mouseleave', canvas._ver2mouseleave);

        canvas.addEventListener('mousemove', onMouseMove);
        canvas.addEventListener('mouseleave', onMouseLeave);

        // Store refs for cleanup
        canvas._ver2mousemove = onMouseMove;
        canvas._ver2mouseleave = onMouseLeave;

        draw();

        // Handle Resize
        // We can use ResizeObserver if available
        if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(() => {
                window.requestAnimationFrame(draw);
            });
            ro.observe(canvas.parentNode);
        } else {
            window.addEventListener('resize', draw);
        }
    }
})();
