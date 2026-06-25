Você é um designer de dashboards de alto nível. Crie o HTML/CSS de um dashboard
de biblioteca escolar com qualidade visual Power BI, usando EXCLUSIVAMENTE classes
Tailwind. Não invente classes — use apenas as documentadas em tailwindcss.com.

REGRAS ABSOLUTAS:
- Zero CDN. Zero importação externa. Zero <link>. Zero @import.
- Apenas classes Tailwind padrão. Nada de CSS customizado.
- Todos os ícones devem ser SVG inline simples (círculo, seta, barra).
- O HTML gerado será injetado dentro de <div class="wrap"> do WordPress admin.
- Não use <html>, <head>, <body> — gere apenas o conteúdo interno.

---

ESTRUTURA DO FORMULÁRIO DE FILTROS (Toolbar Superior):

Crie uma toolbar com fundo branco, borda cinza clara, padding 4, rounded-lg, mb-6,
com display flex, flex-wrap, gap-4, items-end.

Dentro dela, nesta ordem exata:

1. DIV: label "Tipo de Relatório" (block, text-xs, font-bold, text-gray-600, mb-1)
   SELECT: name="bm_report_type"
   Classes: w-48, px-3, py-2, border, border-gray-300, rounded-md, text-sm
   Options: value="overview" (selected) "Visão Geral"
            value="student_performance" "Desempenho do Aluno"
            value="class_reading" "Leitura por Turma"
            value="active_penalties" "Multas Ativas"
            value="genre_ranking" "Ranking por Gênero"
            value="top_books" "Livros Mais Emprestados"
            value="reading_trend" "Tendência de Leitura"
            value="custom" "Relatório Configurável"

2. DIV: label "Período"
   SELECT: name="bm_period"
   Classes: w-36, px-3, py-2, border, border-gray-300, rounded-md, text-sm
   Options: value="week" "Última Semana"
            value="month" (selected) "Último Mês"
            value="bimester" "Último Bimestre"
            value="semester" "Último Semestre"
            value="year" "Último Ano"
            value="custom" "Personalizado"

3. DIV: id="bm-custom-dates" (hidden por padrão, display none)
   Conter dois inputs:
   INPUT: type="date" name="bm_date_start" (w-36, mesma estilização)
   INPUT: type="date" name="bm_date_end" (w-36, mesma estilização)

4. DIV: label "Sujeito"
   SELECT: name="bm_subject"
   Classes: w-36, px-3, py-2, border, border-gray-300, rounded-md, text-sm
   Options: value="all" (selected) "Todos"
            value="student" "Aluno Específico"
            value="class" "Turma"

5. DIV: id="bm-subject-options"
   Dentro dele:
   DIV: id="bm-student-select" (hidden)
     INPUT: type="text" id="bm-student-search-input" placeholder="Digite o nome..." (w-48)
     DIV: id="bm-student-search-results" (max-h-32, overflow-y-auto, mt-1)
     INPUT: type="hidden" name="bm_subject_id" id="bm-subject-id"
   DIV: id="bm-class-select" (hidden)
     INPUT: type="text" name="bm_group" placeholder="Ex: 1º Ano" (w-28)

6. DIV: id="bm-custom-options" (hidden, w-full, mt-2, p-3, bg-gray-50, rounded-md)
   Label "Colunas:" (text-sm, font-bold)
   Checkboxes com name="bm_custom_columns[]":
     value="name" (checked) "Nome"
     value="group" "Turma"
     value="books_read" (checked) "Livros Lidos"
     value="reviews" "Resenhas"
     value="videos" "Vídeos"
     value="xp" "XP"
     value="badges" "Medalhas"
     value="penalties" "Multas"
   SELECT: name="bm_custom_sort" label "Ordenar por:"
     value="name" "Nome"
     value="xp" "XP"
     value="books_read" "Livros Lidos"

7. BUTTON: type="submit"
   Classes: px-6, py-2, bg-blue-600, text-white, rounded-md, hover:bg-blue-700,
   font-medium, text-sm, transition-colors
   Texto: "Gerar Relatório"

8. BUTTON: type="button" id="bm-export-pdf"
   Classes: px-4, py-2, bg-gray-100, text-gray-700, rounded-md,
   hover:bg-gray-200, font-medium, text-sm, transition-colors
   Texto: "Exportar PDF"

---

ESTRUTURA DA ÁREA DE RESULTADOS:

DIV: id="bm-report-result"
Classes: space-y-6

Dentro dele, crie estas SEÇÕES como slots vazios (sem dados de exemplo).
Cada seção DEVE ter o data-section indicado para o JS encontrar:

SEÇÃO 1 — TÍTULO DO RELATÓRIO:
DIV: data-section="report-title"
Contém: H2 (text-xl, font-bold, text-gray-900) + P (text-sm, text-gray-500)
Ambos vazios, serão preenchidos pelo JS.

SEÇÃO 2 — KPI CARDS (4 cards lado a lado):
DIV: data-section="kpi-cards"
Classes: grid, grid-cols-1, sm:grid-cols-2, lg:grid-cols-4, gap-4

Cada card DEVE ter este esqueleto EXATO:
DIV: bg-white, rounded-xl, p-5, shadow-sm, border-l-4, border-blue-500,
     hover:shadow-md, transition-shadow
  DIV: flex, items-center, justify-between
    DIV:
      P: text-xs, font-medium, text-gray-500, uppercase, tracking-wider
         (vazio — será "Empréstimos", "Devoluções" etc.)
      P: text-2xl, font-bold, text-gray-900, mt-1
         (vazio — será o número)
    DIV: w-10, h-10, rounded-full, bg-blue-50, flex, items-center, justify-center
      SVG inline: círculo ou seta simples (w-5, h-5, text-blue-600)
  DIV: mt-3, flex, items-center, gap-1
    SPAN: text-xs, font-medium, text-green-600
      (vazio — será "+20% vs mês anterior")
    SPAN: text-xs, text-gray-400 (vazio — período comparativo)

Repita este card 4 vezes com as cores de borda:
Card 1: border-blue-500, bg-blue-50, text-blue-600 (Empréstimos)
Card 2: border-emerald-500, bg-emerald-50, text-emerald-600 (Devoluções)
Card 3: border-red-500, bg-red-50, text-red-600 (Atrasos)
Card 4: border-amber-500, bg-amber-50, text-amber-600 (Reservas)

SEÇÃO 3 — GRÁFICO DE BARRAS (Ranking por Gênero / Tendência):
DIV: data-section="bar-chart" data-component="bm-chart"
Classes: bg-white, rounded-xl, p-5, shadow-sm
  H3: text-base, font-semibold, text-gray-800, mb-4
  DIV: id="bm-chart-container"
  Classes: space-y-3, max-w-xl
  Vazio — o JS preencherá com barras.
  Cada barra será: flex, items-center, gap-3
    DIV: text-xs, text-gray-600, text-right (label)
    DIV: flex-1, bg-gray-100, rounded-full, h-6, overflow-hidden
      DIV: bg-blue-500, h-full, rounded-full (barra com width%)
      SPAN: text-xs, text-white, font-bold, ml-2 (valor)

SEÇÃO 4 — TABELA DE DADOS:
DIV: data-section="data-table"
Classes: bg-white, rounded-xl, shadow-sm, overflow-hidden
  TABLE: w-full, text-sm
    THEAD: bg-gray-50, border-b, border-gray-200
      TR: THs com px-4, py-3, text-left, text-xs, font-medium,
          text-gray-500, uppercase, tracking-wider
    TBODY: divide-y, divide-gray-100
      TR: hover:bg-gray-50, transition-colors
        TDs com px-4, py-3, text-gray-900
  Vazio — o JS preencherá com dados reais.

---

ESTADOS VISUAIS (importante para o JS):

Estado INICIAL (antes de qualquer relatório ser gerado):
- data-section="report-title" → oculto (hidden)
- data-section="kpi-cards" → oculto
- data-section="bar-chart" → oculto
- data-section="data-table" → oculto
- Mostrar apenas um DIV de boas-vindas: text-center, py-12, text-gray-400
  "Selecione os filtros e clique em Gerar Relatório"

Estado CARREGANDO (enquanto o AJAX processa):
- DIV id="bm-loading" com: text-center, py-8 (hidden por padrão)
  "Carregando..." com animate-pulse

Estado SEM DADOS (quando o JSON retorna vazio):
- DIV id="bm-empty" com: text-center, py-8 (hidden por padrão)
  SVG ícone de caixa vazia + "Nenhum dado encontrado para este período."

---

ESTILO VISUAL GLOBAL:

- Fundo da página: o WordPress já provê. Não defina background no container.
- Todos os cards: bg-white, rounded-xl, shadow-sm
- Textos: gray-900 para títulos, gray-600 para labels, gray-500 para secundários
- Cores de destaque (border-l-4 nos cards):
  Azul (#3b82f6 / blue-500) → Empréstimos
  Verde (#10b981 / emerald-500) → Devoluções
  Vermelho (#ef4444 / red-500) → Atrasos
  Âmbar (#f59e0b / amber-500) → Reservas
- Hover: shadow-md, transition-shadow duration-200
- Font: o WordPress admin já define. Não especifique font-family.

---

ENTREGA:

Gere APENAS o HTML interno (o que ficaria dentro de <div class="wrap">).
Sem comentários explicativos. Sem placeholders de dados de exemplo.
Apenas a estrutura pronta para receber dados dinâmicos.