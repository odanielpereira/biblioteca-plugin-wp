### Fase 0: Planejamento e Estrutura de Governança
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
        → **Verificar:** O plugin aparece na tela de plugins do WordPress.
    2.  [x] Adicionar `defined('ABSPATH') || exit;` na linha 1 após o cabeçalho.
        → **Verificar:** Acessar o arquivo diretamente pelo navegador retorna tela branca.
    3.  [x] Registrar CPT `bm_book` via `register_post_type()` no hook `init`.
        → **Verificar:** flush_rewrite_rules() executado UMA vez. Menu "Livros" aparece no admin.
    4.  [x] Configurar capabilities: `capability_type` => `bm_book`, `map_meta_cap` => true.
        → **Verificar:** Admin vê o menu. Editor não vê.
    5.  [x] Adicionar `register_activation_hook()` para flush de rewrite rules.
        → **Verificar:** Desativar e reativar o plugin não quebra os permalinks.
    6.  [x] Adicionar `register_deactivation_hook()` para flush de rewrite rules (sem apagar dados).
        → **Verificar:** Desativar o plugin remove o menu mas os livros permanecem no banco.

### Fase 2: Metaboxes e Campos Personalizados ← FASE ATIVA

*   **Objetivo:** Adicionar campos personalizados para armazenar os detalhes dos livros (autor, editora, etc.).
*   **Critério de saída:** Ao editar um livro, uma metabox aparece com todos os campos definidos no `escopo.md`. Os dados inseridos nesses campos são salvos e exibidos corretamente ao reabrir a página de edição.

*   **Tarefas:**
    1.  [ ] Usar `add_meta_box()` no hook `add_meta_boxes` para criar uma caixa de "Detalhes do Livro" para o CPT `bm_book`.
        → **Verificar:** Uma nova caixa aparece na tela de edição do livro.
    2.  [ ] Criar a função de callback que renderiza o HTML da metabox.
        → **Verificar:** A caixa contém labels e campos de input para `bm_author`, `bm_publisher`, `bm_publication_year`, `bm_page_count`, `bm_cover_url`, `bm_isbn`.
    3.  [ ] Adicionar um `wp_nonce_field()` na função de callback da metabox para segurança.
        → **Verificar:** Um campo de nonce oculto está presente no formulário.
    4.  [ ] Criar uma função no hook `save_post` para salvar os metadados.
        → **Verificar:** Após salvar, os valores aparecem na tabela `wp_postmeta` do banco de dados.
    5.  [ ] Dentro da função `save_post`, verificar o nonce, a ação do usuário (`DOING_AUTOSAVE`), e as permissões (`current_user_can`) antes de salvar.
        → **Verificar:** Os dados não são salvos em salvamentos automáticos ou por usuários sem permissão.
    6.  [ ] Na função de callback da metabox (Tarefa 2), usar `get_post_meta()` para preencher os campos com os valores já salvos, se existirem.
        → **Verificar:** Ao editar um livro, os campos já vêm preenchidos com os dados salvos anteriormente.

### Fase 3: Comandos WP-CLI

*   **Objetivo:** Criar comandos personalizados para o WP-CLI para gerenciar livros via linha de comando.
*   **Critério de saída:** É possível criar, listar, editar e apagar livros usando comandos `wp bm_book ...`.

### Fase 4: Endpoints da REST API

*   **Objetivo:** Expor a funcionalidade do plugin através da REST API do WordPress.
*   **Critério de saída:** É possível criar, listar, editar e apagar livros através de requisições HTTP para endpoints personalizados da REST API.

### Fase 5: Limpeza e Desinstalação

*   **Objetivo:** Garantir que o plugin possa ser removido de forma limpa.
*   **Critério de saída:** Um arquivo `uninstall.php` é criado e, ao ser executado, remove todos os dados do plugin (CPTs, metadados, opções) do banco de dados.