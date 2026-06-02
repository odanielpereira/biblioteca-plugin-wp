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

## Ciclo 4 — Versão 4.0.0 ← CONCLUÍDO

### Fase 8: Vitrine Pública e Página do Livro ← FASE CONCLUÍDA
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
    6.  [ ] Testar archive no ambiente WordPress e validar critérios de saída. (⚠️ Funcional; archive nativo /livros/ apresenta erro 404 com parâmetros de filtro — ver item 18 do Ciclo de Polimento)

#### Fase 8C-B — Correções Cirúrgicas (Segurança e Manutenção) ← CONCLUÍDA
*   **Descrição:** Correções identificadas na revisão de código antes de avançar para os Filtros Inteligentes.
*   **Critério de saída:** Nenhuma vulnerabilidade CSRF conhecida, código duplicado eliminado, experiência visual consistente entre single e archive.
*   **Tarefas:**
    1.  [x] Adicionar `check_ajax_referer` no handler AJAX `bm_search_book_cover` e incluir nonce no script jQuery inline.
    2.  [x] Unificar funções duplicadas `bm_fetch_cover_from_google` e `bm_search_book_cover` — extrair núcleo comum de busca em 5 níveis, manter wrappers com assinaturas originais.
    3.  [x] Adicionar placeholder visual para livros sem capa no `single-bm_book.php` (coerência com `archive-bm_book.php`).

#### Fase 8D — Filtros Inteligentes na Vitrine
*   **Tarefas:**
    1.  [x] Dropdowns de gênero e categoria no archive.
    2.  [x] Campo de busca textual (título, autor, sinopse).
    3.  [x] Filtros via pre_get_posts no front-end.
    4.  [ ] Manter filtros ao navegar entre páginas. → Ciclo de Polimento item 18
    5.  [ ] Arquitetura extensível para receber bm_discipline e faixa etária no futuro. → Ciclo de Polimento item 18

#### Fase 8E — Vitrine Visual ← CONCLUÍDA
*   **Tarefas:**
    1.  [x] Refinar CSS Grid com hover effects nos cards.
    2.  [x] Aumentar resolução das capas via Google Books API (zoom=2).
    3.  [x] Preparar hooks/ações para injeção futura de carrossel "Mais Lidos" e ranking "Top Leitores".
    4.  [x] Garantir responsividade completa (mobile, tablet, desktop).

#### Fase 8F — Busca Automática de Sinopse ← CONCLUÍDA
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

## Ciclo 5 — Versão 5.0.0 ← EM PLANEJAMENTO

### Fase 9: Usuários, Reservas e Empréstimos ← FASE ATIVA
*   **Objetivo:** Implementar sistema completo de perfis de usuário com 4 níveis hierárquicos, sistema de reservas com fila de espera e controle de empréstimos/devoluções.
*   **Critério de saída:** Alunos podem se cadastrar, fazer login, reservar livros e entrar na fila de espera. Gestores controlam empréstimos e devoluções. Sistema de estoque matemático funcional.

#### Fase 9A — Perfis de Usuário (Roles Customizadas)
*   **Tarefas:**
    1.  [x] Criar roles via add_role(): bm_student, bm_teacher, bm_librarian, bm_super_admin.
    2.  [x] Definir capabilities específicas para cada role.
    3.  [x] Atualizar bm_add_admin_caps() e bm_remove_admin_caps() no activation/uninstall.
    4.  [ ] Criar página de instalação (primeiro acesso): obriga criação do Super Admin + nome da escola. → Ciclo de Polimento
    5.  [ ] Portal de login com redirecionamento por perfil após autenticação. → Ciclo de Polimento
    6.  [x] Ajustar verificações de current_user_can('manage_options') para usar capabilities das novas roles.
    7.  [ ] Visibilidade configurável de campos administrativos por perfil. → Ciclo de Polimento item 22

#### Fase 9B — Autocadastro e Aprovação
*   **Tarefas:**
    1.  [x] Criar formulário de autocadastro (shortcode [bm_register]).
    2.  [x] Campos: nome, e-mail, senha, série/ano, disciplina, telefone/WhatsApp.
    3.  [x] Usuários criados com status pending (user meta bm_approval_status).
    4.  [x] Painel de aprovação no admin para Gestor e Super Admin.
    5.  [x] Registro de capabilities apenas após aprovação.
    6.  [ ] Campos dinâmicos conforme perfil (Aluno: série/ano; Professor: disciplinas). → Ciclo de Polimento item 23
    7.  [ ] Revisar hierarquia de perfis (bm_super_admin redundante com Administrator). → Ciclo de Polimento item 24
    8.  [ ] Centralizar menu de administração da biblioteca. → Ciclo de Polimento item 25

#### Fase 9C — Sistema de Reservas
*   **Tarefas:**
    1.  [x] Criar funções bm_reserve_book() e bm_cancel_reservation().
    2.  [x] Botão "Reservar" no archive, taxonomias e single.
    3.  [x] Usuário deslogado: modal "Faça login ou crie uma conta para poder reservar".
    4.  [x] Estudante: limite de 3 reservas/empréstimos ativos.
    5.  [x] Professor/Gestor/Admin: sem limite, pode reservar em nome de estudante.
    6.  [x] Lista de espera (fila): primeiro a reservar = primeiro da fila.
    7.  [x] Mensagem: "Reserva confirmada! Você é o Xº da lista de espera."
    8.  [ ] wp_cron para expirar reservas após 24h se não confirmadas. → Ciclo de Polimento
    9.  [x] Toggle Reservar/Cancelar: botão muda cor e texto conforme estado.
    10. [ ] Substituir todos os alert() restantes por modal. → Ciclo de Polimento item 26
    11. [ ] Ajustar tempo de exibição do modal (muito rápido). → Ciclo de Polimento item 26
    12. [ ] Melhorar interface de reserva para Professor/Gestor (dropdown de alunos). → Ciclo de Polimento item 28

#### Fase 9D — Empréstimos e Devoluções
*   **Descrição:** Gestor confirma retirada (reserva → empréstimo) e registra devolução.
*   **Tarefas:**
    1.  [ ] Função `bm_confirm_loan()`: transforma reserva ativa em empréstimo (14 dias).
    2.  [ ] Função `bm_return_book()`: registra devolução, atualiza estoque.
    3.  [ ] Prazos flexíveis: opção de alteração global ou manual.
    4.  [ ] Histórico de empréstimos por aluno (`_bm_loan_history`) e por livro.
    5.  [ ] Aba de tarefas do Gestor: alunos com mais de 14 dias de atraso.

#### Fase 9D — Empréstimos e Devoluções
*   **Descrição:** Gestor confirma retirada (reserva → empréstimo) e registra devolução.
*   **Tarefas:**
    1.  [x] Função bm_confirm_loan(): transforma reserva em empréstimo com prazo configurável.
    2.  [x] Função bm_return_book(): registra devolução, atualiza estoque e notifica próximo da fila.
    3.  [x] Função bm_undo_loan(): desfaz empréstimo, volta para estado reservado.
    4.  [x] Prazos flexíveis: campo de dias configurável (1-60) na confirmação.
    5.  [x] Histórico salvo em _bm_loan_history (usuário) e _bm_reservations (livro).
    6.  [x] Página "Empréstimos" no menu Livros com toggle visual (cores por estado).
    7.  [x] Destaque visual para atrasos (fundo rosado + texto vermelho).
    8.  [ ] Aba de tarefas do Gestor: alunos com mais de 14 dias de atraso. → Fase 9F

    #### Fase 9E — Controle de Estoque Matemático
*   **Descrição:** Exibição clara de exemplares totais, emprestados e disponíveis.
*   **Tarefas:**
    1.  [x] Função bm_get_stock_info($post_id): retorna total, emprestados, disponíveis, fila.
    2.  [x] Função bm_display_stock_info(): exibe bloco visual com cores por estado.
    3.  [x] Exibir no single do livro (visitantes veem disponibilidade).
    4.  [x] Atualizar em tempo real ao registrar empréstimo/devolução/reserva.
    5.  [x] Integrar com _bm_copies e _bm_borrowed_count para cálculo correto.
    6.  [ ] Melhorar clareza visual dos números de estoque. → Ciclo de Polimento item 29

#### Fase 9F — Integração com WhatsApp
*   **Descrição:** Botão WhatsApp Web com mensagens pré-programadas para Professor e Gestor.
*   **Tarefas:**
    1.  [ ] Função `bm_whatsapp_link($phone, $message)`: gera link wa.me.
    2.  [ ] Botão no perfil do aluno (visível para Professor e Gestor).
    3.  [ ] Mensagens pré-programadas: cobrança de devolução, notificação de reserva.
    4.  [ ] Integrar na aba de atrasos do Gestor.

#### Fase 9G — Dashboards por Perfil
*   **Descrição:** Interfaces personalizadas para cada role.
*   **Tarefas:**
    1.  [ ] Dashboard do Aluno: XP, medalhas, histórico, prazo de devolução.
    2.  [ ] Dashboard do Professor: monitoramento, WhatsApp, gerador de atividades.
    3.  [ ] Dashboard do Gestor: controle de fluxo, atrasos, gestão de acervo.
    4.  [ ] Painel do Super Admin: configuração da escola, aprovação de gestores, virada de ano.

---

## Ciclo de Polimento — Versão 4.5 ou 5.0 ← PLANEJADO

### Imagens de Capa (Fase 7D / Fase 8E)
1. Aumentar resolução das capas (trocar zoom=1 por zoom=2) — Google Books API. (Origem: Fase 7D — ✅ concluído na 8E)
2. Avaliar opção de hotlink (URL incorporada) vs download local para economizar espaço. (Origem: Fase 8E)
3. Placeholder para capas quebradas ou ausentes no single. (Origem: Fase 8E — ✅ concluído na 8C-B)

### Importação CSV (Fase 6A / Fase 7D / Fase 7G)
4. Checkbox "Buscar capas automaticamente" e "Buscar sinopses automaticamente" na importação, com aviso de que a operação ficará mais lenta. Permitir escolher um, outro ou ambos. (Origem: Fase 7D / Fase 8F)
5. Importação assíncrona para grandes arquivos (evitar timeout). (Origem: Fase 7D)
6. Melhorar detecção de título/autor (evitar que autor vire parte do título em snippets). (Origem: Fase 7D)

### Exportação CSV (Fase 6B / Fase 6C)
7. Aviso de sucesso pós-download na exportação ("X livros exportados"). (Origem: Fase 6C)

### Gerenciamento de Campos (Fase 7H)
8. Corrigir ordem do drag and drop que às vezes sai do lugar ao recarregar a página. (Origem: Fase 7H)
9. Permitir que campos fixos (ISBN, Localização, Exemplares) sejam removíveis/ocultáveis. (Origem: Fase 7H)
10. Criar página de configurações para API Key do Google Books e Gemini. (Origem: Fase 7D / Fase 8G)

### Interface e Usabilidade (Fase 7E / Fase 8B / Ciclo 2)
11. Diagnosticar e corrigir bulk action quebrado (mover vários livros para lixeira). (Origem: Ciclo 2)
12. Seleção individual de duplicados com checkbox na importação. (Origem: Fase 7E)
13. Layout visual das páginas públicas (aplicar protótipo do Stitch). (Origem: Fase 8B)

### Segurança e Performance (Fase 8C-B)
14. Revisão completa de nonces e sanitização. (Origem: Fase 8C-B — ✅ nonce AJAX corrigido)
15. Unificar bm_fetch_cover_from_google e bm_search_book_cover. (Origem: Fase 8C-B — ✅ unificada)

### Funcionalidades Adicionais (Fase 6A / Fase 8D / Fase 8E)
16. Relatório visual de importação: lista na tela com nomes dos livros, status colorido (verde = importado, vermelho = erro, amarelo = duplicado forçado, cinza = ignorado). (Origem: Fase 6A)
17. Ajustar responsividade das capas no archive: avaliar height: auto ou object-fit: contain para não cortar imagens em mobile. (Origem: Fase 8E)
18. Corrigir cruzamento de filtros no archive nativo (/livros/): permitir combinar gênero + busca textual (autor/título) sem erro 404, compatível com temas FSE. (Origem: Fase 8D)

### Integração IA (Fase 8G)
19. Obter chave API Gemini válida (formato AIza...) e testar classificação interdisciplinar. (Origem: Fase 8G)