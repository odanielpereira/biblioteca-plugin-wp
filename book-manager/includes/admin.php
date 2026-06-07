<?php
/**
 * Book Manager — Módulo de Administração
 * Metaboxes, listagem, filtros, campos dinâmicos, importação/exportação CSV
 */

defined('ABSPATH') || exit;

// ==========================================
// FASE 7H: SCRIPTS DO ADMIN (DRAG AND DROP)
// ==========================================
function bm_admin_scripts($hook) {
    if (strpos($hook, 'bm_dynamic_fields') === false && strpos($hook, 'bm_book') === false) return;
    wp_enqueue_script('jquery-ui-sortable');
}
add_action('admin_enqueue_scripts', 'bm_admin_scripts');

// ==========================================
// FASE 2/7A/7B/7F: METABOX DETALHES DO LIVRO
// ==========================================
function bm_render_book_details_metabox( $post ) {
    wp_nonce_field( 'bm_save_book_details', 'bm_book_details_nonce' );
    $fixed_fields = array(
        '_bm_author'    => array('label' => __('Autor:','book-manager'), 'type' => 'text', 'required' => true),
        '_bm_publisher' => array('label' => __('Editora:','book-manager'), 'type' => 'text', 'required' => true),
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

// FASE 12E-T2: Metaboxes para taxonomias dinâmicas
function bm_add_dynamic_taxonomy_metaboxes() {
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) return;
    foreach ($taxonomies as $slug => $info) {
        add_meta_box(
            'bm_tax_' . $slug,
            $info['label'],
            'bm_render_dynamic_taxonomy_metabox',
            'bm_book',
            'side',
            'default',
            array('slug' => $slug, 'label' => $info['label'])
        );
    }
}
add_action('add_meta_boxes', 'bm_add_dynamic_taxonomy_metaboxes');

function bm_render_dynamic_taxonomy_metabox($post, $box) {
    $slug = $box['args']['slug'];
    $terms = wp_get_post_terms($post->ID, $slug, array('fields' => 'ids'));
    $all_terms = get_terms(array('taxonomy' => $slug, 'hide_empty' => false));
    ?>
    <div style="max-height:150px;overflow-y:auto;">
        <?php foreach ($all_terms as $term): ?>
            <label style="display:block;margin:3px 0;">
                <input type="checkbox" name="bm_tax_<?php echo esc_attr($slug); ?>[]" value="<?php echo $term->term_id; ?>" <?php checked(in_array($term->term_id, $terms)); ?> />
                <?php echo esc_html($term->name); ?>
            </label>
        <?php endforeach; ?>
    </div>
    <?php
}

function bm_save_dynamic_taxonomy_terms($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('manage_options') && !current_user_can('edit_bm_books')) return;
    
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) return;
    
    foreach ($taxonomies as $slug => $info) {
        $field = 'bm_tax_' . $slug;
        $terms = isset($_POST[$field]) ? array_map('intval', $_POST[$field]) : array();
        wp_set_post_terms($post_id, $terms, $slug);
    }
}
add_action('save_post_bm_book', 'bm_save_dynamic_taxonomy_terms');

add_action('add_meta_boxes', 'bm_add_book_details_metabox');
function bm_save_book_details_metabox_data( $post_id ) {
    if (!isset($_POST['bm_book_details_nonce']) || !wp_verify_nonce($_POST['bm_book_details_nonce'],'bm_save_book_details')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
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

// ==========================================
// FASE 10C: RESENHA OFICIAL DO GESTOR/ADMIN
// ==========================================
function bm_add_official_review_metabox() {
    add_meta_box('bm_official_review', __('Resenha Oficial', 'book-manager'), 'bm_render_official_review_metabox', 'bm_book', 'normal', 'high');
}
add_action('add_meta_boxes', 'bm_add_official_review_metabox');

function bm_render_official_review_metabox($post) {
    wp_nonce_field('bm_official_review_nonce', 'bm_official_review_nonce_field');
    $review = get_post_meta($post->ID, '_bm_official_review', true);
    $link = get_post_meta($post->ID, '_bm_official_link', true);
    ?>
    <p>
        <label><strong><?php _e('Resenha oficial da biblioteca:', 'book-manager'); ?></strong></label>
        <textarea name="bm_official_review" rows="5" style="width:100%;max-width:600px;margin-top:5px;"><?php echo esc_textarea($review); ?></textarea>
    </p>
    <p>
        <label><strong><?php _e('Link oficial (vídeo ou site):', 'book-manager'); ?></strong></label>
        <input type="url" name="bm_official_link" value="<?php echo esc_attr($link); ?>" style="width:100%;max-width:600px;margin-top:5px;" placeholder="https://..." />
    </p>
    <p class="description"><?php _e('Esta resenha e link aparecerão com destaque na página pública do livro.', 'book-manager'); ?></p>
    <?php
}

function bm_save_official_review($post_id) {
    if (!isset($_POST['bm_official_review_nonce_field']) || !wp_verify_nonce($_POST['bm_official_review_nonce_field'], 'bm_official_review_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('manage_options') && !current_user_can('edit_bm_books')) return;
    
    if (isset($_POST['bm_official_review'])) {
        update_post_meta($post_id, '_bm_official_review', sanitize_textarea_field($_POST['bm_official_review']));
    }
    if (isset($_POST['bm_official_link'])) {
        update_post_meta($post_id, '_bm_official_link', esc_url_raw($_POST['bm_official_link']));
    }
}
add_action('save_post_bm_book', 'bm_save_official_review');

// ==========================================
// FASE 4/7C: LISTAGEM E FILTROS ADMIN
// ==========================================
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
    <div class="bm-filter-form">
    <?php
    echo '<input type="hidden" name="post_type" value="bm_book">';
    if (isset($_GET['orderby']) && !empty($_GET['orderby'])) echo '<input type="hidden" name="orderby" value="'.esc_attr(sanitize_text_field($_GET['orderby'])).'">';
    if (isset($_GET['order']) && !empty($_GET['order'])) echo '<input type="hidden" name="order" value="'.esc_attr(sanitize_text_field($_GET['order'])).'">';
    if (isset($_GET['s'])&&!empty($_GET['s'])) echo '<input type="hidden" name="s" value="'.esc_attr(sanitize_text_field($_GET['s'])).'">';
    ?>
    <p><label for="_bm_author"><?php _e('Autor:','book-manager'); ?></label><input type="text" id="_bm_author" name="_bm_author" value="<?php echo esc_attr($fa); ?>" placeholder="<?php _e('Filtrar por autor','book-manager'); ?>"></p>
    <p><label for="_bm_publisher"><?php _e('Editora:','book-manager'); ?></label><input type="text" id="_bm_publisher" name="_bm_publisher" value="<?php echo esc_attr($fp); ?>" placeholder="<?php _e('Filtrar por editora','book-manager'); ?>"></p>
    <?php
  
    wp_dropdown_categories(array('show_option_all'=>__('Todos os Gêneros','book-manager'),'taxonomy'=>'bm_genre','name'=>'bm_genre_filter','selected'=>isset($_GET['bm_genre_filter'])?$_GET['bm_genre_filter']:''));
    wp_dropdown_categories(array('show_option_all'=>__('Todas as Categorias','book-manager'),'taxonomy'=>'bm_category','name'=>'bm_category_filter','selected'=>isset($_GET['bm_category_filter'])?$_GET['bm_category_filter']:''));
    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($dynamic_taxonomies)) $dynamic_taxonomies = array();
    foreach ($dynamic_taxonomies as $slug => $info) {
        wp_dropdown_categories(array(
            'show_option_all' => $info['label'],
            'taxonomy' => $slug,
            'name' => $slug . '_filter',
            'selected' => isset($_GET[$slug . '_filter']) ? $_GET[$slug . '_filter'] : '',
        ));
    }
    ?>
    <input type="submit" name="filter_action" class="button" value="<?php _e('Filtrar','book-manager'); ?>">
    <a href="<?php echo admin_url('edit.php?post_type=bm_book'); ?>" class="button"><?php _e('Limpar Filtros','book-manager'); ?></a>
    </div><?php
}
add_action('restrict_manage_posts','bm_add_book_filter_form');
function bm_filter_books_by_metadata($query) {
    if (!is_admin()||!$query->is_main_query()||'bm_book'!==$query->get('post_type')) return;
    
    // Não interferir em ações em lote
    if (isset($_GET['action']) || isset($_GET['action2'])) return;
    
    $meta = array();
    if (isset($_GET['_bm_author'])&&!empty($_GET['_bm_author'])) $meta[]=array('key'=>'_bm_author','value'=>sanitize_text_field($_GET['_bm_author']),'compare'=>'LIKE');
    if (isset($_GET['_bm_publisher'])&&!empty($_GET['_bm_publisher'])) $meta[]=array('key'=>'_bm_publisher','value'=>sanitize_text_field($_GET['_bm_publisher']),'compare'=>'LIKE');
    if (!empty($meta)) { $meta['relation']='AND'; $query->set('meta_query',$meta); }
    if (isset($_GET['bm_genre_filter'])&&!empty($_GET['bm_genre_filter'])) { $tq=$query->get('tax_query')?:array(); $tq[]=array('taxonomy'=>'bm_genre','field'=>'term_id','terms'=>intval($_GET['bm_genre_filter'])); $query->set('tax_query',$tq); }
    if (isset($_GET['bm_category_filter'])&&!empty($_GET['bm_category_filter'])) { $tq=$query->get('tax_query')?:array(); $tq[]=array('taxonomy'=>'bm_category','field'=>'term_id','terms'=>intval($_GET['bm_category_filter'])); $query->set('tax_query',$tq); }

    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($dynamic_taxonomies)) $dynamic_taxonomies = array();
    foreach ($dynamic_taxonomies as $slug => $info) {
        if (isset($_GET[$slug . '_filter']) && !empty($_GET[$slug . '_filter'])) {
            $tq = $query->get('tax_query') ?: array();
            $tq[] = array('taxonomy' => $slug, 'field' => 'term_id', 'terms' => intval($_GET[$slug . '_filter']));
            $query->set('tax_query', $tq);
        }
    }
}
add_action('pre_get_posts','bm_filter_books_by_metadata');

// ==========================================
// FASE 6A/7G: IMPORTAÇÃO CSV COM MAPEAMENTO DINÂMICO
// FASE 8C-B: RELATÓRIO MELHORADO
// FASE 8F: INTEGRAÇÃO DE BUSCA AUTOMÁTICA DE SINOPSE
// FASE 11A-B: CLASSIFICAÇÃO POR IA DURANTE IMPORTAÇÃO
// FASE 11B: GERAÇÃO DE NÚMERO DE CHAMADA (RESPEITA CSV)
// ==========================================

// FASE 18: Movido para Importação/Exportação (aba Importar Livros CSV)
function bm_render_csv_import_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $message = ''; $preview = array(); $duplicates = array();
    $stage = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';
    $headers = array();
    if ('process'===$stage && isset($_POST['bm_csv_import_nonce']) && wp_verify_nonce($_POST['bm_csv_import_nonce'],'bm_csv_import_action')) {
        $skip_duplicates = isset($_POST['skip_duplicates'])&&'1'===$_POST['skip_duplicates'];
        $classify_with_ai = isset($_POST['classify_with_ai']) && '1' === $_POST['classify_with_ai'];
        $generate_call_number = isset($_POST['generate_call_number']) && '1' === $_POST['generate_call_number'];
        $imported=0; $skipped=0; $dup_skipped=0; $dup_forced=0;
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
                if ($exists) { $dup_forced++; }
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
                    $sinopse = bm_fetch_sinopse_from_google($title, $author);
                    if (!empty($sinopse)) {
                        $dynamic_fields = get_option('bm_dynamic_fields', array());
                        if (!isset($dynamic_fields['Sinopse'])) {
                            $dynamic_fields['Sinopse'] = array('type' => 'textarea');
                            update_option('bm_dynamic_fields', $dynamic_fields);
                        }
                        update_post_meta($post_id, '_bm_dynamic_sinopse', $sinopse);
                    }
                    if ($classify_with_ai) {
                        $groq_key = bm_get_api_key('groq');
                        if (!empty($groq_key)) {
                            bm_classify_book_with_ai($post_id);
                        }
                    }
                    if ($generate_call_number) {
                        // Verificar se CDU e Cutter já vieram do CSV
                        $csv_cdu = get_post_meta($post_id, '_bm_cdu', true);
                        $csv_cutter = get_post_meta($post_id, '_bm_cutter', true);
                        
                        // Se AMBOS já foram preenchidos pelo CSV, não chama IA
                        if (!empty($csv_cdu) && !empty($csv_cutter)) {
                            // Já tem — apenas travar
                            update_post_meta($post_id, '_bm_cutter_cached', '1');
                            update_post_meta($post_id, '_bm_cutter_locked', '1');
                        } else {
                            // Faltando um ou ambos — chamar IA
                            $groq_key = bm_get_api_key('groq');
                            if (!empty($groq_key)) {
                                $result = bm_generate_call_number($post_id);
                                // Se o CSV já tinha CDU, preservar o CDU do CSV
                                if (!empty($csv_cdu) && $result) {
                                    update_post_meta($post_id, '_bm_cdu', $csv_cdu);
                                }
                                // Se o CSV já tinha Cutter, preservar o Cutter do CSV
                                if (!empty($csv_cutter) && $result) {
                                    update_post_meta($post_id, '_bm_cutter', $csv_cutter);
                                }
                            }
                        }
                    }
                } else { $skipped++; }
            }
        }
        $message = sprintf(__('%d importados, %d ignorados (sem título), %d duplicados pulados, %d duplicados forçados.','book-manager'),$imported,$skipped,$dup_skipped,$dup_forced);
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
        '_bm_cdu'=>__('Classificação (CDU/CDD)','book-manager'),
        '_bm_cutter'=>__('Cutter','book-manager'),
        '_bm_edition'=>__('Edição','book-manager'),
        '_bm_volume'=>__('Volume','book-manager'),
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
                <p><strong><?php _e('Classificação por IA:','book-manager'); ?></strong>
                    <label><input type="checkbox" name="classify_with_ai" value="1" checked> <?php _e('Classificar livros por disciplina (Groq)', 'book-manager'); ?></label></p>
                <p><strong><?php _e('Número de Chamada:','book-manager'); ?></strong>
                    <label><input type="checkbox" name="generate_call_number" value="1" checked> <?php _e('Gerar Classificação/Cutter via IA (Groq)', 'book-manager'); ?></label>
                    <br><small><?php _e('Se o CSV já tiver Classificação e Cutter, a IA não será chamada.', 'book-manager'); ?></small></p>
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

// ==========================================
// FASE 6B/7E: EXPORTAÇÃO CSV FLEXÍVEL
// ==========================================

// FASE 18: Movido para Importação/Exportação (aba Exportar Livros CSV)
function bm_handle_csv_export() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
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

// ==========================================
// FASE 7B/7H: GERENCIAMENTO DE CAMPOS DINÂMICOS
// ==========================================

function bm_add_dynamic_fields_page() { add_submenu_page('edit.php?post_type=bm_book','Gerenciar Campos','Gerenciar Campos','manage_options','bm_dynamic_fields','bm_render_dynamic_fields_page'); }

add_action('admin_menu','bm_add_dynamic_fields_page');
function bm_render_dynamic_fields_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $message = '';
    
    $active_tab = isset($_GET['tab']) && $_GET['tab'] === 'users' ? 'users' : 'books';
    $dynamic_fields = $active_tab === 'users' ? get_option('bm_user_dynamic_fields', array()) : get_option('bm_dynamic_fields', array());
    if (!empty($dynamic_fields) && isset(array_values($dynamic_fields)[0]) && is_string(array_values($dynamic_fields)[0])) {
        $new_fields = array();
        foreach ($dynamic_fields as $name) $new_fields[$name] = array('type' => 'text');
        update_option('bm_dynamic_fields', $new_fields);
        $dynamic_fields = $new_fields;
    }
    $system_fields = $active_tab === 'users' 
        ? array() 
        : array('_bm_author' => 'Autor', '_bm_publisher' => 'Editora', '_bm_isbn' => 'ISBN', '_bm_location' => 'Localização', '_bm_copies' => 'Exemplares');
    $saved_order = get_option('bm_field_order', array());
    $saved_visibility = get_option('bm_field_visibility', array());

    $saved_order = $active_tab === 'users' ? get_option('bm_user_field_order', array()) : get_option('bm_field_order', array());
    $saved_visibility = $active_tab === 'users' ? get_option('bm_user_field_visibility', array()) : get_option('bm_field_visibility', array());

    $all_fields = array();
    foreach ($saved_order as $key) {
        if (isset($system_fields[$key])) $all_fields[$key] = array('label' => $system_fields[$key], 'type' => 'system');
        elseif (isset($dynamic_fields[$key])) $all_fields[$key] = array('label' => $key, 'type' => 'dynamic', 'field_type' => $dynamic_fields[$key]['type']);
    }
    foreach ($system_fields as $key => $label) { if (!isset($all_fields[$key])) $all_fields[$key] = array('label' => $label, 'type' => 'system'); }
    foreach ($dynamic_fields as $key => $info) { if (!isset($all_fields[$key])) $all_fields[$key] = array('label' => $key, 'type' => 'dynamic', 'field_type' => $info['type']); }

    if (isset($_POST['bm_dynamic_nonce']) && wp_verify_nonce($_POST['bm_dynamic_nonce'],'bm_dynamic_action')) {
        $option_name = $active_tab === 'users' ? 'bm_user_dynamic_fields' : 'bm_dynamic_fields';
        $meta_prefix = $active_tab === 'users' ? '_bm_user_' : '_bm_dynamic_';
        
        if (isset($_POST['add_field']) && !empty($_POST['new_field_name'])) {
            $fields = get_option($option_name, array());
            $name = sanitize_text_field($_POST['new_field_name']);
            $type = isset($_POST['new_field_type']) ? sanitize_text_field($_POST['new_field_type']) : 'text';
            $name_lower = mb_strtolower(trim($name));
            
            // Bloquear nomes reservados do Número de Chamada (apenas na aba de livros)
            if ($active_tab === 'books') {
                $reserved_names = array('cdu', 'cdd', 'classificação', 'classificacao', 'cutter');
                if (in_array($name_lower, $reserved_names)) {
                    $message = __('Este nome é reservado para o Número de Chamada. Use outro nome.','book-manager');
                    $name = ''; // Impede a criação
                }
            }
            
            // Verificar duplicatas (case-insensitive)
            $duplicate = false;
            foreach ($fields as $existing_name => $info) {
                if (mb_strtolower(trim($existing_name)) === $name_lower) {
                    $duplicate = true;
                    break;
                }
            }
            
            if ($duplicate) {
                $message = __('Já existe um campo com este nome.','book-manager');
            } elseif (!isset($fields[$name])) {
                $profile = isset($_POST['new_field_profile']) ? sanitize_text_field($_POST['new_field_profile']) : 'both';
                $fields[$name] = array('type' => $type, 'profile' => $profile);
                update_option($option_name, $fields);
                $message = __('Campo adicionado.','book-manager');
            }
        }
        if (isset($_POST['remove_field']) && !empty($_POST['remove_field_name'])) {
            $fields = get_option($option_name, array());
            unset($fields[sanitize_text_field($_POST['remove_field_name'])]);
            update_option($option_name, $fields);
            $message = __('Campo removido.','book-manager');
        }
        if (isset($_POST['save_order'])) {
            $order = isset($_POST['field_order']) ? array_map('sanitize_text_field', $_POST['field_order']) : array();
            $rename_names = isset($_POST['field_rename']) ? array_map('sanitize_text_field', $_POST['field_rename']) : array();
            $fields = get_option($option_name, array());
            foreach ($rename_names as $old_key => $new_name) {
                if (!empty($new_name) && $old_key !== $new_name) {
                    if (isset($fields[$old_key])) {
                        $fields[$new_name] = $fields[$old_key];
                        unset($fields[$old_key]);
                        $old_meta = $meta_prefix . sanitize_key($old_key);
                        $new_meta = $meta_prefix . sanitize_key($new_name);
                        if ($active_tab === 'users') {
                            $all_users = get_users(array('number' => -1));
                            foreach ($all_users as $user) {
                                $value = get_user_meta($user->ID, $old_meta, true);
                                if (!empty($value)) {
                                    update_user_meta($user->ID, $new_meta, $value);
                                    delete_user_meta($user->ID, $old_meta);
                                }
                            }
                        } else {
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
            }
            update_option($option_name, $fields);
            
            // Preservar ordem: campos renomeados mantêm posição
            $final_order = array();
            foreach ($order as $key) {
                // Se foi renomeado, usa o novo nome
                if (isset($rename_names[$key]) && !empty($rename_names[$key]) && $key !== $rename_names[$key]) {
                    $final_order[] = $rename_names[$key];
                } else {
                    $final_order[] = $key;
                }
            }
            
            $order_option = $active_tab === 'users' ? 'bm_user_field_order' : 'bm_field_order';
            update_option($order_option, $final_order);
            $all_keys = array_keys($all_fields);
            $visibility = array();
            foreach ($all_keys as $k) {
                $visibility[$k] = isset($_POST['field_visible']) && in_array($k, (array)$_POST['field_visible']);
            }
            $visibility_option = $active_tab === 'users' ? 'bm_user_field_visibility' : 'bm_field_visibility';
            update_option($visibility_option, $visibility);
            $message = __('Alterações salvas.','book-manager');
        }
        $dynamic_fields = get_option($active_tab === 'users' ? 'bm_user_dynamic_fields' : 'bm_dynamic_fields', array());
        $saved_order = get_option($active_tab === 'users' ? 'bm_user_field_order' : 'bm_field_order', array());
        $saved_visibility = get_option($active_tab === 'users' ? 'bm_user_field_visibility' : 'bm_field_visibility', array());
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
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_dynamic_fields&tab=books'); ?>" class="nav-tab <?php echo $active_tab === 'books' ? 'nav-tab-active' : ''; ?>">📚 <?php _e('Campos de Livros','book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_dynamic_fields&tab=users'); ?>" class="nav-tab <?php echo $active_tab === 'users' ? 'nav-tab-active' : ''; ?>">👤 <?php _e('Campos de Alunos','book-manager'); ?></a>
        </nav>
        
        <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        <h2><?php _e('Adicionar novo campo dinâmico','book-manager'); ?></h2>
        <form method="post"><?php wp_nonce_field('bm_dynamic_action','bm_dynamic_nonce'); ?>
            <input type="text" name="new_field_name" placeholder="<?php _e('Nome do campo','book-manager'); ?>" />
            <select name="new_field_type" style="margin-left:5px;">
                <option value="text"><?php _e('Texto curto','book-manager'); ?></option>
                <option value="email"><?php _e('E-mail','book-manager'); ?></option>
                <option value="textarea"><?php _e('Texto longo','book-manager'); ?></option>
            </select>
            <?php if ($active_tab === 'users'): ?>
                <select name="new_field_profile" style="margin-left:5px;">
                    <option value="both"><?php _e('Aluno e Professor','book-manager'); ?></option>
                    <option value="student"><?php _e('Apenas Aluno','book-manager'); ?></option>
                    <option value="teacher"><?php _e('Apenas Professor','book-manager'); ?></option>
                </select>
            <?php endif; ?>
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
                            <td>
                                <?php 
                                $ft = isset($info['field_type']) ? $info['field_type'] : 'text';
                                if ($ft === 'textarea') _e('Texto longo','book-manager');
                                elseif ($ft === 'email') _e('E-mail','book-manager');
                                else _e('Texto curto','book-manager');
                                ?>
                            </td>
                            <td><label><input type="checkbox" name="field_visible[]" value="<?php echo esc_attr($key); ?>" <?php checked($is_visible); ?> /> <?php _e('Mostrar','book-manager'); ?></label></td>
                            <td>
                                <?php if ($info['type'] === 'dynamic'): ?>
                                    <?php 
                                    $is_locked = isset($dynamic_fields[$key]['locked']) && $dynamic_fields[$key]['locked'];
                                    if ($is_locked): ?>
                                        <span style="color:#999;">🔒 <?php _e('Protegido','book-manager'); ?></span>
                                    <?php else: ?>
                                        <button type="submit" name="remove_field" class="button button-small" onclick="return confirm('<?php _e('Remover este campo?','book-manager'); ?>');">
                                            <input type="hidden" name="remove_field_name" value="<?php echo esc_attr($key); ?>" /><?php _e('Remover','book-manager'); ?>
                                        </button>
                                    <?php endif; ?>
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

// ==========================================
// FASE 10E: CENTRAL DE APIS E CONFIGURAÇÕES
// ==========================================
function bm_get_api_keys() {
    $saved = get_option('bm_api_settings', array());
    if (!is_array($saved)) $saved = array();
    if (!isset($saved['google_books_key'])) $saved['google_books_key'] = '';
    if (!isset($saved['groq_key'])) $saved['groq_key'] = '';
    if (!isset($saved['groq_active'])) $saved['groq_active'] = '1';
    return $saved;
}

function bm_get_api_key($provider) {
    $keys = bm_get_api_keys();
    if ($provider === 'google_books' && defined('BM_GOOGLE_BOOKS_API_KEY') && empty($keys['google_books_key'])) {
        return BM_GOOGLE_BOOKS_API_KEY;
    }
    return isset($keys[$provider . '_key']) ? $keys[$provider . '_key'] : '';
}

function bm_render_api_settings_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $keys = bm_get_api_keys();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_keys'])) {
        $new = array();
        $new['google_books_key'] = trim(sanitize_text_field($_POST['google_books_key']));
        $new['groq_key'] = trim(sanitize_text_field($_POST['groq_key']));
        $new['groq_active'] = isset($_POST['groq_active']) ? '1' : '0';
        
        update_option('bm_api_settings', $new);
        $keys = $new;
        $msg = '<div class="notice notice-success"><p>Salvo! Groq: ' . (empty($new['groq_key']) ? 'VAZIO' : 'OK') . ' | Ativo: ' . $new['groq_active'] . '</p></div>';
    }
    
    $groq_status = !empty($keys['groq_key']) && $keys['groq_active'] === '1' ? 'Groq ✅' : 'Nenhuma IA ativa';
    ?>
    <div class="wrap">
        <h1>APIs e Configurações</h1>
        <?php echo $msg; ?>
        
        <div style="background:#f9f9f9;padding:10px 15px;border-radius:4px;margin-bottom:15px;">
            <strong>IA Ativa:</strong> <?php echo $groq_status; ?>
        </div>
        
        <form method="post">
            <h2>📚 Google Books API</h2>
            <p><input type="text" name="google_books_key" value="<?php echo esc_attr($keys['google_books_key']); ?>" style="width:100%;" placeholder="AIza..." /></p>
            <p class="description">Busca automática de capas e sinopses.</p>
            
            <h2>🤖 Groq (IA Gratuita)</h2>
            <p><input type="text" name="groq_key" value="<?php echo esc_attr($keys['groq_key']); ?>" style="width:100%;" placeholder="gsk_..." /></p>
            <p><label><input type="checkbox" name="groq_active" <?php checked($keys['groq_active'], '1'); ?> /> Ativar Groq</label></p>
            <p class="description">1.500 req/dia grátis · Llama 3 · <a href="https://console.groq.com" target="_blank">console.groq.com</a></p>
            
            <p><input type="submit" name="save_keys" class="button button-primary" value="Salvar" /></p>
        </form>
    </div>
    <?php
}

// ==========================================
// FASE 18: PÁGINA UNIFICADA — BALCÃO DE ATENDIMENTO
// ==========================================
function bm_add_service_desk_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Balcão de Atendimento', 'book-manager'), __('Balcão de Atendimento', 'book-manager'), 'edit_bm_books', 'bm_service_desk', 'bm_render_service_desk_page');
}
add_action('admin_menu', 'bm_add_service_desk_page');

function bm_render_service_desk_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'loans';
    ?>
    <div class="wrap">
        <h1><?php _e('Balcão de Atendimento', 'book-manager'); ?></h1>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_service_desk&tab=loans'); ?>" class="nav-tab <?php echo $tab === 'loans' ? 'nav-tab-active' : ''; ?>">📋 <?php _e('Empréstimos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_service_desk&tab=service'); ?>" class="nav-tab <?php echo $tab === 'service' ? 'nav-tab-active' : ''; ?>">📤 <?php _e('Atendimento', 'book-manager'); ?></a>
        </nav>
        
        <?php
        if ($tab === 'service') {
            bm_render_service_page_content();
        } else {
            bm_render_loans_page_content();
        }
        ?>
    </div>
    <?php
}

// ==========================================
// FASE 12K: ATENDIMENTO (EMPRÉSTIMO RÁPIDO NO BALCÃO)
// FASE 18: Movido para Balcão de Atendimento (bm_service_desk)
// ==========================================

function bm_render_service_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    bm_render_service_page_content();
}

function bm_render_service_page_content() {
    $nonce = wp_create_nonce('bm_service_nonce');
    ?>
    <div class="wrap" style="max-width:1100px;">
        <h1>📋 <?php _e('Atendimento — Balcão', 'book-manager'); ?></h1>
        
        <!-- Campo de código de barras (leitor) -->
        <div style="background:#f0f7ff;padding:10px 15px;border-radius:6px;margin-bottom:15px;border-left:4px solid #2196f3;">
            <label style="font-weight:bold;">📷 <?php _e('Leitor de Código de Barras', 'book-manager'); ?></label>
            <input type="text" id="bm-barcode-input" placeholder="<?php _e('Escaneie o ISBN ou digite...', 'book-manager'); ?>" style="width:100%;padding:10px;font-size:16px;margin-top:5px;border:2px solid #2196f3;border-radius:4px;" autofocus />
            <p class="description" style="margin:5px 0 0 0;"><?php _e('Escaneie o código de barras ou digite o ISBN e pressione Enter para buscar o livro.', 'book-manager'); ?></p>
        </div>
        
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <!-- Coluna do Livro -->
            <div style="flex:1;min-width:350px;background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;">
                <h2 style="margin-top:0;">📖 <?php _e('Livro', 'book-manager'); ?></h2>
                <div style="display:flex;gap:10px;margin-bottom:10px;">
                    <input type="text" id="bm-book-search" placeholder="<?php _e('Buscar por título, autor ou ISBN...', 'book-manager'); ?>" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;" />
                    <button type="button" id="bm-book-search-btn" class="button">🔍</button>
                </div>
                <div id="bm-book-result" style="min-height:100px;padding:10px;background:#f9f9f9;border-radius:4px;">
                    <p style="color:#999;"><?php _e('Busque um livro ou escaneie o código de barras.', 'book-manager'); ?></p>
                </div>
                <div id="bm-book-queue" style="margin-top:10px;display:none;"></div>
            </div>
            
            <!-- Coluna do Aluno -->
            <div style="flex:1;min-width:350px;background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;">
                <h2 style="margin-top:0;">👤 <?php _e('Aluno', 'book-manager'); ?></h2>
                <div style="display:flex;gap:10px;margin-bottom:10px;">
                    <input type="text" id="bm-student-search" placeholder="<?php _e('Buscar por nome ou e-mail...', 'book-manager'); ?>" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;" />
                    <button type="button" id="bm-student-search-btn" class="button">🔍</button>
                </div>
                <div id="bm-student-result" style="min-height:100px;padding:10px;background:#f9f9f9;border-radius:4px;">
                    <p style="color:#999;"><?php _e('Busque um aluno.', 'book-manager'); ?></p>
                </div>
                <div style="margin-top:10px;">
                    <button type="button" id="bm-new-student-btn" class="button" style="width:100%;">➕ <?php _e('Cadastrar Novo Aluno', 'book-manager'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Área de ação -->
        <div id="bm-action-area" style="margin-top:20px;text-align:center;display:none;">
            <button type="button" id="bm-loan-btn" class="button button-primary" style="font-size:18px;padding:15px 40px;">📤 <?php _e('EMPRESTAR', 'book-manager'); ?></button>
            <button type="button" id="bm-return-btn" class="button" style="font-size:18px;padding:15px 40px;background:#46b450;color:#fff;border-color:#46b450;">📥 <?php _e('DEVOLVER', 'book-manager'); ?></button>
            <button type="button" id="bm-renew-btn" class="button" style="font-size:16px;padding:12px 30px;background:#ffc107;color:#111;border-color:#ffc107;">🔄 <?php _e('Renovar +7 dias', 'book-manager'); ?></button>
        </div>
        
        <!-- Área de resultado da ação -->
        <div id="bm-action-result" style="margin-top:15px;display:none;"></div>
    </div>
    
    <!-- Modal de cadastro rápido de aluno -->
    <div id="bm-quick-register-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:25px;border-radius:8px;max-width:450px;width:90%;max-height:80vh;overflow-y:auto;">
            <h2 style="margin-top:0;" id="bm-modal-title">➕ <?php _e('Cadastro Rápido de Aluno', 'book-manager'); ?></h2>
            <form id="bm-quick-register-form" onsubmit="return false;">
                <?php wp_nonce_field('bm_service_nonce', 'bm_quick_register_nonce'); ?>
                <p>
                    <label><strong><?php _e('Nome completo', 'book-manager'); ?> *</strong></label>
                    <input type="text" name="bm_quick_name" required style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <p>
                    <label><strong><?php _e('E-mail', 'book-manager'); ?> *</strong></label>
                    <input type="email" name="bm_quick_email" required style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <p>
                    <label><strong><?php _e('Telefone', 'book-manager'); ?></strong></label>
                    <input type="text" name="bm_quick_phone" style="width:100%;padding:8px;margin-top:4px;" placeholder="5511999999999" />
                </p>
                <?php
                $user_fields = get_option('bm_user_dynamic_fields', array());
                $user_field_order = get_option('bm_user_field_order', array());
                $ordered_fields = array();
                foreach ($user_field_order as $key) {
                    if (isset($user_fields[$key])) $ordered_fields[$key] = $user_fields[$key];
                }
                foreach ($user_fields as $key => $info) {
                    if (!isset($ordered_fields[$key])) $ordered_fields[$key] = $info;
                }
                foreach ($ordered_fields as $field_name => $info):
                    $name_lower = mb_strtolower(trim($field_name));
                    if (in_array($name_lower, array('nome completo', 'e-mail', 'email', 'telefone'))) continue;
                    $meta_key = '_bm_user_' . sanitize_key($field_name);
                ?>
                <p>
                    <label><strong><?php echo esc_html($field_name); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr($meta_key); ?>" style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <?php endforeach; ?>
                <p style="margin-top:15px;display:flex;gap:10px;">
                    <button type="submit" class="button button-primary" style="flex:1;" id="bm-modal-submit-btn"><?php _e('Cadastrar', 'book-manager'); ?></button>
                    <button type="button" class="button" onclick="document.getElementById('bm-quick-register-modal').style.display='none'" style="flex:1;"><?php _e('Cancelar', 'book-manager'); ?></button>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Modal de danos na devolução -->
    <div id="bm-damage-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:25px;border-radius:8px;max-width:400px;width:90%;">
            <h3 style="margin-top:0;">📋 <?php _e('Registro de Devolução', 'book-manager'); ?></h3>
            <p>
                <label><strong><?php _e('Estado do livro:', 'book-manager'); ?></strong></label>
                <select id="bm-damage-status" style="width:100%;padding:8px;margin-top:4px;">
                    <option value="good">✅ <?php _e('Bom', 'book-manager'); ?></option>
                    <option value="acceptable">⚠️ <?php _e('Aceitável', 'book-manager'); ?></option>
                    <option value="damaged">❌ <?php _e('Danificado', 'book-manager'); ?></option>
                </select>
            </p>
            <p>
                <label><strong><?php _e('Observação:', 'book-manager'); ?></strong></label>
                <textarea id="bm-damage-note" rows="3" style="width:100%;margin-top:4px;" placeholder="<?php _e('Descreva o dano...', 'book-manager'); ?>"></textarea>
            </p>
            <p style="display:flex;gap:10px;">
                <button type="button" id="bm-confirm-return" class="button button-primary" style="flex:1;"><?php _e('Confirmar Devolução', 'book-manager'); ?></button>
                <button type="button" class="button" onclick="document.getElementById('bm-damage-modal').style.display='none'" style="flex:1;"><?php _e('Cancelar', 'book-manager'); ?></button>
            </p>
        </div>
    </div>
    
    <script>
    var bmNonce = '<?php echo $nonce; ?>';
    var bmAjaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    var bmSelectedBook = null;
    var bmSelectedStudent = null;
    
    // Leitor de código de barras
    document.getElementById('bm-barcode-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            var isbn = this.value.trim();
            if (isbn) bmSearchBookByISBN(isbn);
        }
    });
    
    // Buscar livro
    document.getElementById('bm-book-search-btn').addEventListener('click', function() {
        var query = document.getElementById('bm-book-search').value.trim();
        if (query) bmSearchBook(query);
    });
    document.getElementById('bm-book-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            var query = this.value.trim();
            if (query) bmSearchBook(query);
        }
    });
    
    // Buscar aluno
    document.getElementById('bm-student-search-btn').addEventListener('click', function() {
        var query = document.getElementById('bm-student-search').value.trim();
        if (query) bmSearchStudent(query);
    });
    document.getElementById('bm-student-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            var query = this.value.trim();
            if (query) bmSearchStudent(query);
        }
    });
    
    // Modal cadastro rápido
    document.getElementById('bm-new-student-btn').addEventListener('click', function() {
        document.getElementById('bm-quick-register-modal').style.display = 'flex';
    });
    
    function bmSearchBookByISBN(isbn) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.found) {
                    bmDisplayBook(r.book);
                } else if (r.can_register) {
                    bmShowBookNotFound(isbn, r.isbn);
                } else {
                    document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>';
                }
            } catch(e) {
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>';
            }
        };
        xhr.send('action=bm_service_search_book&isbn=' + encodeURIComponent(isbn) + '&nonce=' + bmNonce);
    }
    
    function bmSearchBook(query) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.found) {
                    bmDisplayBook(r.book);
                } else {
                    document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>';
                }
            } catch(e) {
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>';
            }
        };
        xhr.send('action=bm_service_search_book&query=' + encodeURIComponent(query) + '&nonce=' + bmNonce);
    }
    
    function bmDisplayBook(book) {
        bmSelectedBook = book;
        var stockColor = book.available > 0 ? '#46b450' : '#dc3545';
        var html = '<div style="padding:10px;">';
        html += '<h3 style="margin:0 0 5px 0;">' + book.title + '</h3>';
        if (book.author) html += '<p style="margin:3px 0;"><strong>Autor:</strong> ' + book.author + '</p>';
        if (book.cdu) html += '<p style="margin:3px 0;"><strong>Classificação:</strong> ' + book.cdu + '</p>';
        html += '<p style="margin:3px 0;"><strong>Disponível:</strong> <span style="color:' + stockColor + ';font-weight:bold;">' + book.available + '/' + book.total + '</span></p>';
        if (book.consulta_local) html += '<p style="margin:3px 0;color:#dc3545;">📌 <strong>Consulta local</strong> — não pode sair da biblioteca</p>';
        if (book.overdue) html += '<p style="margin:3px 0;color:#dc3545;">⚠️ Este livro está com devolução atrasada</p>';
        html += '</div>';
        
        document.getElementById('bm-book-result').innerHTML = html;
        
        // Mostrar fila de espera
        if (book.queue && book.queue.length > 0) {
            var qHtml = '<div style="margin-top:10px;padding:10px;background:#fff8e1;border-radius:4px;"><strong>📋 Fila de espera:</strong><ol style="margin:5px 0;padding-left:20px;">';
            book.queue.forEach(function(q) {
                qHtml += '<li>' + q.name + ' (desde ' + q.date + ')</li>';
            });
            qHtml += '</ol></div>';
            document.getElementById('bm-book-queue').style.display = 'block';
            document.getElementById('bm-book-queue').innerHTML = qHtml;
        } else {
            document.getElementById('bm-book-queue').style.display = 'none';
        }
        
        bmCheckActionReady();
    }
    
    function bmShowBookNotFound(isbn, cleanIsbn) {
        var html = '<p style="color:#dc3545;">Livro não encontrado no acervo.</p>';
        html += '<button type="button" class="button button-primary" onclick="bmRegisterBookByISBN(\'' + cleanIsbn + '\')">📚 Cadastrar este livro via Google Books</button>';
        document.getElementById('bm-book-result').innerHTML = html;
    }
    
    function bmSearchStudent(query) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.found) {
                    bmDisplayStudent(r.student);
                } else if (r.multiple) {
                    bmDisplayStudentList(r.students);
                } else {
                    document.getElementById('bm-student-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>';
                }
            } catch(e) {
                document.getElementById('bm-student-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>';
            }
        };
        xhr.send('action=bm_service_search_student&query=' + encodeURIComponent(query) + '&nonce=' + bmNonce);
    }
    
    function bmDisplayStudent(student) {
        bmSelectedStudent = student;
        var html = '<div style="padding:10px;">';
        html += '<h3 style="margin:0 0 5px 0;"><a href="<?php echo admin_url("edit.php?post_type=bm_book&page=bm_student_detail"); ?>&student_id=' + student.id + '" style="text-decoration:none;color:#111;" target="_blank">' + student.name + '</a> <button type="button" class="button button-small" onclick="bmEditStudent(' + student.id + ')" style="margin-left:10px;font-size:11px;">✏️ Editar</button></h3>';
        html += '<p style="margin:3px 0;"><strong>E-mail:</strong> ' + student.email + '</p>';
        if (student.group) html += '<p style="margin:3px 0;"><strong>Grupo:</strong> ' + student.group + '</p>';
        html += '<p style="margin:3px 0;"><strong>Empréstimos ativos:</strong> ' + student.active_loans + '/' + student.max_loans + '</p>';
        if (student.has_overdue) html += '<p style="margin:3px 0;color:#dc3545;">⚠️ <strong>Possui livro em atraso</strong></p>';
        if (student.blocked) html += '<p style="margin:3px 0;color:#dc3545;">🚫 <strong>Empréstimo bloqueado</strong> — aluno com atraso</p>';
        
        if (student.recent_books && student.recent_books.length > 0) {
            html += '<p style="margin:5px 0 3px 0;"><strong>Últimos livros:</strong></p>';
            student.recent_books.forEach(function(b) {
                html += '<span style="display:inline-block;background:#e3f2fd;padding:2px 8px;border-radius:10px;font-size:11px;margin:2px;">' + b + '</span> ';
            });
        }
        html += '</div>';
        document.getElementById('bm-student-result').innerHTML = html;
        bmCheckActionReady();
    }
    
    function bmDisplayStudentList(students) {
        var html = '<p style="margin:0 0 10px 0;">Múltiplos alunos encontrados:</p>';
        students.forEach(function(s) {
            html += '<div style="padding:8px;margin:3px 0;background:#fff;border:1px solid #eee;border-radius:4px;cursor:pointer;" onclick="bmSelectStudent(' + s.id + ')">';
            html += '<strong>' + s.name + '</strong> — ' + s.email;
            if (s.group) html += ' | ' + s.group;
            html += '</div>';
        });
        document.getElementById('bm-student-result').innerHTML = html;
    }
    
    function bmSelectStudent(id) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.found) bmDisplayStudent(r.student);
        };
        xhr.send('action=bm_service_search_student&student_id=' + id + '&nonce=' + bmNonce);
    }
    
    function bmCheckActionReady() {
        if (bmSelectedBook && bmSelectedStudent) {
            document.getElementById('bm-action-area').style.display = 'block';
        }
    }
    
    // Ações
    document.getElementById('bm-loan-btn').addEventListener('click', function() {
        if (!bmSelectedBook || !bmSelectedStudent) return;
        if (bmSelectedStudent.blocked) {
            alert('Aluno com atraso — empréstimo bloqueado.');
            return;
        }
        if (bmSelectedBook.consulta_local) {
            alert('Este livro é de consulta local e não pode ser emprestado.');
            return;
        }
        if (bmSelectedBook.available <= 0) {
            alert('Não há exemplares disponíveis.');
            return;
        }
        var days = prompt('Prazo do empréstimo (dias):', '14');
        if (!days) return;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            var area = document.getElementById('bm-action-result');
            area.style.display = 'block';
            area.innerHTML = '<div class="notice notice-' + (r.success ? 'success' : 'error') + '"><p>' + r.message + '</p></div>';
            if (r.success) {
                bmSelectedBook = null; bmSelectedStudent = null;
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#999;">Busque um livro.</p>';
                document.getElementById('bm-student-result').innerHTML = '<p style="color:#999;">Busque um aluno.</p>';
                document.getElementById('bm-action-area').style.display = 'none';
                document.getElementById('bm-book-queue').style.display = 'none';
            }
        };
        xhr.send('action=bm_service_loan&book_id=' + bmSelectedBook.id + '&user_id=' + bmSelectedStudent.id + '&days=' + days + '&nonce=' + bmNonce);
    });
    
    document.getElementById('bm-return-btn').addEventListener('click', function() {
        if (!bmSelectedBook || !bmSelectedStudent) return;
        document.getElementById('bm-damage-modal').style.display = 'flex';
    });
    
    document.getElementById('bm-confirm-return').addEventListener('click', function() {
        var condition = document.getElementById('bm-damage-status').value;
        var note = document.getElementById('bm-damage-note').value;
        document.getElementById('bm-damage-modal').style.display = 'none';
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            var area = document.getElementById('bm-action-result');
            area.style.display = 'block';
            area.innerHTML = '<div class="notice notice-' + (r.success ? 'success' : 'error') + '"><p>' + r.message + '</p></div>';
            if (r.success) {
                bmSelectedBook = null; bmSelectedStudent = null;
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#999;">Busque um livro.</p>';
                document.getElementById('bm-student-result').innerHTML = '<p style="color:#999;">Busque um aluno.</p>';
                document.getElementById('bm-action-area').style.display = 'none';
                document.getElementById('bm-book-queue').style.display = 'none';
            }
        };
        xhr.send('action=bm_service_return&book_id=' + bmSelectedBook.id + '&user_id=' + bmSelectedStudent.id + '&condition=' + condition + '&note=' + encodeURIComponent(note) + '&nonce=' + bmNonce);
    });
    
    document.getElementById('bm-renew-btn').addEventListener('click', function() {
        if (!bmSelectedBook || !bmSelectedStudent) return;
        var days = prompt('Renovar por quantos dias?', '7');
        if (!days) return;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            var area = document.getElementById('bm-action-result');
            area.style.display = 'block';
            area.innerHTML = '<div class="notice notice-' + (r.success ? 'success' : 'error') + '"><p>' + r.message + '</p></div>';
        };
        xhr.send('action=bm_service_renew&book_id=' + bmSelectedBook.id + '&user_id=' + bmSelectedStudent.id + '&days=' + days + '&nonce=' + bmNonce);
    });
    
    // Cadastro/Edição rápida de aluno
    document.getElementById('bm-quick-register-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var editId = form.getAttribute('data-edit-id');
        
        var params = 'nonce=' + bmNonce;
        params += '&name=' + encodeURIComponent(form.querySelector('[name="bm_quick_name"]').value);
        params += '&email=' + encodeURIComponent(form.querySelector('[name="bm_quick_email"]').value);
        params += '&phone=' + encodeURIComponent(form.querySelector('[name="bm_quick_phone"]').value);
        
        var dynamicInputs = form.querySelectorAll('input[name^="_bm_user_"]');
        dynamicInputs.forEach(function(input) {
            params += '&' + input.name + '=' + encodeURIComponent(input.value);
        });
        
        if (editId) {
            params = 'action=bm_service_edit_student&student_id=' + editId + '&' + params;
        } else {
            params = 'action=bm_service_quick_register&' + params;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.success) {
                document.getElementById('bm-quick-register-modal').style.display = 'none';
                form.removeAttribute('data-edit-id');
                document.getElementById('bm-modal-title').textContent = '➕ Cadastro Rápido de Aluno';
                document.getElementById('bm-modal-submit-btn').textContent = 'Cadastrar';
                if (editId) {
                    bmSelectStudent(editId);
                } else {
                    bmSelectedStudent = { id: r.student_id, name: r.student_name };
                    document.getElementById('bm-student-result').innerHTML = '<h3>' + r.student_name + '</h3><p style="color:green;">' + r.message + '</p>';
                    bmCheckActionReady();
                }
            } else {
                alert(r.message);
            }
        };
        xhr.send(params);
    });
    
    // Cadastro de livro por ISBN
    function bmRegisterBookByISBN(isbn) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.success) {
                document.getElementById('bm-book-result').innerHTML = '<div style="padding:10px;"><h3>' + r.book_title + '</h3><p style="color:green;">' + r.message + '</p></div>';
                bmSelectedBook = { id: r.book_id, title: r.book_title, author: r.book_author, available: 1, total: 1, consulta_local: false };
                bmCheckActionReady();
            } else {
                alert(r.message);
            }
        };
        xhr.send('action=bm_service_register_book_by_isbn&isbn=' + isbn + '&nonce=' + bmNonce);
    }
    
    // Editar aluno
    function bmEditStudent(id) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.found) {
                var s = r.student;
                var form = document.querySelector('#bm-quick-register-form');
                form.querySelector('[name="bm_quick_name"]').value = s.name || '';
                form.querySelector('[name="bm_quick_email"]').value = s.email || '';
                form.querySelector('[name="bm_quick_phone"]').value = s.phone || '';
                if (s.dynamic_fields) {
                    for (var key in s.dynamic_fields) {
                        var input = form.querySelector('[name="' + key + '"]');
                        if (input) input.value = s.dynamic_fields[key] || '';
                    }
                }
                form.setAttribute('data-edit-id', id);
                document.getElementById('bm-modal-title').textContent = '✏️ Editar Aluno';
                document.getElementById('bm-modal-submit-btn').textContent = 'Salvar Alterações';
                document.getElementById('bm-quick-register-modal').style.display = 'flex';
            }
        };
        xhr.send('action=bm_service_search_student&student_id=' + id + '&nonce=' + bmNonce);
    }
    </script>
    <?php
}

// ==========================================
// FASE 12J: ADMINISTRAÇÃO DE ALUNOS
// FASE 18: Unificado em página Alunos com abas
// ==========================================
function bm_add_students_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Alunos', 'book-manager'), __('Alunos', 'book-manager'), 'edit_bm_books', 'bm_students', 'bm_render_students_unified_page');
}
add_action('admin_menu', 'bm_add_students_page');

function bm_render_students_unified_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';
    ?>
    <div class="wrap">
        <h1><?php _e('Alunos', 'book-manager'); ?></h1>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=list'); ?>" class="nav-tab <?php echo $tab === 'list' ? 'nav-tab-active' : ''; ?>">👥 <?php _e('Lista de Alunos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=approve_users'); ?>" class="nav-tab <?php echo $tab === 'approve_users' ? 'nav-tab-active' : ''; ?>">✅ <?php _e('Aprovar Cadastros', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=approve_readings'); ?>" class="nav-tab <?php echo $tab === 'approve_readings' ? 'nav-tab-active' : ''; ?>">📝 <?php _e('Aprovar Fichas', 'book-manager'); ?></a>
        </nav>
        
        <?php
        if ($tab === 'approve_users') {
            bm_render_approval_page_content();
        } elseif ($tab === 'approve_readings') {
            bm_render_reading_approval_page_content();
        } else {
            bm_render_students_page_content();
        }
        ?>
    </div>
    <?php
}

function bm_render_students_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    bm_render_students_page_content();
}

function bm_render_students_page_content() {
    $msg = '';
    
    // Ações em lote
    if (isset($_POST['bm_bulk_action']) && wp_verify_nonce($_POST['bm_students_nonce'], 'bm_students_action')) {
        $action = sanitize_text_field($_POST['bm_bulk_action']);
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        
        if (!empty($user_ids) && in_array($action, array('approve', 'suspend', 'delete'))) {
            $count = 0;
            foreach ($user_ids as $uid) {
                $user = get_userdata($uid);
                if (!$user || user_can($uid, 'manage_options')) continue;
                
                if ($action === 'approve') {
                    $requested_role = get_user_meta($uid, 'bm_requested_role', true) ?: 'bm_student';
                    wp_update_user(array('ID' => $uid, 'role' => $requested_role));
                    update_user_meta($uid, 'bm_approval_status', 'approved');
                    bm_log_admin_action('Aprovou aluno (lote)', $uid);
                    $count++;
                } elseif ($action === 'suspend') {
                    wp_update_user(array('ID' => $uid, 'role' => 'subscriber'));
                    update_user_meta($uid, 'bm_approval_status', 'suspended');
                    bm_log_admin_action('Suspendeu aluno', $uid);
                    $count++;
                } elseif ($action === 'delete') {
                    if (get_current_user_id() !== $uid) {
                        bm_log_admin_action('Excluiu aluno', $uid);
                        wp_delete_user($uid);
                        $count++;
                    }
                }
            }
            $msg = '<div class="notice notice-success"><p>' . sprintf(__('%d aluno(s) afetado(s).', 'book-manager'), $count) . '</p></div>';
        }
    }
    
    // Filtros
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_group = isset($_GET['filter_group']) ? sanitize_text_field($_GET['filter_group']) : '';
    $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
    $filter_overdue = isset($_GET['filter_overdue']) ? true : false;
    
    $args = array('role' => 'bm_student', 'number' => 50);
    if ($filter_search) $args['search'] = '*' . $filter_search . '*';
    
    if ($filter_status === 'pending') {
        $args['meta_key'] = 'bm_approval_status';
        $args['meta_value'] = 'pending';
    } elseif ($filter_status === 'suspended') {
        $args['role'] = 'subscriber';
        $args['meta_key'] = 'bm_approval_status';
        $args['meta_value'] = 'suspended';
    }
    
    if ($filter_group) {
        $args['meta_query'][] = array('key' => 'bm_student_group', 'value' => $filter_group, 'compare' => 'LIKE');
    }
    
    $students = get_users($args);
    
    // Se filtro por atraso, filtrar manualmente
    if ($filter_overdue) {
        $filtered = array();
        foreach ($students as $student) {
            $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
            $has_overdue = false;
            foreach ($loan_history as $loan) {
                if ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < time()) {
                    $has_overdue = true;
                    break;
                }
            }
            if ($has_overdue) $filtered[] = $student;
        }
        $students = $filtered;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Alunos', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        
        <form method="get" style="margin-bottom:15px;">
            <input type="hidden" name="post_type" value="bm_book">
            <input type="hidden" name="page" value="bm_students">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <div>
                    <label><?php _e('Buscar', 'book-manager'); ?></label>
                    <input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Nome ou e-mail', 'book-manager'); ?>" style="padding:4px 8px;" />
                </div>
                <div>
                    <label><?php _e('Status', 'book-manager'); ?></label>
                    <select name="filter_status" style="padding:4px 8px;">
                        <option value=""><?php _e('Todos', 'book-manager'); ?></option>
                        <option value="approved" <?php selected($filter_status, 'approved'); ?>><?php _e('Aprovado', 'book-manager'); ?></option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php _e('Pendente', 'book-manager'); ?></option>
                        <option value="suspended" <?php selected($filter_status, 'suspended'); ?>><?php _e('Suspenso', 'book-manager'); ?></option>
                    </select>
                </div>
                <div>
                    <label><?php _e('Grupo', 'book-manager'); ?></label>
                    <input type="text" name="filter_group" value="<?php echo esc_attr($filter_group); ?>" placeholder="<?php _e('Ex: 1º Ano', 'book-manager'); ?>" style="padding:4px 8px;width:80px;" />
                </div>
                <div>
                    <label><input type="checkbox" name="filter_overdue" <?php checked($filter_overdue); ?> /> <?php _e('Apenas em atraso', 'book-manager'); ?></label>
                </div>
                <div>
                    <button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button>
                    <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students'); ?>" class="button"><?php _e('Limpar', 'book-manager'); ?></a>
                </div>
            </div>
        </form>
        
        <form method="post">
            <?php wp_nonce_field('bm_students_action', 'bm_students_nonce'); ?>
            <div style="margin-bottom:10px;">
                <select name="bm_bulk_action" style="padding:4px 8px;">
                    <option value=""><?php _e('— Ações em lote —', 'book-manager'); ?></option>
                    <option value="approve"><?php _e('Aprovar', 'book-manager'); ?></option>
                    <option value="suspend"><?php _e('Suspender', 'book-manager'); ?></option>
                    <option value="delete"><?php _e('Excluir', 'book-manager'); ?></option>
                </select>
                <button type="submit" class="button" onclick="return confirm('<?php _e('Confirmar ação em lote?', 'book-manager'); ?>')"><?php _e('Aplicar', 'book-manager'); ?></button>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="bm-select-all-students" /></th>
                        <th><?php _e('Aluno', 'book-manager'); ?></th>
                        <th><?php _e('E-mail', 'book-manager'); ?></th>
                        <th><?php _e('Status', 'book-manager'); ?></th>
                        <th><?php _e('Grupo', 'book-manager'); ?></th>
                        <th><?php _e('XP', 'book-manager'); ?></th>
                        <th><?php _e('Empréstimos', 'book-manager'); ?></th>
                        <th><?php _e('Ações', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="8"><?php _e('Nenhum aluno encontrado.', 'book-manager'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): 
                            $status = get_user_meta($student->ID, 'bm_approval_status', true) ?: 'approved';
                            $group = get_user_meta($student->ID, 'bm_student_group', true);
                            $xp = bm_get_xp($student->ID);
                            $phone = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Telefone'), true);
                            
                            $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
                            $active_loans = 0; $has_overdue = false;
                            foreach ($loan_history as $loan) {
                                if ($loan['status'] === 'active') {
                                    $active_loans++;
                                    if (isset($loan['due_date']) && strtotime($loan['due_date']) < time()) {
                                        $has_overdue = true;
                                    }
                                }
                            }
                            
                            $row_style = $has_overdue ? 'background:#fff3f3;' : '';
                            $status_labels = array('approved' => '✅', 'pending' => '⏳', 'suspended' => '🚫');
                            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : '✅';
                        ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td><input type="checkbox" name="user_ids[]" value="<?php echo $student->ID; ?>" /></td>
                                <td>
                                    <strong><?php echo esc_html($student->display_name); ?></strong>
                                    <?php if ($has_overdue): ?> <span style="color:#dc3545;" title="<?php _e('Em atraso', 'book-manager'); ?>">🔴</span><?php endif; ?>
                                </td>
                                <td><?php echo esc_html($student->user_email); ?></td>
                                <td><?php echo $status_label . ' ' . $status; ?></td>
                                <td><?php echo esc_html($group); ?></td>
                                <td><?php echo $xp; ?></td>
                                <td><?php echo $active_loans; ?> ativo(s)</td>
                                <td>
                                    <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $student->ID); ?>" class="button button-small"><?php _e('Ver', 'book-manager'); ?></a>
                                    <?php if ($phone): ?>
                                        <?php echo bm_whatsapp_button($phone, '', 'WhatsApp'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>
    
    <script>
    document.getElementById('bm-select-all-students').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="user_ids[]"]');
        checkboxes.forEach(function(cb) { cb.checked = this.checked; }.bind(this));
    });
    </script>
    <?php
}

// ==========================================
// FASE 12H: IMPORTAÇÃO DE ALUNOS EM MASSA
// ==========================================
// FASE 18: Movido para Importação/Exportação (aba Importar Alunos CSV)

function bm_render_student_import_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $message = '';
    $stage = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';
    $headers = array();
    
    // Estágio 3: Processamento
    if ('process' === $stage && isset($_POST['bm_student_import_nonce']) && wp_verify_nonce($_POST['bm_student_import_nonce'], 'bm_student_import_action')) {
        $import_as = isset($_POST['import_as']) && $_POST['import_as'] === 'pending' ? 'pending' : 'approved';
        $imported = 0; $skipped = 0; $dup_skipped = 0;
        
        $mapping_raw = isset($_POST['mapping']) ? array_map('sanitize_text_field', $_POST['mapping']) : array();
        $mapping = array();
        foreach ($mapping_raw as $csv_index => $field) {
            if (!empty($field)) $mapping[$field] = intval($csv_index);
        }
        
        if (!empty($_POST['csv_data'])) {
            $rows = json_decode(stripslashes($_POST['csv_data']), true);
            foreach ($rows as $row) {
                $display_name = ''; $user_email = ''; $user_login = ''; $user_pass = '';
                
                $nome_key = '_bm_user_' . sanitize_key('Nome completo');
                $email_key = '_bm_user_' . sanitize_key('E-mail');
                if (isset($mapping[$nome_key]) && isset($row[$mapping[$nome_key]])) $display_name = trim(sanitize_text_field($row[$mapping[$nome_key]]));
                if (isset($mapping[$email_key]) && isset($row[$mapping[$email_key]])) $user_email = sanitize_email($row[$mapping[$email_key]]);
                if (isset($mapping['user_login']) && isset($row[$mapping['user_login']])) $user_login = sanitize_user($row[$mapping['user_login']]);
                if (isset($mapping['user_pass']) && isset($row[$mapping['user_pass']])) $user_pass = $row[$mapping['user_pass']];
                
                if (empty($display_name) || empty($user_email)) { $skipped++; continue; }
                
                if (email_exists($user_email)) { $dup_skipped++; continue; }
                
                if (empty($user_login)) $user_login = sanitize_user($user_email);
                if (empty($user_pass)) $user_pass = wp_generate_password(12, false);
                
                $user_id = wp_insert_user(array(
                    'user_login' => $user_login,
                    'user_email' => $user_email,
                    'user_pass' => $user_pass,
                    'display_name' => $display_name,
                    'role' => 'bm_student',
                ));
                
                if (!is_wp_error($user_id)) {
                    update_user_meta($user_id, 'bm_approval_status', $import_as);
                    update_user_meta($user_id, '_bm_user_' . sanitize_key('Nome completo'), $display_name);
                    update_user_meta($user_id, '_bm_user_' . sanitize_key('E-mail'), $user_email);
                    
                    if (isset($mapping['bm_student_group']) && isset($row[$mapping['bm_student_group']])) {
                        update_user_meta($user_id, 'bm_student_group', sanitize_text_field($row[$mapping['bm_student_group']]));
                    }
                    
                    // Campos dinâmicos de alunos
                    $user_dynamic_fields = get_option('bm_user_dynamic_fields', array());
                    foreach ($user_dynamic_fields as $field_name => $info) {
                        $meta_key = '_bm_user_' . sanitize_key($field_name);
                        if (isset($mapping[$meta_key]) && isset($row[$mapping[$meta_key]])) {
                            update_user_meta($user_id, $meta_key, sanitize_text_field($row[$mapping[$meta_key]]));
                        }
                    }
                    
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }
        $message = sprintf(__('%d alunos importados, %d ignorados (sem nome/e-mail), %d duplicados pulados.', 'book-manager'), $imported, $skipped, $dup_skipped);
    }
    
    // Estágio 2: Leitura do arquivo
    if ('' === $stage && isset($_FILES['csv_file']) && isset($_POST['bm_student_import_nonce'])) {
        if (!wp_verify_nonce($_POST['bm_student_import_nonce'], 'bm_student_import_action')) {
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
                    $line = 0; $all_rows = array();
                    while (($data = fgetcsv($handle, 0, ';')) !== false) {
                        if (1 === ++$line) { $headers = array_map('sanitize_text_field', $data); continue; }
                        $all_rows[] = $data;
                    }
                    fclose($handle);
                    if (empty($headers)) {
                        $message = __('Arquivo sem cabeçalho.', 'book-manager');
                    } else {
                        $stage = 'map';
                    }
                    $_POST['csv_data_preview'] = json_encode($all_rows, JSON_UNESCAPED_UNICODE);
                    $_POST['csv_headers'] = json_encode($headers, JSON_UNESCAPED_UNICODE);
                }
            }
        }
    }
    
    // Campos mapeáveis
    $system_fields = array();
    $user_dynamic_fields = get_option('bm_user_dynamic_fields', array());
    if (!is_array($user_dynamic_fields)) $user_dynamic_fields = array();
    foreach ($user_dynamic_fields as $df => $info) {
        $system_fields['_bm_user_' . sanitize_key($df)] = $df . ' (' . __('dinâmico', 'book-manager') . ')';
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Importar Alunos via CSV', 'book-manager'); ?></h1>
        <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        
        <?php if ('map' === $stage && !empty($headers)): ?>
            <h2><?php _e('Mapeamento de Colunas', 'book-manager'); ?></h2>
            <p><?php _e('Associe cada coluna do seu arquivo ao campo correspondente no sistema.', 'book-manager'); ?></p>
            <form method="post">
                <?php wp_nonce_field('bm_student_import_action', 'bm_student_import_nonce'); ?>
                <input type="hidden" name="import_stage" value="process">
                <input type="hidden" name="csv_data" value="<?php echo esc_attr(json_encode(json_decode(stripslashes($_POST['csv_data_preview']), true), JSON_UNESCAPED_UNICODE)); ?>">
                <h3><?php _e('Mapear colunas', 'book-manager'); ?></h3>
                <?php foreach ($headers as $i => $h): ?>
                    <p><strong><?php echo esc_html($h); ?></strong> →
                    <select name="mapping[<?php echo $i; ?>]">
                        <option value=""><?php _e('— Ignorar —', 'book-manager'); ?></option>
                        <?php foreach ($system_fields as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select></p>
                <?php endforeach; ?>
                <p><strong><?php _e('Importar como:', 'book-manager'); ?></strong>
                    <label><input type="radio" name="import_as" value="approved" checked> <?php _e('Aprovado (direto)', 'book-manager'); ?></label>
                    <label style="margin-left:10px;"><input type="radio" name="import_as" value="pending"> <?php _e('Pendente (aguardando aprovação)', 'book-manager'); ?></label></p>
                <?php submit_button(__('Importar Alunos', 'book-manager')); ?>
            </form>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bm_student_import_action', 'bm_student_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="csv_file"><?php _e('Arquivo CSV', 'book-manager'); ?></label></th>
                        <td><input type="file" id="csv_file" name="csv_file" accept=".csv" /><p class="description"><?php _e('CSV com cabeçalho na primeira linha. Delimitador: ponto e vírgula (;).', 'book-manager'); ?></p></td>
                    </tr>
                </table>
                <?php submit_button(__('Enviar Arquivo', 'book-manager')); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}



// ==========================================
// FASE 11C: GERAÇÃO DE ETIQUETAS
// ==========================================

function bm_add_labels_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Etiquetas', 'book-manager'), __('Etiquetas', 'book-manager'), 'edit_bm_books', 'bm_labels', 'bm_render_labels_page');
}

add_action('admin_menu', 'bm_add_labels_page');

function bm_labels_init_session() {
    if (!session_id() && !headers_sent()) session_start();
    if (!isset($_SESSION['bm_labels_cart'])) $_SESSION['bm_labels_cart'] = array();
}
add_action('init', 'bm_labels_init_session');

function bm_ajax_toggle_label() {
    if (!session_id()) session_start();
    if (!isset($_SESSION['bm_labels_cart'])) $_SESSION['bm_labels_cart'] = array();
    
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
    if (!$book_id) wp_die(json_encode(array('success' => false)));
    
    if (in_array($book_id, $_SESSION['bm_labels_cart'])) {
        $_SESSION['bm_labels_cart'] = array_diff($_SESSION['bm_labels_cart'], array($book_id));
        $action = 'removed';
    } else {
        $_SESSION['bm_labels_cart'][] = $book_id;
        $action = 'added';
    }
    
    wp_die(json_encode(array('success' => true, 'action' => $action, 'count' => count($_SESSION['bm_labels_cart']))));
}
add_action('wp_ajax_bm_toggle_label', 'bm_ajax_toggle_label');

function bm_label_button() {
    if (!is_singular('bm_book')) return;
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $book_id = get_the_ID();
    if (!session_id()) session_start();
    $in_cart = isset($_SESSION['bm_labels_cart']) && in_array($book_id, $_SESSION['bm_labels_cart']);
    $label = $in_cart ? '➖ ' . __('Remover etiqueta', 'book-manager') : '➕ ' . __('Adicionar etiqueta', 'book-manager');
    $color = $in_cart ? '#dc3545' : '#111';
    ?>
    <div style="margin:10px 0;">
        <button type="button" class="bm-label-toggle" data-book="<?php echo $book_id; ?>" style="padding:6px 12px;background:<?php echo $color; ?>;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;">
            <?php echo $label; ?>
        </button>
    </div>
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bm-label-toggle')) {
            var btn = e.target;
            var bookId = btn.getAttribute('data-book');
            btn.disabled = true;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) {
                    btn.textContent = r.action === 'added' ? '➖ Remover etiqueta' : '➕ Adicionar etiqueta';
                    btn.style.background = r.action === 'added' ? '#dc3545' : '#111';
                }
                btn.disabled = false;
            };
            xhr.send('action=bm_toggle_label&book_id=' + bookId);
        }
    });
    </script>
    <?php
}

function bm_render_labels_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    if (!session_id()) session_start();
    $cart = isset($_SESSION['bm_labels_cart']) ? $_SESSION['bm_labels_cart'] : array();
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['bm_labels_cart'] = array();
        $cart = array();
        echo '<div class="notice notice-success"><p>' . __('Etiquetas removidas.', 'book-manager') . '</p></div>';
    }
    
    $filter_genre = isset($_GET['filter_genre']) ? intval($_GET['filter_genre']) : 0;
    $filter_discipline = isset($_GET['filter_discipline']) ? intval($_GET['filter_discipline']) : 0;
    $filter_cdu = isset($_GET['filter_cdu']) ? sanitize_text_field($_GET['filter_cdu']) : '';
    $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
    
    $args = array('post_type' => 'bm_book', 'posts_per_page' => 50, 'post_status' => 'publish');
    if ($filter_genre) { $args['tax_query'][] = array('taxonomy' => 'bm_genre', 'field' => 'term_id', 'terms' => $filter_genre); }
    if ($filter_discipline) { $args['tax_query'][] = array('taxonomy' => 'bm_discipline', 'field' => 'term_id', 'terms' => $filter_discipline); }
    if ($filter_search) $args['s'] = $filter_search;
    if ($filter_cdu) { $args['meta_query'][] = array('key' => '_bm_cdu', 'value' => $filter_cdu, 'compare' => 'LIKE'); }
    
    $books = get_posts($args);
    
    if (isset($_POST['add_selected']) && isset($_POST['book_ids'])) {
        foreach ($_POST['book_ids'] as $id) {
            if (!in_array($id, $cart)) $cart[] = intval($id);
        }
        $_SESSION['bm_labels_cart'] = $cart;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Geração de Etiquetas', 'book-manager'); ?></h1>
        
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:400px;">
                <h2><?php _e('Selecionar Livros', 'book-manager'); ?></h2>
                
                <form method="get" style="margin-bottom:15px;">
                    <input type="hidden" name="post_type" value="bm_book">
                    <input type="hidden" name="page" value="bm_labels">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                        <div><label><?php _e('Buscar', 'book-manager'); ?></label><input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Título ou autor', 'book-manager'); ?>" style="padding:4px 8px;" /></div>
                        <div><label><?php _e('Gênero', 'book-manager'); ?></label><?php wp_dropdown_categories(array('show_option_all' => __('Todos', 'book-manager'), 'taxonomy' => 'bm_genre', 'name' => 'filter_genre', 'selected' => $filter_genre, 'hide_empty' => true)); ?></div>
                        <div><label><?php _e('Disciplina', 'book-manager'); ?></label><?php wp_dropdown_categories(array('show_option_all' => __('Todas', 'book-manager'), 'taxonomy' => 'bm_discipline', 'name' => 'filter_discipline', 'selected' => $filter_discipline, 'hide_empty' => true)); ?></div>
                        <div><label><?php _e('Classif.', 'book-manager'); ?></label><input type="text" name="filter_cdu" value="<?php echo esc_attr($filter_cdu); ?>" style="width:80px;padding:4px 8px;" /></div>
                        <div><button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button></div>
                    </div>
                </form>
                
                <form method="post">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="bm-select-all" /></th>
                                <th><?php _e('Título', 'book-manager'); ?></th>
                                <th><?php _e('Autor', 'book-manager'); ?></th>
                                <th><?php _e('Classif.', 'book-manager'); ?></th>
                                <th><?php _e('Ex.', 'book-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): 
                                $author = get_post_meta($book->ID, '_bm_author', true);
                                $cdu = get_post_meta($book->ID, '_bm_cdu', true);
                                $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true)));
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="book_ids[]" value="<?php echo $book->ID; ?>" <?php checked(in_array($book->ID, $cart)); ?> /></td>
                                    <td><?php echo esc_html($book->post_title); ?></td>
                                    <td><?php echo esc_html($author); ?></td>
                                    <td><?php echo esc_html($cdu); ?></td>
                                    <td><?php echo $copies; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:10px;">
                        <button type="submit" name="add_selected" class="button button-primary"><?php _e('Adicionar etiquetas', 'book-manager'); ?></button>
                    </p>
                </form>
                
                    <script>
    document.getElementById('bm_upload_logo').addEventListener('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'Selecionar logo',
            button: { text: 'Usar esta imagem' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('bm_school_logo').value = attachment.url;
        });
        frame.open();
    });
    </script>
            </div>
            
            <div style="flex:0 0 350px;">
                <h2>🖨️ <?php _e('Etiquetas selecionadas', 'book-manager'); ?> (<?php echo count($cart); ?>)</h2>
                
                <?php if (empty($cart)): ?>
                    <p><?php _e('Nenhuma etiqueta selecionada.', 'book-manager'); ?></p>
                <?php else: ?>
                    <ul style="max-height:400px;overflow-y:auto;list-style:none;padding:0;margin:0;">
                        <?php 
                        $cart_books = get_posts(array('post_type' => 'bm_book', 'post__in' => $cart, 'posts_per_page' => -1, 'orderby' => 'post__in'));
                        foreach ($cart_books as $book): 
                            $author = get_post_meta($book->ID, '_bm_author', true);
                            $cdu = get_post_meta($book->ID, '_bm_cdu', true);
                            $cutter = get_post_meta($book->ID, '_bm_cutter', true);
                            $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true)));
                        ?>
                            <li style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #eee;">
                                <button type="button" class="bm-remove-label" data-book="<?php echo $book->ID; ?>" style="background:#dc3545;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1;">✕</button>
                                <div style="flex:1;font-size:12px;">
                                    <strong><?php echo esc_html($book->post_title); ?></strong>
                                    <?php if ($author): ?><br><small><?php echo esc_html($author); ?></small><?php endif; ?>
                                    <?php if ($cdu): ?><br><small>Class: <?php echo esc_html($cdu); ?> | Cutter: <?php echo esc_html($cutter); ?></small><?php endif; ?>
                                    <br><small><?php printf(__('%d exemplares', 'book-manager'), $copies); ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="post" style="margin-top:10px;display:flex;gap:10px;">
                        <button type="submit" name="clear_cart" class="button"><?php _e('Limpar etiquetas', 'book-manager'); ?></button>
                        <button type="button" id="bm-preview-labels" class="button button-primary">🖨️ <?php _e('Visualizar Impressão', 'book-manager'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bm-remove-label')) {
            var bookId = e.target.getAttribute('data-book');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { location.reload(); };
            xhr.send('action=bm_toggle_label&book_id=' + bookId);
        }
    });
    
    var previewBtn = document.getElementById('bm-preview-labels');
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            var cart = <?php echo json_encode(array_values($cart)); ?>;
            if (cart.length === 0) { alert('<?php _e("Nenhuma etiqueta selecionada.", "book-manager"); ?>'); return; }
            var url = '<?php echo admin_url("admin-ajax.php"); ?>?action=bm_print_labels&ids=' + cart.join(',');
            window.open(url, '_blank');
        });
    }
    </script>
    <?php
}

function bm_ajax_print_labels() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die('Sem permissão.');
    
    $ids = isset($_GET['ids']) ? explode(',', sanitize_text_field($_GET['ids'])) : array();
    if (empty($ids)) wp_die('Nenhum livro selecionado.');
    
    $books = get_posts(array('post_type' => 'bm_book', 'post__in' => $ids, 'posts_per_page' => -1, 'orderby' => 'post__in'));
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php _e('Etiquetas — Visualização', 'book-manager'); ?></title>
        <style>
            @page { size: A4; margin: 1cm 0.5cm; }
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
            .labels-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.2cm; padding: 0; }
            .label { border: 1px dashed #ccc; padding: 0.3cm 0.2cm; text-align: center; height: 2.7cm; display: flex; flex-direction: column; justify-content: center; page-break-inside: avoid; }
            .label .author { font-weight: bold; font-size: 12px; text-transform: uppercase; margin-bottom: 2px; }
            .label .title { font-size: 10px; margin-bottom: 3px; }
            .label .cdu { font-weight: bold; font-size: 16px; margin-bottom: 2px; }
            .label .cutter { font-weight: bold; font-size: 16px; margin-bottom: 2px; }
            .label .info { font-size: 9px; color: #666; }
            .label .barcode { font-size: 9px; letter-spacing: 2px; margin-top: 3px; }
            .no-print { text-align: center; margin: 20px; }
            @media print {
                .no-print { display: none; }
                .label { border: none; }
                body { margin: 0; padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="padding:20px;background:#f9f9f9;margin-bottom:20px;">
            <h2><?php _e('Visualização de Etiquetas', 'book-manager'); ?> (<?php echo count($books); ?> livros)</h2>
            <p><?php _e('Pressione Ctrl+P para imprimir. Ajuste as margens para "Mínimo".', 'book-manager'); ?></p>
            <button onclick="window.print()" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px;">🖨️ <?php _e('Imprimir Agora', 'book-manager'); ?></button>
        </div>
        
        <div class="labels-grid">
            <?php foreach ($books as $book): 
                $author = get_post_meta($book->ID, '_bm_author', true);
                $cdu = get_post_meta($book->ID, '_bm_cdu', true);
                $cutter = get_post_meta($book->ID, '_bm_cutter', true);
                $edition = get_post_meta($book->ID, '_bm_edition', true);
                $isbn = get_post_meta($book->ID, '_bm_isbn', true);
                $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true)));
                
                $author_formatted = '';
                if ($author) {
                    $parts = explode(' ', trim($author));
                    $author_formatted = count($parts) > 1 ? mb_strtoupper(array_pop($parts)) . ', ' . implode(' ', $parts) : mb_strtoupper($author);
                }
                
                $max_labels = max(1, $copies);
                for ($i = 1; $i <= $max_labels; $i++):
            ?>
                <div class="label">
                    <div class="author"><?php echo esc_html($author_formatted); ?></div>
                    <div class="title"><?php echo esc_html($book->post_title); ?></div>
                    <div class="cdu"><?php echo esc_html($cdu); ?></div>
                    <div class="cutter"><?php echo esc_html($cutter); ?></div>
                    <div class="info">
                        <?php if ($edition) echo esc_html($edition) . ' '; ?>
                        <?php if ($copies > 1) printf(__('Ex. %d/%d', 'book-manager'), $i, $copies); ?>
                    </div>
                    <?php if ($isbn): ?>
                        <div class="barcode">|||<?php echo esc_html($isbn); ?>|||</div>
                    <?php endif; ?>
                </div>
            <?php 
                endfor;
            endforeach; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
add_action('wp_ajax_bm_print_labels', 'bm_ajax_print_labels');

// ==========================================
// FASE 12E-T2: CRIADOR DE TAXONOMIAS DINÂMICAS
// ==========================================
function bm_register_dynamic_taxonomies() {
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) return;
    
    foreach ($taxonomies as $slug => $info) {
        register_taxonomy($slug, 'bm_book', array(
            'label'        => $info['label'],
            'rewrite'      => false,
            'hierarchical' => !empty($info['hierarchical']),
            'show_ui'      => true,
            'show_in_menu' => true,
            'capabilities' => array(
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ),
        ));
    }
}
add_action('init', 'bm_register_dynamic_taxonomies', 11);

function bm_add_taxonomies_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Taxonomias', 'book-manager'), __('Taxonomias', 'book-manager'), 'edit_bm_books', 'bm_taxonomies', 'bm_render_taxonomies_page');
}
add_action('admin_menu', 'bm_add_taxonomies_page');

function bm_render_taxonomies_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $msg = '';
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) $taxonomies = array();
    
    // Criar nova taxonomia
    if (isset($_POST['bm_add_taxonomy']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) {
        $name = sanitize_text_field($_POST['bm_taxonomy_name']);
        $slug = sanitize_key($_POST['bm_taxonomy_slug'] ?: $name);
        $hierarchical = isset($_POST['bm_taxonomy_hierarchical']);
        
        if (empty($name)) {
            $msg = '<div class="notice notice-error"><p>' . __('Nome é obrigatório.', 'book-manager') . '</p></div>';
        } elseif (taxonomy_exists($slug) || isset($taxonomies[$slug])) {
            $msg = '<div class="notice notice-error"><p>' . __('Já existe uma taxonomia com este slug.', 'book-manager') . '</p></div>';
        } else {
            $taxonomies[$slug] = array('label' => $name, 'hierarchical' => $hierarchical);
            update_option('bm_dynamic_taxonomies', $taxonomies);
            flush_rewrite_rules();
            $msg = '<div class="notice notice-success"><p>' . sprintf(__('Taxonomia "%s" criada!', 'book-manager'), $name) . '</p></div>';
        }
    }
    
    // Excluir taxonomia
    if (isset($_POST['bm_delete_taxonomy']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) {
        $delete_slug = sanitize_key($_POST['bm_delete_slug']);
        if (isset($taxonomies[$delete_slug])) {
            unset($taxonomies[$delete_slug]);
            update_option('bm_dynamic_taxonomies', $taxonomies);
            flush_rewrite_rules();
            $msg = '<div class="notice notice-success"><p>' . __('Taxonomia removida.', 'book-manager') . '</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Taxonomias Dinâmicas', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        
        <h2><?php _e('Criar Nova Taxonomia', 'book-manager'); ?></h2>
        <form method="post" style="max-width:500px;">
            <?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Nome', 'book-manager'); ?></label></th>
                    <td><input type="text" name="bm_taxonomy_name" required style="width:100%;" placeholder="<?php _e('Ex: Séries', 'book-manager'); ?>" /></td>
                </tr>
                <tr>
                    <th><label><?php _e('Slug', 'book-manager'); ?></label></th>
                    <td><input type="text" name="bm_taxonomy_slug" style="width:100%;" placeholder="<?php _e('Gerado automaticamente', 'book-manager'); ?>" /><p class="description"><?php _e('Deixe em branco para gerar a partir do nome.', 'book-manager'); ?></p></td>
                </tr>
                <tr>
                    <th><label><?php _e('Hierárquica', 'book-manager'); ?></label></th>
                    <td><label><input type="checkbox" name="bm_taxonomy_hierarchical" checked /> <?php _e('Permitir subcategorias (ex: pai/filho)', 'book-manager'); ?></label></td>
                </tr>
            </table>
            <p><input type="submit" name="bm_add_taxonomy" class="button button-primary" value="<?php _e('Criar Taxonomia', 'book-manager'); ?>" /></p>
        </form>
        
        <h2><?php _e('Taxonomias Existentes', 'book-manager'); ?></h2>
        <?php if (empty($taxonomies)): ?>
            <p><?php _e('Nenhuma taxonomia criada.', 'book-manager'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Nome', 'book-manager'); ?></th>
                        <th><?php _e('Slug', 'book-manager'); ?></th>
                        <th><?php _e('Hierárquica', 'book-manager'); ?></th>
                        <th><?php _e('Ações', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($taxonomies as $slug => $info): ?>
                        <tr>
                            <td><strong><?php echo esc_html($info['label']); ?></strong></td>
                            <td><code><?php echo esc_html($slug); ?></code></td>
                            <td><?php echo $info['hierarchical'] ? '✅' : '❌'; ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('<?php _e('Remover esta taxonomia? Os termos criados serão perdidos.', 'book-manager'); ?>');">
                                    <?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?>
                                    <input type="hidden" name="bm_delete_slug" value="<?php echo esc_attr($slug); ?>">
                                    <button type="submit" name="bm_delete_taxonomy" class="button button-small"><?php _e('Remover', 'book-manager'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// FASE 17: PÁGINA DE STATUS DO SISTEMA
// ==========================================

function bm_render_status_page() {
    if (!current_user_can('manage_options')) return;
    
    $plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . '../book-manager.php');
    $keys = bm_get_api_keys();
    $settings = bm_get_settings();
    $total_books = wp_count_posts('bm_book');
    $total = $total_books->publish + $total_books->draft;
    $students = count(get_users(array('role' => 'bm_student')));
    
    $audit_log = get_option('bm_admin_audit_log', array());
    $last_actions = array_slice(array_reverse($audit_log), 0, 10);
    ?>
    <div class="wrap">
        <h1><?php _e('Status do Sistema', 'book-manager'); ?></h1>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:15px;margin-top:15px;">
            
            <!-- Card: Ambiente -->
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">🖥️ <?php _e('Ambiente', 'book-manager'); ?></h3>
                <p><strong>Plugin:</strong> <?php echo esc_html($plugin_data['Version']); ?></p>
                <p><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong>PHP:</strong> <?php echo phpversion(); ?></p>
                <p><strong>Memória:</strong> <?php echo ini_get('memory_limit'); ?></p>
            </div>
            
            <!-- Card: APIs -->
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">🔌 <?php _e('APIs', 'book-manager'); ?></h3>
                <p><strong>Google Books:</strong> <?php echo !empty($keys['google_books_key']) ? '✅ Configurada' : '❌ Não configurada'; ?></p>
                <p><strong>Groq (IA):</strong> <?php echo !empty($keys['groq_key']) ? '✅ Configurada' : '❌ Não configurada'; ?></p>
                <p><strong>IA Ativa:</strong> <?php echo ($keys['groq_active'] === '1' && !empty($keys['groq_key'])) ? '✅ Sim' : '❌ Não'; ?></p>
            </div>
            
            <!-- Card: Acervo -->
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">📚 <?php _e('Acervo', 'book-manager'); ?></h3>
                <p><strong>Total de livros:</strong> <?php echo $total; ?></p>
                <p><strong>Alunos cadastrados:</strong> <?php echo $students; ?></p>
                <p><strong>Sistema:</strong> <?php echo $settings['classification_system'] === 'cdd' ? 'CDD' : 'CDU'; ?></p>
            </div>

                        <!-- Card: Uso da IA -->
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">🤖 <?php _e('Uso da IA (Groq)', 'book-manager'); ?></h3>
                <?php 
                $groq_count = intval(get_option('bm_groq_call_count', 0));
                $groq_success = intval(get_option('bm_groq_success_count', 0));
                ?>
                <p><strong>Total de chamadas:</strong> <?php echo $groq_count; ?></p>
                <p><strong>Bem-sucedidas:</strong> <?php echo $groq_success; ?></p>
                <p><strong>Falhas:</strong> <?php echo max(0, $groq_count - $groq_success); ?></p>
            </div>
            
            
            <!-- Card: Últimas ações administrativas -->
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">📋 <?php _e('Últimas Ações', 'book-manager'); ?></h3>
                <?php if (empty($last_actions)): ?>
                    <p style="color:#999;"><?php _e('Nenhuma ação registrada.', 'book-manager'); ?></p>
                <?php else: ?>
                    <ul style="margin:0;padding-left:15px;font-size:12px;">
                        <?php foreach ($last_actions as $action): ?>
                            <li style="margin:3px 0;">
                                <?php echo esc_html($action['time']); ?> — 
                                <strong><?php echo esc_html($action['admin_user']); ?></strong>: 
                                <?php echo esc_html($action['action']); ?> 
                                <?php echo esc_html($action['target_user']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    <?php
}

// ==========================================
// FASE 12A: PÁGINA DE CONFIGURAÇÕES
// ==========================================
function bm_get_settings() {
    $defaults = array(
        'max_reservations_student' => 3,
        'max_loans_student' => 1,
        'default_loan_days' => 14,
        'reservation_hours' => 24,
        'classification_system' => 'cdu',
        'field_visibility' => array(
            'isbn'      => array('student' => 0, 'teacher' => 0, 'librarian' => 1),
            'location'  => array('student' => 0, 'teacher' => 1, 'librarian' => 1),
            'copies'    => array('student' => 0, 'teacher' => 0, 'librarian' => 1),
            'audit_log' => array('student' => 0, 'teacher' => 0, 'librarian' => 1),
        ),
    );
    $saved = get_option('bm_settings', array());
    if (!is_array($saved)) $saved = array();
    foreach ($defaults as $key => $default) {
        if (!isset($saved[$key])) $saved[$key] = $default;
    }
    return $saved;
}


function bm_add_settings_page() {
    add_submenu_page('edit.php?post_type=bm_book', 'Configurações', 'Configurações', 'manage_options', 'bm_settings', 'bm_render_settings_unified_page');
}

add_action('admin_menu', 'bm_add_settings_page');

function bm_render_settings_unified_page() {
    if (!current_user_can('manage_options')) return;
    
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    ?>
    <div class="wrap">
        <h1><?php _e('Configurações', 'book-manager'); ?></h1>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=general'); ?>" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">⚙️ <?php _e('Limites e Prazos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=apis'); ?>" class="nav-tab <?php echo $tab === 'apis' ? 'nav-tab-active' : ''; ?>">🔌 <?php _e('APIs', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=white_label'); ?>" class="nav-tab <?php echo $tab === 'white_label' ? 'nav-tab-active' : ''; ?>">🎨 <?php _e('Identidade Visual', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=year_transition'); ?>" class="nav-tab <?php echo $tab === 'year_transition' ? 'nav-tab-active' : ''; ?>">🔄 <?php _e('Virada de Ano', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=status'); ?>" class="nav-tab <?php echo $tab === 'status' ? 'nav-tab-active' : ''; ?>">📊 <?php _e('Status', 'book-manager'); ?></a>
        </nav>
        
        <?php
        if ($tab === 'apis') {
            bm_render_api_settings_page();
        } elseif ($tab === 'white_label') {
            bm_render_white_label_page();
        } elseif ($tab === 'year_transition') {
            bm_render_year_transition_page();
        } elseif ($tab === 'status') {
            bm_render_status_page();
        } else {
            bm_render_settings_page();
        }
        ?>
    </div>
    <?php
}

function bm_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $settings = bm_get_settings();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $settings['max_reservations_student'] = absint($_POST['max_reservations_student']);
        $settings['max_loans_student'] = absint($_POST['max_loans_student']);
        $settings['default_loan_days'] = absint($_POST['default_loan_days']);
        $settings['reservation_hours'] = absint($_POST['reservation_hours']);
        $settings['classification_system'] = isset($_POST['classification_system']) && $_POST['classification_system'] === 'cdd' ? 'cdd' : 'cdu';
                if (isset($_POST['librarian_permissions']) && is_array($_POST['librarian_permissions'])) {
            $settings['librarian_permissions'] = array();
            foreach (array('import_csv', 'export_csv', 'dynamic_fields', 'taxonomies', 'loans', 'approve_users', 'approve_readings', 'labels', 'service', 'students', 'student_import') as $perm) {
                $settings['librarian_permissions'][$perm] = isset($_POST['librarian_permissions'][$perm]) ? '1' : '0';
            }
        }
                if (isset($_POST['per_profile_limits']) && is_array($_POST['per_profile_limits'])) {
            $settings['per_profile_limits'] = array();
            foreach ($_POST['per_profile_limits'] as $limit) {
                if (!empty($limit['group'])) {
                    $settings['per_profile_limits'][] = array(
                        'group' => sanitize_text_field($limit['group']),
                        'max_reservations' => absint($limit['max_reservations']),
                        'max_loans' => absint($limit['max_loans']),
                    );
                }
            }
        }
        if (isset($_POST['field_visibility']) && is_array($_POST['field_visibility'])) {
            $settings['field_visibility'] = array();
            foreach (array('isbn', 'location', 'copies', 'audit_log') as $field) {
                $settings['field_visibility'][$field] = array(
                    'student'   => isset($_POST['field_visibility'][$field]['student']) ? 1 : 0,
                    'teacher'   => isset($_POST['field_visibility'][$field]['teacher']) ? 1 : 0,
                    'librarian' => isset($_POST['field_visibility'][$field]['librarian']) ? 1 : 0,
                );
            }
        }
        update_option('bm_settings', $settings);
        $msg = '<div class="notice notice-success"><p>Salvo! Reservas: ' . $settings['max_reservations_student'] . ' | Empréstimos: ' . $settings['max_loans_student'] . ' | Prazo: ' . $settings['default_loan_days'] . 'd | Reserva: ' . $settings['reservation_hours'] . 'h</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Configurações</h1>
        <?php echo $msg; ?>
        
        <form method="post" style="max-width:600px;">
            <h2>Limites e Prazos</h2>
            
            <h3>Limites Globais</h3>
            <table class="form-table">
                <tr>
                    <th><label>Máximo de reservas por aluno</label></th>
                    <td>
                        <input type="number" name="max_reservations_student" value="<?php echo esc_attr($settings['max_reservations_student']); ?>" min="1" max="10" style="width:80px;" />
                        <p class="description">Quantos livros um aluno pode reservar simultaneamente.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Máximo de empréstimos por aluno</label></th>
                    <td>
                        <input type="number" name="max_loans_student" value="<?php echo esc_attr($settings['max_loans_student']); ?>" min="1" max="10" style="width:80px;" />
                        <p class="description">Quantos livros um aluno pode pegar emprestado simultaneamente.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Prazo padrão de empréstimo (dias)</label></th>
                    <td>
                        <input type="number" name="default_loan_days" value="<?php echo esc_attr($settings['default_loan_days']); ?>" min="1" max="60" style="width:80px;" />
                        <p class="description">Prazo padrão ao confirmar um empréstimo.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Prazo de reserva (horas)</label></th>
                    <td>
                        <input type="number" name="reservation_hours" value="<?php echo esc_attr($settings['reservation_hours']); ?>" min="1" max="72" style="width:80px;" />
                        <p class="description">Tempo máximo que uma reserva aguarda retirada.</p>
                    </td>
                </tr>
            </table>

            <h2>Visibilidade de Campos por Perfil</h2>
            <p class="description">Defina quais informações administrativas cada perfil vê na página pública do livro.</p>
            <table class="form-table">
                <tr>
                    <th></th>
                    <th style="text-align:center;">Aluno</th>
                    <th style="text-align:center;">Professor</th>
                    <th style="text-align:center;">Gestor</th>
                </tr>
                <?php
                $fields = array(
                    'isbn'      => 'ISBN',
                    'location'  => 'Localização',
                    'copies'    => 'Exemplares',
                    'audit_log' => 'Histórico de Ações',
                );
                $visibility = isset($settings['field_visibility']) ? $settings['field_visibility'] : array();
                foreach ($fields as $key => $label):
                ?>
                <tr>
                    <th><label><?php echo $label; ?></label></th>
                    <td style="text-align:center;"><input type="checkbox" name="field_visibility[<?php echo $key; ?>][student]" value="1" <?php checked(isset($visibility[$key]['student']) && $visibility[$key]['student']); ?> /></td>
                    <td style="text-align:center;"><input type="checkbox" name="field_visibility[<?php echo $key; ?>][teacher]" value="1" <?php checked(isset($visibility[$key]['teacher']) && $visibility[$key]['teacher']); ?> /></td>
                    <td style="text-align:center;"><input type="checkbox" name="field_visibility[<?php echo $key; ?>][librarian]" value="1" <?php checked(isset($visibility[$key]['librarian']) && $visibility[$key]['librarian']); ?> /></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
                        
            <h3>Limites por Grupo (opcional)</h3>
            <p class="description">Defina limites diferentes para grupos específicos de alunos. Se vazio, usa o limite global acima.</p>
            <table class="form-table">
                <tr>
                    <th><label>Grupo</label></th>
                    <th><label>Máx. Reservas</label></th>
                    <th><label>Máx. Empréstimos</label></th>
                    <th></th>
                </tr>
                <?php 
                $per_profile = isset($settings['per_profile_limits']) ? $settings['per_profile_limits'] : array();
                if (!empty($per_profile)):
                    foreach ($per_profile as $i => $limit):
                ?>
                <tr>
                    <td><input type="text" name="per_profile_limits[<?php echo $i; ?>][group]" value="<?php echo esc_attr($limit['group']); ?>" placeholder="Ex: 1º Ano" style="width:120px;" /></td>
                    <td><input type="number" name="per_profile_limits[<?php echo $i; ?>][max_reservations]" value="<?php echo esc_attr($limit['max_reservations']); ?>" min="0" max="10" style="width:80px;" /></td>
                    <td><input type="number" name="per_profile_limits[<?php echo $i; ?>][max_loans]" value="<?php echo esc_attr($limit['max_loans']); ?>" min="0" max="10" style="width:80px;" /></td>
                    <td><button type="button" class="button button-small" onclick="this.closest('tr').remove()">✕</button></td>
                </tr>
                <?php 
                    endforeach;
                endif;
                ?>
                <tr id="bm-new-limit-row">
                    <td colspan="4">
                        <button type="button" class="button" id="bm-add-limit">+ Adicionar limite por grupo</button>
                    </td>
                </tr>
            </table>
            <script>
            document.getElementById('bm-add-limit').addEventListener('click', function() {
                var tbody = this.closest('table').querySelector('tbody') || this.closest('table');
                var rows = tbody.querySelectorAll('tr');
                var count = rows.length;
                var newRow = document.createElement('tr');
                newRow.innerHTML = '<td><input type="text" name="per_profile_limits[' + count + '][group]" placeholder="Ex: 1º Ano" style="width:120px;" /></td>' +
                    '<td><input type="number" name="per_profile_limits[' + count + '][max_reservations]" value="" min="0" max="10" style="width:80px;" /></td>' +
                    '<td><input type="number" name="per_profile_limits[' + count + '][max_loans]" value="" min="0" max="10" style="width:80px;" /></td>' +
                    '<td><button type="button" class="button button-small" onclick="this.closest(\'tr\').remove()">✕</button></td>';
                var addRow = document.getElementById('bm-new-limit-row');
                addRow.parentNode.insertBefore(newRow, addRow);
            });
            </script>
            
                        
            <h2>Permissões do Gestor</h2>
            <p class="description">Marque quais funcionalidades o Gestor da Biblioteca pode acessar.</p>
            <?php 
            $librarian_perms = isset($settings['librarian_permissions']) ? $settings['librarian_permissions'] : array(
                'import_csv' => '1', 'export_csv' => '1', 'dynamic_fields' => '1',
                'taxonomies' => '1', 'loans' => '1', 'approve_users' => '1',
                'approve_readings' => '1', 'labels' => '1', 'service' => '1',
                'students' => '1', 'student_import' => '1',
            );
            $perm_options = array(
                'import_csv' => 'Importar CSV',
                'export_csv' => 'Exportar CSV',
                'dynamic_fields' => 'Gerenciar Campos',
                'taxonomies' => 'Taxonomias',
                'loans' => 'Empréstimos',
                'approve_users' => 'Aprovar Cadastros',
                'approve_readings' => 'Aprovar Fichas',
                'labels' => 'Etiquetas',
                'service' => 'Atendimento',
                'students' => 'Alunos',
                'student_import' => 'Importar Alunos',
            );
            ?>
            <table class="form-table">
                <?php foreach ($perm_options as $key => $label): ?>
                <tr>
                    <th><label><?php echo $label; ?></label></th>
                    <td><label><input type="checkbox" name="librarian_permissions[<?php echo $key; ?>]" value="1" <?php checked(isset($librarian_perms[$key]) && $librarian_perms[$key] === '1'); ?> /> Permitir</label></td>
                </tr>
                <?php endforeach; ?>
            </table>
            

            <h2>Sistema de Classificação</h2>
            <table class="form-table">
                <tr>
                    <th><label>CDU ou CDD</label></th>
                    <td>
                        <label><input type="radio" name="classification_system" value="cdu" <?php checked($settings['classification_system'], 'cdu'); ?> /> Classificação CDU</label><br>
                        <label><input type="radio" name="classification_system" value="cdd" <?php checked($settings['classification_system'], 'cdd'); ?> /> Classificação CDD</label>
                        <p class="description">Define qual sistema de classificação a IA usará ao gerar o Número de Chamada.</p>
                    </td>
                </tr>
            </table>
            

            <p><input type="submit" name="save_settings" class="button button-primary" value="Salvar Configurações" /></p>
        </form>
    </div>
    <?php
}


// ==========================================
// FASE 12B: WHITE LABEL
// ==========================================
function bm_get_white_label() {
    $defaults = array(
        'enabled' => '0',
        'school_name' => '',
        'school_logo' => '',
        'footer_text' => '',
        'school_url' => '',
    );
    $saved = get_option('bm_white_label', array());
    if (!is_array($saved)) $saved = array();
    foreach ($defaults as $key => $default) {
        if (!isset($saved[$key])) $saved[$key] = $default;
    }
    return $saved;
}

function bm_admin_media_scripts($hook) {
    if (strpos($hook, 'bm_white_label') === false) return;
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'bm_admin_media_scripts');


function bm_render_white_label_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $wl = bm_get_white_label();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_white_label'])) {
        $wl['enabled'] = isset($_POST['wl_enabled']) ? '1' : '0';
        $wl['school_name'] = sanitize_text_field(wp_unslash($_POST['school_name']));
        $wl['school_logo'] = esc_url_raw($_POST['school_logo']);
        $wl['footer_text'] = sanitize_text_field(wp_unslash($_POST['footer_text']));
        $wl['school_url'] = esc_url_raw($_POST['school_url']);
        update_option('bm_white_label', $wl);
        $msg = '<div class="notice notice-success"><p>Salvo! Escola: ' . $wl['school_name'] . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Identidade Visual</h1>
        <?php echo $msg; ?>
        
        <form method="post" style="max-width:600px;">
            <p>
                <label><input type="checkbox" name="wl_enabled" <?php checked($wl['enabled'], '1'); ?> /> <strong>Ativar identidade visual personalizada</strong></label>
            </p>
            
            <h2>Personalização da Escola</h2>
            <table class="form-table">
                <tr>
                    <th><label>Nome da escola</label></th>
                    <td>
                        <input type="text" name="school_name" value="<?php echo esc_attr($wl['school_name']); ?>" style="width:100%;" placeholder="Ex: Escola Municipal Paulo Freire" />
                        <p class="description">Substitui "Catálogo de Livros" no título da vitrine.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>URL da escola</label></th>
                    <td>
                        <input type="url" name="school_url" value="<?php echo esc_attr($wl['school_url']); ?>" style="width:100%;" placeholder="https://..." />
                    </td>
                </tr>
                <tr>
                    <th><label>Logo da escola</label></th>
                    <td>
                        <input type="text" name="school_logo" id="bm_school_logo" value="<?php echo esc_attr($wl['school_logo']); ?>" style="width:80%;" placeholder="https://..." />
                        <button type="button" class="button" id="bm_upload_logo" style="margin-left:5px;">Upload</button>
                        <?php if ($wl['school_logo']): ?>
                            <br><img src="<?php echo esc_url($wl['school_logo']); ?>" style="max-width:200px;max-height:80px;margin-top:5px;" />
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label>Texto do rodapé</label></th>
                    <td>
                        <input type="text" name="footer_text" value="<?php echo esc_attr($wl['footer_text']); ?>" style="width:100%;" placeholder="Ex: Biblioteca Central — 2024" />
                        <p class="description">Exibido no rodapé da vitrine e páginas do livro.</p>
                    </td>
                </tr>
            </table>
            
            <p><input type="submit" name="save_white_label" class="button button-primary" value="Salvar" /></p>
        </form>
    </div>
    
    <script>
    document.getElementById('bm_upload_logo').addEventListener('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Selecionar logo', button: { text: 'Usar esta imagem' }, multiple: false });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('bm_school_logo').value = attachment.url;
        });
        frame.open();
    });
    </script>
    <?php
}

// ==========================================
// FASE 12C: VIRADA DE ANO LETIVO
// ==========================================
function bm_get_year_transition_settings() {
    $defaults = array(
        'enabled' => '0',
        'transition_month' => '12',
        'transition_day' => '31',
        'reset_xp' => '0',
        'reset_badges' => '0',
        'clear_reservations' => '1',
        'activate_recadastro' => '1',
        'history_enabled' => '0',
        'clear_reading_log' => '0',
        'clear_reviews' => '0',
        'clear_videos' => '0',
        'clear_ratings' => '0',
        'clear_loan_history' => '0',
        'clear_before_year' => '',
    );
    $saved = get_option('bm_year_transition', array());
    if (!is_array($saved)) $saved = array();
    foreach ($defaults as $key => $default) {
        if (!isset($saved[$key])) $saved[$key] = $default;
    }
    return $saved;
}

// ==========================================
// FASE 12C: EXPORTAÇÃO CSV DE ALUNOS (admin_init)
// ==========================================
function bm_handle_students_csv_export() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_POST['export_students_csv'])) return;
    
    $current_year = date('Y');
    $students = get_users(array('role' => 'bm_student'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alunos_historico_' . $current_year . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Nome', 'E-mail', 'XP', 'Medalhas', 'Livros Lidos', 'Fichas', 'Resenhas', 'Vídeos', 'Empréstimos Ativos'), ';');
    foreach ($students as $student) {
        $xp = get_user_meta($student->ID, '_bm_xp', true) ?: '0';
        $badges = get_user_meta($student->ID, '_bm_badges', true) ?: array();
        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
        $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
        $active_loans = count(array_filter($loan_history, function($l) { return $l['status'] === 'active'; }));
        fputcsv($output, array(
            $student->display_name, $student->user_email, $xp, count($badges),
            count($loan_history), count($reading_log),
            count(array_filter($reading_log, function($l) { return !empty($l['review']); })),
            count(array_filter($reading_log, function($l) { return !empty($l['video_url']); })),
            $active_loans,
        ), ';');
    }
    fclose($output);
    exit;
}
add_action('admin_init', 'bm_handle_students_csv_export');


function bm_render_year_transition_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $settings = bm_get_year_transition_settings();
    $current_year = date('Y');
    
            // Salvar configurações da virada (checkboxes de ações + data)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $settings['enabled'] = isset($_POST['yt_enabled']) ? '1' : '0';
        $settings['transition_month'] = absint($_POST['transition_month']);
        $settings['transition_day'] = absint($_POST['transition_day']);
        $settings['reset_xp'] = isset($_POST['reset_xp']) ? '1' : '0';
        $settings['reset_badges'] = isset($_POST['reset_badges']) ? '1' : '0';
        $settings['clear_reservations'] = isset($_POST['clear_reservations']) ? '1' : '0';
        $settings['activate_recadastro'] = isset($_POST['activate_recadastro']) ? '1' : '0';
        update_option('bm_year_transition', $settings);
        $msg = '<div class="notice notice-success"><p>Configurações salvas! Virada ' . ($settings['enabled'] === '1' ? 'ATIVADA' : 'DESATIVADA') . '.</p></div>';
    }
    
    // Salvar configurações de histórico
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_history'])) {
        $settings['history_enabled'] = isset($_POST['history_enabled']) ? '1' : '0';
        $settings['clear_reading_log'] = isset($_POST['clear_reading_log']) ? '1' : '0';
        $settings['clear_reviews'] = isset($_POST['clear_reviews']) ? '1' : '0';
        $settings['clear_videos'] = isset($_POST['clear_videos']) ? '1' : '0';
        $settings['clear_ratings'] = isset($_POST['clear_ratings']) ? '1' : '0';
        $settings['clear_loan_history'] = isset($_POST['clear_loan_history']) ? '1' : '0';
        $settings['clear_before_year'] = sanitize_text_field($_POST['clear_before_year']);
        update_option('bm_year_transition', $settings);
        $msg = '<div class="notice notice-success"><p>Configurações de histórico salvas!</p></div>';
    }
    
    // Executar virada
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_transition'])) {
        $students = get_users(array('role' => 'bm_student'));
        
        // Backup dos rankings
        $rankings_backup = array();
        foreach ($students as $student) {
            $xp = get_user_meta($student->ID, '_bm_xp', true);
            $badges = get_user_meta($student->ID, '_bm_badges', true);
            $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
            $rankings_backup[] = array(
                'user_id' => $student->ID, 'name' => $student->display_name, 'email' => $student->user_email,
                'xp' => $xp, 'badges' => $badges, 'total_loans' => count($loan_history), 'year' => $current_year,
            );
        }
        update_option('bm_ranking_archive_' . $current_year, $rankings_backup);
        
        // Resetar XP
        if ($settings['reset_xp'] === '1') {
            foreach ($students as $student) {
                delete_user_meta($student->ID, '_bm_xp');
                delete_user_meta($student->ID, '_bm_xp_history');
            }
        }
        
        // Resetar medalhas
        if ($settings['reset_badges'] === '1') {
            foreach ($students as $student) {
                delete_user_meta($student->ID, '_bm_badges');
            }
        }
        
        // Limpar reservas
        if ($settings['clear_reservations'] === '1') {
            $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
            foreach ($all_books as $book) {
                $reservations = get_post_meta($book->ID, '_bm_reservations', true);
                if (!is_array($reservations)) continue;
                $cleaned = array();
                foreach ($reservations as $r) { if ($r['status'] === 'active') $cleaned[] = $r; }
                update_post_meta($book->ID, '_bm_reservations', $cleaned);
                update_post_meta($book->ID, '_bm_borrowed_count', count(array_filter($cleaned, function($r) { return $r['status'] === 'active'; })));
            }
            foreach ($students as $student) {
                delete_user_meta($student->ID, '_bm_active_reservations');
                delete_user_meta($student->ID, '_bm_reservation_count');
            }
        }
        
        // Limpeza de histórico
        if ($settings['history_enabled'] === '1') {
            $before_year = !empty($settings['clear_before_year']) ? intval($settings['clear_before_year']) : $current_year;
            
            foreach ($students as $student) {
                // Fichas de leitura
                if ($settings['clear_reading_log'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    $cleaned = array();
                    foreach ($reading_log as $log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year >= $before_year) $cleaned[] = $log;
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $cleaned);
                }
                
                // Resenhas (texto)
                if ($settings['clear_reviews'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    foreach ($reading_log as &$log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year < $before_year) $log['review'] = '';
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $reading_log);
                }
                
                // Vídeos
                if ($settings['clear_videos'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    foreach ($reading_log as &$log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year < $before_year) $log['video_url'] = '';
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $reading_log);
                }
                
                // Avaliações (estrelas)
                if ($settings['clear_ratings'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    foreach ($reading_log as &$log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year < $before_year) $log['rating'] = 0;
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $reading_log);
                }
                
                // Histórico de empréstimos
                if ($settings['clear_loan_history'] === '1') {
                    $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
                    $cleaned = array();
                    foreach ($loan_history as $loan) {
                        $loan_year = date('Y', strtotime($loan['loan_date']));
                        if ($loan_year >= $before_year) $cleaned[] = $loan;
                    }
                    update_user_meta($student->ID, '_bm_loan_history', $cleaned);
                }
            }
        }
        
        // Recadastramento
        if ($settings['activate_recadastro'] === '1') {
            update_option('bm_recadastro_required', '1');
            update_option('bm_recadastro_year', $current_year + 1);
        } else {
            update_option('bm_recadastro_required', '0');
        }
        
        // Log
        $log = get_option('bm_year_transition_log', array());
        $log[] = array('date' => current_time('mysql'), 'user' => get_current_user_id(), 'settings' => $settings);
        update_option('bm_year_transition_log', $log);
        
        update_option('bm_last_year_transition', $current_year);
        
        $msg = '<div class="notice notice-success"><p><strong>✅ Virada de Ano Letivo concluída!</strong> Backup salvo como bm_ranking_archive_' . $current_year . '.</p></div>';
    }
    
    // Exportar CSV de alunos — movido para bm_handle_students_csv_export() via admin_init
    
    $last_transition = get_option('bm_last_year_transition', 'Nunca');
    $recadastro_active = get_option('bm_recadastro_required', '0');
    $transition_date = $settings['transition_day'] . '/' . $settings['transition_month'];
    ?>
    <div class="wrap">
        <h1>🔄 Virada de Ano Letivo</h1>
        <?php echo $msg; ?>
        
        <form method="post" style="max-width:700px;">
            <h2>Configurações</h2>
            <table class="form-table">
                <tr><th><label>Ativar sistema de virada de ano letivo</label></th><td><label><input type="checkbox" name="yt_enabled" <?php checked($settings['enabled'], '1'); ?> /> Habilitar</label><p class="description">Se desativado, todo histórico continua indefinidamente.</p></td></tr>
                <tr><th><label>Data da virada (mês/dia)</label></th><td>
                    <select name="transition_month" style="width:100px;"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>" <?php selected($settings['transition_month'], $m); ?>><?php echo date_i18n('F', mktime(0, 0, 0, $m, 1)); ?></option><?php endfor; ?></select>
                    <select name="transition_day" style="width:80px;margin-left:5px;"><?php for ($d = 1; $d <= 31; $d++): ?><option value="<?php echo $d; ?>" <?php selected($settings['transition_day'], $d); ?>><?php echo $d; ?></option><?php endfor; ?></select>
                    <p class="description">Define quando a virada acontece. Ex: 31/Dezembro (Brasil) ou 30/Junho (Austrália).</p></td></tr>
            </table>
            
            <h2>Ações da Virada</h2>
            <table class="form-table">
                <tr><th><label>Resetar pontuações (XP)</label></th><td><label><input type="checkbox" name="reset_xp" <?php checked($settings['reset_xp'], '1'); ?> /> Zerar pontuações de todos os alunos</label></td></tr>
                <tr><th><label>Resetar medalhas</label></th><td><label><input type="checkbox" name="reset_badges" <?php checked($settings['reset_badges'], '1'); ?> /> Zerar medalhas de todos os alunos</label></td></tr>
                <tr><th><label>Limpar reservas pendentes</label></th><td><label><input type="checkbox" name="clear_reservations" <?php checked($settings['clear_reservations'], '1'); ?> /> Remover todas as reservas não confirmadas</label><p class="description">Empréstimos ativos não serão afetados.</p></td></tr>
                <tr><th><label>Ativar recadastramento de alunos</label></th><td><label><input type="checkbox" name="activate_recadastro" <?php checked($settings['activate_recadastro'], '1'); ?> /> Exigir que alunos confirmem dados no próximo login</label><p class="description">Apenas alunos (bm_student) serão afetados.</p></td></tr>
            </table>
            
            <p><input type="submit" name="save_settings" class="button" value="Salvar Configurações" /></p>
        </form>
        
        <form method="post" style="max-width:700px;">
            <p><input type="submit" name="export_students_csv" class="button" value="📥 Exportar dados dos alunos (CSV)" /></p>
        </form>
        
        <form method="post" style="max-width:700px;">
        </form>
        
        <hr style="margin:30px 0;" />
        
        <form method="post" style="max-width:700px;">
            <h2>🗑️ Limpeza de Histórico</h2>
            <p class="description">⚠️ Esta seção controla a exclusão permanente de dados históricos dos alunos. Por padrão, o histórico NUNCA é apagado.</p>
            
            <table class="form-table">
                <tr><th><label>Habilitar limpeza de histórico</label></th><td><label><input type="checkbox" name="history_enabled" id="bm_history_toggle" <?php checked($settings['history_enabled'], '1'); ?> /> Permitir configurar limpeza de dados históricos</label></td></tr>
            </table>
            
            <div id="bm_history_options" style="<?php echo $settings['history_enabled'] === '1' ? '' : 'opacity:0.5;pointer-events:none;'; ?>">
                <table class="form-table">
                    <tr><th><label>Apagar fichas de leitura</label></th><td><label><input type="checkbox" name="clear_reading_log" <?php checked($settings['clear_reading_log'], '1'); ?> /> Remove fichas de leitura (_bm_reading_log)</label></td></tr>
                    <tr><th><label>Apagar resenhas (texto)</label></th><td><label><input type="checkbox" name="clear_reviews" <?php checked($settings['clear_reviews'], '1'); ?> /> Remove textos das resenhas</label></td></tr>
                    <tr><th><label>Apagar vídeo-resenhas (links)</label></th><td><label><input type="checkbox" name="clear_videos" <?php checked($settings['clear_videos'], '1'); ?> /> Remove links de vídeos</label></td></tr>
                    <tr><th><label>Apagar avaliações (estrelas)</label></th><td><label><input type="checkbox" name="clear_ratings" <?php checked($settings['clear_ratings'], '1'); ?> /> Remove notas com estrelas</label></td></tr>
                    <tr><th><label>Apagar histórico de empréstimos</label></th><td><label><input type="checkbox" name="clear_loan_history" <?php checked($settings['clear_loan_history'], '1'); ?> /> Remove histórico (_bm_loan_history)</label></td></tr>
                    <tr><th><label>Apagar apenas dados anteriores a (ano)</label></th><td><input type="number" name="clear_before_year" value="<?php echo esc_attr($settings['clear_before_year']); ?>" style="width:80px;" placeholder="<?php echo $current_year; ?>" /><p class="description">Deixe vazio para apagar tudo. Ex: "2024" apaga apenas dados de 2023 para trás.</p></td></tr>
                </table>
                <p><input type="submit" name="save_history" class="button" value="Salvar Configurações de Histórico" /></p>
            </div>
        </form>
        
        <hr style="margin:30px 0;" />
        
        <?php if ($settings['enabled'] === '1'): ?>
            <div style="background:#fff3f3;padding:15px;border-radius:8px;border:2px solid #dc3545;margin-bottom:20px;">
                <h2 style="color:#dc3545;margin-top:0;">⚠️ Executar Virada de Ano Letivo</h2>
                <p>Esta ação é <strong>irreversível</strong>. Um backup automático dos rankings será salvo antes.</p>
                <p><strong>Data configurada:</strong> <?php echo $transition_date; ?> | <strong>Última virada:</strong> <?php echo esc_html($last_transition); ?></p>
                <?php if ($recadastro_active === '1'): ?><p style="color:#dc3545;">Recadastramento ATIVO para <?php echo get_option('bm_recadastro_year', date('Y')); ?>.</p><?php endif; ?>
                
                <form method="post">
                    <p><strong>Digite "VIRADA <?php echo $current_year; ?>" para confirmar:</strong></p>
                    <input type="text" id="bm_confirm_text" style="width:300px;padding:8px;font-size:16px;" placeholder="VIRADA <?php echo $current_year; ?>" />
                    <br><br>
                    <input type="submit" name="execute_transition" id="bm_transition_btn" class="button button-primary" value="🔄 Executar Virada de Ano Letivo" disabled style="background:#dc3545;border-color:#dc3545;color:#fff;" />
                </form>
            </div>
            <script>document.getElementById('bm_confirm_text').addEventListener('input', function() { document.getElementById('bm_transition_btn').disabled = this.value !== 'VIRADA <?php echo $current_year; ?>'; });</script>
        <?php else: ?>
            <p style="color:#666;">O sistema de virada de ano letivo está <strong>desativado</strong>. Ative-o nas configurações acima.</p>
        <?php endif; ?>
    </div>
    
    <script>
    document.getElementById('bm_history_toggle').addEventListener('change', function() {
        if (this.checked) {
            var confirmed = confirm('⚠️ Atenção: Você está prest a acessar opções que podem apagar permanentemente o histórico pedagógico dos alunos. Recomendamos fortemente exportar esses dados via CSV antes de prosseguir. Deseja continuar?');
            if (!confirmed) { this.checked = false; return; }
        }
        var options = document.getElementById('bm_history_options');
        options.style.opacity = this.checked ? '1' : '0.5';
        options.style.pointerEvents = this.checked ? 'auto' : 'none';
    });
    </script>
    <?php
}