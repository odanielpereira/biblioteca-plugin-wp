<?php
defined('ABSPATH') || exit;

// ==========================================
// METABOXES E CAMPOS DINÂMICOS
// ==========================================

function bm_render_book_details_metabox( $post ) {
    wp_nonce_field( 'bm_save_book_details', 'bm_book_details_nonce' );
    $fixed_fields = array(
        '_bm_author'    => array('label' => __('Autor:','book-manager'), 'type' => 'text', 'required' => true),
        '_bm_publisher' => array('label' => __('Editora:','book-manager'), 'type' => 'text', 'required' => true),
        '_bm_isbn'      => array('label' => __('ISBN:','book-manager'), 'type' => 'text'),
        '_bm_location'  => array('label' => __('Localização:','book-manager'), 'type' => 'text'),
        '_bm_copies'    => array('label' => __('Exemplares:','book-manager'), 'type' => 'number'),
        '_bm_library_unit' => array('label' => __('Unidade:','book-manager'), 'type' => 'text'),
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

    $isbn_value = get_post_meta($post->ID, '_bm_isbn', true);
    $title_value = $post->post_title;
    $author_value = get_post_meta($post->ID, '_bm_author', true);
    ?>
    <p>
        <button type="button" id="bm-fill-by-isbn" class="button" style="margin-top:5px;" <?php echo empty($isbn_value) ? 'disabled' : ''; ?>>
            📚 <?php _e('Preencher via ISBN', 'book-manager'); ?>
        </button>
        <button type="button" id="bm-search-isbn" class="button" style="margin-top:5px;margin-left:5px;" <?php echo (empty($isbn_value) && !empty($title_value)) ? '' : 'disabled'; ?>>
            🔍 <?php _e('Buscar ISBN', 'book-manager'); ?>
        </button>
        <span id="bm-fill-loading" style="display:none;margin-left:10px;color:#666;"></span>
        <span id="bm-fill-result" style="display:none;margin-left:10px;"></span>
    </p>
    <script>
    jQuery(document).ready(function($) {
        $('#bm-fill-by-isbn').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true);
            $('#bm-fill-loading').show().text('<?php _e('Buscando na Google Books...', 'book-manager'); ?>');
            $('#bm-fill-result').hide();
            $.post(ajaxurl, {
                action: 'bm_fill_by_isbn',
                nonce: '<?php echo wp_create_nonce("bm_fill_by_isbn_nonce"); ?>',
                post_id: <?php echo $post->ID; ?>,
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
            $('#bm-fill-loading').show().text('<?php _e('Buscando ISBN...', 'book-manager'); ?>');
            $('#bm-fill-result').hide();
            $.post(ajaxurl, {
                action: 'bm_search_isbn',
                nonce: '<?php echo wp_create_nonce("bm_search_isbn_nonce"); ?>',
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
        echo '<hr><h4>'.__('Histórico de Ações','book-manager').'</h4><ul style="font-size:12px;color:#666;">';
        foreach (array_reverse($audit_log) as $entry) echo '<li>'.esc_html($entry['time']).' — '.esc_html($entry['user']).': '.esc_html($entry['action']).'</li>';
        echo '</ul>';
    }
}
function bm_add_book_details_metabox() { add_meta_box('bm_book_details', __('Detalhes do Livro','book-manager'), 'bm_render_book_details_metabox', 'bm_book', 'normal', 'high'); }

function bm_add_dynamic_taxonomy_metaboxes() {
    bm_install_default_taxonomies();
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) return;
    $skip = array('bm_discipline');
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
    remove_meta_box('bm_genrediv', 'bm_book', 'side');
    remove_meta_box('bm_categorydiv', 'bm_book', 'side');
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
    
    $skip = array('bm_discipline');
    
    foreach ($taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
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

// Metabox de Resenha Oficial
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

// Gerenciamento de Campos Dinâmicos
function bm_render_dynamic_fields_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $message = '';
    
    $active_tab = isset($_GET['tab']) && $_GET['tab'] === 'users' ? 'users' : 'books';

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
            $message = '<div class="notice notice-warning"><p>' . __('Campos inválidos ou reservados foram removidos dos campos dinâmicos.', 'book-manager') . '</p></div>';
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
            
            if ($active_tab === 'books') {
                $reserved_names = array('cdu', 'cdd', 'classificação', 'classificacao', 'cutter');
                if (in_array($name_lower, $reserved_names)) {
                    $message = __('Este nome é reservado para o Número de Chamada. Use outro nome.','book-manager');
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
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('bm_dynamic_action','bm_dynamic_nonce'); ?>
                                            <input type="hidden" name="remove_field_name" value="<?php echo esc_attr($key); ?>" />
                                            <button type="submit" name="remove_field" class="button button-small" onclick="return confirm('<?php _e('Remover este campo?','book-manager'); ?>');"><?php _e('Remover','book-manager'); ?></button>
                                        </form>
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

function bm_admin_scripts($hook) {
    if (strpos($hook, 'bm_dynamic_fields') === false && strpos($hook, 'bm_book') === false) return;
    wp_enqueue_script('jquery-ui-sortable');
}
add_action('admin_enqueue_scripts', 'bm_admin_scripts');

// Registrar submenu Gerenciar Campos
function bm_register_dynamic_fields_submenu() {
    add_submenu_page('edit.php?post_type=bm_book', __('Gerenciar Campos','book-manager'), __('Gerenciar Campos','book-manager'), 'edit_bm_books', 'bm_dynamic_fields', 'bm_render_dynamic_fields_page');
}
add_action('admin_menu', 'bm_register_dynamic_fields_submenu');