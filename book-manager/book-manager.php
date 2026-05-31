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
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'bm_book',
        'map_meta_cap'       => true,
        'supports'           => array( 'title' ),
        'delete_with_user'   => false,
        'menu_icon'          => 'dashicons-book',
        'rewrite'            => false,
    );

    register_post_type( 'bm_book', $args );
}
add_action( 'init', 'bm_register_book_cpt' );

function bm_add_admin_caps() {
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
            $admin_role->add_cap($cap);
        }
    }
}

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

function bm_plugin_activation() {
    bm_register_book_cpt();
    bm_add_admin_caps();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bm_plugin_activation' );

function bm_plugin_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bm_plugin_deactivation' );

// --- FASE 2: Metaboxes e Campos Personalizados ---

function bm_render_book_details_metabox( $post ) {
    wp_nonce_field( 'bm_save_book_details', 'bm_book_details_nonce' );

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

function bm_add_book_details_metabox() {
    add_meta_box(
        'bm_book_details',
        __( 'Detalhes do Livro', 'book-manager' ),
        'bm_render_book_details_metabox',
        'bm_book',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'bm_add_book_details_metabox' );

function bm_save_book_details_metabox_data( $post_id ) {
    if ( ! isset( $_POST['bm_book_details_nonce'] ) || ! wp_verify_nonce( $_POST['bm_book_details_nonce'], 'bm_save_book_details' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $fields = array( '_bm_author', '_bm_publisher' );

    foreach ( $fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
        }
    }
}
add_action( 'save_post_bm_book', 'bm_save_book_details_metabox_data' );

// --- FASE 4: Interface de Listagem e Visualização ---

function bm_manage_book_columns( $columns ) {
    $new_columns = array();
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'title') {
            $new_columns['_bm_author'] = __('Autor', 'book-manager');
            $new_columns['_bm_publisher'] = __('Editora', 'book-manager');
        }
    }
    if (!isset($new_columns['_bm_author'])) {
        $new_columns['_bm_author'] = __('Autor', 'book-manager');
        $new_columns['_bm_publisher'] = __('Editora', 'book-manager');
    }
    return $new_columns;
}
add_filter('manage_bm_book_posts_columns', 'bm_manage_book_columns');

function bm_manage_book_custom_column_content($column_key, $post_id) {
    if ('_bm_author' === $column_key) {
        echo esc_html(get_post_meta($post_id, '_bm_author', true));
    } elseif ('_bm_publisher' === $column_key) {
        echo esc_html(get_post_meta($post_id, '_bm_publisher', true));
    }
}
add_action('manage_bm_book_posts_custom_column', 'bm_manage_book_custom_column_content', 10, 2);

function bm_add_book_filter_form() {
    global $typenow;
    if ('bm_book' !== $typenow) {
        return;
    }

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
            <?php
            $current_orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'title'; 
            $current_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC'; 

            echo '<input type="hidden" name="post_type" value="bm_book">';
            echo '<input type="hidden" name="orderby" value="' . esc_attr($current_orderby) . '">';
            echo '<input type="hidden" name="order" value="' . esc_attr($current_order) . '">';
            
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
add_action('restrict_manage_posts', 'bm_add_book_filter_form');

function bm_filter_books_by_metadata($query) {
    if (!is_admin() || !$query->is_main_query() || 'bm_book' !== $query->get('post_type')) {
        return;
    }

    $meta_query_args = array();

    if (isset($_GET['_bm_author']) && !empty($_GET['_bm_author'])) {
        $meta_query_args[] = array(
            'key' => '_bm_author',
            'value' => sanitize_text_field($_GET['_bm_author']),
            'compare' => 'LIKE',
        );
    }

    if (isset($_GET['_bm_publisher']) && !empty($_GET['_bm_publisher'])) {
        $meta_query_args[] = array(
            'key' => '_bm_publisher',
            'value' => sanitize_text_field($_GET['_bm_publisher']),
            'compare' => 'LIKE',
        );
    }

    if (!empty($meta_query_args)) {
        $existing_meta_query = $query->get('meta_query') ?: array();
        if (empty($existing_meta_query) || !isset($existing_meta_query['relation'])) {
             $meta_query = array_merge($existing_meta_query, $meta_query_args);
             $meta_query['relation'] = 'AND';
        } else {
             $meta_query = $existing_meta_query;
             foreach($meta_query_args as $arg) {
                  $meta_query[] = $arg;
             }
        }
        $query->set('meta_query', $meta_query);
        
        if (isset($_GET['_bm_author']) && !empty($_GET['_bm_author'])) {
            $query->set('meta_key', '_bm_author');
            $query->set('orderby', 'meta_value');
        } elseif (isset($_GET['_bm_publisher']) && !empty($_GET['_bm_publisher'])) {
            $query->set('meta_key', '_bm_publisher');
            $query->set('orderby', 'meta_value');
        }
    }

    if ( ! $query->get('meta_query') && !isset($_GET['s']) ) {
         $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'title';
         $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
         
         if ($orderby === '_bm_author') {
             $query->set('meta_key', '_bm_author');
         } elseif ($orderby === '_bm_publisher') {
             $query->set('meta_key', '_bm_publisher');
         }
         
         $query->set('orderby', $orderby);
         $query->set('order', $order);
    } elseif ($query->get('meta_query') && !isset($_GET['orderby'])) {
    } elseif (isset($_GET['orderby']) && $_GET['orderby'] === 'title' && !$query->get('meta_query')) {
         $query->set('orderby', 'title');
         $query->set('order', isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC');
    }
}
add_action('pre_get_posts', 'bm_filter_books_by_metadata');

// --- FASE 6A: Importação CSV ---

function bm_add_csv_import_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=bm_book',
        'Importar CSV',
        'Importar CSV',
        'manage_options',
        'bm_csv_import',
        'bm_render_csv_import_page'
    );
}
add_action('admin_menu', 'bm_add_csv_import_submenu_page');

function bm_render_csv_import_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $message     = '';
    $preview     = array();
    $duplicates  = array();
    $stage       = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';

    // Estágio 2: Processar a importação após decisão do usuário
    if ('process' === $stage && isset($_POST['bm_csv_import_nonce']) && wp_verify_nonce($_POST['bm_csv_import_nonce'], 'bm_csv_import_action')) {
        $skip_duplicates = isset($_POST['skip_duplicates']) && '1' === $_POST['skip_duplicates'];
        $imported = 0;
        $skipped  = 0;
        $dup_skipped = 0;

        if (!empty($_POST['csv_data'])) {
            $rows = json_decode(stripslashes($_POST['csv_data']), true);
            foreach ($rows as $row) {
                $title     = sanitize_text_field($row[0]);
                $author    = sanitize_text_field($row[1]);
                $publisher = sanitize_text_field($row[2]);

                if (empty($title)) {
                    $skipped++;
                    continue;
                }

                $exists = bm_find_duplicate_book($title, $author, $publisher);
                if ($exists && $skip_duplicates) {
                    $dup_skipped++;
                    continue;
                }

                $post_id = wp_insert_post(array(
                    'post_type'   => 'bm_book',
                    'post_title'  => $title,
                    'post_status' => 'publish',
                ));

                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, '_bm_author', $author);
                    update_post_meta($post_id, '_bm_publisher', $publisher);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }
        $message = sprintf(__('%d importados, %d ignorados (sem título), %d duplicados pulados.', 'book-manager'), $imported, $skipped, $dup_skipped);
    }

    // Estágio 1: Upload e prévia
    if ('' === $stage && isset($_FILES['csv_file']) && isset($_POST['bm_csv_import_nonce'])) {
        if (!wp_verify_nonce($_POST['bm_csv_import_nonce'], 'bm_csv_import_action')) {
            $message = __('Erro de segurança.', 'book-manager');
        } elseif (empty($_FILES['csv_file']['tmp_name'])) {
            $message = __('Nenhum arquivo enviado.', 'book-manager');
        } else {
            $filetype = wp_check_filetype($_FILES['csv_file']['name']);
            if ('csv' !== $filetype['ext']) {
                $message = __('Formato inválido.', 'book-manager');
            } else {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                if ($handle) {
                    $line = 0;
                    while (($data = fgetcsv($handle, 0, ';')) !== false) {
                        $line++;
                        if (1 === $line) continue;
                        $title     = isset($data[0]) ? trim(sanitize_text_field($data[0])) : '';
                        $author    = isset($data[1]) ? sanitize_text_field($data[1]) : '';
                        $publisher = isset($data[2]) ? sanitize_text_field($data[2]) : '';
                        if (empty($title)) continue;
                        $row = array($title, $author, $publisher);
                        $preview[] = $row;
                        if (bm_find_duplicate_book($title, $author, $publisher)) {
                            $duplicates[] = $row;
                        }
                    }
                    fclose($handle);
                }
            }
        }
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Importar Livros via CSV', 'book-manager'); ?></h1>
        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($preview)): ?>
            <h2><?php echo sprintf(__('%d livros encontrados no arquivo.', 'book-manager'), count($preview)); ?></h2>
            <?php if (!empty($duplicates)): ?>
                <div class="notice notice-warning">
                    <p><?php echo sprintf(__('%d livros já existem no acervo.', 'book-manager'), count($duplicates)); ?></p>
                    <ul style="margin-left:20px;list-style:disc;">
                        <?php foreach ($duplicates as $d): ?>
                            <li><?php echo esc_html($d[0] . ' — ' . $d[1] . ' / ' . $d[2]); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('bm_csv_import_action', 'bm_csv_import_nonce'); ?>
                <input type="hidden" name="import_stage" value="process">
                <input type="hidden" name="csv_data" value="<?php echo esc_attr(json_encode($preview)); ?>">
                <?php if (!empty($duplicates)): ?>
                    <p><strong><?php _e('Como deseja tratar os duplicados?', 'book-manager'); ?></strong></p>
                    <label><input type="radio" name="skip_duplicates" value="1" checked> <?php _e('Pular duplicados', 'book-manager'); ?></label><br>
                    <label><input type="radio" name="skip_duplicates" value="0"> <?php _e('Importar mesmo assim', 'book-manager'); ?></label>
                <?php else: ?>
                    <input type="hidden" name="skip_duplicates" value="0">
                <?php endif; ?>
                <p><?php submit_button('Confirmar Importação'); ?></p>
            </form>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bm_csv_import_action', 'bm_csv_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="csv_file"><?php _e('Arquivo CSV', 'book-manager'); ?></label></th>
                        <td>
                            <input type="file" id="csv_file" name="csv_file" accept=".csv" />
                            <p class="description"><?php _e('Formato: Título;Autor;Editora. Primeira linha ignorada.', 'book-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Enviar Arquivo'); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

function bm_find_duplicate_book($title, $author, $publisher) {
    $existing = get_posts(array(
        'post_type'      => 'bm_book',
        'title'          => $title,
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ));

    if (empty($existing)) return false;

    foreach ($existing as $book) {
        $existing_author    = get_post_meta($book->ID, '_bm_author', true);
        $existing_publisher = get_post_meta($book->ID, '_bm_publisher', true);
        if ($author === $existing_author && $publisher === $existing_publisher) {
            return $book->ID;
        }
    }
    return false;
}

// --- FASE 6B: Exportação CSV ---

function bm_add_csv_export_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=bm_book',
        'Exportar CSV',
        'Exportar CSV',
        'manage_options',
        'bm_csv_export',
        'bm_render_csv_export_page'
    );
}
add_action('admin_menu', 'bm_add_csv_export_submenu_page');

function bm_handle_csv_export() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['bm_csv_export_nonce']) || !wp_verify_nonce($_POST['bm_csv_export_nonce'], 'bm_csv_export_action')) {
        return;
    }

    $books = get_posts(array(
        'post_type'      => 'bm_book',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ));

    if (empty($books)) {
        return;
    }

    $total = count($books);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="livros.csv"');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, array('Título', 'Autor', 'Editora'), ';');

    foreach ($books as $book) {
        fputcsv($output, array(
            $book->post_title,
            get_post_meta($book->ID, '_bm_author', true),
            get_post_meta($book->ID, '_bm_publisher', true),
        ), ';');
    }

    fclose($output);
    exit;
}

add_action('admin_init', 'bm_handle_csv_export');


function bm_render_csv_export_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $book_count = wp_count_posts('bm_book');
    $total = $book_count->publish + $book_count->draft + $book_count->trash;

    $exported = isset($_GET['exported']) ? intval($_GET['exported']) : 0;
    ?>
    <div class="wrap">
        <h1><?php _e('Exportar Livros para CSV', 'book-manager'); ?></h1>
        <?php if ($exported > 0): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo sprintf(__('Sucesso! %d livros exportados.', 'book-manager'), $exported); ?></p>
            </div>
        <?php endif; ?>
        <p><?php echo sprintf(__('%d livros disponíveis para exportação.', 'book-manager'), $total); ?></p>
        <form method="post">
            <?php wp_nonce_field('bm_csv_export_action', 'bm_csv_export_nonce'); ?>
            <?php submit_button('Baixar CSV'); ?>
        </form>
    </div>
    <?php
}