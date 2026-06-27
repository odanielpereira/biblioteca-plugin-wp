/**
 * Book Manager — Reports Dashboard
 * Fase 6 COMPLETA: Sparklines, Radar/Perfil, Timeline, Meta, Drill-down, Grid de capas, Refinamento visual, Títulos de seção
 */
(function() {
    const bm = window.bmReports || {};

    if (!bm.ajaxUrl) {
        const msg = document.createElement('div');
        msg.style.cssText = 'background:#dc3545;color:#fff;padding:15px;margin:20px;border-radius:8px;font-family:sans-serif;';
        msg.textContent = 'Erro: Dados de configuração não carregados.';
        document.addEventListener('DOMContentLoaded', function() {
            const target = document.getElementById('bm-report-result') || document.body;
            target.insertBefore(msg, target.firstChild);
        });
        return;
    }

    function calculateVariance(current, previous) {
        if (!previous || previous === 0) return { value: 0, isPositive: false, formatted: '' };
        const diff = ((current - previous) / previous) * 100;
        const rounded = Math.round(diff);
        return { value: rounded, isPositive: rounded >= 0, formatted: (rounded >= 0 ? '+' : '') + rounded + '%' };
    }

    function rankEntities(data, key, limit) {
        limit = limit || 3;
        return [...data].sort(function(a, b) { return (b[key] || 0) - (a[key] || 0); }).slice(0, limit);
    }

    function formatPercent(value) { return Math.round(value * 100) + '%'; }

    window.calculateVariance = calculateVariance;
    window.rankEntities = rankEntities;
    window.formatPercent = formatPercent;

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('bm-report-form');
        if (!form) return;
        form.addEventListener('submit', function(e) { e.preventDefault(); bmFetchReport(); });
        const periodSelect = document.querySelector('select[name="bm_period"]');
        const subjectSelect = document.querySelector('select[name="bm_subject"]');
        const typeSelect = document.querySelector('select[name="bm_report_type"]');
        if (periodSelect) periodSelect.addEventListener('change', bmToggleCustomDates);
        if (subjectSelect) subjectSelect.addEventListener('change', bmToggleSubjectOptions);
        if (typeSelect) typeSelect.addEventListener('change', bmToggleCustomOptions);
        const studentSearch = document.getElementById('bm-student-search-input');
        if (studentSearch) studentSearch.addEventListener('keyup', bmSearchStudent);
        const exportPdfBtn = document.getElementById('bm-export-pdf');
        if (exportPdfBtn) exportPdfBtn.addEventListener('click', bmExportPDF);
    });

    function bmToggleCustomDates() {
        const div = document.getElementById('bm-custom-dates');
        if (div) { div.classList.toggle('hidden', this.value !== 'custom'); div.classList.toggle('flex', this.value === 'custom'); }
    }
    function bmToggleSubjectOptions() {
        const studentSelect = document.getElementById('bm-student-select');
        const classSelect = document.getElementById('bm-class-select');
        if (studentSelect) studentSelect.classList.toggle('hidden', this.value !== 'student');
        if (classSelect) classSelect.classList.toggle('hidden', this.value !== 'class');
    }
    function bmToggleCustomOptions() {
        const div = document.getElementById('bm-custom-options');
        if (div) div.classList.toggle('hidden', this.value !== 'custom');
    }

    function bmSearchStudent() {
        const query = this.value.trim();
        if (query.length < 2) return;
        const params = new URLSearchParams();
        params.append('action', 'bm_service_search_student');
        params.append('query', query);
        params.append('nonce', bm.serviceNonce);
        fetch(bm.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
        .then(r => r.json())
        .then(r => {
            const results = document.getElementById('bm-student-search-results');
            if (!results) return;
            let html = '';
            if (r.found) {
                document.getElementById('bm-subject-id').value = r.student.id;
                html = '<div class="bm-student-result-item" data-student-id="' + r.student.id + '" data-student-name="' + r.student.name + '" style="padding:6px;background:#e8f5e9;border-radius:4px;cursor:pointer;margin:2px 0;">' + r.student.name + ' (' + r.student.email + ')</div>';
            } else if (r.multiple) {
                r.students.forEach(function(s) {
                    html += '<div class="bm-student-result-item" data-student-id="' + s.id + '" data-student-name="' + s.name + '" style="padding:6px;background:#f5f5f5;border-radius:4px;cursor:pointer;margin:2px 0;">' + s.name + ' (' + s.email + ')</div>';
                });
            } else { html = '<p style="color:#999;font-size:12px;">Nenhum aluno encontrado.</p>'; }
            results.innerHTML = html;
            document.querySelectorAll('.bm-student-result-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    document.getElementById('bm-subject-id').value = this.getAttribute('data-student-id');
                    results.innerHTML = '<strong>' + this.getAttribute('data-student-name') + '</strong> selecionado';
                });
            });
        });
    }

    function bmFetchReport() {
        const form = document.getElementById('bm-report-form');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.append('action', 'bm_get_report_data');
        params.append('nonce', bm.nonce);
        bmShowState('loading');
        fetch(bm.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
        .then(r => r.json())
        .then(response => {
            document.getElementById('bm-loading').classList.add('hidden');
            if (response.success && response.data) { bmRenderReport(response.data); }
            else { bmShowState('empty'); }
        })
        .catch(() => { document.getElementById('bm-loading').classList.add('hidden'); bmShowState('empty'); });
    }

    function bmShowState(state) {
        const welcome = document.getElementById('bm-welcome');
        const loading = document.getElementById('bm-loading');
        const empty = document.getElementById('bm-empty');
        const title = document.querySelector('[data-section="report-title"]');
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        const chart = document.querySelector('[data-section="bar-chart"]');
        const table = document.querySelector('[data-section="data-table"]');
        const pieChart = document.querySelector('[data-section="pie-chart"]');
        const lineChart = document.querySelector('[data-section="line-chart"]');
        const topReaders = document.querySelector('[data-section="top-readers"]');
        const inactiveAlerts = document.querySelector('[data-section="inactive-alerts"]');
        if (welcome) welcome.classList.add('hidden');
        if (loading) loading.classList.add('hidden');
        if (empty) empty.classList.add('hidden');
        if (title) title.classList.add('hidden');
        if (kpi) kpi.classList.add('hidden');
        if (chart) chart.classList.add('hidden');
        if (table) table.classList.add('hidden');
        if (pieChart) pieChart.classList.add('hidden');
        if (lineChart) lineChart.classList.add('hidden');
        if (topReaders) topReaders.classList.add('hidden');
        if (inactiveAlerts) inactiveAlerts.classList.add('hidden');
        switch (state) {
            case 'loading': if (loading) loading.classList.remove('hidden'); break;
            case 'empty': if (empty) empty.classList.remove('hidden'); break;
            case 'welcome': if (welcome) welcome.classList.remove('hidden'); break;
        }
    }

    function bmRenderReport(data) {
        const meta = data._meta || {};
        const type = meta.type || 'overview';
        const titleSection = document.querySelector('[data-section="report-title"]');
        if (titleSection && data.title) {
            titleSection.classList.remove('hidden');
            const h2 = titleSection.querySelector('h2');
            const p = titleSection.querySelector('p');
            if (h2) h2.textContent = data.title;
            if (p && data.period_start) p.textContent = data.period_start + ' — ' + data.period_end;
        }
        switch (type) {
            case 'overview': bmRenderOverview(data); break;
            case 'student_performance': bmRenderStudentPerformance(data); break;
            case 'class_reading': bmRenderClassReading(data); break;
            case 'active_penalties': bmRenderPenalties(data); break;
            case 'genre_ranking': bmRenderGenreRanking(data); break;
            case 'top_books': bmRenderTopBooks(data); break;
            case 'reading_trend': bmRenderReadingTrend(data); break;
            case 'custom': bmRenderCustom(data); break;
        }
    }

    function bmSparklineSVG(data, color) {
        if (!data || data.length === 0) return '';
        const w = 120, h = 34;
        const max = Math.max.apply(null, data);
        const min = Math.min.apply(null, data);
        const rng = (max - min) || 1;
        const pts = data.map(function(v, i) {
            const x = (i / (data.length - 1)) * w;
            const y = h - ((v - min) / rng) * (h - 6) - 3;
            return [x, y];
        });
        const line = pts.map(function(p) { return p.join(','); }).join(' ');
        const area = '0,' + h + ' ' + line + ' ' + w + ',' + h;
        return '<svg viewBox="0 0 ' + w + ' ' + h + '" class="bm-sparkline w-full h-9" preserveAspectRatio="none">' +
            '<polygon points="' + area + '" fill="' + color + '" opacity="0.12"/>' +
            '<polyline points="' + line + '" fill="none" stroke="' + color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<circle cx="' + pts[pts.length - 1][0] + '" cy="' + pts[pts.length - 1][1] + '" r="2.5" fill="' + color + '"/>' +
            '</svg>';
    }

    function bmRenderOverview(data) {
        bmHideKPI(); bmHideChart(); bmHideTable();
        var oldPie = document.querySelector('[data-section="pie-chart"]');
        var oldLine = document.querySelector('[data-section="line-chart"]');
        var oldTop = document.querySelector('[data-section="top-readers"]');
        var oldInactive = document.querySelector('[data-section="inactive-alerts"]');
        if (oldPie) oldPie.classList.add('hidden');
        if (oldLine) oldLine.classList.add('hidden');
        if (oldTop) oldTop.classList.add('hidden');
        if (oldInactive) oldInactive.classList.add('hidden');
        var drill = document.getElementById('bm-drill-detail');
        if (drill) { drill.classList.add('hidden'); drill.innerHTML = ''; }

        var dashboard = document.getElementById('bm-dashboard');
        if (!dashboard) return;
        dashboard.innerHTML = '';
        dashboard.classList.remove('hidden');
        var period = (data._meta && data._meta.period) ? data._meta.period : 'month';

        bmRenderDashboardKPIs(data);

        var destHeader = document.createElement('h2');
        destHeader.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        destHeader.textContent = 'Destaques do Período';
        dashboard.appendChild(destHeader);
        var row4 = document.createElement('div');
        row4.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6';
        if (data.students && data.students.length > 0) row4.appendChild(bmCreateHighlightCard('student', data.students[0], period));
        if (data.top_book) row4.appendChild(bmCreateHighlightCard('book', data.top_book, period));
        row4.appendChild(bmCreateHighlightCard('revelation', data.revelation_student, period));
        dashboard.appendChild(row4);

        var grafHeader = document.createElement('h2');
        grafHeader.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        grafHeader.textContent = 'Gráficos';
        dashboard.appendChild(grafHeader);
        var row5 = document.createElement('div');
        row5.className = 'grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6';
        if (data.months && Object.keys(data.months).length > 0) row5.appendChild(bmCreateChartCard('Tendência de Leitura', data.months, 'line', period, 'reading_trend'));
        if (data.genres && Object.keys(data.genres).length > 0) row5.appendChild(bmCreateChartCard('Gêneros Mais Lidos', data.genres, 'pizza', period, 'genre_ranking'));
        dashboard.appendChild(row5);

        var rank1Header = document.createElement('h2');
        rank1Header.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        rank1Header.textContent = 'Rankings — Alunos';
        dashboard.appendChild(rank1Header);
        var row6 = document.createElement('div');
        row6.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6';
        if (data.students && data.students.length > 0) row6.appendChild(bmCreateRankingCard('Top Leitores', data.students, period, 'student_performance', 5));
        if (data.top_reviewers && data.top_reviewers.length > 0) row6.appendChild(bmCreateRankingCard('Top Resenhadores', data.top_reviewers, period, 'student_performance', 5));
        if (data.top_video_reviewers && data.top_video_reviewers.length > 0) row6.appendChild(bmCreateRankingCard('Top Vídeo-Resen.', data.top_video_reviewers, period, 'student_performance', 5));
        if (data.class_ranking && data.class_ranking.length > 0) row6.appendChild(bmCreateRankingCard('Ranking de Turmas', data.class_ranking, period, 'class_reading', 5));
        dashboard.appendChild(row6);

        var rank2Header = document.createElement('h2');
        rank2Header.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        rank2Header.textContent = 'Rankings — Livros';
        dashboard.appendChild(rank2Header);
        var row7 = document.createElement('div');
        row7.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6';
        if (data.books && data.books.length > 0) row7.appendChild(bmCreateRankingCard('Livros +Emprestados', data.books.map(function(b) { return { name: b.title, loans: b.loans }; }), period, 'top_books', 5));
        row7.appendChild(bmCreateRankingCard('Livros +Resenhados', (data.most_reviewed_books && data.most_reviewed_books.length > 0) ? data.most_reviewed_books : [], period, 'top_books', 5));
        row7.appendChild(bmCreateRankingCard('Livros +Vídeos', (data.most_video_books && data.most_video_books.length > 0) ? data.most_video_books : [], period, 'top_books', 5));
        if (data.top_authors && data.top_authors.length > 0) row7.appendChild(bmCreateRankingCard('Autor +Lido', data.top_authors, period, 'top_books', 5));
        dashboard.appendChild(row7);

        var alertHeader = document.createElement('h2');
        alertHeader.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        alertHeader.textContent = 'Alertas';
        dashboard.appendChild(alertHeader);
        var row8 = document.createElement('div');
        row8.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6';
        if (data.inactive_students && data.inactive_students.length > 0) row8.appendChild(bmCreateAlertCard('Alunos sem leitura', data.inactive_students, period, 'student_performance'));
        row8.appendChild(bmCreateAlertCard('Atrasos +7 dias', (data.overdue_students && data.overdue_students.length > 0) ? data.overdue_students : [], period, 'active_penalties'));
        row8.appendChild(bmCreateAlertCard('Livros com fila', (data.books_with_queue && data.books_with_queue.length > 0) ? data.books_with_queue : [], period, 'top_books'));
        dashboard.appendChild(row8);

        var utilHeader = document.createElement('h2');
        utilHeader.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        utilHeader.textContent = 'Utilidades';
        dashboard.appendChild(utilHeader);
        var row9 = document.createElement('div');
        row9.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6';
        row9.appendChild(bmCreateUtilityCard('Últimos Cadastrados', (data.recent_books && data.recent_books.length > 0) ? data.recent_books : [], period, 'top_books', '🆕'));
        if (data.acquisition_suggestions_count !== undefined) row9.appendChild(bmCreateUtilityCard('Sugestões de Aquisição', data.acquisition_suggestions_count + ' pendentes', period, 'custom', '📚'));
        if (data.recent_activity && data.recent_activity.length > 0) row9.appendChild(bmCreateUtilityCard('Atividade Recente', data.recent_activity, period, '', '📋'));
        if (data.never_borrowed && data.never_borrowed.length > 0) row9.appendChild(bmCreateUtilityCard('Nunca Emprestados', data.never_borrowed.slice(0, 5).map(function(b) { return typeof b === 'string' ? b : (b.title || b); }), period, 'top_books', '🟡'));
        dashboard.appendChild(row9);

        var metaHeader = document.createElement('h2');
        metaHeader.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        metaHeader.textContent = 'Meta & Perfil';
        dashboard.appendChild(metaHeader);
        var row10 = document.createElement('div');
        row10.className = 'grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6';
        if (data.reading_goal) {
            var goalCard = document.createElement('div');
            goalCard.className = 'bg-white rounded-xl p-5 shadow-sm';
            var pct = Math.round((data.reading_goal.current / data.reading_goal.target) * 100);
            var marksHTML = '<div class="flex justify-between text-xs text-gray-400 mt-1"><span>0</span><span>' + Math.round(data.reading_goal.target * 0.25) + '</span><span>' + Math.round(data.reading_goal.target * 0.5) + '</span><span>' + Math.round(data.reading_goal.target * 0.75) + '</span><span>' + data.reading_goal.target + '</span></div>';
            goalCard.innerHTML = '<h3 class="text-base font-semibold text-gray-800 mb-2">🎯 Meta de Leitura</h3>' +
                '<div class="flex items-end gap-2 mb-3"><span class="text-3xl font-bold text-gray-900">' + data.reading_goal.current + '</span><span class="text-sm text-gray-500 mb-1">de ' + data.reading_goal.target + ' livros</span><span class="ml-auto text-2xl font-bold text-green-600">' + pct + '%</span></div>' +
                '<div class="h-4 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600" style="width:' + pct + '%; transition: width 1s ease;"></div></div>' + marksHTML;
            row10.appendChild(goalCard);
        }
        var topStudent = (data.students && data.students.length > 0) ? data.students[0] : null;
        row10.appendChild(bmCreateProfileCard(topStudent, period));
        dashboard.appendChild(row10);

        setTimeout(function() {
            var dash = document.getElementById('bm-dashboard');
            if (!dash) return;
            var dragCard = null;
            var placeholder = document.createElement('div');
            placeholder.className = 'border-2 border-dashed border-blue-400 rounded-xl bg-blue-50 h-24';
            placeholder.style.display = 'none';
            dash.appendChild(placeholder);
            dash.querySelectorAll('.bm-card-clickable').forEach(function(card) {
                card.setAttribute('draggable', 'true');
                card.addEventListener('dragstart', function(e) {
                    dragCard = this; this.style.opacity = '0.5'; e.dataTransfer.effectAllowed = 'move';
                    setTimeout(function() { placeholder.style.display = 'block'; }, 0);
                });
                card.addEventListener('dragend', function() {
                    this.style.opacity = '1'; placeholder.style.display = 'none'; dragCard = null;
                    dash.querySelectorAll('.bm-card-clickable').forEach(function(c) { c.style.transform = ''; });
                });
                card.addEventListener('dragover', function(e) {
                    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
                    var rect = this.getBoundingClientRect();
                    var midY = rect.top + rect.height / 2;
                    if (e.clientY < midY) { this.parentNode.insertBefore(placeholder, this); }
                    else { this.parentNode.insertBefore(placeholder, this.nextSibling); }
                });
            });
            dash.addEventListener('dragover', function(e) { e.preventDefault(); });
            dash.addEventListener('drop', function(e) {
                e.preventDefault();
                if (dragCard && placeholder.style.display !== 'none') {
                    placeholder.parentNode.insertBefore(dragCard, placeholder);
                    placeholder.style.display = 'none';
                }
            });
        }, 500);
    }

    function bmRenderDashboardKPIs(data) {
        var dashboard = document.getElementById('bm-dashboard');
        if (!dashboard) return;

        var kpiTitle1 = document.createElement('h2');
        kpiTitle1.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        kpiTitle1.textContent = 'Indicadores Principais';
        dashboard.appendChild(kpiTitle1);
        var row1 = document.createElement('div');
        row1.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6';
        row1.appendChild(bmCreateKPICard('EMPRÉSTIMOS', data.total_loans, data.total_loans_prev ? calculateVariance(data.total_loans, data.total_loans_prev) : null, data.period || 'month', 'overview', data.period, 'all', '', data.spark_loans));
        row1.appendChild(bmCreateKPICard('DEVOLUÇÕES', data.total_returns, data.total_returns_prev ? calculateVariance(data.total_returns, data.total_returns_prev) : null, data.period || 'month', 'overview', data.period, 'all', '', data.spark_returns));
        row1.appendChild(bmCreateKPICard('EM ATRASO', data.total_overdue, null, data.period || 'month', 'active_penalties', data.period, 'all', '', data.spark_overdue));
        row1.appendChild(bmCreateKPICard('RESERVAS PENDENTES', data.total_reservations, null, data.period || 'month', 'overview', data.period, 'all', '', data.spark_reservations));
        dashboard.appendChild(row1);

        var kpiTitle2 = document.createElement('h2');
        kpiTitle2.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        kpiTitle2.textContent = 'Indicadores Secundários';
        dashboard.appendChild(kpiTitle2);
        var row2 = document.createElement('div');
        row2.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6';
        row2.appendChild(bmCreateKPICard('MÉDIA POR ALUNO', data.average || '0', null, data.period || 'month', 'class_reading', data.period, 'all', '', data.spark_average));
        row2.appendChild(bmCreateKPICard('TAXA DE DEVOLUÇÃO', data.return_rate || '0%', null, data.period || 'month', 'overview', data.period, 'all', '', data.spark_return_rate));
        row2.appendChild(bmCreateKPICard('TEMPO MÉDIO LEITURA', data.avg_days || '0 dias', null, data.period || 'month', 'top_books', data.period, 'all', '', data.spark_avg_days));
        row2.appendChild(bmCreateKPICard('GIRO DO ACERVO', data.turnover || '0', null, data.period || 'month', 'overview', data.period, 'all', '', data.spark_turnover));
        dashboard.appendChild(row2);

        var kpiTitle3 = document.createElement('h2');
        kpiTitle3.className = 'text-sm font-bold text-gray-700 uppercase tracking-wide mb-3';
        kpiTitle3.textContent = 'Engajamento';
        dashboard.appendChild(kpiTitle3);
        var row3 = document.createElement('div');
        row3.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6';
        row3.appendChild(bmCreateKPICard('MULTAS ATIVAS', data.total_penalties || '0', null, data.period || 'month', 'active_penalties', data.period, 'all', '', data.spark_penalties));
        row3.appendChild(bmCreateKPICard('TOTAL RESENHAS', data.total_reviews || '0', null, data.period || 'month', 'student_performance', data.period, 'all', '', data.spark_reviews));
        row3.appendChild(bmCreateKPICard('VÍDEO-RESENHAS', data.total_videos || '0', null, data.period || 'month', 'student_performance', data.period, 'all', '', data.spark_videos));
        row3.appendChild(bmCreateKPICard('TAXA PARTICIPAÇÃO', data.participation_rate || '0%', null, data.period || 'month', 'student_performance', data.period, 'all', '', data.spark_participation));
        dashboard.appendChild(row3);
    }

    function bmRenderStudentPerformance(data) {
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        if (!kpi) return;
        kpi.classList.remove('hidden');
        const cards = kpi.querySelectorAll('.bg-white');
        if (data.students && data.total_students) {
            if (cards.length >= 4) {
                bmFillKPICard(cards[0], 'ALUNOS', data.total_students, null);
                bmFillKPICard(cards[1], 'LIVROS LIDOS', data.total_books, null);
                bmFillKPICard(cards[2], 'RESENHAS', data.total_reviews, null);
                bmFillKPICard(cards[3], 'VÍDEOS', data.total_videos, null);
            }
            bmHideChart(); bmHideTable();
            if (data.students.length >= 3) bmRenderTopReaders(data.students);
            if (data.inactive_students && data.inactive_students.length > 0) bmRenderInactiveAlerts(data.inactive_students);
            return;
        }
        if (cards.length >= 4) {
            bmFillKPICard(cards[0], 'LIVROS LIDOS', data.books_read, null);
            bmFillKPICard(cards[1], 'EMPRÉSTIMOS ATIVOS', data.active_loans, null);
            bmFillKPICard(cards[2], 'RESENHAS', data.reviews, null);
            bmFillKPICard(cards[3], 'XP', data.xp, null);
        }
        bmHideChart();
        if (data.books_read_list && data.books_read_list.length > 0) {
            bmRenderTable(['Livro', 'Autor', 'Devolvido em'], data.books_read_list.map(function(b) { return [b.title, b.author || '—', b.returned_date || '—']; }));
        } else { bmHideTable(); }
    }

    function bmRenderClassReading(data) {
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        if (!kpi) return;
        kpi.classList.remove('hidden');
        const cards = kpi.querySelectorAll('.bg-white');
        if (cards.length >= 4) {
            bmFillKPICard(cards[0], 'ALUNOS', data.total_students, null);
            bmFillKPICard(cards[1], 'LIVROS LIDOS', data.total_books, null);
            bmFillKPICard(cards[2], 'MÉDIA POR ALUNO', data.average, null);
            bmFillKPICard(cards[3], 'EM ATRASO', data.overdue_count, null);
        }
        bmHideChart();
        if (data.students && data.students.length > 0) {
            bmRenderTable(['Aluno', 'Livros Lidos', 'Status'], data.students.map(function(s) { return [s.name, s.books_read, s.has_overdue ? 'Atrasado' : 'Em dia']; }));
            if (data.students.length >= 3) bmRenderTopReaders(data.students);
        } else { bmHideTable(); }
        if (data.never_read && data.never_read.length > 0) bmRenderInactiveAlerts(data.never_read);
    }

    function bmRenderPenalties(data) {
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        if (!kpi) return;
        kpi.classList.remove('hidden');
        const cards = kpi.querySelectorAll('.bg-white');
        if (cards.length >= 1) bmFillKPICard(cards[0], 'TOTAL DE MULTAS ATIVAS', data.total, '');
        for (let i = 1; i < cards.length; i++) cards[i].classList.add('hidden');
        bmHideChart();
        if (data.penalties && data.penalties.length > 0) {
            bmRenderTable(['Aluno', 'Tipo', 'Descrição', 'Data', 'Até'], data.penalties.map(function(p) {
                const typeLabel = p.type === 'warning' ? 'Advertência' : (p.type === 'suspension' ? 'Suspensão' : 'Multa');
                return [p.student_name, typeLabel, p.note || '—', p.date || '—', p.until || '—'];
            }));
        } else { bmHideTable(); }
    }

    function bmRenderGenreRanking(data) {
        bmHideKPI();
        if (data.genres && Object.keys(data.genres).length > 0) bmRenderPieChart(data.genres, 'Distribuição de Gêneros');
        bmHideTable();
    }

    function bmRenderTopBooks(data) {
        bmHideKPI(); bmHideChart();
        if (data.books && data.books.length > 0) {
            bmRenderTable(['#', 'Livro', 'Autor', 'Empréstimos'], data.books.map(function(b, i) { return [i + 1, b.title, b.author || '—', b.loans]; }));
        }
    }

    function bmRenderReadingTrend(data) {
        bmHideKPI();
        if (data.months && Object.keys(data.months).length > 0) bmRenderLineChart(data.months, 'Tendência de Leitura');
        bmHideTable();
    }

    function bmRenderCustom(data) {
        bmHideKPI(); bmHideChart();
        if (data.columns && data.rows && data.rows.length > 0) {
            var labels = { 'name': 'Nome', 'group': 'Turma', 'books_read': 'Livros Lidos', 'reviews': 'Resenhas', 'videos': 'Vídeos', 'xp': 'XP', 'badges': 'Medalhas', 'penalties': 'Multas' };
            var headers = data.columns.map(function(col) { return labels[col] || col; });
            bmRenderTable(headers, data.rows.map(function(row) { return data.columns.map(function(col) { return row[col] !== undefined ? row[col] : '—'; }); }));
        } else { bmHideTable(); }
    }

    function bmFillKPICard(card, label, value, variance) {
        const labelEl = card.querySelector('.text-xs.font-medium.text-gray-500.uppercase');
        const valueEl = card.querySelector('.text-2xl.font-bold');
        const varianceEl = card.querySelector('.mt-3 span:first-child');
        const periodEl = card.querySelector('.mt-3 span:last-child');
        if (labelEl) labelEl.textContent = label;
        if (valueEl) valueEl.textContent = value !== undefined ? value : '0';
        if (varianceEl) {
            if (variance && variance.formatted) {
                varianceEl.textContent = variance.formatted;
                varianceEl.classList.remove('text-green-600', 'text-negative');
                varianceEl.classList.add(variance.isPositive ? 'text-positive' : 'text-negative');
            } else { varianceEl.textContent = ''; }
        }
        if (periodEl) periodEl.textContent = variance && variance.formatted ? 'vs período anterior' : '';
        card.classList.remove('hidden');
    }

    function bmRenderBarChart(data, title) {
        const chartSection = document.querySelector('[data-section="bar-chart"]');
        if (!chartSection) return;
        chartSection.classList.remove('hidden');
        const h3 = chartSection.querySelector('h3');
        if (h3) h3.textContent = title;
        const container = document.getElementById('bm-chart-container');
        if (!container) return;
        const entries = Object.entries(data);
        if (entries.length === 0) return;
        const max = Math.max(...entries.map(e => e[1]));
        let html = '';
        entries.forEach(function([label, value]) {
            const pct = max > 0 ? Math.round((value / max) * 100) : 0;
            html += '<div class="flex items-center gap-3 bm-bar-row"><div class="w-28 text-xs text-gray-600 text-right truncate" title="' + label + '">' + label + '</div><div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden bm-bar-bg"><div class="bg-blue-500 h-full rounded-full flex items-center bm-bar-fill animate-slide-in" style="width:0%;" data-width="' + pct + '%"><span class="text-xs text-white font-bold ml-2">' + value + '</span></div></div></div>';
        });
        container.innerHTML = html;
        requestAnimationFrame(function() { container.querySelectorAll('.bm-bar-fill').forEach(function(bar) { bar.style.width = bar.getAttribute('data-width'); }); });
        container.querySelectorAll('.bm-bar-row').forEach(function(row) {
            row.addEventListener('mouseenter', function() { this.querySelector('.bm-bar-bg').style.transform = 'scale(1.02)'; this.querySelector('.bm-bar-bg').style.transition = 'transform 0.15s ease'; });
            row.addEventListener('mouseleave', function() { this.querySelector('.bm-bar-bg').style.transform = 'scale(1)'; });
        });
    }

    function bmRenderTable(headers, rows) {
        const tableSection = document.querySelector('[data-section="data-table"]');
        if (!tableSection) return;
        tableSection.classList.remove('hidden');
        const thead = tableSection.querySelector('thead tr');
        const tbody = tableSection.querySelector('tbody');
        if (!thead || !tbody) return;
        thead.innerHTML = headers.map(function(h) { return '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' + h + '</th>'; }).join('');
        tbody.innerHTML = rows.map(function(row, i) {
            const rowClass = i % 2 === 0 ? '' : 'bg-gray-50';
            return '<tr class="hover:bg-gray-50 transition-colors ' + rowClass + '">' + row.map(function(cell) { return '<td class="px-4 py-3 text-gray-900">' + cell + '</td>'; }).join('') + '</tr>';
        }).join('');
    }

    function bmRenderPieChart(data, title) {
        const section = document.querySelector('[data-section="pie-chart"]');
        if (!section) return;
        section.classList.remove('hidden');
        const h3 = section.querySelector('h3');
        if (h3) h3.textContent = title;
        const container = document.getElementById('bm-pie-container');
        const legend = document.getElementById('bm-pie-legend');
        if (!container || !legend) return;
        const entries = Object.entries(data);
        if (entries.length === 0) { section.classList.add('hidden'); return; }
        const total = entries.reduce(function(sum, e) { return sum + e[1]; }, 0);
        const colors = ['#3b82f6','#ef4444','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#f97316'];
        const size = 192, cx = size/2, cy = size/2, outerR = 80, innerR = 50;
        let currentAngle = -Math.PI / 2;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '">';
        entries.forEach(function(entry, i) {
            var sliceAngle = (entry[1] / total) * 2 * Math.PI;
            var x1 = cx + outerR * Math.cos(currentAngle), y1 = cy + outerR * Math.sin(currentAngle);
            var x2 = cx + outerR * Math.cos(currentAngle + sliceAngle), y2 = cy + outerR * Math.sin(currentAngle + sliceAngle);
            var largeArc = sliceAngle > Math.PI ? 1 : 0;
            svg += '<path d="M' + cx + ',' + cy + ' L' + x1 + ',' + y1 + ' A' + outerR + ',' + outerR + ' 0 ' + largeArc + ' 1 ' + x2 + ',' + y2 + ' Z" fill="' + colors[i % colors.length] + '" class="bm-donut-slice" stroke="#fff" stroke-width="1"><title>' + entry[0] + ': ' + entry[1] + '</title></path>';
            var midAngle = currentAngle + sliceAngle / 2, labelR = (outerR + innerR) / 2;
            var pct = Math.round((entry[1] / total) * 100);
            if (pct >= 5) svg += '<text x="' + (cx + labelR * Math.cos(midAngle)) + '" y="' + (cy + labelR * Math.sin(midAngle) - 4) + '" text-anchor="middle" fill="#fff" font-size="10" font-weight="600">' + pct + '%</text>';
            currentAngle += sliceAngle;
        });
        if (innerR > 0) svg += '<circle cx="' + cx + '" cy="' + cy + '" r="' + innerR + '" fill="#fff" />';
        svg += '</svg>';
        container.innerHTML = svg;
        var legendHtml = '';
        entries.forEach(function(entry, i) {
            var pct = Math.round((entry[1] / total) * 100);
            legendHtml += '<div class="bm-legend-item"><span class="bm-legend-dot" style="background:' + colors[i % colors.length] + ';"></span><span>' + entry[0] + '</span><span class="bm-legend-value">' + entry[1] + ' (' + pct + '%)</span></div>';
        });
        legend.innerHTML = legendHtml;
    }

    function bmRenderLineChart(data, title) {
        const section = document.querySelector('[data-section="line-chart"]');
        if (!section) return;
        section.classList.remove('hidden');
        const h3 = section.querySelector('h3');
        if (h3) h3.textContent = title;
        const container = document.getElementById('bm-line-container');
        if (!container) return;
        const entries = Object.entries(data);
        if (entries.length === 0) { section.classList.add('hidden'); return; }
        const values = entries.map(function(e) { return e[1]; });
        const maxVal = Math.max.apply(null, values), minVal = Math.min.apply(null, values), range = maxVal - minVal || 1;
        const padding = { top: 20, right: 20, bottom: 40, left: 40 }, width = 600, height = 256;
        const chartW = width - padding.left - padding.right, chartH = height - padding.top - padding.bottom;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + width + ' ' + height + '" class="bm-line-chart-svg">';
        for (var g = 0; g <= 4; g++) {
            var gy = padding.top + (chartH / 4) * g;
            svg += '<line x1="' + padding.left + '" y1="' + gy + '" x2="' + (width - padding.right) + '" y2="' + gy + '" class="bm-line-grid" />';
            svg += '<text x="' + (padding.left - 6) + '" y="' + (gy + 4) + '" text-anchor="end" class="bm-line-axis-text">' + Math.round(maxVal - (range / 4) * g) + '</text>';
        }
        var points = '', pathD = '', stepX = entries.length > 1 ? chartW / (entries.length - 1) : chartW;
        entries.forEach(function(entry, i) {
            var px = padding.left + stepX * i, py = padding.top + chartH - ((entry[1] - minVal) / range) * chartH;
            points += '<circle cx="' + px + '" cy="' + py + '" r="3" class="bm-line-point" data-value="' + entry[1] + '" data-label="' + entry[0] + '" />';
            pathD += (i === 0 ? 'M' : 'L') + px + ' ' + py + ' ';
            var label = entry[0]; if (label.length > 3) label = label.substring(5);
            svg += '<text x="' + px + '" y="' + (height - 8) + '" text-anchor="middle" class="bm-line-axis-text">' + label + '</text>';
        });
        svg += '<path d="' + pathD + '" class="bm-line-path" />' + points + '</svg>';
        container.innerHTML = svg;
        var tooltip = document.createElement('div'); tooltip.className = 'bm-tooltip hidden'; container.appendChild(tooltip);
        container.querySelectorAll('.bm-line-point').forEach(function(point) {
            point.addEventListener('mouseenter', function() { tooltip.textContent = this.getAttribute('data-label') + ': ' + this.getAttribute('data-value'); tooltip.classList.remove('hidden'); });
            point.addEventListener('mousemove', function(e) { var rect = container.getBoundingClientRect(); tooltip.style.left = (e.clientX - rect.left) + 'px'; tooltip.style.top = (e.clientY - rect.top) + 'px'; });
            point.addEventListener('mouseleave', function() { tooltip.classList.add('hidden'); });
        });
    }

    function bmRenderTopReaders(data) {
        const section = document.querySelector('[data-section="top-readers"]');
        if (!section) return;
        const top3 = rankEntities(data, 'books_read', 3);
        if (top3.length === 0) { section.classList.add('hidden'); return; }
        section.classList.remove('hidden');
        const maxBooks = top3[0].books_read || 1;
        const slots = [
            { id: 'bm-reader-gold', index: 0 },
            { id: 'bm-reader-silver', index: 1 },
            { id: 'bm-reader-bronze', index: 2 }
        ];
        slots.forEach(function(slot) {
            const card = document.getElementById(slot.id);
            if (!card) return;
            const student = top3[slot.index];
            if (student) {
                card.classList.remove('hidden');
                const nameEl = card.querySelector('.text-lg');
                const countEl = card.querySelector('.text-sm');
                const bar = card.querySelector('[class*="bg-"][class*="h-full"]');
                if (nameEl) nameEl.textContent = student.name;
                if (countEl) countEl.textContent = student.books_read + ' livros';
                if (bar) bar.style.width = Math.round((student.books_read / maxBooks) * 100) + '%';
            } else { card.classList.add('hidden'); }
        });
    }

    function bmRenderInactiveAlerts(data) {
        const section = document.querySelector('[data-section="inactive-alerts"]');
        if (!section || !data || data.length === 0) { if (section) section.classList.add('hidden'); return; }
        section.classList.remove('hidden');
        const list = document.getElementById('bm-inactive-list');
        if (!list) return;
        var html = '';
        data.forEach(function(name) { html += '<span class="bg-white text-red-700 text-xs font-medium px-3 py-1 rounded-full border border-red-300">' + name + '</span>'; });
        list.innerHTML = html;
    }

    function bmHideKPI() { const kpi = document.querySelector('[data-section="kpi-cards"]'); if (kpi) kpi.classList.add('hidden'); }
    function bmHideChart() { const chart = document.querySelector('[data-section="bar-chart"]'); if (chart) chart.classList.add('hidden'); }
    function bmHideTable() { const table = document.querySelector('[data-section="data-table"]'); if (table) table.classList.add('hidden'); }

    function bmExportPDF() {
        const form = document.getElementById('bm-report-form');
        if (!form) return;
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        let url = bm.ajaxUrl + '?action=bm_export_report_pdf';
        url += '&type=' + (params.get('bm_report_type') || 'overview');
        url += '&period=' + (params.get('bm_period') || 'month');
        url += '&date_start=' + (params.get('bm_date_start') || '');
        url += '&date_end=' + (params.get('bm_date_end') || '');
        url += '&subject_id=' + (params.get('bm_subject_id') || '0');
        url += '&group=' + (params.get('bm_group') || '');
        window.open(url, '_blank');
    }

    function bmDrillToReportInline(type, period, subject, subjectId) {
        var params = new URLSearchParams();
        params.append('action', 'bm_get_report_data');
        params.append('nonce', bm.nonce);
        params.append('bm_report_type', type);
        params.append('bm_period', period);
        params.append('bm_subject', subject);
        if (subjectId) params.append('bm_subject_id', subjectId);
        var drillSection = document.getElementById('bm-drill-detail');
        if (!drillSection) {
            drillSection = document.createElement('div');
            drillSection.id = 'bm-drill-detail';
            drillSection.className = 'mt-6 border-t border-gray-200 pt-4';
            var dashboard = document.getElementById('bm-dashboard');
            if (dashboard) dashboard.parentNode.insertBefore(drillSection, dashboard.nextSibling);
        }
        drillSection.innerHTML = '<div class="flex items-center justify-between mb-4"><h3 class="text-lg font-bold text-gray-900">Carregando...</h3><div class="flex items-center gap-2"><input id="bm-drill-search" type="text" placeholder="Buscar..." class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 w-48"><button id="bm-drill-csv" class="text-sm bg-blue-600 text-white font-medium px-3 py-1.5 rounded-lg hover:bg-blue-700">Exportar CSV</button><button class="bm-close-drill text-sm text-blue-600 hover:underline">← Voltar</button></div></div>';
        drillSection.classList.remove('hidden');
        drillSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        document.querySelector('.bm-close-drill').addEventListener('click', function() { drillSection.classList.add('hidden'); drillSection.innerHTML = ''; });
        fetch(bm.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success && response.data) {
                var data = response.data;
                var html = '<div class="flex items-center justify-between mb-4"><h3 class="text-lg font-bold text-gray-900">' + (data.title || 'Relatório') + '</h3><div class="flex items-center gap-2"><input id="bm-drill-search2" type="text" placeholder="Buscar..." class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 w-48"><button id="bm-drill-csv2" class="text-sm bg-blue-600 text-white font-medium px-3 py-1.5 rounded-lg hover:bg-blue-700">Exportar CSV</button><button class="bm-close-drill2 text-sm text-blue-600 hover:underline">← Voltar</button></div></div>';
                drillSection.innerHTML = html;
                document.querySelector('.bm-close-drill2').addEventListener('click', function() { drillSection.classList.add('hidden'); drillSection.innerHTML = ''; });
                var tempContainer = document.createElement('div');
                drillSection.appendChild(tempContainer);
                var tableData = null;
                if (type === 'student_performance' && data.students) {
                    if (data.students.length >= 3) { var topDiv = document.createElement('div'); topDiv.innerHTML = '<h4 class="text-base font-semibold text-gray-700 mb-2">Top 3 Leitores</h4>'; tempContainer.appendChild(topDiv); }
                    var tableDiv = document.createElement('div'); tableDiv.innerHTML = '<h4 class="text-base font-semibold text-gray-700 mb-2 mt-4">Todos os Alunos</h4>'; tempContainer.appendChild(tableDiv);
                    var table = document.createElement('table'); table.className = 'w-full text-sm';
                    table.innerHTML = '<thead class="bg-gray-50 border-b"><tr><th class="px-4 py-2 text-left">Aluno</th><th class="px-4 py-2 text-left">Livros</th><th class="px-4 py-2 text-left">Resenhas</th><th class="px-4 py-2 text-left">Vídeos</th></tr></thead><tbody>' + data.students.map(function(s) { return '<tr class="border-b hover:bg-gray-50"><td class="px-4 py-2">' + s.name + '</td><td class="px-4 py-2">' + (s.books_read||0) + '</td><td class="px-4 py-2">' + (s.reviews||0) + '</td><td class="px-4 py-2">' + (s.videos||0) + '</td></tr>'; }).join('') + '</tbody>';
                    tableDiv.appendChild(table);
                    tableData = { cols: ['Aluno','Livros','Resenhas','Vídeos'], rows: data.students.map(function(s) { return [s.name, s.books_read||0, s.reviews||0, s.videos||0]; }) };
                } else if (type === 'top_books' && data.books) {
                    var table = document.createElement('table'); table.className = 'w-full text-sm';
                    table.innerHTML = '<thead class="bg-gray-50 border-b"><tr><th class="px-4 py-2 text-left">#</th><th class="px-4 py-2 text-left">Livro</th><th class="px-4 py-2 text-left">Autor</th><th class="px-4 py-2 text-left">Empréstimos</th></tr></thead><tbody>' + data.books.map(function(b, i) { return '<tr class="border-b hover:bg-gray-50"><td class="px-4 py-2">' + (i+1) + '</td><td class="px-4 py-2 font-medium">' + b.title + '</td><td class="px-4 py-2">' + (b.author||'—') + '</td><td class="px-4 py-2">' + b.loans + '</td></tr>'; }).join('') + '</tbody>';
                    tempContainer.appendChild(table);
                    tableData = { cols: ['#','Livro','Autor','Empréstimos'], rows: data.books.map(function(b, i) { return [i+1, b.title, b.author||'—', b.loans]; }) };
                } else if (type === 'active_penalties' && data.penalties) {
                    var table = document.createElement('table'); table.className = 'w-full text-sm';
                    table.innerHTML = '<thead class="bg-gray-50 border-b"><tr><th class="px-4 py-2 text-left">Aluno</th><th class="px-4 py-2 text-left">Tipo</th><th class="px-4 py-2 text-left">Data</th></tr></thead><tbody>' + data.penalties.map(function(p) { return '<tr class="border-b hover:bg-gray-50"><td class="px-4 py-2">' + p.student_name + '</td><td class="px-4 py-2">' + p.type + '</td><td class="px-4 py-2">' + p.date + '</td></tr>'; }).join('') + '</tbody>';
                    tempContainer.appendChild(table);
                    tableData = { cols: ['Aluno','Tipo','Data'], rows: data.penalties.map(function(p) { return [p.student_name, p.type, p.date]; }) };
                } else { tempContainer.innerHTML = '<p class="text-gray-500">Dados carregados. Use os filtros do topo para explorar outros períodos.</p>'; }
                if (tableData) {
                    document.getElementById('bm-drill-search2').addEventListener('input', function() {
                        var q = this.value.toLowerCase();
                        tempContainer.querySelectorAll('tbody tr').forEach(function(tr) { tr.style.display = tr.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none'; });
                    });
                    document.getElementById('bm-drill-csv2').addEventListener('click', function() {
                        var csv = tableData.cols.join(',') + '\n' + tableData.rows.map(function(r) { return r.map(function(c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(','); }).join('\n');
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
                        a.download = (data.title || 'relatorio').replace(/\s+/g, '_') + '.csv';
                        a.click();
                    });
                }
            }
        });
    }

    function bmCreateKPICard(label, value, variance, period, drillType, drillPeriod, drillSubject, drillSubjectId, sparkData) {
        var card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-5 shadow-sm border-l-4 border-blue-500 bm-card-clickable';
        card.style.cursor = 'pointer';
        if (drillType) {
            card.setAttribute('data-drill-type', drillType);
            card.setAttribute('data-drill-period', drillPeriod);
            card.setAttribute('data-drill-subject', drillSubject || 'all');
            card.setAttribute('data-drill-subject-id', drillSubjectId || '');
        }
        var varianceHtml = '';
        if (variance && variance.formatted) {
            var varClass = variance.isPositive ? 'text-positive' : 'text-negative';
            varianceHtml = '<span class="text-xs font-medium ' + varClass + '">' + variance.formatted + '</span>';
        }
        var sparkHtml = '';
        if (sparkData && sparkData.length > 0) { sparkHtml = '<div class="mt-1">' + bmSparklineSVG(sparkData, '#3b82f6') + '</div>'; }
        var periodHtml = '';
        if (period) {
            periodHtml = '<select class="bm-kpi-period text-xs border border-gray-200 rounded px-1 py-0.5 mt-2" onclick="event.stopPropagation()">' +
                '<option value="week"' + (period === 'week' ? ' selected' : '') + '>Semana</option>' +
                '<option value="month"' + (period === 'month' ? ' selected' : '') + '>Mês</option>' +
                '<option value="bimester"' + (period === 'bimester' ? ' selected' : '') + '>Bimestre</option>' +
                '<option value="semester"' + (period === 'semester' ? ' selected' : '') + '>Semestre</option>' +
                '<option value="year"' + (period === 'year' ? ' selected' : '') + '>Ano</option>' +
                '</select>';
        }
        card.innerHTML = '<div class="flex items-center justify-between"><div>' +
            '<p class="text-xs font-medium text-gray-500 uppercase tracking-wider">' + label + '</p>' +
            '<p class="text-2xl font-bold text-gray-900 mt-1">' + value + '</p></div>' +
            '<div class="w-10 h-10 rounded-full ' + (label.indexOf('EMPRÉSTIMOS') >= 0 ? 'bg-blue-50' : label.indexOf('DEVOLUÇÕES') >= 0 || label.indexOf('LIVROS LIDOS') >= 0 ? 'bg-emerald-50' : label.indexOf('EM ATRASO') >= 0 || label.indexOf('MULTAS') >= 0 ? 'bg-red-50' : 'bg-amber-50') + ' flex items-center justify-center">' +
            '<svg class="w-5 h-5 ' + (label.indexOf('EMPRÉSTIMOS') >= 0 ? 'text-blue-600' : label.indexOf('DEVOLUÇÕES') >= 0 || label.indexOf('LIVROS LIDOS') >= 0 ? 'text-emerald-600' : label.indexOf('EM ATRASO') >= 0 || label.indexOf('MULTAS') >= 0 ? 'text-red-600' : 'text-amber-600') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle></svg>' +
            '</div></div>' + sparkHtml +
            '<div class="mt-3 flex items-center gap-1">' + (varianceHtml || '<span class="text-xs font-medium text-green-600"></span>') + '<span class="text-xs text-gray-400"></span></div>' + periodHtml;
        card.addEventListener('click', function() { if (drillType) bmDrillToReportInline(drillType, drillPeriod, drillSubject || 'all', drillSubjectId || ''); });
        return card;
    }

    function bmCreateHighlightCard(type, data, period) {
        var card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-5 shadow-sm border-l-4 border-yellow-400 bm-card-clickable text-center';
        var drillType, drillSubject, drillSubjectId;
        var content = '';
        if (type === 'student') {
            drillType = 'student_performance'; drillSubject = 'student'; drillSubjectId = data ? (data.id || '') : '';
            var initial = data && data.name ? data.name.charAt(0).toUpperCase() : '?';
            content = '<div class="w-14 h-14 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white text-xl font-bold mx-auto mb-2">' + initial + '</div><p class="text-lg font-bold text-gray-900">' + (data ? (data.name || '—') : '—') + '</p><p class="text-sm text-gray-500">' + (data ? (data.books_read || 0) : 0) + ' livros</p>';
        } else if (type === 'book') {
            drillType = 'top_books'; drillSubject = 'all'; drillSubjectId = '';
            var initialB = data && data.title ? data.title.charAt(0).toUpperCase() : '?';
            content = '<div class="w-12 h-16 rounded bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white text-xl font-bold mx-auto mb-2 shadow">' + initialB + '</div><p class="text-lg font-bold text-gray-900">' + (data ? (data.title || '—') : '—') + '</p><p class="text-sm text-gray-500">' + (data ? (data.author || '') : '') + '</p><p class="text-sm text-gray-500">' + (data ? (data.loans || 0) : 0) + ' empréstimos</p>';
        } else if (type === 'revelation') {
            drillType = 'student_performance'; drillSubject = 'student'; drillSubjectId = data ? (data.id || '') : '';
            var initialR = data && data.name ? data.name.charAt(0).toUpperCase() : '?';
            content = '<div class="w-14 h-14 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white text-xl font-bold mx-auto mb-2">' + initialR + '</div><p class="text-lg font-bold text-gray-900">' + (data ? (data.name || '—') : '—') + '</p><p class="text-sm text-gray-500">+' + (data ? (data.increase || 0) : 0) + ' livros vs período anterior</p>';
        }
        card.innerHTML = content;
        card.addEventListener('click', function() { bmDrillToReportInline(drillType, period, drillSubject, drillSubjectId); });
        return card;
    }

    function bmCreateChartCard(title, data, chartType, period, drillType) {
        var card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-5 shadow-sm bm-card-clickable';
        if (drillType) card.setAttribute('data-drill-type', drillType);
        var toggles = '<div class="flex items-center gap-2 mb-3" onclick="event.stopPropagation()">' +
            '<button class="bm-toggle-chart text-xs px-2 py-1 rounded ' + (chartType === 'bar' ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600') + '" data-chart="bar">Barras</button>' +
            '<button class="bm-toggle-chart text-xs px-2 py-1 rounded ' + (chartType === 'line' ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600') + '" data-chart="line">Linha</button>' +
            '<button class="bm-toggle-chart text-xs px-2 py-1 rounded ' + (chartType === 'pizza' ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600') + '" data-chart="pizza">Pizza</button>' +
            '<select class="bm-chart-period text-xs border border-gray-200 rounded px-1 py-0.5 ml-auto" onclick="event.stopPropagation()">' +
            '<option value="week"' + (period === 'week' ? ' selected' : '') + '>Semana</option>' +
            '<option value="month"' + (period === 'month' ? ' selected' : '') + '>Mês</option>' +
            '<option value="bimester"' + (period === 'bimester' ? ' selected' : '') + '>Bimestre</option>' +
            '<option value="semester"' + (period === 'semester' ? ' selected' : '') + '>Semestre</option>' +
            '<option value="year"' + (period === 'year' ? ' selected' : '') + '>Ano</option>' +
            '</select></div>';
        var chartContainer = '<div id="bm-chart-' + drillType + '" class="w-full" style="min-height:200px;" onclick="event.stopPropagation()"></div>';
        card.innerHTML = '<h3 class="text-base font-semibold text-gray-800 mb-2">' + title + '</h3>' + toggles + chartContainer;
        setTimeout(function() {
            var chartDiv = card.querySelector('[id^="bm-chart-"]');
            if (chartDiv) {
                if (chartType === 'pizza') bmRenderPieChartInContainer(chartDiv, data, title);
                else if (chartType === 'line') bmRenderLineChartInContainer(chartDiv, data, title);
                else bmRenderBarChartInContainer(chartDiv, data, title);
            }
        }, 0);
        card.querySelectorAll('.bm-toggle-chart').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var newType = this.getAttribute('data-chart');
                card.querySelectorAll('.bm-toggle-chart').forEach(function(b) { b.className = 'bm-toggle-chart text-xs px-2 py-1 rounded bg-gray-100 text-gray-600'; });
                this.className = 'bm-toggle-chart text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 font-bold';
                var chartDiv = card.querySelector('[id^="bm-chart-"]');
                if (chartDiv) {
                    if (newType === 'pizza') bmRenderPieChartInContainer(chartDiv, data, title);
                    else if (newType === 'line') bmRenderLineChartInContainer(chartDiv, data, title);
                    else bmRenderBarChartInContainer(chartDiv, data, title);
                }
            });
        });
        card.addEventListener('click', function() { if (drillType) bmDrillToReportInline(drillType, period, 'all', ''); });
        return card;
    }

    function bmCreateRankingCard(title, data, period, drillType, limit) {
        limit = limit || 5;
        var card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-5 shadow-sm bm-card-clickable';
        if (drillType) card.setAttribute('data-drill-type', drillType);
        var toggles = '<div class="flex items-center gap-2 mb-3" onclick="event.stopPropagation()">' +
            '<button class="bm-toggle-limit text-xs px-2 py-1 rounded ' + (limit === 1 ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600') + '" data-limit="1">1</button>' +
            '<button class="bm-toggle-limit text-xs px-2 py-1 rounded ' + (limit === 3 ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600') + '" data-limit="3">3</button>' +
            '<button class="bm-toggle-limit text-xs px-2 py-1 rounded ' + (limit === 5 ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600') + '" data-limit="5">5</button>' +
            '<button class="bm-toggle-limit text-xs px-2 py-1 rounded ' + (limit === 10 ? 'bg-blue-100 text-blue-700 font-bold' : 'bg-gray-100 text-gray-600') + '" data-limit="10">10</button>' +
            '<select class="bm-ranking-period text-xs border border-gray-200 rounded px-1 py-0.5 ml-auto" onclick="event.stopPropagation()">' +
            '<option value="week"' + (period === 'week' ? ' selected' : '') + '>Semana</option>' +
            '<option value="month"' + (period === 'month' ? ' selected' : '') + '>Mês</option>' +
            '<option value="bimester"' + (period === 'bimester' ? ' selected' : '') + '>Bimestre</option>' +
            '<option value="semester"' + (period === 'semester' ? ' selected' : '') + '>Semestre</option>' +
            '<option value="year"' + (period === 'year' ? ' selected' : '') + '>Ano</option>' +
            '</select></div>';
        var listHtml = '<div class="bm-ranking-list space-y-1">';
        if (!data || data.length === 0) {
            listHtml += '<p class="text-xs text-gray-400 py-2">Nenhum dado no período</p>';
        } else {
            var maxVal = data[0].books_read || data[0].loans || data[0].reviews || data[0].videos || data[0].average || 1;
            data.slice(0, limit).forEach(function(item, i) {
                var val = item.books_read || item.loans || item.reviews || item.videos || item.average || 0;
                var pct = maxVal > 0 ? Math.round((val / maxVal) * 100) : 0;
                var medal = i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : (i + 1)));
                listHtml += '<div class="flex items-center gap-2 text-sm"><span class="w-6 text-center font-bold">' + medal + '</span><span class="flex-1 truncate">' + (item.name || item.title || item.author || '—') + '</span><span class="font-bold text-gray-700 w-8 text-right">' + val + '</span><div class="w-20 bg-gray-100 rounded-full h-2 overflow-hidden"><div class="bg-blue-400 h-full rounded-full" style="width:' + pct + '%;"></div></div></div>';
            });
        }
        listHtml += '</div>';
        card.innerHTML = '<h3 class="text-base font-semibold text-gray-800 mb-2">' + title + '</h3>' + toggles + listHtml;
        if (data && data.length > 0) {
            var maxValOuter = data[0].books_read || data[0].loans || data[0].reviews || data[0].videos || data[0].average || 1;
            card.querySelectorAll('.bm-toggle-limit').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var newLimit = parseInt(this.getAttribute('data-limit'));
                    card.querySelectorAll('.bm-toggle-limit').forEach(function(b) { b.className = 'bm-toggle-limit text-xs px-2 py-1 rounded bg-gray-100 text-gray-600'; });
                    this.className = 'bm-toggle-limit text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 font-bold';
                    var list = card.querySelector('.bm-ranking-list');
                    if (list) {
                        var html = '';
                        data.slice(0, newLimit).forEach(function(item, i) {
                            var val = item.books_read || item.loans || item.reviews || item.videos || item.average || 0;
                            var pct = maxValOuter > 0 ? Math.round((val / maxValOuter) * 100) : 0;
                            var medal = i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : (i + 1)));
                            html += '<div class="flex items-center gap-2 text-sm"><span class="w-6 text-center font-bold">' + medal + '</span><span class="flex-1 truncate">' + (item.name || item.title || item.author || '—') + '</span><span class="font-bold text-gray-700 w-8 text-right">' + val + '</span><div class="w-20 bg-gray-100 rounded-full h-2 overflow-hidden"><div class="bg-blue-400 h-full rounded-full" style="width:' + pct + '%;"></div></div></div>';
                        });
                        list.innerHTML = html;
                    }
                });
            });
        }
        card.addEventListener('click', function() { if (drillType) bmDrillToReportInline(drillType, period, 'all', ''); });
        return card;
    }

    function bmCreateAlertCard(title, data, period, drillType, icon) {
        var card = document.createElement('div');
        card.className = 'bg-red-50 rounded-xl p-5 shadow-sm border-l-4 border-red-500 bm-card-clickable';
        var listHtml = '<div class="flex flex-wrap gap-2 mt-2">';
        if (!data || data.length === 0) { listHtml += '<p class="text-xs text-gray-400 py-2">Nenhum dado no período</p>'; }
        else { data.forEach(function(item) { var name = typeof item === 'string' ? item : (item.name || item.student_name || item.title || '—'); listHtml += '<span class="bg-white text-red-700 text-xs font-medium px-3 py-1 rounded-full border border-red-300">' + name + '</span>'; }); }
        listHtml += '</div>';
        card.innerHTML = '<h3 class="text-base font-semibold text-red-800 mb-2">' + (icon || '⚠️') + ' ' + title + '</h3>' + listHtml;
        card.addEventListener('click', function() { if (drillType) bmDrillToReportInline(drillType, period, 'all', ''); });
        return card;
    }

    function bmCreateUtilityCard(title, data, period, drillType, icon) {
        var card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-5 shadow-sm bm-card-clickable';
        var contentHtml = '';
        if (title === 'Atividade Recente' && Array.isArray(data)) {
            contentHtml = '<div class="flex flex-col gap-2 mt-2">';
            if (data.length === 0) { contentHtml += '<p class="text-xs text-gray-400 py-2">Nenhuma atividade no período</p>'; }
            else { data.forEach(function(item) { var text = typeof item === 'string' ? item : (item.action || item); var time = (typeof item === 'object' && item.time) ? item.time : ''; contentHtml += '<div class="flex items-start gap-2 text-xs"><span class="w-1.5 h-1.5 rounded-full bg-green-500 mt-1.5 shrink-0"></span><span class="text-gray-600">' + text + (time ? '<span class="block text-gray-400">' + time + '</span>' : '') + '</span></div>'; }); }
            contentHtml += '</div>';
        } else if (title === 'Últimos Cadastrados' && Array.isArray(data)) {
            if (data.length === 0) { contentHtml = '<p class="text-xs text-gray-400 py-2">Nenhum dado no período</p>'; }
            else { contentHtml = '<div class="grid grid-cols-3 gap-2 mt-2">'; data.forEach(function(item) { var bookTitle = typeof item === 'string' ? item : (item.title || ''); var cover = (typeof item === 'object' && item.cover) ? item.cover : ''; var initial = bookTitle ? bookTitle.charAt(0).toUpperCase() : '?'; contentHtml += cover ? '<div class="flex flex-col items-center gap-1"><img src="' + cover + '" alt="' + bookTitle + '" class="w-full aspect-[3/4] rounded object-cover shadow"><span class="text-xs text-gray-600 text-center truncate w-full">' + bookTitle + '</span></div>' : '<div class="flex flex-col items-center gap-1"><div class="w-full aspect-[3/4] rounded bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-lg shadow">' + initial + '</div><span class="text-xs text-gray-600 text-center truncate w-full">' + bookTitle + '</span></div>'; }); contentHtml += '</div>'; }
        } else if (Array.isArray(data)) {
            if (data.length === 0) { contentHtml = '<p class="text-xs text-gray-400 py-2">Nenhum dado no período</p>'; }
            else { contentHtml = '<div class="flex flex-wrap gap-2 mt-2">'; data.forEach(function(item) { contentHtml += '<span class="bg-gray-100 text-gray-700 text-xs font-medium px-3 py-1 rounded-full">' + (typeof item === 'string' ? item : (item.title || item.name || '')) + '</span>'; }); contentHtml += '</div>'; }
        } else { contentHtml = '<p class="text-sm text-gray-600 mt-2">' + (data || '') + '</p>'; }
        card.innerHTML = '<h3 class="text-base font-semibold text-gray-800 mb-2">' + (icon || '📋') + ' ' + title + '</h3>' + contentHtml;
        card.addEventListener('click', function() { if (drillType) bmDrillToReportInline(drillType, period, 'all', ''); });
        return card;
    }

    function bmCreateProfileCard(topStudent, period) {
        var card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-5 shadow-sm';
        card.innerHTML = '<h3 class="text-base font-semibold text-gray-800 mb-2">📊 Perfil de Leitura</h3>' +
            '<div class="flex gap-2 mb-3"><input id="bm-profile-search" type="text" placeholder="Buscar aluno..." class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 flex-1"><button id="bm-profile-search-btn" class="text-sm bg-blue-600 text-white font-medium px-3 py-1.5 rounded-lg hover:bg-blue-700">Buscar</button></div>' +
            '<div id="bm-profile-radar" class="w-full" style="min-height:250px;"><p class="text-xs text-gray-400 text-center py-8">Carregando...</p></div>';
        card.querySelector('#bm-profile-search-btn').addEventListener('click', function() {
            var query = document.getElementById('bm-profile-search').value.trim();
            if (query.length < 2) return;
            var params = new URLSearchParams();
            params.append('action', 'bm_service_search_student');
            params.append('query', query);
            params.append('nonce', bm.serviceNonce);
            fetch(bm.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
            .then(r => r.json())
            .then(r => {
                if (r.found) { bmLoadRadar(card.querySelector('#bm-profile-radar'), r.student.id, period); }
                else if (r.multiple) {
                    var list = '<div class="text-xs">';
                    r.students.forEach(function(s) { list += '<div class="bm-profile-student-item cursor-pointer hover:bg-blue-50 px-2 py-1 rounded" data-student-id="' + s.id + '">' + s.name + ' (' + s.email + ')</div>'; });
                    list += '</div>';
                    card.querySelector('#bm-profile-radar').innerHTML = '<p class="text-xs text-gray-500 mb-1">Múltiplos encontrados:</p>' + list;
                    card.querySelectorAll('.bm-profile-student-item').forEach(function(item) { item.addEventListener('click', function() { bmLoadRadar(card.querySelector('#bm-profile-radar'), this.getAttribute('data-student-id'), period); }); });
                } else { card.querySelector('#bm-profile-radar').innerHTML = '<p class="text-xs text-gray-400 text-center py-8">Aluno não encontrado.</p>'; }
            });
        });
        if (topStudent && topStudent.id) { setTimeout(function() { bmLoadRadar(card.querySelector('#bm-profile-radar'), topStudent.id, period); }, 300); }
        return card;
    }

    function bmLoadRadar(container, userId, period) {
        container.innerHTML = '<p class="text-xs text-gray-400 text-center py-8">Carregando...</p>';
        var params = new URLSearchParams();
        params.append('action', 'bm_get_radar_data');
        params.append('nonce', bm.nonce);
        params.append('user_id', userId);
        params.append('period', period);
        fetch(bm.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
        .then(r => r.json())
        .then(response => {
            if (response.success && response.data && response.data.alunos && response.data.alunos.length > 0) {
                var data = response.data;
                container.innerHTML = data.alunos.length === 1 ? bmRadarSVG(data.eixos, data.alunos[0]) : bmRadarSVG(data.eixos, data.alunos[0], data.alunos[1]);
            } else { container.innerHTML = '<p class="text-xs text-gray-400 text-center py-8">Nenhum dado de leitura no período.</p>'; }
        })
        .catch(function() { container.innerHTML = '<p class="text-xs text-gray-400 text-center py-8">Erro ao carregar.</p>'; });
    }

    function bmRadarSVG(eixos, aluno1, aluno2) {
        if (!eixos || eixos.length === 0) return '<p class="text-xs text-gray-400 text-center py-8">Sem dados no período.</p>';
        var n = eixos.length, cx = 160, cy = 150, R = 110, maxV = Math.max.apply(null, aluno1.valores.concat(aluno2 ? aluno2.valores : []));
        if (maxV === 0) maxV = 1;
        var pt = function(i, v) { var a = -Math.PI / 2 + (i / n) * Math.PI * 2; var rr = (v / maxV) * R; return [cx + rr * Math.cos(a), cy + rr * Math.sin(a)]; };
        var rings = '';
        for (var g = 1; g <= 4; g++) { var rr = (g / 4) * R; var poly = eixos.map(function(_, i) { var a = -Math.PI / 2 + (i / n) * Math.PI * 2; return (cx + rr * Math.cos(a)) + ',' + (cy + rr * Math.sin(a)); }).join(' '); rings += '<polygon points="' + poly + '" fill="none" stroke="#e2e8f0" stroke-width="1"/>'; }
        var axes = eixos.map(function(e, i) { var p = pt(i, maxV); var lp = pt(i, maxV * 1.15); return '<line x1="' + cx + '" y1="' + cy + '" x2="' + p[0] + '" y2="' + p[1] + '" stroke="#e2e8f0"/><text x="' + lp[0] + '" y="' + lp[1] + '" text-anchor="middle" font-size="9" fill="#64748b">' + e + '</text>'; }).join('');
        var polyFn = function(vals, cor) { var p = vals.map(function(v, i) { return pt(i, v).join(','); }).join(' '); return '<polygon points="' + p + '" fill="' + cor + '" fill-opacity="0.18" stroke="' + cor + '" stroke-width="2"/>' + vals.map(function(v, i) { var p2 = pt(i, v); return '<circle cx="' + p2[0] + '" cy="' + p2[1] + '" r="3" fill="' + cor + '"/>'; }).join(''); };
        var svgInner = rings + axes + polyFn(aluno1.valores, aluno1.cor);
        if (aluno2) svgInner += polyFn(aluno2.valores, aluno2.cor);
        var leg = '<div class="flex items-center justify-center gap-4 text-xs mt-1"><span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:' + aluno1.cor + '"></span>' + aluno1.nome + '</span>';
        if (aluno2) leg += '<span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:' + aluno2.cor + '"></span>' + aluno2.nome + '</span>';
        leg += '</div>';
        return '<svg viewBox="0 0 320 320" class="w-full" style="max-height:280px">' + svgInner + '</svg>' + leg;
    }

    function bmRenderBarChartInContainer(container, data, title) {
        var entries = Object.entries(data);
        if (entries.length === 0) return;
        var max = Math.max.apply(null, entries.map(function(e) { return e[1]; }));
        var html = '';
        entries.forEach(function(entry) { var pct = max > 0 ? Math.round((entry[1] / max) * 100) : 0; html += '<div class="flex items-center gap-2 mb-1"><div class="w-24 text-xs text-gray-600 text-right truncate">' + entry[0] + '</div><div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden"><div class="bg-blue-500 h-full rounded-full flex items-center animate-slide-in" style="width:' + pct + '%;"><span class="text-xs text-white font-bold ml-2">' + entry[1] + '</span></div></div></div>'; });
        container.innerHTML = html;
    }

    function bmRenderPieChartInContainer(container, data, title) {
        var entries = Object.entries(data);
        if (entries.length === 0) return;
        var total = entries.reduce(function(sum, e) { return sum + e[1]; }, 0);
        var colors = ['#3b82f6','#ef4444','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#f97316'];
        var size = 180, cx = size/2, cy = size/2, outerR = 70, innerR = 40;
        var currentAngle = -Math.PI / 2;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '">';
        entries.forEach(function(entry, i) { var sliceAngle = (entry[1] / total) * 2 * Math.PI; var x1 = cx + outerR * Math.cos(currentAngle), y1 = cy + outerR * Math.sin(currentAngle); var x2 = cx + outerR * Math.cos(currentAngle + sliceAngle), y2 = cy + outerR * Math.sin(currentAngle + sliceAngle); var largeArc = sliceAngle > Math.PI ? 1 : 0; svg += '<path d="M' + cx + ',' + cy + ' L' + x1 + ',' + y1 + ' A' + outerR + ',' + outerR + ' 0 ' + largeArc + ' 1 ' + x2 + ',' + y2 + ' Z" fill="' + colors[i % colors.length] + '" stroke="#fff" stroke-width="1"><title>' + entry[0] + ': ' + entry[1] + '</title></path>'; var midAngle = currentAngle + sliceAngle / 2, labelR = (outerR + innerR) / 2; var pct = Math.round((entry[1] / total) * 100); if (pct >= 5) svg += '<text x="' + (cx + labelR * Math.cos(midAngle)) + '" y="' + (cy + labelR * Math.sin(midAngle) - 3) + '" text-anchor="middle" fill="#fff" font-size="9" font-weight="600">' + pct + '%</text>'; currentAngle += sliceAngle; });
        if (innerR > 0) svg += '<circle cx="' + cx + '" cy="' + cy + '" r="' + innerR + '" fill="#fff" />';
        svg += '</svg>';
        var legendHtml = '<div class="flex flex-wrap gap-2 mt-2">';
        entries.forEach(function(entry, i) { legendHtml += '<span class="text-xs text-gray-600"><span class="inline-block w-2 h-2 rounded-full mr-1" style="background:' + colors[i % colors.length] + ';"></span>' + entry[0] + ' (' + entry[1] + ')</span>'; });
        legendHtml += '</div>';
        container.innerHTML = '<div style="text-align:center;">' + svg + '</div>' + legendHtml;
    }

    function bmRenderLineChartInContainer(container, data, title) {
        var entries = Object.entries(data);
        if (entries.length === 0) return;
        var values = entries.map(function(e) { return e[1]; });
        var maxVal = Math.max.apply(null, values), minVal = Math.min.apply(null, values), range = maxVal - minVal || 1;
        var padding = { top: 15, right: 15, bottom: 30, left: 35 }, width = 500, height = 200;
        var chartW = width - padding.left - padding.right, chartH = height - padding.top - padding.bottom;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + width + ' ' + height + '" class="bm-line-chart-svg">';
        for (var g = 0; g <= 3; g++) { var gy = padding.top + (chartH / 3) * g; svg += '<line x1="' + padding.left + '" y1="' + gy + '" x2="' + (width - padding.right) + '" y2="' + gy + '" class="bm-line-grid" />'; }
        var pathD = '', stepX = entries.length > 1 ? chartW / (entries.length - 1) : chartW;
        entries.forEach(function(entry, i) { var px = padding.left + stepX * i, py = padding.top + chartH - ((entry[1] - minVal) / range) * chartH; pathD += (i === 0 ? 'M' : 'L') + px + ' ' + py + ' '; svg += '<circle cx="' + px + '" cy="' + py + '" r="3" class="bm-line-point" />'; var label = entry[0]; if (label.length > 3) label = label.substring(5); svg += '<text x="' + px + '" y="' + (height - 6) + '" text-anchor="middle" class="bm-line-axis-text">' + label + '</text>'; });
        svg += '<path d="' + pathD + '" class="bm-line-path" /></svg>';
        container.innerHTML = svg;
    }

    window.bmRenderPieChart = bmRenderPieChart;
    window.bmRenderLineChart = bmRenderLineChart;
    window.bmRenderTopReaders = bmRenderTopReaders;
    window.bmRenderInactiveAlerts = bmRenderInactiveAlerts;
})();