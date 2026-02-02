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
    });
})();
