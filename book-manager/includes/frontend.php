<?php
/**
 * Book Manager — Módulo Frontend
 * Templates, vitrine, filtros, capas, sinopse, IA
 */

defined('ABSPATH') || exit;

// ==========================================
// FASE 8B: FORÇAR TEMPLATES DO PLUGIN (SINGLE E ARCHIVE)
// ==========================================
function bm_force_templates($template) {
    if (is_singular('bm_book')) {
        $plugin_template = plugin_dir_path(__FILE__) . '../single-bm_book.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    if (is_post_type_archive('bm_book')) {
        $plugin_template = plugin_dir_path(__FILE__) . '../archive-bm_book.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}
add_filter('template_include', 'bm_force_templates', 99);

// ==========================================
// FASE 8D: FILTROS INTELIGENTES NO FRONT-END
// ==========================================
function bm_filter_books_frontend($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('bm_book')) return;

    if (isset($_GET['bm_genre']) && !empty($_GET['bm_genre']) && $_GET['bm_genre'] !== '0') {
        $tax_query = $query->get('tax_query') ?: array();
        $tax_query[] = array(
            'taxonomy' => 'bm_genre',
            'field' => 'term_id',
            'terms' => intval($_GET['bm_genre']),
        );
        $query->set('tax_query', $tax_query);
    }

    if (isset($_GET['bm_category']) && !empty($_GET['bm_category']) && $_GET['bm_category'] !== '0') {
        $tax_query = $query->get('tax_query') ?: array();
        $tax_query[] = array(
            'taxonomy' => 'bm_category',
            'field' => 'term_id',
            'terms' => intval($_GET['bm_category']),
        );
        $query->set('tax_query', $tax_query);
    }

    if (isset($_GET['bm_search']) && !empty($_GET['bm_search'])) {
        $search = sanitize_text_field($_GET['bm_search']);
        $query->set('s', $search);
    }
}
add_action('pre_get_posts', 'bm_filter_books_frontend');

// ==========================================
// FASE 8E: HOOK PARA CARROSSEL FUTURO (MAIS LIDOS)
// ==========================================
function bm_after_catalog_grid() {
    do_action('bm_after_catalog_grid');
}

// ==========================================
// FASE 7D: BUSCA DE CAPA VIA GOOGLE BOOKS API (NÚCLEO COMUM)
// FASE 8C-B: UNIFICADA
// FASE 8E: RESOLUÇÃO DE CAPA ALTERADA PARA ZOOM=2
// ==========================================
function bm_google_books_search($title, $author, $publisher, $isbn = '') {
    $queries = array();
    if (!empty($isbn)) { $c = preg_replace('/[^0-9]/', '', $isbn); if (strlen($c) >= 10) $queries[] = 'isbn:' . $c; }
    if (!empty($title) && !empty($author) && !empty($publisher)) $queries[] = $title . ' ' . $author . ' ' . $publisher;
    if (!empty($title) && !empty($author)) $queries[] = $title . ' ' . $author;
    if (!empty($title) && !empty($publisher)) $queries[] = $title . ' ' . $publisher;
    if (!empty($title)) $queries[] = $title;
    if (empty($queries)) return false;

    $st = mb_strtolower(trim($title));
    $sa = mb_strtolower(trim($author));

    foreach ($queries as $query) {
        $url = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($query) . '&key=' . BM_GOOGLE_BOOKS_API_KEY;
        $r = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($r)) continue;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($body['items'])) continue;

        $hc = false;
        foreach ($body['items'] as $item) {
            if (isset($item['volumeInfo']['imageLinks']['thumbnail'])) { $hc = true; break; }
        }
        if (!$hc) continue;

        $best = null;
        foreach ($body['items'] as $item) {
            $it = isset($item['volumeInfo']['title']) ? mb_strtolower(trim($item['volumeInfo']['title'])) : '';
            if (!isset($item['volumeInfo']['imageLinks']['thumbnail'])) continue;
            if ($it === $st) {
                $ia = isset($item['volumeInfo']['authors']) ? mb_strtolower(implode(' ', $item['volumeInfo']['authors'])) : '';
                if (empty($sa) || strpos($ia, $sa) !== false) { $best = $item; break; }
                if (!$best) $best = $item;
            }
            if (strpos($it, $st) !== false && !$best) {
                $ia = isset($item['volumeInfo']['authors']) ? mb_strtolower(implode(' ', $item['volumeInfo']['authors'])) : '';
                if (empty($sa) || strpos($ia, $sa) !== false) $best = $item;
            }
        }
        if (!$best) {
            foreach ($body['items'] as $item) {
                if (isset($item['volumeInfo']['imageLinks']['thumbnail'])) { $best = $item; break; }
            }
        }
        if ($best && isset($best['volumeInfo']['imageLinks']['thumbnail'])) {
            $mt = mb_strtolower(trim($best['volumeInfo']['title']));
            similar_text($st, $mt, $pct);
            $min = (mb_strlen($st) < 10) ? 30 : 50;
            if ($pct >= $min || strpos($mt, $st) !== false || strpos($st, $mt) !== false) {
                $thumb = str_replace('http://', 'https://', $best['volumeInfo']['imageLinks']['thumbnail']);
                $thumb = str_replace('&zoom=1', '&zoom=2', $thumb);
                if (strpos($thumb, '&zoom=') === false) {
                    $thumb .= '&zoom=2';
                }
                return $thumb;
            }
        }
    }
    return false;
}

function bm_fetch_cover_from_google($title, $author, $publisher, $isbn = '') {
    return bm_google_books_search($title, $author, $publisher, $isbn);
}

// ==========================================
// FASE 7D: BUSCA DE CAPA VIA AJAX (BOTÃO NA EDIÇÃO)
// FASE 8C-B: CORREÇÃO DE SEGURANÇA — ADICIONADO NONCE
// ==========================================
function bm_search_book_cover() {
    if (!current_user_can('manage_options')) wp_die(__('Sem permissão.','book-manager'));
    check_ajax_referer('bm_search_cover', 'nonce');
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

// ==========================================
// FASE 7D: BOTÃO "BUSCAR CAPA" NA EDIÇÃO
// FASE 8C-B: CORREÇÃO — ENVIO DE NONCE NO AJAX
// ==========================================
function bm_add_cover_button() {
    global $post; if(!$post||'bm_book'!==$post->post_type) return;
    $nonce = wp_create_nonce('bm_search_cover');
    ?><script>jQuery(document).ready(function($){$('#bm_search_cover').on('click',function(){var b=$(this);b.prop('disabled',true).val('Buscando...');$.post(ajaxurl,{action:'bm_search_book_cover',nonce:'<?php echo $nonce; ?>',post_id:$('#post_ID').val(),isbn:$('#_bm_isbn').val(),title:$('#title').val(),author:$('#_bm_author').val(),publisher:$('#_bm_publisher').val()},function(r){alert(r);location.reload();});});});</script>
    <input type="button" id="bm_search_cover" class="button" value="<?php _e('Buscar Capa','book-manager'); ?>" /><?php
}
add_action('edit_form_after_title','bm_add_cover_button');

// ==========================================
// FASE 8F: BUSCA AUTOMÁTICA DE SINOPSE
// ==========================================
function bm_fetch_sinopse_from_google($title, $author, $isbn = '') {
    $queries = array();
    if (!empty($isbn)) { $c = preg_replace('/[^0-9]/', '', $isbn); if (strlen($c) >= 10) $queries[] = 'isbn:' . $c; }
    if (!empty($title) && !empty($author)) $queries[] = $title . ' ' . $author;
    if (!empty($title)) $queries[] = $title;
    if (empty($queries)) return '';

    $st = mb_strtolower(trim($title));

    foreach ($queries as $query) {
        $url = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($query) . '&key=' . BM_GOOGLE_BOOKS_API_KEY;
        $r = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($r)) continue;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($body['items'])) continue;

        $best = null;
        foreach ($body['items'] as $item) {
            $it = isset($item['volumeInfo']['title']) ? mb_strtolower(trim($item['volumeInfo']['title'])) : '';
            if ($it === $st) { $best = $item; break; }
            if (strpos($it, $st) !== false && !$best) $best = $item;
        }
        if (!$best) $best = $body['items'][0];

        if ($best && isset($best['volumeInfo']['description'])) {
            $mt = mb_strtolower(trim($best['volumeInfo']['title']));
            similar_text($st, $mt, $pct);
            $min = (mb_strlen($st) < 10) ? 30 : 50;
            if ($pct >= $min || strpos($mt, $st) !== false || strpos($st, $mt) !== false) {
                return wp_kses_post($best['volumeInfo']['description']);
            }
        }
    }
    return '';
}

function bm_ajax_fetch_sinopse() {
    if (!current_user_can('manage_options')) wp_die(__('Sem permissão.','book-manager'));
    check_ajax_referer('bm_sinopse_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $author = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';
    $isbn = isset($_POST['isbn']) ? sanitize_text_field($_POST['isbn']) : '';

    if (empty($title)) wp_die(__('Preencha o título.','book-manager'));

    $sinopse = bm_fetch_sinopse_from_google($title, $author, $isbn);
    if (empty($sinopse)) wp_die(__('Nenhuma sinopse encontrada.','book-manager'));

    $dynamic_fields = get_option('bm_dynamic_fields', array());
    if (!isset($dynamic_fields['Sinopse'])) {
        $dynamic_fields['Sinopse'] = array('type' => 'textarea');
        update_option('bm_dynamic_fields', $dynamic_fields);
    }
    update_post_meta($post_id, '_bm_dynamic_sinopse', $sinopse);
    wp_die(__('Sinopse salva com sucesso!','book-manager'));
}
add_action('wp_ajax_bm_fetch_sinopse', 'bm_ajax_fetch_sinopse');

function bm_add_sinopse_button() {
    global $post;
    if (!$post || 'bm_book' !== $post->post_type) return;
    $nonce = wp_create_nonce('bm_sinopse_nonce');
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#bm_fetch_sinopse').on('click', function() {
            var b = $(this);
            b.prop('disabled', true).val('Buscando...');
            $.post(ajaxurl, {
                action: 'bm_fetch_sinopse',
                nonce: '<?php echo $nonce; ?>',
                post_id: $('#post_ID').val(),
                title: $('#title').val(),
                author: $('#_bm_author').val(),
                isbn: $('#_bm_isbn').val()
            }, function(r) {
                alert(r);
                location.reload();
            });
        });
    });
    </script>
    <input type="button" id="bm_fetch_sinopse" class="button" value="<?php _e('Buscar Sinopse', 'book-manager'); ?>" style="margin-left:10px;" />
    <?php
}
add_action('edit_form_after_title', 'bm_add_sinopse_button');

// ==========================================
// FASE 8G: CLASSIFICAÇÃO INTERDISCIPLINAR POR IA
// ==========================================
function bm_classify_book_with_ai($post_id) {
    $title = get_the_title($post_id);
    $author = get_post_meta($post_id, '_bm_author', true);
    $sinopse = get_post_meta($post_id, '_bm_dynamic_sinopse', true);
    $genres = wp_get_post_terms($post_id, 'bm_genre', array('fields' => 'names'));
    $genre_list = implode(', ', $genres);

    $prompt = "Com base nas informações a seguir, sugira de 1 a 3 disciplinas escolares relacionadas a este livro. Responda APENAS com os nomes das disciplinas, separados por vírgula.\n\nTítulo: " . $title . "\nAutor: " . $author . "\nGênero: " . $genre_list . "\nSinopse: " . wp_strip_all_tags($sinopse);

    $api_key = defined('BM_GEMINI_API_KEY') ? BM_GEMINI_API_KEY : '';
    if (empty($api_key)) return false;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
    $body = json_encode(array(
        'contents' => array(
            array('parts' => array(array('text' => $prompt)))
        )
    ));

    $response = wp_remote_post($url, array(
        'timeout' => 30,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => $body,
    ));

    if (is_wp_error($response)) return false;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['candidates'][0]['content']['parts'][0]['text'])) return false;

    $disciplinas_text = trim($data['candidates'][0]['content']['parts'][0]['text']);
    $disciplinas = array_map('trim', explode(',', $disciplinas_text));

    $term_ids = array();
    foreach ($disciplinas as $disciplina) {
        if (empty($disciplina)) continue;
        $term = term_exists($disciplina, 'bm_discipline');
        if (!$term) {
            $term = wp_insert_term($disciplina, 'bm_discipline');
        }
        if (!is_wp_error($term)) {
            $term_ids[] = is_array($term) ? $term['term_id'] : $term;
        }
    }

    if (!empty($term_ids)) {
        wp_set_post_terms($post_id, $term_ids, 'bm_discipline');
        update_post_meta($post_id, '_bm_ai_classified', '1');
        return count($term_ids);
    }

    return false;
}

function bm_add_ai_classify_button() {
    global $post;
    if (!$post || 'bm_book' !== $post->post_type) return;
    $classified = get_post_meta($post->ID, '_bm_ai_classified', true);
    $label = $classified ? __('Reclassificar com IA', 'book-manager') : __('Classificar com IA', 'book-manager');
    $nonce = wp_create_nonce('bm_ai_classify_nonce');
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#bm_ai_classify').on('click', function() {
            var b = $(this);
            b.prop('disabled', true).val('Analisando...');
            $.post(ajaxurl, {
                action: 'bm_ai_classify',
                nonce: '<?php echo $nonce; ?>',
                post_id: $('#post_ID').val()
            }, function(r) {
                alert(r);
                location.reload();
            });
        });
    });
    </script>
    <input type="button" id="bm_ai_classify" class="button" value="<?php echo esc_attr($label); ?>" style="margin-left:10px;" />
    <?php
}
add_action('edit_form_after_title', 'bm_add_ai_classify_button');

function bm_ajax_ai_classify() {
    if (!current_user_can('manage_options')) wp_die(__('Sem permissão.', 'book-manager'));
    check_ajax_referer('bm_ai_classify_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_die(__('Livro inválido.', 'book-manager'));

    $count = bm_classify_book_with_ai($post_id);
    if ($count) {
        wp_die(sprintf(__('%d disciplina(s) atribuída(s)!', 'book-manager'), $count));
    } else {
        wp_die(__('Não foi possível classificar. Verifique a chave da API Gemini.', 'book-manager'));
    }
}
add_action('wp_ajax_bm_ai_classify', 'bm_ajax_ai_classify');