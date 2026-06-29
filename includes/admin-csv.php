<?php
/**
 * Book Manager — Módulo de Importação/Exportação
 * CSV, ZIP, Nº Chamada, central de dados
 */

defined('ABSPATH') || exit;

// ==========================================
// FASE 18: PÁGINA UNIFICADA — IMPORTAÇÃO/EXPORTAÇÃO
// ==========================================
function bm_add_data_io_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Importação/Exportação', 'book-manager'), __('Importação/Exportação', 'book-manager'), 'edit_bm_books', 'bm_data_io', 'bm_render_data_io_page');
}
add_action('admin_menu', 'bm_add_data_io_page');

function bm_render_data_io_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'import_books';
    ?>
    <div class="wrap">
        <h1><?php _e('Importação/Exportação', 'book-manager'); ?></h1>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=import_books'); ?>" class="nav-tab <?php echo $tab === 'import_books' ? 'nav-tab-active' : ''; ?>">📥 <?php _e('Importar Livros CSV', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=export_books'); ?>" class="nav-tab <?php echo $tab === 'export_books' ? 'nav-tab-active' : ''; ?>">📤 <?php _e('Exportar Livros CSV', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=import_students'); ?>" class="nav-tab <?php echo $tab === 'import_students' ? 'nav-tab-active' : ''; ?>">👥 <?php _e('Importar Alunos CSV', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=import_call_number'); ?>" class="nav-tab <?php echo $tab === 'import_call_number' ? 'nav-tab-active' : ''; ?>">📋 <?php _e('Importar Nº Chamada', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=export_call_number'); ?>" class="nav-tab <?php echo $tab === 'export_call_number' ? 'nav-tab-active' : ''; ?>">📋 <?php _e('Exportar Nº Chamada', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=export_import_all'); ?>" class="nav-tab <?php echo $tab === 'export_import_all' ? 'nav-tab-active' : ''; ?>">📦 <?php _e('Exportar/Importar Tudo', 'book-manager'); ?></a>
        </nav>
        
        <?php
        if ($tab === 'export_import_all') {
            bm_render_export_import_all_page();
        } elseif ($tab === 'export_books') {
            bm_render_csv_export_page();
        } elseif ($tab === 'import_students') {
            bm_render_student_import_page();
        } elseif ($tab === 'import_call_number') {
            bm_render_call_number_import_page();
        } elseif ($tab === 'export_call_number') {
            bm_render_call_number_export_page();
        } else {
            bm_render_csv_import_page();
        }
        ?>
    </div>
    <?php
}

// ==========================================
// FASE 6A/7G: IMPORTAÇÃO CSV COM MAPEAMENTO DINÂMICO
// ==========================================
function bm_render_csv_import_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $message = ''; $preview = array(); $duplicates = array();
    $stage = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';
    $headers = array();
    if ('process'===$stage && isset($_POST['bm_csv_import_nonce']) && wp_verify_nonce($_POST['bm_csv_import_nonce'],'bm_csv_import_action')) {
        $skip_duplicates = isset($_POST['skip_duplicates'])&&'1'===$_POST['skip_duplicates'];
        $classify_with_ai = isset($_POST['classify_with_ai']) && '1' === $_POST['classify_with_ai'];
        $generate_call_number = isset($_POST['generate_call_number']) && '1' === $_POST['generate_call_number'];
        $google_enabled = isset($_POST['google_covers']) || isset($_POST['google_sinopse']) || isset($_POST['google_rating']) || isset($_POST['google_subtitle']) || isset($_POST['google_published_date']) || isset($_POST['google_page_count']) || isset($_POST['google_isbn13']) || isset($_POST['google_isbn10']);
        $youtube_search = isset($_POST['youtube_search']) && '1' === $_POST['youtube_search'];
        $google_covers = isset($_POST['google_covers']) && '1' === $_POST['google_covers'];
        $google_sinopse = isset($_POST['google_sinopse']) && '1' === $_POST['google_sinopse'];
        $google_rating = isset($_POST['google_rating']) && '1' === $_POST['google_rating'];
        $google_subtitle = isset($_POST['google_subtitle']) && '1' === $_POST['google_subtitle'];
        $google_published_date = isset($_POST['google_published_date']) && '1' === $_POST['google_published_date'];
        $google_page_count = isset($_POST['google_page_count']) && '1' === $_POST['google_page_count'];
        $google_isbn13 = isset($_POST['google_isbn13']) && '1' === $_POST['google_isbn13'];
        $google_isbn10 = isset($_POST['google_isbn10']) && '1' === $_POST['google_isbn10'];
        $imported=0; $skipped=0; $dup_skipped=0; $dup_forced=0;
        $imported_list = array(); $dup_list = array(); $error_list = array();
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
                if (empty($title)) { $skipped++; $error_list[] = array('title' => __('(sem título)', 'book-manager'), 'reason' => __('Linha sem título', 'book-manager')); continue; }
                $csv_location = isset($mapping['_bm_location']) && isset($row[$mapping['_bm_location']]) ? sanitize_text_field($row[$mapping['_bm_location']]) : '';
                $exists = bm_find_duplicate_book($title,$author,$publisher,$csv_location);
                if ($exists && $skip_duplicates) { $dup_skipped++; $dup_list[] = array('title' => $title, 'author' => $author, 'reason' => __('Duplicado — já existe no acervo', 'book-manager')); continue; }
                if ($exists) { $dup_forced++; }
                $post_id = wp_insert_post(array('post_type'=>'bm_book','post_title'=>$title,'post_status'=>'publish'));
                if ($post_id && !is_wp_error($post_id)) {
                    if ($author) update_post_meta($post_id,'_bm_author',$author);
                    if ($publisher) update_post_meta($post_id,'_bm_publisher',$publisher);
                    foreach ($mapping as $field => $index) {
                        if (in_array($field,array('title','_bm_author','_bm_publisher'))) continue;
                        if (isset($row[$index])&&!empty($row[$index])) {
                            $raw_value = trim($row[$index]);
                            if (taxonomy_exists($field)) {
                                $term_names = array_map('trim', explode(',', $raw_value));
                                $term_ids = array();
                                foreach ($term_names as $term_name) {
                                    if (empty($term_name)) continue;
                                    $term = term_exists($term_name, $field);
                                    if (!$term) {
                                        $new_term = wp_insert_term($term_name, $field);
                                        if (!is_wp_error($new_term)) {
                                            $term_ids[] = intval($new_term['term_id']);
                                        }
                                    } else {
                                        $term_ids[] = intval(is_array($term) ? $term['term_id'] : $term);
                                    }
                                }
                                if (!empty($term_ids)) {
                                    wp_set_post_terms($post_id, $term_ids, $field, false);
                                }
                            } else {
                                update_post_meta($post_id, $field, sanitize_text_field($raw_value));
                            }
                        }
                    }
                    $imported++;
                    $imported_list[] = array('title' => $title, 'author' => $author);
                    if ($google_enabled) {
                        $google_data = bm_fetch_google_book_data($title, $author, $publisher);
                        
                        if ($google_data) {
                            $settings = bm_get_settings();
                            $cover_mode = isset($settings['cover_mode']) ? $settings['cover_mode'] : 'download';
                            if ($google_covers && !empty($google_data['cover_url'])) {
                                if ($cover_mode === 'hotlink') {
                                    update_post_meta($post_id, '_bm_cover_hotlink', $google_data['cover_url']);
                                } else {
                                    require_once ABSPATH . 'wp-admin/includes/media.php';
                                    require_once ABSPATH . 'wp-admin/includes/file.php';
                                    require_once ABSPATH . 'wp-admin/includes/image.php';
                                    $ir = wp_remote_get($google_data['cover_url'], array('timeout' => 15));
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
                            }
                            
                            if ($google_sinopse && !empty($google_data['description'])) {
                                $dynamic_fields = get_option('bm_dynamic_fields', array());
                                if (!isset($dynamic_fields['Sinopse'])) {
                                    $dynamic_fields['Sinopse'] = array('type' => 'textarea');
                                    update_option('bm_dynamic_fields', $dynamic_fields);
                                }
                                update_post_meta($post_id, '_bm_dynamic_sinopse', $google_data['description']);
                            }
                            
                            if ($google_rating && !empty($google_data['rating'])) {
                                update_post_meta($post_id, '_bm_google_rating', $google_data['rating']);
                            }
                            
                            if ($google_subtitle && !empty($google_data['subtitle'])) {
                                update_post_meta($post_id, '_bm_google_subtitle', $google_data['subtitle']);
                            }
                            
                            if ($google_published_date && !empty($google_data['published_date'])) {
                                update_post_meta($post_id, '_bm_google_published_date', $google_data['published_date']);
                            }
                            
                            if ($google_page_count && !empty($google_data['page_count'])) {
                                update_post_meta($post_id, '_bm_google_page_count', $google_data['page_count']);
                            }
                            
                            if ($google_isbn13 && !empty($google_data['isbn13'])) {
                                update_post_meta($post_id, '_bm_isbn', $google_data['isbn13']);
                            } elseif ($google_isbn10 && !empty($google_data['isbn10'])) {
                                update_post_meta($post_id, '_bm_isbn', $google_data['isbn10']);
                            }
                        }
                    }
                    if ($youtube_search) {
                        $youtube_data = bm_search_youtube_video($title, $author, $publisher);
                        if ($youtube_data && !empty($youtube_data['url'])) {
                            update_post_meta($post_id, '_bm_official_link', $youtube_data['url']);
                        }
                    }
                    
                    if ($classify_with_ai) {
                        $groq_key = bm_get_api_key('groq');
                        if (!empty($groq_key)) {
                            bm_classify_book_with_ai($post_id);
                        }
                    }
                    if (isset($_POST['classify_reading_level_with_ai']) && $_POST['classify_reading_level_with_ai'] === '1') {
                        $csv_has_reading_level = false;
                        foreach ($mapping as $field => $index) {
                            if ($field === 'bm_reading_level' && isset($row[$index]) && !empty(trim($row[$index]))) {
                                $csv_has_reading_level = true;
                                break;
                            }
                        }
                        if (!$csv_has_reading_level) {
                            $groq_key = bm_get_api_key('groq');
                            if (!empty($groq_key)) {
                                bm_classify_reading_level_with_ai($post_id);
                            }
                        }
                    }
                    if ($generate_call_number) {
                        $csv_cdu = get_post_meta($post_id, '_bm_cdu', true);
                        $csv_cutter = get_post_meta($post_id, '_bm_cutter', true);
                        
                        if (!empty($csv_cdu) && !empty($csv_cutter)) {
                            update_post_meta($post_id, '_bm_cutter_cached', '1');
                            update_post_meta($post_id, '_bm_cutter_locked', '1');
                        } else {
                            $groq_key = bm_get_api_key('groq');
                            if (!empty($groq_key)) {
                                $result = bm_generate_call_number($post_id);
                                if (!empty($csv_cdu) && $result) {
                                    update_post_meta($post_id, '_bm_cdu', $csv_cdu);
                                }
                                if (!empty($csv_cutter) && $result) {
                                    update_post_meta($post_id, '_bm_cutter', $csv_cutter);
                                }
                            }
                        }
                    }
                } else { $skipped++; }
            }
        }
        $message = '<div class="notice notice-success"><p><strong>' . __('Importação concluída!', 'book-manager') . '</strong> ' . sprintf(__('%d livros processados.', 'book-manager'), $imported + $dup_skipped + $dup_forced + $skipped) . '</p></div>';
        
        if (!empty($imported_list)) {
            $message .= '<div style="background:#e8f5e9;padding:10px;border-radius:4px;margin-bottom:10px;border-left:4px solid #46b450;">';
            $message .= '<strong>✅ ' . sprintf(__('Importados com sucesso (%d):', 'book-manager'), count($imported_list)) . '</strong>';
            $message .= '<ul style="margin:5px 0 0 0;padding-left:20px;max-height:300px;overflow-y:auto;">';
            foreach ($imported_list as $item) {
                $message .= '<li>' . esc_html($item['title']) . ($item['author'] ? ' — ' . esc_html($item['author']) : '') . '</li>';
            }
            $message .= '</ul></div>';
        }
        
        if (!empty($dup_list)) {
            $message .= '<div style="background:#fff8e1;padding:10px;border-radius:4px;margin-bottom:10px;border-left:4px solid #f0ad4e;">';
            $message .= '<strong>⚠️ ' . sprintf(__('Duplicados pulados (%d):', 'book-manager'), count($dup_list)) . '</strong>';
            $message .= '<ul style="margin:5px 0 0 0;padding-left:20px;max-height:300px;overflow-y:auto;">';
            foreach ($dup_list as $item) {
                $message .= '<li>' . esc_html($item['title']) . ($item['author'] ? ' — ' . esc_html($item['author']) : '') . ' <span style="color:#999;">(' . esc_html($item['reason']) . ')</span></li>';
            }
            $message .= '</ul></div>';
        }
        
        if (!empty($error_list)) {
            $message .= '<div style="background:#fff3f3;padding:10px;border-radius:4px;margin-bottom:10px;border-left:4px solid #dc3545;">';
            $message .= '<strong>❌ ' . sprintf(__('Erros (%d):', 'book-manager'), count($error_list)) . '</strong>';
            $message .= '<ul style="margin:5px 0 0 0;padding-left:20px;max-height:300px;overflow-y:auto;">';
            foreach ($error_list as $item) {
                $message .= '<li>' . esc_html($item['title']) . ' <span style="color:#999;">(' . esc_html($item['reason']) . ')</span></li>';
            }
            $message .= '</ul></div>';
        }
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
    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    foreach ($dynamic_taxonomies as $slug => $info) {
        $system_fields[$slug] = $info['label'] . ' (' . __('taxonomia','book-manager') . ')';
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Importar Livros via CSV','book-manager'); ?></h1>
        <?php if ($message): ?><div class="notice notice-success is-dismissible"><?php echo wp_kses_post($message); ?></div><?php endif; ?>
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
                <h3><?php _e('Google Books API', 'book-manager'); ?></h3>
                <p>
                    <label>
                        <input type="checkbox" id="bm-enable-google-api" onchange="bmToggleGoogleApi()">
                        <strong><?php _e('Habilitar busca automática via Google Books', 'book-manager'); ?></strong>
                    </label>
                </p>
                
                <div id="bm-google-api-options" style="display:none;padding:10px;background:#f9f9f9;border-radius:4px;margin-bottom:10px;">
                    <p><strong><?php _e('Selecione o que importar:', 'book-manager'); ?></strong></p>
                    <p>
                        <label><input type="checkbox" name="google_covers" value="1" checked> <?php _e('Capa do livro', 'book-manager'); ?></label><br>
                        <label><input type="checkbox" name="google_sinopse" value="1" checked> <?php _e('Sinopse', 'book-manager'); ?></label>
                    </p>
                    <p style="color:#999;font-size:12px;"><?php _e('Estes campos são buscados automaticamente durante a importação.', 'book-manager'); ?></p>
                    <p>
                        <label><input type="checkbox" name="google_rating" value="1"> <?php _e('Avaliação (estrelas)', 'book-manager'); ?></label><br>
                        <label><input type="checkbox" name="google_subtitle" value="1"> <?php _e('Subtítulo', 'book-manager'); ?></label><br>
                        <label><input type="checkbox" name="google_published_date" value="1"> <?php _e('Data de publicação', 'book-manager'); ?></label><br>
                        <label><input type="checkbox" name="google_page_count" value="1"> <?php _e('Número de páginas', 'book-manager'); ?></label>
                    </p>
                    <p style="color:#999;font-size:12px;"><?php _e('Acima: dados complementares (desmarcados por padrão).', 'book-manager'); ?></p>
                    <p>
                        <strong><?php _e('ISBN:', 'book-manager'); ?></strong><br>
                        <label><input type="checkbox" name="google_isbn13" value="1" checked onchange="if(this.checked)document.querySelector('[name=google_isbn10]').checked=false"> <?php _e('ISBN-13', 'book-manager'); ?></label>
                        <span style="color:#666;font-size:11px;"><?php _e('Mais utilizado no Brasil', 'book-manager'); ?></span><br>
                        <label><input type="checkbox" name="google_isbn10" value="1" onchange="if(this.checked)document.querySelector('[name=google_isbn13]').checked=false"> <?php _e('ISBN-10', 'book-manager'); ?></label>
                        <span style="color:#666;font-size:11px;"><?php _e('Para acervos publicados antes de 2007', 'book-manager'); ?></span>
                    </p>
                </div>
                
                <script>
                function bmToggleGoogleApi() {
                    var enabled = document.getElementById('bm-enable-google-api').checked;
                    document.getElementById('bm-google-api-options').style.display = enabled ? 'block' : 'none';
                }
                </script>
                <h3><?php _e('YouTube', 'book-manager'); ?></h3>
                <p>
                    <label><input type="checkbox" name="youtube_search" value="1"> <strong><?php _e('Buscar vídeo-resenha oficial no YouTube', 'book-manager'); ?></strong></label>
                    <br><small><?php _e('Busca por título + autor + editora e salva como resenha oficial do livro.', 'book-manager'); ?></small>
                </p>
                
                <p><strong><?php _e('Classificação por IA:','book-manager'); ?></strong>
                <label><input type="checkbox" name="classify_with_ai" value="1" checked> <?php _e('Classificar livros por disciplina (Groq)', 'book-manager'); ?></label></p>
                <p><strong><?php _e('Nível de Leitura por IA:', 'book-manager'); ?></strong>
                    <label><input type="checkbox" name="classify_reading_level_with_ai" value="1"> <?php _e('Classificar Nível de Leitura automaticamente (Groq)', 'book-manager'); ?></label>
                    <br><small><?php _e('Se o CSV não tiver a coluna Nível de Leitura ou o valor estiver vazio, a IA analisará o livro e escolherá entre: Muito fácil, Fácil, Intermediário, Avançado, Muito avançado. Se a IA não souber, o campo ficará vazio.', 'book-manager'); ?></small></p>
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
    $existing = get_posts(array(
        'post_type'              => 'bm_book',
        'title'                  => $title,
        'posts_per_page'         => -1,
        'post_status'            => array('publish', 'draft', 'pending', 'private'),
        'cache_results'          => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ));
    
    if (empty($existing)) return false;
    
    $csv_author    = trim(mb_strtolower($author));
    $csv_publisher = trim(mb_strtolower($publisher));
    
    foreach ($existing as $book) {
        $book_author    = trim(mb_strtolower(get_post_meta($book->ID, '_bm_author', true)));
        $book_publisher = trim(mb_strtolower(get_post_meta($book->ID, '_bm_publisher', true)));
        
        if ($book_author === $csv_author && $book_publisher === $csv_publisher) {
            return $book->ID;
        }
    }
    return false;
}

// ==========================================
// FASE 6B/7E: EXPORTAÇÃO CSV FLEXÍVEL
// ==========================================
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
add_action('admin_init', 'bm_handle_csv_export', 10);

function bm_csv_export_redirect() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!isset($_POST['bm_csv_export_nonce']) || !wp_verify_nonce($_POST['bm_csv_export_nonce'], 'bm_csv_export_action')) return;
    
    $books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    $count = count($books);
    
    $redirect_url = add_query_arg(array(
        'post_type' => 'bm_book',
        'page' => 'bm_data_io',
        'tab' => 'export_books',
        'exported' => $count,
    ), admin_url('edit.php'));
    
    set_transient('bm_export_message', $count, 60);
}
add_action('admin_init', 'bm_csv_export_redirect', 9);

function bm_render_csv_export_page() {
    if (!current_user_can('manage_options')) return;
    
    $export_msg = get_transient('bm_export_message');
    if ($export_msg) {
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d livros exportados com sucesso!', 'book-manager'), $export_msg) . '</p></div>';
        delete_transient('bm_export_message');
    }
    
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
// FASE 12H: IMPORTAÇÃO DE ALUNOS EM MASSA
// ==========================================
function bm_render_student_import_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $message = '';
    $stage = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';
    $headers = array();
    
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
// FASE 19: IMPORTAÇÃO DE NÚMERO DE CHAMADA VIA CSV
// ==========================================
function bm_render_call_number_import_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $message = '';
    $stage = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';
    $headers = array();
    
    if ('process' === $stage && isset($_POST['bm_cn_import_nonce']) && wp_verify_nonce($_POST['bm_cn_import_nonce'], 'bm_cn_import_action')) {
        $imported = 0; $skipped = 0;
        
        $mapping_raw = isset($_POST['mapping']) ? array_map('sanitize_text_field', $_POST['mapping']) : array();
        $mapping = array();
        foreach ($mapping_raw as $csv_index => $field) {
            if (!empty($field)) $mapping[$field] = intval($csv_index);
        }
        
        if (!empty($_POST['csv_data'])) {
            $rows = json_decode(stripslashes($_POST['csv_data']), true);
            foreach ($rows as $row) {
                $title = '';
                if (isset($mapping['title']) && isset($row[$mapping['title']])) $title = trim(sanitize_text_field($row[$mapping['title']]));
                if (empty($title)) { $skipped++; continue; }
                
                $existing = get_posts(array('post_type' => 'bm_book', 'title' => $title, 'posts_per_page' => 1, 'post_status' => 'any'));
                if (empty($existing)) { $skipped++; continue; }
                
                $post_id = $existing[0]->ID;
                
                if (isset($mapping['_bm_cdu']) && isset($row[$mapping['_bm_cdu']])) {
                    update_post_meta($post_id, '_bm_cdu', sanitize_text_field($row[$mapping['_bm_cdu']]));
                }
                if (isset($mapping['_bm_cutter']) && isset($row[$mapping['_bm_cutter']])) {
                    update_post_meta($post_id, '_bm_cutter', sanitize_text_field($row[$mapping['_bm_cutter']]));
                }
                if (isset($mapping['_bm_edition']) && isset($row[$mapping['_bm_edition']])) {
                    update_post_meta($post_id, '_bm_edition', sanitize_text_field($row[$mapping['_bm_edition']]));
                }
                if (isset($mapping['_bm_volume']) && isset($row[$mapping['_bm_volume']])) {
                    update_post_meta($post_id, '_bm_volume', sanitize_text_field($row[$mapping['_bm_volume']]));
                }
                
                update_post_meta($post_id, '_bm_cutter_cached', '1');
                update_post_meta($post_id, '_bm_cutter_locked', '1');
                $imported++;
            }
        }
        $message = sprintf(__('%d números de chamada importados, %d ignorados.', 'book-manager'), $imported, $skipped);
    }
    
    if ('' === $stage && isset($_FILES['csv_file']) && isset($_POST['bm_cn_import_nonce'])) {
        if (!wp_verify_nonce($_POST['bm_cn_import_nonce'], 'bm_cn_import_action')) {
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
    
    $system_fields = array(
        'title' => __('Título (obrigatório)', 'book-manager'),
        '_bm_cdu' => __('Classificação (CDU/CDD)', 'book-manager'),
        '_bm_cutter' => __('Cutter', 'book-manager'),
        '_bm_edition' => __('Edição', 'book-manager'),
        '_bm_volume' => __('Volume', 'book-manager'),
    );
    ?>
    <div class="wrap">
        <h1><?php _e('Importar Número de Chamada via CSV', 'book-manager'); ?></h1>
        <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        
        <?php if ('map' === $stage && !empty($headers)): ?>
            <h2><?php _e('Mapeamento de Colunas', 'book-manager'); ?></h2>
            <p><?php _e('Associe cada coluna do seu arquivo ao campo correspondente.', 'book-manager'); ?></p>
            <form method="post">
                <?php wp_nonce_field('bm_cn_import_action', 'bm_cn_import_nonce'); ?>
                <input type="hidden" name="import_stage" value="process">
                <input type="hidden" name="csv_data" value="<?php echo esc_attr(json_encode(json_decode(stripslashes($_POST['csv_data_preview']), true), JSON_UNESCAPED_UNICODE)); ?>">
                <?php foreach ($headers as $i => $h): ?>
                    <p><strong><?php echo esc_html($h); ?></strong> →
                    <select name="mapping[<?php echo $i; ?>]">
                        <option value=""><?php _e('— Ignorar —', 'book-manager'); ?></option>
                        <?php foreach ($system_fields as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select></p>
                <?php endforeach; ?>
                <?php submit_button(__('Importar', 'book-manager')); ?>
            </form>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bm_cn_import_action', 'bm_cn_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="csv_file"><?php _e('Arquivo CSV', 'book-manager'); ?></label></th>
                        <td><input type="file" id="csv_file" name="csv_file" accept=".csv" /><p class="description"><?php _e('CSV com colunas: Título, Classificação, Cutter, Edição, Volume.', 'book-manager'); ?></p></td>
                    </tr>
                </table>
                <?php submit_button(__('Enviar Arquivo', 'book-manager')); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// FASE 19: EXPORTAÇÃO DE NÚMERO DE CHAMADA
// ==========================================
function bm_render_call_number_export_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $total = wp_count_posts('bm_book');
    $total = $total->publish + $total->draft + $total->trash;
    ?>
    <div class="wrap">
        <h1><?php _e('Exportar Número de Chamada', 'book-manager'); ?></h1>
        <p><?php echo sprintf(__('%d livros no acervo. Apenas livros com Classificação ou Cutter preenchidos serão exportados.', 'book-manager'), $total); ?></p>
        <form method="post">
            <?php wp_nonce_field('bm_cn_export_action', 'bm_cn_export_nonce'); ?>
            <p><?php _e('Exporta: Título, Classificação, Cutter, Edição, Volume.', 'book-manager'); ?></p>
            <?php submit_button(__('Exportar CSV', 'book-manager')); ?>
        </form>
    </div>
    <?php
}

function bm_handle_call_number_export() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!isset($_POST['bm_cn_export_nonce']) || !wp_verify_nonce($_POST['bm_cn_export_nonce'], 'bm_cn_export_action')) return;
    
    $books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => 999, 'post_status' => 'publish'));
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="numero_chamada.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Título', 'Classificação', 'Cutter', 'Edição', 'Volume'), ';');
    
    foreach ($books as $book) {
        $cdu = get_post_meta($book->ID, '_bm_cdu', true);
        $cutter = get_post_meta($book->ID, '_bm_cutter', true);
        $edition = get_post_meta($book->ID, '_bm_edition', true);
        $volume = get_post_meta($book->ID, '_bm_volume', true);
        
        if (!empty($cdu) || !empty($cutter)) {
            fputcsv($output, array(
                $book->post_title,
                $cdu,
                $cutter,
                $edition,
                $volume,
            ), ';');
        }
    }
    fclose($output);
    exit;
}
add_action('admin_init', 'bm_handle_call_number_export');

// ==========================================
// FASE 33: CENTRAL DE EXPORTAR/IMPORTAR TUDO
// ==========================================
function bm_render_export_import_all_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $subtab = isset($_GET['subtab']) ? sanitize_text_field($_GET['subtab']) : 'export';
    ?>
    <div class="wrap">

    <?php
    $export_msg = get_transient('bm_export_all_message');
    if ($export_msg) {
        $class = $export_msg['type'] === 'success' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . wp_kses_post($export_msg['text']) . '</p></div>';
        delete_transient('bm_export_all_message');
    }
    ?>
        <p style="color:#555;font-size:14px;margin-bottom:20px;"><?php _e('Faça backup do seu acervo completo ou migre dados entre sites. Exporte para um arquivo compactado e importe quando precisar.', 'book-manager'); ?></p>
        
        <div style="display:flex;gap:30px;flex-wrap:wrap;margin-bottom:20px;">
            <div style="flex:1;min-width:250px;background:#f0f7ff;padding:15px;border-radius:6px;border-left:4px solid #0073aa;">
                <strong style="font-size:15px;">📤 <?php _e('Exportar', 'book-manager'); ?></strong>
                <p style="margin:8px 0 0 0;color:#555;"><?php _e('Selecione os módulos → Escolha o formato → Baixe o arquivo', 'book-manager'); ?></p>
            </div>
            <div style="flex:1;min-width:250px;background:#fff8e1;padding:15px;border-radius:6px;border-left:4px solid #ff9800;">
                <strong style="font-size:15px;">📥 <?php _e('Importar', 'book-manager'); ?></strong>
                <p style="margin:8px 0 0 0;color:#555;"><?php _e('Faça upload do arquivo → Revise a prévia → Confirme a importação', 'book-manager'); ?></p>
            </div>
        </div>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=export_import_all&subtab=export'); ?>" class="nav-tab <?php echo $subtab === 'export' ? 'nav-tab-active' : ''; ?>">📤 <?php _e('Exportar', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_data_io&tab=export_import_all&subtab=import'); ?>" class="nav-tab <?php echo $subtab === 'import' ? 'nav-tab-active' : ''; ?>">📥 <?php _e('Importar', 'book-manager'); ?></a>
        </nav>
        
        <?php if ($subtab === 'import'): ?>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #ff9800;">
                <p style="margin:0;font-size:14px;">📥 <strong><?php _e('Importar dados', 'book-manager'); ?></strong> — <?php _e('Faça upload do arquivo → Revise a prévia → Confirme a importação', 'book-manager'); ?></p>
            </div>
            
            <?php
            $import_report = get_transient('bm_import_report');
            if ($import_report):
                delete_transient('bm_import_report');
            ?>
                <div class="notice notice-success is-dismissible"><p><strong><?php _e('✅ Importação concluída!', 'book-manager'); ?></strong></p></div>
                <?php foreach ($import_report as $module): ?>
                    <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;margin-bottom:15px;">
                        <h3 style="margin:0 0 10px 0;"><?php echo esc_html($module['label']); ?></h3>
                        <?php 
                        $imported = count($module['imported']);
                        $duplicates = count($module['duplicates']);
                        $errors = count($module['errors']);
                        $total = $imported + $duplicates + $errors;
                        ?>
                        <p style="margin:0 0 10px 0;color:#555;"><?php printf(__('%d registros processados.', 'book-manager'), $total); ?></p>
                        <?php if ($imported > 0): ?>
                            <div style="background:#e8f5e9;padding:10px;border-radius:4px;margin-bottom:8px;border-left:4px solid #46b450;">
                                <strong>📥 <?php printf(__('Importados com sucesso (%d):', 'book-manager'), $imported); ?></strong>
                                <ul style="margin:5px 0 0 0;padding-left:20px;max-height:200px;overflow-y:auto;">
                                    <?php foreach ($module['imported'] as $item): ?>
                                        <li><?php echo esc_html($item['item']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if ($duplicates > 0): ?>
                            <div style="background:#fff8e1;padding:10px;border-radius:4px;margin-bottom:8px;border-left:4px solid #f0ad4e;">
                                <strong>⚠️ <?php printf(__('Duplicados — não importados (%d):', 'book-manager'), $duplicates); ?></strong>
                                <ul style="margin:5px 0 0 0;padding-left:20px;max-height:200px;overflow-y:auto;">
                                    <?php foreach ($module['duplicates'] as $item): ?>
                                        <li><?php echo esc_html($item['item']); ?> — <span style="color:#999;"><?php echo esc_html($item['reason']); ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if ($errors > 0): ?>
                            <div style="background:#fff3f3;padding:10px;border-radius:4px;margin-bottom:8px;border-left:4px solid #dc3545;">
                                <strong>❌ <?php printf(__('Erros — não importados (%d):', 'book-manager'), $errors); ?></strong>
                                <ul style="margin:5px 0 0 0;padding-left:20px;max-height:200px;overflow-y:auto;">
                                    <?php foreach ($module['errors'] as $item): ?>
                                        <li><?php echo esc_html($item['item']); ?> — <span style="color:#999;"><?php echo esc_html($item['reason']); ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if ($imported === 0 && $duplicates === 0 && $errors === 0): ?>
                            <p style="color:#999;"><?php _e('Nenhum registro processado.', 'book-manager'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php
            $import_preview = get_transient('bm_import_preview');
            if ($import_preview):
                delete_transient('bm_import_preview');
            ?>
                <div class="notice notice-success is-dismissible"><p><?php _e('✅ Arquivo analisado com sucesso!', 'book-manager'); ?></p></div>
                <?php foreach ($import_preview as $module): ?>
                    <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;margin-bottom:15px;">
                        <h3 style="margin:0 0 5px 0;"><?php echo esc_html($module['label']); ?></h3>
                        <?php if (!empty($module['standard_mode'])): ?>
                            <p style="background:#e8f5e9;display:inline-block;padding:4px 12px;border-radius:4px;font-size:13px;margin:0 0 8px 0;">✅ <?php _e('Arquivo reconhecido — Modo Padrão', 'book-manager'); ?></p>
                        <?php endif; ?>
                        <p style="margin:0 0 10px 0;color:#555;"><?php printf(__('%s — %d registros encontrados', 'book-manager'), esc_html($module['filename']), $module['count']); ?></p>
                        <?php if ($module['count'] > 0): ?>
                            <table class="wp-list-table widefat fixed striped" style="margin-bottom:10px;">
                                <thead><tr><?php foreach ($module['preview_headers'] as $h): ?><th><?php echo esc_html($h); ?></th><?php endforeach; ?></tr></thead>
                                <tbody>
                                    <?php foreach ($module['preview_rows'] as $row): ?>
                                        <tr><?php foreach ($row as $cell): ?><td><?php echo esc_html($cell); ?></td><?php endforeach; ?></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <form method="post" style="margin-top:15px;">
                    <?php wp_nonce_field('bm_import_execute_action', 'bm_import_execute_nonce'); ?>
                    <input type="hidden" name="bm_import_mode" value="<?php echo isset($_POST['bm_import_mode']) ? esc_attr($_POST['bm_import_mode']) : 'add'; ?>" />
                    <p>
                        <input type="submit" name="bm_import_execute_submit" class="button button-primary" value="<?php _e('✅ Confirmar Importação', 'book-manager'); ?>" style="font-size:16px;padding:12px 30px;" />
                    </p>
                    <p class="description"><?php _e('Esta ação irá salvar os dados no sistema. Verifique a prévia antes de confirmar.', 'book-manager'); ?></p>
                </form>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bm_import_all_action', 'bm_import_all_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bm_import_file"><?php _e('Arquivo ZIP', 'book-manager'); ?></label></th>
                        <td>
                            <input type="file" id="bm_import_file" name="bm_import_file" accept=".zip" style="width:100%;max-width:400px;" />
                            <p class="description"><?php _e('Selecione um arquivo .zip gerado pela exportação do Book Manager.', 'book-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <p><input type="submit" name="bm_import_submit" class="button button-primary" value="<?php _e('📥 Enviar e analisar', 'book-manager'); ?>" /></p>
            </form>
                        
            <hr style="margin:25px 0;" />
            <h3><?php _e('Arquivo individual (CSV ou JSON)', 'book-manager'); ?></h3>
            <p style="color:#555;"><?php _e('Se você exportou apenas um módulo, envie o arquivo diretamente.', 'book-manager'); ?></p>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('bm_import_single_action', 'bm_import_single_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bm_import_single_file"><?php _e('Arquivo', 'book-manager'); ?></label></th>
                        <td>
                            <input type="file" id="bm_import_single_file" name="bm_import_single_file" accept=".csv,.json" style="width:100%;max-width:400px;" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bm_import_single_type"><?php _e('Tipo de módulo', 'book-manager'); ?></label></th>
                        <td>
                            <select name="bm_import_single_type" id="bm_import_single_type" style="width:200px;">
                                <option value="books">📚 <?php _e('Livros', 'book-manager'); ?></option>
                                <option value="students">👥 <?php _e('Alunos', 'book-manager'); ?></option>
                                <option value="loans">📋 <?php _e('Histórico de Circulação', 'book-manager'); ?></option>
                                <option value="readings">📝 <?php _e('Fichas de Leitura', 'book-manager'); ?></option>
                                <option value="taxonomies">🏷️ <?php _e('Taxonomias', 'book-manager'); ?></option>
                                <option value="settings">⚙️ <?php _e('Configurações (JSON)', 'book-manager'); ?></option>
                            </select>
                            <p class="description"><?php _e('Informe qual tipo de dado este arquivo contém.', 'book-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Modo de importação', 'book-manager'); ?></label></th>
                        <td>
                            <label style="margin-right:20px;"><input type="radio" name="bm_import_mode" value="add" checked /> <?php _e('Apenas adicionar novos registros (recomendado)', 'book-manager'); ?></label><br>
                            <label><input type="radio" name="bm_import_mode" value="overwrite" /> <?php _e('Sobrescrever dados existentes', 'book-manager'); ?></label>
                            <p class="description"><?php _e('"Adicionar" ignora duplicados. "Sobrescrever" atualiza registros que já existem.', 'book-manager'); ?></p>
                        </td>
                    </tr>
                </table>
                <p><input type="submit" name="bm_import_single_submit" class="button button-primary" value="<?php _e('📥 Enviar e analisar', 'book-manager'); ?>" /></p>
            </form>
        <?php else: ?>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #0073aa;">
                <p style="margin:0;font-size:14px;">📤 <strong><?php _e('Exportar dados', 'book-manager'); ?></strong> — <?php _e('Selecione os módulos → Escolha o formato → Baixe o arquivo', 'book-manager'); ?></p>
            </div>
            
            <form method="post" id="bm-export-all-form">
                <?php wp_nonce_field('bm_export_all_action', 'bm_export_all_nonce'); ?>
                
                <p style="margin-bottom:5px;"><strong><?php _e('O que você deseja exportar?', 'book-manager'); ?></strong></p>
                <p style="color:#777;margin-top:0;font-size:13px;"><?php _e('Marque os módulos que deseja incluir no arquivo de exportação.', 'book-manager'); ?></p>
                
                <table class="form-table" style="max-width:700px;">
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="bm-check-all" checked /></th>
                        <th style="width:200px;"><?php _e('Módulo', 'book-manager'); ?></th>
                        <th><?php _e('O que contém', 'book-manager'); ?></th>
                    </tr>
                    <tr>
                        <td><input type="checkbox" name="bm_export_modules[]" value="books" checked class="bm-export-check" /></td>
                        <td><strong>📚 <?php _e('Livros', 'book-manager'); ?></strong></td>
                        <td style="color:#555;"><?php _e('Títulos, autores, ISBN, localização, capas, sinopses, Número de Chamada e todas as informações do seu acervo.', 'book-manager'); ?></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" name="bm_export_modules[]" value="students" checked class="bm-export-check" /></td>
                        <td><strong>👥 <?php _e('Alunos', 'book-manager'); ?></strong></td>
                        <td style="color:#555;"><?php _e('Nome, e-mail, telefone, turma, série e todos os dados cadastrais. Senhas NÃO são exportadas.', 'book-manager'); ?></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" name="bm_export_modules[]" value="loans" checked class="bm-export-check" /></td>
                        <td><strong>📋 <?php _e('Histórico de circulação', 'book-manager'); ?></strong></td>
                        <td style="color:#555;"><?php _e('Quem pegou qual livro, quando, se devolveu, se atrasou. Inclui reservas e agendamentos.', 'book-manager'); ?></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" name="bm_export_modules[]" value="readings" checked class="bm-export-check" /></td>
                        <td><strong>📝 <?php _e('Fichas de leitura', 'book-manager'); ?></strong></td>
                        <td style="color:#555;"><?php _e('Resenhas, notas com estrelas e vídeos que os alunos enviaram.', 'book-manager'); ?></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" name="bm_export_modules[]" value="taxonomies" checked class="bm-export-check" /></td>
                        <td><strong>🏷️ <?php _e('Categorias e gêneros', 'book-manager'); ?></strong></td>
                        <td style="color:#555;"><?php _e('Gêneros, categorias, disciplinas e outras classificações que você criou.', 'book-manager'); ?></td>
                    </tr>
                    <tr>
                        <td><input type="checkbox" name="bm_export_modules[]" value="settings" checked class="bm-export-check" /></td>
                        <td><strong>⚙️ <?php _e('Configurações da escola', 'book-manager'); ?></strong></td>
                        <td style="color:#555;"><?php _e('Nome da escola, logotipo, regras de multa, prazos e preferências do sistema.', 'book-manager'); ?></td>
                    </tr>
                </table>
                
                <p style="margin-top:15px;"><strong><?php _e('Formato do arquivo:', 'book-manager'); ?></strong></p>
                <p style="margin-top:0;">
                    <label style="margin-right:20px;"><input type="radio" name="bm_export_format" value="zip" checked /> <?php _e('ZIP — arquivo compactado com um CSV para cada módulo (recomendado para migração)', 'book-manager'); ?></label><br>
                    <label><input type="radio" name="bm_export_format" value="csv" /> <?php _e('CSV único — todos os módulos em um só arquivo (mais simples, porém limitado)', 'book-manager'); ?></label>
                </p>
                
                <p style="margin-top:15px;">
                    <button type="submit" name="bm_export_all_submit" class="button button-primary" style="font-size:15px;padding:8px 24px;">📤 <?php _e('Exportar', 'book-manager'); ?></button>
                </p>
            </form>
            
            <script>
            document.getElementById('bm-check-all').addEventListener('change', function() {
                var checks = document.querySelectorAll('.bm-export-check');
                checks.forEach(function(c) { c.checked = this.checked; }.bind(this));
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}

function bm_export_books_full() {
    $books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    if (empty($books)) return array('csv' => '', 'count' => 0);
    
    $dynamic_fields = get_option('bm_dynamic_fields', array());
    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($dynamic_taxonomies)) $dynamic_taxonomies = array();
    
    $headers = array('Título', 'Autor', 'Editora', 'ISBN', 'Localização', 'Exemplares', 'Unidade');
    $headers[] = 'Gêneros';
    $headers[] = 'Categorias';
    $headers[] = 'Disciplinas';
    foreach ($dynamic_taxonomies as $slug => $info) {
        $headers[] = $info['label'];
    }
    foreach ($dynamic_fields as $name => $info) {
        $headers[] = $name;
    }
    $headers = array_merge($headers, array('Classificação', 'Cutter', 'Edição', 'Volume', 'Capa (URL)', 'Sinopse', 'Atividades Pedagógicas', 'Resenha Oficial', 'Link Oficial'));
    
    $output = fopen('php://temp', 'r+');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');
    
    foreach ($books as $book) {
        $row = array();
        $row[] = $book->post_title;
        $row[] = get_post_meta($book->ID, '_bm_author', true);
        $row[] = get_post_meta($book->ID, '_bm_publisher', true);
        $row[] = get_post_meta($book->ID, '_bm_isbn', true);
        $row[] = get_post_meta($book->ID, '_bm_location', true);
        $row[] = get_post_meta($book->ID, '_bm_copies', true);
        $row[] = get_post_meta($book->ID, '_bm_library_unit', true);
        
        $genres = wp_get_post_terms($book->ID, 'bm_genre', array('fields' => 'names'));
        $row[] = implode(', ', $genres);
        $categories = wp_get_post_terms($book->ID, 'bm_category', array('fields' => 'names'));
        $row[] = implode(', ', $categories);
        $disciplines = wp_get_post_terms($book->ID, 'bm_discipline', array('fields' => 'names'));
        $row[] = implode(', ', $disciplines);
        
        foreach ($dynamic_taxonomies as $slug => $info) {
            $terms = wp_get_post_terms($book->ID, $slug, array('fields' => 'names'));
            $row[] = implode(', ', $terms);
        }
        
        foreach ($dynamic_fields as $name => $info) {
            $key = '_bm_dynamic_' . sanitize_key($name);
            $row[] = get_post_meta($book->ID, $key, true);
        }
        
        $row[] = get_post_meta($book->ID, '_bm_cdu', true);
        $row[] = get_post_meta($book->ID, '_bm_cutter', true);
        $row[] = get_post_meta($book->ID, '_bm_edition', true);
        $row[] = get_post_meta($book->ID, '_bm_volume', true);
        
        $cover = get_the_post_thumbnail_url($book->ID, 'full');
        if (!$cover) $cover = get_post_meta($book->ID, '_bm_cover_hotlink', true);
        $row[] = $cover ? $cover : '';
        
        $row[] = get_post_meta($book->ID, '_bm_dynamic_sinopse', true);
        $row[] = get_post_meta($book->ID, '_bm_activities', true);
        $row[] = get_post_meta($book->ID, '_bm_official_review', true);
        $row[] = get_post_meta($book->ID, '_bm_official_link', true);
        
        fputcsv($output, $row, ';');
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return array('csv' => $csv, 'count' => count($books));
}

function bm_export_students_full() {
    $students = get_users(array('role' => 'bm_student', 'number' => 999));
    if (empty($students)) return array('csv' => '', 'count' => 0);
    
    $user_fields = get_option('bm_user_dynamic_fields', array());
    
    $headers = array('Nome', 'E-mail', 'Status de Aprovação');
    foreach ($user_fields as $field_name => $info) {
        $headers[] = $field_name;
    }
    $headers[] = 'Bloqueado (multa ativa)';
    
    $output = fopen('php://temp', 'r+');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');
    
    foreach ($students as $student) {
        $row = array();
        $row[] = $student->display_name;
        $row[] = $student->user_email;
        $row[] = get_user_meta($student->ID, 'bm_approval_status', true) ?: 'approved';
        
        foreach ($user_fields as $field_name => $info) {
            $meta_key = '_bm_user_' . sanitize_key($field_name);
            $row[] = get_user_meta($student->ID, $meta_key, true);
        }
        
        $blocked = get_user_meta($student->ID, '_bm_penalty_active', true) === '1' ? 'Sim' : 'Não';
        $row[] = $blocked;
        
        fputcsv($output, $row, ';');
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return array('csv' => $csv, 'count' => count($students));
}

function bm_export_loans_full() {
    $books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    $all_records = array();
    
    foreach ($books as $book) {
        $book_title = $book->post_title;
        $book_author = get_post_meta($book->ID, '_bm_author', true);
        
        $reservations = get_post_meta($book->ID, '_bm_reservations', true);
        if (is_array($reservations)) {
            foreach ($reservations as $r) {
                $user = get_userdata($r['user_id']);
                if (!$user) continue;
                
                $status_labels = array(
                    'waiting' => __('Reservado', 'book-manager'),
                    'active' => __('Emprestado', 'book-manager'),
                    'returned' => __('Devolvido', 'book-manager'),
                    'rejected' => __('Rejeitado', 'book-manager'),
                    'cancelled' => __('Cancelado', 'book-manager'),
                );
                $status = isset($status_labels[$r['status']]) ? $status_labels[$r['status']] : $r['status'];
                
                $days_late = '';
                if ($r['status'] === 'returned' && isset($r['due_date']) && isset($r['returned_date'])) {
                    $due_time = strtotime($r['due_date']);
                    $return_time = strtotime($r['returned_date']);
                    if ($return_time > $due_time) {
                        $days_late = ceil(($return_time - $due_time) / DAY_IN_SECONDS);
                    }
                }
                
                $penalties = get_user_meta($r['user_id'], '_bm_penalties', true) ?: array();
                $has_penalty = 'Não';
                foreach ($penalties as $p) {
                    if (isset($p['note']) && strpos($p['note'], (string)$book->ID) !== false) {
                        $has_penalty = 'Sim';
                        break;
                    }
                }
                
                $all_records[] = array(
                    'student_name' => $user->display_name,
                    'student_email' => $user->user_email,
                    'book_title' => $book_title,
                    'book_author' => $book_author,
                    'status' => $status,
                    'reservation_date' => isset($r['date']) ? $r['date'] : '',
                    'loan_date' => isset($r['loan_date']) ? $r['loan_date'] : '',
                    'due_date' => isset($r['due_date']) ? $r['due_date'] : '',
                    'returned_date' => isset($r['returned_date']) ? $r['returned_date'] : '',
                    'type' => 'Normal',
                    'days_late' => $days_late,
                    'penalty' => $has_penalty,
                );
            }
        }
        
        $bulk = get_post_meta($book->ID, '_bm_bulk_reservation', true);
        if (is_array($bulk)) {
            foreach ($bulk as $br) {
                $teacher = get_userdata($br['teacher_id']);
                $student = !empty($br['student_id']) ? get_userdata($br['student_id']) : null;
                $user = $student ?: $teacher;
                if (!$user) continue;
                
                $status_labels = array(
                    'active' => __('Agendado', 'book-manager'),
                    'separated' => __('Separado', 'book-manager'),
                    'completed' => __('Concluído', 'book-manager'),
                    'cancelled' => __('Cancelado', 'book-manager'),
                );
                $status = isset($status_labels[$br['status']]) ? $status_labels[$br['status']] : $br['status'];
                
                $all_records[] = array(
                    'student_name' => $user->display_name,
                    'student_email' => $user->user_email,
                    'book_title' => $book_title,
                    'book_author' => $book_author,
                    'status' => $status,
                    'reservation_date' => isset($br['created_at']) ? $br['created_at'] : '',
                    'loan_date' => isset($br['start_date']) ? $br['start_date'] : '',
                    'due_date' => isset($br['end_date']) ? $br['end_date'] : '',
                    'returned_date' => '',
                    'type' => 'Agendamento',
                    'days_late' => '',
                    'penalty' => 'Não',
                );
            }
        }
    }
    
    if (empty($all_records)) return array('csv' => '', 'count' => 0);
    
    $headers = array(
        'Nome do aluno', 'E-mail do aluno', 'Título do livro', 'Autor do livro',
        'Status', 'Data da reserva', 'Data do empréstimo', 'Devolução prevista',
        'Devolução real', 'Tipo', 'Dias em atraso', 'Penalidade aplicada'
    );
    
    $output = fopen('php://temp', 'r+');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');
    
    foreach ($all_records as $record) {
        fputcsv($output, array(
            $record['student_name'], $record['student_email'], $record['book_title'], $record['book_author'],
            $record['status'], $record['reservation_date'], $record['loan_date'], $record['due_date'],
            $record['returned_date'], $record['type'], $record['days_late'], $record['penalty'],
        ), ';');
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return array('csv' => $csv, 'count' => count($all_records));
}

function bm_export_taxonomies_full() {
    $taxonomies = array('bm_genre', 'bm_category', 'bm_discipline');
    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($dynamic_taxonomies)) $dynamic_taxonomies = array();
    foreach ($dynamic_taxonomies as $slug => $info) {
        $taxonomies[] = $slug;
    }
    
    $all_terms = array();
    foreach ($taxonomies as $taxonomy) {
        $taxonomy_obj = get_taxonomy($taxonomy);
        if (!$taxonomy_obj) continue;
        $taxonomy_label = $taxonomy_obj->label;
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
        if (is_wp_error($terms) || empty($terms)) continue;
        foreach ($terms as $term) {
            $parent_name = '';
            if ($term->parent > 0) {
                $parent_term = get_term($term->parent, $taxonomy);
                if ($parent_term && !is_wp_error($parent_term)) {
                    $parent_name = $parent_term->name;
                }
            }
            $all_terms[] = array(
                'taxonomy' => $taxonomy_label,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => $parent_name,
                'description' => $term->description,
                'count' => $term->count,
            );
        }
    }
    
    if (empty($all_terms)) return array('csv' => '', 'count' => 0);
    
    $headers = array('Taxonomia', 'Termo', 'Slug', 'Termo Pai', 'Descrição', 'Livros');
    $output = fopen('php://temp', 'r+');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');
    foreach ($all_terms as $term) {
        fputcsv($output, array($term['taxonomy'], $term['name'], $term['slug'], $term['parent'], $term['description'], $term['count']), ';');
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return array('csv' => $csv, 'count' => count($all_terms));
}

function bm_export_readings_full() {
    $users = get_users(array('role__in' => array('bm_student', 'bm_teacher'), 'number' => 999));
    $all_readings = array();
    
    foreach ($users as $user) {
        $reading_log = get_user_meta($user->ID, '_bm_reading_log', true);
        if (!is_array($reading_log) || empty($reading_log)) continue;
        
        foreach ($reading_log as $log) {
            $book_title = get_the_title($log['book_id']);
            if (empty($book_title)) continue;
            
            $status_labels = array('approved' => __('Aprovada', 'book-manager'), 'pending' => __('Pendente', 'book-manager'), 'rejected' => __('Rejeitada', 'book-manager'));
            $status = isset($status_labels[$log['status']]) ? $status_labels[$log['status']] : $log['status'];
            
            $all_readings[] = array(
                'student_name' => $user->display_name,
                'student_email' => $user->user_email,
                'book_title' => $book_title,
                'rating' => isset($log['rating']) ? $log['rating'] : 0,
                'review' => isset($log['review']) ? $log['review'] : '',
                'video_url' => isset($log['video_url']) ? $log['video_url'] : '',
                'date' => isset($log['date']) ? $log['date'] : '',
                'status' => $status,
                'featured' => isset($log['featured']) && $log['featured'] ? 'Sim' : 'Não',
            );
        }
    }
    
    if (empty($all_readings)) return array('csv' => '', 'count' => 0);
    
    $headers = array('Nome do aluno', 'E-mail do aluno', 'Título do livro', 'Nota', 'Resenha', 'Vídeo', 'Data', 'Status', 'Destaque');
    $output = fopen('php://temp', 'r+');
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');
    foreach ($all_readings as $reading) {
        $rating_display = $reading['rating'] > 0 ? str_repeat('★', $reading['rating']) . str_repeat('☆', 5 - $reading['rating']) : '';
        fputcsv($output, array($reading['student_name'], $reading['student_email'], $reading['book_title'], $rating_display, $reading['review'], $reading['video_url'], $reading['date'], $reading['status'], $reading['featured']), ';');
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return array('csv' => $csv, 'count' => count($all_readings));
}

function bm_export_settings_full() {
    $settings = array(
        'plugin' => 'book-manager',
        'version' => '8.1.1',
        'exported_at' => current_time('mysql'),
        'aviso_seguranca' => __('ATENÇÃO: As chaves de API (Google Books, Groq, YouTube) NÃO foram exportadas por segurança. Ao importar este arquivo, você precisará configurar novas chaves de API manualmente em Biblioteca → Configurações → APIs.', 'book-manager'),
        'settings' => array(),
    );
    
    $bm_settings = get_option('bm_settings', array());
    if (!empty($bm_settings)) {
        $settings['settings']['bm_settings'] = $bm_settings;
    }
    
    $white_label = get_option('bm_white_label', array());
    if (!empty($white_label)) {
        $settings['settings']['bm_white_label'] = $white_label;
    }
    
    $penalty_rules = get_option('bm_penalty_rules', array());
    if (!empty($penalty_rules)) {
        $settings['settings']['bm_penalty_rules'] = $penalty_rules;
    }
    
    $api_settings = get_option('bm_api_settings', array());
    if (!empty($api_settings)) {
        $safe_api = array();
        $safe_api['google_books'] = !empty($api_settings['google_books_key']) ? 'configurada' : 'não configurada';
        $safe_api['groq'] = !empty($api_settings['groq_key']) ? 'configurada' : 'não configurada';
        $safe_api['groq_active'] = isset($api_settings['groq_active']) ? $api_settings['groq_active'] : '0';
        $safe_api['youtube'] = !empty($api_settings['youtube_key']) ? 'configurada' : 'não configurada';
        $safe_api['chatbot_active'] = isset($api_settings['chatbot_active']) ? $api_settings['chatbot_active'] : '1';
        if (!empty($api_settings['groq_persona'])) {
            $safe_api['groq_persona'] = $api_settings['groq_persona'];
        }
        $settings['settings']['bm_api_settings'] = $safe_api;
    }
    
    $dynamic_fields = get_option('bm_dynamic_fields', array());
    if (!empty($dynamic_fields)) {
        $settings['settings']['bm_dynamic_fields'] = $dynamic_fields;
    }
    
    $user_dynamic_fields = get_option('bm_user_dynamic_fields', array());
    if (!empty($user_dynamic_fields)) {
        $settings['settings']['bm_user_dynamic_fields'] = $user_dynamic_fields;
    }
    
    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!empty($dynamic_taxonomies)) {
        $settings['settings']['bm_dynamic_taxonomies'] = $dynamic_taxonomies;
    }
    
    $field_order = get_option('bm_field_order', array());
    if (!empty($field_order)) {
        $settings['settings']['bm_field_order'] = $field_order;
    }
    $user_field_order = get_option('bm_user_field_order', array());
    if (!empty($user_field_order)) {
        $settings['settings']['bm_user_field_order'] = $user_field_order;
    }
    
    $field_visibility = get_option('bm_field_visibility', array());
    if (!empty($field_visibility)) {
        $settings['settings']['bm_field_visibility'] = $field_visibility;
    }
    $user_field_visibility = get_option('bm_user_field_visibility', array());
    if (!empty($user_field_visibility)) {
        $settings['settings']['bm_user_field_visibility'] = $user_field_visibility;
    }
    
    $year_transition = get_option('bm_year_transition', array());
    if (!empty($year_transition)) {
        $settings['settings']['bm_year_transition'] = $year_transition;
    }
    
    $recadastro = get_option('bm_recadastro_required', '0');
    $settings['settings']['bm_recadastro_required'] = $recadastro;
    
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $count = count($settings['settings']);
    
    return array('csv' => $json, 'count' => $count);
}

function bm_handle_export_all() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!isset($_POST['bm_export_all_submit']) || !isset($_POST['bm_export_all_nonce'])) return;
    if (!wp_verify_nonce($_POST['bm_export_all_nonce'], 'bm_export_all_action')) return;
    
    $modules = isset($_POST['bm_export_modules']) ? array_map('sanitize_text_field', $_POST['bm_export_modules']) : array();
    $format = isset($_POST['bm_export_format']) ? sanitize_text_field($_POST['bm_export_format']) : 'zip';
    
    if (empty($modules)) return;
    
    $module_map = array(
        'books' => array('func' => 'bm_export_books_full', 'name' => 'livros', 'ext' => 'csv'),
        'students' => array('func' => 'bm_export_students_full', 'name' => 'alunos', 'ext' => 'csv'),
        'loans' => array('func' => 'bm_export_loans_full', 'name' => 'historico_circulacao', 'ext' => 'csv'),
        'readings' => array('func' => 'bm_export_readings_full', 'name' => 'fichas_leitura', 'ext' => 'csv'),
        'taxonomies' => array('func' => 'bm_export_taxonomies_full', 'name' => 'taxonomias', 'ext' => 'csv'),
        'settings' => array('func' => 'bm_export_settings_full', 'name' => 'configuracoes_biblioteca', 'ext' => 'json'),
    );
    
    if ($format === 'csv' && count($modules) === 1) {
        $module = $modules[0];
        if (!isset($module_map[$module])) return;
        
        $info = $module_map[$module];
        $result = call_user_func($info['func']);
        $content = $result['csv'];
        $count = $result['count'];
        
        if (empty($content) || $count === 0) {
            $error_messages = array(
                'books' => __('Nenhum livro encontrado para exportar.', 'book-manager'),
                'students' => __('Nenhum aluno encontrado para exportar.', 'book-manager'),
                'loans' => __('Nenhum registro de circulação encontrado para exportar.', 'book-manager'),
                'readings' => __('Nenhuma ficha de leitura encontrada para exportar.', 'book-manager'),
                'taxonomies' => __('Nenhuma taxonomia encontrada para exportar.', 'book-manager'),
                'settings' => __('Nenhuma configuração encontrada para exportar.', 'book-manager'),
            );
            $error_msg = isset($error_messages[$module]) ? $error_messages[$module] : __('Nenhum dado encontrado.', 'book-manager');
            set_transient('bm_export_all_message', array('type' => 'error', 'text' => $error_msg), 60);
            wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'export'), admin_url('edit.php')));
            exit;
        }
        
        $upload_dir = wp_upload_dir();
        $filename = $info['name'] . '_' . date('Y-m-d_His') . '.' . $info['ext'];
        $filepath = $upload_dir['path'] . '/' . $filename;
        $fileurl = $upload_dir['url'] . '/' . $filename;
        file_put_contents($filepath, $content);
        
        $success_messages = array(
            'books' => sprintf(__('✅ %d livros exportados com sucesso!', 'book-manager'), $count),
            'students' => sprintf(__('✅ %d alunos exportados com sucesso!', 'book-manager'), $count),
            'loans' => sprintf(__('✅ %d registros de circulação exportados com sucesso!', 'book-manager'), $count),
            'readings' => sprintf(__('✅ %d fichas de leitura exportadas com sucesso!', 'book-manager'), $count),
            'taxonomies' => sprintf(__('✅ %d termos de taxonomias exportados com sucesso!', 'book-manager'), $count),
            'settings' => sprintf(__('✅ Configurações exportadas com sucesso! (%d itens)', 'book-manager'), $count),
        );
        $message = isset($success_messages[$module]) ? $success_messages[$module] : __('Exportado com sucesso!', 'book-manager');
        
        if ($module === 'settings') {
            $message .= '<br><small style="color:#f0ad4e;">⚠️ ' . __('As chaves de API NÃO foram incluídas. Você precisará configurá-las manualmente ao importar.', 'book-manager') . '</small>';
        }
        $message .= ' <a href="' . esc_url($fileurl) . '" class="button button-primary" style="margin-left:10px;">📥 ' . __('Baixar arquivo', 'book-manager') . '</a>';
        set_transient('bm_export_all_message', array('type' => 'success', 'text' => $message), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'export'), admin_url('edit.php')));
        exit;
    }
    
    $files = array();
    $total_counts = array();
    
    foreach ($modules as $module) {
        if (!isset($module_map[$module])) continue;
        $info = $module_map[$module];
        $result = call_user_func($info['func']);
        $content = $result['csv'];
        $count = $result['count'];
        
        if (empty($content) || $count === 0) continue;
        
        $filename = $info['name'] . '.' . $info['ext'];
        $files[$filename] = $content;
        $total_counts[$info['name']] = $count;
    }
    
    if (empty($files)) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Nenhum dado encontrado nos módulos selecionados.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'export'), admin_url('edit.php')));
        exit;
    }
    
    $upload_dir = wp_upload_dir();
    $zip_filename = 'backup_biblioteca_' . date('Y-m-d_His') . '.zip';
    $zip_filepath = $upload_dir['path'] . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_filepath, ZipArchive::CREATE) !== true) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Erro ao criar arquivo ZIP.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'export'), admin_url('edit.php')));
        exit;
    }
    
    foreach ($files as $filename => $content) {
        $zip->addFromString($filename, $content);
    }
    $zip->close();
    
    $zip_fileurl = $upload_dir['url'] . '/' . $zip_filename;
    
    $count_parts = array();
    foreach ($total_counts as $name => $count) {
        $labels = array(
            'livros' => __('livros', 'book-manager'),
            'alunos' => __('alunos', 'book-manager'),
            'historico_circulacao' => __('registros de circulação', 'book-manager'),
            'fichas_leitura' => __('fichas de leitura', 'book-manager'),
            'taxonomias' => __('termos de taxonomias', 'book-manager'),
            'configuracoes_biblioteca' => __('configurações', 'book-manager'),
        );
        $label = isset($labels[$name]) ? $labels[$name] : $name;
        $count_parts[] = $count . ' ' . $label;
    }
    
    $message = '✅ ' . __('Backup exportado com sucesso!', 'book-manager') . ' ' . implode(', ', $count_parts) . '.';
    $message .= '<br><small style="color:#f0ad4e;">⚠️ ' . __('As chaves de API NÃO foram incluídas no arquivo de configurações.', 'book-manager') . '</small>';
    $message .= ' <a href="' . esc_url($zip_fileurl) . '" class="button button-primary" style="margin-left:10px;">📥 ' . __('Baixar arquivo ZIP', 'book-manager') . '</a>';
    set_transient('bm_export_all_message', array('type' => 'success', 'text' => $message), 60);
    wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'export'), admin_url('edit.php')));
    exit;
}
add_action('admin_init', 'bm_handle_export_all', 20);

function bm_handle_import_all() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!isset($_POST['bm_import_submit']) || !isset($_POST['bm_import_all_nonce'])) return;
    if (!wp_verify_nonce($_POST['bm_import_all_nonce'], 'bm_import_all_action')) return;
    
    if (empty($_FILES['bm_import_file']['tmp_name'])) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Nenhum arquivo enviado.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    $file = $_FILES['bm_import_file'];
    $filetype = wp_check_filetype($file['name']);
    if ($filetype['ext'] !== 'zip') {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Formato inválido. Envie um arquivo .zip.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Erro ao abrir o arquivo ZIP.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    $module_map = array(
        'livros.csv' => array('label' => '📚 ' . __('Livros', 'book-manager'), 'module' => 'books'),
        'alunos.csv' => array('label' => '👥 ' . __('Alunos', 'book-manager'), 'module' => 'students'),
        'historico_circulacao.csv' => array('label' => '📋 ' . __('Histórico de Circulação', 'book-manager'), 'module' => 'loans'),
        'fichas_leitura.csv' => array('label' => '📝 ' . __('Fichas de Leitura', 'book-manager'), 'module' => 'readings'),
        'taxonomias.csv' => array('label' => '🏷️ ' . __('Taxonomias', 'book-manager'), 'module' => 'taxonomies'),
        'configuracoes_biblioteca.json' => array('label' => '⚙️ ' . __('Configurações', 'book-manager'), 'module' => 'settings'),
    );
    
    $preview = array();
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        if (!isset($module_map[$filename])) continue;
        
        $info = $module_map[$filename];
        $content = $zip->getFromIndex($i);
        if (empty($content)) continue;
        
        $rows = array();
        $lines = explode("\n", trim($content));
        $header_line = array_shift($lines);
        $headers = str_getcsv($header_line, ';');
        $row_count = 0;
        $preview_rows = array();
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row_count++;
            if ($row_count <= 5) {
                $preview_rows[] = str_getcsv($line, ';');
            }
        }
        
        $preview[] = array(
            'label' => $info['label'],
            'filename' => $filename,
            'count' => $row_count,
            'preview_headers' => $headers,
            'preview_rows' => $preview_rows,
            'module' => $info['module'],
        );
    }
    
    $zip->close();
    
    if (empty($preview)) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Nenhum módulo reconhecido no arquivo ZIP.', 'book-manager')), 60);
    } else {
        set_transient('bm_import_preview', $preview, 300);
    }
    
    wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
    exit;
}
add_action('admin_init', 'bm_handle_import_all');

function bm_execute_import() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!isset($_POST['bm_import_execute_submit']) || !isset($_POST['bm_import_execute_nonce'])) return;
    if (!wp_verify_nonce($_POST['bm_import_execute_nonce'], 'bm_import_execute_action')) return;
    
    $import_preview = get_transient('bm_import_preview');
    if (!$import_preview) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Nenhum dado para importar. Envie o arquivo novamente.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    $mode = isset($_POST['bm_import_mode']) ? sanitize_text_field($_POST['bm_import_mode']) : 'add';
    $report = array();
    
    foreach ($import_preview as $module) {
        $module_type = $module['module'];
        $module_result = array(
            'label' => $module['label'],
            'imported' => array(),
            'duplicates' => array(),
            'errors' => array(),
        );
        
        $content = isset($module['raw_content']) ? $module['raw_content'] : '';
        
        if (empty($content) && !empty($module['filepath'])) {
            $content = file_get_contents($module['filepath']);
        }
        
        if (empty($content)) {
            $module_result['errors'][] = array('item' => $module['label'], 'reason' => __('Arquivo vazio ou não encontrado.', 'book-manager'));
            $report[] = $module_result;
            continue;
        }
        
        $lines = explode("\n", trim($content));
        $header_line = array_shift($lines);
        $headers = str_getcsv($header_line, ';');
        
        foreach ($lines as $line_num => $line) {
            if (empty(trim($line))) continue;
            $data = str_getcsv($line, ';');
            
            switch ($module_type) {
                case 'books':
                    $title = isset($data[0]) ? sanitize_text_field($data[0]) : '';
                    $author = isset($data[1]) ? sanitize_text_field($data[1]) : '';
                    $publisher = isset($data[2]) ? sanitize_text_field($data[2]) : '';
                    
                    if (empty($title)) {
                        $module_result['errors'][] = array('item' => __('Linha', 'book-manager') . ' ' . ($line_num + 2), 'reason' => __('Título vazio.', 'book-manager'));
                        continue;
                    }
                    
                    $existing_id = bm_find_duplicate_book($title, $author, $publisher);
                    
                    if ($existing_id && $mode === 'add') {
                        $module_result['duplicates'][] = array('item' => $title . ($author ? ' — ' . $author : ''), 'reason' => __('Já existe no acervo.', 'book-manager'));
                        continue;
                    }
                    
                    $post_id = $existing_id ? $existing_id : wp_insert_post(array('post_type' => 'bm_book', 'post_title' => $title, 'post_status' => 'publish'));
                    
                    if ($post_id && !is_wp_error($post_id)) {
                        if (!empty($data[1])) update_post_meta($post_id, '_bm_author', $author);
                        if (!empty($data[2])) update_post_meta($post_id, '_bm_publisher', $publisher);
                        if (!empty($data[3])) update_post_meta($post_id, '_bm_isbn', sanitize_text_field($data[3]));
                        if (!empty($data[4])) update_post_meta($post_id, '_bm_location', sanitize_text_field($data[4]));
                        if (!empty($data[5])) update_post_meta($post_id, '_bm_copies', absint($data[5]));
                        $module_result['imported'][] = array('item' => $title . ($author ? ' — ' . $author : ''));
                    } else {
                        $module_result['errors'][] = array('item' => $title, 'reason' => __('Erro ao salvar.', 'book-manager'));
                    }
                    break;
                    
                case 'students':
                    $student_name = isset($data[0]) ? sanitize_text_field($data[0]) : '';
                    $student_email = isset($data[1]) ? sanitize_email($data[1]) : '';
                    
                    if (empty($student_name) || empty($student_email)) {
                        $module_result['errors'][] = array('item' => $student_name ?: __('Linha', 'book-manager') . ' ' . ($line_num + 2), 'reason' => __('Nome ou e-mail vazio.', 'book-manager'));
                        continue;
                    }
                    
                    $existing_email = email_exists($student_email);
                    
                    if ($existing_email && $mode === 'add') {
                        $module_result['duplicates'][] = array('item' => $student_name . ' (' . $student_email . ')', 'reason' => __('E-mail já cadastrado.', 'book-manager'));
                        continue;
                    }
                    
                    $user_id = $existing_email ? $existing_email : wp_insert_user(array(
                        'user_login' => sanitize_user($student_email),
                        'user_email' => $student_email,
                        'display_name' => $student_name,
                        'user_pass' => wp_generate_password(12, false),
                        'role' => 'bm_student',
                    ));
                    
                    if ($user_id && !is_wp_error($user_id)) {
                        update_user_meta($user_id, 'bm_approval_status', 'approved');
                        update_user_meta($user_id, '_bm_user_' . sanitize_key('Nome completo'), $student_name);
                        update_user_meta($user_id, '_bm_user_' . sanitize_key('E-mail'), $student_email);
                        $module_result['imported'][] = array('item' => $student_name . ' (' . $student_email . ')');
                    } else {
                        $error_msg = is_wp_error($user_id) ? $user_id->get_error_message() : __('Erro ao salvar.', 'book-manager');
                        $module_result['errors'][] = array('item' => $student_name, 'reason' => $error_msg);
                    }
                    break;
                    
                case 'loans':
                case 'readings':
                case 'taxonomies':
                    $module_result['errors'][] = array('item' => $module['label'], 'reason' => __('Importação deste módulo será implementada em breve.', 'book-manager'));
                    break;
                    
                case 'settings':
                    $module_result['errors'][] = array('item' => $module['label'], 'reason' => __('Use a importação individual de JSON para configurações.', 'book-manager'));
                    break;
            }
        }
        
        $report[] = $module_result;
    }
    
    set_transient('bm_import_report', $report, 300);
    delete_transient('bm_import_preview');
    wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
    exit;
}
add_action('admin_init', 'bm_execute_import');

function bm_handle_import_single() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!isset($_POST['bm_import_single_submit']) || !isset($_POST['bm_import_single_nonce'])) return;
    if (!wp_verify_nonce($_POST['bm_import_single_nonce'], 'bm_import_single_action')) return;
    
    if (empty($_FILES['bm_import_single_file']['tmp_name'])) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Nenhum arquivo enviado.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    $file = $_FILES['bm_import_single_file'];
    $user_type = isset($_POST['bm_import_single_type']) ? sanitize_text_field($_POST['bm_import_single_type']) : '';
    $standard_mode = false;
    
    $module_labels = array(
        'books' => '📚 ' . __('Livros', 'book-manager'),
        'students' => '👥 ' . __('Alunos', 'book-manager'),
        'loans' => '📋 ' . __('Histórico de Circulação', 'book-manager'),
        'readings' => '📝 ' . __('Fichas de Leitura', 'book-manager'),
        'taxonomies' => '🏷️ ' . __('Taxonomias', 'book-manager'),
        'settings' => '⚙️ ' . __('Configurações', 'book-manager'),
    );
    
    $filename = $file['name'];
    $file_patterns = array(
        'books' => array('livros_', 'livros.'),
        'students' => array('alunos_', 'alunos.'),
        'loans' => array('historico_circulacao_', 'historico_circulacao.'),
        'readings' => array('fichas_leitura_', 'fichas_leitura.'),
        'taxonomies' => array('taxonomias_', 'taxonomias.'),
        'settings' => array('configuracoes_biblioteca_', 'configuracoes_biblioteca.'),
    );
    
    $detected_type = '';
    foreach ($file_patterns as $module => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($filename, $pattern) !== false) {
                $detected_type = $module;
                break 2;
            }
        }
    }
    
    if (!empty($detected_type)) {
        $type = $detected_type;
        $standard_mode = true;
    } else {
        $type = $user_type;
    }
    
    if (!isset($module_labels[$type])) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Tipo de módulo inválido.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    $content = file_get_contents($file['tmp_name']);
    if (empty($content)) {
        set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('Arquivo vazio.', 'book-manager')), 60);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    if ($type === 'settings') {
        $json_data = json_decode($content, true);
        if (!$json_data) {
            set_transient('bm_export_all_message', array('type' => 'error', 'text' => __('JSON inválido.', 'book-manager')), 60);
            wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
            exit;
        }
        $count = isset($json_data['settings']) ? count($json_data['settings']) : 0;
        $preview[] = array(
            'label' => $module_labels[$type],
            'filename' => $filename,
            'count' => $count,
            'preview_headers' => array(__('Chave', 'book-manager'), __('Valor', 'book-manager')),
            'preview_rows' => array_slice(array_map(function($k, $v) { return array($k, is_array($v) ? __('(array)', 'book-manager') : (string)$v); }, array_keys($json_data['settings'] ?? $json_data), $json_data['settings'] ?? $json_data), 0, 5),
            'module' => $type,
            'standard_mode' => $standard_mode,
        );
        set_transient('bm_import_preview', $preview, 300);
        wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
        exit;
    }
    
    $rows = array();
    $lines = explode("\n", trim($content));
    $header_line = array_shift($lines);
    $headers = str_getcsv($header_line, ';');
    $row_count = 0;
    $preview_rows = array();
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $row_count++;
        if ($row_count <= 5) {
            $preview_rows[] = str_getcsv($line, ';');
        }
    }
    
    $preview[] = array(
        'label' => $module_labels[$type],
        'filename' => $filename,
        'count' => $row_count,
        'preview_headers' => $headers,
        'preview_rows' => $preview_rows,
        'module' => $type,
        'standard_mode' => $standard_mode,
    );
    
    set_transient('bm_import_preview', $preview, 300);
    wp_redirect(add_query_arg(array('post_type' => 'bm_book', 'page' => 'bm_data_io', 'tab' => 'export_import_all', 'subtab' => 'import'), admin_url('edit.php')));
    exit;
}
add_action('admin_init', 'bm_handle_import_single');