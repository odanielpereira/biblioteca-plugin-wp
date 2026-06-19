# SEGURANÇA.md — Padrões de Segurança e Codificação

> Versão: 1.0
> Data: 2026-06-08
> Propósito: Estabelecer regras obrigatórias de segurança, escaping e internacionalização para o plugin Book Manager, conforme WordPress Coding Standards e requisitos do Plugin Check.

---

## 1. INPUT (ENTRADA DE DADOS)

### 1.1 Unslash antes de sanitizar

Todo dado vindo do usuário (POST, GET, REQUEST) deve passar por wp_unslash() antes de qualquer sanitização.

ERRADO:   $name = sanitize_text_field($_POST['name']);
CORRETO:  $name = sanitize_text_field(wp_unslash($_POST['name']));

### 1.2 Sanitização obrigatória

| Tipo de dado | Função |
|--------------|--------|
| Texto simples | sanitize_text_field() |
| E-mail | sanitize_email() |
| URL | esc_url_raw() |
| Número inteiro | absint() ou intval() |
| Texto longo (textarea) | sanitize_textarea_field() |
| HTML permitido | wp_kses_post() |
| Chave/slug | sanitize_key() |

### 1.3 Verificação de nonce

Todo formulário que processa dados deve verificar nonce.

ERRADO:   if (isset($_POST['save_data'])) { ... }
CORRETO:  if (isset($_POST['bm_nonce']) && wp_verify_nonce(wp_unslash($_POST['bm_nonce']), 'bm_action')) { ... }

### 1.4 Verificação de capability

Toda ação administrativa deve verificar permissões do usuário.
if (!current_user_can('manage_options') && !current_user_can('edit_bm_books')) return;

---

## 2. OUTPUT (SAÍDA DE DADOS)

### 2.1 Regra de ouro

Todo output deve ser escapado. Nenhuma variável pode ser enviada para o HTML sem uma função de escaping.

### 2.2 Funções de escaping

| Contexto | Função |
|----------|--------|
| Texto em HTML | esc_html() |
| Atributo HTML | esc_attr() |
| URL | esc_url() |
| Textarea | esc_textarea() |
| Classe CSS | sanitize_html_class() |

### 2.3 Texto traduzível

ERRADO:   _e('Salvar', 'book-manager');
CORRETO:  esc_html_e('Salvar', 'book-manager');

ERRADO:   echo __('Salvar', 'book-manager');
CORRETO:  echo esc_html__('Salvar', 'book-manager');

### 2.4 URLs

ERRADO:   echo admin_url('edit.php?post_type=bm_book');
CORRETO:  echo esc_url(admin_url('edit.php?post_type=bm_book'));

### 2.5 Variáveis em HTML

ERRADO:   echo '<div class="' . $class . '">' . $text . '</div>';
CORRETO:  echo '<div class="' . esc_attr($class) . '">' . esc_html($text) . '</div>';

---

## 3. INTERNACIONALIZAÇÃO (i18n)

### 3.1 Placeholders ordenados

Strings com múltiplos placeholders devem usar %1$s, %2$s, %3$d (ordenados).

ERRADO:   __('%d importados, %d ignorados', 'book-manager');
CORRETO:  __('%1$d importados, %2$d ignorados', 'book-manager');

### 3.2 Comentários para tradutores

Strings com placeholders devem ter um comentário translators na linha anterior.

Exemplo:
translators: %1$d: number of books imported, %2$d: number of books skipped
$msg = sprintf(__('%1$d importados, %2$d ignorados', 'book-manager'), $imported, $skipped);

### 3.3 gmdate() em vez de date()

ERRADO:   $time = date('Y-m-d H:i:s');
CORRETO:  $time = gmdate('Y-m-d H:i:s');

---

## 4. SISTEMA DE ARQUIVOS

### 4.1 fopen/fclose

Evitar fopen() e fclose(). Usar WP_Filesystem quando possível. Para CSV, usar fgetcsv() e fputcsv() é aceitável, mas o Plugin Check ainda emitirá warnings.

### 4.2 Proteção de acesso direto

Todo arquivo PHP deve começar com: defined('ABSPATH') || exit;

### 4.3 Pasta languages

O plugin deve ter uma pasta languages/ vazia (ou com arquivo .pot).

---

## 5. BANCO DE DADOS

### 5.1 Usar post_meta e user_meta

- PROIBIDO: criar tabelas customizadas
- OBRIGATÓRIO: get_post_meta(), update_post_meta(), get_user_meta(), update_user_meta()

### 5.2 Prefixo obrigatório

Todas as meta keys devem usar o prefixo _bm_

---

## 6. DEPENDÊNCIAS

### 6.1 Proibido

- CDN para scripts ou estilos
- Bibliotecas externas via composer ou npm
- REST API customizada (exceto Fase 26, opcional)
- Gateways de pagamento reais
- Serviços externos de PDF

### 6.2 Permitido

- APIs externas via wp_remote_get() / wp_remote_post() (Google Books, Groq, YouTube)
- Chart.js incluso como arquivo único (sem CDN)
- TCPDF incluso como arquivo único (sem composer)

---

## 7. EXPORTAÇÃO DE DADOS

### 7.1 Proibido exportar

- Senhas de usuários (user_pass)
- Dados pessoais de alunos em APIs públicas
- Logs de auditoria para usuários não autorizados

---

## 8. CHECKLIST PRÉ-COMMIT

Antes de cada commit, verificar:

- [ ] Nenhum POST ou GET sem wp_unslash()
- [ ] Nenhum _e() ou __() sem escaping
- [ ] Nenhum admin_url() sem esc_url()
- [ ] Nenhum date() que deveria ser gmdate()
- [ ] Nenhum arquivo sem defined('ABSPATH') || exit;
- [ ] Nonces presentes em formulários
- [ ] Capabilities verificadas em ações administrativas
- [ ] Placeholders ordenados em strings traduzíveis

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