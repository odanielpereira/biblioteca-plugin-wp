# ESCOPO.md — Plugin de Gestão de Livros para WordPress

## 1. IDENTIDADE DO PLUGIN
- **Nome:** Gestão de Livros
- **Slug:** `book-manager`
- **Text Domain:** `book-manager`
- **Prefixo de funções:** `bm_`
- **Prefixo de meta keys:** `_bm_`
- **Versão atual:** 1.0.0

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

## 3. FUNCIONALIDADES EXISTENTES (PRESERVADAS — v1.0.0)

### 3.1 Custom Post Type
- **Slug do CPT:** `bm_book`
- **Args obrigatórios:**
  - `public` → false
  - `show_ui` → true
  - `capability_type` → `bm_book`
  - `map_meta_cap` → true
  - `supports` → ['title'] (apenas título)
  - `delete_with_user` → false
  - `menu_icon` → `dashicons-book`
  - `rewrite` → false

### 3.2 Meta Keys (wp_postmeta)
| Meta Key | Tipo | Obrigatório | Sanitização |
|----------|------|-------------|-------------|
| `_bm_author` | string | Não | `sanitize_text_field()` |
| `_bm_publisher` | string | Não | `sanitize_text_field()` |

**Tabela envolvida:** Apenas `wp_posts` e `wp_postmeta`. Zero tabelas customizadas.

### 3.3 Permissões
- Ações restritas: `add`, `edit`, `delete`
- Capacidade requerida: `manage_options` (apenas Administradores)
- Cada operação deve ser precedida por `current_user_can('manage_options')`
- Funções `bm_add_admin_caps()` e `bm_remove_admin_caps()` implementadas

### 3.4 Comportamento de Exclusão
- Exclusão via `wp_trash_post()`, não força delete permanente
- CPT suporta lixeira

### 3.5 Obrigações de Limpeza
- **Na ativação:** Registrar o CPT + `flush_rewrite_rules()` + `bm_add_admin_caps()`
- **Na desativação:** `flush_rewrite_rules()` apenas
- **Na desinstalação (`uninstall.php`):**
  - Deletar TODOS os posts do tipo `bm_book` permanentemente
  - Deletar TODAS as meta keys `_bm_author` e `_bm_publisher`
  - Chamar `bm_remove_admin_caps()`

### 3.6 Metabox
- Metabox "Detalhes do Livro" com campos Autor e Editora
- Nonce field + `wp_verify_nonce()`
- Salvamento com `sanitize_text_field()` e `current_user_can('manage_options')`

### 3.7 Listagem e Filtros
- Colunas customizadas: Título, Autor, Editora (via `manage_bm_book_posts_columns`)
- Filtros por Autor e Editora (via `restrict_manage_posts` + `pre_get_posts`)
- Busca nativa por Título preservada

## 4. NOVA FUNCIONALIDADE — IMPORTAÇÃO CSV (Fase 6A)

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

## 5. NOVA FUNCIONALIDADE — EXPORTAÇÃO CSV (Fase 6B)

### 5.1 Interface
- Subpágina "Exportar CSV" dentro do menu "Livros" (OU botão na página de importação)
- Acesso restrito a `manage_options`

### 5.2 Geração do CSV
- Colunas na ordem: Título, Autor, Editora
- Delimitador: `;`
- Codificação: UTF-8 com BOM (para compatibilidade com Excel)
- Cabeçalho na primeira linha
- Usar `get_posts()` para buscar todos os `bm_book` (todos os status)
- Saída: download forçado via headers PHP
  - `Content-Type: text/csv; charset=utf-8`
  - `Content-Disposition: attachment; filename="livros.csv"`

## 6. NOVA FUNCIONALIDADE — AJUSTES DE USABILIDADE (Fase 6C)

### 6.1 Aviso na Exportação
- Após o download do CSV, exibir mensagem: "X livros exportados com sucesso."
- A mensagem deve aparecer na própria página de exportação, não no arquivo CSV.

### 6.2 Detecção de Duplicados na Importação
- Critério de duplicata: Título + Autor + Editora (os três campos juntos).
- Antes de inserir, verificar se já existe um `bm_book` com o mesmo título E mesmos valores em `_bm_author` e `_bm_publisher`.
- Se duplicata encontrada:
  - Exibir lista dos títulos duplicados.
  - Oferecer opção: "Pular" ou "Importar mesmo assim".
- Livros com mesmo título mas autor ou editora diferentes NÃO são considerados duplicados.

### 6.3 Confirmação Pré-Importação
- Antes de processar o CSV, exibir prévia:
  - "X livros serão importados."
  - "Y já existem no acervo."
  - Botões: "Continuar" / "Cancelar".

### 6.4 Relatório Detalhado
- Após a importação, exibir:
  - "X livros importados com sucesso."
  - "Y linhas ignoradas (sem título)."
  - "Z duplicados pulados."
  - "W duplicados importados mesmo assim."
  - "Total de linhas processadas: T."

  ## 7. NOVA FUNCIONALIDADE — EXPANSÃO DA FICHA CATALOGRÁFICA (Fase 7)

### 7.1 Campos Fixos de Catalogação (Fase 7A)
- Adicionar à metabox "Detalhes do Livro" os campos:
  - Gênero (`_bm_genre`) — texto, opcional
  - Categoria (`_bm_category`) — texto, opcional
  - Número de exemplares (`_bm_copies`) — número, opcional
  - ISBN (`_bm_isbn`) — texto, opcional
  - Localização (`_bm_location`) — texto, opcional
- Nenhum campo é obrigatório. Apenas Título permanece obrigatório.
- Sanitização: `sanitize_text_field()` para campos de texto, `intval()` para exemplares.
- Exibir apenas campos que possuem valor na ficha do livro.
- Importação/exportação CSV deve aceitar as novas colunas como opcionais.
- Critério de duplicata permanece Título + Autor + Editora.

### 7.2 Campos Dinâmicos (Fase 7B)
- Interface para o Gestor criar campos personalizados.
- Cada campo dinâmico tem: nome, tipo (texto/número) e valor.
- Salvos como meta keys com prefixo `_bm_dynamic_`.
- Exibidos na metabox junto com os campos fixos.
- Importação/exportação CSV aceita colunas dinâmicas.

### 7.3 Taxonomias (Fase 7C)
- Criar taxonomias para Gênero e Categoria (hierárquica).
- Registro via `register_taxonomy()` vinculado ao CPT `bm_book`.
- Interface de seleção na metabox (como categorias de posts).
- Filtros na listagem usando as taxonomias.
- Suporte na importação/exportação CSV.

### 7.4 Capa do Livro (Fase 7D)
- Habilitar `thumbnail` no `supports` do CPT.
- Campo de upload de imagem na metabox.
- Busca automática de capa via Google Books API usando `wp_remote_get()`.
- A busca automática é opcional e não bloqueia o cadastro.

### 7.5 Filtros na Exportação e Seleção Individual de Duplicados (Fase 7E)
- Filtros na exportação: permitir exportar apenas livros de determinado autor/editora/gênero.
- Seleção individual de duplicados na importação: checkbox ao lado de cada duplicado para decidir individualmente.

### 7.6 Soft Delete e Auditoria (Fase 7F)
- Livros nunca são excluídos fisicamente (apenas movidos para lixeira).
- Registro de auditoria: log de quem criou, editou ou excluiu cada livro.
- Tabela de auditoria usando `wp_posts` com post type `bm_audit_log` ou meta keys.

### 7.7 Mapeamento Dinâmico de Colunas na Importação (Fase 7G)
- Leitura prévia dos cabeçalhos do CSV.
- Interface para o usuário associar colunas do arquivo aos campos do sistema.
- Exemplo: "Nome do Livro" → "Título", "Escritor" → "Autor".
- Suporte a número variável de colunas.
- O mapeamento é aplicado antes da detecção de duplicados.

## 8. BARREIRAS DO ESCOPO (Proibido)tente
- ❌ Adicionar novos campos fixos à metabox
- ❌ Modificar os hooks de activation/deactivation/uninstall
- ❌ Usar bibliotecas externas (Laravel-Excel, PhpSpreadsheet, etc.)
- ❌ Criar tabelas customizadas no banco
- ❌ Interface no front-end
- ❌ Shortcodes, widgets ou blocos Gutenberg
- ❌ REST API endpoints customizados
- ❌ Qualquer dependência de composer, npm ou CDN externo