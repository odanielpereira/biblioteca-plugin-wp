# Dicionário de Componentes — Dashboard de Relatórios

Referência técnica de todos os seletores, classes e funções utilizados na interface.

---

## 1. Slots HTML (`data-section`)

| Atributo | Componente | Renderizador JS |
|----------|------------|-----------------|
| `data-section="report-title"` | Título e período do relatório | `bmRenderReport()` |
| `data-section="kpi-cards"` | 4 cartões KPI | `bmRenderOverview()`, `bmRenderStudentPerformance()`, `bmRenderClassReading()`, `bmRenderPenalties()` |
| `data-section="bar-chart"` | Gráfico de barras horizontais | `bmRenderBarChart()` |
| `data-section="pie-chart"` | Gráfico de pizza/donut SVG | `bmRenderPieChart()` |
| `data-section="line-chart"` | Gráfico de linha SVG | `bmRenderLineChart()` |
| `data-section="top-readers"` | Ranking Top 3 com medalhas | `bmRenderTopReaders()` |
| `data-section="inactive-alerts"` | Lista de alunos inativos | `bmRenderInactiveAlerts()` |
| `data-section="data-table"` | Tabela de dados | `bmRenderTable()` |

### Atributos auxiliares

| Atributo | Local | Função |
|----------|-------|--------|
| `data-component="bm-chart"` | Container do gráfico de barras | Identificação para scripts |
| `id="bm-chart-container"` | Div interna do gráfico de barras | Onde o HTML das barras é injetado |
| `id="bm-report-form"` | Formulário principal | Interceptado por `bmFetchReport()` |
| `id="bm-report-result"` | Container de resultados | Onde todos os componentes são exibidos |
| `id="bm-welcome"` | Estado inicial | Mensagem "Selecione os filtros..." |
| `id="bm-loading"` | Estado de carregamento | Animação de pulso |
| `id="bm-empty"` | Estado vazio | Ícone de caixa + mensagem |
| `id="bm-student-search-input"` | Campo de busca de aluno | Escutado por `bmSearchStudent()` |
| `id="bm-student-search-results"` | Container de resultados da busca | Onde os nomes aparecem |
| `id="bm-subject-id"` | Campo oculto | ID do aluno selecionado |
| `id="bm-export-pdf"` | Botão de exportação | Escutado por `bmExportPDF()` |
| `id="bm-custom-dates"` | Container de datas personalizadas | Mostrado/escondido conforme período |
| `id="bm-subject-options"` | Container de seleção de sujeito | Contém aluno e turma |
| `id="bm-custom-options"` | Container de colunas configuráveis | Mostrado só no tipo `custom` |

---

## 2. Estados Visuais

| ID | Estado | Quando aparece |
|----|--------|----------------|
| `bm-welcome` | Inicial | Página carregada, nenhum relatório gerado |
| `bm-loading` | Carregando | Durante a requisição AJAX |
| `bm-empty` | Vazio | Relatório retornou sem dados |
| `data-section` (todos) | Dados | Relatório retornou com dados |

---

## 3. Classes CSS Customizadas

### 3.1 Classes de componente

| Classe | Aplica a | Função |
|--------|----------|--------|
| `.bm-student-result-item` | Div de resultado de busca | Item clicável na busca de aluno |

### 3.2 Classes de indicadores (a criar na Fase 2.2)

| Classe | Valor CSS | Uso |
|--------|-----------|-----|
| `.text-positive` | `color: #16a34a` | Variação positiva nos KPIs |
| `.text-negative` | `color: #dc2626` | Variação negativa nos KPIs |
| `.badge-gold` | `border-color: #f59e0b; background: #fffbeb` | Card do 1º lugar |
| `.badge-silver` | `border-color: #9ca3af; background: #f9fafb` | Card do 2º lugar |
| `.badge-bronze` | `border-color: #d97706; background: #fff7ed` | Card do 3º lugar |
| `.tooltip` | `position: absolute; background: #1f2937; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px` | Tooltip em gráficos |
| `.animate-slide-in` | `animation: bm-slide-in 0.3s ease` | Transição de cards |

### 3.3 Classes Tailwind já existentes

Ver `assets/css/tailwind-custom.css` para a lista completa. As principais:
- Grid: `grid`, `grid-cols-1`, `sm:grid-cols-2`, `lg:grid-cols-4`
- Cores de borda: `border-blue-500`, `border-emerald-500`, `border-red-500`, `border-amber-500`
- Backgrounds: `bg-blue-50`, `bg-emerald-50`, `bg-red-50`, `bg-amber-50`
- Texto: `text-2xl`, `font-bold`, `text-gray-500`, `uppercase`, `tracking-wider`
- Hover: `hover:shadow-md`, `hover:bg-gray-50`

---

## 4. Funções JavaScript

### 4.1 Controle de estados

| Função | Parâmetros | Ação |
|--------|------------|------|
| `bmShowState(state)` | `'welcome'` / `'loading'` / `'empty'` | Esconde todos os estados, mostra o selecionado |
| `bmHideKPI()` | nenhum | Esconde `[data-section="kpi-cards"]` |
| `bmHideChart()` | nenhum | Esconde `[data-section="bar-chart"]` |
| `bmHideTable()` | nenhum | Esconde `[data-section="data-table"]` |

### 4.2 Comunicação com o servidor

| Função | Método | Endpoint | Nonce usado |
|--------|--------|----------|-------------|
| `bmFetchReport()` | POST | `bm_get_report_data` | `bm.nonce` (`bm_reports_nonce`) |
| `bmSearchStudent()` | POST | `bm_service_search_student` | `bm.serviceNonce` (`bm_service_nonce`) |
| `bmExportPDF()` | GET (nova aba) | `bm_export_report_pdf` | Nenhum (não é AJAX) |

### 4.3 Renderizadores principais

| Função | Entrada | Componentes que aciona |
|--------|---------|------------------------|
| `bmRenderReport(data)` | Objeto JSON completo | Roteia por `_meta.type` |
| `bmRenderOverview(data)` | Dados de `overview` | KPIs + Alertas de Inativos |
| `bmRenderStudentPerformance(data)` | Dados de `student_performance` | KPIs + Tabela + Ranking (se todos) |
| `bmRenderClassReading(data)` | Dados de `class_reading` | KPIs + Tabela + Ranking |
| `bmRenderPenalties(data)` | Dados de `active_penalties` | KPI + Tabela |
| `bmRenderGenreRanking(data)` | Dados de `genre_ranking` | Pizza/Donut |
| `bmRenderTopBooks(data)` | Dados de `top_books` | Tabela ranqueada |
| `bmRenderReadingTrend(data)` | Dados de `reading_trend` | Linha |
| `bmRenderCustom(data)` | Dados de `custom` | Tabela dinâmica |

### 4.4 Preenchedores de componentes

| Função | Parâmetros |
|--------|------------|
| `bmFillKPICad(card, label, value, variance)` | Elemento DOM do card, título, número, objeto de variação |
| `bmRenderBarChart(data, title)` | Objeto `{ "label": valor }`, título |
| `bmRenderPieChart(data, title)` | Objeto `{ "categoria": valor }`, título |
| `bmRenderLineChart(data, title)` | Objeto `{ "mês": valor }`, título |
| `bmRenderTopReaders(data)` | Array de `{ name, books_read }` (ordenado) |
| `bmRenderInactiveAlerts(data)` | Array de strings (nomes) |
| `bmRenderTable(headers, rows)` | Array de strings, array de arrays |

### 4.5 Utilitários de BI

| Função | Entrada | Saída |
|--------|---------|-------|
| `calculateVariance(current, previous)` | `100, 80` | `{ value: 25, isPositive: true, formatted: '+25%' }` |
| `rankEntities(array, key, limit)` | `[{...}], 'books_read', 3` | Array com 3 objetos |
| `formatPercent(value)` | `0.25` | `'25%'` |

### 4.6 Controle de campos dinâmicos

| Função | Gatilho | Ação |
|--------|---------|------|
| `bmToggleCustomDates()` | `change` no select de período | Mostra/esconde datas customizadas |
| `bmToggleSubjectOptions()` | `change` no select de sujeito | Mostra/esconde campo de aluno ou turma |
| `bmToggleCustomOptions()` | `change` no select de tipo | Mostra/esconde colunas configuráveis |

---

## 5. Objeto `bmReports` (injetado via PHP)

```javascript
window.bmReports = {
    ajaxUrl: 'https://escola.local/wp-admin/admin-ajax.php',
    nonce: 'abc123...',          // bm_reports_nonce
    serviceNonce: 'def456...'    // bm_service_nonce
};
```

**Local de injeção:** `includes/admin-service.php`, função `bm_render_reports_page()`, via `wp_localize_script('bm-reports-dashboard', 'bmReports', array(...))`.

---

## 6. Arquivos da Stack

| Arquivo | Tipo | Função |
|---------|------|--------|
| `includes/admin-service.php` | PHP | Renderiza a página de relatórios, injeta HTML + enqueues |
| `includes/reports.php` | PHP | Motor de relatórios + endpoint JSON `bm_get_report_data` |
| `assets/js/reports-dashboard.js` | JavaScript | Renderização dinâmica + AJAX bridge |
| `assets/css/tailwind-custom.css` | CSS | Classes Tailwind + estilos customizados |
