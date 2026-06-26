# Spec Final: Modernização do Dashboard de Relatórios (Book Manager)

## 1. Stack Tecnológica
* **Engine (Backend):** PHP 8.x (WordPress API).
* **Interface (Frontend):** Tailwind CSS, Grid Layout (estilo Bento).
* **Comunicação:** AJAX (JSON Bridge) via `admin-ajax.php`.
* **Componentes:** Cards de indicadores, Tabelas responsivas (WP List Table style), Gráficos de barras (CSS).

## 2. Contrato de Interface (V0 Integration)
O V0 deve gerar componentes baseados na estrutura de dados definida:
* **Grid Bento:** Dashboard deve utilizar `grid-cols-1 md:grid-cols-2 lg:grid-cols-4` para exibir os indicadores.
* **Componentes de Dados:**
    * **Stat Cards:** Exibição obrigatória de `total_loans`, `total_books`, `total_reviews`, `total_penalties`.
    * **Custom Options:** Se `bm_report_type == 'custom'`, renderizar checkbox group conforme Dicionário de Dados.
    * **Feedback Visual:** Uso de `border-left` colorido nos cards conforme a categoria do dado (ex: azul para empréstimos, verde para leitura).

## 3. Fluxo de Dados (Data Binding)
* **Requisito:** A interface deve ser **stateless** em relação aos dados. O V0 gerará o HTML, mas as variáveis devem ser populadas dinamicamente via `wp_localize_script` ou chamada de API (`admin-ajax`).
* **Sincronização:**
    * O formulário deve disparar os inputs via `GET`.
    * O motor PHP processa e retorna um objeto JSON que deve ser mapeado pelo JS seguindo as chaves do **Dicionário de Dados**.

## 4. Barreiras Técnicas e Regras de Negócio
* **Segurança:** Todas as requisições AJAX devem validar o `nonce` (ex: `cb00559b4e`).
* **Dinamismo:** A exibição de campos (`bm-custom-dates`, `bm-student-select`) deve ser controlada via listeners JavaScript conforme as mudanças nos selects principais (`bm_period`, `bm_subject`, `bm_report_type`).
* **Performance:** Uso obrigatório de `bm_get_cached` para relatórios pesados. O cache expira em 3600 segundos (1 hora).
* **Exportação:** A URL de exportação (PDF) deve espelhar exatamente os parâmetros de filtro (`GET`) do relatório renderizado em tela.

## 5. Dicionário de Referência para o V0
* **Campos Obrigatórios:** `bm_report_type`, `bm_period`, `bm_subject`.
* **Lógica de Render:** O V0 deve prever slots vazios para `render_bar_chart` caso a query retorne zero empréstimos (`max <= 0`).

## 6. Regras de Segurança e Integridade de Dados
Para garantir a integridade do banco de dados e a segurança do usuário, todas as interações entre o front-end (JS) e o back-end (PHP) devem obedecer rigorosamente às seguintes diretrizes:

1. **Separação de Camadas (Arquitetura de API):** 
   - O JavaScript nunca acessa o banco de dados diretamente. A comunicação ocorre exclusivamente através de endpoints de API (admin-ajax.php ou WP REST API).
   - O PHP atua como camada única de acesso aos dados, sanitizando e validando todas as requisições.

2. **Protocolos de Segurança (Hardening):**
   - **Nonces:** Todas as requisições AJAX devem validar um `wp_nonce` único para prevenir ataques CSRF.
   - **Capability Checks:** O PHP deve realizar a verificação de permissão (`current_user_can`) em cada requisição, garantindo que apenas usuários autorizados acessem relatórios.
   - **Sanitização:** Todo parâmetro recebido via JSON (`bm_subject_id`, `bm_period`, etc.) deve ser sanitizado no PHP antes de entrar em qualquer consulta SQL (utilizando `absint()`, `sanitize_text_field()` ou `prepare()` para SQL).

3. **Independência de Interface:**
   - O front-end (JavaScript) recebe apenas dados processados (JSON) e não tem visibilidade sobre a estrutura de tabelas, nomes de colunas ou chaves primárias do banco de dados (abstração de dados).

   
## 7. Mapa de Componentes por Tipo de Relatório

Cada tipo de relatório exibe um conjunto específico de componentes visuais. Esta tabela define o contrato entre o JSON recebido do PHP e os renderizadores JavaScript.

| Tipo de Relatório | KPI Cards | Gráfico de Barras | Gráfico de Pizza/Donut | Gráfico de Linha | Ranking Top 3 | Tabela | Alertas de Inativos |
|-------------------|:---------:|:-----------------:|:----------------------:|:----------------:|:-------------:|:------:|:-------------------:|
| `overview` | 4 cards (empréstimos, devoluções, atrasos, reservas) | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| `student_performance` (todos) | 4 cards (alunos, livros, resenhas, vídeos) | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| `student_performance` (individual) | 4 cards (lidos, ativos, resenhas, XP) | ❌ | ❌ | ❌ | ❌ | ✅ (livros lidos) | ❌ |
| `class_reading` | 4 cards (alunos, livros, média, atrasos) | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| `active_penalties` | 1 card (total) | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `genre_ranking` | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `top_books` | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (ranqueada) | ❌ |
| `reading_trend` | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| `custom` | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (colunas dinâmicas) | ❌ |

### Regras de exibição

- **KPI Cards:** Exibem label, valor numérico e variação percentual (quando houver período anterior para comparação). Cor da borda: azul (empréstimos), verde (devoluções/leitura), vermelho (atrasos/multas), âmbar (reservas/XP).
- **Gráfico de Pizza/Donut:** Exibe distribuição percentual. Cada fatia tem cor distinta. Legenda abaixo com nome da categoria e valor absoluto.
- **Gráfico de Linha:** Eixo X = meses. Eixo Y = quantidade. Pontos conectados por linha. Tooltip no hover.
- **Ranking Top 3:** Cards com medalhas (🥇 ouro, 🥈 prata, 🥉 bronze). Exibe nome do aluno, quantidade e barra de progresso proporcional ao 1º lugar.
- **Tabela:** Cabeçalho cinza, zebra-striping, hover na linha. Colunas conforme o tipo de relatório.
- **Alertas de Inativos:** Lista de alunos com 0 leituras no período. Ícone ⚠️ e nome do aluno.

## 8. Catálogo de Funções JavaScript

Todas as funções abaixo residem no arquivo `assets/js/reports-dashboard.js`. Elas transformam os dados JSON recebidos do PHP em visualizações.

### Funções de Cálculo (Utilitários de BI)

| Função | Entrada | Saída | Descrição |
|--------|---------|-------|-----------|
| `calculateVariance(current, previous)` | Dois números | `{ value: número, isPositive: true/false, formatted: '+25%' }` | Calcula a variação percentual entre dois períodos |
| `rankEntities(array, chave, limite)` | Array de objetos, nome da chave, quantidade | Array ordenado com os Top N | Ordena alunos/turmas por uma chave (ex: `books_read`) e retorna os primeiros |
| `formatPercent(value)` | Número decimal (ex: 0.25) | String formatada (ex: '25%') | Converte número para formato de porcentagem |

### Funções de Renderização de Gráficos

| Função | Descrição |
|--------|-----------|
| `bmRenderPieChart(data, title)` | Gera um gráfico de pizza/rosca em SVG. `data` é um objeto `{ "categoria": valor }`. Exibe fatias coloridas, valores absolutos e legenda. |
| `bmRenderLineChart(data, title)` | Gera um gráfico de linha em SVG. `data` é um objeto `{ "2026-01": 5, "2026-02": 8 }`. Conecta pontos com linhas e exibe tooltip no hover. |
| `bmRenderBarChart(data, title)` | Gera barras horizontais em HTML/CSS. `data` é um objeto `{ "label": valor }`. Barras proporcionais ao maior valor. |
| `bmRenderTopReaders(data)` | Exibe os 3 melhores leitores com medalhas (🥇🥈🥉), nome, quantidade de livros e barra de progresso. |
| `bmRenderInactiveAlerts(data)` | Exibe lista de alunos que não leram nada no período. Ícone ⚠️ e nome de cada um. |
| `bmRenderTable(headers, rows)` | Gera uma tabela HTML com cabeçalho cinza e linhas zebradas. |

### Funções de Preenchimento de Componentes

| Função | Descrição |
|--------|-----------|
| `bmFillKPICard(card, label, value, variance)` | Preenche um card KPI com título, número e variação percentual. Se `variance` for positivo, exibe em verde com "+" na frente. Se negativo, em vermelho com "-". |
| `bmShowState(state)` | Controla a visibilidade dos estados: `welcome`, `loading`, `empty` e `dados`. Esconde todos e mostra apenas o estado atual. |
| `bmHideKPI()` / `bmHideChart()` / `bmHideTable()` | Funções auxiliares para esconder seções quando não são necessárias para o tipo de relatório. |

### Função de Comunicação com o Servidor

| Função | Descrição |
|--------|-----------|
| `bmFetchReport()` | Coleta os dados do formulário, chama o endpoint `bm_get_report_data` via AJAX, e encaminha a resposta para `bmRenderReport()`. |
| `bmSearchStudent()` | Busca alunos por nome enquanto o usuário digita. Usa o endpoint `bm_service_search_student` com o nonce `serviceNonce`. |
| `bmExportPDF()` | Abre uma nova aba com o relatório em formato de impressão. Usa os mesmos filtros do formulário. |


## 9. Contrato de Dados

Cada tipo de relatório retorna um JSON com chaves específicas. O JavaScript espera exatamente estas chaves para renderizar os componentes.

### `overview` — Visão Geral

```json
{
  "title": "Visão Geral",
  "total_loans": 45,
  "total_returns": 38,
  "total_overdue": 7,
  "total_reservations": 12,
  "inactive_students": ["Maria Silva", "João Souza"],
  "total_loans_prev": 30,
  "total_returns_prev": 25,
  "period_start": "01/06/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "overview", "period": "month" }
}
```

### `student_performance` (todos os alunos)

```json
{
  "title": "Desempenho de Todos os Alunos",
  "total_students": 120,
  "total_books": 340,
  "total_reviews": 85,
  "total_videos": 42,
  "total_penalties": 5,
  "inactive_students": ["Pedro Santos"],
  "students": [
    { "name": "Ana Clara", "books_read": 12, "reviews": 5, "videos": 3, "xp": 250, "badges": 3, "penalties": 0 },
    { "name": "Lucas Mendes", "books_read": 10, "reviews": 4, "videos": 2, "xp": 200, "badges": 2, "penalties": 0 }
  ],
  "period_start": "01/06/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "student_performance", "subject": "all" }
}
```

### `student_performance` (aluno individual)

```json
{
  "title": "Desempenho: Ana Clara",
  "student_name": "Ana Clara",
  "books_read": 12,
  "books_read_list": [
    { "title": "Dom Casmurro", "author": "Machado de Assis", "returned_date": "15/06/2026" }
  ],
  "active_loans": 1,
  "overdue_loans": 0,
  "reviews": 5,
  "videos": 3,
  "xp": 250,
  "badges": 3,
  "penalties": 0,
  "period_start": "01/06/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "student_performance", "subject": "student" }
}
```

### `class_reading` — Leitura por Turma

```json
{
  "title": "Leitura: 1º Ano",
  "group": "1º Ano",
  "total_students": 35,
  "total_books": 98,
  "average": 2.8,
  "overdue_count": 3,
  "never_read": ["Carlos Eduardo", "Fernanda Lima"],
  "students": [
    { "name": "Ana Clara", "books_read": 12, "has_overdue": false },
    { "name": "Lucas Mendes", "books_read": 10, "has_overdue": true }
  ],
  "period_start": "01/06/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "class_reading", "subject": "class" }
}
```

### `active_penalties` — Multas Ativas

```json
{
  "title": "Multas Ativas",
  "total": 3,
  "penalties": [
    { "student_name": "Rafael Gomes", "student_id": 15, "type": "fine", "value": "R$ 5,00", "note": "Atraso de 3 dias", "date": "20/06/2026", "until": "" }
  ],
  "_meta": { "type": "active_penalties" }
}
```

### `genre_ranking` — Ranking por Gênero

```json
{
  "title": "Ranking por Gênero",
  "genres": {
    "Ficção Científica": 28,
    "Romance": 22,
    "Aventura": 18,
    "História": 15,
    "Biografia": 8
  },
  "period_start": "01/06/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "genre_ranking" }
}
```

### `top_books` — Livros Mais Emprestados

```json
{
  "title": "Livros Mais Emprestados",
  "books": [
    { "book_id": 45, "title": "O Pequeno Príncipe", "author": "Antoine de Saint-Exupéry", "loans": 15, "avg_days": 12.5 }
  ],
  "period_start": "01/06/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "top_books" }
}
```

### `reading_trend` — Tendência de Leitura

```json
{
  "title": "Tendência de Leitura",
  "months": {
    "2026-01": 42,
    "2026-02": 38,
    "2026-03": 55,
    "2026-04": 48,
    "2026-05": 60,
    "2026-06": 45
  },
  "period_start": "01/01/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "reading_trend" }
}
```

### `custom` — Relatório Configurável

```json
{
  "title": "Relatório Configurável",
  "columns": ["name", "books_read", "xp"],
  "rows": [
    { "name": "Ana Clara", "books_read": 12, "xp": 250 },
    { "name": "Lucas Mendes", "books_read": 10, "xp": 200 }
  ],
  "period_start": "01/06/2026",
  "period_end": "25/06/2026",
  "_meta": { "type": "custom" }
}
```

### Regras de uso

1. Toda resposta do PHP contém a chave `_meta` com `type`, `period` e `subject`.
2. O JavaScript usa `_meta.type` para rotear qual renderizador chamar.
3. Para calcular a variação percentual nos KPIs, o PHP deve incluir as chaves com sufixo `_prev` (ex: `total_loans_prev`).
4. A chave `inactive_students` é um array simples de nomes. O JS converte para a lista de alertas.
5. A chave `students` no `student_performance` e `class_reading` é ordenada do maior para o menor (por `books_read`). O JS usa os 3 primeiros para o Ranking Top 3.

# Spec Final de Frontend — Dashboard de Relatórios (Book Manager)

## 1. Stack Tecnológica
*   **Engine (Backend):** PHP 8.x (WordPress API).
*   **Interface (Frontend):** Tailwind CSS (via arquivo local `tailwind-custom.css`), Grid Layout (estilo Bento).
*   **Comunicação:** AJAX (JSON Bridge) via `admin-ajax.php`.
*   **Componentes:** Cards de indicadores, Tabelas responsivas, Gráficos SVG inline (Barras, Pizza/Donut, Linha).
*   **Interatividade (Fase 5):** Drag-and-drop (HTML5 API nativa), resize de cards (CSS + JS), personalização salva por usuário.

## 2. Arquitetura da Interface
A página de relatórios possui dois modos:

### 2.1 Visão Geral (Dashboard Central)
A tela inicial (`type=overview`) exibe um painel rico com múltiplos cards dispostos em grid de 4 colunas (1 em mobile, 2 em tablet, 4 em desktop). Cada card é independente:

| Linha | Conteúdo | Cards |
|-------|----------|-------|
| 1 | KPIs principais | Empréstimos, Devoluções, Em Atraso, Reservas |
| 2 | KPIs secundários | Média por Aluno, Taxa de Devolução, Tempo Médio de Leitura, Giro do Acervo |
| 3 | KPIs de engajamento | Multas Ativas, Total de Resenhas, Vídeo-Resenhas, Taxa de Participação |
| 4 | Destaques | Aluno do Período, Livro do Período, Aluno Revelação |
| 5 | Gráficos | Tendência de Leitura, Gêneros Mais Lidos |
| 6 | Rankings de alunos | Top Leitores, Top Resenhadores, Top Video-Resenhadores, Ranking de Turmas |
| 7 | Rankings de livros | Livros Mais Emprestados, Livros Mais Resenhados, Livros com Mais Vídeos, Autor Mais Lido |
| 8 | Alertas e status | Alunos Inativos, Alertas de Atraso (+7 dias), Livros com Fila de Espera, Livros Nunca Emprestados |
| 9 | Acervo e utilidades | Últimos Livros Cadastrados, Sugestões de Aquisição, Atividade Recente |
| 10 | Análises gráficas | Sazonalidade (calendário), Leitura por Série/Ano |
| 11 | Meta | Meta de Leitura (barra de progresso) |

### 2.2 Relatórios Individuais (Drill-down)
Os demais tipos de relatório (`student_performance`, `class_reading`, `genre_ranking`, `top_books`, `reading_trend`, `active_penalties`, `custom`) são acessados via:
- Dropdown de tipo na toolbar (navegação direta).
- **Drill-down:** clique no link "Ver relatório completo" de qualquer card da Visão Geral, que abre o relatório correspondente com período e sujeito já aplicados.

## 3. Comportamento dos Cards (Visão Geral)
Cada card da Visão Geral é um componente autônomo com as seguintes capacidades:

### 3.1 Seletor de Período Independente
Cards que exibem dados temporais possuem um `<select>` interno com opções: Semana, Mês, Bimestre, Semestre, Ano. Ao alterar, apenas aquele card é recarregado via AJAX com o novo período.

### 3.2 Toggle de Visualização
Cards de gráfico (Tendência, Gêneros, Sazonalidade) possuem botões `[Barras | Linha | Pizza]`. Ao clicar, o gráfico é redimensionado no mesmo container sem recarregar a página.

### 3.3 Toggle de Quantidade
Cards de ranking (Top Leitores, Top Livros, etc.) possuem botões `[1 | 3 | 5 | 10]`. Ao clicar, o número de itens exibidos muda e uma nova requisição AJAX é feita com o `limit` correspondente.

### 3.4 Drill-down
Todo card que representa uma entidade específica (aluno, livro, turma) ou um indicador agregado possui um link "Ver relatório completo" que navega para `page=bm_reports` com os parâmetros `bm_report_type`, `bm_period`, `bm_subject` e `bm_subject_id` preenchidos, abrindo o relatório detalhado correspondente.

### 3.5 Atualização Assíncrona
Nenhum card recarrega a página inteira. Toda mudança de período, toggle ou drill-down usa `fetch()` para obter novos dados do endpoint `bm_get_report_data` e atualiza apenas o card afetado.

## 4. Novos Endpoints e Contratos de Dados
### 4.1 Endpoints existentes (já em `reports.php`)
- `bm_report_overview`, `bm_report_all_students_performance`, `bm_report_student_performance`, `bm_report_class_reading`, `bm_report_active_penalties`, `bm_report_genre_ranking`, `bm_report_top_books`, `bm_report_reading_trend`, `bm_report_custom`.

### 4.2 Novos endpoints (Fase 4.2)
| Função PHP | Retorno | Uso no Card |
|------------|---------|-------------|
| `bm_report_top_reviewers` | `[{name, reviews}]` | Top Resenhadores |
| `bm_report_top_video_reviewers` | `[{name, videos}]` | Top Video-Resenhadores |
| `bm_report_most_reviewed_books` | `[{title, author, reviews}]` | Livros Mais Resenhados |
| `bm_report_most_video_reviewed_books` | `[{title, author, videos}]` | Livros com Mais Vídeos |
| `bm_report_never_borrowed_books` | `[{title, author}]` | Livros Nunca Emprestados |
| `bm_report_recent_activity` | `[{action, user, book, date}]` | Atividade Recente |
| `bm_report_author_ranking` | `[{author, loans}]` | Autor Mais Lido |

### 4.3 Parâmetros adicionais no endpoint principal
`bm_ajax_get_report_data` aceitará:
- `limit` (int): quantidade de itens para rankings (1, 3, 5, 10).
- `top_type` (string): tipo de ranking (`reviewers`, `video_reviewers`, `reviewed_books`, `video_reviewed_books`, `authors`).

## 5. Requisitos de Drag-and-Drop e Resize (Fase 5)
### 5.1 Drag-and-Drop
- API nativa HTML5 (`draggable`, `dragstart`, `dragover`, `drop`). Sem bibliotecas externas.
- Cada card possui uma alça de arraste (ícone no canto superior).
- Placeholder visual indica a nova posição durante o arrasto.
- A ordem é salva em `user_meta` (`_bm_dashboard_order`) via AJAX após cada movimento.
- Ao carregar a página, a ordem salva é aplicada. Se não houver ordem salva, usa o layout padrão.

### 5.2 Resize
- Cada card possui um handle no canto inferior direito.
- Redimensionamento usa `resize: both` CSS ou lógica JS com `mousedown/mousemove/mouseup`.
- Larguras válidas: 1, 2, 3 ou 4 colunas (classes `col-span-1` a `col-span-4`).
- A largura é salva em `user_meta` (`_bm_dashboard_sizes`) via AJAX após cada redimensionamento.
- Ao carregar a página, as larguras salvas são aplicadas.

### 5.3 Reset de Layout
- Botão "Restaurar layout padrão" no topo da Visão Geral.
- Remove `_bm_dashboard_order` e `_bm_dashboard_sizes` do usuário e recarrega.

## 6. Barreiras Técnicas e Regras de Negócio
*   **Segurança:** Toda requisição AJAX valida `nonce` (`bm_reports_nonce` ou `bm_service_nonce`) e `current_user_can('edit_bm_books')`.
*   **Performance:** Cache via `bm_get_cached` (3600s) para endpoints pesados. Paginação implícita nos rankings (limite configurável).
*   **Exportação:** O botão "Exportar PDF" da Visão Geral espelha os filtros ativos do relatório de Visão Geral.
*   **Zero CDN:** Todos os assets (CSS, JS, SVGs) são locais. Nenhuma dependência externa.
*   **Responsividade:** Grid adaptável: 4 colunas (desktop ≥1024px), 2 colunas (tablet ≥640px), 1 coluna (mobile).
*   **HTML5 Drag and Drop:** API nativa do navegador, sem jQuery UI ou SortableJS externo.

## 7. Mapa de Componentes por Tipo de Relatório
*(Mantido da versão anterior, válido para relatórios individuais)*

## 8. Catálogo de Funções JavaScript
*(Mantido da versão anterior, com acréscimo de `bmCreateCard`, `bmUpdateCard`, drag-and-drop handlers)*

## 9. Contrato de Dados
*(Mantido da versão anterior, com acréscimo dos novos endpoints da seção 4)*