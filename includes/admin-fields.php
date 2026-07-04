<?php
/**
 * Book Manager — Fields & Metaboxes Module
 * Admin metaboxes, listings, filters, dynamic fields, official review
 */

defined('ABSPATH') || exit;

// ==========================================
// ADMIN SCRIPTS (DRAG AND DROP)
// ==========================================
function bm_admin_scripts($hook) {
    if (strpos($hook, 'bm_dynamic_fields') === false && strpos($hook, 'bm_book') === false) return;
    wp_enqueue_script('jquery-ui-sortable');
}
add_action('admin_enqueue_scripts', 'bm_admin_scripts');

// ==========================================
// BOOK DETAILS METABOX
// ==========================================
function bm_render_book_details_metabox( $post ) {
    wp_nonce_field( 'bm_save_book_details', 'bm_book_details_nonce' );
    $fixed_fields = array(
        '_bm_author'    => array('label' => esc_html__('Autor:','book-manager'), 'type' => 'text', 'required' => true),
        '_bm_publisher' => array('label' => esc_html__('Editora:','book-manager'), 'type' => 'text', 'required' => true),
        '_bm_isbn'      => array('label' => 'ISBN:', 'type' => 'text'),
        '_bm_location'  => array('label' => esc_html__('Localização:','book-manager'), 'type' => 'text'),
        '_bm_copies'    => array('label' => esc_html__('Exemplares:','book-manager'), 'type' => 'number'),
        '_bm_library_unit' => array('label' => esc_html__('Unidade:','book-manager'), 'type' => 'text'),
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
    
    // Remove reserved field names (CDU, CDD, Cutter, etc.)
    $reserved_names = array('cdu', 'cdd', 'classificação', 'classificacao', 'cutter');
    foreach ($all_fields as $fkey => $field) {
        if (in_array(mb_strtolower(trim($fkey)), $reserved_names)) {
            unset($all_fields[$fkey]);
        }
    }
    
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

    // Fill by ISBN buttons
    $isbn_value = get_post_meta($post->ID, '_bm_isbn', true);
    $title_value = $post->post_title;
    $author_value = get_post_meta($post->ID, '_bm_author', true);
    ?>
    <?php 
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    $genre_label = isset($taxonomies['bm_genre']['label']) ? $taxonomies['bm_genre']['label'] : esc_html__('Gênero', 'book-manager');
    $category_label = isset($taxonomies['bm_category']['label']) ? $taxonomies['bm_category']['label'] : esc_html__('Categoria', 'book-manager');
    $reading_level_label = isset($taxonomies['bm_reading_level']['label']) ? $taxonomies['bm_reading_level']['label'] : esc_html__('Nível de Leitura', 'book-manager');
    ?>
    <p>
        <button type="button" id="bm-classify-genre" class="button" style="margin-top:5px;">
            <?php printf(esc_html__('Classificar %s', 'book-manager'), esc_html($genre_label)); ?>
        </button>
        <button type="button" id="bm-classify-category" class="button" style="margin-top:5px;margin-left:5px;">
            <?php printf(esc_html__('Classificar %s', 'book-manager'), esc_html($category_label)); ?>
        </button>
        <button type="button" id="bm-classify-reading-level" class="button" style="margin-top:5px;margin-left:5px;">
            <?php printf(esc_html__('Classificar %s', 'book-manager'), esc_html($reading_level_label)); ?>
        </button>
        <span id="bm-classify-loading" style="display:none;margin-left:10px;color:#666;"></span>
        <span id="bm-classify-result" style="display:none;margin-left:10px;"></span>

        <script>
        jQuery(document).ready(function($) {
            function bmClassify(action, nonce, label) {
                var btn = $('#bm-classify-' + action);
                btn.prop('disabled', true);
                $('#bm-classify-loading').show().text('Analisando ' + label + '...');
                $('#bm-classify-result').hide();
                $.post(ajaxurl, {
                    action: 'bm_ajax_classify_' + action,
                    nonce: nonce,
                    post_id: <?php echo esc_attr($post->ID); ?>
                }, function(r) {
                    $('#bm-classify-loading').hide();
                    if (r.success) {
                        $('#bm-classify-result').css('color', 'green').text(r.data).show();
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $('#bm-classify-result').css('color', 'red').text(r.data).show();
                        btn.prop('disabled', false);
                    }
                });
            }
            
            $('#bm-classify-genre').on('click', function() { bmClassify('genre', '<?php echo esc_attr(wp_create_nonce("bm_ai_classify_nonce")); ?>', '<?php echo esc_js($genre_label); ?>'); });
            $('#bm-classify-category').on('click', function() { bmClassify('category', '<?php echo esc_attr(wp_create_nonce("bm_ai_classify_nonce")); ?>', '<?php echo esc_js($category_label); ?>'); });
            $('#bm-classify-reading-level').on('click', function() { bmClassify('reading_level', '<?php echo esc_attr(wp_create_nonce("bm_ai_classify_nonce")); ?>', '<?php echo esc_js($reading_level_label); ?>'); });
        });
        </script>

        <button type="button" id="bm-fill-by-isbn" class="button" style="margin-top:5px;" <?php echo empty($isbn_value) ? 'disabled' : ''; ?>>
            📚 <?php esc_html_e('Preencher via ISBN', 'book-manager'); ?>
        </button>
        <button type="button" id="bm-search-isbn" class="button" style="margin-top:5px;margin-left:5px;" <?php echo (empty($isbn_value) && !empty($title_value)) ? '' : 'disabled'; ?>>
            🔍 <?php esc_html_e('Buscar ISBN', 'book-manager'); ?>
        </button>
        <span id="bm-fill-loading" style="display:none;margin-left:10px;color:#666;"></span>
        <span id="bm-fill-result" style="display:none;margin-left:10px;"></span>
    </p>
    <script>
    jQuery(document).ready(function($) {
        $('#bm-fill-by-isbn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true);
            $('#bm-fill-loading').show().text('<?php echo esc_js(__('Buscando na Google Books...', 'book-manager')); ?>');
            $('#bm-fill-result').hide();
            $.post(ajaxurl, {
                action: 'bm_fill_by_isbn',
                nonce: '<?php echo esc_attr(wp_create_nonce("bm_fill_by_isbn_nonce")); ?>',
                post_id: <?php echo esc_attr($post->ID); ?>,
                isbn: $('#_bm_isbn').val()
            }, function(r) {
                $('#bm-fill-loading').hide();
                if (r.success) {
                    $('#bm-fill-result').css('color', 'green').text(r.data.message).show();
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $('#bm-fill-result').css('color', 'red').text(r.data).show();
                    btn.prop('disabled', false);
                }
            });
        });
        
        $('#bm-search-isbn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true);
            $('#bm-fill-loading').show().text('<?php echo esc_js(__('Buscando ISBN...', 'book-manager')); ?>');
            $('#bm-fill-result').hide();
            $.post(ajaxurl, {
                action: 'bm_search_isbn',
                nonce: '<?php echo esc_attr(wp_create_nonce("bm_search_isbn_nonce")); ?>',
                title: $('#title').val(),
                author: $('#_bm_author').val()
            }, function(r) {
                $('#bm-fill-loading').hide();
                if (r.success) {
                    $('#_bm_isbn').val(r.data.isbn);
                    $('#bm-fill-by-isbn').prop('disabled', false);
                    $('#bm-fill-result').css('color', 'green').text('ISBN encontrado: ' + r.data.isbn).show();
                } else {
                    $('#bm-fill-result').css('color', 'red').text(r.data).show();
                    btn.prop('disabled', false);
                }
            }, 'json');
        });
    });
    </script>
    <?php

    $audit_log = get_post_meta($post->ID, '_bm_audit_log', true);
    if (!empty($audit_log)) {
        echo '<hr><h4>'.esc_html__('Histórico de Ações','book-manager').'</h4><ul style="font-size:12px;color:#666;">';
        foreach (array_reverse($audit_log) as $entry) echo '<li>'.esc_html($entry['time']).' — '.esc_html($entry['user']).': '.esc_html($entry['action']).'</li>';
        echo '</ul>';
    }
}
function bm_add_book_details_metabox() { add_meta_box('bm_book_details', esc_html__('Detalhes do Livro','book-manager'), 'bm_render_book_details_metabox', 'bm_book', 'normal', 'high'); }
add_action('add_meta_boxes', 'bm_add_book_details_metabox');

function bm_save_book_details_metabox_data( $post_id ) {
    if (!isset($_POST['bm_book_details_nonce']) || !wp_verify_nonce($_POST['bm_book_details_nonce'],'bm_save_book_details')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    foreach (array('_bm_author','_bm_publisher','_bm_isbn','_bm_location','_bm_library_unit') as $f) {
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
// DYNAMIC TAXONOMY METABOXES
// ==========================================
function bm_add_dynamic_taxonomy_metaboxes() {
    bm_install_default_taxonomies();
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) return;
    $skip = array('bm_discipline', 'bm_reading_level');
    foreach ($taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
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

function bm_remove_native_taxonomy_metaboxes() {
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) return;
    $skip = array('bm_discipline', 'bm_reading_level');
    foreach ($taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
        remove_meta_box($slug . 'div', 'bm_book', 'side');
    }
}
add_action('add_meta_boxes', 'bm_remove_native_taxonomy_metaboxes', 20);

function bm_render_dynamic_taxonomy_metabox($post, $box) {
    $slug = $box['args']['slug'];
    $terms = wp_get_post_terms($post->ID, $slug, array('fields' => 'ids'));
    $all_terms = get_terms(array('taxonomy' => $slug, 'hide_empty' => false));
    ?>
    <div style="max-height:150px;overflow-y:auto;">
        <?php foreach ($all_terms as $term): ?>
            <label style="display:block;margin:3px 0;">
                <input type="checkbox" name="bm_tax_<?php echo esc_attr($slug); ?>[]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array($term->term_id, $terms)); ?> />
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
    
    $skip = array('bm_discipline', 'bm_genre', 'bm_reading_level');
    
    foreach ($taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
        $field = 'bm_tax_' . $slug;
        $terms = isset($_POST[$field]) ? array_map('intval', $_POST[$field]) : array();
        wp_set_post_terms($post_id, $terms, $slug);
    }
}
add_action('save_post_bm_book', 'bm_save_dynamic_taxonomy_terms');

// ==========================================
// OFFICIAL REVIEW METABOX
// ==========================================
function bm_add_official_review_metabox() {
    add_meta_box('bm_official_review', esc_html__('Resenha Oficial', 'book-manager'), 'bm_render_official_review_metabox', 'bm_book', 'normal', 'high');
}
add_action('add_meta_boxes', 'bm_add_official_review_metabox');

function bm_render_official_review_metabox($post) {
    wp_nonce_field('bm_official_review_nonce', 'bm_official_review_nonce_field');
    $review = get_post_meta($post->ID, '_bm_official_review', true);
    $link = get_post_meta($post->ID, '_bm_official_link', true);
    ?>
    <p>
        <label><strong><?php esc_html_e('Resenha oficial da biblioteca:', 'book-manager'); ?></strong></label>
        <textarea name="bm_official_review" rows="5" style="width:100%;max-width:600px;margin-top:5px;"><?php echo esc_textarea($review); ?></textarea>
    </p>
    <p>
        <label><strong><?php esc_html_e('Link oficial (vídeo ou site):', 'book-manager'); ?></strong></label>
        <input type="url" name="bm_official_link" value="<?php echo esc_attr($link); ?>" style="width:100%;max-width:600px;margin-top:5px;" placeholder="https://..." />
    </p>
    <p class="description"><?php esc_html_e('Esta resenha e link aparecerão com destaque na página pública do livro.', 'book-manager'); ?></p>
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
// ADMIN LISTING & FILTERS
// ==========================================
function bm_manage_book_columns($columns) {
    $new = array();
    foreach ($columns as $k => $t) { $new[$k] = $t; if ($k==='title') { $new['_bm_author']=esc_html__('Autor','book-manager'); $new['_bm_publisher']=esc_html__('Editora','book-manager'); $new['taxonomy-bm_genre']=esc_html__('Gênero','book-manager'); $new['taxonomy-bm_category']=esc_html__('Categoria','book-manager'); } }
    if (!isset($new['taxonomy-bm_genre'])) { $new['taxonomy-bm_genre']=esc_html__('Gênero','book-manager'); $new['taxonomy-bm_category']=esc_html__('Categoria','book-manager'); }
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
    ?>
    <div class="bm-filter-form">
    <?php
    echo '<input type="hidden" name="post_type" value="bm_book">';
    if (isset($_GET['orderby']) && !empty($_GET['orderby'])) echo '<input type="hidden" name="orderby" value="'.esc_attr(sanitize_text_field($_GET['orderby'])).'">';
    if (isset($_GET['order']) && !empty($_GET['order'])) echo '<input type="hidden" name="order" value="'.esc_attr(sanitize_text_field($_GET['order'])).'">';
    if (isset($_GET['s'])&&!empty($_GET['s'])) echo '<input type="hidden" name="s" value="'.esc_attr(sanitize_text_field($_GET['s'])).'">';
    ?>
    <p><label for="_bm_author"><?php esc_html_e('Autor:','book-manager'); ?></label><input type="text" id="_bm_author" name="_bm_author" value="<?php echo esc_attr($fa); ?>" placeholder="<?php esc_attr_e('Filtrar por autor','book-manager'); ?>"></p>
    <p><label for="_bm_publisher"><?php esc_html_e('Editora:','book-manager'); ?></label><input type="text" id="_bm_publisher" name="_bm_publisher" value="<?php echo esc_attr($fp); ?>" placeholder="<?php esc_attr_e('Filtrar por editora','book-manager'); ?>"></p>
    <?php
  
    wp_dropdown_categories(array('show_option_all'=>esc_html__('Todos os Gêneros','book-manager'),'taxonomy'=>'bm_genre','name'=>'bm_genre_filter','selected'=>isset($_GET['bm_genre_filter'])?$_GET['bm_genre_filter']:''));
    wp_dropdown_categories(array('show_option_all'=>esc_html__('Todas as Categorias','book-manager'),'taxonomy'=>'bm_category','name'=>'bm_category_filter','selected'=>isset($_GET['bm_category_filter'])?$_GET['bm_category_filter']:''));

    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($dynamic_taxonomies)) $dynamic_taxonomies = array();
    $skip = array('bm_genre', 'bm_category', 'bm_discipline');
    foreach ($dynamic_taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
        wp_dropdown_categories(array(
            'show_option_all' => $info['label'],
            'taxonomy' => $slug,
            'name' => $slug . '_filter',
            'selected' => isset($_GET[$slug . '_filter']) ? $_GET[$slug . '_filter'] : '',
        ));
    }
    ?>
    <input type="submit" name="filter_action" class="button" value="<?php esc_attr_e('Filtrar','book-manager'); ?>">
    <a href="<?php echo esc_url(admin_url('edit.php?post_type=bm_book')); ?>" class="button"><?php esc_html_e('Limpar Filtros','book-manager'); ?></a>
    </div><?php
}
add_action('restrict_manage_posts','bm_add_book_filter_form');

function bm_filter_books_by_metadata($query) {
    if (!is_admin()||!$query->is_main_query()||'bm_book'!==$query->get('post_type')) return;
    
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
// DYNAMIC FIELDS MANAGEMENT
// ==========================================
function bm_add_dynamic_fields_page() {
    add_submenu_page('edit.php?post_type=bm_book', esc_html__('Gerenciar Campos', 'book-manager'), esc_html__('Gerenciar Campos', 'book-manager'), 'edit_bm_books', 'bm_dynamic_fields', 'bm_render_dynamic_fields_page');
}
add_action('admin_menu', 'bm_add_dynamic_fields_page');

function bm_render_dynamic_fields_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $message = '';
    
    $active_tab = isset($_GET['tab']) && $_GET['tab'] === 'users' ? 'users' : 'books';

    // Clean reserved field names
    if ($active_tab === 'books') {
        $reserved_names = array('cdu', 'cdd', 'classificação', 'classificacao', 'cutter');
        $fields_to_check = get_option('bm_dynamic_fields', array());
        $cleaned = false;
        foreach ($fields_to_check as $field_name => $info) {
            if (in_array(mb_strtolower(trim($field_name)), $reserved_names) || trim($field_name) === '') {
                unset($fields_to_check[$field_name]);
                $cleaned = true;
            }
        }
        if ($cleaned) {
            update_option('bm_dynamic_fields', $fields_to_check);
            $message = '<div class="notice notice-warning"><p>' . esc_html__('Campos inválidos ou reservados foram removidos dos campos dinâmicos.', 'book-manager') . '</p></div>';
        }
    }    
    $dynamic_fields = $active_tab === 'users' ? get_option('bm_user_dynamic_fields', array()) : get_option('bm_dynamic_fields', array());
    if (!empty($dynamic_fields) && isset(array_values($dynamic_fields)[0]) && is_string(array_values($dynamic_fields)[0])) {
        $new_fields = array();
        foreach ($dynamic_fields as $name) $new_fields[$name] = array('type' => 'text');
        update_option('bm_dynamic_fields', $new_fields);
        $dynamic_fields = $new_fields;
    }
    $system_fields = $active_tab === 'users' 
        ? array() 
        : array('_bm_author' => esc_html__('Autor', 'book-manager'), '_bm_publisher' => esc_html__('Editora', 'book-manager'), '_bm_isbn' => 'ISBN', '_bm_location' => esc_html__('Localização', 'book-manager'), '_bm_copies' => esc_html__('Exemplares', 'book-manager'));
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
            
            if ($active_tab === 'books') {
                $reserved_names = array('cdu', 'cdd', 'classificação', 'classificacao', 'cutter');
                if (in_array($name_lower, $reserved_names)) {
                    $message = esc_html__('Este nome é reservado para o Número de Chamada. Use outro nome.','book-manager');
                    $name = '';
                }
            }
            
            $duplicate = false;
            foreach ($fields as $existing_name => $info) {
                if (mb_strtolower(trim($existing_name)) === $name_lower) {
                    $duplicate = true;
                    break;
                }
            }
            
            if ($duplicate) {
                $message = esc_html__('Já existe um campo com este nome.','book-manager');
            } elseif (!isset($fields[$name])) {
                $profile = isset($_POST['new_field_profile']) ? sanitize_text_field($_POST['new_field_profile']) : 'both';
                $fields[$name] = array('type' => $type, 'profile' => $profile);
                update_option($option_name, $fields);
                $message = esc_html__('Campo adicionado.','book-manager');
            }
        }
        if (isset($_POST['remove_field']) && !empty($_POST['remove_field_name'])) {
            $fields = get_option($option_name, array());
            unset($fields[sanitize_text_field($_POST['remove_field_name'])]);
            update_option($option_name, $fields);
            $message = esc_html__('Campo removido.','book-manager');
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
            
            $final_order = array();
            foreach ($order as $key) {
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
            $message = esc_html__('Alterações salvas.','book-manager');
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
        <h1><?php esc_html_e('Gerenciar Campos','book-manager'); ?></h1>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=bm_book&page=bm_dynamic_fields&tab=books')); ?>" class="nav-tab <?php echo $active_tab === 'books' ? 'nav-tab-active' : ''; ?>">📚 <?php esc_html_e('Campos de Livros','book-manager'); ?></a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=bm_book&page=bm_dynamic_fields&tab=users')); ?>" class="nav-tab <?php echo $active_tab === 'users' ? 'nav-tab-active' : ''; ?>">👤 <?php esc_html_e('Campos de Alunos','book-manager'); ?></a>
        </nav>
        
        <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        <h2><?php esc_html_e('Adicionar novo campo dinâmico','book-manager'); ?></h2>
        <form method="post"><?php wp_nonce_field('bm_dynamic_action','bm_dynamic_nonce'); ?>
            <input type="text" name="new_field_name" placeholder="<?php esc_attr_e('Nome do campo','book-manager'); ?>" />
            <select name="new_field_type" style="margin-left:5px;">
                <option value="text"><?php esc_html_e('Texto curto','book-manager'); ?></option>
                <option value="email"><?php esc_html_e('E-mail','book-manager'); ?></option>
                <option value="textarea"><?php esc_html_e('Texto longo','book-manager'); ?></option>
            </select>
            <?php if ($active_tab === 'users'): ?>
                <select name="new_field_profile" style="margin-left:5px;">
                    <option value="both"><?php esc_html_e('Aluno e Professor','book-manager'); ?></option>
                    <option value="student"><?php esc_html_e('Apenas Aluno','book-manager'); ?></option>
                    <option value="teacher"><?php esc_html_e('Apenas Professor','book-manager'); ?></option>
                </select>
            <?php endif; ?>
            <input type="submit" name="add_field" class="button" value="<?php esc_attr_e('Adicionar','book-manager'); ?>" />
        </form>
        <h2><?php esc_html_e('Gerenciar Campos Existentes','book-manager'); ?></h2>
        <p><?php esc_html_e('Arraste para reordenar. Campos do sistema não podem ser renomeados. Desmarque "Mostrar" para ocultar.','book-manager'); ?></p>
        <form method="post" id="bm-fields-form">
            <?php wp_nonce_field('bm_dynamic_action','bm_dynamic_nonce'); ?>
            <table class="wp-list-table widefat fixed striped" id="bm-fields-table">
                <thead><tr><th style="width:30px"></th><th><?php esc_html_e('Nome do Campo','book-manager'); ?></th><th><?php esc_html_e('Tipo','book-manager'); ?></th><th><?php esc_html_e('Visível','book-manager'); ?></th><th><?php esc_html_e('Remover','book-manager'); ?></th></tr></thead>
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
                                if ($ft === 'textarea') esc_html_e('Texto longo','book-manager');
                                elseif ($ft === 'email') esc_html_e('E-mail','book-manager');
                                else esc_html_e('Texto curto','book-manager');
                                ?>
                            </td>
                            <td><label><input type="checkbox" name="field_visible[]" value="<?php echo esc_attr($key); ?>" <?php checked($is_visible); ?> /> <?php esc_html_e('Mostrar','book-manager'); ?></label></td>
                            <td>
                                <?php if ($info['type'] === 'dynamic'): ?>
                                    <?php 
                                    $is_locked = isset($dynamic_fields[$key]['locked']) && $dynamic_fields[$key]['locked'];
                                    if ($is_locked): ?>
                                        <span style="color:#999;">🔒 <?php esc_html_e('Protegido','book-manager'); ?></span>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('bm_dynamic_action','bm_dynamic_nonce'); ?>
                                            <input type="hidden" name="remove_field_name" value="<?php echo esc_attr($key); ?>" />
                                            <button type="submit" name="remove_field" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Remover este campo?','book-manager')); ?>');"><?php esc_html_e('Remover','book-manager'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?><span style="color:#999;">—</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <input type="hidden" name="save_order" value="1" />
            <?php submit_button(esc_html__('Salvar Todas as Alterações','book-manager')); ?>
        </form>
    </div>
    <script>jQuery(document).ready(function($){$('#bm-fields-table tbody').sortable({handle:'.dashicons-menu'});});</script>
    <?php
}