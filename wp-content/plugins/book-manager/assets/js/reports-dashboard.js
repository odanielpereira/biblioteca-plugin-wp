/**
 * Book Manager — Reports Dashboard
 * Renderização dinâmica + AJAX bridge para relatórios
 */
(function() {
    const bm = window.bmReports || {};

    
    // Guard clause: verifica se o PHP injetou os dados necessários
    if (!bm.ajaxUrl) {
        const msg = document.createElement('div');
        msg.style.cssText = 'background:#dc3545;color:#fff;padding:15px;margin:20px;border-radius:8px;font-family:sans-serif;';
        msg.textContent = 'Erro: Dados de configuração não carregados. Recarregue a página ou contate o administrador.';
        document.addEventListener('DOMContentLoaded', function() {
            const target = document.getElementById('bm-report-result') || document.body;
            target.insertBefore(msg, target.firstChild);
        });
        return; // Aborta a execução do script
    }
    
    // ==========================================
    // UTILITÁRIOS DE BI
    // ==========================================
    
    /**
     * Calcula a variação percentual entre dois valores
     * @param {number} current - Valor atual
     * @param {number} previous - Valor do período anterior
     * @returns {object} { value: número, isPositive: boolean, formatted: string }
     */
    function calculateVariance(current, previous) {
        if (!previous || previous === 0) {
            return { value: 0, isPositive: false, formatted: '' };
        }
        const diff = ((current - previous) / previous) * 100;
        const rounded = Math.round(diff);
        return {
            value: rounded,
            isPositive: rounded >= 0,
            formatted: (rounded >= 0 ? '+' : '') + rounded + '%'
        };
    }
    
    /**
     * Ordena um array de objetos por uma chave e retorna os top N
     * @param {Array} data - Array de objetos
     * @param {string} key - Chave para ordenar (ex: 'books_read')
     * @param {number} limit - Quantidade de itens no topo (padrão 3)
     * @returns {Array} Array ordenado com os top N
     */
    function rankEntities(data, key, limit) {
        limit = limit || 3;
        return [...data].sort(function(a, b) {
            return (b[key] || 0) - (a[key] || 0);
        }).slice(0, limit);
    }
    
    /**
     * Formata um número decimal como porcentagem
     * @param {number} value - Valor decimal (ex: 0.25)
     * @returns {string} String formatada (ex: '25%')
     */
    function formatPercent(value) {
        return Math.round(value * 100) + '%';
    }

        
    // Expor funções de BI ao escopo global para teste no console
    window.calculateVariance = calculateVariance;
    window.rankEntities = rankEntities;
    window.formatPercent = formatPercent;

    
    // ==========================================
    // INICIALIZAÇÃO
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('bm-report-form');
        if (!form) return;
        
        // Intercepta submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            bmFetchReport();
        });
        
        // Listeners de campos dinâmicos
        const periodSelect = document.querySelector('select[name="bm_period"]');
        const subjectSelect = document.querySelector('select[name="bm_subject"]');
        const typeSelect = document.querySelector('select[name="bm_report_type"]');
        
        if (periodSelect) periodSelect.addEventListener('change', bmToggleCustomDates);
        if (subjectSelect) subjectSelect.addEventListener('change', bmToggleSubjectOptions);
        if (typeSelect) typeSelect.addEventListener('change', bmToggleCustomOptions);
        
        // Busca de aluno
        const studentSearch = document.getElementById('bm-student-search-input');
        if (studentSearch) studentSearch.addEventListener('keyup', bmSearchStudent);
        
        // Exportação PDF
        const exportPdfBtn = document.getElementById('bm-export-pdf');
        if (exportPdfBtn) exportPdfBtn.addEventListener('click', bmExportPDF);
    });
    
    // ==========================================
    // CONTROLE DE CAMPOS DINÂMICOS
    // ==========================================
    function bmToggleCustomDates() {
        const div = document.getElementById('bm-custom-dates');
        if (div) {
            div.classList.toggle('hidden', this.value !== 'custom');
            div.classList.toggle('flex', this.value === 'custom');
        }
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
    
    // ==========================================
    // BUSCA DE ALUNO
    // ==========================================
    function bmSearchStudent() {
        const query = this.value.trim();
        if (query.length < 2) return;
        
        const params = new URLSearchParams();
        params.append('action', 'bm_service_search_student');
        params.append('query', query);
        params.append('nonce', bm.serviceNonce);
        
        fetch(bm.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(r => r.json())
        .then(r => {
            const results = document.getElementById('bm-student-search-results');
            if (!results) return;
            
            let html = '';
            if (r.found) {
                document.getElementById('bm-subject-id').value = r.student.id;
                html = '<div class="bm-student-result-item" data-student-id="' + r.student.id + '" style="padding:6px;background:#e8f5e9;border-radius:4px;cursor:pointer;margin:2px 0;">' + r.student.name + ' (' + r.student.email + ')</div>';
            } else if (r.multiple) {
                r.students.forEach(function(s) {
                    html += '<div class="bm-student-result-item" data-student-id="' + s.id + '" style="padding:6px;background:#f5f5f5;border-radius:4px;cursor:pointer;margin:2px 0;">' + s.name + ' (' + s.email + ')</div>';
                });
            } else {
                html = '<p style="color:#999;font-size:12px;">Nenhum aluno encontrado.</p>';
            }
            results.innerHTML = html;
            
            document.querySelectorAll('.bm-student-result-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    document.getElementById('bm-subject-id').value = this.getAttribute('data-student-id');
                    results.innerHTML = '<strong>' + this.getAttribute('data-student-name') + '</strong> selecionado';
                });
            });
        });
    }
    
    // ==========================================
    // CHAMADA AJAX PRINCIPAL
    // ==========================================
    function bmFetchReport() {
        const form = document.getElementById('bm-report-form');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.append('action', 'bm_get_report_data');
        params.append('nonce', bm.nonce);
        
        bmShowState('loading');
        
        fetch(bm.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(r => r.json())
        .then(response => {
            document.getElementById('bm-loading').classList.add('hidden');
            if (response.success && response.data) {
                bmRenderReport(response.data);
            } else {
                bmShowState('empty');
            }
        })
        .catch(() => {
            document.getElementById('bm-loading').classList.add('hidden');
            bmShowState('empty');
        });
    }
    
    // ==========================================
    // CONTROLE DE ESTADOS
    // ==========================================
    function bmShowState(state) {
        const welcome = document.getElementById('bm-welcome');
        const loading = document.getElementById('bm-loading');
        const empty = document.getElementById('bm-empty');
        const title = document.querySelector('[data-section="report-title"]');
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        const chart = document.querySelector('[data-section="bar-chart"]');
        const table = document.querySelector('[data-section="data-table"]');
        
        if (welcome) welcome.classList.add('hidden');
        if (loading) loading.classList.add('hidden');
        if (empty) empty.classList.add('hidden');
        if (title) title.classList.add('hidden');
        if (kpi) kpi.classList.add('hidden');
        if (chart) chart.classList.add('hidden');
        if (table) table.classList.add('hidden');
        
        const pieChart = document.querySelector('[data-section="pie-chart"]');
        const lineChart = document.querySelector('[data-section="line-chart"]');
        const topReaders = document.querySelector('[data-section="top-readers"]');
        const inactiveAlerts = document.querySelector('[data-section="inactive-alerts"]');
        if (pieChart) pieChart.classList.add('hidden');
        if (lineChart) lineChart.classList.add('hidden');
        if (topReaders) topReaders.classList.add('hidden');
        if (inactiveAlerts) inactiveAlerts.classList.add('hidden');
        
        switch (state) {
            case 'loading':
                if (loading) loading.classList.remove('hidden');
                break;
            case 'empty':
                if (empty) empty.classList.remove('hidden');
                break;
            case 'welcome':
                if (welcome) welcome.classList.remove('hidden');
                break;
        }
    }
    
    // ==========================================
    // ROTEADOR DE RENDERIZAÇÃO
    // ==========================================
    function bmRenderReport(data) {
        const meta = data._meta || {};
        const type = meta.type || 'overview';
        
        // Título
        const titleSection = document.querySelector('[data-section="report-title"]');
        if (titleSection && data.title) {
            titleSection.classList.remove('hidden');
            const h2 = titleSection.querySelector('h2');
            const p = titleSection.querySelector('p');
            if (h2) h2.textContent = data.title;
            if (p && data.period_start) {
                p.textContent = data.period_start + ' — ' + data.period_end;
            }
        }
        
        // Roteia conforme o tipo
        switch (type) {
            case 'overview':
                bmRenderOverview(data);
                break;
            case 'student_performance':
                bmRenderStudentPerformance(data);
                break;
            case 'class_reading':
                bmRenderClassReading(data);
                break;
            case 'active_penalties':
                bmRenderPenalties(data);
                break;
            case 'genre_ranking':
                bmRenderGenreRanking(data);
                break;
            case 'top_books':
                bmRenderTopBooks(data);
                break;
            case 'reading_trend':
                bmRenderReadingTrend(data);
                break;
            case 'custom':
                bmRenderCustom(data);
                break;
        }
    }
    
    // ==========================================
    // RENDERIZADORES POR TIPO
    // ==========================================
    
    function bmRenderOverview(data) {
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        if (!kpi) return;
        kpi.classList.remove('hidden');
        
        const cards = kpi.querySelectorAll('.bg-white');
        if (cards.length >= 4) {
            var varianceLoans = data.total_loans_prev ? calculateVariance(data.total_loans, data.total_loans_prev) : null;
            var varianceReturns = data.total_returns_prev ? calculateVariance(data.total_returns, data.total_returns_prev) : null;
            
            bmFillKPICard(cards[0], 'EMPRÉSTIMOS', data.total_loans, varianceLoans);
            bmFillKPICard(cards[1], 'DEVOLUÇÕES', data.total_returns, varianceReturns);
            bmFillKPICard(cards[2], 'EM ATRASO', data.total_overdue, null);
            bmFillKPICard(cards[3], 'RESERVAS PENDENTES', data.total_reservations, null);
        }
        
        bmHideChart();
        bmHideTable();
        
        // Alertas de inativos
        if (data.inactive_students && data.inactive_students.length > 0) {
            bmRenderInactiveAlerts(data.inactive_students);
        }
    }
    
    function bmRenderStudentPerformance(data) {
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        if (!kpi) return;
        kpi.classList.remove('hidden');
        
        const cards = kpi.querySelectorAll('.bg-white');
        
        // Visão geral (todos os alunos) — dados agregados
        if (data.students && data.total_students) {
            if (cards.length >= 4) {
                bmFillKPICard(cards[0], 'ALUNOS', data.total_students, null);
                bmFillKPICard(cards[1], 'LIVROS LIDOS', data.total_books, null);
                bmFillKPICard(cards[2], 'RESENHAS', data.total_reviews, null);
                bmFillKPICard(cards[3], 'VÍDEOS', data.total_videos, null);
            }
            
            bmHideChart();
            bmHideTable();
            
            // Ranking Top 3
            if (data.students.length >= 3) {
                bmRenderTopReaders(data.students);
            }
            
            // Alertas de inativos
            if (data.inactive_students && data.inactive_students.length > 0) {
                bmRenderInactiveAlerts(data.inactive_students);
            }
            return;
        }
        
        // Aluno individual
        if (cards.length >= 4) {
            bmFillKPICard(cards[0], 'LIVROS LIDOS', data.books_read, null);
            bmFillKPICard(cards[1], 'EMPRÉSTIMOS ATIVOS', data.active_loans, null);
            bmFillKPICard(cards[2], 'RESENHAS', data.reviews, null);
            bmFillKPICard(cards[3], 'XP', data.xp, null);
        }
        
        bmHideChart();
        
        if (data.books_read_list && data.books_read_list.length > 0) {
            bmRenderTable(['Livro', 'Autor', 'Devolvido em'], data.books_read_list.map(function(b) {
                return [b.title, b.author || '—', b.returned_date || '—'];
            }));
        } else {
            bmHideTable();
        }
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
            bmRenderTable(
                ['Aluno', 'Livros Lidos', 'Status'],
                data.students.map(function(s) {
                    return [s.name, s.books_read, s.has_overdue ? 'Atrasado' : 'Em dia'];
                })
            );
            
            // Ranking Top 3
            if (data.students.length >= 3) {
                bmRenderTopReaders(data.students);
            }
        } else {
            bmHideTable();
        }
        
        // Alertas de inativos
        if (data.never_read && data.never_read.length > 0) {
            bmRenderInactiveAlerts(data.never_read);
        }
    }
    
    function bmRenderPenalties(data) {
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        if (!kpi) return;
        kpi.classList.remove('hidden');
        
        const cards = kpi.querySelectorAll('.bg-white');
        if (cards.length >= 1) {
            bmFillKPICard(cards[0], 'TOTAL DE MULTAS ATIVAS', data.total, '');
        }
        // Esconde cards não usados
        for (let i = 1; i < cards.length; i++) {
            cards[i].classList.add('hidden');
        }
        
        bmHideChart();
        
        if (data.penalties && data.penalties.length > 0) {
            bmRenderTable(
                ['Aluno', 'Tipo', 'Descrição', 'Data', 'Até'],
                data.penalties.map(function(p) {
                    const typeLabel = p.type === 'warning' ? 'Advertência' : (p.type === 'suspension' ? 'Suspensão' : 'Multa');
                    return [p.student_name, typeLabel, p.note || '—', p.date || '—', p.until || '—'];
                })
            );
        } else {
            bmHideTable();
        }
    }
    
    function bmRenderGenreRanking(data) {
        bmHideKPI();
        
        if (data.genres && Object.keys(data.genres).length > 0) {
            bmRenderPieChart(data.genres, 'Distribuição de Gêneros');
        }
        
        bmHideTable();
    }
    
    function bmRenderTopBooks(data) {
        bmHideKPI();
        bmHideChart();
        
        if (data.books && data.books.length > 0) {
            bmRenderTable(
                ['#', 'Livro', 'Autor', 'Empréstimos'],
                data.books.map(function(b, i) {
                    return [i + 1, b.title, b.author || '—', b.loans];
                })
            );
        }
    }
    
    function bmRenderReadingTrend(data) {
        bmHideKPI();
        
        if (data.months && Object.keys(data.months).length > 0) {
            bmRenderLineChart(data.months, 'Tendência de Leitura');
        }
        
        bmHideTable();
    }
    
    function bmRenderCustom(data) {
        bmHideKPI();
        bmHideChart();
        
        if (data.columns && data.rows && data.rows.length > 0) {
            var labels = {
                'name': 'Nome',
                'group': 'Turma',
                'books_read': 'Livros Lidos',
                'reviews': 'Resenhas',
                'videos': 'Vídeos',
                'xp': 'XP',
                'badges': 'Medalhas',
                'penalties': 'Multas'
            };
            var headers = data.columns.map(function(col) {
                return labels[col] || col;
            });
            
            bmRenderTable(headers, data.rows.map(function(row) {
                return data.columns.map(function(col) {
                    return row[col] !== undefined ? row[col] : '—';
                });
            }));
        } else {
            bmHideTable();
        }
    }
    
    // ==========================================
    // PREENCHEDORES DE COMPONENTES
    // ==========================================
    
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
            } else {
                varianceEl.textContent = '';
            }
        }
        if (periodEl) {
            periodEl.textContent = variance && variance.formatted ? 'vs período anterior' : '';
        }
        
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
            html += '<div class="flex items-center gap-3 bm-bar-row">';
            html += '<div class="w-28 text-xs text-gray-600 text-right truncate" title="' + label + '">' + label + '</div>';
            html += '<div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden bm-bar-bg">';
            html += '<div class="bg-blue-500 h-full rounded-full flex items-center bm-bar-fill animate-slide-in" style="width:0%;" data-width="' + pct + '%">';
            html += '<span class="text-xs text-white font-bold ml-2">' + value + '</span>';
            html += '</div></div></div>';
        });
        
        container.innerHTML = html;
        
        // Animar barras após inserir no DOM
        requestAnimationFrame(function() {
            container.querySelectorAll('.bm-bar-fill').forEach(function(bar) {
                bar.style.width = bar.getAttribute('data-width');
            });
        });
        
        // Tooltip nas barras
        container.querySelectorAll('.bm-bar-row').forEach(function(row) {
            row.addEventListener('mouseenter', function() {
                this.querySelector('.bm-bar-bg').style.transform = 'scale(1.02)';
                this.querySelector('.bm-bar-bg').style.transition = 'transform 0.15s ease';
            });
            row.addEventListener('mouseleave', function() {
                this.querySelector('.bm-bar-bg').style.transform = 'scale(1)';
            });
        });
    }
    
    function bmRenderTable(headers, rows) {
        const tableSection = document.querySelector('[data-section="data-table"]');
        if (!tableSection) return;
        tableSection.classList.remove('hidden');
        
        const thead = tableSection.querySelector('thead tr');
        const tbody = tableSection.querySelector('tbody');
        if (!thead || !tbody) return;
        
        thead.innerHTML = headers.map(function(h) {
            return '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">' + h + '</th>';
        }).join('');
        
        tbody.innerHTML = rows.map(function(row, i) {
            const rowClass = i % 2 === 0 ? '' : 'bg-gray-50';
            return '<tr class="hover:bg-gray-50 transition-colors ' + rowClass + '">' +
                row.map(function(cell) {
                    return '<td class="px-4 py-3 text-gray-900">' + cell + '</td>';
                }).join('') +
                '</tr>';
        }).join('');
    }
    
    // ==========================================
    // GRÁFICOS SVG
    // ==========================================
    
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
        if (entries.length === 0) {
            section.classList.add('hidden');
            return;
        }
        
        const total = entries.reduce(function(sum, e) { return sum + e[1]; }, 0);
        const colors = ['#3b82f6','#ef4444','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#f97316'];
        const size = 192;
        const cx = size / 2;
        const cy = size / 2;
        const outerR = 80;
        const innerR = 50;
        let currentAngle = -Math.PI / 2;
        
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '">';
        
        entries.forEach(function(entry, i) {
            var sliceAngle = (entry[1] / total) * 2 * Math.PI;
            var x1 = cx + outerR * Math.cos(currentAngle);
            var y1 = cy + outerR * Math.sin(currentAngle);
            var x2 = cx + outerR * Math.cos(currentAngle + sliceAngle);
            var y2 = cy + outerR * Math.sin(currentAngle + sliceAngle);
            var largeArc = sliceAngle > Math.PI ? 1 : 0;
            
            var path = 'M' + cx + ',' + cy + ' ';
            path += 'L' + x1 + ',' + y1 + ' ';
            path += 'A' + outerR + ',' + outerR + ' 0 ' + largeArc + ' 1 ' + x2 + ',' + y2 + ' ';
            path += 'Z';
            
            svg += '<path d="' + path + '" fill="' + colors[i % colors.length] + '" class="bm-donut-slice" stroke="#fff" stroke-width="1" />';
            
            var midAngle = currentAngle + sliceAngle / 2;
            var labelR = (outerR + innerR) / 2;
            var lx = cx + labelR * Math.cos(midAngle);
            var ly = cy + labelR * Math.sin(midAngle);
            var pct = Math.round((entry[1] / total) * 100);
            
            if (pct >= 5) {
                svg += '<text x="' + lx + '" y="' + (ly - 4) + '" text-anchor="middle" fill="#fff" font-size="10" font-weight="600">' + pct + '%</text>';
            }
            
            currentAngle += sliceAngle;
        });
        
        if (innerR > 0) {
            svg += '<circle cx="' + cx + '" cy="' + cy + '" r="' + innerR + '" fill="#fff" />';
        }
        
        svg += '</svg>';
        container.innerHTML = svg;
        
        var legendHtml = '';
        entries.forEach(function(entry, i) {
            var pct = Math.round((entry[1] / total) * 100);
            legendHtml += '<div class="bm-legend-item">';
            legendHtml += '<span class="bm-legend-dot" style="background:' + colors[i % colors.length] + ';"></span>';
            legendHtml += '<span>' + entry[0] + '</span>';
            legendHtml += '<span class="bm-legend-value">' + entry[1] + ' (' + pct + '%)</span>';
            legendHtml += '</div>';
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
        if (entries.length === 0) {
            section.classList.add('hidden');
            return;
        }
        
        const values = entries.map(function(e) { return e[1]; });
        const maxVal = Math.max.apply(null, values);
        const minVal = Math.min.apply(null, values);
        const range = maxVal - minVal || 1;
        
        const padding = { top: 20, right: 20, bottom: 40, left: 40 };
        const width = 600;
        const height = 256;
        const chartW = width - padding.left - padding.right;
        const chartH = height - padding.top - padding.bottom;
        
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + width + ' ' + height + '" class="bm-line-chart-svg">';
        
        // Grade horizontal
        for (var g = 0; g <= 4; g++) {
            var gy = padding.top + (chartH / 4) * g;
            svg += '<line x1="' + padding.left + '" y1="' + gy + '" x2="' + (width - padding.right) + '" y2="' + gy + '" class="bm-line-grid" />';
            var gval = Math.round(maxVal - (range / 4) * g);
            svg += '<text x="' + (padding.left - 6) + '" y="' + (gy + 4) + '" text-anchor="end" class="bm-line-axis-text">' + gval + '</text>';
        }
        
        // Pontos e linha
        var points = '';
        var pathD = '';
        var stepX = entries.length > 1 ? chartW / (entries.length - 1) : chartW;
        
        entries.forEach(function(entry, i) {
            var px = padding.left + stepX * i;
            var py = padding.top + chartH - ((entry[1] - minVal) / range) * chartH;
            points += '<circle cx="' + px + '" cy="' + py + '" r="3" class="bm-line-point" data-value="' + entry[1] + '" data-label="' + entry[0] + '" />';
            pathD += (i === 0 ? 'M' : 'L') + px + ' ' + py + ' ';
            
            // Rótulo do eixo X
            var label = entry[0];
            if (label.length > 3) label = label.substring(5);
            svg += '<text x="' + px + '" y="' + (height - 8) + '" text-anchor="middle" class="bm-line-axis-text">' + label + '</text>';
        });
        
        svg += '<path d="' + pathD + '" class="bm-line-path" />';
        svg += points;
        svg += '</svg>';
        
        container.innerHTML = svg;
        
        // Tooltip
        var tooltip = document.createElement('div');
        tooltip.className = 'bm-tooltip hidden';
        container.appendChild(tooltip);
        
        container.querySelectorAll('.bm-line-point').forEach(function(point) {
            point.addEventListener('mouseenter', function() {
                tooltip.textContent = this.getAttribute('data-label') + ': ' + this.getAttribute('data-value');
                tooltip.classList.remove('hidden');
            });
            point.addEventListener('mousemove', function(e) {
                var rect = container.getBoundingClientRect();
                tooltip.style.left = (e.clientX - rect.left) + 'px';
                tooltip.style.top = (e.clientY - rect.top) + 'px';
            });
            point.addEventListener('mouseleave', function() {
                tooltip.classList.add('hidden');
            });
        });
    }
    
    function bmRenderTopReaders(data) {
        const section = document.querySelector('[data-section="top-readers"]');
        if (!section) return;
        
        const top3 = rankEntities(data, 'books_read', 3);
        if (top3.length === 0) {
            section.classList.add('hidden');
            return;
        }
        
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
                if (bar) {
                    const pct = Math.round((student.books_read / maxBooks) * 100);
                    bar.style.width = pct + '%';
                }
            } else {
                card.classList.add('hidden');
            }
        });
    }
    
    function bmRenderInactiveAlerts(data) {
        const section = document.querySelector('[data-section="inactive-alerts"]');
        if (!section) return;
        
        if (!data || data.length === 0) {
            section.classList.add('hidden');
            return;
        }
        
        section.classList.remove('hidden');
        
        const list = document.getElementById('bm-inactive-list');
        if (!list) return;
        
        var html = '';
        data.forEach(function(name) {
            html += '<span class="bg-white text-red-700 text-xs font-medium px-3 py-1 rounded-full border border-red-300">' + name + '</span>';
        });
        list.innerHTML = html;
    }
    
    // ==========================================
    // CONTROLE DE VISIBILIDADE
    // ==========================================
    function bmHideKPI() {
        const kpi = document.querySelector('[data-section="kpi-cards"]');
        if (kpi) kpi.classList.add('hidden');
    }
    
    function bmHideChart() {
        const chart = document.querySelector('[data-section="bar-chart"]');
        if (chart) chart.classList.add('hidden');
    }
    
    function bmHideTable() {
        const table = document.querySelector('[data-section="data-table"]');
        if (table) table.classList.add('hidden');
    }
    
    // ==========================================
    // EXPORTAÇÃO PDF
    // ==========================================
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
    
    // Expor funções de gráficos ao escopo global para teste no console
    window.bmRenderPieChart = bmRenderPieChart;
    window.bmRenderLineChart = bmRenderLineChart;
    window.bmRenderTopReaders = bmRenderTopReaders;
    window.bmRenderInactiveAlerts = bmRenderInactiveAlerts;
})();