# ESCOPO.md — Plugin de Gestão de Livros para WordPress

## 1. IDENTIDADE DO PLUGIN
- **Nome:** Gestão de Livros
- **Slug:** `book-manager`
- **Text Domain:** `book-manager`
- **Prefixo de funções:** `bm_`
- **Prefixo de meta keys:** `_bm_`
- **Versão atual:** 5.0.0

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

## 3. FUNCIONALIDADES EXISTENTES (CONSOLIDADO — Ciclos 1, 2, 3 e 4)

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
- **Dinâmicos:** Criados pelo Gestor via interface, com tipo (texto curto/longo), prefixo `_bm_dynamic_`
- **Gerenciamento:** Renomear, reordenar (drag and drop), ocultar/mostrar, migração de dados ao renomear

### 3.4 Importação CSV
- Mapeamento dinâmico de colunas (Upload → Mapeamento → Processamento)
- Detecção de duplicados por Título + Autor + Editora
- Opção de pular ou forçar importação de duplicados
- Busca automática de capas durante importação (Google Books API)
- Busca automática de sinopse durante importação (Google Books API)

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

### 3.10 Sinopse e Classificação por IA
- Busca automática de sinopse via Google Books API
- Botão "Buscar Sinopse" na edição + integração na importação CSV
- Taxonomia bm_discipline com metabox de checkboxes
- Integração com API Gemini para classificação interdisciplinar (código pronto, chave pendente)
- Cache de resultados via _bm_ai_classified

## 4. IMPORTAÇÃO CSV (Fase 6A) ← CONCLUÍDO (Ciclo 2)

> **Status:** Implementado e expandido nos Ciclos 3 e 4 com mapeamento dinâmico de colunas, detecção de duplicados, busca de capas e sinopses. Ver changelog entradas 42-49, 65.

### 4.1 Interface
- Subpágina "Importar CSV" dentro do menu "Livros" (`add_submenu_page`)
- Slug: `bm_csv_import`
- Acesso restrito a `manage_options`
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
- Acesso restrito a `manage_options`

### 5.2 Geração do CSV
- Colunas na ordem: Título, Autor, Editora
- Delimitador: `;`
- Codificação: UTF-8 com BOM
- Cabeçalho na primeira linha
- Usar `get_posts()` para buscar todos os `bm_book`
- Saída: download forçado via headers PHP

## 6. AJUSTES DE USABILIDADE (Fase 6C) ← CONCLUÍDO (Ciclo 2)

> **Status:** Implementado. Ver changelog entradas 48-49.

### 6.1 Aviso na Exportação
- Exibir mensagem: "X livros disponíveis para exportação."

### 6.2 Detecção de Duplicados na Importação
- Critério: Título + Autor + Editora
- Opção: "Pular" ou "Importar mesmo assim"

### 6.3 Confirmação Pré-Importação
- Prévia: "X livros serão importados. Y já existem."

### 6.4 Relatório Detalhado
- Importados, ignorados, duplicados pulados, duplicados forçados

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
### 8.4 Filtros Inteligentes na Vitrine (8D) ✅ (MVP parcial, cruzamento pendente)
### 8.5 Vitrine Visual (8E) ✅
### 8.6 Busca Automática de Sinopse (8F) ✅
### 8.7 Classificação Interdisciplinar por IA (8G) ✅ (código pronto, chave API pendente)

## 9. CICLO 5 — USUÁRIOS, RESERVAS E EMPRÉSTIMOS ← CONCLUÍDO

### 9.0 Requisitos de Segurança (OBRIGATÓRIO)
- **Hierarquia de acesso:** O sistema deve implementar 4 perfis de usuário com privilégios distintos.
- **Autenticação:** Login via portal centralizado. Autocadastro pendente de aprovação.
- **Dados sensíveis:** Contatos de alunos (telefone/WhatsApp) visíveis apenas para Professor e Gestor.
- **Nonces:** Todos os formulários e ações AJAX devem ter verificação de nonce.
- **Sanitização:** Todos os inputs devem passar por `sanitize_text_field()` ou equivalente.
- **Capabilities:** Cada role deve ter capabilities específicas — não usar `manage_options` para novos perfis.

### 9.1 Perfis de Usuário (Roles Customizadas)
- **Roles:**
  - `bm_student` (Aluno) — acesso básico
  - `bm_teacher` (Professor/Funcionário) — acesso pedagógico
  - `bm_librarian` (Gestor da Biblioteca) — acesso operacional
  - `bm_super_admin` (Super Administrador) — acesso total
- **Registro via `add_role()`** no activation hook
- **Remoção de roles** no uninstall
- **Página de instalação (primeiro acesso):** obriga criação do primeiro Super Admin e cadastro do nome da escola. Autodestrói após uso.
- **Portal de login:** redirecionamento por perfil após autenticação

### 9.2 Autocadastro e Aprovação
- **Formulário de autocadastro:** alunos e professores podem se registrar
- **Campos do cadastro:** nome, e-mail, senha, série/ano (aluno), disciplina (professor), telefone/WhatsApp
- **Status pendente:** cadastros ficam como `pending` até aprovação do Gestor
- **Aprovação:** Gestor ou Super Admin aprovam via painel admin
- **Notificação:** e-mail ou mensagem após aprovação

### 9.3 Sistema de Reservas
- **Regras de reserva:**
  - Estudante (`bm_student`): pode reservar até 3 livros simultaneamente
  - Professor, Gestor e Super Admin: podem reservar quantos quiserem, inclusive em nome de um estudante
- **Botão "Reservar"** visível em todas as páginas públicas (archive, taxonomias, single)
- **Usuário deslogado:** vê o botão, ao clicar recebe mensagem: "Faça login ou crie uma conta para poder reservar"
- **Usuário logado (estudante):** reserva válida se tiver menos de 3 reservas/empréstimos ativos
- **Prazo da reserva:** 24 horas. Se não houver retirada física, expira automaticamente via wp_cron
- **Reserva para terceiros:** Professor/Gestor pode reservar para um aluno e notificá-lo
- **Lista de espera (fila):**
  - Múltiplos alunos podem reservar o mesmo livro
  - Ordem: primeiro a reservar = primeiro da fila
  - Livros emprestados também podem ser reservados
  - Mensagem ao reservar: "Reserva confirmada! Você é o Xº da lista de espera."
  - Quando o livro é devolvido, o próximo da fila é notificado

### 9.4 Empréstimos e Devoluções
- **Confirmação de retirada:** Gestor transforma reserva ativa em empréstimo
- **Prazo padrão:** 14 dias para empréstimo
- **Prazos flexíveis:** opção de alteração global (recessos/férias) ou manual por caso
- **Devolução:** Gestor registra devolução, atualiza estoque, notifica próximo da fila
- **Histórico:** registro de empréstimos por aluno e por livro
- **Atrasos:** aba de tarefas do Gestor destaca alunos com mais de 14 dias

### 9.5 Controle de Estoque Matemático
- **Exibição clara:** total de exemplares, quantos emprestados, quantos disponíveis
- **Atualização em tempo real:** ao registrar empréstimo/devolução
- **Exibição no single:** visitantes veem disponibilidade, alunos veem botão de reserva

### 9.6 Integração com WhatsApp
- **Botão WhatsApp Web:** abre chat com mensagem pré-programada
- **Disponível para:** Professor e Gestor (contato com alunos)
- **Mensagens pré-programadas:** cobrança de devolução, notificação de reserva disponível
- **Link:** `https://wa.me/55XXXXXXXXXXX?text=MENSAGEM`

### 9.7 Dashboards por Perfil
- **Dashboard do Aluno:** pontos (XP), medalhas, histórico de leituras, prazo de devolução, ferramenta de resenha
- **Dashboard do Professor:** monitoramento de alunos, WhatsApp, gerador de atividades por IA
- **Dashboard do Gestor:** controle de fluxo (empréstimos/devoluções), aba de atrasos, gestão de acervo
- **Painel do Super Admin:** configuração da escola, aprovação de gestores, virada de ano letivo

### 9.8 Estrutura de Dados para Reservas e Empréstimos
- **Meta keys no livro (`_bm_book`):**
  - `_bm_reservations` — Array de reservas (user_id, data, status, posição na fila)
  - `_bm_borrowed_count` — Quantos exemplares estão emprestados no momento
- **Meta keys no usuário:**
  - `_bm_active_reservations` — Array de IDs de livros reservados
  - `_bm_borrowed_books` — Array de IDs de livros emprestados
  - `_bm_reservation_count` — Total de reservas ativas (máx 3 para estudante)

  ## 10. CICLO 6 — GAMIFICAÇÃO E ENGAJAMENTO ← EM PLANEJAMENTO

> **Status:** Ciclo 6 em planejamento. Foco em engajamento dos alunos com ranking, fichas de leitura e medalhas.

### 10.0 Requisitos de Segurança (OBRIGATÓRIO)
- **Dados de alunos:** Nomes e fotos (avatar) no ranking público. Avaliar consentimento/LGPD.
- **Conteúdo de resenhas:** Texto e vídeos passam por aprovação do Gestor antes de exibição pública.
- **Nonces:** Todos os formulários de ficha de leitura devem ter verificação de nonce.

### 10.1 Ranking de Leitores (Fase 10A)
- **Shortcode:** `[bm_ranking]` para exibir ranking em qualquer página
- **Parâmetros:** `period` (week, month, bimester, year), `limit` (padrão 10)
- **Cálculo:** Contar empréstimos com status 'returned' no período, agrupados por usuário
- **Exibição:** Avatar, nome, quantidade de livros lidos
- **Destaque:** Medalhas 🥇🥈🥉 para os 3 primeiros
- **Integração:** Hook `bm_after_catalog_grid()` para carrossel de "Mais Lidos"

### 10.2 Ficha de Leitura (Fase 10B)
- **Shortcode:** `[bm_reading_log]` — formulário para aluno preencher após leitura
- **Campos:** Selecionar livro (dentre os devolvidos), nota (1-5 estrelas), resenha (textarea)
- **Metadado:** Salvo como `_bm_reading_log` no usuário (array de fichas)
- **Aprovação:** Opcional — Gestor aprova para liberar XP

### 10.3 Vídeo-Resenha (Fase 10C)
- **Campo:** URL de vídeo (YouTube, TikTok, Instagram) na ficha de leitura
- **Exibição:** Vídeos aprovados aparecem na página individual do livro
- **Metadado:** Integrado ao `_bm_reading_log`

### 10.4 XP e Medalhas (Fase 10D)
- **XP:** Pontos por ação (ler livro = 10 XP, resenha = 5 XP, vídeo = 10 XP)
- **Função:** `bm_add_xp($user_id, $amount, $reason)`
- **Medalhas:** Concedidas automaticamente por `bm_check_badges($user_id)`
- **Badges:**
  - 🐭 Rato de Biblioteca: 5 livros lidos
  - 📚 Leitor Voraz: 15 livros lidos
  - 🏆 Mestre das Ciências: 10 livros de uma mesma disciplina
  - 🎬 Crítico de Cinema: 5 vídeo-resenhas
- **Exibição:** Dashboard do aluno + shortcode `[bm_badges]`

## 11. CICLO 7 — FERRAMENTAS PEDAGÓGICAS ← PLANEJADO

> **Status:** Ciclo 7 planejado. Foco em ferramentas de apoio ao professor e catalogação avançada.

### 11.0 Requisitos de Segurança (OBRIGATÓRIO)
- **API Gemini:** Chave configurável na página de configurações (Fase 12A)
- **Cache:** Resultados de IA salvos como metadado para evitar chamadas repetidas
- **Nonces:** Presentes em todos os botões de ação AJAX

### 11.1 Gerador de Atividades por IA (Fase 11A)
- **Acesso:** Exclusivo para Professor (bm_teacher) e superior
- **Gatilho:** Botão "Gerar Atividade" no dashboard do Professor
- **Prompt:** Enviar título, autor, sinopse e disciplina para Gemini
- **Resposta:** 3 sugestões de atividades pedagógicas
- **Cache:** Salvo como `_bm_activities` no livro

### 11.2 CDU e Cutter (Fase 11B)
- **CDU:** IA sugere código de Classificação Decimal Universal
- **Cutter:** Cálculo automático da Tabela Cutter-Sanborn (autor + título)
- **Campos:** `_bm_cdu` e `_bm_cutter`
- **Cache:** `_bm_cdu_cached` para evitar rechamadas

### 11.3 Geração de Etiquetas (Fase 11C)
- **Página:** "Etiquetas" no menu Livros
- **Seleção:** Checkboxes para escolher livros
- **Layout:** Código de barras, CDU, Cutter, título, autor
- **Impressão:** CSS @media print para folha A4

## 12. CICLO 8 — INFRAESTRUTURA E CONFIGURAÇÕES ← PLANEJADO

> **Status:** Ciclo 8 planejado. Foco em adaptabilidade, white label e virada de ano.

### 12.0 Requisitos de Segurança (OBRIGATÓRIO)
- **Configurações:** Acesso restrito a `manage_options` (apenas Admin)
- **Virada de ano:** Confirmação dupla antes de executar ação irreversível
- **API Keys:** Salvas com sanitização adequada

### 12.1 Página de Configurações (Fase 12A)
- **Subpágina:** "Configurações" no menu Livros
- **Campos:** API Key Google Books, API Key Gemini
- **Limites:** Máximo de reservas por aluno (padrão 3), máximo de empréstimos (padrão 1), prazo padrão (padrão 14)
- **Armazenamento:** `get_option('bm_settings')` — array associativo

### 12.2 White Label (Fase 12B)
- **Nome da escola:** Substitui "Catálogo de Livros" no título
- **Logo:** Upload via WordPress media uploader
- **Cores:** Primária e secundária (aplicadas via CSS inline)
- **Anos letivos:** Configuráveis (ex: 1º Ano EM, 2º Ano EM...)

### 12.3 Virada de Ano Letivo (Fase 12C)
- **Acesso:** Exclusivo Admin (manage_options)
- **Ações:** Arquivar rankings, resetar XP (opcional), limpar reservas, ativar recadastramento
- **Segurança:** Confirmação dupla com senha do admin

### 12.4 Integração Google Drive (Fase 12D)
- **Campo:** URL pública do Google Sheets na importação CSV
- **Download:** `wp_remote_get()` para baixar como CSV
- **Processamento:** Mesmo fluxo de mapeamento dinâmico existente

## 13. BARREIRAS DO ESCOPO (Proibido)
- ❌ Alterar a estrutura do CPT existente
- ❌ Modificar os hooks de activation/deactivation/uninstall
- ❌ Usar bibliotecas externas (Laravel-Excel, PhpSpreadsheet, etc.)
- ❌ Criar tabelas customizadas no banco (usar post meta e user meta)
- ❌ Shortcodes, widgets ou blocos Gutenberg (para a vitrine)
- ❌ REST API endpoints customizados
- ❌ Qualquer dependência de composer, npm ou CDN externo