# ESCOPO.md — Plugin de Gestão de Livros para WordPress

## 1. IDENTIDADE DO PLUGIN
- **Nome:** Gestão de Livros
- **Slug:** `book-manager`
- **Text Domain:** `book-manager`
- **Prefixo de funções:** `bm_`
- **Prefixo de meta keys:** `_bm_`
- **Versão atual:** 3.0.0

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

## 3. FUNCIONALIDADES EXISTENTES (CONSOLIDADO — Ciclos 1, 2 e 3)

### 3.1 Custom Post Type
- **Slug do CPT:** `bm_book`
- `public` → false (será alterado para true no Ciclo 4)
- `supports` → title, thumbnail
- `capability_type` → `bm_book` com `map_meta_cap` → true
- `delete_with_user` → false, `menu_icon` → `dashicons-book`, `rewrite` → false

### 3.2 Taxonomias
- `bm_genre` (Gêneros) — hierárquica
- `bm_category` (Categorias) — hierárquica

### 3.3 Campos
- **Fixos:** `_bm_author`, `_bm_publisher`, `_bm_isbn`, `_bm_location`, `_bm_copies`
- **Dinâmicos:** Criados pelo Gestor via interface, com tipo (texto curto/longo), prefixo `_bm_dynamic_`
- **Gerenciamento:** Renomear, reordenar (drag and drop), ocultar/mostrar, migração de dados ao renomear

### 3.4 Importação CSV
- Mapeamento dinâmico de colunas (Upload → Mapeamento → Processamento)
- Detecção de duplicados por Título + Autor + Editora
- Opção de pular ou forçar importação de duplicados
- Busca automática de capas durante importação (Google Books API)

### 3.5 Exportação CSV
- Flexível: filtros dinâmicos (campo + operador + valor), múltiplos com E/OU
- Seleção de colunas para exportar (checkboxes)
- Nomes amigáveis para campos dinâmicos e taxonomias

### 3.6 Capa do Livro
- Upload manual via thumbnail
- Busca automática via Google Books API com 5 níveis hierárquicos
- Fallback em cascata: ISBN → Título+Autor+Editora → Título+Autor → Título+Editora → Título

### 3.7 Auditoria
- Log de criação, edição, lixeira e restauração
- Exibido na metabox "Histórico de Ações"
- Soft delete nativo (wp_trash_post)

### 3.8 Permissões e Limpeza
- Acesso restrito a `manage_options`
- Activation: registra CPT + taxonomias + capabilities + flush
- Deactivation: flush apenas
- Uninstall: remove posts, meta keys, capabilities

## 4. IMPORTAÇÃO CSV (Fase 6A) ← CONCLUÍDO (Ciclo 2)

> **Status:** Implementado e expandido no Ciclo 3 com mapeamento dinâmico de colunas, detecção de duplicados e busca de capas. Ver changelog entradas 42-49.

### 4.1 Interface
- Subpágina "Importar CSV" dentro do menu "Livros" (`add_submenu_page`)
- Slug: `bm_csv_import`
- Acesso restrito a `manage_options`
- Formulário com campo de upload de arquivo `.csv`
- Nonce de segurança no formulário

### 4.2 Processamento
- Delimitador: `;` (ponto e vírgula)
- Codificação esperada: UTF-8
- Cabeçalho: primeira linha ignorada
- Colunas na ordem fixa: Título, Autor, Editora
- Título é obrigatório. Linhas sem título são ignoradas e contabilizadas.
- Sanitização: `sanitize_text_field()` em todos os campos
- Inserção: `wp_insert_post()` para criar o `bm_book` + `update_post_meta()` para Autor e Editora
- Status do post: `publish`

### 4.3 Relatório
- Após processamento, exibir:
  - "X livros importados com sucesso."
  - "Y linhas ignoradas (sem título)."
  - "Z linhas processadas no total."

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

## 8. CICLO 4 — VITRINE PÚBLICA E PÁGINA DO LIVRO ← EM PLANEJAMENTO

### 8.0 Requisitos de Segurança (OBRIGATÓRIO)
- **Controle de exibição:** A página pública deve usar `current_user_can('manage_options')` para decidir o que mostrar.
- **Dados SENSÍVEIS (apenas admin):** ISBN, Localização, Número de exemplares, Histórico de auditoria (`_bm_audit_log`).
- **Dados PÚBLICOS (visitantes):** Título, Autor, Editora, Capa, Gênero, Categoria, Sinopse (se cadastrada), Campos dinâmicos públicos.
- **REST API:** Não expor endpoints customizados para o CPT `bm_book`. A REST API nativa do WordPress para CPTs públicos deve ser avaliada — se necessário, desabilitar via `show_in_rest => false`.
- **Indexação:** O arquivo `archive` e as páginas `single` podem ser indexados por mecanismos de busca. Adicionar `noindex` se a escola preferir manter o acervo privado (opção futura de configuração).
- **Feeds RSS:** O WordPress gera feeds automaticamente para CPTs públicos. Avaliar se devem ser desabilitados.

### 8.1 Tornar CPT Público
- Alterar `public` → true
- Adicionar `has_archive` → true
- Adicionar `rewrite` → `['slug' => 'livros']`
- Manter `show_in_rest` → false (barreira de segurança)
- Manter `exclude_from_search` → false (permitir busca interna, avaliar indexação externa)

### 8.2 Página Individual do Livro (Single)
- Template para exibir ficha completa
- Visitantes veem: capa, título, autor, editora, gêneros, sinopse, campos dinâmicos públicos
- Admin logado vê adicionalmente: ISBN, localização, exemplares, histórico de auditoria
- Exibir apenas campos que possuem valor
- Botão de reserva aparecerá no futuro (Ciclo 5 ou 6)

### 8.3 Página de Catálogo (Archive)
- Grid de capas com informações básicas (título, autor)
- Paginação
- Ordenação por título, autor, data de cadastro
- Cada capa linka para a página individual do livro

### 8.4 Filtros Inteligentes na Vitrine
- Por gênero, categoria (dropdowns)
- Por autor, editora (campos de texto com autocomplete)
- Busca textual (título, autor, sinopse)
- Filtros funcionam via `pre_get_posts` no front-end

### 8.5 Vitrine Visual
- Grid de capas responsivo
- Hover com informações básicas
- Preparação para futuro carrossel de "Mais Lidos" (Ciclo 7 — Gamificação)

### 8.6 Busca Automática de Sinopse
- Aproveitar a Google Books API para buscar o campo `description` (sinopse)
- Salvar como campo dinâmico do tipo textarea (`_bm_dynamic_sinopse` ou nome definido pelo usuário)
- Integrar na importação CSV (junto com a busca de capa)
- Exibir na página pública do livro (visitantes podem ler a sinopse)
- A busca é opcional e não bloqueia o cadastro/importação

### 8.7 Classificação Interdisciplinar por IA (Planejamento Futuro — Ciclo 9/10)
- **Objetivo:** Conectar o acervo às disciplinas escolares, tornando a biblioteca um recurso pedagógico interdisciplinar.
- **Taxonomia:** Criar `bm_discipline` (Disciplinas) — hierárquica (ex: Ciências da Natureza > Biologia, Física, Química).
- **Relação livro-disciplina:** Um livro pode pertencer a múltiplas disciplinas.
- **Motor de IA:**
  - Enviar metadados do livro (título, autor, sinopse, gênero) para API de IA (Gemini/ChatGPT)
  - A IA retorna: disciplinas sugeridas + justificativa + sugestões de atividades
  - Resultado salvo como metadado para não repetir chamadas (cache)
- **Processamento em lote:** Classificar todo o acervo com controle de custos de API.
- **Interface do Professor:** Dashboard com busca de livros por disciplina e atividades sugeridas.
- **Dependências:** Vitrine pública (Ciclo 4), Usuários e perfis (Ciclo 5), Integração com IA externa.

## 9. BARREIRAS DO ESCOPO (Proibido)
- ❌ Alterar a estrutura do CPT existente (exceto `public` e `rewrite` para o Ciclo 4)
- ❌ Modificar os hooks de activation/deactivation/uninstall
- ❌ Usar bibliotecas externas (Laravel-Excel, PhpSpreadsheet, etc.)
- ❌ Criar tabelas customizadas no banco
- ❌ Shortcodes, widgets ou blocos Gutenberg (para a vitrine)
- ❌ REST API endpoints customizados
- ❌ Qualquer dependência de composer, npm ou CDN externo