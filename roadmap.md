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
*   **Tarefas:**
    1.  [x] Criar `/wp-content/plugins/book-manager/book-manager.php` com cabeçalho WordPress padrão.
    2.  [x] Adicionar `defined('ABSPATH') || exit;`.
    3.  [x] Registrar CPT `bm_book` via `register_post_type()` no hook `init`.
    4.  [x] Configurar capabilities: `capability_type` => `bm_book`, `map_meta_cap` => true.
    5.  [x] Adicionar `register_activation_hook()` para flush de rewrite rules.
    6.  [x] Adicionar `register_deactivation_hook()` para flush de rewrite rules (sem apagar dados).

### Fase 2: Metaboxes e Campos Personalizados ← FASE CONCLUÍDA
*   **Objetivo:** Implementar a metabox para adicionar e editar detalhes do livro (Autor e Editora), garantindo a segurança e a aderência ao escopo.
*   **Critério de saída:** A metabox "Detalhes do Livro" aparece na tela de edição do CPT `bm_book`, com campos para Autor e Editora. Os dados inseridos são salvos corretamente e preenchidos ao recarregar a página.
*   **Tarefas:**
    1.  [x] Usar `add_meta_box()` no hook `add_meta_boxes` para criar a caixa "Detalhes do Livro" para o CPT `bm_book`.
    2.  [x] Criar a função de callback que renderiza os campos Autor e Editora na metabox.
    3.  [x] Incluir um nonce field (`wp_nonce_field`) na metabox para verificação de segurança.
    4.  [x] Implementar o hook `save_post` (`save_post_bm_book`) com:
        *   Verificação do nonce (`wp_verify_nonce`).
        *   Verificação de permissão (`current_user_can('manage_options')`).
        *   Sanitização dos campos com `sanitize_text_field()`.
        *   Salvamento dos dados com `update_post_meta()`.
    5.  [x] Preencher os campos Autor e Editora com os valores salvos usando `get_post_meta()` na função de callback da metabox.

### Fase 4: Interface de Listagem e Visualização ← FASE CONCLUÍDA
*   **Objetivo:** Criar uma interface para listar todos os livros cadastrados e visualizar seus detalhes, aderindo estritamente ao escopo e princípios.
*   **Critério de saída:** A listagem nativa do CPT `bm_book` é customizada para exibir colunas de Título, Autor e Editora, com funcionalidade de busca/filtro. Nenhuma interface de menu duplicada é criada.
*   **Tarefas:**
    1.  [x] Customizar a listagem nativa do CPT `bm_book` para exibir colunas de Título, Autor e Editora.
    2.  [x] Implementar funcionalidade de busca/filtro por Título, Autor e Editora na listagem customizada.

### Fase 5: Desativação, Desinstalação e Limpeza ← FASE CONCLUÍDA
*   **Objetivo:** Garantir que o plugin possa ser desativado e desinstalado de forma limpa, removendo todas as opções e dados criados.
*   **Critério de saída:** O plugin pode ser desativado sem erros. A desinstalação remove completamente o CPT `bm_book`, seus metadados e quaisquer outras opções criadas pelo plugin.
*   **Tarefas:**
    1.  [x] Criar o arquivo `uninstall.php`.
    2.  [x] Implementar a lógica de remoção do CPT `bm_book` e limpeza de metadados no `uninstall.php`.
    3.  [x] Garantir que a desativação (hook `register_deactivation_hook`) apenas execute `flush_rewrite_rules()`, conforme o `escopo.md`.

---

## Ciclo 2 — Versão 2.0.0 ← EM ANDAMENTO

### Fase 6: Importação e Exportação CSV ← FASE ATIVA
*   **Objetivo:** Permitir que o Gestor importe livros em massa via arquivo CSV e exporte o acervo cadastrado.
*   **Critério de saída:** 
    *   6A: O admin consegue fazer upload de um CSV com Título, Autor e Editora, e os livros são criados no sistema com relatório de resultados.
    *   6B: O admin consegue baixar um CSV com todos os livros cadastrados e suas informações.

#### Fase 6A — Importação CSV
*   **Descrição:** Desenvolver a funcionalidade de importar dados de livros a partir de um arquivo CSV, permitindo o cadastro em massa.
*   **Critérios de Saída:**
    *   Criar subpágina no menu "Livros" para a funcionalidade de importação.
    *   Implementar formulário de upload de arquivo CSV com nonce para segurança.
    *   Processar upload de CSV: delimitador `;`, codificação UTF-8, ignorar linha de cabeçalho.
    *   Validar dados: Título é obrigatório. Aplicar `sanitize_text_field()` nos campos.
    *   Utilizar `wp_insert_post()` para criar novos posts do tipo `bm_book`.
    *   Utilizar `update_post_meta()` para salvar os metadados (`_bm_author`, `_bm_publisher`).
    *   Exibir relatório detalhado dos resultados da importação (sucessos, falhas, motivos).
*   **Tarefas:**
    1.  [x] Criar subpágina "Importar CSV" no menu "Livros" (`add_submenu_page`).
    2.  [x] Renderizar formulário de upload com nonce de segurança.
    3.  [x] Processar o arquivo CSV (delimitador `;`, UTF-8, ignorar cabeçalho).
    4.  [x] Para cada linha: validar título obrigatório, sanitizar, inserir via `wp_insert_post()` + `update_post_meta()`.
    5.  [x] Exibir relatório: "X importados, Y ignorados (sem título)".

#### Fase 6B — Exportação CSV
*   **Descrição:** Desenvolver a funcionalidade de exportar todos os dados de livros cadastrados para um arquivo CSV.
*   **Critérios de Saída:**
    *   Criar subpágina no menu "Livros" para a funcionalidade de exportação.
    *   Buscar todos os livros utilizando `get_posts()` com o post type `bm_book`.
    *   Gerar arquivo CSV com as colunas: Título, Autor, Editora.
    *   CSV deve usar delimitador `;` e codificação UTF-8 com BOM.
    *   Forçar download do arquivo CSV via headers HTTP.
*   **Tarefas:**
 1.  [x] Criar subpágina "Exportar CSV" no menu "Livros" (`add_submenu_page`).
2.  [x] Buscar todos os livros com `get_posts()`.
3.  [x] Gerar arquivo CSV (delimitador `;`, UTF-8 com BOM) com colunas Título, Autor, Editora.
4.  [x] Forçar download via headers PHP.

### Fase 6C — Ajustes de Usabilidade ← FASE ATIVA
*   **Objetivo:** Refinar a experiência de importação e exportação com avisos, detecção de duplicados e relatórios detalhados.
*   **Critério de saída:** O usuário recebe feedback claro em todas as operações. A importação detecta duplicados por Título + Autor + Editora e oferece opção de pular ou importar.
*   **Tarefas:**
 1. [x] Aviso na exportação: "X livros disponíveis para exportação"
2. [x] Detecção de duplicados: Título + Autor + Editora, opção pular ou forçar
3. [x] Confirmação pré-importação: prévia com lista de duplicados
4. [x] Relatório detalhado: importados, ignorados, duplicados pulados