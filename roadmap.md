# Roadmap

## Fase 0: Planejamento e Estrutura de Governança ← FASE CONCLUÍDA
*   **Objetivo:** Estabelecer a "constituição" do projeto com documentos que definem o comportamento da IA, o escopo técnico, o log de atividades e o plano de desenvolvimento.
*   **Critério de saída:** Todos os documentos de governança (`claude.md`, `escopo.md`, `changelog.md`, `roadmap.md`) estão criados, revisados e versionados no repositório.
*   **Tarefas:**
    1.  [x] Definição do Escopo (`escopo.md`)
    2.  [x] Definição do Comportamento da IA (`claude.md`)
    3.  [x] Criação do Log de Atividades (`changelog.md`)
    4.  [x] Definição do Roadmap (`roadmap.md`)
    5.  [x] Envio para o repositório Git.
    6.  [x] Criação da tag `v0.1-planning-complete`.

## Fase 1: Estrutura Base e Custom Post Type (CPT) ← FASE CONCLUÍDA
*   **Objetivo:** Criar a fundação do plugin com slug `book-manager` e o CPT `bm_book` visível apenas para administradores.
*   **Critério de saída:** Um admin consegue ver o menu "Livros" no painel, adicionar um livro com título, e o livro aparece na listagem.

## Fase 2: Metaboxes e Campos Personalizados ← FASE CONCLUÍDA
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

## Fase 4: Interface de Listagem e Visualização ← FASE CONCLUÍDA
*   **Objetivo:** Criar uma interface para listar todos os livros cadastrados e visualizar seus detalhes, aderindo estritamente ao escopo e princípios.
*   **Critério de saída:** A listagem nativa do CPT `bm_book` é customizada para exibir colunas de Título, Autor e Editora, com funcionalidade de busca/filtro. Nenhuma interface de menu duplicada é criada.
*   **Tarefas:**
    1.  [x] Customizar a listagem nativa do CPT `bm_book` para exibir colunas de Título, Autor e Editora.
    2.  [x] Implementar funcionalidade de busca/filtro por Título, Autor e Editora na listagem customizada.

## Fase 5: Desativação, Desinstalação e Limpeza ← FASE CONCLUÍDA
*   **Objetivo:** Garantir que o plugin possa ser desativado e desinstalado de forma limpa, removendo todas as opções e dados criados.
*   **Critério de saída:** O plugin pode ser desativado sem erros. A desinstalação remove completamente o CPT `bm_book`, seus metadados e quaisquer outras opções criadas pelo plugin.
*   **Tarefas:**
    1.  [x] Criar o arquivo `uninstall.php`.
    2.  [x] Implementar a lógica de remoção do CPT `bm_book` e limpeza de metadados no `uninstall.php`.
    3.  [x] Garantir que a desativação (hook `register_deactivation_hook`) apenas execute `flush_rewrite_rules()`, conforme o `escopo.md`.
