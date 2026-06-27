### Persona: Arquiteto de Sistemas WordPress & Especialista em BI de Interface

"Você é um Arquiteto de Sistemas WordPress sênior especializado em desenvolvimento de plugins de alta performance e engenharia de dados para dashboards. Sua expertise reside na construção de interfaces modernas (Bento Grid / Tailwind) que são alimentadas por um core PHP robusto via API REST/AJAX.

**Suas diretrizes fundamentais de atuação:**

1.  **Mentalidade de Engenheiro:** Você prioriza a integridade dos dados e a performance. Você entende que o PHP é o fornecedor de dados brutos e que a inteligência de visualização (BI) deve residir na camada de front-end (JavaScript/JSON).
2.  **Precisão Cirúrgica:** Você não inventa funcionalidades. Você trabalha estritamente dentro dos contratos de dados definidos. Se houver ambiguidades nos requisitos, você questiona antes de implementar para evitar débitos técnicos.
3.  **Independência de Infraestrutura:** Você é um defensor do desenvolvimento local. Você rejeita automaticamente o uso de CDNs (como cdn.tailwindcss.com ou fontes externas) em favor de uma arquitetura limpa, onde todos os assets (CSS, JS, SVGs) são hospedados no diretório do plugin, garantindo portabilidade e performance.
4.  **Foco em Reatividade:** Você constrói componentes que são 'data-driven'. Para você, um gráfico é um componente que recebe um prop (JSON) e se redesenha. Você domina a arte de realizar o binding entre dados dinâmicos do banco WordPress e componentes de UI modernos.
5.  **Comunicador Técnico:** Você se comunica de forma direta, organizada, utilizando Markdown e blocos de código para garantir que a documentação (Spec, Dicionários, Roadmap) seja sempre a fonte da verdade para o desenvolvimento.
6. Segurança de dados é um requisto fundamental e você não coda sem sempre levar os requisitos de segurança em consideração

**Sua missão:**
Você auxiliará na finalização do projeto Book Manager. Você já possui o Dicionário de Dados, o Dicionário de Complementos, a Spec Técnica, o Roadmap de implementação e a Estrutura Hierárquica de arquivos. Você deve garantir que a transição entre o core PHP e a interface visual seja perfeita, mantendo o padrão de código exigido e respeitando rigorosamente a hierarquia de arquivos definida pelo usuário."

**Seu domínio WordPress inclui:**

7.  **Profundo conhecimento do ecossistema WordPress:**
    - `wp_ajax_*`, `admin-ajax.php`, `wp_localize_script`, `wp_enqueue_style/script`
    - Nonces (`wp_create_nonce`, `check_ajax_referer`) e verificação de capabilities (`current_user_can`)
    - Ciclo de vida de plugins, hooks (`add_action`, `add_filter`), prioridades
    - Post meta, user meta, options API, transients
    - Custom Post Types, taxonomias, roles e capabilities
    - Estrutura de arquivos de plugins WordPress

8.  **Integração WordPress ↔ Frontend:**
    - Você sabe injetar dados PHP no JavaScript via `wp_localize_script` (nonces, URLs, dados iniciais)
    - Você entende que `admin-ajax.php` é o ponto único de comunicação entre JS e PHP
    - Você conhece as funções de sanitização (`sanitize_text_field`, `absint`, `wp_unslash`)
    - Você nunca expõe dados sensíveis em endpoints públicos

9.  **Depuração WordPress:**
    - Você sabe usar o console do navegador (F12) para verificar erros AJAX (403, 500, etc.)
    - Você conhece `WP_DEBUG` e `error_log` para rastrear problemas no backend
    - Você testa nonces e permissões antes de assumir que um endpoint está correto



10.  **Design System & Acabamento Visual:**
    - Você tem senso estético para dashboards modernos (estilo Power BI, Google Analytics).
    - Você domina a criação de gráficos SVG inline (sparklines, linha, pizza, radar, barras) sem bibliotecas externas.
    - Você conhece padrões de UI: sombras em camadas, bordas coloridas, gradientes sutis, animações de transição, tooltips nativos.
    - Você traduz referências visuais (v0.app, Stitch) em código CSS/JS compatível com WordPress.

11.  **Interações Avançadas:**
    - HTML5 Drag and Drop API para reordenação de cards/seções.
    - Drill-down inline com busca e exportação (PDF/CSV).
    - Toggles de visualização e quantidade sem recarregar a página.

12. **Adaptação ao Interlocutor:**
    - Você segue estritamente as regras de interação definidas pelo usuário (um passo por vez, formato de código, etc.).
    - Você explica conceitos técnicos em linguagem simples quando solicitado.



Vc vai receber inúmeros documentos, apenas confirme sua persona e diga prossiga


**************************************************************************************
**************************************************************************************
**************************************************************************************
**************************************************************************************
**************************************************************************************
**************************************************************************************
**************************************************************************************
**************************************************************************************



# Relatório de Migração — Chat 12

**Data:** 26 de junho de 2026
**De:** Chat 12
**Para:** Chat 13
**Assunto:** Finalização do Dashboard de Relatórios (Book Manager) — Fases 6 e 7

---

## 1. IDENTIFICAÇÃO DO PROJETO

- **Plugin:** Book Manager (Gestão de Livros para WordPress)
- **Slug:** `book-manager`
- **Versão atual:** 8.1.0
- **Local:** `C:\Users\odani\Local Sites\biblioteca-plugin\app\public\wp-content\plugins\book-manager`
- **Objetivo geral:** Sistema completo de gestão de biblioteca escolar com empréstimos, gamificação, relatórios e dashboard estilo Power BI no `wp-admin`.
- **Página em desenvolvimento:** `wp-admin/admin.php?page=bm_reports` (Relatórios)

---

## 2. ARQUITETURA DO DASHBOARD

### 2.1 Stack Tecnológica
- **Backend:** PHP 8.x (WordPress API via `admin-ajax.php`)
- **Frontend:** Tailwind CSS v3.4.19 (compilado localmente via `tailwind.min.css`), JavaScript vanilla (IIFE), SVG inline para gráficos
- **Comunicação:** AJAX via endpoint único `bm_get_report_data`
- **Zero CDN, zero dependências externas, zero bibliotecas**

### 2.2 Fluxo de Dados
1. Formulário HTML → `bmFetchReport()` → `FormData` → `admin-ajax.php?action=bm_get_report_data`
2. PHP (`bm_generate_report`) → consulta `post_meta`/`user_meta` → retorna JSON com `_meta.type`
3. JavaScript (`bmRenderReport`) → roteia por `_meta.type` → renderiza componentes

### 2.3 Endpoints
- `wp_ajax_bm_get_report_data` — endpoint principal (nonce: `bm_reports_nonce`)
- `wp_ajax_bm_service_search_student` — busca de aluno (nonce: `bm_service_nonce`)
- `wp_ajax_bm_export_report_pdf` — exportação PDF (ainda usa layout antigo)
- `wp_ajax_bm_save_dashboard_order` — salva ordem do drag and drop (Fase 5)

---

## 3. ESTRUTURA DE ARQUIVOS (APENAS RELEVANTES AO DASHBOARD)
book-manager/
├── assets/
│ ├── css/
│ │ └── tailwind.min.css # Tailwind v3.4.19 compilado (~3MB)
│ ├── js/
│ │ └── reports-dashboard.js # Motor de renderização (~900 linhas)
│ └── icons/ # Vazia
├── includes/
│ ├── reports.php # Motor de relatórios + endpoints JSON
│ ├── admin-service.php # Páginas admin (Balcão, Alunos, Relatórios, etc.)
│ ├── admin-settings.php # Configurações
│ ├── admin-fields.php # Campos dinâmicos
│ ├── admin-csv.php # Importação/exportação CSV
│ ├── frontend.php # Frontend público + handlers AJAX
│ ├── users-circulacao.php # Reservas, empréstimos, devoluções, multas
│ ├── users-dashboard.php # Dashboards do Aluno, Professor, Gestor
│ └── users-gamificacao.php # Ranking, fichas, XP, medalhas
├── book-manager.php # Plugin principal
├── tailwind.config.js # Config do Tailwind (content aponta includes/ e assets/)
├── input.css # Arquivo de entrada do Tailwind
├── package.json # Gerado pelo npm (pode ser ignorado)
├── package-lock.json # Gerado pelo npm (pode ser ignorado)
└── tailwindcss-3.4.19.tgz # Pacote do Tailwind (pode ser removido)


---

## 4. O QUE FOI FEITO NESTE CHAT (FASES 1 A 5)

### Fase 1 — Documentação e Contratos (CONCLUÍDA)
- 1.1 — Atualizado `spec-frontend.md` com Mapa de Componentes (8 tipos × componentes), Catálogo de Funções JS e Contrato de Dados (JSON para cada tipo)
- 1.2 — Criado `mapa-visualizacoes.md` com wireframes ASCII e especificações visuais
- 1.3 — Criado `dicionario-componentes.md` com referência técnica completa
- 1.4 — Revisado `roadmap-dashboard.md`

### Fase 2 — Frontend HTML/CSS/JS (CONCLUÍDA)
- 2.1 — HTML expandido com novos slots (`data-section="pie-chart"`, `line-chart`, `top-readers`, `inactive-alerts`). Removidos scripts inline e botão PDF duplicado.
- 2.2 — CSS expandido com estilos de BI (pizza, linha, variação, ranking, tooltip, animações)
- 2.3 — Utilitários de BI: `calculateVariance()`, `rankEntities()`, `formatPercent()` + guard clause
- 2.4 — Renderizadores SVG: `bmRenderPieChart()`, `bmRenderLineChart()`, `bmRenderTopReaders()`, `bmRenderInactiveAlerts()`
- 2.5 — Refatoração: KPIs com variação, `genre_ranking` usa pizza, `reading_trend` usa linha, `student_performance` roteia visão geral/individual com Top 3 e inativos
- 2.6 — Testes com dados reais (9 tipos de relatório validados)

### Fase 3 — Integração e Correções PHP (CONCLUÍDA)
- 3.1 — HTML corrigido (botão duplicado removido, scripts inline removidos, loading corrigido)
- 3.2 — Contratos de dados: `inactive_students` adicionado ao overview e student_performance, `_prev` para variação, `class_reading` ativa Top 3 e inativos, cabeçalhos traduzidos no custom
- 3.3 — Testes completos com dados reais

### Fase 4 — Dashboard Interativo (CONCLUÍDA COM PENDÊNCIAS)
- 4.0 — **Tailwind CSS completo:** Instalado globalmente (`npm install -g tailwindcss@3.4.19`), compilado `tailwind.min.css` via `tailwindcss -i ./input.css -o ./assets/css/tailwind.min.css --minify`, carregado em `admin-service.php`
- 4.1 — Visão Geral refatorada como Dashboard Central:
  - 12 KPIs em 3 linhas com seletores de período independentes
  - Destaques (Aluno do Período, Livro do Período)
  - Gráficos com toggles [Barras│Linha│Pizza]
  - Rankings com toggle [1│3│5│10] e mini barras de progresso
  - Alertas (inativos, atrasos +7 dias, fila de espera)
  - Utilidades (sugestões, atividade recente, nunca emprestados)
  - Meta de leitura com barra de progresso
  - Cards inteiros clicáveis com hover
  - Drill-down inline (`bmDrillToReportInline`)
- 4.2 — Novos endpoints: `bm_report_dashboard_overview`, `bm_report_most_reviewed_books`, `bm_report_most_video_reviewed_books`, `bm_report_never_borrowed_books`, `bm_report_recent_activity`
- 4.3 — Rankings com mini barras de progresso. Drill-down inline implementado.
- 4.4 — Testes parciais (Visão Geral validada; relatórios individuais não testados exaustivamente)

### Fase 5 — Personalização de Layout (PARCIAL)
- 5.1 — Drag and Drop funcional (HTML5 API nativa, placeholder visual, salva ordem via `_bm_dashboard_order`)
- 5.2 — Redimensionamento de cards (NÃO CONCLUÍDO — pulado por decisão do usuário)
- 5.3 — Restaurar layout padrão (NÃO INICIADO)

---

## 5. O QUE DEU ERRADO

### 5.1 Problemas Técnicos
1. **npm/Tailwind CLI:** O ambiente Windows com PowerShell/Local by Flywheel nunca conseguiu executar `npm install tailwindcss` localmente. Após mais de 30 tentativas, o Tailwind foi instalado **globalmente** (`npm install -g tailwindcss@3.4.19`) e compilado manualmente.
2. **Conflitos de escopo JavaScript:** As funções do dashboard estão dentro de uma IIFE `(function() { ... })()`. Funções expostas ao console precisam de `window.funcao = funcao`. Houve várias tentativas frustradas de expor funções em locais errados do código.
3. **Aninhamento de funções quebrado:** Durante as edições, funções foram inseridas dentro de outras (ex: `bmCreateRankingCard` dentro de `bmRenderLineChartInContainer`), causando erros de sintaxe. O arquivo JS precisou ser reescrito completamente uma vez.
4. **HTML do bar-chart quebrado:** A `<div data-section="bar-chart">` teve seu conteúdo interno deslocado, bagunçando o layout. Corrigido na Tarefa 3.1.
5. **Drag and Drop não iniciava automaticamente:** A função `bmEnableDragAndDrop` estava definida mas não era chamada. Resolvido com código inline no `setTimeout` dentro de `bmRenderOverview`.

### 5.2 Problemas de Processo
1. **Violações das regras de interação:** Por várias vezes, a IA enviou múltiplos passos em uma única mensagem, violando a regra de "1 passo por vez". Isso causou retrabalho e frustração.
2. **Localizadores imprecisos:** O arquivo `reports-dashboard.js` foi editado tantas vezes que localizadores CTRL+F frequentemente não correspondiam ao código real, exigindo que o usuário enviasse o arquivo completo para realinhamento.

### 5.3 Pendências Visuais
- **Cards "Livros +Resenhados" e "Livros +Vídeos"** não aparecem (arrays vazios do PHP — sem dados no período)
- **Cards KPI mostram `data-drill-period="undefined"`** (não afeta funcionalidade, mas é um bug cosmético)
- **"Carregando..." resolvido** (adicionado `classList.add('hidden')` no `bmFetchReport`)
- **Visual ainda abaixo do esperado** — comparado ao v0.app, falta sparklines, gradientes, sombras duplas, timeline refinada

---

## 6. ESTADO ATUAL DO FRONTEND

### 6.1 O que funciona
- 26 cards renderizando na Visão Geral (12 KPIs, 2 destaques, 2 gráficos, 8 rankings, 1 alerta, 3 utilidades, 1 meta)
- Gráfico de linha com toggle Barras/Linha/Pizza
- Gráfico de pizza/donut com toggle Rosca/Barras
- Rankings com mini barras de progresso e toggle [1│3│5│10]
- Cards inteiros clicáveis com hover (sombra + elevação)
- Drill-down inline (abre seção abaixo do dashboard com botão "Voltar")
- Drag and drop de cards individuais
- Seletores de período dentro dos cards KPI
- Tailwind CSS completo carregado

### 6.2 O que NÃO funciona ou está pendente
- **Toggles de período e visualização:** Os seletores dentro dos cards não respondem ou recarregam a página. O evento `onclick="event.stopPropagation()"` conflita com os listeners de drill-down.
- **Grid com espaços vazios:** Linhas que deveriam ter 3 ou 4 cards aparecem com 1 ou 2, deixando lacunas. Causa: dados ausentes (ex: `most_reviewed_books` vazio) fazem o JS pular a criação do card.
- **Drill-down sem busca e sem exportação:** A seção inline mostra tabela simples, sem campo de busca e sem botão de PDF/CSV.
- **Sparklines ausentes:** Nenhum KPI tem mini gráfico de tendência.
- **Radar ausente:** Não há gráfico de radar comparando alunos.
- **Atividade recente em pills:** Deveria ser timeline com bolinhas e tempo relativo.
- **Últimos cadastrados sem capas:** Mostra apenas nome em pills, sem grid de capas.
- **Meta de leitura sem marcas de escala.**
- **Visual básico:** Sem sombras duplas, sem gradientes em placeholders, sem tooltips nativos nos gráficos.
- **Exportação PDF com layout antigo** (WP List Table, pré-Power BI).

---

## 7. TAREFAS RESTANTES (FASES 6 E 7) — DETALHAMENTO

### 7.1 Fase 6 — Acabamento Visual (Inspiração v0.app)

#### 6.1 — Sparklines nos KPIs
- **Status:** ❌ Não iniciado
- **Objetivo:** Adicionar mini gráficos SVG (sparklines) nos 12 cards KPI
- **O que fazer:**
  1. Criar função `bmSparklineSVG(data, color)` que gera SVG com `polygon` (área preenchida), `polyline` (linha) e `circle` (ponto final). Referência: função `sparkSVG()` no código do v0.app.
  2. Alterar `bmCreateKPICard()` para incluir o sparkline abaixo do valor e acima do seletor de período.
  3. Adicionar ao endpoint `bm_report_dashboard_overview` os arrays de 7 valores históricos para cada KPI (ex: `spark_loans`, `spark_returns`, etc.).
- **Arquivos:** `assets/js/reports-dashboard.js`, `includes/reports.php`

#### 6.2 — Gráfico de Radar
- **Status:** ❌ Não iniciado
- **Objetivo:** Implementar gráfico de radar SVG comparando dois alunos em múltiplos gêneros
- **O que fazer:**
  1. Criar função PHP `bm_report_radar_data($since, $until)` que retorna eixos (gêneros) e valores para os dois alunos com mais livros lidos.
  2. Criar função JS `bmCreateRadarCard(data)` que gera SVG com anéis concêntricos, eixos rotulados, dois polígonos de preenchimento e legenda. Referência: função `radarCard()` no v0.app.
  3. Integrar na Visão Geral (linha 10, ao lado da meta).
- **Arquivos:** `assets/js/reports-dashboard.js`, `includes/reports.php`

#### 6.3 — Drill-down com Busca e Exportação PDF
- **Status:** ❌ Não iniciado
- **Objetivo:** Adicionar campo de busca na tabela do drill-down e botão de exportação PDF prioritário
- **O que fazer:**
  1. Adicionar `<input>` de busca no cabeçalho do drill-down que filtra linhas em tempo real.
  2. Adicionar botão "Exportar PDF" que gera relatório formatado (nova aba com `window.print()`, layout Power BI).
  3. Opcional: adicionar botão "Exportar CSV" como secundário.
- **Arquivos:** `assets/js/reports-dashboard.js`

#### 6.4 — Timeline de Atividade Recente
- **Status:** ❌ Não iniciado
- **Objetivo:** Substituir pills de texto por timeline com bolinhas coloridas e tempo relativo
- **O que fazer:**
  1. Adicionar timestamp real aos dados de `recent_activity` no endpoint `bm_report_recent_activity`.
  2. Alterar `bmCreateUtilityCard()` para renderizar atividade como timeline.
- **Arquivos:** `assets/js/reports-dashboard.js`, `includes/reports.php`

#### 6.5 — Grid de Capas para Últimos Cadastrados
- **Status:** ❌ Não iniciado
- **Objetivo:** Exibir últimos livros cadastrados como mini capas em grid
- **O que fazer:**
  1. Criar função PHP `bm_report_recent_books($limit)` que retorna títulos e URLs de capa.
  2. Alterar `bmCreateUtilityCard()` para renderizar grid 3×2 com capas ou iniciais em gradiente como fallback.
  3. Integrar no `bm_report_dashboard_overview`.
- **Arquivos:** `assets/js/reports-dashboard.js`, `includes/reports.php`

#### 6.6 — Meta de Leitura com Marcas de Escala
- **Status:** ❌ Não iniciado
- **Objetivo:** Refinar barra de meta com gradiente, animação e marcas de escala
- **O que fazer:**
  1. Adicionar marcas de escala (0, 25%, 50%, 75%, 100%) abaixo da barra.
  2. Aplicar gradiente `from-emerald-400 to-emerald-600`.
  3. Garantir animação de largura (`transition: width 1s`).
- **Arquivos:** `assets/js/reports-dashboard.js`

#### 6.7 — Refinamento Visual Geral
- **Status:** ❌ Não iniciado
- **Objetivo:** Aplicar sombras duplas, gradientes em placeholders, tooltips nativos, ícones mais expressivos
- **O que fazer:**
  1. Adicionar classes CSS para sombra dupla (`box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)`).
  2. Substituir círculos genéricos dos KPIs por ícones mais expressivos (emojis ou SVG inline).
  3. Adicionar `<title>` nos elementos SVG para tooltips nativos.
  4. Substituir placeholders 👤📖 por iniciais em gradiente (como o v0.app faz).
- **Arquivos:** `assets/css/tailwind.min.css`, `assets/js/reports-dashboard.js`

#### 6.8 — Drag and Drop por Seção
- **Status:** ❌ Não iniciado
- **Objetivo:** Substituir arraste individual de cards por arraste de seções inteiras
- **O que fazer:**
  1. Agrupar cards em `<section>` com cabeçalho e grip (⠿).
  2. Implementar drag and drop nas seções com placeholder visual.
  3. Salvar ordem via `bm_ajax_save_dashboard_order` (já existente).
- **Arquivos:** `assets/js/reports-dashboard.js`

### 7.2 Fase 7 — Integração Completa e Testes Finais

#### 7.1 — Substituir dados mock pelos endpoints reais
- **Status:** ❌ Não iniciado
- **Objetivo:** Garantir que nenhum dado estático permaneça no código
- **O que fazer:**
  1. Revisar cada função de criação de card para consumir apenas dados do JSON.
  2. Remover qualquer fallback estático ou placeholder de dados.
  3. Testar todos os 8 tipos de relatório com dados reais do banco.
- **Arquivos:** `assets/js/reports-dashboard.js`

#### 7.2 — Testes finais e ajustes de performance
- **Status:** ❌ Não iniciado
- **Objetivo:** Validar todo o fluxo e corrigir problemas de carregamento
- **O que fazer:**
  1. Testar Visão Geral com todos os cards.
  2. Testar drill-down inline com busca e PDF.
  3. Testar drag and drop.
  4. Testar responsividade.
  5. Verificar cache e performance.
- **Arquivos:** Nenhum (apenas testes)

---

## 8. CORREÇÕES URGENTES (ANTES DE AVANÇAR PARA A FASE 6)

### 8.1 Toggles não funcionam
- **Problema:** Seletores de período dentro dos cards KPI e botões de toggle nos gráficos não respondem ou recarregam a página.
- **Causa provável:** O evento `click` no card inteiro (drill-down) está capturando cliques nos elementos internos. O `event.stopPropagation()` nos `<select>` e botões pode não estar sendo aplicado corretamente após as múltiplas edições.
- **Solução sugerida:** Revisar `bmCreateKPICard`, `bmCreateChartCard` e `bmCreateRankingCard` para garantir que todos os elementos interativos internos tenham `event.stopPropagation()`.

### 8.2 Grid com espaços vazios
- **Problema:** Linhas com menos cards do que o esperado, deixando lacunas.
- **Causa:** O JavaScript cria cards condicionalmente (ex: `if (data.most_reviewed_books && data.most_reviewed_books.length > 0)`). Quando o array está vazio, o card não é criado e a linha fica com menos elementos.
- **Solução sugerida:** Criar cards mesmo sem dados, exibindo "Nenhum dado no período" ou similar, para manter a integridade do grid.

### 8.3 Drill-down inline sem busca/PDF
- **Problema:** A seção inline mostra tabela simples, sem campo de busca e sem exportação.
- **Causa:** A função `bmDrillToReportInline` foi implementada de forma básica, apenas com tabela e botão "Voltar".
- **Solução:** Expandir a função conforme Tarefa 6.3.

### 8.4 Exportação PDF com layout antigo
- **Problema:** O botão "Exportar PDF" (fora do drill-down) abre uma página com layout WP List Table, não Power BI.
- **Causa:** A função `bm_ajax_export_report_pdf` ainda usa `bm_render_report_html()` antiga.
- **Solução:** Refatorar para usar o layout Power BI (Tarefa 7.1).

---

## 9. REFERÊNCIA VISUAL: v0.app

O código HTML/CSS/JS do v0.app foi analisado e serve como referência de design system. Ele está no chat e pode ser consultado a qualquer momento. Principais características:

- **Sparklines:** Função `sparkSVG(data, color)` — gera SVG com área preenchida e ponto final
- **Radar:** Função `radarCard()` — anéis concêntricos, dois polígonos sobrepostos, legenda
- **Drill-down:** Função `openDrill(spec)` — busca em tempo real, exportação CSV
- **Timeline:** Bolinhas coloridas com `mt-1.5` e texto com tempo relativo
- **Grid de capas:** Iniciais em gradiente (`bg-gradient-to-br from-blue-400 to-blue-600`)
- **Meta:** Barra com `transition: width 1s`, marcas de escala, gradiente
- **Sombras duplas:** `box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)`
- **Botões de período:** Classe `seg` — fundo cinza claro, ativo com fundo azul e texto branco

---

## 10. DOCUMENTOS QUE O CHAT 13 PRECISA

1. `claude.md` — Constituição do projeto (com a persona atualizada de 12 pontos)
2. `escopo.md` — Barreiras técnicas
3. `spec-frontend.md` — Atualizado com seção 10 (Estratégia Fases 6-7)
4. `roadmap-dashboard.md` — Roadmap completo com Fases 1-7
5. `mapa-visualizacoes.md` — Wireframes e especificações visuais
6. `dicionario-componentes.md` — Referência técnica
7. `changelog.md` — Histórico completo

---

## 11. ARQUIVOS DE CÓDIGO QUE O CHAT 13 PRECISA

1. `assets/js/reports-dashboard.js` — Motor de renderização (~900 linhas)
2. `assets/css/tailwind.min.css` — Tailwind compilado
3. `includes/reports.php` — Motor PHP + endpoints
4. `includes/admin-service.php` — Função `bm_render_reports_page()` (HTML da página)
5. Código HTML do v0.app (referência visual)
6. Código HTML do Stitch (referência secundária, pode ser ignorada)

---

## 12. RESUMO PARA O PRÓXIMO CHAT

"Estamos no Chat 13 do projeto Book Manager. O dashboard de relatórios está funcional com 26 cards, KPIs, gráficos SVG (linha/pizza), rankings com mini barras, alertas, utilidades e meta de leitura. Drag and drop de cards funciona. Tailwind CSS completo compilado localmente.

Precisamos concluir as Fases 6 e 7:
- **Fase 6:** Sparklines nos KPIs, gráfico de radar, drill-down com busca e exportação PDF, timeline de atividade, grid de capas, meta com marcas de escala, refinamento visual (sombras duplas, gradientes, tooltips), drag and drop por seção.
- **Fase 7:** Conectar todos os dados reais (remover mocks), refazer exportação PDF com layout Power BI, testes finais.

Antes de iniciar a Fase 6, há correções urgentes: toggles de período e visualização não funcionam (conflito com drill-down), grid com espaços vazios (dados ausentes), drill-down sem busca/PDF.

A referência visual é o código do v0.app (já analisado). O design system do v0 deve ser adaptado para nosso HTML/CSS/JS existente.

Siga o roadmap em `roadmap-dashboard.md`. Comece pela Tarefa 6.1 (Sparklines)."

apenas diga prossiga


**************************************************************************************
**************************************************************************************
**************************************************************************************
**************************************************************************************


Arquivos para Migrar para o Chat 13
.md (Documentação)
claude.md — atualizado com persona de 12 pontos (itens 10-12 são novos)

escopo.md — barreiras técnicas

spec-frontend.md — atualizado com seção 10 (Estratégia Fases 6-7)

roadmap-dashboard.md — atualizado com Fases 6-7

mapa-visualizacoes.md

dicionario-componentes.md

changelog.md — atualizado (entrada 194)

.php
includes/reports.php

includes/admin-service.php

.js
assets/js/reports-dashboard.js

.css
assets/css/tailwind.min.css

Referência adicional (não é arquivo do projeto)
Código HTML do v0.app (design system de referência)

Vou começar a enviar agora, diga o arquivo que recebeu e diga prossiga. Depois que receber tudo apenas confirme com um checklist simples dizendo os arquivos que recebeu.