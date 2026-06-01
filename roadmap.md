# Roadmap

## Ciclo 1 — Versão 1.0.0 ← CONCLUÍDO

### Fase 0: Planejamento e Estrutura de Governança ← FASE CONCLUÍDA
*   **Objetivo:** Estabelecer a "constituição" do projeto com documentos que definem o comportamento da IA, o escopo técnico, o log de atividades e o plano de desenvolvimento.
*   **Critério de saída:** Todos os documentos de governança (`claude.md`, `escopo.md`, `changelog.md`, `roadmap.md`) estão criados, revisados e versionados no repositório.
*   **Tarefas:**
    1.  [x] Definição do Escopo (`escopo.md`)
    2.  [x] Definição do Comportamento da IA (`claude.md`)
    3.  [x] Criação do Log de Atividades (`changelog.md`)
    4.  [x] Definição do Roadmap (`roadmap.md`)
    5.  [x] Envio para o repositório Git.
    6.  [x] Criação da tag `v0.1-planning-complete`.

### Fase 1: Estrutura Base e Custom Post Type (CPT) ← FASE CONCLUÍDA
*   **Objetivo:** Criar a fundação do plugin com slug `book-manager` e o CPT `bm_book` visível apenas para administradores.
*   **Critério de saída:** Um admin consegue ver o menu "Livros" no painel, adicionar um livro com título, e o livro aparece na listagem.

### Fase 2: Metaboxes e Campos Personalizados ← FASE CONCLUÍDA
*   **Objetivo:** Implementar a metabox para adicionar e editar detalhes do livro (Autor e Editora).

### Fase 4: Interface de Listagem e Visualização ← FASE CONCLUÍDA
*   **Objetivo:** Customizar a listagem nativa com colunas de Título, Autor, Editora e filtros.

### Fase 5: Desativação, Desinstalação e Limpeza ← FASE CONCLUÍDA
*   **Objetivo:** Garantir limpeza completa na desinstalação.

---

## Ciclo 2 — Versão 2.0.0 ← CONCLUÍDO

### Fase 6: Importação e Exportação CSV ← FASE CONCLUÍDA
*   **Fase 6A — Importação CSV** ✅
*   **Fase 6B — Exportação CSV** ✅
*   **Fase 6C — Ajustes de Usabilidade** ✅

---

## Ciclo 3 — Versão 3.0.0 ← CONCLUÍDO

### Fase 7: Expansão da Ficha Catalográfica ← FASE CONCLUÍDA
*   **Fase 7A — Campos Fixos de Catalogação** ✅
*   **Fase 7B — Campos Dinâmicos** ✅
*   **Fase 7C — Taxonomias** ✅
*   **Fase 7D — Capa do Livro** ✅
*   **Fase 7E — Exportação Flexível** ✅
*   **Fase 7F — Soft Delete e Auditoria** ✅
*   **Fase 7G — Mapeamento Dinâmico de Colunas** ✅
*   **Fase 7H — Gerenciamento de Campos** ✅

---

## Ciclo 4 — Versão 4.0.0 ← EM ANDAMENTO

### Fase 8: Vitrine Pública e Página do Livro ← FASE ATIVA
*   **Objetivo:** Abrir o acervo ao público com uma vitrine visual, página individual para cada livro e busca inteligente, garantindo a segurança dos dados sensíveis.
*   **Critério de saída:** Visitantes navegam pelo catálogo público, veem capas e informações básicas, filtram por gênero/categoria. Admin logado vê dados sensíveis adicionais.

#### Fase 8A — Tornar CPT Público
*   **Descrição:** Alterar `public` para `true`, habilitar `has_archive` e `rewrite`. Manter `show_in_rest => false` por segurança.
*   **Tarefas:**
    1.  [ ] Alterar `public` → true no registro do CPT.
    2.  [ ] Adicionar `has_archive` → true.
    3.  [ ] Adicionar `rewrite` → `['slug' => 'livros']`.
    4.  [ ] Adicionar `show_in_rest` → false.
    5.  [ ] Testar se as URLs `/livros/` e `/livros/nome-do-livro` funcionam.

#### Fase 8B — Página Individual do Livro (Single)
*   **Descrição:** Criar template para exibir a ficha completa do livro, com controle de visibilidade por perfil.
*   **Tarefas:**
    1.  [ ] Criar arquivo `single-bm_book.php` no tema ou plugin.
    2.  [ ] Exibir capa, título, autor, editora, gêneros, sinopse para visitantes.
    3.  [ ] Exibir ISBN, localização, exemplares e histórico de auditoria apenas para admin (`current_user_can('manage_options')`).
    4.  [ ] Ocultar campos vazios.
    5.  [ ] Layout responsivo.

#### Fase 8C — Página de Catálogo (Archive)
*   **Descrição:** Criar página de listagem com grid de capas.
*   **Tarefas:**
    1.  [ ] Criar arquivo `archive-bm_book.php`.
    2.  [ ] Grid de capas com título e autor.
    3.  [ ] Paginação.
    4.  [ ] Cada capa linka para a página individual.
    5.  [ ] Layout responsivo.

#### Fase 8D — Filtros Inteligentes na Vitrine
*   **Descrição:** Adicionar filtros por gênero, categoria, autor e busca textual.
*   **Tarefas:**
    1.  [ ] Dropdowns de gênero e categoria.
    2.  [ ] Campo de busca textual (título, autor, sinopse).
    3.  [ ] Filtros via `pre_get_posts` no front-end.
    4.  [ ] Manter filtros ao navegar entre páginas.

#### Fase 8E — Vitrine Visual
*   **Descrição:** Refinar o layout com grid de capas e hover.
*   **Tarefas:**
    1.  [ ] Grid de capas responsivo (CSS Grid).
    2.  [ ] Hover com informações básicas.
    3.  [ ] Preparar estrutura para futuro carrossel de "Mais Lidos".

#### Fase 8F — Busca Automática de Sinopse
*   **Descrição:** Buscar sinopse via Google Books API e salvar como campo dinâmico.
*   **Tarefas:**
    1.  [ ] Criar função `bm_fetch_sinopse_from_google()` reaproveitando a lógica da busca de capa.
    2.  [ ] Botão "Buscar Sinopse" na tela de edição.
    3.  [ ] Integrar na importação CSV.
    4.  [ ] Exibir sinopse na página pública.

#### Fase 8G — Classificação Interdisciplinar por IA (Planejamento)
*   **Descrição:** Planejamento para Ciclo 9/10. Conectar livros a disciplinas escolares via IA.
*   **Tarefas:**
    1.  [ ] Planejar taxonomia `bm_discipline`.
    2.  [ ] Planejar integração com API de IA (Gemini/ChatGPT).
    3.  [ ] Planejar cache de resultados.