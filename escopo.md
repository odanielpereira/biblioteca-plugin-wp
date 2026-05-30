# ESCOPO.md — Plugin de Gestão de Livros para WordPress

## 1. IDENTIDADE DO PLUGIN
- **Nome:** Gestão de Livros
- **Slug:** `book-manager`
- **Text Domain:** `book-manager`
- **Prefixo de funções:** `bm_`
- **Prefixo de meta keys:** `_bm_`

## 2. REFERÊNCIA ÚNICA
- 100% do código deve seguir: https://developer.wordpress.org/
- Funções obrigatórias de consulta antes de implementar:
  - `register_post_type()` → https://developer.wordpress.org/reference/functions/register_post_type/
  - `add_meta_box()` → https://developer.wordpress.org/reference/functions/add_meta_box/
  - `update_post_meta()` → https://developer.wordpress.org/reference/functions/update_post_meta/
  - `current_user_can()` → https://developer.wordpress.org/reference/functions/current_user_can/
  - `wp_verify_nonce()` → https://developer.wordpress.org/reference/functions/wp_verify_nonce/
  - `sanitize_text_field()` → https://developer.wordpress.org/reference/functions/sanitize_text_field/
  - `register_activation_hook()` → https://developer.wordpress.org/reference/functions/register_activation_hook/
  - `register_uninstall_hook()` → https://developer.wordpress.org/reference/functions/register_uninstall_hook/

## 3. ESTRUTURA DE DADOS (Banco)

### 3.1 Custom Post Type
- **Slug do CPT:** `bm_book`
- **Args obrigatórios:**
  - `public` → false
  - `show_ui` → true
  - `capability_type` → `bm_book`
  - `map_meta_cap` → true
  - `supports` → ['title'] (apenas título, sem editor de conteúdo)
  - `delete_with_user` → false

### 3.2 Meta Keys (wp_postmeta)
| Meta Key | Tipo | Obrigatório | Sanitização |
|----------|------|-------------|-------------|
| `_bm_author` | string | Não | `sanitize_text_field()` |
| `_bm_publisher` | string | Não | `sanitize_text_field()` |

**Tabela envolvida:** Apenas `wp_posts` e `wp_postmeta`. Zero tabelas customizadas.

## 4. PERMISSÕES
- Ações restritas: `add`, `edit`, `delete`
- Capacidade requerida: `manage_options` (apenas Administradores)
- Cada operação deve ser precedida por `current_user_can('manage_options')`

## 5. COMPORTAMENTO DE EXCLUSÃO
- Exclusão via `wp_trash_post()`, não força delete permanente
- CPT deve suportar lixeira (`'show_in_trash' => true` implicitamente via custom capabilities)

## 6. OBRIGAÇÕES DE LIMPEZA (Ativação/Desativação)
- **Na ativação:** Registrar o CPT via `register_post_type()` + `flush_rewrite_rules()` UMA vez
- **Na desativação:** `flush_rewrite_rules()` apenas
- **Na desinstalação (`uninstall.php`):**
  - Deletar TODOS os posts do tipo `bm_book` permanentemente
  - Deletar TODAS as meta keys `_bm_author` e `_bm_publisher`
  - Remover capabilities do CPT do banco

## 7. BARREIRAS DO ESCOPO (Proibido)
- ❌ Criar tabelas customizadas no banco
- ❌ Usar taxonomias para Autor ou Editora
- ❌ Interface no front-end (apenas wp-admin)
- ❌ Shortcodes, widgets ou blocos Gutenberg
- ❌ Importação/exportação de livros
- ❌ REST API endpoints customizados
- ❌ Qualquer dependência de composer, npm ou CDN externo