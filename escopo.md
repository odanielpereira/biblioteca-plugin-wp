# ESCOPO.md — Plugin de Gestão de Livros para WordPress

## 1. IDENTIDADE DO PLUGIN
- **Nome:** Gestão de Livros
- **Slug:** `book-manager`
- **Text Domain:** `book-manager`
- **Prefixo de funções:** `bm_`
- **Prefixo de meta keys:** `_bm_`
- **Versão atual:** 8.1.0

## 2. REFERÊNCIA ÚNICA
- 100% do código deve seguir: https://developer.wordpress.org/
- Funções obrigatórias de consulta antes de implementar:
  - `register_post_type()` → https://developer.wordpress.org/reference/functions/register_post_type/
  - `add_meta_box()` → https://developer.wordpress.org/reference/functions/add_meta_box/
  - `update_post_meta()` → https://developer.wordpress.org/reference/functions/update_post_meta/
  - `get_post_meta()` → https://developer.wordpress.org/reference/functions/get_post_meta/
  - `current_user_can()` → https://developer.wordpress.org/reference/functions/current_user_can/
  - `wp_verify_nonce()` → https://developer.wordpress.org/reference/functions/wp_verify_nonce/
  - `wp_nonce_field()` → https://developer.wordpress.org/reference/functions/wp_nonce_field/
  - `sanitize_text_field()` → https://developer.wordpress.org/reference/functions/sanitize_text_field/
  - `register_activation_hook()` → https://developer.wordpress.org/reference/functions/register_activation_hook/
  - `register_deactivation_hook()` → https://developer.wordpress.org/reference/functions/register_deactivation_hook/
  - `register_uninstall_hook()` → https://developer.wordpress.org/reference/functions/register_uninstall_hook/
  - `wp_insert_post()` → https://developer.wordpress.org/reference/functions/wp_insert_post/
  - `wp_delete_post()` → https://developer.wordpress.org/reference/functions/wp_delete_post/
  - `add_submenu_page()` → https://developer.wordpress.org/reference/functions/add_submenu_page/
  - `wp_check_filetype()` → https://developer.wordpress.org/reference/functions/wp_check_filetype/
  - `wp_remote_get()` → https://developer.wordpress.org/reference/functions/wp_remote_get/
  - `fgetcsv()` → https://www.php.net/manual/pt_BR/function.fgetcsv.php
  - `wp_insert_user()` → https://developer.wordpress.org/reference/functions/wp_insert_user/
  - `wp_update_user()` → https://developer.wordpress.org/reference/functions/wp_update_user/
  - `get_users()` → https://developer.wordpress.org/reference/functions/get_users/
  - `add_role()` → https://developer.wordpress.org/reference/functions/add_role/
  - `wp_cron()` → https://developer.wordpress.org/reference/functions/wp_cron/
  - `wp_schedule_event()` → https://developer.wordpress.org/reference/functions/wp_schedule_event/
  - `get_plugin_data()` → https://developer.wordpress.org/reference/functions/get_plugin_data/
  - `get_bloginfo()` → https://developer.wordpress.org/reference/functions/get_bloginfo/
  - `email_exists()` → https://developer.wordpress.org/reference/functions/email_exists/
  - `sanitize_email()` → https://developer.wordpress.org/reference/functions/sanitize_email/
  - `wp_delete_user()` → https://developer.wordpress.org/reference/functions/wp_delete_user/
  - `wp_mail()` → https://developer.wordpress.org/reference/functions/wp_mail/
  - `register_rest_route()` → https://developer.wordpress.org/reference/functions/register_rest_route/

## 3. FUNCIONALIDADES EXISTENTES (CONSOLIDADO — Ciclos 1 a 8)

### 3.1 Custom Post Type
- **Slug do CPT:** `bm_book`
- `public` → true
- `supports` → title, thumbnail
- `capability_type` → `bm_book` com `map_meta_cap` → true
- `delete_with_user` → false, `menu_icon` → `dashicons-book`
- `rewrite` → `['slug' => 'livros']`
- `has_archive` → true

### 3.2 Taxonomias
- `bm_genre` (Gêneros) — hierárquica
- `bm_category` (Categorias) — hierárquica
- `bm_discipline` (Disciplinas) — hierárquica

### 3.3 Campos
- **Fixos:** `_bm_author`, `_bm_publisher`, `_bm_isbn`, `_bm_location`, `_bm_copies`
- **Número de Chamada:** `_bm_cdu`, `_bm_cutter`, `_bm_edition`, `_bm_volume`
- **Dinâmicos:** Criados pelo Gestor via interface, com tipo (texto curto/longo), prefixo `_bm_dynamic_`
- **Gerenciamento:** Renomear, reordenar (drag and drop), ocultar/mostrar, migração de dados ao renomear

### 3.4 Importação CSV
- Mapeamento dinâmico de colunas (Upload → Mapeamento → Processamento)
- Detecção de duplicados por Título + Autor + Editora
- Opção de pular ou forçar importação de duplicados
- Busca automática de capas durante importação (Google Books API)
- Busca automática de sinopse durante importação (Google Books API)
- Classificação por disciplina durante importação (IA — Groq)
- Geração de Número de Chamada durante importação (IA — Groq, com prioridade CSV > IA)
- Campos do Número de Chamada mapeáveis no CSV (Classificação, Cutter, Edição, Volume)

### 3.5 Exportação CSV
- Flexível: filtros dinâmicos (campo + operador + valor), múltiplos com E/OU
- Seleção de colunas para exportar (checkboxes)
- Nomes amigáveis para campos dinâmicos e taxonomias

### 3.6 Capa do Livro
- Upload manual via thumbnail
- Busca automática via Google Books API com 5 níveis hierárquicos
- Fallback em cascata: ISBN → Título+Autor+Editora → Título+Autor → Título+Editora → Título
- Resolução aumentada: zoom=2

### 3.7 Auditoria
- Log de criação, edição, lixeira e restauração
- Exibido na metabox "Histórico de Ações"
- Soft delete nativo (wp_trash_post)

### 3.8 Permissões e Limpeza
- Acesso restrito a `manage_options`
- Activation: registra CPT + taxonomias + capabilities + flush
- Deactivation: flush apenas
- Uninstall: remove posts, meta keys, capabilities

### 3.9 Vitrine Pública
- Página individual do livro (single-bm_book.php) com controle de visibilidade por perfil
- Catálogo (archive-bm_book.php) com grid de capas, paginação e responsividade
- Filtros inteligentes: dropdowns de gênero e categoria, busca textual por título
- Vitrine visual com hover effects nos cards
- Placeholder para livros sem capa
- Hook para carrossel futuro (bm_after_catalog_grid)
- Número de Chamada visível para todos
- Resenhas e vídeo-resenhas dos leitores
- Disciplinas relacionadas com justificativas pedagógicas
- Atividades pedagógicas visíveis para Professor/Gestor/Admin

### 3.10 Sinopse e Classificação por IA
- Busca automática de sinopse via Google Books API
- Botão "Buscar Sinopse" na edição + integração na importação CSV
- Taxonomia bm_discipline com metabox de checkboxes
- Classificação por disciplina via Groq (análise binária Sim/Não com justificativas)
- Cache de resultados via _bm_ai_classified
- Exibição na página do livro (pills azuis + justificativas)

### 3.11 Central de APIs
- Página "APIs e Configurações" no menu Livros
- Campos: Google Books API Key, Groq API Key
- Checkbox de ativar/desativar IA
- Indicador visual de IA ativa

### 3.12 Gerador de Atividades por IA
- Botão "Gerar Atividades" na edição e na vitrine do livro
- Integração com Groq (Llama 3.3 70B Versatile)
- Atividades salvas em _bm_activities com cache
- Exibição na metabox "Atividades Pedagógicas (IA)" e na página pública
- Acesso: Professor, Gestor e Admin

### 3.13 Número de Chamada (CDU + Cutter)
- Metabox "Número de Chamada" com Classificação, Cutter, Volume, Edição, Exemplares
- Autor formatado automaticamente (SOBRENOME, Nome) padrão AACR2
- Geração por IA via Groq seguindo manual UFSM (Cutter-Sanborn)
- Sistema de bloqueio/desbloqueio de edição com aviso de segurança
- Histórico de versões com restauração
- Resolução de conflitos de Cutter (sufixo numérico)
- Integração na importação CSV com prioridade: CSV > IA > Manual
- Rótulo "Classificação" neutro para CDU/CDD
- Exibição na vitrine como "Número de Chamada"

### 3.14 Geração de Etiquetas
- Página "Etiquetas" no menu Livros com carrinho persistente via sessão PHP
- Seleção de livros por checkboxes com filtros (busca, gênero, disciplina, classificação)
- Botão "Adicionar etiqueta" na página individual do livro (Gestor/Admin)
- Visualização de impressão em nova aba com grid A4 (3×8 = 24 etiquetas/folha)
- Layout: autor, título, classificação, cutter, edição, exemplar, código de barras ISBN
- Suporte a múltiplos exemplares (Ex. 1/56)
- CSS @media print para impressão direta

### 3.15 Chatbot da Biblioteca
- Botão flutuante 💬 no canto inferior direito do site
- Integração com Groq para responder sobre acervo, disponibilidade e recomendações
- Prompt inclui catálogo com títulos, autores, localização e disponibilidade
- Funciona para visitantes e logados via AJAX
- Não revela dados pessoais de alunos

### 3.16 Gamificação e Engajamento
- Ranking de leitores (`[bm_ranking]`) por período (semana, mês, bimestre, ano)
- Ficha de leitura (`[bm_reading_log]`) com nota (estrelas), resenha e vídeo-resenha
- Resenha oficial do Gestor/Admin com destaque visual
- XP e Medalhas automáticas (Rato de Biblioteca, Leitor Voraz, Mestre das Ciências, Crítico de Cinema)
- Dashboard do aluno com XP e medalhas

### 3.17 Usuários e Perfis
- 4 roles customizadas: bm_student (Aluno), bm_teacher (Professor), bm_librarian (Gestor), Administrator (Super Admin)
- Autocadastro com aprovação pendente
- Dashboard por perfil (Aluno, Professor, Gestor)
- Sistema de reservas com fila de espera e limite configurável
- Empréstimos com prazo configurável (0-60 dias) e contador regressivo (4 cores)
- Controle de estoque matemático
- WhatsApp com mensagens pré-programadas e contador de envios

### 3.18 Campos Dinâmicos para Alunos
- Interface unificada com abas: "Campos de Livros" e "Campos de Alunos"
- Prefixo `_bm_user_` para campos dinâmicos de alunos
- Tipos: texto curto, texto longo, e-mail
- Drag and drop, renomear, ocultar/mostrar, migração de dados ao renomear
- Campos pré-instalados na ativação: Nome completo, E-mail, Telefone (bloqueados), Série/Ano, Turno, Turma

### 3.19 Importação de Alunos em Massa
- Subpágina "Importar Alunos" com fluxo Upload → Mapeamento → Processamento
- Mapeamento dinâmico com campos `_bm_user_*`
- Detecção de duplicados por e-mail
- Opção: approved ou pending
- Relatório: X importados, Y ignorados, Z duplicados

### 3.20 Administração de Alunos
- Subpágina "Alunos" com listagem, filtros e ações em lote
- Página individual com cards, histórico de empréstimos, medalhas
- Exportar histórico do aluno via CSV
- Indicador visual de atraso, WhatsApp, observações internas

### 3.21 Atendimento (Empréstimo Rápido no Balcão)
- Tela unificada com busca de livro e aluno via AJAX
- Botões Emprestar, Devolver (com registro de danos), Renovar (+7 dias)
- Leitor de código de barras com foco automático para ISBN
- Modal de cadastro/edição rápida de aluno
- Cadastro de livro por ISBN via Google Books API
- Fila de espera visível e bloqueio por atraso

## 4. IMPORTAÇÃO CSV (Fase 6A) ← CONCLUÍDO (Ciclo 2)

> **Status:** Implementado e expandido nos Ciclos 3, 4 e 7 com mapeamento dinâmico, detecção de duplicados, busca de capas/sinopses, classificação por IA e número de chamada. Ver changelog entradas 42-49, 65, 98.

### 4.1 Interface
- Subpágina "Importar CSV" dentro do menu "Livros" (`add_submenu_page`)
- Slug: `bm_csv_import`
- Acesso restrito a `manage_options` e `edit_bm_books`
- Formulário com campo de upload de arquivo `.csv`
- Nonce de segurança no formulário

### 4.2 Processamento
- Delimitador: `;` (ponto e vírgula)
- Codificação esperada: UTF-8
- Cabeçalho: primeira linha lida para mapeamento dinâmico
- Título é obrigatório. Linhas sem título são ignoradas e contabilizadas.
- Sanitização: `sanitize_text_field()` em todos os campos
- Inserção: `wp_insert_post()` para criar o `bm_book` + `update_post_meta()` para metadados
- Status do post: `publish`
- Checkboxes opcionais: Classificação por IA, Número de Chamada, Capas, Sinopses

### 4.3 Relatório
- Após processamento, exibir:
  - "X livros importados com sucesso."
  - "Y linhas ignoradas (sem título)."
  - "Z duplicados pulados."
  - "W duplicados forçados."

## 5. EXPORTAÇÃO CSV (Fase 6B) ← CONCLUÍDO (Ciclo 2)

> **Status:** Implementado e expandido no Ciclo 3 com exportação flexível (filtros dinâmicos, seleção de colunas, combinação E/OU). Ver changelog entradas 50-57.

### 5.1 Interface
- Subpágina "Exportar CSV" dentro do menu "Livros"
- Acesso restrito a `manage_options` e `edit_bm_books`

### 5.2 Geração do CSV
- Delimitador: `;`
- Codificação: UTF-8 com BOM
- Cabeçalho na primeira linha
- Usar `get_posts()` para buscar todos os `bm_book`
- Saída: download forçado via headers PHP

## 6. AJUSTES DE USABILIDADE (Fase 6C) ← CONCLUÍDO (Ciclo 2)

> **Status:** Implementado. Ver changelog entradas 48-49.

### 6.1 Aviso na Exportação
### 6.2 Detecção de Duplicados na Importação
### 6.3 Confirmação Pré-Importação
### 6.4 Relatório Detalhado

## 7. EXPANSÃO DA FICHA CATALOGRÁFICA (Fases 7A-7H) ← CONCLUÍDO (Ciclo 3)

> **Status:** Ciclo 3 concluído. Ver changelog entradas 50-61.

### 7.1 Campos Fixos de Catalogação (7A) ✅
### 7.2 Campos Dinâmicos (7B) ✅
### 7.3 Taxonomias (7C) ✅
### 7.4 Capa do Livro (7D) ✅
### 7.5 Exportação Flexível (7E) ✅
### 7.6 Soft Delete e Auditoria (7F) ✅
### 7.7 Mapeamento Dinâmico de Colunas (7G) ✅
### 7.8 Gerenciamento de Campos (7H) ✅

## 8. CICLO 4 — VITRINE PÚBLICA E PÁGINA DO LIVRO ← CONCLUÍDO

> **Status:** Ciclo 4 concluído. Ver changelog entradas 63-78.

### 8.1 Tornar CPT Público (8A) ✅
### 8.2 Página Individual do Livro — Single (8B) ✅
### 8.3 Página de Catálogo — Archive (8C) ✅
### 8.4 Filtros Inteligentes na Vitrine (8D) ✅ (MVP parcial)
### 8.5 Vitrine Visual (8E) ✅
### 8.6 Busca Automática de Sinopse (8F) ✅
### 8.7 Classificação Interdisciplinar por IA (8G) ✅ (substituída pela 11A-B)

## 9. CICLO 5 — USUÁRIOS, RESERVAS E EMPRÉSTIMOS ← CONCLUÍDO

> **Status:** Ciclo 5 concluído. Ver changelog entradas 80-90.

### 9.1 Perfis de Usuário (9A) ✅
### 9.2 Autocadastro e Aprovação (9B) ✅
### 9.3 Sistema de Reservas (9C) ✅
### 9.4 Empréstimos e Devoluções (9D) ✅
### 9.5 Controle de Estoque Matemático (9E) ✅
### 9.6 Integração com WhatsApp (9F) ✅
### 9.7 Dashboards por Perfil (9G) ✅
### 9.8 Modularização (9H) ✅

## 10. CICLO 6 — GAMIFICAÇÃO E ENGAJAMENTO ← CONCLUÍDO

> **Status:** Ciclo 6 concluído. Ver changelog entradas 92-95.

### 10.1 Ranking de Leitores (10A) ✅
### 10.2 Ficha de Leitura (10B) ✅
### 10.3 Vídeo-Resenha e Resenha Oficial (10C) ✅
### 10.4 XP e Medalhas — Badges (10D) ✅
### 10.5 Central de APIs e Configurações (10E) ✅

## 11. CICLO 7 — FERRAMENTAS PEDAGÓGICAS ← CONCLUÍDO

> **Status:** Ciclo 7 concluído. Ver changelog entradas 96-99.

### 11.1 Gerador de Atividades por IA (11A) ✅
### 11.2 Classificação por Disciplina com IA (11A-B) ✅
### 11.3 Número de Chamada — CDU + Cutter (11B) ✅
### 11.4 Geração de Etiquetas (11C) ✅
### 11.5 Chatbot da Biblioteca (11E) ✅

## 12. CICLO 8 — INFRAESTRUTURA E CONFIGURAÇÕES ← CONCLUÍDO

> **Status:** Ciclo 8 concluído. Ver changelog entradas 100-129.

### 12.0 Requisitos de Segurança (OBRIGATÓRIO)
- **Configurações:** Acesso restrito a `manage_options` (apenas Admin)
- **Virada de ano:** Confirmação dupla antes de executar ação irreversível
- **Permissões:** Revisão de capabilities — substituir `manage_options` por capabilities granulares onde aplicável

### 12.1 Página de Configurações (Fase 12A) ✅
- **Subpágina:** "Configurações" no menu Livros (acesso: Admin)
- **Campos:** Limites de reservas por aluno, máximo de empréstimos, prazo padrão de empréstimo (dias), prazo de reserva (horas)
- **Armazenamento:** `get_option('bm_settings')` — array associativo

### 12.2 White Label (Fase 12B) ✅
- **Nome da escola:** Substitui "Catálogo de Livros" no título
- **Logo:** Upload via WordPress media uploader
- **Texto do rodapé e URL do site da escola**

### 12.3 Virada de Ano Letivo (Fase 12C) ✅
- **Acesso:** Exclusivo Admin (manage_options)
- **Ações:** Backup automático antes da virada, arquivar rankings, resetar XP (opcional), limpar reservas, ativar recadastramento
- **Segurança:** Confirmação dupla com senha do admin

### 12.4 Limpeza de Código Morto (Fase 12D) — MOVIDO PARA CICLO DE POLIMENTO

### 12.5 Refinamentos de Sistema (Fase 12E) ✅
- **Centralizar menu:** Menu principal "Biblioteca" ✅
- **Criador de Taxonomias Dinâmicas:** Gestor cria suas próprias taxonomias via interface ✅
- **Configuração de limites por perfil:** Máximo de reservas e empréstimos por aluno → MOVIDO PARA CICLO DE POLIMENTO
- **Limpar roles sujas** na ativação do plugin ✅
- **Revisão de permissões:** Substituir `manage_options` por capabilities granulares → MOVIDO PARA CICLO DE POLIMENTO
- **Seletor CDU ou CDD** na central de configurações ✅
- **Visibilidade configurável** de campos administrativos por perfil ✅

### 12.6 Status e Diagnóstico (Fase 12F) — MOVIDO PARA CICLO DE POLIMENTO

### 12.7 Campos Dinâmicos para Alunos (Fase 12G) ✅
- Interface unificada com abas: "Campos de Livros" e "Campos de Alunos"
- Prefixo `_bm_user_` para campos dinâmicos de alunos
- Tipos: texto curto, texto longo, e-mail
- Drag and drop, renomear, ocultar/mostrar, migração de dados
- Campos pré-instalados: Nome completo, E-mail, Telefone (bloqueados), Série/Ano, Turno, Turma

### 12.8 Importação de Alunos em Massa (Fase 12H) ✅
- Subpágina "Importar Alunos" com fluxo Upload → Mapeamento → Processamento
- Mapeamento dinâmico com campos `_bm_user_*`
- Detecção de duplicados por e-mail
- Relatório detalhado

### 12.9 Dashboard e Cadastro de Alunos (Fase 12I) ✅
- Dashboard do aluno exibe campos dinâmicos e busca rápida
- Shortcode `[bm_register]` com perfil primeiro e campos condicionais
- Trava de recadastramento pós-virada
- Professor vê dados do aluno em modo leitura

### 12.10 Administração de Alunos (Fase 12J) ✅
- Subpágina "Alunos" com listagem, filtros e ações em lote
- Página individual com histórico, XP, medalhas, exportação CSV
- Indicador visual de atraso, WhatsApp, observações internas

### 12.11 Atendimento — Empréstimo Rápido no Balcão (Fase 12K) ✅
- Tela unificada com busca livro/aluno via AJAX
- Emprestar, Devolver (com danos), Renovar
- Leitor de código de barras, cadastro rápido de aluno/livro
- Fila de espera visível e bloqueio por atraso

## 13. CICLO 9 — POLIMENTO (EM ANDAMENTO)

### 13.1 Limpeza de Código Morto (Fase 14)
- **Acesso:** Admin
- **Arquivos:** `admin.php`, `book-manager.php`, `frontend.php`
- **Tarefas:** Remover bloco FASE 8G (Gemini), `bm_deepseek_request()`, `bm_super_admin`, constantes wp-config, código órfão
- **Teste:** Verificar todas as funcionalidades após remoção

### 13.2 Performance, Auditoria e uninstall (Fase 15)
- **Acesso:** Admin
- **Arquivos:** `uninstall.php`, `users.php`, `book-manager.php`
- **Auditoria expandida:** `bm_log_admin_action()` para ações de alunos (aprovar, suspender, excluir)
- **Armazenamento:** `bm_admin_audit_log` (option, array com últimos 100 registros)
- **Performance:** Wrapper `bm_get_cached()` / `bm_set_cached()` para transients. Substituir `get_posts()` sem limite por queries paginadas nos dashboards (limite 20 + link "Ver todos")
- **Funções obrigatórias:** `get_transient()`, `set_transient()`

### 13.3 Gerenciar Campos e Taxonomias (Fase 16)
- **Acesso:** Admin e Gestor
- **Arquivos:** `admin.php`
- **Tarefas:** Campos fixos removíveis/ocultáveis, campos por perfil, unificar Classificação, ordem no modal de Atendimento

### 13.4 Status, Diagnóstico e Configurações (Fase 17)
- **Acesso:** Admin (Status, Configurações), Admin e Gestor (Permissões)
- **Arquivos:** `admin.php`
- **Subpágina:** "Status" (slug: `bm_status`)
- **Funções:** `bm_get_system_status()`, `bm_get_groq_usage()`, `bm_get_error_log()`
- **Armazenamento:** `bm_groq_call_count` (option), `bm_groq_call_log` (option), `bm_error_log` (option)
- **Limites por perfil:** Expandir `bm_get_settings()` com `per_profile_limits`
- **Permissões do Gestor:** Interface com checkboxes para cada funcionalidade
- **Funções obrigatórias:** `get_plugin_data()`, `get_bloginfo()`, `phpversion()`, `ini_get()`

### 13.5 Listagem, Menu e Usabilidade (Fase 18)
- **Acesso:** Admin e Gestor
- **Arquivos:** `admin.php`
- **Tarefas:** Corrigir bulk action, organizar menu com abas/telas unificadas

### 13.6 Importação e Exportação CSV (Fase 19)
- **Acesso:** Admin e Gestor
- **Arquivos:** `admin.php`
- **Tarefas:** Checkbox Google Books API, importação assíncrona, barra de progresso, relatório visual, detecção de título/autor, aviso pós-download, seleção individual de duplicados, coluna de vídeo-resenha, importação dedicada de Número de Chamada

### 13.7 Capas, Filtros, IA e APIs (Fase 20)
- **Acesso:** Admin (APIs), Todos (filtros, capas), Admin/Gestor (IA)
- **Arquivos:** `frontend.php`, `archive-bm_book.php`
- **Tarefas:** Hotlink vs download, responsividade, cruzamento de filtros, refatorar constantes, prompt IA, persona/tom, chatbot configurável, preenchimento por ISBN, avaliação Google Books, livros relacionados

### 13.8 Páginas Públicas (Fase 21)
- **Acesso:** Todos
- **Arquivos:** `archive-bm_book.php`, `single-bm_book.php`
- **Tarefas:** Placeholder capas (✅), layout Stitch, resenhas aprovadas no single

### 13.9 Central de Exportar/Importar Tudo (Fase 22)
- **Acesso:** Admin e Gestor
- **Subpágina:** "Exportar/Importar Dados" (slug: `bm_data_io`)
- **Arquivos:** `admin.php`
- **Módulos exportáveis:** Livros (CSV), Alunos (CSV), Histórico de empréstimos (CSV), Fichas de leitura (CSV), Taxonomias (CSV), Configurações (JSON)
- **Exportar:** Checkboxes por módulo + "Tudo" + formato (CSV/XML). ZIP com múltiplos arquivos.
- **Importar:** Upload de ZIP ou CSV individual + preview + mapeamento dinâmico
- **Funções:** `bm_add_data_io_page()`, `bm_export_data()`, `bm_import_data()`
- **Geração de ZIP:** `ZipArchive` (nativo PHP)
- **Funções obrigatórias:** `ZipArchive`, `wp_upload_dir()`
- **Barreiras:** ❌ Não exportar senhas (`user_pass` nunca incluso)

### 13.10 Sistema de Multas (Fase 23)
- **Acesso:** Admin e Gestor (configurar), Aluno (consultar próprias)
- **Arquivos:** `admin.php`, `users.php`, `frontend.php`
- **Armazenamento:** `bm_penalty_rules` (option), `_bm_penalties` (user_meta), `_bm_penalty_active` (user_meta 0/1), `_bm_penalty_until` (user_meta date)
- **Regras:** Tipo (advertência/suspensão/valor), progressão (1ª vez = X, 2ª vez = Y), moeda (BRL)
- **Funções:** `bm_calculate_penalty()`, `bm_apply_penalty()`, `bm_check_penalty_block()`, `bm_display_penalties()`
- **Integração:** `bm_return_book()` calcula multa se atraso, `bm_ajax_service_loan()` verifica bloqueio, página do aluno (12J) exibe multas, WhatsApp notifica
- **Barreiras:** ❌ Não integrar gateways de pagamento reais

### 13.11 Empréstimos, Reservas e WhatsApp (Fase 24)
- **Acesso:** Aluno (renovação própria), Professor/Gestor (todas)
- **Arquivos:** `users.php`
- **Renovação online:** Botão "Renovar" no dashboard do aluno. Regra: só se não houver fila de espera. +7 dias a partir da data atual.
- **Notificações por e-mail:** `bm_send_email_notification()` wrapper para `wp_mail()`. Tipos: `overdue`, `due_today`, `overdue_alert`, `reservation_available`, `penalty_applied`.
- **Armazenamento:** `bm_email_settings` (option)
- **Funções obrigatórias:** `wp_mail()`

### 13.12 Funcionalidades para Biblioteca Escolar (Fase 25)
- **Acesso:** Professor (reserva, lista, relatório), Gestor/Admin (todas)
- **Arquivos:** `users.php`, `admin.php`
- **Reserva antecipada:** `bm_add_bulk_reservation()` — Professor reserva lote para data futura. Armazenamento: `_bm_bulk_reservation` (post_meta)
- **Lista de leitura:** `bm_add_reading_list()` — Professor cria lista por turma. Armazenamento: `_bm_reading_list` (option)
- **Relatório de turma:** `bm_get_class_report($group, $period)`
- **Painel aniversariantes:** `bm_get_birthdays($month)` no dashboard do Gestor
- **Empréstimo entre bibliotecas:** `bm_set_library_unit()` — campo "Unidade" no livro. Armazenamento: `_bm_library_unit` (post_meta)
- **Barreiras:** ❌ Não integrar sistemas externos de matrícula

### 13.13 Funcionalidades para Qualquer Biblioteca (Fase 26)
- **Acesso:** Todos logados (sugestão), Todos (catálogo, compartilhar, acessibilidade), Gestor/Admin (estatísticas, inventário)
- **Arquivos:** `users.php`, `frontend.php`, `single-bm_book.php`, `archive-bm_book.php`
- **Sugestão de aquisição:** `bm_add_acquisition_suggestion()`. Armazenamento: `_bm_acquisition_suggestions` (option)
- **Catálogo avançado:** Filtros expandidos (idioma, ano, faixa etária)
- **Redes sociais:** Botões de compartilhamento no single
- **Acessibilidade:** Modo alto contraste/fonte aumentada (CSS toggle)
- **API pública (opcional):** `register_rest_route('bm/v1', '/books')` — ativar/desativar nas Configurações. Sem autenticação, sem dados pessoais.
- **Estatísticas:** `bm_get_library_stats($period)`. Cache em `bm_stats_cache` (option)
- **Checklist de inventário:** `bm_inventory_check($post_id)`. Armazenamento: `_bm_inventory_check` (post_meta date)
- **Funções obrigatórias:** `register_rest_route()`
- **Barreiras:** ❌ API não expor dados pessoais, ❌ sem CDN para fontes

### 13.14 Dashboards, Perfis e Gamificação (Fase 27)
- **Acesso:** Aluno (seu dashboard), Professor (monitoramento), Gestor/Admin (todos)
- **Arquivos:** `users.php`, `single-bm_book.php`
- **Tarefas:** Substituir alert() por modal, seletor de período, ranking no dashboard, filtros no ranking, perfil público, vitrine de resenhas, curadoria, `[bm_top_books]`, dashboard enriquecido, design system, XP por ficha, XP manual, link Minhas Fichas, duplicação Nome/E-mail

### 13.15 Vídeo e Embed (Fase 28)
- **Acesso:** Todos (visualização), Admin/Gestor (importação)
- **Arquivos:** `users.php`, `single-bm_book.php`
- **Tarefas:** Vídeo-resenhas via CSV, Instagram Reels, correção TikTok/Instagram embed

### 13.16 Etiquetas e Número de Chamada (Fase 29)
- **Acesso:** Admin e Gestor
- **Arquivos:** `admin.php`, `frontend.php`
- **Tarefas:** Reordenação configurável do Número de Chamada, layout A4 27 etiquetas

### 13.17 Página de Instalação e Identidade Visual (Fase 30)
- **Acesso:** Admin (primeiro acesso)
- **Arquivos:** `admin.php`, `book-manager.php`
- **Página de instalação:** Obriga criação do Super Admin + nome da escola no primeiro acesso. Autodestrói após uso.
- **API Keys:** (✅ concluído)

### 13.18 Sistema de Relatórios (Fase 31)
- **Acesso:** Gestor/Admin (administrativos), Professor (turma), Aluno (próprio)
- **Subpágina:** "Relatórios" (slug: `bm_reports`)
- **Arquivos:** `admin.php` (novo módulo `includes/reports.php` opcional)
- **Motor:** `bm_generate_report($args)` — parâmetros: tipo, sujeito, período, filtros, formato
- **Tipos pré-definidos:** Histórico do aluno, Leitura por turma, Visão geral, Multas ativas, Ranking por gênero, Relatório configurável
- **Visualização:** Tabelas HTML + gráficos (Chart.js inline ou CSS puro)
- **PDF:** TCPDF incluso como arquivo único no plugin
- **Interface:** Dropdown tipo, seletor período, filtros dinâmicos, botão Gerar + Exportar PDF
- **Funções obrigatórias:** `get_users()`, `get_posts()`, `wp_localize_script()`
- **Barreiras:** ❌ Sem CDN para Chart.js, ❌ sem serviços externos de PDF, ❌ sem tabelas customizadas

## 14. NOVAS META KEYS E OPÇÕES (CICLO 9)

| Meta Key / Option | Tipo | Fase |
|----------|------|------|
| `bm_penalty_rules` | Option (array) | 23 |
| `_bm_penalties` | User meta (array) | 23 |
| `_bm_penalty_active` | User meta (0/1) | 23 |
| `_bm_penalty_until` | User meta (date) | 23 |
| `_bm_bulk_reservation` | Post meta (array) | 25 |
| `_bm_reading_list` | Option (array) | 25 |
| `_bm_library_unit` | Post meta (texto) | 25 |
| `_bm_acquisition_suggestions` | Option (array) | 26 |
| `bm_stats_cache` | Option (array) | 26 |
| `_bm_inventory_check` | Post meta (date) | 26 |
| `bm_admin_audit_log` | Option (array) | 15 |
| `bm_email_settings` | Option (array) | 24 |

## 15. BARREIRAS DO ESCOPO (Proibido)
- ❌ Alterar a estrutura do CPT existente
- ❌ Modificar os hooks de activation/deactivation/uninstall
- ❌ Usar bibliotecas externas (Laravel-Excel, PhpSpreadsheet, etc.)
- ❌ Criar tabelas customizadas no banco (usar post meta e user meta)
- ❌ Shortcodes, widgets ou blocos Gutenberg (para a vitrine)
- ❌ REST API endpoints customizados (exceto Fase 26, opcional e configurável)
- ❌ Qualquer dependência de composer, npm ou CDN externo
- ❌ Não usar CDN para Chart.js ou qualquer biblioteca JS (incluir no plugin)
- ❌ Não exportar senhas de usuários em nenhum formato
- ❌ API pública não expor dados pessoais de alunos
- ❌ Não integrar gateways de pagamento reais
- ❌ TCPDF deve ser incluído como arquivo único no plugin (sem composer)

## 16. SEGURANÇA (OBRIGATÓRIO)

### 16.1 Nonces — Formulários e Handlers AJAX
Todo formulário administrativo e toda requisição AJAX devem conter nonce de verificação.

**Formulários com nonce:**
- Detalhes do livro (`bm_save_book_details`)
- Resenha oficial (`bm_official_review_nonce`)
- Importação CSV livros (`bm_csv_import_action`)
- Exportação CSV livros (`bm_csv_export_action`)
- Gerenciar campos (`bm_dynamic_action`)
- Importação alunos CSV (`bm_student_import_action`)
- Importação Nº Chamada (`bm_cn_import_action`)
- Exportação Nº Chamada (`bm_cn_export_action`)
- Taxonomias dinâmicas (`bm_taxonomy_action`)
- Empréstimos (`bm_loan_action`)
- Aprovação de cadastros (`bm_approval_action`)
- Aprovação de fichas (`bm_reading_action`)
- Alunos — ações em lote (`bm_students_action`)
- Detalhes do aluno (`bm_student_detail_action`)
- Autocadastro `[bm_register]` (`bm_register_action`)
- Recadastramento (`bm_recadastro_action`)
- Nº Chamada — metabox (`bm_call_number_nonce`)

**Handlers AJAX com nonce:**
- `bm_search_book_cover` (`bm_search_cover`)
- `bm_fetch_sinopse` (`bm_sinopse_nonce`)
- `bm_ai_classify` (`bm_ai_classify_nonce`)
- `bm_generate_activities` (`bm_activities_nonce`)
- `bm_chatbot` (`bm_chatbot_nonce`)
- `bm_reserve_book` / `bm_cancel_reservation` (`bm_reserve_nonce`)
- `bm_update_rating` (`bm_rating_nonce`)
- `bm_track_whatsapp` (`bm_whatsapp_nonce`)
- `bm_service_search_book` / `bm_service_search_student` (`bm_service_nonce`)
- `bm_service_loan` / `bm_service_return` / `bm_service_renew` (`bm_service_nonce`)
- `bm_service_quick_register` / `bm_service_edit_student` (`bm_service_nonce`)
- `bm_service_register_book_by_isbn` (`bm_service_nonce`)
- `bm_generate_call_number` / `bm_restore_call_number` (`bm_call_number_nonce`)

**AJAX sem nonce (dívida técnica — Pós-Polimento):**
- `bm_toggle_label` — adicionar/remover etiqueta
- `bm_print_labels` — visualização de impressão (verifica capability)
- `bm_quick_search` — busca rápida no dashboard

### 16.2 Verificação de Capabilities
Toda função administrativa deve verificar permissão antes de executar.

| Funcionalidade | Capability |
|---------------|-----------|
| Configurações, APIs, Virada de ano, Status, White label | `manage_options` (exclusivo Admin) |
| CRUD livros, Empréstimos, Atendimento, Alunos, CSV, Etiquetas, Taxonomias | `edit_bm_books` ou `manage_options` |
| Gerar atividades, Classificar com IA | `edit_bm_book` ou `manage_options` |
| Buscar capa/sinopse (Google Books) | `manage_options` (exclusivo Admin) |
| Reservar, Ficha de leitura | Logado (qualquer perfil) |
| Catálogo, Página do livro, Chatbot, Ranking | Público (sem restrição) |

### 16.3 Sanitização de Entrada
Todo dado externo deve ser sanitizado antes de processamento ou armazenamento.

| Tipo | Função |
|------|--------|
| Dados de `$_POST`/`$_GET` | `wp_unslash()` antes de `sanitize_text_field()` |
| Texto simples | `sanitize_text_field()` |
| E-mail | `sanitize_email()` |
| Texto longo | `sanitize_textarea_field()` |
| Inteiros | `absint()` |
| URL para banco | `esc_url_raw()` |
| HTML de APIs | `wp_kses_post()` |
| Upload CSV | `wp_check_filetype()` |

### 16.4 Escape de Saída
Todo dado exibido em HTML deve ser escapado no contexto correto.

| Contexto | Função |
|----------|--------|
| Texto em HTML | `esc_html()` |
| Atributos HTML | `esc_attr()` |
| URLs | `esc_url()` |
| Textarea | `esc_textarea()` |
| Texto traduzível com echo | `esc_html_e()` em vez de `_e()` |

### 16.5 Proteções Estruturais
- Todos os arquivos PHP iniciam com `defined('ABSPATH') || exit;`
- Todos os saves de metabox verificam `DOING_AUTOSAVE`
- Senhas nunca exportadas em CSV
- Senhas geradas via `wp_generate_password()` na importação
- Virada de ano letivo exige confirmação por escrito (`VIRADA {ano}`)
- Chatbot não revela dados pessoais de alunos
- API pública futura (Fase 26) não exporá dados pessoais
- Número de telefone acessível apenas por Gestor/Admin

### 16.6 Proibições de Segurança
- ❌ Formulário administrativo sem nonce
- ❌ Handler AJAX sem `check_ajax_referer()`
- ❌ `manage_options` onde capability granular é suficiente
- ❌ Dado de `$_POST`/`$_GET` sem sanitização
- ❌ Output HTML sem escape
- ❌ Senha em CSV de exportação
- ❌ Dado pessoal exposto em endpoint público
- ❌ Ação irreversível sem confirmação explícita
- ❌ Arquivo PHP acessível diretamente sem `ABSPATH`
- ❌ Usar `$_REQUEST` — sempre `$_POST` ou `$_GET` explícitos

## 17. PREMISSAS DE PERFORMANCE (OBRIGATÓRIO)

### 17.1 CSS e JavaScript
- Estilos repetidos devem ser extraídos para arquivo `book-manager.css` externo
- Scripts repetidos devem ser extraídos para arquivo `book-manager.js` externo
- Usar `wp_enqueue_style()` e `wp_enqueue_script()` para carregamento
- CSS/JS inline só para estilos verdadeiramente dinâmicos (ex: cores baseadas em opções)

### 17.2 Banco de Dados
- Listagens nunca usam `posts_per_page = -1` — sempre paginar (limite 20-50)
- Consultas pesadas devem usar `bm_get_cached()` / `bm_set_cached()` com transients
- Dashboards, rankings e relatórios obrigatoriamente usam cache (5 minutos padrão)

### 17.3 AJAX
- Usar AJAX apenas onde melhora a experiência do usuário
- Ações administrativas com AJAX: Confirmar, Devolver, Rejeitar, Desfazer, Renovar
- Não usar AJAX para carregamento inicial de páginas
- Todo handler AJAX deve verificar nonce e capability

### 17.4 Impacto Visual
- Cada funcionalidade nova deve considerar: isso adiciona HTML/CSS/JS pesado?
- Preferir soluções nativas do WordPress a bibliotecas externas
- Gráficos (Fase 31/32): usar CSS puro para gráficos simples, Chart.js local para complexos