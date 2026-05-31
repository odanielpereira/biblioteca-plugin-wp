<?php
/**
 * Plugin Name:       Gestão de Livros
 * Plugin URI:        https://github.com/odanielpereira/biblioteca-plugin-wp
 * Description:       Gerenciador de livros para o tema Biblioteca.
 * Version:           1.0.0
 * Author:            Daniel Pereira
 * Author URI:        https://odanielpereira.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       book-manager
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// Função para registrar o CPT 'bm_book'
function bm_register_book_cpt() {
    $labels = array(
        'name'               => 'Livros',
        'singular_name'      => 'Livro',
        'menu_name'          => 'Livros',
        'add_new'            => 'Adicionar Novo',
        'add_new_item'       => 'Adicionar Novo Livro',
        'edit_item'          => 'Editar Livro',
        'new_item'           => 'Novo Livro',
        'view_item'          => 'Ver Livro',
        'search_items'       => 'Buscar Livros',
        'not_found'          => 'Nenhum livro encontrado',
        'not_found_in_trash' => 'Nenhum livro na lixeira',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false, // Conforme escopo.md, não deve ser público
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'bm_book',
        'map_meta_cap'       => true,
        'supports'           => array( 'title' ), // Apenas título conforme escopo.md para esta fase
        'delete_with_user'   => false, // Não associar à exclusão de usuário
        'menu_icon'          => 'dashicons-book', // Ícone do menu
        'rewrite'            => false, // Conforme escopo.md, sem permalink customizado
    );

    register_post_type( 'bm_book', $args );
}
add_action( 'init', 'bm_register_book_cpt' );

// Adiciona as capabilities para o usuário administrator
function bm_add_admin_caps() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        // Capabilities necessárias para gerenciar 'bm_book'
        $caps = [
            'edit_bm_book',
            'read_bm_book',
            'delete_bm_book',
            'edit_bm_books',
            'edit_others_bm_books',
            'publish_bm_books',
            'read_private_bm_books',
            'delete_bm_books',
            'delete_private_bm_books',
            'delete_published_bm_books',
            'delete_others_bm_books',
            'edit_private_bm_books',
            'edit_published_bm_books',
        ];
        foreach ($caps as $cap) {
            $admin_role->add_cap($cap);
        }
    }
}

// Remove as capabilities do administrator (usado em uninstall.php, conforme escopo.md)
function bm_remove_admin_caps() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $caps = [
            'edit_bm_book',
            'read_bm_book',
            'delete_bm_book',
            'edit_bm_books',
            'edit_others_bm_books',
            'publish_bm_books',
            'read_private_bm_books',
            'delete_bm_books',
            'delete_private_bm_books',
            'delete_published_bm_books',
            'delete_others_bm_books',
            'edit_private_bm_books',
            'edit_published_bm_books',
        ];
        foreach ($caps as $cap) {
            $admin_role->remove_cap($cap);
        }
    }
}

// Hook de ativação: registra CPT, adiciona caps de admin e faz flush das rewrite rules
function bm_plugin_activation() {
    bm_register_book_cpt();
    bm_add_admin_caps();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bm_plugin_activation' );

// Hook de desativação: apenas flush das rewrite rules, conforme escopo.md
function bm_plugin_deactivation() {
    // Conforme o escopo.md, na desativação apenas flush_rewrite_rules() é executado.
    // A remoção de capabilities deve ocorrer apenas em uninstall.php.
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bm_plugin_deactivation' );

// --- FASE 2: Metaboxes e Campos Personalizados ---

// Função para renderizar o HTML da metabox de detalhes do livro
function bm_render_book_details_metabox( $post ) {
    // Tarefa 3: Adiciona um campo nonce para verificação de segurança
    wp_nonce_field( 'bm_save_book_details', 'bm_book_details_nonce' );

    // Recupera os metadados existentes, se houver, para preenchimento dos campos
    // Conforme escopo.md e roadmap.md, campos devem ser: title (implícito), _bm_author, _bm_publisher
    $author = get_post_meta( $post->ID, '_bm_author', true );
    $publisher = get_post_meta( $post->ID, '_bm_publisher', true );

    ?>
    <p>
        <label for="_bm_author"><?php _e( 'Autor:', 'book-manager' ); ?></label>
        <input type="text" id="_bm_author" name="_bm_author" value="<?php echo esc_attr( $author ); ?>" size="50" />
    </p>
    <p>
        <label for="_bm_publisher"><?php _e( 'Editora:', 'book-manager' ); ?></label>
        <input type="text" id="_bm_publisher" name="_bm_publisher" value="<?php echo esc_attr( $publisher ); ?>" size="50" />
    </p>
    <?php
}

// Função para adicionar a metabox de detalhes do livro
// Tarefa 1: add_meta_box() no hook add_meta_boxes
function bm_add_book_details_metabox() {
    add_meta_box(
        'bm_book_details', // ID único da metabox
        __( 'Detalhes do Livro', 'book-manager' ), // Título da metabox
        'bm_render_book_details_metabox', // Função de callback para renderizar o conteúdo (inclui Tarefa 2 e 3)
        'bm_book', // O CPT ao qual a metabox será adicionada
        'normal', // Contexto (normal, side, advanced)
        'high' // Prioridade (high, core, default, low)
    );
}
add_action( 'add_meta_boxes', 'bm_add_book_details_metabox' );

// Hook para salvar os metadados da metabox (Tarefa 4)
function bm_save_book_details_metabox_data( $post_id ) {
    // Verifica se o nonce é válido (parte da Tarefa 4)
    if ( ! isset( $_POST['bm_book_details_nonce'] ) || ! wp_verify_nonce( $_POST['bm_book_details_nonce'], 'bm_save_book_details' ) ) {
        return;
    }

    // Verifica se é um salvamento automático (parte da Tarefa 4)
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Verifica as permissões do usuário (parte da Tarefa 4)
    // Conforme exigido pelo escopo.md (Linha 44), a permissão necessária é 'manage_options'.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Campos a serem salvos, conforme escopo.md (_bm_author e _bm_publisher)
    $fields = array( '_bm_author', '_bm_publisher' );

    foreach ( $fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            // Sanitiza e salva o valor (parte da Tarefa 4)
            // Usando sanitize_text_field como especificado no escopo.md
            $sanitized_value = sanitize_text_field( $_POST[ $field ] );
            update_post_meta( $post_id, $field, $sanitized_value );
        }
        // O bloco 'else' que continha delete_post_meta foi removido,
        // pois não é especificado no escopo.md e pode remover dados úteis.
        // update_post_meta com string vazia salva corretamente o valor como vazio.
    }
}
add_action( 'save_post_bm_book', 'bm_save_book_details_metabox_data' );

// --- FASE 4: Interface de Listagem e Visualização ---
// Tarefa 1: Customizar a listagem nativa do CPT `bm_book` para exibir colunas de Título, Autor e Editora.
// Tarefa 2: Implementar funcionalidade de busca/filtro por Título, Autor e Editora na listagem customizada.

/**
 * Adiciona colunas customizadas à lista de livros no admin.
 * Usado para exibir Autor e Editora na listagem nativa.
 */
function bm_manage_book_columns( $columns ) {
    $new_columns = array();
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        // Insere as colunas de Autor e Editora após a coluna 'title'
        if ($key === 'title') {
            $new_columns['_bm_author'] = __('Autor', 'book-manager');
            $new_columns['_bm_publisher'] = __('Editora', 'book-manager');
        }
    }
    // Garante que as colunas sejam adicionadas mesmo se 'title' não for encontrada (caso raro)
    if (!isset($new_columns['_bm_author'])) {
        $new_columns['_bm_author'] = __('Autor', 'book-manager');
        $new_columns['_bm_publisher'] = __('Editora', 'book-manager');
    }
    return $new_columns;
}
add_filter('manage_bm_book_posts_columns', 'bm_manage_book_columns');

/**
 * Exibe o conteúdo das colunas customizadas (Autor e Editora).
 * Esta função é chamada para cada linha na listagem de posts.
 */
function bm_manage_book_custom_column_content($column_key, $post_id) {
    if ('_bm_author' === $column_key) {
        echo esc_html(get_post_meta($post_id, '_bm_author', true));
    } elseif ('_bm_publisher' === $column_key) {
        echo esc_html(get_post_meta($post_id, '_bm_publisher', true));
    }
}
add_action('manage_bm_book_posts_custom_column', 'bm_manage_book_custom_column_content', 10, 2);

/**
 * Adiciona campos de filtro (busca/select) acima da tabela de listagem de livros.
 * Utiliza o hook 'restrict_manage_posts'.
 */
function bm_add_book_filter_form() {
    // Verifica se estamos na página de listagem do CPT 'bm_book'
    global $typenow;
    if ('bm_book' !== $typenow) {
        return;
    }

    // Recupera os valores atuais de filtro, se existirem, e sanitiza
    $filter_author = isset($_GET['_bm_author']) ? sanitize_text_field($_GET['_bm_author']) : '';
    $filter_publisher = isset($_GET['_bm_publisher']) ? sanitize_text_field($_GET['_bm_publisher']) : '';
    ?>
    <style>
        .bm-filter-form p {
            display: inline-block;
            margin-right: 15px;
            vertical-align: top;
        }
        .bm-filter-form label {
            margin-right: 5px;
            font-weight: bold;
        }
        .bm-filter-form input[type="text"],
        .bm-filter-form select {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
    </style>
    <div class="wrap bm-filter-form">
        <form method="get">
            <!-- Mantém os parâmetros de ordenação e busca nativa do WordPress -->
            <?php
            // Define os valores padrão de ordenação se não estiverem definidos
            $current_orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'title'; 
            $current_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC'; 

            // Campos ocultos para manter a ordenação e busca nativa
            echo '<input type="hidden" name="post_type" value="bm_book">';
            // REMOVIDO: echo '<input type="hidden" name="page" value="edit.php">'; // Conflitava com post_type
            echo '<input type="hidden" name="orderby" value="' . esc_attr($current_orderby) . '">';
            echo '<input type="hidden" name="order" value="' . esc_attr($current_order) . '">';
            
            // Se houver busca nativa por título, mantém o parâmetro 's'
            if (isset($_GET['s']) && !empty($_GET['s'])) {
                echo '<input type="hidden" name="s" value="' . esc_attr(sanitize_text_field($_GET['s'])) . '">';
            }

            ?>
            <p>
                <label for="_bm_author"><?php _e('Autor:', 'book-manager'); ?></label>
                <input type="text" id="_bm_author" name="_bm_author" value="<?php echo esc_attr($filter_author); ?>" placeholder="<?php _e('Filtrar por autor', 'book-manager'); ?>">
            </p>
            <p>
                <label for="_bm_publisher"><?php _e('Editora:', 'book-manager'); ?></label>
                <input type="text" id="_bm_publisher" name="_bm_publisher" value="<?php echo esc_attr($filter_publisher); ?>" placeholder="<?php _e('Filtrar por editora', 'book-manager'); ?>">
            </p>
            <input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php _e('Filtrar', 'book-manager'); ?>">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book'); ?>" class="button"><?php _e('Limpar Filtros', 'book-manager'); ?></a>
        </form>
    </div>
    <?php
}
// Adiciona o formulário de filtro acima da tabela de listagem
add_action('restrict_manage_posts', 'bm_add_book_filter_form');

/**
 * Modifica a query principal para incluir os filtros de metadados (Autor e Editora).
 * Utiliza o hook 'pre_get_posts'. Reutiliza a busca nativa de título.
 */
function bm_filter_books_by_metadata($query) {
    // Verifica se é a query principal da listagem de admin e se é para o CPT correto
    // e se os filtros foram aplicados (ou seja, se a página foi submetida com parâmetros de filtro)
    if (!is_admin() || !$query->is_main_query() || 'bm_book' !== $query->get('post_type')) {
        return;
    }

    $meta_query_args = array();

    // Filtro por Autor
    if (isset($_GET['_bm_author']) && !empty($_GET['_bm_author'])) {
        $meta_query_args[] = array(
            'key' => '_bm_author',
            'value' => sanitize_text_field($_GET['_bm_author']),
            'compare' => 'LIKE', // Usa LIKE para permitir buscas parciais
        );
    }

    // Filtro por Editora
    if (isset($_GET['_bm_publisher']) && !empty($_GET['_bm_publisher'])) {
        $meta_query_args[] = array(
            'key' => '_bm_publisher',
            'value' => sanitize_text_field($_GET['_bm_publisher']),
            'compare' => 'LIKE',
        );
    }

    // Se houver filtros de metadados aplicados, adiciona a meta query à consulta
    if (!empty($meta_query_args)) {
        // Combina a meta query com a query existente (se houver)
        $existing_meta_query = $query->get('meta_query') ?: array();
        // Se a chave 'relation' não existir, define como 'AND' por padrão para múltiplos filtros de metadados
        if (empty($existing_meta_query) || !isset($existing_meta_query['relation'])) {
             $meta_query = array_merge($existing_meta_query, $meta_query_args);
             $meta_query['relation'] = 'AND'; // Garante que múltiplos filtros de metadados sejam combinados com AND
        } else {
             // Se já existe uma meta_query com 'relation', apenas anexa os novos args
             $meta_query = $existing_meta_query;
             foreach($meta_query_args as $arg) {
                  $meta_query[] = $arg;
             }
        }
        $query->set('meta_query', $meta_query);
        
        // Define a ordenação por meta_value se um filtro de metadado estiver ativo.
        // Prioriza ordenação por Autor se ambos estiverem filtrados e ordenados.
        if (isset($_GET['_bm_author']) && !empty($_GET['_bm_author'])) {
            $query->set('meta_key', '_bm_author');
            $query->set('orderby', 'meta_value');
        } elseif (isset($_GET['_bm_publisher']) && !empty($_GET['_bm_publisher'])) {
            $query->set('meta_key', '_bm_publisher');
            $query->set('orderby', 'meta_value');
        }
    }

    // Garante que a busca nativa por título (parâmetro 's') seja considerada
    // O WordPress geralmente combina automaticamente a busca nativa com meta queries.
    
    // Configura a ordenação padrão se nenhum filtro de metadado estiver ativo E a busca nativa por 's' não estiver ativa.
    // Se a busca nativa por 's' estiver ativa, o WP cuida da ordenação por 'title' por padrão.
    if ( ! $query->get('meta_query') && !isset($_GET['s']) ) {
         $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'title';
         $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
         
         // Se a ordenação for por autor ou editora, mas não há filtros de metadados ativos no GET
         // (o que significa que a meta_query não foi definida), precisamos definir meta_key para que a ordenação funcione.
         // Isso pode acontecer se o usuário clica em um link de ordenação sem ter aplicado filtros de texto.
         if ($orderby === '_bm_author') {
             $query->set('meta_key', '_bm_author');
         } elseif ($orderby === '_bm_publisher') {
             $query->set('meta_key', '_bm_publisher');
         }
         
         $query->set('orderby', $orderby);
         $query->set('order', $order);
    } elseif ($query->get('meta_query') && !isset($_GET['orderby'])) {
         // Se há meta_query ativa mas nenhuma ordenação via GET especificada,
         // garantimos que a meta_key esteja definida para a ordenação padrão (seja a primeira meta_key na query)
         // ou deixamos o WP decidir a ordenação padrão.
         // A lógica acima já lida com a definição de meta_key se filtros de metadados ativos estiverem presentes.
    } elseif (isset($_GET['orderby']) && $_GET['orderby'] === 'title' && !$query->get('meta_query')) {
         // Se a ordenação for explicitamente por título e não houver meta_query, definimos title e ASC
         $query->set('orderby', 'title');
         $query->set('order', isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC');
    }
}
add_action('pre_get_posts', 'bm_filter_books_by_metadata');
