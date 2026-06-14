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

    $tax_query = array();

    $bm_genre = isset($_GET['bm_genre']) ? $_GET['bm_genre'] : '';
    if ($bm_genre !== '' && $bm_genre !== '0') {
        $tax_query[] = array(
            'taxonomy' => 'bm_genre',
            'field' => 'term_id',
            'terms' => intval($bm_genre),
        );
    }

    $bm_category = isset($_GET['bm_category']) ? $_GET['bm_category'] : '';
    if ($bm_category !== '' && $bm_category !== '0') {
        $tax_query[] = array(
            'taxonomy' => 'bm_category',
            'field' => 'term_id',
            'terms' => intval($bm_category),
        );
    }

    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }
    if (!empty($tax_query)) {
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
        $url = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($query) . '&key=' . bm_get_api_key('google_books');
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
    $settings = bm_get_settings();
    $cover_mode = isset($settings['cover_mode']) ? $settings['cover_mode'] : 'download';
    $cover_url = bm_google_books_search($title, $author, $publisher, $isbn);
    
    if (!$cover_url) return false;
    
    // Se hotlink, retorna a URL direta sem baixar
    if ($cover_mode === 'hotlink') {
        return array('mode' => 'hotlink', 'url' => $cover_url);
    }
    
    return $cover_url;
}

// ==========================================
// FASE 19: BUSCA COMPLETA DE DADOS VIA GOOGLE BOOKS API
// ==========================================



// ==========================================
// FASE 19: BUSCA DE VÍDEO NO YOUTUBE
// ==========================================
function bm_search_youtube_video($title, $author = '', $publisher = '') {
    $keys = bm_get_api_keys();
    $youtube_key = isset($keys['youtube_key']) ? $keys['youtube_key'] : '';
    if (empty($youtube_key)) return false;
    
    // Tentar na ordem: título + autor + editora → título + autor → título
    $queries = array();
    if (!empty($title) && !empty($author) && !empty($publisher)) $queries[] = $title . ' ' . $author . ' ' . $publisher . ' livro resenha';
    if (!empty($title) && !empty($author)) $queries[] = $title . ' ' . $author . ' livro';
    if (!empty($title)) $queries[] = $title . ' livro';
    
    foreach ($queries as $query) {
        $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&q=' . urlencode($query) . '&type=video&maxResults=1&key=' . $youtube_key;
        $response = wp_remote_get($url, array('timeout' => 10));
        
        if (is_wp_error($response)) continue;
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data['items'][0]['id']['videoId'])) {
            $video_id = $data['items'][0]['id']['videoId'];
            return array(
                'video_id' => $video_id,
                'url' => 'https://www.youtube.com/watch?v=' . $video_id,
                'embed_url' => 'https://www.youtube.com/embed/' . $video_id,
                'title' => $data['items'][0]['snippet']['title'],
            );
        }
    }
    
    return false;
}


// ==========================================
// FASE 19: BUSCA COMPLETA DE DADOS VIA GOOGLE BOOKS API
// ==========================================
function bm_fetch_google_book_data($title, $author = '', $publisher = '', $isbn = '') {
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
        $url = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($query) . '&key=' . bm_get_api_key('google_books');
        $r = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($r)) continue;
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if (empty($body['items'])) continue;

        $best = null;
        foreach ($body['items'] as $item) {
            $it = isset($item['volumeInfo']['title']) ? mb_strtolower(trim($item['volumeInfo']['title'])) : '';
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
        if (!$best) $best = $body['items'][0];

        if ($best && isset($best['volumeInfo'])) {
            $info = $best['volumeInfo'];
            $mt = mb_strtolower(trim($info['title']));
            similar_text($st, $mt, $pct);
            $min = (mb_strlen($st) < 10) ? 30 : 50;
            if ($pct >= $min || strpos($mt, $st) !== false || strpos($st, $mt) !== false) {
                $data = array();
                
                // Capa
                if (isset($info['imageLinks']['thumbnail'])) {
                    $thumb = str_replace('http://', 'https://', $info['imageLinks']['thumbnail']);
                    $thumb = str_replace('&zoom=1', '&zoom=2', $thumb);
                    if (strpos($thumb, '&zoom=') === false) $thumb .= '&zoom=2';
                    $data['cover_url'] = $thumb;
                }
                
                // Sinopse
                if (isset($info['description'])) {
                    $data['description'] = wp_kses_post($info['description']);
                }
                
                // Avaliação
                if (isset($info['averageRating'])) {
                    $data['rating'] = floatval($info['averageRating']);
                }
                
                // Subtítulo
                if (isset($info['subtitle'])) {
                    $data['subtitle'] = sanitize_text_field($info['subtitle']);
                }
                
                // Data de publicação
                if (isset($info['publishedDate'])) {
                    $data['published_date'] = sanitize_text_field($info['publishedDate']);
                }
                
                // Número de páginas
                if (isset($info['pageCount'])) {
                    $data['page_count'] = intval($info['pageCount']);
                }
                
                // ISBNs
                if (isset($info['industryIdentifiers'])) {
                    foreach ($info['industryIdentifiers'] as $identifier) {
                        if ($identifier['type'] === 'ISBN_13') $data['isbn13'] = $identifier['identifier'];
                        if ($identifier['type'] === 'ISBN_10') $data['isbn10'] = $identifier['identifier'];
                    }
                }
                
                return $data;
            }
        }
    }
    return false;
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
        $url='https://www.googleapis.com/books/v1/volumes?q='.urlencode($q).'&key='.bm_get_api_key('google_books');
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
        $url = 'https://www.googleapis.com/books/v1/volumes?q=' . urlencode($query) . '&key=' . bm_get_api_key('google_books');
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
// FASE 11B: CLASSIFICAÇÃO POR DISCIPLINA COM GROQ
// ==========================================
function bm_classify_book_with_ai($post_id) {
    $groq_key = bm_get_api_key('groq');
    if (empty($groq_key)) return false;
    
    $title = get_the_title($post_id);
    $author = get_post_meta($post_id, '_bm_author', true);
    $sinopse = get_post_meta($post_id, '_bm_dynamic_sinopse', true);
    
    // Buscar todas as disciplinas cadastradas
    $all_disciplines = get_terms(array('taxonomy' => 'bm_discipline', 'hide_empty' => false));
    if (empty($all_disciplines)) return false;
    
    $discipline_names = array();
    foreach ($all_disciplines as $term) {
        $discipline_names[] = $term->name;
    }
    $discipline_list = implode(', ', $discipline_names);
    
    // Montar prompt
     $keys = bm_get_api_keys();
    $persona = isset($keys['groq_persona']) && !empty($keys['groq_persona']) ? $keys['groq_persona'] : '';
    $persona_instruction = $persona ? "Personalidade: " . $persona . "\n\n" : '';
    $prompt = $persona_instruction . "Analise o livro abaixo e responda SOMENTE com um JSON válido neste formato exato:\n\n";
    $prompt .= "{\n";
    foreach ($discipline_names as $name) {
        $prompt .= '  "' . $name . '": {"relacionado": true ou false, "justificativa": "uma frase curta em português"},\n';
    }
    $prompt .= "}\n\n";
    $prompt .= "Livro: \"" . $title . "\"\n";
    if ($author) $prompt .= "Autor: " . $author . "\n";
    if ($sinopse) $prompt .= "Sinopse: " . wp_strip_all_tags($sinopse) . "\n";
    $prompt .= "\nRegras:\n- Se o livro NÃO tem relação com a disciplina, use false e justificativa vazia \"\".\n- Se tem relação, use true e escreva uma justificativa pedagógica rica e contextualizada, entre 40 e 50 palavras, em português. Explique por que este livro se relaciona com a disciplina citando temas, personagens, contexto histórico, conceitos ou possíveis atividades. Use tom lúdico e apropriado ao universo escolar brasileiro.\n- Responda APENAS o JSON, sem texto antes ou depois.";
    
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $body = json_encode(array(
        'model' => 'llama-3.3-70b-versatile',
        'messages' => array(
            array('role' => 'user', 'content' => $prompt)
        ),
        'temperature' => 0.3,
        'max_tokens' => 1000,
    ));
    
    $response = wp_remote_post($url, array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $groq_key,
        ),
        'body' => $body,
    ));
    
    if (is_wp_error($response)) return false;
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($data['choices'][0]['message']['content'])) return false;
    
    $result_text = $data['choices'][0]['message']['content'];
    
    // Limpar resposta (remover markdown se houver)
    $result_text = trim($result_text);
    $result_text = preg_replace('/^```json\s*/', '', $result_text);
    $result_text = preg_replace('/\s*```$/', '', $result_text);
    
    $classification = json_decode($result_text, true);
    if (!is_array($classification)) return false;
    
    // Processar resultados
    $selected_terms = array();
    $justifications = array();
    
    foreach ($classification as $discipline_name => $info) {
        if (isset($info['relacionado']) && $info['relacionado'] === true) {
            $term = term_exists($discipline_name, 'bm_discipline');
            if ($term) {
                $term_id = is_array($term) ? $term['term_id'] : $term;
                $selected_terms[] = $term_id;
                if (!empty($info['justificativa'])) {
                    $justifications[$discipline_name] = $info['justificativa'];
                }
            }
        }
    }
    
    if (!empty($selected_terms)) {
        wp_set_post_terms($post_id, $selected_terms, 'bm_discipline');
        update_post_meta($post_id, '_bm_discipline_justifications', $justifications);
        update_post_meta($post_id, '_bm_ai_classified', '1');
        return count($selected_terms);
    }
    
    return false;
}

// Botão "Classificar com IA" na edição
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

// Handler AJAX
function bm_ajax_ai_classify() {
    if (!current_user_can('manage_options') && !current_user_can('edit_bm_books')) wp_die(__('Sem permissão.', 'book-manager'));
    check_ajax_referer('bm_ai_classify_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_die(__('Livro inválido.', 'book-manager'));

    $count = bm_classify_book_with_ai($post_id);
    if ($count) {
        wp_die(sprintf(__('%d disciplina(s) atribuída(s)!', 'book-manager'), $count));
    } else {
        wp_die(__('Não foi possível classificar. Verifique a chave da API Groq.', 'book-manager'));
    }
}
add_action('wp_ajax_bm_ai_classify', 'bm_ajax_ai_classify');

// Botão "Gerar Atividades" na edição do livro
function bm_add_activities_button() {
    global $post;
    if (!$post || 'bm_book' !== $post->post_type) return;
    $cached = get_post_meta($post->ID, '_bm_activities', true);
    $label = $cached ? __('Regenerar Atividades', 'book-manager') : __('Gerar Atividades', 'book-manager');
    $nonce = wp_create_nonce('bm_activities_nonce');
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#bm_generate_activities').on('click', function() {
            var b = $(this);
            b.prop('disabled', true).val('Gerando...');
            $.post(ajaxurl, {
                action: 'bm_generate_activities',
                nonce: '<?php echo $nonce; ?>',
                post_id: $('#post_ID').val()
            }, function(r) {
                alert(r);
                location.reload();
            });
        });
    });
    </script>
    <input type="button" id="bm_generate_activities" class="button" value="<?php echo esc_attr($label); ?>" style="margin-left:10px;" />
    <?php
}
add_action('edit_form_after_title', 'bm_add_activities_button');

// Handler AJAX
function bm_ajax_generate_activities() {
    if (!current_user_can('edit_bm_book') && !current_user_can('manage_options')) wp_die(__('Sem permissão.', 'book-manager'));
    check_ajax_referer('bm_activities_nonce', 'nonce');
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_die(__('Livro inválido.', 'book-manager'));
    
    // Usar Groq diretamente (gratuito)
    $groq_key = bm_get_api_key('groq');
    if (empty($groq_key)) wp_die(__('Chave Groq não configurada.', 'book-manager'));
    
    $title = get_the_title($post_id);
    $author = get_post_meta($post_id, '_bm_author', true);
    $sinopse = get_post_meta($post_id, '_bm_dynamic_sinopse', true);
    
     $keys = bm_get_api_keys();
    $persona_act = isset($keys['groq_persona']) && !empty($keys['groq_persona']) ? $keys['groq_persona'] . "\n\n" : '';
    $prompt = $persona_act . "Sugira 3 atividades pedagógicas para o livro \"" . $title . "\"";
    if ($author) $prompt .= ", de " . $author;
    $prompt .= ". Responda em português, numerando as atividades de 1 a 3.";
    if ($sinopse) $prompt .= "\nSinopse: " . wp_strip_all_tags($sinopse);
    
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $body = json_encode(array(
        'model' => 'llama-3.3-70b-versatile',
        'messages' => array(
            array('role' => 'user', 'content' => $prompt)
        ),
        'temperature' => 0.7,
        'max_tokens' => 500,
    ));
    
    // Contador de chamadas
    $count = intval(get_option('bm_groq_call_count', 0));
    update_option('bm_groq_call_count', $count + 1);
    
    $response = wp_remote_post($url, array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $groq_key,
        ),
        'body' => $body,
    ));
    
    if (is_wp_error($response)) {
        wp_die(__('Erro de conexão:', 'book-manager') . ' ' . $response->get_error_message());
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($http_code !== 200) {
        $error_msg = isset($data['error']['message']) ? $data['error']['message'] : __('Erro desconhecido', 'book-manager');
        wp_die('HTTP ' . $http_code . ': ' . $error_msg);
    }
    
    if (isset($data['choices'][0]['message']['content'])) {
        $result = $data['choices'][0]['message']['content'];
        update_post_meta($post_id, '_bm_activities', $result);
        $success = intval(get_option('bm_groq_success_count', 0));
        update_option('bm_groq_success_count', $success + 1);
        wp_die(__('Atividades geradas com sucesso!', 'book-manager'));
    }
    
    wp_die(__('Resposta inesperada da API.', 'book-manager'));
}
add_action('wp_ajax_bm_generate_activities', 'bm_ajax_generate_activities');

// Exibir atividades no single do livro (para professores e gestores)
function bm_display_activities($book_id = null) {
    if (!$book_id) $book_id = get_the_ID();
    if (!current_user_can('edit_bm_book') && !current_user_can('manage_options')) return '';
    
    $activities = get_post_meta($book_id, '_bm_activities', true);
    if (empty($activities)) return '';
    
    return '<hr><h2>📝 ' . __('Atividades Pedagógicas', 'book-manager') . '</h2><div style="background:#f0f7ff;padding:20px;border-radius:8px;border-left:4px solid #2196f3;">' . nl2br(esc_html($activities)) . '</div>';
}

function bm_add_activities_metabox() {
    add_meta_box('bm_activities_box', __('Atividades Pedagógicas (IA)', 'book-manager'), 'bm_render_activities_metabox', 'bm_book', 'normal', 'default');
}
add_action('add_meta_boxes', 'bm_add_activities_metabox');

function bm_render_activities_metabox($post) {
    $activities = get_post_meta($post->ID, '_bm_activities', true);
    if (empty($activities)) {
        echo '<p style="color:#999;">' . __('Nenhuma atividade gerada. Clique em "Gerar Atividades" acima.', 'book-manager') . '</p>';
    } else {
        echo '<div style="background:#f0f7ff;padding:15px;border-left:4px solid #2196f3;">';
        echo nl2br(esc_html($activities));
        echo '</div>';
    }
}

// ==========================================
// FASE 11E: CHATBOT DA BIBLIOTECA (GROQ)
// ==========================================
function bm_chatbot_scripts() {
    if (is_admin()) return;
        $keys = bm_get_api_keys();
    if (isset($keys['chatbot_active']) && $keys['chatbot_active'] === '0') return;
    ?>
    <style>
    #bm-chatbot-toggle {
        position:fixed; bottom:20px; right:20px; width:60px; height:60px;
        background:#111; color:#fff; border:none; border-radius:50%; font-size:28px;
        cursor:pointer; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.3);
    }
    #bm-chatbot-box {
        position:fixed; bottom:90px; right:20px; width:350px; max-height:450px;
        background:#fff; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,0.2);
        display:none; flex-direction:column; z-index:9998; overflow:hidden;
    }
    #bm-chatbot-header {
        background:#111; color:#fff; padding:12px 15px; font-weight:bold; font-size:14px;
    }
    #bm-chatbot-messages {
        flex:1; padding:10px; overflow-y:auto; max-height:300px; font-size:13px;
    }
    #bm-chatbot-messages .bm-msg-user { background:#e3f2fd; padding:8px 12px; border-radius:12px 12px 0 12px; margin:5px 0; text-align:right; }
    #bm-chatbot-messages .bm-msg-bot { background:#f5f5f5; padding:8px 12px; border-radius:12px 12px 12px 0; margin:5px 0; }
    #bm-chatbot-input-area { display:flex; padding:10px; border-top:1px solid #eee; }
    #bm-chatbot-input { flex:1; padding:8px; border:1px solid #ddd; border-radius:20px; font-size:13px; }
    #bm-chatbot-send { background:#111; color:#fff; border:none; border-radius:20px; padding:8px 15px; margin-left:5px; cursor:pointer; font-size:13px; }
    @media (max-width:400px) { #bm-chatbot-box { width:90%; right:5%; } }
    </style>

    <button id="bm-chatbot-toggle" onclick="bmToggleChat()">💬</button>
    <div id="bm-chatbot-box">
        <div id="bm-chatbot-header">📚 Diva - Bibliotecária Virtual <span style="float:right;cursor:pointer;" onclick="bmToggleChat()">✕</span></div>
        <div id="bm-chatbot-messages">
            <div class="bm-msg-bot">👋 Olá! Sou o bibliotecário virtual. Pergunte-me sobre livros, disponibilidade ou peça recomendações!</div>
        </div>
        <div id="bm-chatbot-input-area">
            <input type="text" id="bm-chatbot-input" placeholder="Digite sua pergunta..." onkeypress="if(event.key==='Enter')bmChatSend()" />
            <button id="bm-chatbot-send" onclick="bmChatSend()">Enviar</button>
        </div>
    </div>
    <script>
    function bmToggleChat() {
        var box = document.getElementById('bm-chatbot-box');
        box.style.display = box.style.display === 'flex' ? 'none' : 'flex';
    }
    function bmChatSend() {
        var input = document.getElementById('bm-chatbot-input');
        var msg = input.value.trim();
        if (!msg) return;
        
        var messages = document.getElementById('bm-chatbot-messages');
        messages.innerHTML += '<div class="bm-msg-user">' + msg + '</div>';
        input.value = '';
        messages.innerHTML += '<div class="bm-msg-bot" id="bm-typing">Pensando...</div>';
        messages.scrollTop = messages.scrollHeight;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            document.getElementById('bm-typing').remove();
            var r = JSON.parse(xhr.responseText);
            messages.innerHTML += '<div class="bm-msg-bot">' + (r.reply || 'Desculpe, não entendi.') + '</div>';
            messages.scrollTop = messages.scrollHeight;
        };
        xhr.send('action=bm_chatbot&nonce=<?php echo wp_create_nonce("bm_chatbot_nonce"); ?>&message=' + encodeURIComponent(msg));
    }
    </script>
    <?php
}
add_action('wp_footer', 'bm_chatbot_scripts');

function bm_get_student_context($user_id) {
    $student = get_userdata($user_id);
    if (!$student || !in_array('bm_student', (array) $student->roles)) return '';
    
    $context = "ALUNO LOGADO: " . $student->display_name . "\n";
    
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    $active_loans = array();
    $overdue_loans = array();
    $returned_books = array();
    
    foreach ($loan_history as $loan) {
        $title = get_the_title($loan['book_id']);
        if ($loan['status'] === 'active') {
            $due = isset($loan['due_date']) ? strtotime($loan['due_date']) : 0;
            $days = ceil(($due - time()) / DAY_IN_SECONDS);
            if ($days < 0) {
                $overdue_loans[] = array('title' => $title, 'days' => abs($days), 'due_date' => date('d/m/Y', $due));
            } else {
                $active_loans[] = array('title' => $title, 'days' => $days, 'due_date' => date('d/m/Y', $due));
            }
        } elseif ($loan['status'] === 'returned') {
            $returned_books[] = $title;
        }
    }
    
    if (!empty($active_loans)) {
        $context .= "Empréstimos ativos:\n";
        foreach ($active_loans as $loan) {
            $context .= "- \"" . $loan['title'] . "\" | Devolver até: " . $loan['due_date'] . " | Faltam " . $loan['days'] . " dias\n";
        }
    }
    
    if (!empty($overdue_loans)) {
        $context .= "EM ATRASO:\n";
        foreach ($overdue_loans as $loan) {
            $context .= "- \"" . $loan['title'] . "\" | Deveria ter sido devolvido em: " . $loan['due_date'] . " | " . $loan['days'] . " dias de atraso\n";
        }
    }
    
    if (!empty($returned_books)) {
        $ultimos = array_slice($returned_books, -5);
        $context .= "Últimos livros lidos: " . implode(', ', array_map(function($t) { return '"' . $t . '"'; }, $ultimos)) . "\n";
    }
    
    if (empty($active_loans) && empty($overdue_loans)) {
        $context .= "Nenhum empréstimo ativo no momento.\n";
    }
    
    return $context;
}

function bm_ajax_chatbot() {
    check_ajax_referer('bm_chatbot_nonce', 'nonce');
    $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
    if (empty($message)) wp_die(json_encode(array('reply' => 'Digite uma pergunta.')));
    
    $groq_key = bm_get_api_key('groq');
    if (empty($groq_key)) wp_die(json_encode(array('reply' => 'Chatbot não configurado.')));
    
    // Buscar acervo resumido com sinopses para recomendação inteligente
    $books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => 30, 'post_status' => 'publish'));
    $catalog = '';
    foreach ($books as $book) {
        $author = get_post_meta($book->ID, '_bm_author', true);
        $location = get_post_meta($book->ID, '_bm_location', true);
        $copies = intval(get_post_meta($book->ID, '_bm_copies', true));
        $borrowed = intval(get_post_meta($book->ID, '_bm_borrowed_count', true));
        $available = $copies - $borrowed;
        $genres = wp_get_post_terms($book->ID, 'bm_genre', array('fields' => 'names'));
        $disciplines = wp_get_post_terms($book->ID, 'bm_discipline', array('fields' => 'names'));
        $sinopse = get_post_meta($book->ID, '_bm_dynamic_sinopse', true);
        $sinopse_curta = $sinopse ? mb_substr(wp_strip_all_tags($sinopse), 0, 300) : '';
        
        $catalog .= "- \"" . $book->post_title . "\"";
        if ($author) $catalog .= " | Autor: " . $author;
        if (!empty($genres)) $catalog .= " | Gênero: " . implode(', ', $genres);
        if ($sinopse_curta) $catalog .= " | Sinopse: " . $sinopse_curta;
        if ($location) $catalog .= " | Local: " . $location;
        $catalog .= " | Disponível: " . max(0, $available);
        if (!empty($disciplines)) $catalog .= " | Disciplinas: " . implode(', ', $disciplines);
        $catalog .= "\n";
    }
    
    // Persona personalizada da central de APIs
    $keys = bm_get_api_keys();
    $persona_chat = isset($keys['groq_persona']) && !empty($keys['groq_persona']) ? $keys['groq_persona'] : '';

    // Contexto do usuário logado
    $user_context = '';
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        if (in_array('bm_student', (array) $user->roles)) {
            $user_context = bm_get_student_context($user_id);
        }
    }

    $prompt = "Você é Diva Barbalho, bibliotecária escolar brasileira, culta, empática e apaixonada por livros. Pode te chamar de Diva. Você trabalha em uma biblioteca escolar e adora ajudar alunos e professores a descobrirem novas leituras.\n";
    if (!empty($persona_chat)) {
        $prompt .= "DIRETRIZES DE PERSONALIDADE: " . $persona_chat . "\n";
    }
    $prompt .= "\n";
    $prompt .= "- NUNCA repita saudações como 'Olá' ou 'É um prazer' se o usuário já iniciou a conversa. Vá direto ao ponto.\n";
    if (!empty($user_context)) {
        $prompt .= "- Você reconhece o aluno logado e pode comentar sobre os livros que ele está lendo, prazos de devolução ou atrasos, sempre com tom amigável e incentivador, nunca repreensivo.\n";
        $prompt .= $user_context . "\n";
    }
    $prompt .= "- Se perguntarem sobre um livro específico, descreva o enredo, temas e por que ele é interessante, usando a sinopse disponível.\n";
    $prompt .= "- Se pedirem recomendação, analise o acervo e sugira livros que combinem com o gosto da pessoa. Explique POR QUE cada livro combina.\n";
    $prompt .= "- Se não encontrar nada no acervo que atenda ao pedido, seja honesta mas ofereça alternativas próximas.\n";
    $prompt .= "- Limite-se ao acervo disponível. Não invente livros que não estão na lista.\n";
    $prompt .= "- SEMPRE responda em 1 ou 2 frases curtas e diretas, no máximo 3 frases. Nunca escreva parágrafos longos.\n";
    $prompt .= "- Só faça respostas mais elaboradas se a pessoa pedir explicitamente uma análise ou recomendação detalhada.\n";
    $prompt .= "- Responda em português.\n\n";
    $prompt .= "ACERVO:\n" . $catalog . "\n\n";
    $prompt .= "PERGUNTA DO USUÁRIO: " . $message . "\n\n";
    $prompt .= "Resposta da Diva:";
    
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $body = json_encode(array(
        'model' => 'llama-3.3-70b-versatile',
        'messages' => array(array('role' => 'user', 'content' => $prompt)),
        'temperature' => 0.7,
        'max_tokens' => 500,
    ));
    
    // Contador de chamadas
    $count = intval(get_option('bm_groq_call_count', 0));
    update_option('bm_groq_call_count', $count + 1);
    
    $response = wp_remote_post($url, array(
        'timeout' => 20,
        'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $groq_key),
        'body' => $body,
    ));
    
    if (is_wp_error($response)) wp_die(json_encode(array('reply' => 'Erro de conexão.')));
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $reply = isset($data['choices'][0]['message']['content']) ? $data['choices'][0]['message']['content'] : 'Hmm, não consegui entender. Pode perguntar de outro jeito?';
    
    if (isset($data['choices'][0]['message']['content'])) {
        $success = intval(get_option('bm_groq_success_count', 0));
        update_option('bm_groq_success_count', $success + 1);
    }
    
    wp_die(json_encode(array('reply' => $reply)));
}
add_action('wp_ajax_bm_chatbot', 'bm_ajax_chatbot');
add_action('wp_ajax_nopriv_bm_chatbot', 'bm_ajax_chatbot');

// ==========================================
// FASE 12I-T5: BUSCA RÁPIDA NO DASHBOARD
// ==========================================
function bm_ajax_quick_search() {
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    if (empty($query)) wp_die(json_encode(array('success' => false)));
    
    $args = array(
        'post_type' => 'bm_book',
        'posts_per_page' => 5,
        'post_status' => 'publish',
        's' => $query,
    );
    
    $books = get_posts($args);
    $results = array();
    
    foreach ($books as $book) {
        $total = intval(get_post_meta($book->ID, '_bm_copies', true));
        $borrowed = intval(get_post_meta($book->ID, '_bm_borrowed_count', true));
        $available = max(0, $total - $borrowed);
        
        $results[] = array(
            'title' => $book->post_title,
            'author' => get_post_meta($book->ID, '_bm_author', true),
            'total' => $total,
            'available' => $available,
            'url' => get_permalink($book->ID),
        );
    }
    
    wp_die(json_encode(array('success' => true, 'books' => $results)));
}
add_action('wp_ajax_bm_quick_search', 'bm_ajax_quick_search');

// ==========================================
// FASE 12K: HANDLERS AJAX DE ATENDIMENTO
// ==========================================

// Buscar livro por título, autor ou ISBN
function bm_ajax_service_search_book() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('found' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $isbn = isset($_POST['isbn']) ? sanitize_text_field($_POST['isbn']) : '';
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    
    if (!empty($isbn)) {
        // Buscar por ISBN exato
        $posts = get_posts(array(
            'post_type' => 'bm_book',
            'posts_per_page' => 1,
            'meta_key' => '_bm_isbn',
            'meta_value' => $isbn,
        ));
    } elseif (!empty($query)) {
        $posts = get_posts(array(
            'post_type' => 'bm_book',
            'posts_per_page' => 1,
            's' => $query,
        ));
    } else {
        wp_die(json_encode(array('found' => false, 'message' => 'Digite um termo de busca.')));
    }
    
    if (!empty($posts)) {
        $book = $posts[0];
        $total = intval(get_post_meta($book->ID, '_bm_copies', true));
        $borrowed = intval(get_post_meta($book->ID, '_bm_borrowed_count', true));
        
        // Fila de espera
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        $queue = array();
        foreach ($reservations as $r) {
            if ($r['status'] === 'waiting') {
                $user = get_userdata($r['user_id']);
                $queue[] = array('name' => $user ? $user->display_name : '#' . $r['user_id'], 'date' => date('d/m/Y', strtotime($r['date'])));
            }
        }
        
        // Verificar se há empréstimo ativo em atraso
        $overdue = false;
        foreach ($reservations as $r) {
            if ($r['status'] === 'active' && isset($r['due_date']) && strtotime($r['due_date']) < time()) {
                $overdue = true;
                break;
            }
        }
        
        wp_die(json_encode(array(
            'found' => true,
            'book' => array(
                'id' => $book->ID,
                'title' => $book->post_title,
                'author' => get_post_meta($book->ID, '_bm_author', true),
                'cdu' => get_post_meta($book->ID, '_bm_cdu', true),
                'total' => $total,
                'available' => max(0, $total - $borrowed),
                'consulta_local' => get_post_meta($book->ID, '_bm_consulta_local', true) == '1',
                'queue' => $queue,
                'overdue' => $overdue,
            ),
        )));
    }
    
    // Não encontrado — verificar se é ISBN para sugerir cadastro
    if (!empty($isbn)) {
        $clean_isbn = preg_replace('/[^0-9]/', '', $isbn);
        if (strlen($clean_isbn) >= 10) {
            wp_die(json_encode(array('found' => false, 'can_register' => true, 'isbn' => $clean_isbn, 'message' => 'Livro não encontrado.')));
        }
    }
    
    wp_die(json_encode(array('found' => false, 'message' => 'Nenhum livro encontrado.')));
}
add_action('wp_ajax_bm_service_search_book', 'bm_ajax_service_search_book');

// Buscar aluno por nome ou e-mail
function bm_ajax_service_search_student() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('found' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    
    if ($student_id) {
        $student = get_userdata($student_id);
        if ($student && in_array('bm_student', (array) $student->roles)) {
            wp_die(json_encode(array('found' => true, 'student' => bm_format_student_data($student))));
        }
        wp_die(json_encode(array('found' => false, 'message' => 'Aluno não encontrado.')));
    }
    
    if (empty($query)) wp_die(json_encode(array('found' => false, 'message' => 'Digite um nome ou e-mail.')));
    
    $students = get_users(array('role' => 'bm_student', 'search' => '*' . $query . '*', 'number' => 10));
    
    if (empty($students)) {
        wp_die(json_encode(array('found' => false, 'message' => 'Nenhum aluno encontrado.')));
    } elseif (count($students) === 1) {
        wp_die(json_encode(array('found' => true, 'student' => bm_format_student_data($students[0]))));
    } else {
        $list = array();
        foreach ($students as $s) {
            $list[] = array(
                'id' => $s->ID,
                'name' => $s->display_name,
                'email' => $s->user_email,
                'group' => get_user_meta($s->ID, 'bm_student_group', true),
            );
        }
        wp_die(json_encode(array('found' => false, 'multiple' => true, 'students' => $list)));
    }
}

// ==========================================
// FASE 12K: AÇÕES DO ATENDIMENTO
// ==========================================

// Emprestar
function bm_ajax_service_loan() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $user_id = intval($_POST['user_id']);
    $days = isset($_POST['days']) ? intval($_POST['days']) : 14;
    
    if (!$book_id || !$user_id) wp_die(json_encode(array('success' => false, 'message' => 'Selecione livro e aluno.')));
    
        // Verificar penalidade ativa
    $penalty_block = bm_check_penalty_block($user_id);
    if ($penalty_block) {
        $msg = __('Empréstimo bloqueado.', 'book-manager');
        if ($penalty_block['type'] === 'suspension') {
            $msg .= ' ' . sprintf(__('Aluno suspenso até %s.', 'book-manager'), date('d/m/Y', strtotime($penalty_block['until'])));
        } elseif ($penalty_block['type'] === 'fine') {
            $msg .= ' ' . sprintf(__('Aluno possui multa de R$ %.2f em aberto.', 'book-manager'), $penalty_block['value']);
        } else {
            $msg .= ' ' . __('Aluno possui advertência ativa.', 'book-manager');
        }
        wp_die(json_encode(array('success' => false, 'message' => $msg)));
    }
    
    // Verificar consulta local
    if (get_post_meta($book_id, '_bm_consulta_local', true) == '1') {
        wp_die(json_encode(array('success' => false, 'message' => 'Este livro é de consulta local e não pode ser emprestado.')));
    }
    
    // Verificar se aluno tem atraso (bloqueio)
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    foreach ($loan_history as $loan) {
        if ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < time()) {
            wp_die(json_encode(array('success' => false, 'message' => 'Aluno possui livro em atraso. Empréstimo bloqueado.')));
        }
    }
    
    // Criar reserva primeiro (se não existir)
    $reservations = get_post_meta($book_id, '_bm_reservations', true) ?: array();
    $has_reservation = false;
    foreach ($reservations as $r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'waiting') $has_reservation = true;
    }
    
    if (!$has_reservation) {
        $result = bm_reserve_book($book_id, 1, $user_id); // user_id 1 = admin reservando para aluno
        if (isset($result['error'])) {
            wp_die(json_encode(array('success' => false, 'message' => $result['error'])));
        }
    }
    
    $result = bm_confirm_loan($book_id, $user_id, $days);
    if (isset($result['error'])) {
        wp_die(json_encode(array('success' => false, 'message' => $result['error'])));
    }
    
    wp_die(json_encode(array('success' => true, 'message' => '✅ Emprestado com sucesso! Devolução: ' . date('d/m/Y', strtotime('+' . $days . ' days')))));
}
add_action('wp_ajax_bm_service_loan', 'bm_ajax_service_loan');

// Devolver
function bm_ajax_service_return() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $user_id = intval($_POST['user_id']);
    $condition = isset($_POST['condition']) ? sanitize_text_field($_POST['condition']) : 'good';
    $note = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';
    
    if (!$book_id || !$user_id) wp_die(json_encode(array('success' => false, 'message' => 'Selecione livro e aluno.')));
    
    // Salvar condição de devolução
    if (!empty($note) || $condition !== 'good') {
        $return_log = get_post_meta($book_id, '_bm_return_log', true) ?: array();
        $return_log[] = array(
            'user_id' => $user_id,
            'date' => current_time('mysql'),
            'condition' => $condition,
            'note' => $note,
        );
        update_post_meta($book_id, '_bm_return_log', $return_log);
    }
    
    $result = bm_return_book($book_id, $user_id);
    if (isset($result['error'])) {
        wp_die(json_encode(array('success' => false, 'message' => $result['error'])));
    }
    
    wp_die(json_encode(array('success' => true, 'message' => '✅ ' . $result['message'])));
}
add_action('wp_ajax_bm_service_return', 'bm_ajax_service_return');

// Renovar
function bm_ajax_service_renew() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $user_id = intval($_POST['user_id']);
    $days = isset($_POST['days']) ? intval($_POST['days']) : 7;
    
    if (!$book_id || !$user_id) wp_die(json_encode(array('success' => false, 'message' => 'Selecione livro e aluno.')));
    
    // Atualizar due_date no empréstimo ativo
    $reservations = get_post_meta($book_id, '_bm_reservations', true) ?: array();
    $found = false;
    foreach ($reservations as &$r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'active') {
            $old_due = $r['due_date'];
            $r['due_date'] = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', strtotime($old_due)));
            $found = true;
            break;
        }
    }
    
    if (!$found) wp_die(json_encode(array('success' => false, 'message' => 'Empréstimo ativo não encontrado.')));
    
    update_post_meta($book_id, '_bm_reservations', $reservations);
    
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    foreach ($loan_history as &$loan) {
        if ($loan['book_id'] == $book_id && $loan['status'] === 'active') {
            $loan['due_date'] = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', strtotime($loan['due_date'])));
            break;
        }
    }
    update_user_meta($user_id, '_bm_loan_history', $loan_history);
    
    $new_due = date('d/m/Y', strtotime('+' . $days . ' days'));
    wp_die(json_encode(array('success' => true, 'message' => '🔄 Renovado! Nova data de devolução: ' . $new_due)));
}
add_action('wp_ajax_bm_service_renew', 'bm_ajax_service_renew');


function bm_ajax_advance_reserve() {
    if (!is_user_logged_in()) wp_die(json_encode(array('success' => false, 'error' => 'Faça login.')));
    check_ajax_referer('bm_reserve_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $user_id = get_current_user_id();
    $group = sanitize_text_field($_POST['group']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    
    if (!$book_id || empty($group) || empty($start_date) || empty($end_date)) {
        wp_die(json_encode(array('success' => false, 'error' => 'Preencha todos os campos.')));
    }
    
    $reservations = get_post_meta($book_id, '_bm_bulk_reservation', true) ?: array();
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    
    $reservations[] = array(
        'teacher_id' => $user_id,
        'group' => $group,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'status' => 'active',
        'student_id' => $student_id,
        'created_at' => current_time('mysql'),
    );
    update_post_meta($book_id, '_bm_bulk_reservation', $reservations);
    
    bm_log_audit($book_id, "Reserva antecipada por professor #$user_id para turma $group ($start_date até $end_date)");
    
    wp_die(json_encode(array('success' => true, 'message' => '📅 Reserva antecipada confirmada para ' . date('d/m/Y', strtotime($start_date)) . '!')));
}
add_action('wp_ajax_bm_advance_reserve', 'bm_ajax_advance_reserve');


function bm_ajax_separate_advance() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $created_at = isset($_POST['created_at']) ? sanitize_text_field($_POST['created_at']) : '';
    
    $bulk = get_post_meta($book_id, '_bm_bulk_reservation', true) ?: array();
    foreach ($bulk as $key => $item) {
        if (isset($item['created_at']) && $item['created_at'] === $created_at) {
            $bulk[$key]['status'] = 'separated';
            update_post_meta($book_id, '_bm_bulk_reservation', $bulk);
            wp_die(json_encode(array('success' => true)));
        }
    }
    wp_die(json_encode(array('success' => false, 'message' => 'Registro não encontrado.')));
}
add_action('wp_ajax_bm_separate_advance', 'bm_ajax_separate_advance');

function bm_ajax_cancel_advance() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $created_at = isset($_POST['created_at']) ? sanitize_text_field($_POST['created_at']) : '';
    
    $bulk = get_post_meta($book_id, '_bm_bulk_reservation', true) ?: array();
    foreach ($bulk as $key => $item) {
        if (isset($item['created_at']) && $item['created_at'] === $created_at) {
            $bulk[$key]['status'] = 'cancelled';
            update_post_meta($book_id, '_bm_bulk_reservation', $bulk);
            wp_die(json_encode(array('success' => true)));
        }
    }
    wp_die(json_encode(array('success' => false, 'message' => 'Registro não encontrado.')));
}
add_action('wp_ajax_bm_cancel_advance', 'bm_ajax_cancel_advance');

function bm_ajax_reject_reservation() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $user_id = intval($_POST['user_id']);
    
    $result = bm_reject_reservation($book_id, $user_id);
    if (isset($result['error'])) {
        wp_die(json_encode(array('success' => false, 'message' => $result['error'])));
    }
    wp_die(json_encode(array('success' => true, 'message' => $result['message'])));
}
add_action('wp_ajax_bm_reject_reservation', 'bm_ajax_reject_reservation');


function bm_ajax_undo_loan() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $user_id = intval($_POST['user_id']);
    
    $result = bm_undo_loan($book_id, $user_id);
    if (isset($result['error'])) {
        wp_die(json_encode(array('success' => false, 'message' => $result['error'])));
    }
    wp_die(json_encode(array('success' => true, 'message' => $result['message'])));
}
add_action('wp_ajax_bm_undo_loan', 'bm_ajax_undo_loan');

// Renovação feita pelo próprio aluno no dashboard
function bm_ajax_renew_loan() {
    if (!is_user_logged_in()) wp_die(json_encode(array('success' => false, 'message' => 'Faça login.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $book_id = intval($_POST['book_id']);
    $user_id = intval($_POST['user_id']);
    
    if (get_current_user_id() != $user_id) {
        wp_die(json_encode(array('success' => false, 'message' => 'Você só pode renovar seus próprios livros.')));
    }
    
    // Verificar fila de espera
    $reservations = get_post_meta($book_id, '_bm_reservations', true) ?: array();
    $has_queue = false;
    foreach ($reservations as $r) {
        if ($r['status'] === 'waiting' && $r['user_id'] != $user_id) {
            $has_queue = true;
            break;
        }
    }
    
    if ($has_queue) {
        wp_die(json_encode(array('success' => false, 'message' => 'Há alunos na fila de espera. Não é possível renovar.')));
    }
    
    $days = 7;
    
    // Atualizar due_date
    foreach ($reservations as &$r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'active') {
            $r['due_date'] = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', strtotime($r['due_date'])));
            break;
        }
    }
    update_post_meta($book_id, '_bm_reservations', $reservations);
    
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    foreach ($loan_history as &$loan) {
        if ($loan['book_id'] == $book_id && $loan['status'] === 'active') {
            $loan['due_date'] = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', strtotime($loan['due_date'])));
            break;
        }
    }
    update_user_meta($user_id, '_bm_loan_history', $loan_history);
    
    wp_die(json_encode(array('success' => true, 'message' => '🔄 Renovado por mais ' . $days . ' dias!')));
}
add_action('wp_ajax_bm_renew_loan', 'bm_ajax_renew_loan');

// Cadastro rápido de aluno
function bm_ajax_service_quick_register() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    
    if (empty($name) || empty($email)) wp_die(json_encode(array('success' => false, 'message' => 'Nome e e-mail são obrigatórios.')));
    if (email_exists($email)) wp_die(json_encode(array('success' => false, 'message' => 'E-mail já cadastrado.')));
    
    $password = wp_generate_password(12, false);
    $user_id = wp_insert_user(array(
        'user_login' => sanitize_user($email),
        'user_email' => $email,
        'user_pass' => $password,
        'display_name' => $name,
        'role' => 'bm_student',
    ));
    
    if (is_wp_error($user_id)) wp_die(json_encode(array('success' => false, 'message' => $user_id->get_error_message())));
    
    update_user_meta($user_id, 'bm_approval_status', 'approved');
    update_user_meta($user_id, '_bm_user_' . sanitize_key('Nome completo'), $name);
    update_user_meta($user_id, '_bm_user_' . sanitize_key('E-mail'), $email);
    update_user_meta($user_id, '_bm_user_' . sanitize_key('Telefone'), $phone);
    
    // Campos dinâmicos
    $user_fields = get_option('bm_user_dynamic_fields', array());
    foreach ($user_fields as $field_name => $info) {
        $meta_key = '_bm_user_' . sanitize_key($field_name);
        if (isset($_POST[$meta_key]) && !empty($_POST[$meta_key])) {
            update_user_meta($user_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
        }
    }
    
    wp_die(json_encode(array('success' => true, 'message' => '✅ Aluno cadastrado!', 'student_id' => $user_id, 'student_name' => $name)));
}
add_action('wp_ajax_bm_service_quick_register', 'bm_ajax_service_quick_register');

// Cadastro de livro por ISBN via Google Books
function bm_ajax_service_register_book_by_isbn() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $isbn = sanitize_text_field($_POST['isbn']);
    if (empty($isbn)) wp_die(json_encode(array('success' => false, 'message' => 'ISBN inválido.')));
    
    $url = 'https://www.googleapis.com/books/v1/volumes?q=isbn:' . $isbn . '&key=' . bm_get_api_key('google_books');
    $response = wp_remote_get($url, array('timeout' => 15));
    
    if (is_wp_error($response)) wp_die(json_encode(array('success' => false, 'message' => 'Erro ao buscar na Google Books.')));
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['items'][0]['volumeInfo'])) wp_die(json_encode(array('success' => false, 'message' => 'Livro não encontrado na Google Books.')));
    
    $info = $data['items'][0]['volumeInfo'];
    $title = sanitize_text_field($info['title']);
    $author = isset($info['authors']) ? sanitize_text_field(implode(', ', $info['authors'])) : '';
    $publisher = isset($info['publisher']) ? sanitize_text_field($info['publisher']) : '';
    
    $post_id = wp_insert_post(array(
        'post_type' => 'bm_book',
        'post_title' => $title,
        'post_status' => 'publish',
    ));
    
    if (is_wp_error($post_id)) wp_die(json_encode(array('success' => false, 'message' => 'Erro ao criar livro.')));
    
    update_post_meta($post_id, '_bm_isbn', $isbn);
    update_post_meta($post_id, '_bm_author', $author);
    update_post_meta($post_id, '_bm_publisher', $publisher);
    update_post_meta($post_id, '_bm_copies', '1');
    
    // Buscar capa
    if (isset($info['imageLinks']['thumbnail'])) {
        $cover_url = str_replace('http://', 'https://', $info['imageLinks']['thumbnail']);
        $cover_url = str_replace('&zoom=1', '&zoom=2', $cover_url);
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $ir = wp_remote_get($cover_url, array('timeout' => 15));
        if (!is_wp_error($ir)) {
            $body = wp_remote_retrieve_body($ir);
            if (!empty($body)) {
                $ud = wp_upload_dir();
                $fn = 'book-cover-' . $post_id . '-' . time() . '.jpg';
                $fp = $ud['path'] . '/' . $fn;
                file_put_contents($fp, $body);
                $att = array('post_mime_type' => 'image/jpeg', 'post_title' => $title, 'post_content' => '', 'post_status' => 'inherit');
                $aid = wp_insert_attachment($att, $fp, $post_id);
                if (!is_wp_error($aid)) {
                    $ad = wp_generate_attachment_metadata($aid, $fp);
                    wp_update_attachment_metadata($aid, $ad);
                    set_post_thumbnail($post_id, $aid);
                }
            }
        }
    }
    
    wp_die(json_encode(array('success' => true, 'message' => '✅ Livro cadastrado: ' . $title, 'book_id' => $post_id, 'book_title' => $title, 'book_author' => $author)));
}
add_action('wp_ajax_bm_service_register_book_by_isbn', 'bm_ajax_service_register_book_by_isbn');

// Editar aluno via atendimento
function bm_ajax_service_edit_student() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(json_encode(array('success' => false, 'message' => 'Sem permissão.')));
    check_ajax_referer('bm_service_nonce', 'nonce');
    
    $student_id = intval($_POST['student_id']);
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    
    if (!$student_id || empty($name) || empty($email)) wp_die(json_encode(array('success' => false, 'message' => 'Nome e e-mail são obrigatórios.')));
    
    $existing = get_userdata($student_id);
    if (!$existing || !in_array('bm_student', (array) $existing->roles)) wp_die(json_encode(array('success' => false, 'message' => 'Aluno não encontrado.')));
    
    // Verificar se e-mail já existe em outro usuário
    $email_owner = email_exists($email);
    if ($email_owner && $email_owner != $student_id) wp_die(json_encode(array('success' => false, 'message' => 'E-mail já cadastrado para outro aluno.')));
    
    wp_update_user(array('ID' => $student_id, 'display_name' => $name, 'user_email' => $email));
    update_user_meta($student_id, '_bm_user_' . sanitize_key('Nome completo'), $name);
    update_user_meta($student_id, '_bm_user_' . sanitize_key('E-mail'), $email);
    update_user_meta($student_id, '_bm_user_' . sanitize_key('Telefone'), $phone);
    
    // Campos dinâmicos
    $user_fields = get_option('bm_user_dynamic_fields', array());
    foreach ($user_fields as $field_name => $info) {
        $meta_key = '_bm_user_' . sanitize_key($field_name);
        if (isset($_POST[$meta_key])) {
            update_user_meta($student_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
        }
    }
    
    wp_die(json_encode(array('success' => true, 'message' => '✅ Aluno atualizado!')));
}
add_action('wp_ajax_bm_service_edit_student', 'bm_ajax_service_edit_student');

add_action('wp_ajax_bm_service_search_student', 'bm_ajax_service_search_student');

function bm_format_student_data($student) {
    $settings = bm_get_settings();
    $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
    $active_loans = 0; $has_overdue = false; $recent_books = array();
    
    foreach (array_reverse($loan_history) as $loan) {
        if ($loan['status'] === 'active') {
            $active_loans++;
            if (isset($loan['due_date']) && strtotime($loan['due_date']) < time()) $has_overdue = true;
        }
        if (count($recent_books) < 3) {
            $title = get_the_title($loan['book_id']);
            if ($title) $recent_books[] = $title;
        }
    }
    
    // Campos dinâmicos
    $dynamic_fields = array();
    $user_fields = get_option('bm_user_dynamic_fields', array());
    foreach ($user_fields as $field_name => $info) {
        $meta_key = '_bm_user_' . sanitize_key($field_name);
        $dynamic_fields[$meta_key] = get_user_meta($student->ID, $meta_key, true);
    }
    
    return array(
        'id' => $student->ID,
        'name' => $student->display_name,
        'email' => $student->user_email,
        'phone' => get_user_meta($student->ID, '_bm_user_' . sanitize_key('Telefone'), true),
        'group' => get_user_meta($student->ID, 'bm_student_group', true),
        'active_loans' => $active_loans,
        'max_loans' => $settings['max_loans_student'],
        'has_overdue' => $has_overdue,
        'blocked' => $has_overdue,
        'recent_books' => $recent_books,
        'dynamic_fields' => $dynamic_fields,
    );
}

// ==========================================
// FASE 11B: NÚMERO DE CHAMADA (CDU + CUTTER)
// ==========================================

function bm_generate_call_number($post_id) {
    $groq_key = bm_get_api_key('groq');
    if (empty($groq_key)) return false;
    
    $title = get_the_title($post_id);
    $author = get_post_meta($post_id, '_bm_author', true);
    $sinopse = get_post_meta($post_id, '_bm_dynamic_sinopse', true);
    $genres = wp_get_post_terms($post_id, 'bm_genre', array('fields' => 'names'));
    $genre_list = implode(', ', $genres);
    
    $cdu_prompt = "Atribua o código CDU para: \"" . $title . "\"";
    if ($author) $cdu_prompt .= " | Autor: " . $author;
    if (!empty($genre_list)) $cdu_prompt .= " | Gênero: " . $genre_list;
    if ($sinopse) $cdu_prompt .= " | Sinopse: " . wp_strip_all_tags(substr($sinopse, 0, 200));
    $cdu_prompt .= "\nResponda APENAS o código CDU.";
    
    $cdu = bm_groq_simple_request($cdu_prompt);
    
    $cutter_prompt = "Gere o código Cutter-Sanborn (1 letra + 2-3 dígitos + 1 letra minúscula) para:";
    if ($author) $cutter_prompt .= " Autor: " . $author;
    $cutter_prompt .= " Título: " . $title;
    $cutter_prompt .= "\nRegras: ignorar artigos, prefixos juntos (De Greve=Degreve), números por extenso (100=Cem). Responda APENAS o código.";
    
    $cutter = bm_groq_simple_request($cutter_prompt);
    
    if ($cdu && $cutter) {
        $cutter = bm_resolve_cutter_conflict($cutter, $post_id);
        
        $history = get_post_meta($post_id, '_bm_cutter_history', true) ?: array();
        $old_cdu = get_post_meta($post_id, '_bm_cdu', true);
        $old_cutter = get_post_meta($post_id, '_bm_cutter', true);
        if ($old_cdu || $old_cutter) {
            $history[] = array('cdu' => $old_cdu, 'cutter' => $old_cutter, 'date' => current_time('mysql'), 'user' => get_current_user_id());
            update_post_meta($post_id, '_bm_cutter_history', $history);
        }
        
        update_post_meta($post_id, '_bm_cdu', sanitize_text_field($cdu));
        update_post_meta($post_id, '_bm_cutter', sanitize_text_field($cutter));
        update_post_meta($post_id, '_bm_cutter_cached', '1');
        update_post_meta($post_id, '_bm_cutter_locked', '1');
        return array('cdu' => $cdu, 'cutter' => $cutter);
    }
    return false;
}

function bm_generate_cdu_only($post_id) {
    $title = get_the_title($post_id);
    $author = get_post_meta($post_id, '_bm_author', true);
    $sinopse = get_post_meta($post_id, '_bm_dynamic_sinopse', true);
    $genres = wp_get_post_terms($post_id, 'bm_genre', array('fields' => 'names'));
    
    $settings = bm_get_settings();
    $system_label = ($settings['classification_system'] === 'cdd') ? 'CDD' : 'CDU';
    $prompt = "Atribua o código {$system_label} para: \"" . $title . "\"";
    if ($author) $prompt .= " | Autor: " . $author;
    if (!empty($genres)) $prompt .= " | Gênero: " . implode(', ', $genres);
    if ($sinopse) $prompt .= " | Sinopse: " . wp_strip_all_tags(substr($sinopse, 0, 200));
    $prompt .= "\nResponda APENAS o código CDU.";
    
    return bm_groq_simple_request($prompt);
}

function bm_generate_cutter_only($post_id) {
    $title = get_the_title($post_id);
    $author = get_post_meta($post_id, '_bm_author', true);
    
    $prompt = "Gere o código Cutter-Sanborn (1 letra + 2-3 dígitos + 1 letra minúscula) para:";
    if ($author) $prompt .= " Autor: " . $author;
    $prompt .= " Título: " . $title;
    $prompt .= "\nRegras: ignorar artigos, prefixos juntos, números por extenso. Responda APENAS o código.";
    
    $cutter = bm_groq_simple_request($prompt);
    if ($cutter) {
        return bm_resolve_cutter_conflict($cutter, $post_id);
    }
    return false;
}

function bm_groq_simple_request($prompt) {
    $groq_key = bm_get_api_key('groq');
    if (empty($groq_key)) return false;
    
    // Contador de chamadas
    $count = intval(get_option('bm_groq_call_count', 0));
    update_option('bm_groq_call_count', $count + 1);
    
    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $body = json_encode(array(
        'model' => 'llama-3.3-70b-versatile',
        'messages' => array(array('role' => 'user', 'content' => $prompt)),
        'temperature' => 0.2,
        'max_tokens' => 50,
    ));
    
    $response = wp_remote_post($url, array(
        'timeout' => 15,
        'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $groq_key),
        'body' => $body,
    ));
    
    if (is_wp_error($response)) return false;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $result = isset($data['choices'][0]['message']['content']) ? trim($data['choices'][0]['message']['content']) : false;
    
    if ($result) {
        $success = intval(get_option('bm_groq_success_count', 0));
        update_option('bm_groq_success_count', $success + 1);
    }
    
    return $result;
}

function bm_resolve_cutter_conflict($cutter, $post_id) {
    $existing = get_posts(array(
        'post_type' => 'bm_book', 'posts_per_page' => -1, 'post__not_in' => array($post_id),
        'meta_key' => '_bm_cutter', 'meta_value' => $cutter,
    ));
    if (!empty($existing)) return $cutter . (count($existing) + 1);
    return $cutter;
}

function bm_add_call_number_metabox() { add_meta_box('bm_call_number', __('Número de Chamada', 'book-manager'), 'bm_render_call_number_metabox', 'bm_book', 'side', 'default'); }
add_action('add_meta_boxes', 'bm_add_call_number_metabox');

function bm_render_call_number_metabox($post) {
    wp_nonce_field('bm_call_number_nonce', 'bm_call_number_nonce_field');
    
    $title = get_the_title($post->ID);
    $author = get_post_meta($post->ID, '_bm_author', true);
    $cdu = get_post_meta($post->ID, '_bm_cdu', true);
    $cutter = get_post_meta($post->ID, '_bm_cutter', true);
    $locked = get_post_meta($post->ID, '_bm_cutter_locked', true);
    $history = get_post_meta($post->ID, '_bm_cutter_history', true) ?: array();
    $edition = get_post_meta($post->ID, '_bm_edition', true);
    $volume = get_post_meta($post->ID, '_bm_volume', true);
    $copies = max(1, intval(get_post_meta($post->ID, '_bm_copies', true)));
    $readonly = $locked ? 'readonly' : '';
    
    $author_formatted = '';
    if ($author) {
        $parts = explode(' ', trim($author));
        $author_formatted = count($parts) > 1 ? mb_strtoupper(array_pop($parts)) . ', ' . implode(' ', $parts) : mb_strtoupper($author);
    }
    ?>
    <p><label><strong><?php _e('Título:', 'book-manager'); ?></strong></label><input type="text" value="<?php echo esc_attr($title); ?>" style="width:100%;" readonly /></p>
    <p><label><strong><?php _e('Autor:', 'book-manager'); ?></strong></label><input type="text" value="<?php echo esc_attr($author_formatted ?: $author); ?>" style="width:100%;" readonly /></p>
    <p><label><strong><?php _e('Classificação:', 'book-manager'); ?></strong></label><input type="text" name="bm_cdu" value="<?php echo esc_attr($cdu); ?>" style="width:100%;" <?php echo $readonly; ?> /></p>
    <p><label><strong><?php _e('Cutter:', 'book-manager'); ?></strong></label><input type="text" name="bm_cutter" value="<?php echo esc_attr($cutter); ?>" style="width:100%;" <?php echo $readonly; ?> /></p>
    <p><label><strong><?php _e('Volume:', 'book-manager'); ?></strong></label><input type="text" name="bm_volume" value="<?php echo esc_attr($volume); ?>" style="width:100%;" placeholder="v.1" /></p>
    <p><label><strong><?php _e('Edição:', 'book-manager'); ?></strong></label><input type="text" name="bm_edition" value="<?php echo esc_attr($edition); ?>" style="width:100%;" placeholder="3.ed." /></p>
    <p><label><strong><?php _e('Exemplares:', 'book-manager'); ?></strong></label><input type="text" name="bm_copies" value="<?php echo $copies; ?>" style="width:100%;" /></p>
    
    <button type="button" id="bm-generate-call" class="button" style="width:100%;margin-top:5px;">🤖 <?php echo ($cdu && $cutter) ? __('Regenerar Número de Chamada', 'book-manager') : __('Gerar Número de Chamada', 'book-manager'); ?></button>
    
    <?php if ($locked): ?>
        <p style="color:#f0ad4e;font-size:11px;">⚠️ <?php _e('Número de chamada bloqueado.', 'book-manager'); ?></p>
        <button type="button" id="bm-unlock-call" class="button button-small" style="width:100%;">🔓 <?php _e('Habilitar edição', 'book-manager'); ?></button>
    <?php endif; ?>
    
    <?php if (!empty($history)): ?>
        <p style="margin-top:10px;"><strong><?php _e('Histórico:', 'book-manager'); ?></strong></p>
        <select name="bm_restore_history" style="width:100%;font-size:11px;">
            <option value="">— <?php _e('Versões anteriores', 'book-manager'); ?> —</option>
            <?php foreach (array_reverse($history) as $i => $h): ?>
                <option value="<?php echo $i; ?>"><?php echo $h['cdu'] . ' / ' . $h['cutter'] . ' (' . date('d/m/Y', strtotime($h['date'])) . ')'; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="bm-restore-btn" class="button button-small" style="display:none;width:100%;margin-top:3px;">↩️ <?php _e('Restaurar versão', 'book-manager'); ?></button>
    <?php endif; ?>
    
    <script>
    (function() {
        var genBtn = document.getElementById('bm-generate-call');
        if (genBtn) {
            genBtn.addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;
                btn.textContent = 'Gerando...';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) { location.reload(); }
                        else { alert(r.data || 'Erro'); btn.disabled = false; btn.textContent = '🤖 Gerar Número de Chamada'; }
                    } catch(e) {
                        alert('Erro: ' + xhr.responseText.substring(0, 200));
                        btn.disabled = false;
                        btn.textContent = '🤖 Gerar Número de Chamada';
                    }
                };
                xhr.send('action=bm_generate_call_number&nonce=<?php echo wp_create_nonce("bm_call_number_nonce"); ?>&post_id=<?php echo $post->ID; ?>');
            });
        }
        
        var unlockBtn = document.getElementById('bm-unlock-call');
        if (unlockBtn) {
            unlockBtn.addEventListener('click', function() {
                if (confirm('<?php _e("Atenção: Ao editar manualmente, o número de chamada pode não corresponder ao livro físico. Continuar?", "book-manager"); ?>')) {
                    var cduInput = document.querySelector('input[name="bm_cdu"]');
                    var cutterInput = document.querySelector('input[name="bm_cutter"]');
                    if (cduInput) cduInput.removeAttribute('readonly');
                    if (cutterInput) cutterInput.removeAttribute('readonly');
                    this.style.display = 'none';
                }
            });
        }
        
        var restoreBtn = document.getElementById('bm-restore-btn');
        var historySelect = document.querySelector('select[name="bm_restore_history"]');
        if (historySelect && restoreBtn) {
            historySelect.addEventListener('change', function() {
                restoreBtn.style.display = this.value !== '' ? 'inline-block' : 'none';
            });
            restoreBtn.addEventListener('click', function() {
                var index = historySelect.value;
                if (index !== '') {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) location.reload();
                    };
                    xhr.send('action=bm_restore_call_number&nonce=<?php echo wp_create_nonce("bm_call_number_nonce"); ?>&post_id=<?php echo $post->ID; ?>&history_index=' + index);
                }
            });
        }
    })();
    </script>
    <?php
}

function bm_save_call_number_metabox($post_id) {
    if (!isset($_POST['bm_call_number_nonce_field']) || !wp_verify_nonce($_POST['bm_call_number_nonce_field'], 'bm_call_number_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('manage_options') && !current_user_can('edit_bm_books')) return;
    if (isset($_POST['bm_cdu'])) update_post_meta($post_id, '_bm_cdu', sanitize_text_field($_POST['bm_cdu']));
    if (isset($_POST['bm_cutter'])) update_post_meta($post_id, '_bm_cutter', sanitize_text_field($_POST['bm_cutter']));
    if (isset($_POST['bm_volume'])) update_post_meta($post_id, '_bm_volume', sanitize_text_field($_POST['bm_volume']));
    if (isset($_POST['bm_edition'])) update_post_meta($post_id, '_bm_edition', sanitize_text_field($_POST['bm_edition']));
    if (isset($_POST['bm_copies'])) update_post_meta($post_id, '_bm_copies', absint($_POST['bm_copies']));
}
add_action('save_post_bm_book', 'bm_save_call_number_metabox');

function bm_ajax_generate_call_number() {
    $groq_key = bm_get_api_key('groq');
    if (empty($groq_key)) wp_die(json_encode(array('success' => false, 'data' => 'Chave Groq não configurada.')));
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) wp_die(json_encode(array('success' => false, 'data' => 'Livro inválido.')));
    
    $result = bm_generate_call_number($post_id);
    if ($result) {
        wp_die(json_encode(array('success' => true, 'data' => 'Gerado! Classificação: ' . $result['cdu'] . ' / Cutter: ' . $result['cutter'])));
    } else {
        wp_die(json_encode(array('success' => false, 'data' => 'A IA não retornou um resultado válido. Tente novamente.')));
    }
}
add_action('wp_ajax_bm_generate_call_number', 'bm_ajax_generate_call_number');

function bm_ajax_restore_call_number() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $index = isset($_POST['history_index']) ? intval($_POST['history_index']) : -1;
    $history = get_post_meta($post_id, '_bm_cutter_history', true) ?: array();
    if (isset($history[$index])) {
        update_post_meta($post_id, '_bm_cdu', $history[$index]['cdu']);
        update_post_meta($post_id, '_bm_cutter', $history[$index]['cutter']);
        wp_die(json_encode(array('success' => true)));
    }
    wp_die(json_encode(array('success' => false)));
}
add_action('wp_ajax_bm_restore_call_number', 'bm_ajax_restore_call_number');

function bm_display_call_number($book_id = null) {
    if (!$book_id) $book_id = get_the_ID();
    $cdu = get_post_meta($book_id, '_bm_cdu', true);
    $cutter = get_post_meta($book_id, '_bm_cutter', true);
    if (empty($cdu) && empty($cutter)) return '';
    
    $title = get_the_title($book_id);
    $author = get_post_meta($book_id, '_bm_author', true);
    $edition = get_post_meta($book_id, '_bm_edition', true);
    $volume = get_post_meta($book_id, '_bm_volume', true);
    $copies = max(1, intval(get_post_meta($book_id, '_bm_copies', true)));
    
    $author_formatted = '';
    if ($author) {
        $parts = explode(' ', trim($author));
        $author_formatted = count($parts) > 1 ? mb_strtoupper(array_pop($parts)) . ', ' . implode(' ', $parts) : mb_strtoupper($author);
    }
    
    $settings = bm_get_settings();
    $order = isset($settings['call_number_order']) ? $settings['call_number_order'] : array('cdu', 'cutter', 'author', 'title', 'edition', 'volume', 'copies');
    
    $lines = array();
    foreach ($order as $field) {
        switch ($field) {
            case 'cdu':
                if ($cdu) $lines[] = '<p style="font-size:18px;font-weight:bold;margin:5px 0;">' . esc_html($cdu) . '</p>';
                break;
            case 'cutter':
                if ($cutter) $lines[] = '<p style="font-size:18px;font-weight:bold;margin:5px 0;">' . esc_html($cutter) . '</p>';
                break;
            case 'author':
                if ($author_formatted) $lines[] = '<p style="font-size:10px;font-weight:bold;margin:2px 0;">' . esc_html($author_formatted) . '</p>';
                break;
            case 'title':
                $lines[] = '<p style="font-size:10px;margin:2px 0;">' . esc_html($title) . '</p>';
                break;
            case 'edition':
                if ($edition) $lines[] = '<p style="margin:3px 0;color:#666;">' . esc_html($edition) . '</p>';
                break;
            case 'volume':
                if ($volume) $lines[] = '<p style="margin:3px 0;color:#666;">' . esc_html($volume) . '</p>';
                break;
            case 'copies':
                if ($copies > 0) $lines[] = '<p style="margin:3px 0;color:#666;">' . sprintf(__('%d exemplares', 'book-manager'), $copies) . '</p>';
                break;
        }
    }
    
    $html = '<hr><h2>📋 ' . __('Número de Chamada', 'book-manager') . '</h2>';
    $html .= '<div style="background:#f9f9f9;padding:15px;border-radius:8px;border:1px solid #ddd;max-width:300px;margin:0 auto;text-align:center;">';
    $html .= implode('', $lines);
    $html .= '</div>';
    
    return $html;
}