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

function bm_admin_scripts($hook) {
    if (strpos($hook, 'bm_dynamic_fields') === false && strpos($hook, 'bm_book') === false) return;
    wp_enqueue_script('jquery-ui-sortable');
}
add_action('admin_enqueue_scripts', 'bm_admin_scripts');

// --- FASE 1: CPT ---
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
        'supports'           => array( 'title', 'thumbnail' ),
        'delete_with_user'   => false,
        'menu_icon'          => 'dashicons-book',
        'rewrite'            => false,
    );
    register_post_type( 'bm_book', $args );
}
add_action( 'init', 'bm_register_book_cpt' );

// --- FASE 7C: Taxonomias ---
function bm_register_taxonomies() {
    register_taxonomy('bm_genre', 'bm_book', array(
        'label'        => __('Gêneros', 'book-manager'),
        'labels'       => array(
            'name'              => __('Gêneros', 'book-manager'),
            'singular_name'     => __('Gênero', 'book-manager'),
            'search_items'      => __('Buscar Gêneros', 'book-manager'),
            'all_items'         => __('Todos os Gêneros', 'book-manager'),
            'parent_item'       => __('Gênero Pai', 'book-manager'),
            'parent_item_colon' => __('Gênero Pai:', 'book-manager'),
            'edit_item'         => __('Editar Gênero', 'book-manager'),
            'update_item'       => __('Atualizar Gênero', 'book-manager'),
            'add_new_item'      => __('Adicionar Novo Gênero', 'book-manager'),
            'new_item_name'     => __('Nome do Novo Gênero', 'book-manager'),
            'menu_name'         => __('Gêneros', 'book-manager'),
        ),
        'rewrite'      => false,
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'capabilities' => array(
            'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options', 'assign_terms' => 'manage_options',
        ),
    ));
    register_taxonomy('bm_category', 'bm_book', array(
        'label'        => __('Categorias', 'book-manager'),
        'rewrite'      => false,
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => true,
        'capabilities' => array(
            'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options', 'assign_terms' => 'manage_options',
        ),
    ));
}
add_action('init', 'bm_register_taxonomies');

// --- FASE 1: Capabilities ---
function bm_add_admin_caps() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $caps = ['edit_bm_book','read_bm_book','delete_bm_book','edit_bm_books','edit_others_bm_books','publish_bm_books','read_private_bm_books','delete_bm_books','delete_private_bm_books','delete_published_bm_books','delete_others_bm_books','edit_private_bm_books','edit_published_bm_books'];
        foreach ($caps as $cap) $admin_role->add_cap($cap);
    }
}
function bm_remove_admin_caps() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $caps = ['edit_bm_book','read_bm_book','delete_bm_book','edit_bm_books','edit_others_bm_books','publish_bm_books','read_private_bm_books','delete_bm_books','delete_private_bm_books','delete_published_bm_books','delete_others_bm_books','edit_private_bm_books','edit_published_bm_books'];
        foreach ($caps as $cap) $admin_role->remove_cap($cap);
    }
}
function bm_plugin_activation() { bm_register_book_cpt(); bm_register_taxonomies(); bm_add_admin_caps(); flush_rewrite_rules(); }
register_activation_hook( __FILE__, 'bm_plugin_activation' );
function bm_plugin_deactivation() { flush_rewrite_rules(); }
register_deactivation_hook( __FILE__, 'bm_plugin_deactivation' );

// --- FASE 2: Metaboxes e Campos Personalizados ---
function bm_render_book_details_metabox( $post ) {
    wp_nonce_field( 'bm_save_book_details', 'bm_book_details_nonce' );
    $fixed_fields = array(
        '_bm_author'    => array('label' => __('Autor:','book-manager'), 'type' => 'text'),
        '_bm_publisher' => array('label' => __('Editora:','book-manager'), 'type' => 'text'),
        '_bm_isbn'      => array('label' => __('ISBN:','book-manager'), 'type' => 'text'),
        '_bm_location'  => array('label' => __('Localização:','book-manager'), 'type' => 'text'),
        '_bm_copies'    => array('label' => __('Exemplares:','book-manager'), 'type' => 'number'),
    );
    $dynamic_fields = get_option('bm_dynamic_fields', array());
    $saved_order = get_option('bm_field_order', array());
    $saved_visibility = get_option('bm_field_visibility', array());
    $all_fields = array();
    foreach ($saved_order as $key) {
        if (isset($fixed_fields[$key])) $all_fields[$key] = array_merge($fixed_fields[$key], array('key' => $key, 'source' => 'fixed'));
        elseif (isset($dynamic_fields[$key])) $all_fields[$key] = array('label' => $key . ':', 'type' => $dynamic_fields[$key]['type'], 'key' => '_bm_dynamic_' . sanitize_key($key), 'source' => 'dynamic');
    }
    foreach ($fixed_fields as $key => $info) { if (!isset($all_fields[$key])) $all_fields[$key] = array_merge($info, array('key' => $key, 'source' => 'fixed')); }
    foreach ($dynamic_fields as $key => $info) { if (!isset($all_fields[$key])) $all_fields[$key] = array('label' => $key . ':', 'type' => $info['type'], 'key' => '_bm_dynamic_' . sanitize_key($key), 'source' => 'dynamic'); }
    foreach ($all_fields as $key => $field) {
        $visible = !isset($saved_visibility[$key]) || $saved_visibility[$key];
        if (!$visible) continue;
        $meta_key = $field['key'];
        $value = get_post_meta($post->ID, $meta_key, true);
        if ($field['type'] === 'number') {
            echo '<p><label for="'.esc_attr($meta_key).'">'.esc_html($field['label']).'</label> <input type="number" id="'.esc_attr($meta_key).'" name="'.esc_attr($meta_key).'" value="'.esc_attr($value).'" min="0" size="10" /></p>';
        } elseif ($field['type'] === 'textarea') {
            echo '<p><label for="'.esc_attr($meta_key).'">'.esc_html($field['label']).'</label><br><textarea id="'.esc_attr($meta_key).'" name="'.esc_attr($meta_key).'" rows="4" style="width:100%;max-width:500px;">'.esc_textarea($value).'</textarea></p>';
        } else {
            echo '<p><label for="'.esc_attr($meta_key).'">'.esc_html($field['label']).'</label> <input type="text" id="'.esc_attr($meta_key).'" name="'.esc_attr($meta_key).'" value="'.esc_attr($value).'" size="50" /></p>';
        }
    }
    $audit_log = get_post_meta($post->ID, '_bm_audit_log', true);
    if (!empty($audit_log)) {
        echo '<hr><h4>'.__('Histórico de Ações','book-manager').'</h4><ul style="font-size:12px;color:#666;">';
        foreach (array_reverse($audit_log) as $entry) echo '<li>'.esc_html($entry['time']).' — '.esc_html($entry['user']).': '.esc_html($entry['action']).'</li>';
        echo '</ul>';
    }
}
function bm_add_book_details_metabox() { add_meta_box('bm_book_details', __('Detalhes do Livro','book-manager'), 'bm_render_book_details_metabox', 'bm_book', 'normal', 'high'); }
add_action('add_meta_boxes', 'bm_add_book_details_metabox');
function bm_save_book_details_metabox_data( $post_id ) {
    if (!isset($_POST['bm_book_details_nonce']) || !wp_verify_nonce($_POST['bm_book_details_nonce'],'bm_save_book_details')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('manage_options')) return;
    foreach (array('_bm_author','_bm_publisher','_bm_isbn','_bm_location') as $f) {
        if (isset($_POST[$f])) update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
    }
    if (isset($_POST['_bm_copies'])) update_post_meta($post_id, '_bm_copies', absint($_POST['_bm_copies']));
    $dynamic_fields = get_option('bm_dynamic_fields', array());
    foreach ($dynamic_fields as $field => $info) {
        $key = '_bm_dynamic_' . sanitize_key($field);
        if (isset($_POST[$key])) update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
    }
}
add_action('save_post_bm_book', 'bm_save_book_details_metabox_data');

// --- FASE 4: Listagem e Filtros ---
function bm_manage_book_columns($columns) {
    $new = array();
    foreach ($columns as $k => $t) { $new[$k] = $t; if ($k==='title') { $new['_bm_author']=__('Autor','book-manager'); $new['_bm_publisher']=__('Editora','book-manager'); $new['taxonomy-bm_genre']=__('Gênero','book-manager'); $new['taxonomy-bm_category']=__('Categoria','book-manager'); } }
    if (!isset($new['taxonomy-bm_genre'])) { $new['taxonomy-bm_genre']=__('Gênero','book-manager'); $new['taxonomy-bm_category']=__('Categoria','book-manager'); }
    return $new;
}
add_filter('manage_bm_book_posts_columns','bm_manage_book_columns');
function bm_manage_book_custom_column_content($col,$pid) {
    if ('_bm_author'===$col) echo esc_html(get_post_meta($pid,'_bm_author',true));
    elseif ('_bm_publisher'===$col) echo esc_html(get_post_meta($pid,'_bm_publisher',true));
}
add_action('manage_bm_book_posts_custom_column','bm_manage_book_custom_column_content',10,2);
function bm_add_book_filter_form() {
    global $typenow; if ('bm_book'!==$typenow) return;
    $fa = isset($_GET['_bm_author'])?sanitize_text_field($_GET['_bm_author']):'';
    $fp = isset($_GET['_bm_publisher'])?sanitize_text_field($_GET['_bm_publisher']):'';
    ?><style>.bm-filter-form p{display:inline-block;margin-right:15px;vertical-align:top}.bm-filter-form label{margin-right:5px;font-weight:bold}.bm-filter-form input[type="text"],.bm-filter-form select{padding:5px;border:1px solid #ccc;border-radius:4px}</style>
    <div class="wrap bm-filter-form"><form method="get">
    <?php
    echo '<input type="hidden" name="post_type" value="bm_book">';
    echo '<input type="hidden" name="orderby" value="'.esc_attr(isset($_GET['orderby'])?sanitize_text_field($_GET['orderby']):'title').'">';
    echo '<input type="hidden" name="order" value="'.esc_attr(isset($_GET['order'])?sanitize_text_field($_GET['order']):'ASC').'">';
    if (isset($_GET['s'])&&!empty($_GET['s'])) echo '<input type="hidden" name="s" value="'.esc_attr(sanitize_text_field($_GET['s'])).'">';
    ?>
    <p><label for="_bm_author"><?php _e('Autor:','book-manager'); ?></label><input type="text" id="_bm_author" name="_bm_author" value="<?php echo esc_attr($fa); ?>" placeholder="<?php _e('Filtrar por autor','book-manager'); ?>"></p>
    <p><label for="_bm_publisher"><?php _e('Editora:','book-manager'); ?></label><input type="text" id="_bm_publisher" name="_bm_publisher" value="<?php echo esc_attr($fp); ?>" placeholder="<?php _e('Filtrar por editora','book-manager'); ?>"></p>
    <?php
    wp_dropdown_categories(array('show_option_all'=>__('Todos os Gêneros','book-manager'),'taxonomy'=>'bm_genre','name'=>'bm_genre_filter','selected'=>isset($_GET['bm_genre_filter'])?$_GET['bm_genre_filter']:''));
    wp_dropdown_categories(array('show_option_all'=>__('Todas as Categorias','book-manager'),'taxonomy'=>'bm_category','name'=>'bm_category_filter','selected'=>isset($_GET['bm_category_filter'])?$_GET['bm_category_filter']:''));
    ?>
    <input type="submit" name="filter_action" class="button" value="<?php _e('Filtrar','book-manager'); ?>">
    <a href="<?php echo admin_url('edit.php?post_type=bm_book'); ?>" class="button"><?php _e('Limpar Filtros','book-manager'); ?></a>
    </form></div><?php
}
add_action('restrict_manage_posts','bm_add_book_filter_form');
function bm_filter_books_by_metadata($query) {
    if (!is_admin()||!$query->is_main_query()||'bm_book'!==$query->get('post_type')) return;
    $meta = array();
    if (isset($_GET['_bm_author'])&&!empty($_GET['_bm_author'])) $meta[]=array('key'=>'_bm_author','value'=>sanitize_text_field($_GET['_bm_author']),'compare'=>'LIKE');
    if (isset($_GET['_bm_publisher'])&&!empty($_GET['_bm_publisher'])) $meta[]=array('key'=>'_bm_publisher','value'=>sanitize_text_field($_GET['_bm_publisher']),'compare'=>'LIKE');
    if (!empty($meta)) { $meta['relation']='AND'; $query->set('meta_query',$meta); }
    if (isset($_GET['bm_genre_filter'])&&!empty($_GET['bm_genre_filter'])) { $tq=$query->get('tax_query')?:array(); $tq[]=array('taxonomy'=>'bm_genre','field'=>'term_id','terms'=>intval($_GET['bm_genre_filter'])); $query->set('tax_query',$tq); }
    if (isset($_GET['bm_category_filter'])&&!empty($_GET['bm_category_filter'])) { $tq=$query->get('tax_query')?:array(); $tq[]=array('taxonomy'=>'bm_category','field'=>'term_id','terms'=>intval($_GET['bm_category_filter'])); $query->set('tax_query',$tq); }
}
add_action('pre_get_posts','bm_filter_books_by_metadata');

// --- FASE 6A: Importação CSV ---
function bm_add_csv_import_submenu_page() { add_submenu_page('edit.php?post_type=bm_book','Importar CSV','Importar CSV','manage_options','bm_csv_import','bm_render_csv_import_page'); }
add_action('admin_menu','bm_add_csv_import_submenu_page');
function bm_render_csv_import_page() {
    if (!current_user_can('manage_options')) return;
    $message = ''; $preview = array(); $duplicates = array();
    $stage = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';
    $headers = array();
    if ('process'===$stage && isset($_POST['bm_csv_import_nonce']) && wp_verify_nonce($_POST['bm_csv_import_nonce'],'bm_csv_import_action')) {
        $skip_duplicates = isset($_POST['skip_duplicates'])&&'1'===$_POST['skip_duplicates'];
        $imported=0; $skipped=0; $dup_skipped=0;
        $mapping_raw = isset($_POST['mapping']) ? array_map('sanitize_text_field',$_POST['mapping']) : array();
        $mapping = array();
        foreach ($mapping_raw as $csv_index => $field) { if (!empty($field)) $mapping[$field] = intval($csv_index); }
        if (!empty($_POST['csv_data'])) {
            $rows = json_decode(stripslashes($_POST['csv_data']), true);
            foreach ($rows as $row) {
                $title=''; $author=''; $publisher='';
                if (isset($mapping['title'])&&isset($row[$mapping['title']])) $title=trim(sanitize_text_field($row[$mapping['title']]));
                if (isset($mapping['_bm_author'])&&isset($row[$mapping['_bm_author']])) $author=sanitize_text_field($row[$mapping['_bm_author']]);
                if (isset($mapping['_bm_publisher'])&&isset($row[$mapping['_bm_publisher']])) $publisher=sanitize_text_field($row[$mapping['_bm_publisher']]);
                if (empty($title)) { $skipped++; continue; }
                $exists = bm_find_duplicate_book($title,$author,$publisher);
                if ($exists && $skip_duplicates) { $dup_skipped++; continue; }
                $post_id = wp_insert_post(array('post_type'=>'bm_book','post_title'=>$title,'post_status'=>'publish'));
                if ($post_id && !is_wp_error($post_id)) {
                    if ($author) update_post_meta($post_id,'_bm_author',$author);
                    if ($publisher) update_post_meta($post_id,'_bm_publisher',$publisher);
                    foreach ($mapping as $field => $index) {
                        if (in_array($field,array('title','_bm_author','_bm_publisher'))) continue;
                        if (isset($row[$index])&&!empty($row[$index])) update_post_meta($post_id,$field,sanitize_text_field($row[$index]));
                    }
                    $imported++;
                    $cover_url = bm_fetch_cover_from_google($title, $author, $publisher);
                    if ($cover_url) {
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        $ir = wp_remote_get($cover_url, array('timeout' => 15));
                        if (!is_wp_error($ir)) {
                            $id = wp_remote_retrieve_body($ir);
                            if (!empty($id)) {
                                $ud = wp_upload_dir(); $fn = 'book-cover-' . $post_id . '-' . time() . '.jpg'; $fp = $ud['path'] . '/' . $fn;
                                file_put_contents($fp, $id);
                                $att = array('post_mime_type' => 'image/jpeg', 'post_title' => $title, 'post_content' => '', 'post_status' => 'inherit');
                                $aid = wp_insert_attachment($att, $fp, $post_id);
                                if (!is_wp_error($aid)) { $ad = wp_generate_attachment_metadata($aid, $fp); wp_update_attachment_metadata($aid, $ad); set_post_thumbnail($post_id, $aid); }
                            }
                        }
                    }
                } else { $skipped++; }
            }
        }
        $message = sprintf(__('%d importados, %d ignorados (sem título), %d duplicados pulados.','book-manager'),$imported,$skipped,$dup_skipped);
    }
    if ('map'===$stage && isset($_POST['bm_csv_import_nonce']) && wp_verify_nonce($_POST['bm_csv_import_nonce'],'bm_csv_import_action')) {
        $headers = isset($_POST['csv_headers']) ? json_decode(stripslashes($_POST['csv_headers']), true) : array();
    }
    if (''===$stage && isset($_FILES['csv_file']) && isset($_POST['bm_csv_import_nonce'])) {
        if (!wp_verify_nonce($_POST['bm_csv_import_nonce'],'bm_csv_import_action')) $message = __('Erro de segurança.','book-manager');
        elseif (empty($_FILES['csv_file']['tmp_name'])) $message = __('Nenhum arquivo enviado.','book-manager');
        else {
            $filetype = wp_check_filetype($_FILES['csv_file']['name']);
            if ('csv'!==$filetype['ext']) $message = __('Formato inválido.','book-manager');
            else {
                $handle = fopen($_FILES['csv_file']['tmp_name'],'r');
                if ($handle) {
                    $line=0; $all_rows=array();
                    while (($data = fgetcsv($handle,0,';')) !== false) {
                        if (1===++$line) { $headers = array_map('sanitize_text_field',$data); continue; }
                        $all_rows[] = $data;
                    }
                    fclose($handle);
                    if (empty($headers)) $message = __('Arquivo sem cabeçalho.','book-manager');
                    else { $stage='map'; }
                    $_POST['csv_data_preview'] = json_encode($all_rows, JSON_UNESCAPED_UNICODE);
                    $_POST['csv_headers'] = json_encode($headers, JSON_UNESCAPED_UNICODE);
                }
            }
        }
    }
    $system_fields = array(
        'title'=>__('Título (obrigatório)','book-manager'),
        '_bm_author'=>__('Autor','book-manager'),
        '_bm_publisher'=>__('Editora','book-manager'),
        '_bm_isbn'=>'ISBN',
        '_bm_location'=>__('Localização','book-manager'),
        '_bm_copies'=>__('Exemplares','book-manager'),
    );
    $dynamic_fields = get_option('bm_dynamic_fields', array());
    foreach ($dynamic_fields as $df => $info) $system_fields['_bm_dynamic_'.sanitize_key($df)] = $df.' ('.__('dinâmico','book-manager').')';
    ?>
    <div class="wrap">
        <h1><?php _e('Importar Livros via CSV','book-manager'); ?></h1>
        <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        <?php if ('map'===$stage && !empty($headers)): ?>
            <h2><?php _e('Mapeamento de Colunas','book-manager'); ?></h2>
            <p><?php _e('Associe cada coluna do seu arquivo ao campo correspondente no sistema.','book-manager'); ?></p>
            <form method="post">
                <?php wp_nonce_field('bm_csv_import_action','bm_csv_import_nonce'); ?>
                <input type="hidden" name="import_stage" value="process">
                <input type="hidden" name="csv_data" value="<?php echo esc_attr(json_encode(json_decode(stripslashes($_POST['csv_data_preview']),true), JSON_UNESCAPED_UNICODE)); ?>">
                <h3><?php _e('Mapear colunas','book-manager'); ?></h3>
                <?php foreach ($headers as $i => $h): ?>
                    <p><strong><?php echo esc_html($h); ?></strong> →
                    <select name="mapping[<?php echo $i; ?>]">
                        <option value=""><?php _e('— Ignorar —','book-manager'); ?></option>
                        <?php foreach ($system_fields as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select></p>
                <?php endforeach; ?>
                <p><strong><?php _e('Duplicados:','book-manager'); ?></strong>
                    <label><input type="radio" name="skip_duplicates" value="1" checked> <?php _e('Pular','book-manager'); ?></label>
                    <label><input type="radio" name="skip_duplicates" value="0"> <?php _e('Importar mesmo assim','book-manager'); ?></label></p>
                <?php submit_button(__('Importar','book-manager')); ?>
            </form>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bm_csv_import_action','bm_csv_import_nonce'); ?>
                <table class="form-table"><tr><th><label for="csv_file"><?php _e('Arquivo CSV','book-manager'); ?></label></th><td><input type="file" id="csv_file" name="csv_file" accept=".csv" /><p class="description"><?php _e('CSV com cabeçalho na primeira linha.','book-manager'); ?></p></td></tr></table>
                <?php submit_button(__('Enviar Arquivo','book-manager')); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
function bm_find_duplicate_book($title,$author,$publisher) {
    $existing = get_posts(array('post_type'=>'bm_book','title'=>$title,'posts_per_page'=>1,'post_status'=>'any'));
    if (empty($existing)) return false;
    foreach ($existing as $book) if ($author===get_post_meta($book->ID,'_bm_author',true)&&$publisher===get_post_meta($book->ID,'_bm_publisher',true)) return $book->ID;
    return false;
}

// --- FASE 6B: Exportação CSV ---
function bm_add_csv_export_submenu_page() { add_submenu_page('edit.php?post_type=bm_book','Exportar CSV','Exportar CSV','manage_options','bm_csv_export','bm_render_csv_export_page'); }
add_action('admin_menu','bm_add_csv_export_submenu_page');
function bm_handle_csv_export() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_POST['bm_csv_export_nonce'])||!wp_verify_nonce($_POST['bm_csv_export_nonce'],'bm_csv_export_action')) return;
    $args = array('post_type'=>'bm_book','posts_per_page'=>-1,'post_status'=>'any');
    $meta_query = array(); $tax_query = array();
    if (isset($_POST['filters'])&&is_array($_POST['filters'])) {
        foreach ($_POST['filters'] as $filter) {
            if (empty($filter['value'])) continue;
            $field = sanitize_text_field($filter['field']); $op = sanitize_text_field($filter['op']); $value = sanitize_text_field($filter['value']);
            if (in_array($field,array('bm_genre','bm_category'))) { $term = get_term_by('name',$value,$field); if ($term) $tax_query[]=array('taxonomy'=>$field,'field'=>'term_id','terms'=>$term->term_id); }
            else { $meta_query[]=array('key'=>$field,'value'=>$value,'compare'=>($op==='='?'=':'LIKE')); }
        }
    }
    if (!empty($meta_query)) { $meta_query['relation']=(isset($_POST['filter_relation'])&&'OR'===$_POST['filter_relation'])?'OR':'AND'; $args['meta_query']=$meta_query; }
    if (!empty($tax_query)) { $tax_query['relation']=(isset($_POST['filter_relation'])&&'OR'===$_POST['filter_relation'])?'OR':'AND'; $args['tax_query']=$tax_query; }
    $books = get_posts($args); if (empty($books)) return;
    $columns = isset($_POST['columns'])?array_map('sanitize_text_field',$_POST['columns']):array('title','_bm_author','_bm_publisher');
    $dynamic_fields = get_option('bm_dynamic_fields',array());
    $headers = array();
    foreach ($columns as $col) {
        if ('title'===$col) $headers[]='Título';
        elseif ('bm_genre'===$col) $headers[]='Gênero';
        elseif ('bm_category'===$col) $headers[]='Categoria';
        elseif (strpos($col,'_bm_dynamic_')===0) { $dn=str_replace('_bm_dynamic_','',$col); $orig=$dn; foreach($dynamic_fields as $df => $info) if(sanitize_key($df)===$dn){$orig=$df;break;} $headers[]=$orig; }
        elseif (strpos($col,'_bm_')===0) { $h=substr($col,4); $map=array('author'=>'Autor','publisher'=>'Editora','isbn'=>'ISBN','location'=>'Localização','copies'=>'Exemplares'); $headers[]=isset($map[$h])?$map[$h]:ucfirst($h); }
        else $headers[]=$col;
    }
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="livros.csv"'); echo "\xEF\xBB\xBF";
    $output = fopen('php://output','w'); fputcsv($output,$headers,';');
    foreach ($books as $book) {
        $row = array();
        foreach ($columns as $col) {
            if ('title'===$col) $row[]=$book->post_title;
            elseif ('bm_genre'===$col) { $t=wp_get_post_terms($book->ID,'bm_genre',array('fields'=>'names')); $row[]=implode(', ',$t); }
            elseif ('bm_category'===$col) { $t=wp_get_post_terms($book->ID,'bm_category',array('fields'=>'names')); $row[]=implode(', ',$t); }
            elseif (strpos($col,'_bm_dynamic_')===0||strpos($col,'_bm_')===0) $row[]=get_post_meta($book->ID,$col,true);
        }
        fputcsv($output,$row,';');
    }
    fclose($output); exit;
}
add_action('admin_init','bm_handle_csv_export');
function bm_render_csv_export_page() {
    if (!current_user_can('manage_options')) return;
    $total = wp_count_posts('bm_book'); $total = $total->publish + $total->draft + $total->trash;
    $fields = array('_bm_author'=>'Autor','_bm_publisher'=>'Editora','_bm_isbn'=>'ISBN','_bm_location'=>'Localização','_bm_copies'=>'Exemplares','bm_genre'=>'Gênero','bm_category'=>'Categoria');
    $dynamic_fields = get_option('bm_dynamic_fields',array());
    foreach ($dynamic_fields as $df => $info) $fields['_bm_dynamic_'.sanitize_key($df)]=$df;
    ?>
    <div class="wrap"><h1><?php _e('Exportar Livros para CSV','book-manager'); ?></h1><p><?php echo sprintf(__('%d livros no acervo.','book-manager'),$total); ?></p>
    <form method="post"><?php wp_nonce_field('bm_csv_export_action','bm_csv_export_nonce'); ?>
    <h3><?php _e('Colunas para exportar','book-manager'); ?></h3>
    <p><label><input type="checkbox" name="columns[]" value="title" checked> <?php _e('Título','book-manager'); ?></label>
    <?php foreach ($fields as $key=>$label): ?><label style="margin-left:10px"><input type="checkbox" name="columns[]" value="<?php echo esc_attr($key); ?>" checked> <?php echo esc_html($label); ?></label><?php endforeach; ?></p>
    <h3><?php _e('Filtros (opcional)','book-manager'); ?></h3>
    <div id="bm-export-filters"><div class="bm-filter-row" style="margin-bottom:5px;">
    <select name="filters[0][field]"><?php foreach ($fields as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
    <select name="filters[0][op]"><option value="="><?php _e('Igual a','book-manager'); ?></option><option value="LIKE"><?php _e('Contém','book-manager'); ?></option></select>
    <input type="text" name="filters[0][value]" placeholder="<?php _e('Valor','book-manager'); ?>" /></div></div>
    <p><button type="button" class="button" id="bm-add-filter"><?php _e('+ Adicionar Filtro','book-manager'); ?></button>
    <select name="filter_relation" style="margin-left:10px;"><option value="AND"><?php _e('E (todos os filtros)','book-manager'); ?></option><option value="OR"><?php _e('OU (qualquer filtro)','book-manager'); ?></option></select></p>
    <?php submit_button(__('Exportar CSV','book-manager')); ?></form></div>
    <script>jQuery(document).ready(function($){var i=1;$('#bm-add-filter').on('click',function(){var h='<div class="bm-filter-row" style="margin-bottom:5px;">'+$('#bm-export-filters .bm-filter-row').first().html().replace(/filters\[0\]/g,'filters['+i+']')+'</div>';$('#bm-export-filters').append(h);i++;});});</script>
    <?php
}

// --- FASE 7B: Campos Dinâmicos ---
function bm_add_dynamic_fields_page() { add_submenu_page('edit.php?post_type=bm_book','Gerenciar Campos','Gerenciar Campos','manage_options','bm_dynamic_fields','bm_render_dynamic_fields_page'); }
add_action('admin_menu','bm_add_dynamic_fields_page');
function bm_render_dynamic_fields_page() {
    if (!current_user_can('manage_options')) return;
    $message = '';
    $dynamic_fields = get_option('bm_dynamic_fields', array());
    if (!empty($dynamic_fields) && isset(array_values($dynamic_fields)[0]) && is_string(array_values($dynamic_fields)[0])) {
        $new_fields = array();
        foreach ($dynamic_fields as $name) $new_fields[$name] = array('type' => 'text');
        update_option('bm_dynamic_fields', $new_fields);
        $dynamic_fields = $new_fields;
    }
    $system_fields = array('_bm_author' => 'Autor', '_bm_publisher' => 'Editora', '_bm_isbn' => 'ISBN', '_bm_location' => 'Localização', '_bm_copies' => 'Exemplares');
    $saved_order = get_option('bm_field_order', array());
    $saved_visibility = get_option('bm_field_visibility', array());

    $all_fields = array();
    foreach ($saved_order as $key) {
        if (isset($system_fields[$key])) $all_fields[$key] = array('label' => $system_fields[$key], 'type' => 'system');
        elseif (isset($dynamic_fields[$key])) $all_fields[$key] = array('label' => $key, 'type' => 'dynamic', 'field_type' => $dynamic_fields[$key]['type']);
    }
    foreach ($system_fields as $key => $label) { if (!isset($all_fields[$key])) $all_fields[$key] = array('label' => $label, 'type' => 'system'); }
    foreach ($dynamic_fields as $key => $info) { if (!isset($all_fields[$key])) $all_fields[$key] = array('label' => $key, 'type' => 'dynamic', 'field_type' => $info['type']); }

    if (isset($_POST['bm_dynamic_nonce']) && wp_verify_nonce($_POST['bm_dynamic_nonce'],'bm_dynamic_action')) {
        if (isset($_POST['add_field']) && !empty($_POST['new_field_name'])) {
            $fields = get_option('bm_dynamic_fields', array());
            $name = sanitize_text_field($_POST['new_field_name']);
            $type = isset($_POST['new_field_type']) ? sanitize_text_field($_POST['new_field_type']) : 'text';
            if (!isset($fields[$name])) { $fields[$name] = array('type' => $type); update_option('bm_dynamic_fields', $fields); $message = __('Campo adicionado.','book-manager'); }
        }
        if (isset($_POST['remove_field']) && !empty($_POST['remove_field_name'])) {
            $fields = get_option('bm_dynamic_fields', array());
            unset($fields[sanitize_text_field($_POST['remove_field_name'])]);
            update_option('bm_dynamic_fields', $fields);
            $message = __('Campo removido.','book-manager');
        }
        if (isset($_POST['save_order'])) {
            $order = isset($_POST['field_order']) ? array_map('sanitize_text_field', $_POST['field_order']) : array();
            $rename_names = isset($_POST['field_rename']) ? array_map('sanitize_text_field', $_POST['field_rename']) : array();
            $fields = get_option('bm_dynamic_fields', array());
            foreach ($rename_names as $old_key => $new_name) {
                if (!empty($new_name) && $old_key !== $new_name) {
                    if (isset($fields[$old_key])) {
                        $fields[$new_name] = $fields[$old_key];
                        unset($fields[$old_key]);
                        // Migra dados nos livros
                        $old_meta = '_bm_dynamic_' . sanitize_key($old_key);
                        $new_meta = '_bm_dynamic_' . sanitize_key($new_name);
                        $all_books = get_posts(array('post_type'=>'bm_book','posts_per_page'=>-1,'post_status'=>'any'));
                        foreach ($all_books as $book) {
                            $value = get_post_meta($book->ID, $old_meta, true);
                            if (!empty($value)) {
                                update_post_meta($book->ID, $new_meta, $value);
                                delete_post_meta($book->ID, $old_meta);
                            }
                        }
                    }
                }
            }
            update_option('bm_dynamic_fields', $fields);
            update_option('bm_field_order', $order);
            $all_keys = array_keys($all_fields);
            $visibility = array();
            foreach ($all_keys as $k) {
                $visibility[$k] = isset($_POST['field_visible']) && in_array($k, (array)$_POST['field_visible']);
            }
            update_option('bm_field_visibility', $visibility);
            $message = __('Alterações salvas.','book-manager');
        }
        $dynamic_fields = get_option('bm_dynamic_fields', array());
        $saved_order = get_option('bm_field_order', array());
        $saved_visibility = get_option('bm_field_visibility', array());
        $all_fields = array();
        foreach ($saved_order as $key) {
            if (isset($system_fields[$key])) $all_fields[$key] = array('label' => $system_fields[$key], 'type' => 'system');
            elseif (isset($dynamic_fields[$key])) $all_fields[$key] = array('label' => $key, 'type' => 'dynamic', 'field_type' => $dynamic_fields[$key]['type']);
        }
        foreach ($system_fields as $key => $label) { if (!isset($all_fields[$key])) $all_fields[$key] = array('label' => $label, 'type' => 'system'); }
        foreach ($dynamic_fields as $key => $info) { if (!isset($all_fields[$key])) $all_fields[$key] = array('label' => $key, 'type' => 'dynamic', 'field_type' => $info['type']); }
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Gerenciar Campos','book-manager'); ?></h1>
        <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        <h2><?php _e('Adicionar novo campo dinâmico','book-manager'); ?></h2>
        <form method="post"><?php wp_nonce_field('bm_dynamic_action','bm_dynamic_nonce'); ?>
            <input type="text" name="new_field_name" placeholder="<?php _e('Nome do campo','book-manager'); ?>" />
            <select name="new_field_type" style="margin-left:5px;">
                <option value="text"><?php _e('Texto curto','book-manager'); ?></option>
                <option value="textarea"><?php _e('Texto longo','book-manager'); ?></option>
            </select>
            <input type="submit" name="add_field" class="button" value="<?php _e('Adicionar','book-manager'); ?>" />
        </form>
        <h2><?php _e('Gerenciar Campos Existentes','book-manager'); ?></h2>
        <p><?php _e('Arraste para reordenar. Campos do sistema não podem ser renomeados. Desmarque "Mostrar" para ocultar.','book-manager'); ?></p>
        <form method="post" id="bm-fields-form">
            <?php wp_nonce_field('bm_dynamic_action','bm_dynamic_nonce'); ?>
            <table class="wp-list-table widefat fixed striped" id="bm-fields-table">
                <thead><tr><th style="width:30px"></th><th><?php _e('Nome do Campo','book-manager'); ?></th><th><?php _e('Tipo','book-manager'); ?></th><th><?php _e('Visível','book-manager'); ?></th><th><?php _e('Remover','book-manager'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($all_fields as $key => $info): $is_visible = isset($saved_visibility[$key]) ? $saved_visibility[$key] : true; ?>
                        <tr>
                            <td><span class="dashicons dashicons-menu" style="cursor:move;color:#999;"></span></td>
                            <td>
                                <input type="hidden" name="field_order[]" value="<?php echo esc_attr($key); ?>" />
                                <?php if ($info['type'] === 'system'): ?>
                                    <input type="text" value="<?php echo esc_attr($info['label']); ?>" style="width:100%;" readonly />
                                <?php else: ?>
                                    <input type="text" name="field_rename[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($info['label']); ?>" style="width:100%;" />
                                <?php endif; ?>
                            </td>
                            <td><?php echo (isset($info['field_type']) && $info['field_type'] === 'textarea') ? __('Texto longo','book-manager') : __('Texto curto','book-manager'); ?></td>
                            <td><label><input type="checkbox" name="field_visible[]" value="<?php echo esc_attr($key); ?>" <?php checked($is_visible); ?> /> <?php _e('Mostrar','book-manager'); ?></label></td>
                            <td>
                                <?php if ($info['type'] === 'dynamic'): ?>
                                    <button type="submit" name="remove_field" class="button button-small" onclick="return confirm('<?php _e('Remover este campo?','book-manager'); ?>');">
                                        <input type="hidden" name="remove_field_name" value="<?php echo esc_attr($key); ?>" /><?php _e('Remover','book-manager'); ?>
                                    </button>
                                <?php else: ?><span style="color:#999;">—</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <input type="hidden" name="save_order" value="1" />
            <?php submit_button(__('Salvar Todas as Alterações','book-manager')); ?>
        </form>
    </div>
    <script>jQuery(document).ready(function($){$('#bm-fields-table tbody').sortable({handle:'.dashicons-menu'});});</script>
    <?php
}

// --- FASE 7D: Capa do Livro ---
function bm_fetch_cover_from_google($title,$author,$publisher,$isbn='') {
    $queries = array();
    if (!empty($isbn)) { $c=preg_replace('/[^0-9]/','',$isbn); if(strlen($c)>=10) $queries[]='isbn:'.$c; }
    if (!empty($title)&&!empty($author)&&!empty($publisher)) $queries[]=$title.' '.$author.' '.$publisher;
    if (!empty($title)&&!empty($author)) $queries[]=$title.' '.$author;
    if (!empty($title)&&!empty($publisher)) $queries[]=$title.' '.$publisher;
    if (!empty($title)) $queries[]=$title;
    foreach ($queries as $query) {
        $url='https://www.googleapis.com/books/v1/volumes?q='.urlencode($query).'&key='.BM_GOOGLE_BOOKS_API_KEY;
        $r=wp_remote_get($url,array('timeout'=>10)); if(is_wp_error($r)) continue;
        $body=json_decode(wp_remote_retrieve_body($r),true); if(empty($body['items'])) continue;
        $hc=false; foreach($body['items'] as $item) if(isset($item['volumeInfo']['imageLinks']['thumbnail'])){$hc=true;break;} if(!$hc) continue;
        $st=mb_strtolower(trim($title)); $sa=mb_strtolower(trim($author)); $best=null;
        foreach($body['items'] as $item) {
            $it=isset($item['volumeInfo']['title'])?mb_strtolower(trim($item['volumeInfo']['title'])):''; if(!isset($item['volumeInfo']['imageLinks']['thumbnail'])) continue;
            if($it===$st){ $ia=isset($item['volumeInfo']['authors'])?mb_strtolower(implode(' ',$item['volumeInfo']['authors'])):''; if(empty($sa)||strpos($ia,$sa)!==false){$best=$item;break;} if(!$best)$best=$item; }
            if(strpos($it,$st)!==false&&!$best){ $ia=isset($item['volumeInfo']['authors'])?mb_strtolower(implode(' ',$item['volumeInfo']['authors'])):''; if(empty($sa)||strpos($ia,$sa)!==false)$best=$item; }
        }
        if(!$best){ foreach($body['items'] as $item) if(isset($item['volumeInfo']['imageLinks']['thumbnail'])){$best=$item;break;} }
        if($best&&isset($best['volumeInfo']['imageLinks']['thumbnail'])){
            $mt=mb_strtolower(trim($best['volumeInfo']['title'])); similar_text($st,$mt,$pct); $min=(mb_strlen($st)<10)?30:50;
            if($pct>=$min||strpos($mt,$st)!==false||strpos($st,$mt)!==false) return str_replace('http://','https://',$best['volumeInfo']['imageLinks']['thumbnail']);
        }
    }
    return false;
}
function bm_search_book_cover() {
    if(!current_user_can('manage_options')) wp_die(__('Sem permissão.','book-manager'));
    $post_id=isset($_POST['post_id'])?intval($_POST['post_id']):0; $isbn=isset($_POST['isbn'])?sanitize_text_field($_POST['isbn']):''; $title=isset($_POST['title'])?sanitize_text_field($_POST['title']):''; $author=isset($_POST['author'])?sanitize_text_field($_POST['author']):''; $publisher=isset($_POST['publisher'])?sanitize_text_field($_POST['publisher']):'';
    $queries=array(); $ln=array();
    if(!empty($isbn)){$c=preg_replace('/[^0-9]/','',$isbn); if(strlen($c)>=10){$queries[]='isbn:'.$c;$ln[]='ISBN';}}
    if(!empty($title)&&!empty($author)&&!empty($publisher)){$queries[]=$title.' '.$author.' '.$publisher;$ln[]='Título + Autor + Editora';}
    if(!empty($title)&&!empty($author)){$queries[]=$title.' '.$author;$ln[]='Título + Autor';}
    if(!empty($title)&&!empty($publisher)){$queries[]=$title.' '.$publisher;$ln[]='Título + Editora';}
    if(!empty($title)){$queries[]=$title;$ln[]='Título';}
    if(empty($queries)) wp_die(__('Preencha Título, Autor ou ISBN.','book-manager'));
    $body=null; $used='';
    foreach($queries as $i=>$q){
        $url='https://www.googleapis.com/books/v1/volumes?q='.urlencode($q).'&key='.BM_GOOGLE_BOOKS_API_KEY;
        $r=wp_remote_get($url,array('timeout'=>15)); if(is_wp_error($r)) continue;
        $body=json_decode(wp_remote_retrieve_body($r),true);
        if(!empty($body['items'])){$hc=false;foreach($body['items'] as $item) if(isset($item['volumeInfo']['imageLinks']['thumbnail'])){$hc=true;break;} if($hc){$used=$ln[$i];break;}}
    }
    if(empty($body['items'])) wp_die(__('Nenhuma capa encontrada.','book-manager'));
    $img='';$best=null;$st=mb_strtolower(trim($title));$sa=mb_strtolower(trim($author));
    foreach($body['items'] as $item){
        $it=isset($item['volumeInfo']['title'])?mb_strtolower(trim($item['volumeInfo']['title'])):''; if(!isset($item['volumeInfo']['imageLinks']['thumbnail'])) continue;
        if($it===$st){$ia=isset($item['volumeInfo']['authors'])?mb_strtolower(implode(' ',$item['volumeInfo']['authors'])):''; if(empty($sa)||strpos($ia,$sa)!==false){$best=$item;break;} if(!$best)$best=$item;}
        if(strpos($it,$st)!==false&&!$best){$ia=isset($item['volumeInfo']['authors'])?mb_strtolower(implode(' ',$item['volumeInfo']['authors'])):''; if(empty($sa)||strpos($ia,$sa)!==false)$best=$item;}
    }
    if(!$best){foreach($body['items'] as $item) if(isset($item['volumeInfo']['imageLinks']['thumbnail'])){$best=$item;break;}}
    if($best&&isset($best['volumeInfo']['title'])){$mt=mb_strtolower(trim($best['volumeInfo']['title']));similar_text($st,$mt,$pct);$min=(mb_strlen($st)<10)?30:50; if($pct<$min&&strpos($mt,$st)===false&&strpos($st,$mt)===false) wp_die(__('Nenhuma capa encontrada.','book-manager')); $img=$best['volumeInfo']['imageLinks']['thumbnail'];$img=str_replace('http://','https://',$img);}
    if(empty($img)) wp_die(__('Nenhuma capa encontrada.','book-manager'));
    require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/image.php';
    $ir=wp_remote_get($img,array('timeout'=>15)); if(is_wp_error($ir)) wp_die(__('Erro ao baixar a capa.','book-manager'));
    $id=wp_remote_retrieve_body($ir); if(empty($id)) wp_die(__('Erro ao baixar a capa.','book-manager'));
    $ud=wp_upload_dir(); $fn='book-cover-'.$post_id.'-'.time().'.jpg'; $fp=$ud['path'].'/'.$fn; file_put_contents($fp,$id);
    $att=array('post_mime_type'=>'image/jpeg','post_title'=>get_the_title($post_id),'post_content'=>'','post_status'=>'inherit');
    $aid=wp_insert_attachment($att,$fp,$post_id); if(is_wp_error($aid)) wp_die(__('Erro ao salvar a capa.','book-manager'));
    $ad=wp_generate_attachment_metadata($aid,$fp); wp_update_attachment_metadata($aid,$ad); set_post_thumbnail($post_id,$aid);
    $msg=$used?sprintf(__('Capa salva via %s!','book-manager'),$used):__('Capa salva com sucesso!','book-manager'); wp_die($msg);
}
add_action('wp_ajax_bm_search_book_cover','bm_search_book_cover');
function bm_add_cover_button() {
    global $post; if(!$post||'bm_book'!==$post->post_type) return;
    ?><script>jQuery(document).ready(function($){$('#bm_search_cover').on('click',function(){var b=$(this);b.prop('disabled',true).val('Buscando...');$.post(ajaxurl,{action:'bm_search_book_cover',post_id:$('#post_ID').val(),isbn:$('#_bm_isbn').val(),title:$('#title').val(),author:$('#_bm_author').val(),publisher:$('#_bm_publisher').val()},function(r){alert(r);location.reload();});});});</script>
    <input type="button" id="bm_search_cover" class="button" value="<?php _e('Buscar Capa','book-manager'); ?>" /><?php
}
add_action('edit_form_after_title','bm_add_cover_button');

// --- FASE 7F: Soft Delete e Auditoria ---
function bm_log_audit($post_id,$action) {
    $user=wp_get_current_user(); $un=$user?$user->user_login:'sistema'; $time=current_time('mysql');
    $entry=array('action'=>$action,'user'=>$un,'time'=>$time);
    $log=get_post_meta($post_id,'_bm_audit_log',true); if(!is_array($log)) $log=array();
    $log[]=$entry; if(count($log)>20) $log=array_slice($log,-20);
    update_post_meta($post_id,'_bm_audit_log',$log);
}
function bm_audit_post_updated($post_id,$post_after,$post_before) {
    if('bm_book'!==get_post_type($post_id)) return; if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE) return;
    if('auto-draft'===$post_before->post_status&&'publish'===$post_after->post_status) bm_log_audit($post_id,'Livro criado');
    elseif($post_before->post_title!==$post_after->post_title||$post_before->post_status!==$post_after->post_status) bm_log_audit($post_id,'Livro editado');
}
add_action('post_updated','bm_audit_post_updated',10,3);
function bm_audit_trashed($post_id) { if('bm_book'===get_post_type($post_id)) bm_log_audit($post_id,'Movido para lixeira'); }
add_action('trashed_post','bm_audit_trashed');
function bm_audit_untrashed($post_id) { if('bm_book'===get_post_type($post_id)) bm_log_audit($post_id,'Restaurado da lixeira'); }
add_action('untrashed_post','bm_audit_untrashed');