# Mapa de Visualizações — Dashboard de Relatórios

Documento de referência visual para desenvolvimento do frontend. Define a aparência e o comportamento de cada componente.

---

## 1. Layout Bento Grid

```
┌──────────────────────────────────────────────────────────┐
│  FORMULÁRIO DE FILTROS                                    │
│  [Tipo] [Período] [Datas] [Sujeito] [Aluno/Turma]        │
│  [Colunas configuráveis (se custom)]                      │
│  [Gerar Relatório] [Exportar PDF]                         │
├──────────────────────────────────────────────────────────┤
│  TÍTULO DO RELATÓRIO                                      │
│  Período: 01/06/2026 — 25/06/2026                        │
├──────────┬──────────┬──────────┬──────────────────────────┤
│ KPI 1    │ KPI 2    │ KPI 3    │ KPI 4                    │
│ (azul)   │ (verde)  │ (verm)   │ (âmbar)                  │
├──────────┴──────────┴──────────┴──────────────────────────┤
│  RANKING TOP 3                                            │
│  ┌─────────┐    ┌─────────┐    ┌─────────┐               │
│  │ 🥇 Nome │    │ 🥈 Nome │    │ 🥉 Nome │               │
│  │  12 liv.│    │  10 liv.│    │  8 liv. │               │
│  └─────────┘    └─────────┘    └─────────┘               │
├──────────────────────────────────────────────────────────┤
│  GRÁFICO (pizza/donut ou linha ou barras)                 │
│  ┌──────────────────────────┐                             │
│  │                          │                             │
│  └──────────────────────────┘                             │
│  Legenda: Ficção 28  Romance 22  Aventura 18              │
├──────────────────────────────────────────────────────────┤
│  ALERTAS DE INATIVOS                                      │
│  ⚠️ Maria Silva  ⚠️ João Souza                            │
├──────────────────────────────────────────────────────────┤
│  TABELA DE DADOS                                          │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Cabeçalho (cinza)                                     │ │
│  │ Linha zebrada                                         │ │
│  │ Linha                                                  │ │
│  └──────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
```

---

## 2. Especificação Visual de Cada Componente

### 2.1 KPI Card

```
┌──────────────────────────────┐
│ 🔵 EMPRÉSTIMOS               │  ← borda esquerda colorida (4px)
│                              │
│ 45                           │  ← número grande (2xl, bold)
│                              │
│ +20% vs mês anterior         │  ← variação: verde se positivo, vermelho se negativo
└──────────────────────────────┘
```

**Cores por indicador:**
| Indicador | Cor da borda | Ícone |
|-----------|-------------|-------|
| Empréstimos | Azul (`#3b82f6`) | Círculo azul |
| Devoluções / Livros Lidos | Verde (`#10b981`) | Círculo verde |
| Em Atraso / Multas | Vermelho (`#ef4444`) | Círculo vermelho |
| Reservas / XP / Média | Âmbar (`#f59e0b`) | Círculo âmbar |

**Regra de variação:**
- `+X%` → texto verde (`#16a34a`)
- `-X%` → texto vermelho (`#dc2626`)
- Sem período anterior para comparar → não exibe variação

---

### 2.2 Gráfico de Barras

```
┌─────────────────────────────────────────┐
│ Empréstimos por Gênero                  │
│                                         │
│ Ficção Científica  ██████████████ 28    │  ← barra azul proporcional
│ Romance            ████████████   22    │
│ Aventura           ██████████     18    │
│ História           ████████       15    │
│ Biografia          ████            8    │
└─────────────────────────────────────────┘
```

**Regras:**
- Cor da barra: azul (`#3b82f6`)
- Fundo da barra: cinza claro (`#e5e7eb`)
- Altura: 24px
- Valor numérico dentro da barra (branco, bold, 12px)
- Label à esquerda (cinza, 12px, alinhado à direita, truncado com `...` se longo)
- Tooltip ao passar o mouse: nome completo + valor

---

### 2.3 Gráfico de Pizza/Donut (SVG)

```
┌─────────────────────────────────────────┐
│ Distribuição de Gêneros                 │
│                                         │
│         ┌─────────┐                     │
│        ╱  28  FC  ╲                    │  ← anel com fatias coloridas
│       │  22  Rom   │                    │
│       │  18  Av    │                    │
│        ╲  15  His ╱                     │
│         └─────────┘                     │
│                                         │
│ 🟦 Ficção Científica: 28                │  ← legenda com bolinha colorida
│ 🟥 Romance: 22                          │
│ 🟩 Aventura: 18                         │
│ 🟨 História: 15                         │
│ 🟪 Biografia: 8                         │
└─────────────────────────────────────────┘
```

**Regras:**
- Gráfico gerado em SVG inline (sem bibliotecas externas)
- Formato: anel (donut) com furo central
- Cores das fatias: paleta fixa (azul, vermelho, verde, amarelo, roxo, laranja, rosa, ciano)
- Tamanho: 200px × 200px
- Cada fatia mostra o valor numérico centralizado
- Abaixo do gráfico: legenda com bolinha colorida + nome da categoria + valor

---

### 2.4 Gráfico de Linha (SVG)

```
┌─────────────────────────────────────────┐
│ Tendência de Leitura                    │
│                                         │
│ 60 ┤                                    │
│ 55 ┤           ╭─╮                      │
│ 50 ┤          ╱   ╲                     │
│ 45 ┤    ╭─╮  ╱     ╲──╮                 │
│ 40 ┤   ╱   ╲╱        ╲                 │
│ 35 ┤  ╱                                │
│ 30 ┤ ╱                                 │
│    └────────────────────────────        │
│     Jan  Fev  Mar  Abr  Mai  Jun       │
└─────────────────────────────────────────┘
```

**Regras:**
- Gráfico gerado em SVG inline
- Linha azul (`#3b82f6`) com espessura 2px
- Pontos (bolinhas) em cada valor
- Eixo X: meses abreviados (Jan, Fev, Mar...)
- Eixo Y: escala automática baseada no maior valor
- Tooltip ao passar o mouse sobre cada ponto: "Mês: valor"
- Grade horizontal leve (cinza claro)

---

### 2.5 Ranking Top 3

```
┌────────────────┐  ┌────────────────┐  ┌────────────────┐
│     🥇 1º      │  │     🥈 2º      │  │     🥉 3º      │
│                │  │                │  │                │
│  Ana Clara     │  │  Lucas Mendes  │  │  Pedro Santos  │
│                │  │                │  │                │
│  12 livros     │  │  10 livros     │  │  8 livros      │
│                │  │                │  │                │
│  ████████████  │  │  ██████████    │  │  ████████      │
└────────────────┘  └────────────────┘  └────────────────┘
```

**Regras:**
- 3 cards lado a lado
- Card do 1º lugar: borda dourada (`#f59e0b`), fundo levemente amarelado
- Card do 2º lugar: borda cinza (`#9ca3af`)
- Card do 3º lugar: borda bronze (`#d97706`)
- Foto do aluno (ou placeholder 👤) no topo de cada card
- Barra de progresso proporcional ao 1º lugar (1º = 100%, 2º = 83%, 3º = 66%)

---

### 2.6 Alertas de Inativos

```
┌─────────────────────────────────────────┐
│ ⚠️ Alunos sem leitura no período        │
│                                         │
│ Maria Silva · João Souza · Pedro Santos │  ← nomes em pills
└─────────────────────────────────────────┘
```

**Regras:**
- Fundo: vermelho bem claro (`#fef2f2`)
- Borda esquerda: vermelha (`#ef4444`)
- Ícone: ⚠️ antes do título
- Nomes exibidos em formato de pills (cápsulas) com fundo cinza claro
- Se não houver inativos: seção não aparece

---

### 2.7 Tabela de Dados

```
┌─────────────────────────────────────────┐
│ CABEÇALHO 1  │ CABEÇALHO 2  │ CABEÇ. 3 │  ← fundo cinza, texto uppercase
├───────────────┼───────────────┼──────────┤
│ Dado 1        │ Dado 2        │ Dado 3   │  ← fundo branco
├───────────────┼───────────────┼──────────┤
│ Dado 1        │ Dado 2        │ Dado 3   │  ← fundo cinza claro (zebra)
└─────────────────────────────────────────┘
```

**Regras:**
- Cabeçalho: fundo cinza (`#f9fafb`), texto cinza escuro, uppercase, tracking-wider
- Linhas: zebra-striping (alterna branco e cinza claro)
- Hover: fundo cinza claro na linha
- Texto: tamanho small (14px)
- Colunas numéricas: alinhadas à direita

---

## 3. Slots HTML (`data-section`)

| Slot | Componente | Quando aparece |
|------|------------|----------------|
| `data-section="report-title"` | Título + período | Sempre |
| `data-section="kpi-cards"` | 4 cartões KPI | `overview`, `student_performance`, `class_reading`, `active_penalties` |
| `data-section="bar-chart"` | Gráfico de barras | Nenhum (substituído por pizza/linha) — mantido para compatibilidade |
| `data-section="pie-chart"` | Gráfico de pizza/donut (SVG) | `genre_ranking` |
| `data-section="line-chart"` | Gráfico de linha (SVG) | `reading_trend` |
| `data-section="top-readers"` | Ranking Top 3 | `student_performance` (todos), `class_reading` |
| `data-section="inactive-alerts"` | Alertas de inativos | `overview`, `student_performance` (todos), `class_reading` |
| `data-section="data-table"` | Tabela de dados | Todos exceto `overview` |

---

## 4. Legenda de Cores e Ícones

| Elemento | Cor/Ícone | Significado |
|----------|-----------|-------------|
| Borda azul | `#3b82f6` | Empréstimos, Alunos |
| Borda verde | `#10b981` | Devoluções, Livros Lidos |
| Borda vermelha | `#ef4444` | Em Atraso, Multas |
| Borda âmbar | `#f59e0b` | Reservas, XP, Média |
| Variação positiva | `#16a34a` | Aumento em relação ao período anterior |
| Variação negativa | `#dc2626` | Queda em relação ao período anterior |
| 🥇 Ouro | `#f59e0b` | 1º lugar |
| 🥈 Prata | `#9ca3af` | 2º lugar |
| 🥉 Bronze | `#d97706` | 3º lugar |
| ⚠️ | — | Aluno sem leitura no período |