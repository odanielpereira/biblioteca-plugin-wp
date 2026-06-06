# ESCOPO.md — Plugin de Gestão de Livros para WordPress

## 1. IDENTIDADE DO PLUGIN
- **Nome:** Gestão de Livros
- **Slug:** `book-manager`
- **Text Domain:** `book-manager`
- **Prefixo de funções:** `bm_`
- **Prefixo de meta keys:** `_bm_`
- **Versão atual:** 7.0.0

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

## 3. FUNCIONALIDADES EXISTENTES (CONSOLIDADO — Ciclos 1 a 7)

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
- Sistema de reservas com fila de espera e limite de 3 para estudantes
- Empréstimos com prazo configurável (0-60 dias) e contador regressivo (4 cores)
- Controle de estoque matemático
- WhatsApp com mensagens pré-programadas e contador de envios

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

## 12. CICLO 8 — INFRAESTRUTURA E CONFIGURAÇÕES ← EM PLANEJAMENTO

> **Status:** Ciclo 8 em planejamento. Foco em adaptabilidade, white label, virada de ano, limpeza de código e refinamentos.

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

### 12.5 Refinamentos de Sistema (Fase 12E)
- **Centralizar menu:** Menu principal "Biblioteca" ✅
- **Criador de Taxonomias Dinâmicas:** Gestor cria suas próprias taxonomias via interface
- **Configuração de limites por perfil:** Máximo de reservas e empréstimos por aluno
- **Limpar roles sujas** na ativação do plugin
- **Revisão de permissões:** Substituir `manage_options` por capabilities granulares
- **Seletor CDU ou CDD** na central de configurações
- **Visibilidade configurável** de campos administrativos por perfil

### 12.6 Status e Diagnóstico (Fase 12F)
- **Acesso:** Exclusivo Admin (`manage_options`)
- **Subpágina:** "Status" no menu Biblioteca (slug: `bm_status`)
- **Armazenamento:** Nenhum — apenas leitura de dados do sistema
- **Campos e funções:**
  - `bm_get_system_status()` — retorna array com: versão do plugin (`get_plugin_data()`), versão PHP (`phpversion()`), versão WordPress (`get_bloginfo('version')`), limite de memória (`ini_get('memory_limit')`), status das chaves API (`bm_get_api_keys()`)
  - `bm_get_groq_usage()` — contador de chamadas via `get_option('bm_groq_call_count')` e `get_option('bm_groq_call_log')`
  - `bm_get_error_log()` — últimos 20 registros de `get_option('bm_error_log')`
- **Interface:** Cards com métricas principais + tabela de logs
- **Funções WordPress obrigatórias:**
  - `get_plugin_data()` → https://developer.wordpress.org/reference/functions/get_plugin_data/
  - `get_bloginfo()` → https://developer.wordpress.org/reference/functions/get_bloginfo/
  - `get_option()` → https://developer.wordpress.org/reference/functions/get_option/
- **Barreiras:**
  - ❌ Não modificar wp-config.php
  - ❌ Não expor dados sensíveis (chaves API aparecem como "Configurada" / "Não configurada")

### 12.7 Campos Dinâmicos para Alunos (Fase 12G)
- **Acesso:** Admin e Gestor (`manage_options` ou `edit_bm_books`)
- **Armazenamento:** `get_option('bm_user_dynamic_fields')` — array associativo igual ao de livros
- **Meta keys:** Prefixo `_bm_user_` (ex: `_bm_user_serie`, `_bm_user_turno`)
- **Campos:** Tipos suportados: `text` (texto curto) e `textarea` (texto longo). Sem campos fixos obrigatórios — gestor define tudo
- **Funções:**
  - Adaptar `bm_render_dynamic_fields_page()` para suportar abas: "Campos de Livros" e "Campos de Alunos"
  - `bm_render_user_dynamic_fields_metabox()` — exibir na edição do usuário
  - `bm_save_user_dynamic_fields()` — hook em `edit_user_profile_update` e `personal_options_update`
- **Funções WordPress obrigatórias:**
  - `get_users()` → https://developer.wordpress.org/reference/functions/get_users/
  - `get_user_meta()` → https://developer.wordpress.org/reference/functions/get_user_meta/
  - `update_user_meta()` → https://developer.wordpress.org/reference/functions/update_user_meta/
  - `add_action('edit_user_profile', ...)` → https://developer.wordpress.org/reference/hooks/edit_user_profile/
  - `add_action('edit_user_profile_update', ...)` → https://developer.wordpress.org/reference/hooks/edit_user_profile_update/
- **Barreiras:**
  - ❌ Não criar tabelas customizadas
  - ❌ Não modificar a tabela `wp_users` — apenas `wp_usermeta`

### 12.8 Importação de Alunos em Massa (Fase 12H)
- **Acesso:** Admin e Gestor (`manage_options` ou `edit_bm_books`)
- **Subpágina:** "Importar Alunos" no menu Biblioteca (slug: `bm_student_import`)
- **Armazenamento:** `wp_users` (nativo) + `wp_usermeta` (meta keys com prefixo `_bm_`)
- **Fluxo:** Upload → Mapeamento → Processamento (idêntico ao de livros — Fase 6A/7G)
- **Colunas mapeáveis:**
  - Fixas: `user_login`, `display_name`, `user_email`, `user_pass`
  - Dinâmicas: prefixo `_bm_user_` (ex: `_bm_user_serie`, `_bm_user_turno`)
  - Meta nativa: `bm_student_group` (agrupamento de alunos)
- **Regras:**
  - Status: `bm_approval_status` = `'approved'` (direto) ou `'pending'`
  - Role padrão: `bm_student`
  - Detecção de duplicados por `user_email` (não por nome)
  - Relatório: X importados, Y ignorados (sem e-mail), Z duplicados
- **Funções WordPress obrigatórias:**
  - `wp_insert_user()` → https://developer.wordpress.org/reference/functions/wp_insert_user/
  - `email_exists()` → https://developer.wordpress.org/reference/functions/email_exists/
  - `fgetcsv()` → https://www.php.net/manual/pt_BR/function.fgetcsv.php
  - `sanitize_email()` → https://developer.wordpress.org/reference/functions/sanitize_email/
  - `sanitize_text_field()` → https://developer.wordpress.org/reference/functions/sanitize_text_field/
- **Barreiras:**
  - ❌ Não permitir importar Administradores ou Gestores via CSV
  - ❌ Não criar tabelas customizadas

### 12.9 Dashboard e Cadastro de Alunos (Fase 12I)
- **Acesso:** Aluno logado (dashboard próprio), Admin/Gestor (edição)
- **Armazenamento:** `wp_usermeta` (campos dinâmicos `_bm_user_*`)
- **Funções:**
  - `bm_student_dashboard_content()` — já existe, expandir para exibir campos dinâmicos preenchidos
  - `bm_register_form()` — já existe como shortcode `[bm_register]`, expandir para incluir campos dinâmicos do gestor
  - `bm_render_student_edit_page()` — página de edição de aluno no admin com: dados nativos (nome, e-mail) + campos dinâmicos + histórico de leitura/XP/medalhas
  - `bm_teacher_view_student()` — Professor vê dados do aluno em modo leitura (sem edição)
- **Funções WordPress obrigatórias:**
  - `get_user_meta()` → https://developer.wordpress.org/reference/functions/get_user_meta/
  - `update_user_meta()` → https://developer.wordpress.org/reference/functions/update_user_meta/
  - `get_users()` → https://developer.wordpress.org/reference/functions/get_users/
  - `wp_update_user()` → https://developer.wordpress.org/reference/functions/wp_update_user/
- **Barreiras:**
  - ❌ Professor NÃO pode editar dados de alunos (apenas leitura)
  - ❌ Não expor senhas em tela alguma

### 12.10 Administração de Alunos (Fase 12J)
- **Acesso:** Admin e Gestor (`manage_options` ou `edit_bm_books`)
- **Subpágina:** "Alunos" no menu Biblioteca (slug: `bm_students`)
- **Armazenamento:** `wp_users` + `wp_usermeta` (apenas leitura/atualização, sem criação de tabelas)
- **Funções:**
  - `bm_add_students_list_page()` — subpágina "Alunos"
  - `bm_render_students_list_page()` — tabela com:
    - Colunas: Nome, E-mail, Grupo, Status, XP, Empréstimos ativos
    - Filtros: grupo (`bm_student_group`), status (`bm_approval_status`), busca textual
    - Ações em lote: Aprovar, Suspender (mudar para `subscriber`), Excluir (`wp_delete_user()`)
  - `bm_render_student_detail_page()` — página individual do aluno com:
    - Dados cadastrais (nativos + dinâmicos)
    - Histórico de leitura (fichas, resenhas, vídeos)
    - XP e medalhas
    - Empréstimos ativos e histórico
- **Funções WordPress obrigatórias:**
  - `get_users()` → https://developer.wordpress.org/reference/functions/get_users/
  - `wp_delete_user()` → https://developer.wordpress.org/reference/functions/wp_delete_user/
  - `get_user_meta()` → https://developer.wordpress.org/reference/functions/get_user_meta/
  - `wp_update_user()` → https://developer.wordpress.org/reference/functions/wp_update_user/
- **Barreiras:**
  - ❌ Não usar `WP_List_Table` (simplicidade)
  - ❌ Não permitir exclusão do próprio usuário logado
  - ❌ Não permitir que Gestor exclua Admin

### 12.11 Atendimento — Empréstimo Rápido no Balcão (Fase 12K)
- **Acesso:** Admin e Gestor (`manage_options` ou `edit_bm_books`)
- **Subpágina:** "Atendimento" no menu Biblioteca (slug: `bm_service`)
- **Armazenamento:** Post meta (`_bm_reservations`, `_bm_borrowed_count`) + User meta (`_bm_loan_history`)
- **Meta keys novas:** `_bm_consulta_local` — checkbox no cadastro/edição do livro (0 ou 1)
- **Funções:**
  - `bm_add_service_page()` — subpágina "Atendimento"
  - `bm_render_service_page()` — interface unificada com:
    - **Busca de livro:** Campo com autocomplete AJAX (`bm_ajax_search_books()`), exibindo disponibilidade em tempo real
    - **Busca de aluno:** Campo com autocomplete AJAX (`bm_ajax_search_students()`), exibindo pendências, livros ativos, limite
    - **Botão "Emprestar":** Aplica regras existentes (`bm_confirm_loan()`), verifica estoque, exibe alerta se limite atingido ou atraso
    - **Botão "Devolver":** Chama `bm_return_book()`
    - **Indicador "Consulta local":** Se `_bm_consulta_local` = '1', exibe "📌 Consulta local — não pode sair"
    - **Modal de cadastro rápido:** Nome, e-mail, telefone + campos dinâmicos — cria usuário na hora
    - **Histórico rápido:** Últimos 3 livros lidos pelo aluno
    - **Leitor de código de barras:** Campo com foco automático para ISBN/ID, escaneia e preenche busca do livro
- **Funções WordPress obrigatórias:**
  - `wp_insert_user()` → https://developer.wordpress.org/reference/functions/wp_insert_user/
  - `get_users()` → https://developer.wordpress.org/reference/functions/get_users/
  - `get_post_meta()` → https://developer.wordpress.org/reference/functions/get_post_meta/
  - `update_post_meta()` → https://developer.wordpress.org/reference/functions/update_post_meta/
  - `wp_verify_nonce()` → https://developer.wordpress.org/reference/functions/wp_verify_nonce/
- **Barreiras:**
  - ❌ Não criar tabelas customizadas para fila de atendimento
  - ❌ Não usar WebSockets (atualização é via AJAX)
  - ❌ Não modificar a estrutura de `_bm_reservations`

## 12.12 NOVAS META KEYS E OPÇÕES (CICLO 8)

| Meta Key / Option | Tipo | Fase |
|----------|------|------|
| `_bm_user_*` | User meta (dinâmico) | 12G, 12H, 12I |
| `bm_student_group` | User meta (texto) | 12H, 12J |
| `_bm_consulta_local` | Post meta (0/1) | 12K |
| `bm_groq_call_count` | Option (inteiro) | 12F |
| `bm_groq_call_log` | Option (array) | 12F |
| `bm_error_log` | Option (array) | 12F |
| `bm_user_dynamic_fields` | Option (array) | 12G |

## 13. BARREIRAS DO ESCOPO (Proibido)
- ❌ Alterar a estrutura do CPT existente
- ❌ Modificar os hooks de activation/deactivation/uninstall
- ❌ Usar bibliotecas externas (Laravel-Excel, PhpSpreadsheet, etc.)
- ❌ Criar tabelas customizadas no banco (usar post meta e user meta)
- ❌ Shortcodes, widgets ou blocos Gutenberg (para a vitrine)
- ❌ REST API endpoints customizados
- ❌ Qualquer dependência de composer, npm ou CDN externo