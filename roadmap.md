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

#### Fase 8A — Tornar CPT Público ← CONCLUÍDA
*   **Descrição:** Alterar `public` para `true`, habilitar `has_archive` e `rewrite`. Manter `show_in_rest => false` por segurança.
*   **Tarefas:**
    1.  [x] Alterar `public` → true no registro do CPT.
    2.  [x] Adicionar `has_archive` → true.
    3.  [x] Adicionar `rewrite` → `['slug' => 'livros']`.
    4.  [x] Adicionar `show_in_rest` → false.
    5.  [x] Testar se as URLs `/livros/` e `/livros/nome-do-livro` funcionam.

#### Fase 8B — Página Individual do Livro (Single) ← CONCLUÍDA
*   **Descrição:** Criar template para exibir a ficha completa do livro, com controle de visibilidade por perfil.
*   **Tarefas:**
    1.  [x] Criar arquivo `single-bm_book.php` no tema ou plugin.
    2.  [x] Exibir capa, título, autor, editora, gêneros, sinopse para visitantes.
    3.  [x] Exibir ISBN, localização, exemplares e histórico de auditoria apenas para admin (`current_user_can('manage_options')`).
    4.  [x] Ocultar campos vazios.
    5.  [x] Layout responsivo.

#### Fase 8C — Página de Catálogo (Archive)
*   **Descrição:** Criar página de listagem com grid de capas.
*   **Tarefas:**
    1.  [x] Criar arquivo `archive-bm_book.php`.
    2.  [x] Grid de capas com título e autor.
    3.  [x] Paginação.
    4.  [x] Cada capa linka para a página individual.
    5.  [x] Layout responsivo.
    6.  [ ] Testar archive no ambiente WordPress e validar critérios de saída. (⚠️ Funcional com shortcode [bm_catalog]; archive nativo /livros/ apresenta erro 404 com parâmetros de filtro — ver item 18 do Ciclo de Polimento)


#### Fase 8C-B — Correções Cirúrgicas (Segurança e Manutenção)
*   **Descrição:** Correções identificadas na revisão de código antes de avançar para os Filtros Inteligentes.
*   **Critério de saída:** Nenhuma vulnerabilidade CSRF conhecida, código duplicado eliminado, experiência visual consistente entre single e archive.
*   **Tarefas:**
    1.  [x] Adicionar `check_ajax_referer` no handler AJAX `bm_search_book_cover` e incluir nonce no script jQuery inline.
    2.  [x] Unificar funções duplicadas `bm_fetch_cover_from_google` e `bm_search_book_cover` — extrair núcleo comum de busca em 5 níveis, manter wrappers com assinaturas originais.s
    3.  [x] Adicionar placeholder visual para livros sem capa no `single-bm_book.php` (coerência com `archive-bm_book.php`).

#### Fase 8D — Filtros Inteligentes na Vitrine
*   **Tarefas:**
    1.  [x] Dropdowns de gênero e categoria no archive.
    2.  [x] Campo de busca textual (título, autor, sinopse).
    3.  [x] Filtros via pre_get_posts no front-end.
    4.  [ ] Manter filtros ao navegar entre páginas.
    5.  [ ] Arquitetura extensível para receber bm_discipline e faixa etária no futuro.

#### Fase 8E — Vitrine Visual
*   **Tarefas:**
    1.  [x] Refinar CSS Grid com hover effects nos cards.
    2.  [x] Aumentar resolução das capas via Google Books API (zoom=2).
    3.  [x] Preparar hooks/ações para injeção futura de carrossel "Mais Lidos" e ranking "Top Leitores".
    4.  [x] Garantir responsividade completa (mobile, tablet, desktop)..

#### Fase 8F — Busca Automática de Sinopse
*   **Descrição:** Buscar sinopse via Google Books API e salvar como campo dinâmico.
*   **Tarefas:**
    1.  [x] Criar função bm_fetch_sinopse_from_google() reaproveitando a lógica unificada de busca.
    2.  [x] Botão "Buscar Sinopse" na tela de edição.
    3.  [x] Integrar na importação CSV.
    4.  [x] Exibir sinopse na página pública (single).

#### Fase 8G — Classificação Interdisciplinar por IA
*   **Tarefas:**
    1.  [x] Planejar taxonomia bm_discipline.
    2.  [x] Criar taxonomia bm_discipline com metabox na edição.
    3.  [x] Integrar chamada à API Gemini (código pronto, pendente chave válida).
    4.  [x] Implementar cache de resultados (_bm_ai_classified).
    5.  [ ] Obter chave API Gemini válida e testar funcionalidade. → Ciclo de Polimento item 19

---

## Ciclo de Polimento — Versão 4.5 ou 5.0 ← PLANEJADO

### Imagens de Capa
*   **Tarefas:**
    1.  [ ] Aumentar resolução das capas (trocar `zoom=1` por `zoom=2`) — Google Books API.
    2.  [ ] Avaliar opção de hotlink (URL incorporada) vs download local para economizar espaço.
    3.  [x] Placeholder para capas quebradas ou ausentes no archive. ⚠️ Pendente no single (ver Fase 8C-B).

### Importação CSV
*   **Tarefas:**
    4.  [ ] Checkbox "Buscar capas automaticamente" com aviso de tempo de importação.
    5.  [ ] Importação assíncrona para grandes arquivos (evitar timeout).
    6.  [ ] Melhorar detecção de título/autor (evitar que autor vire parte do título em snippets).

### Exportação CSV
*   **Tarefas:**
    7.  [ ] Aviso de sucesso pós-download na exportação ("X livros exportados").

### Gerenciamento de Campos
*   **Tarefas:**
    8.  [ ] Corrigir ordem do drag and drop que às vezes sai do lugar ao recarregar a página.
    9.  [ ] Permitir que campos fixos (ISBN, Localização, Exemplares) sejam removíveis/ocultáveis.
    10. [ ] Criar página de configurações para API Key do Google Books.

### Interface e Usabilidade
*   **Tarefas:**
    11. [ ] Diagnosticar e corrigir bulk action quebrado (mover vários livros para lixeira).
    12. [ ] Seleção individual de duplicados com checkbox na importação.
    13. [ ] Layout visual das páginas públicas (aplicar protótipo do Stitch).

### Segurança e Performance
*   **Tarefas:**
    14. [x] Revisão completa de nonces e sanitização. ⚠️ Pendente nonce no AJAX (ver Fase 8C-B).
    15. [x] Unificar `bm_fetch_cover_from_google` e `bm_search_book_cover` (ver Fase 8C-B).