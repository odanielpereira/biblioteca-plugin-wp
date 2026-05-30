### Fase 1: Estrutura Base e Custom Post Type (CPT) ← FASE ATIVA

*   **Objetivo:** Criar a fundação do plugin com slug `book-manager` e o CPT `bm_book` visível apenas para administradores.
*   **Critério de saída:** Um admin consegue ver o menu "Livros" no painel, adicionar um livro com título, e o livro aparece na listagem.

*   **Tarefas:**
    1.  [ ] Criar `/wp-content/plugins/book-manager/book-manager.php` com cabeçalho WordPress padrão.
        → **Verificar:** O plugin aparece na tela de plugins do WordPress.
    2.  [ ] Adicionar `defined('ABSPATH') || exit;` na linha 1 após o cabeçalho.
        → **Verificar:** Acessar o arquivo diretamente pelo navegador retorna tela branca.
    3.  [ ] Registrar CPT `bm_book` via `register_post_type()` no hook `init`.
        → **Verificar:** flush_rewrite_rules() executado UMA vez. Menu "Livros" aparece no admin.
    4.  [ ] Configurar capabilities: `capability_type` => `bm_book`, `map_meta_cap` => true.
        → **Verificar:** Admin vê o menu. Editor não vê.
    5.  [ ] Adicionar `register_activation_hook()` para flush de rewrite rules.
        → **Verificar:** Desativar e reativar o plugin não quebra os permalinks.
    6.  [ ] Adicionar `register_deactivation_hook()` para flush de rewrite rules (sem apagar dados).
        → **Verificar:** Desativar o plugin remove o menu mas os livros permanecem no banco.