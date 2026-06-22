<?php
/**
 * Book Manager — Módulo de Gamificação
 * Ranking, fichas de leitura, XP, medalhas, perfil público, detalhes do aluno
 */

defined('ABSPATH') || exit;

// ==========================================
// FASE 10A: RANKING DE LEITORES
// ==========================================

function bm_get_top_books($period = 'month', $limit = 10, $genre = '') {
    $cache_key = 'bm_top_books_' . $period . '_' . $limit . '_' . $genre;
    $cached = bm_get_cached($cache_key);
    if ($cached) return $cached;
    
    $now = current_time('timestamp');
    switch ($period) {
        case 'week': $since = strtotime('-7 days', $now); break;
        case 'bimester': $since = strtotime('-60 days', $now); break;
        case 'year': $since = strtotime('-365 days', $now); break;
        case 'month':
        default: $since = strtotime('-30 days', $now); break;
    }
    
    $args = array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'publish');
    if ($genre) {
        $args['tax_query'] = array(array('taxonomy' => 'bm_genre', 'field' => 'name', 'terms' => $genre));
    }
    
    $books = get_posts($args);
    $top = array();
    
    foreach ($books as $book) {
        $count = 0;
        $loan_history_all = array();
        $users = get_users(array('role' => 'bm_student'));
        foreach ($users as $user) {
            $loans = get_user_meta($user->ID, '_bm_loan_history', true) ?: array();
            foreach ($loans as $loan) {
                if ($loan['book_id'] == $book->ID && $loan['status'] === 'returned' && isset($loan['returned_date'])) {
                    $returned_time = strtotime($loan['returned_date']);
                    if ($returned_time >= $since && $returned_time <= $now) {
                        $count++;
                    }
                }
            }
        }
        if ($count > 0) {
            $top[] = array(
                'id' => $book->ID,
                'title' => $book->post_title,
                'author' => get_post_meta($book->ID, '_bm_author', true),
                'cover' => get_the_post_thumbnail_url($book->ID, 'medium'),
                'count' => $count,
            );
        }
    }
    
    usort($top, function($a, $b) { return $b['count'] - $a['count']; });
    $top = array_slice($top, 0, $limit);
    
    bm_set_cached($cache_key, $top, 3600);
    return $top;
}

function bm_get_ranking($period = 'month', $limit = 10, $group = '', $genre = '', $discipline = '', $sort = 'books') {
    $now = current_time('timestamp');
    
    switch ($period) {
        case 'week':
            $since = strtotime('-7 days', $now);
            break;
        case 'bimester':
            $since = strtotime('-60 days', $now);
            break;
        case 'year':
            $since = strtotime('-365 days', $now);
            break;
        case 'month':
        default:
            $since = strtotime('-30 days', $now);
            break;
    }
    
    $students = get_users(array('role' => 'bm_student'));
    $ranking = array();
    
    foreach ($students as $student) {
        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
        $count = 0;
        
        foreach ($loan_history as $loan) {
            if ($loan['status'] === 'returned' && isset($loan['returned_date'])) {
                $returned_time = strtotime($loan['returned_date']);
                if ($returned_time >= $since && $returned_time <= $now) {
                    $count++;
                }
            }
        }
        
        if ($count > 0) {
            $ranking[] = array(
                'user_id' => $student->ID,
                'name' => $student->display_name,
                'avatar' => get_avatar_url($student->ID, array('size' => 60)),
                'count' => $count,
            );
        }
    }
    
    usort($ranking, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    return array_slice($ranking, 0, $limit);
}

function bm_ranking_shortcode($atts) {
    $atts = shortcode_atts(array(
        'period' => 'month',
        'limit' => 10,
        'group' => '',
        'genre' => '',
        'discipline' => '',
        'sort' => 'books',
    ), $atts);
    
    $ranking = bm_get_ranking($atts['period'], intval($atts['limit']), $atts['group'], $atts['genre'], $atts['discipline'], $atts['sort']);
    
    if (empty($ranking)) {
        return '<p>' . __('Nenhum leitor no período.', 'book-manager') . '</p>';
    }
    
    $period_labels = array(
        'week' => __('esta semana', 'book-manager'),
        'month' => __('este mês', 'book-manager'),
        'bimester' => __('este bimestre', 'book-manager'),
        'year' => __('este ano', 'book-manager'),
    );
    $period_label = isset($period_labels[$atts['period']]) ? $period_labels[$atts['period']] : $atts['period'];
    
    ob_start();
    ?>
    <div class="bm-ranking" style="max-width:600px;margin:0 auto;padding:20px;">
        <h2 style="text-align:center;margin-bottom:20px;">🏆 <?php printf(__('Top Leitores de %s', 'book-manager'), $period_label); ?></h2>
        
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($ranking as $index => $reader): 
                $medal = '';
                $bg = '#f9f9f9';
                $border = '';
                if ($index === 0) { $medal = '🥇'; $bg = '#fff8e1'; $border = '2px solid #ffc107'; }
                elseif ($index === 1) { $medal = '🥈'; $bg = '#f5f5f5'; $border = '2px solid #9e9e9e'; }
                elseif ($index === 2) { $medal = '🥉'; $bg = '#fff3e0'; $border = '2px solid #ff9800'; }
            ?>
                <div style="display:flex;align-items:center;gap:15px;padding:12px 15px;background:<?php echo $bg; ?>;border-radius:8px;<?php echo $border ? 'border:' . $border . ';' : ''; ?>">
                    <div style="font-size:24px;font-weight:bold;width:40px;text-align:center;color:#666;">
                        <?php echo $medal ? $medal : ($index + 1); ?>
                    </div>
                    <div style="width:50px;height:50px;border-radius:50%;overflow:hidden;background:#eee;flex-shrink:0;">
                        <?php if ($reader['avatar']): ?>
                            <img src="<?php echo esc_url($reader['avatar']); ?>" alt="<?php echo esc_attr($reader['name']); ?>" style="width:100%;height:100%;object-fit:cover;" />
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#999;font-size:20px;">👤</div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <strong><?php echo esc_html($reader['name']); ?></strong>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:22px;font-weight:bold;color:#111;"><?php echo $reader['count']; ?></span>
                        <span style="font-size:12px;color:#666;display:block;"><?php echo $reader['count'] == 1 ? __('livro lido', 'book-manager') : __('livros lidos', 'book-manager'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bm_ranking', 'bm_ranking_shortcode');

function bm_top_books_shortcode($atts) {
    $atts = shortcode_atts(array(
        'period' => 'month',
        'limit' => 10,
        'genre' => '',
    ), $atts);
    
    $books = bm_get_top_books($atts['period'], intval($atts['limit']), $atts['genre']);
    
    if (empty($books)) {
        return '<p>' . __('Nenhum livro encontrado no período.', 'book-manager') . '</p>';
    }
    
    $period_labels = array(
        'week' => __('esta semana', 'book-manager'),
        'month' => __('este mês', 'book-manager'),
        'bimester' => __('este bimestre', 'book-manager'),
        'year' => __('este ano', 'book-manager'),
    );
    $period_label = isset($period_labels[$atts['period']]) ? $period_labels[$atts['period']] : $atts['period'];
    
    ob_start();
    ?>
    <div style="max-width:900px;margin:0 auto;padding:20px;">
        <h2 style="text-align:center;margin-bottom:20px;">📚 <?php printf(__('Livros Mais Emprestados de %s', 'book-manager'), $period_label); ?></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:15px;">
            <?php foreach ($books as $index => $book): 
                $medal = '';
                $border = '';
                if ($index === 0) { $medal = '🥇'; $border = '2px solid #ffc107'; }
                elseif ($index === 1) { $medal = '🥈'; $border = '2px solid #9e9e9e'; }
                elseif ($index === 2) { $medal = '🥉'; $border = '2px solid #ff9800'; }
            ?>
                <div style="background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);<?php echo $border ? 'border:' . $border . ';' : ''; ?>">
                    <a href="<?php echo get_permalink($book['id']); ?>" style="text-decoration:none;color:inherit;">
                        <?php if ($book['cover']): ?>
                            <img src="<?php echo esc_url($book['cover']); ?>" style="width:100%;height:200px;object-fit:cover;" alt="<?php echo esc_attr($book['title']); ?>" />
                        <?php else: ?>
                            <div style="width:100%;height:200px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:14px;"><?php _e('Sem capa', 'book-manager'); ?></div>
                        <?php endif; ?>
                        <div style="padding:10px;">
                            <?php if ($medal): ?><span style="font-size:20px;"><?php echo $medal; ?></span><?php endif; ?>
                            <strong style="font-size:13px;"><?php echo esc_html($book['title']); ?></strong>
                            <?php if ($book['author']): ?><p style="font-size:11px;color:#666;margin:3px 0;"><?php echo esc_html($book['author']); ?></p><?php endif; ?>
                            <span style="font-size:12px;color:#111;"><?php printf(__('%d empréstimos', 'book-manager'), $book['count']); ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bm_top_books', 'bm_top_books_shortcode');

// ==========================================
// FASE 10B: FICHA DE LEITURA
// FASE 10D: MODAL DE AVALIAÇÃO + BÔNUS DE XP DO GESTOR
// ==========================================
function bm_reading_log_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('Faça login para preencher sua ficha de leitura.', 'book-manager') . '</p>';
    }
    
    $user_id = get_current_user_id();
    $notice = '';
    
    if (isset($_POST['bm_reading_log_submit']) && wp_verify_nonce($_POST['bm_reading_log_nonce'], 'bm_reading_log_action')) {
        $book_id = intval($_POST['bm_book_id']);
        $rating = intval($_POST['bm_rating']);
        $review = sanitize_textarea_field($_POST['bm_review']);
        $video_url = sanitize_text_field($_POST['bm_video_url']);
        
        $errors = array();
        if (!$book_id) $errors[] = __('Selecione um livro.', 'book-manager');
        if (empty($review)) $errors[] = __('Escreva uma resenha.', 'book-manager');
        
        if (empty($errors)) {
            $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
            
            $exists = false;
            foreach ($reading_log as $log) {
                if ($log['book_id'] == $book_id) { $exists = true; break; }
            }
            
            if ($exists) {
                $notice = '<p style="color:#f0ad4e;">' . __('Você já preencheu a ficha deste livro.', 'book-manager') . '</p>';
            } else {
                $entry = array(
                    'book_id' => $book_id,
                    'rating' => $rating,
                    'review' => $review,
                    'video_url' => $video_url,
                    'date' => current_time('mysql'),
                    'status' => 'pending',
                    'xp_awarded' => false,
                );
                $reading_log[] = $entry;
                update_user_meta($user_id, '_bm_reading_log', $reading_log);
                
                $notice = '<p style="color:green;">' . __('Ficha de leitura enviada! Aguardando aprovação.', 'book-manager') . '</p>';
                
                if ($rating == 0) {
                    $nonce = wp_create_nonce('bm_rating_nonce');
                    $notice .= '
                    <div id="bm-rating-modal" style="display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
                        <div style="background:#fff;padding:30px;border-radius:8px;max-width:400px;text-align:center;">
                            <p style="font-size:16px;margin-bottom:15px;">' . __('Você não avaliou este livro. Que tal dar uma nota?', 'book-manager') . '</p>
                            <div style="display:flex;gap:5px;justify-content:center;font-size:28px;margin-bottom:15px;" id="bm-modal-stars">
                                <span onclick="bmModalRate(1)" style="cursor:pointer;color:#ccc;">★</span>
                                <span onclick="bmModalRate(2)" style="cursor:pointer;color:#ccc;">★</span>
                                <span onclick="bmModalRate(3)" style="cursor:pointer;color:#ccc;">★</span>
                                <span onclick="bmModalRate(4)" style="cursor:pointer;color:#ccc;">★</span>
                                <span onclick="bmModalRate(5)" style="cursor:pointer;color:#ccc;">★</span>
                            </div>
                            <input type="hidden" id="bm-modal-rating-value" value="0" />
                            <button onclick="bmSubmitRating(' . $book_id . ', \'' . $nonce . '\')" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-right:5px;">' . __('Avaliar agora', 'book-manager') . '</button>
                            <button onclick="document.getElementById(\'bm-rating-modal\').style.display=\'none\'" style="padding:8px 20px;background:#eee;color:#333;border:none;border-radius:4px;cursor:pointer;">' . __('Agora não', 'book-manager') . '</button>
                        </div>
                    </div>
                    <script>
                    function bmModalRate(r) {
                        document.getElementById("bm-modal-rating-value").value = r;
                        var stars = document.querySelectorAll("#bm-modal-stars span");
                        stars.forEach(function(s, i) { s.style.color = i < r ? "#ffc107" : "#ccc"; });
                    }
                    function bmSubmitRating(bookId, nonce) {
                        var rating = document.getElementById("bm-modal-rating-value").value;
                        if (rating > 0) {
                            var xhr = new XMLHttpRequest();
                            xhr.open("POST", "' . admin_url('admin-ajax.php') . '");
                            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                            xhr.send("action=bm_update_rating&book_id=" + bookId + "&rating=" + rating + "&nonce=" + nonce);
                        }
                        document.getElementById("bm-rating-modal").style.display = "none";
                    }
                    </script>';
                }
            }
        } else {
            $notice = '<p style="color:red;">' . implode('<br>', array_map('esc_html', $errors)) . '</p>';
        }
    }
    
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    $logged_book_ids = array_column($reading_log, 'book_id');
    
    $available_books = array();
    foreach ($loan_history as $loan) {
        if ($loan['status'] === 'returned' && !in_array($loan['book_id'], $logged_book_ids)) {
            $available_books[] = array(
                'id' => $loan['book_id'],
                'title' => get_the_title($loan['book_id']),
            );
        }
    }
    
    $my_logs = array();
    foreach ($reading_log as $log) {
        $log['book_title'] = get_the_title($log['book_id']);
        $my_logs[] = $log;
    }
    $my_logs = array_reverse($my_logs);
    
    ob_start();
    ?>
    <div class="bm-reading-log" style="max-width:600px;margin:0 auto;padding:20px;">
        <h1><?php _e('Ficha de Leitura', 'book-manager'); ?></h1>
        <?php echo $notice; ?>
        
        <?php if (!empty($available_books)): ?>
            <h2><?php _e('Nova Ficha', 'book-manager'); ?></h2>
            <form method="post" style="background:#f9f9f9;padding:20px;border-radius:8px;">
                <?php wp_nonce_field('bm_reading_log_action', 'bm_reading_log_nonce'); ?>
                
                <p>
                    <label><strong><?php _e('Livro:', 'book-manager'); ?></strong></label>
                    <select name="bm_book_id" required style="width:100%;padding:8px;margin-top:4px;">
                        <option value=""><?php _e('— Selecione —', 'book-manager'); ?></option>
                        <?php foreach ($available_books as $book): ?>
                            <option value="<?php echo $book['id']; ?>"><?php echo esc_html($book['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                
                <p>
                    <label><strong><?php _e('Nota (opcional):', 'book-manager'); ?></strong></label>
                    <div style="display:flex;gap:5px;margin-top:4px;font-size:24px;" id="bm-star-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span data-value="<?php echo $i; ?>" style="cursor:pointer;color:#ccc;" onclick="bmSetRating(<?php echo $i; ?>)">★</span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="bm_rating" id="bm_rating_value" value="0" />
                </p>
                
                <p>
                    <label><strong><?php _e('Resenha:', 'book-manager'); ?></strong></label>
                    <div style="display:flex;gap:8px;align-items:flex-start;">
    <textarea name="bm_review" id="bm_review_textarea" rows="5" required style="flex:1;padding:8px;margin-top:4px;" placeholder="<?php _e('O que você achou do livro?', 'book-manager'); ?>"></textarea>
    <button type="button" id="bm-mic-btn" style="margin-top:4px;padding:8px 12px;background:#111;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:16px;" title="<?php _e('Ditar resenha', 'book-manager'); ?>">🎤</button>
</div>
                </p>
                
                <p>
                    <label><strong><?php _e('Vídeo-Resenha (opcional):', 'book-manager'); ?></strong></label>
                    <input type="url" name="bm_video_url" style="width:100%;padding:8px;margin-top:4px;" placeholder="https://youtube.com/watch?v=... ou https://tiktok.com/@user/video/..." />
                </p>
                
                <p>
                    <input type="submit" name="bm_reading_log_submit" value="<?php _e('Enviar Ficha', 'book-manager'); ?>" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;" />
                </p>
            </form>
            
            <script>
            function bmSetRating(rating) {
                document.getElementById('bm_rating_value').value = rating;
                var stars = document.querySelectorAll('#bm-star-rating span');
                stars.forEach(function(star, index) {
                    star.style.color = index < rating ? '#ffc107' : '#ccc';
                });
            }
            </script>

            <script>
            (function() {
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) return;

                var micBtn = document.getElementById('bm-mic-btn');
                var textarea = document.getElementById('bm_review_textarea');
                if (!micBtn || !textarea) return;

                var recognition = new SpeechRecognition();
                recognition.continuous = true;
                recognition.interimResults = true;
                recognition.lang = 'pt-BR';

                var isListening = false;

                micBtn.addEventListener('click', function() {
                    if (isListening) {
                        recognition.stop();
                        return;
                    }
                    recognition.start();
                });

                recognition.onstart = function() {
                    isListening = true;
                    micBtn.textContent = '🔴';
                    micBtn.style.background = '#dc3545';
                    micBtn.style.animation = 'bm-pulse 1s infinite';
                };

                recognition.onend = function() {
                    isListening = false;
                    micBtn.textContent = '🎤';
                    micBtn.style.background = '#111';
                    micBtn.style.animation = '';
                };

                recognition.onresult = function(event) {
                    var finalTranscript = '';
                    for (var i = event.resultIndex; i < event.results.length; i++) {
                        if (event.results[i].isFinal) {
                            finalTranscript += event.results[i][0].transcript;
                        }
                    }
                    if (finalTranscript) {
                        textarea.value = (textarea.value + ' ' + finalTranscript).trim();
                    }
                };

                recognition.onerror = function(event) {
                    console.log('Speech recognition error', event.error);
                    recognition.stop();
                };

                // Add pulse animation style
                var style = document.createElement('style');
                style.textContent = '@keyframes bm-pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }';
                document.head.appendChild(style);
            })();
            </script>

        <?php else: ?>
            <p><?php _e('Você não tem livros disponíveis para resenha. Leia e devolva um livro primeiro!', 'book-manager'); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($my_logs)): ?>
            <h2><?php _e('Minhas Fichas', 'book-manager'); ?></h2>
            <?php foreach ($my_logs as $log): 
                $status_label = $log['status'] === 'approved' ? __('✅ Aprovada', 'book-manager') : __('⏳ Pendente', 'book-manager');
                $status_color = $log['status'] === 'approved' ? 'green' : '#f0ad4e';
            ?>
                <div style="background:#f9f9f9;padding:15px;border-radius:8px;margin-bottom:10px;">
                    <?php 
                    $cover_url = get_the_post_thumbnail_url($log['book_id'], 'thumbnail');
                    if (!$cover_url) $cover_url = get_post_meta($log['book_id'], '_bm_cover_hotlink', true);
                    if ($cover_url): ?>
                        <img src="<?php echo esc_url($cover_url); ?>" style="width:40px;height:56px;object-fit:cover;border-radius:3px;margin-right:10px;float:left;" alt="" />
                    <?php else: ?>
                        <div style="width:40px;height:56px;background:#f0f0f0;border-radius:3px;float:left;margin-right:10px;text-align:center;line-height:56px;font-size:8px;color:#999;"><?php _e('Sem capa', 'book-manager'); ?></div>
                    <?php endif; ?>
                    <strong><?php echo esc_html($log['book_title']); ?></strong>
                    <span style="color:<?php echo $status_color; ?>;float:right;"><?php echo $status_label; ?></span>
                    <?php if ($log['rating'] > 0): ?>
                        <div style="color:#ffc107;margin:5px 0;">
                            <?php echo str_repeat('★', $log['rating']) . str_repeat('☆', 5 - $log['rating']); ?>
                        </div>
                    <?php endif; ?>
                    <p style="margin:5px 0;color:#666;"><?php echo esc_html($log['review']); ?></p>
                    <?php if ($log['status'] === 'approved' && isset($log['xp_awarded']) && $log['xp_awarded']): 
                        $xp_log = get_user_meta($user_id, '_bm_xp_history', true) ?: array();
                        $xp_ganho = 0;
                        foreach (array_reverse($xp_log) as $xp_entry) {
                            if (strpos($xp_entry['reason'], get_the_title($log['book_id'])) !== false) {
                                $xp_ganho = $xp_entry['amount'];
                                break;
                            }
                        }
                    ?>
                        <span style="background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-size:12px;">+<?php echo $xp_ganho; ?> XP</span>
                    <?php endif; ?>
                    <?php if (!empty($log['video_url'])): ?>
                        <p style="margin:5px 0;font-size:12px;">🎬 <a href="<?php echo esc_url($log['video_url']); ?>" target="_blank"><?php _e('Ver vídeo-resenha', 'book-manager'); ?></a></p>
                    <?php endif; ?>
                    <small style="color:#999;"><?php echo date('d/m/Y', strtotime($log['date'])); ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bm_reading_log', 'bm_reading_log_shortcode');

function bm_ajax_update_rating() {
    if (!is_user_logged_in()) wp_die();
    check_ajax_referer('bm_rating_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $book_id = intval($_POST['book_id']);
    $rating = intval($_POST['rating']);
    
    if ($rating < 1 || $rating > 5) wp_die();
    
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    foreach ($reading_log as &$log) {
        if ($log['book_id'] == $book_id) {
            $log['rating'] = $rating;
            break;
        }
    }
    update_user_meta($user_id, '_bm_reading_log', $reading_log);
    wp_die();
}
add_action('wp_ajax_bm_update_rating', 'bm_ajax_update_rating');

// ==========================================
// FASE 10B: APROVAÇÃO DE FICHAS DE LEITURA
// ==========================================
function bm_render_reading_approval_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    bm_render_reading_approval_page_content();
}

function bm_render_reading_approval_page_content() {

    if (isset($_POST['bm_reading_action']) && wp_verify_nonce($_POST['bm_reading_nonce'], 'bm_reading_action')) {
        $user_id = intval($_POST['user_id']);
        $book_id = intval($_POST['book_id']);
        $action = sanitize_text_field($_POST['bm_reading_action']);
        $bonus_xp = isset($_POST['bm_bonus_xp']) ? intval($_POST['bm_bonus_xp']) : 0;
        
        $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
        foreach ($reading_log as &$log) {
            if ($log['book_id'] == $book_id) {
                $log['status'] = $action === 'approve' ? 'approved' : 'rejected';
                break;
            }
        }
        update_user_meta($user_id, '_bm_reading_log', $reading_log);
        
        if ($action === 'approve') {
            $xp_reading = isset($_POST['bm_xp_reading']) ? intval($_POST['bm_xp_reading']) : 0;
            $xp_review = isset($_POST['bm_xp_review']) ? intval($_POST['bm_xp_review']) : 0;
            $xp_video = isset($_POST['bm_xp_video']) ? intval($_POST['bm_xp_video']) : 0;
            bm_award_xp_on_approval($user_id, $book_id, $xp_reading, $xp_review, $xp_video);
        }
        
        
        if ($action === 'approve' && isset($_POST['bm_featured_review']) && $_POST['bm_featured_review'] === '1') {
            $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
            foreach ($reading_log as &$log) {
                if ($log['book_id'] == $book_id) {
                    $log['featured'] = true;
                    break;
                }
            }
            update_user_meta($user_id, '_bm_reading_log', $reading_log);
            
            $featured_count = 0;
            $all_users = get_users(array('role__in' => array('bm_student', 'bm_teacher')));

            foreach ($all_users as $u) {
                $u_log = get_user_meta($u->ID, '_bm_reading_log', true) ?: array();
                foreach ($u_log as &$l) {
                    if ($l['book_id'] == $book_id && $l['status'] === 'approved' && isset($l['featured']) && $l['featured']) {
                        $featured_count++;
                        if ($featured_count > 3) {
                            $l['featured'] = false;
                        }
                    }
                }
                update_user_meta($u->ID, '_bm_reading_log', $u_log);
            }
        }

        echo $action === 'approve' 
            ? '<div class="notice notice-success"><p>' . __('Ficha aprovada! XP concedido.', 'book-manager') . '</p></div>'
            : '<div class="notice notice-error"><p>' . __('Ficha rejeitada.', 'book-manager') . '</p></div>';
    }
    
    $all_users = get_users(array('role__in' => array('bm_student', 'bm_teacher')));
    $readings = array();
    $current_filter = isset($_GET['bm_reading_status']) ? sanitize_text_field($_GET['bm_reading_status']) : 'pending';
    
    foreach ($all_users as $user) {
        $reading_log = get_user_meta($user->ID, '_bm_reading_log', true) ?: array();
        foreach ($reading_log as $log) {
            if ($log['status'] === $current_filter) {
                $log['user_id'] = $user->ID;
                $log['user_name'] = $user->display_name;
                $log['book_title'] = get_the_title($log['book_id']);
                $readings[] = $log;
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Aprovar Fichas de Leitura', 'book-manager'); ?></h1>
        <form method="get" style="margin-bottom:15px;">
            <input type="hidden" name="post_type" value="bm_book">
            <input type="hidden" name="page" value="bm_students">
            <input type="hidden" name="tab" value="approve_readings">
            <label><strong><?php _e('Status:', 'book-manager'); ?></strong></label>
            <select name="bm_reading_status" onchange="this.form.submit()" style="margin-left:5px;">
                <option value="pending" <?php selected($current_filter, 'pending'); ?>><?php _e('Pendentes', 'book-manager'); ?></option>
                <option value="approved" <?php selected($current_filter, 'approved'); ?>><?php _e('Aprovadas', 'book-manager'); ?></option>
            </select>
        </form>
        <?php if (empty($readings)): ?>
            <p><?php _e('Nenhuma ficha pendente.', 'book-manager'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Aluno', 'book-manager'); ?></th>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Nota', 'book-manager'); ?></th>
                        <th><?php _e('Resenha', 'book-manager'); ?></th>
                        <th><?php _e('Vídeo', 'book-manager'); ?></th>
                        <?php $settings = bm_get_settings(); ?>
                        <?php if ($settings['xp_enabled'] === '1'): ?>
                        <th><?php _e('Nota Leitura', 'book-manager'); ?></th>
                        <th><?php _e('Nota Resenha', 'book-manager'); ?></th>
                        <th><?php _e('Nota Vídeo', 'book-manager'); ?></th>
                        <?php endif; ?>
                        <th><?php _e('Data', 'book-manager'); ?></th>
                        <th><?php _e('Ações', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($readings as $reading): 
                        $is_pending = ($reading['status'] === 'pending');
                    ?>
                        <tr>
                            <td><?php echo esc_html($reading['user_name']); ?></td>
                            <td>
                                <?php 
                                $cover_url = get_the_post_thumbnail_url($reading['book_id'], 'thumbnail');
                                if (!$cover_url) $cover_url = get_post_meta($reading['book_id'], '_bm_cover_hotlink', true);
                                if ($cover_url): ?>
                                    <img src="<?php echo esc_url($cover_url); ?>" style="width:40px;height:56px;object-fit:cover;border-radius:3px;margin-right:8px;vertical-align:middle;" alt="" />
                                <?php else: ?>
                                    <div style="width:40px;height:56px;background:#f0f0f0;border-radius:3px;display:inline-block;vertical-align:middle;margin-right:8px;text-align:center;line-height:56px;font-size:8px;color:#999;"><?php _e('Sem capa', 'book-manager'); ?></div>
                                <?php endif; ?>
                                <?php echo esc_html($reading['book_title']); ?>
                            </td>
                            <td><?php echo $reading['rating'] > 0 ? str_repeat('★', $reading['rating']) . str_repeat('☆', 5 - $reading['rating']) : '—'; ?></td>
                            <td><?php echo esc_html($reading['review']); ?></td>
                            <td>
                                <?php if (!empty($reading['video_url'])): ?>
                                    <a href="<?php echo esc_url($reading['video_url']); ?>" target="_blank">🎬</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <?php $settings = bm_get_settings(); ?>
                            <?php if ($settings['xp_enabled'] === '1' && $is_pending): ?>
                            <td>
                                <input type="number" name="bm_xp_reading" value="0" min="0" max="100" style="width:60px;padding:4px;text-align:center;" title="<?php _e('Nota da leitura', 'book-manager'); ?>" form="approve-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>" />
                            </td>
                            <td>
                                <input type="number" name="bm_xp_review" value="0" min="0" max="100" style="width:60px;padding:4px;text-align:center;" title="<?php _e('Nota da resenha', 'book-manager'); ?>" form="approve-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>" />
                            </td>
                            <td>
                                <input type="number" name="bm_xp_video" value="0" min="0" max="100" style="width:60px;padding:4px;text-align:center;" title="<?php _e('Nota do vídeo', 'book-manager'); ?>" form="approve-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>" />
                            </td>
                            <?php elseif ($settings['xp_enabled'] === '1' && !$is_pending): ?>
                            <td colspan="3" style="color:#999;">—</td>
                            <?php endif; ?>
                            <td><?php echo date('d/m/Y', strtotime($reading['date'])); ?></td>
                            <td>
                                <?php if ($is_pending): ?>
                                <form method="post" style="display:inline;" id="approve-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>">
                                    <?php wp_nonce_field('bm_reading_action', 'bm_reading_nonce'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $reading['user_id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $reading['book_id']; ?>">
                                    <label style="margin-right:10px;"><input type="checkbox" name="bm_featured_review" value="1" form="approve-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>" /> ⭐ <?php _e('Destacar', 'book-manager'); ?></label>
                                    <button type="submit" name="bm_reading_action" value="approve" class="button button-primary"><?php _e('Aprovar', 'book-manager'); ?></button>
                                    <button type="submit" name="bm_reading_action" value="reject" class="button"><?php _e('Rejeitar', 'book-manager'); ?></button>
                                </form>
                                <?php else: ?>
                                <form method="post" style="display:inline;" id="unapprove-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>">
                                    <?php wp_nonce_field('bm_reading_action', 'bm_reading_nonce'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $reading['user_id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $reading['book_id']; ?>">
                                    <button type="submit" name="bm_reading_action" value="unapprove" class="button button-small" style="background:#ffc107;color:#111;border-color:#ffc107;"><?php _e('Desaprovar', 'book-manager'); ?></button>
                                </form>
                                <form method="post" style="display:inline;" id="delete-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>" onsubmit="return confirm('<?php _e('Excluir esta ficha permanentemente?', 'book-manager'); ?>');">
                                    <?php wp_nonce_field('bm_reading_action', 'bm_reading_nonce'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $reading['user_id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $reading['book_id']; ?>">
                                    <button type="submit" name="bm_reading_action" value="delete" class="button button-small" style="background:#dc3545;color:#fff;border-color:#dc3545;"><?php _e('Excluir', 'book-manager'); ?></button>
                                </form>
                                <?php endif; ?>
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
// FASE 10D: XP E MEDALHAS (BADGES)
// ==========================================
function bm_add_xp($user_id, $amount, $reason = '') {
    $current_xp = intval(get_user_meta($user_id, '_bm_xp', true));
    $new_xp = $current_xp + $amount;
    update_user_meta($user_id, '_bm_xp', $new_xp);
    
    $xp_history = get_user_meta($user_id, '_bm_xp_history', true) ?: array();
    $xp_history[] = array(
        'amount' => $amount,
        'reason' => $reason,
        'date' => current_time('mysql'),
        'total' => $new_xp,
    );
    update_user_meta($user_id, '_bm_xp_history', $xp_history);
    
    bm_check_badges($user_id);
    
    return $new_xp;
}

function bm_get_student_rank($user_id) {
    $students = get_users(array('role' => 'bm_student'));
    $ranking = array();
    
    foreach ($students as $student) {
        $xp = intval(get_user_meta($student->ID, '_bm_xp', true));
        $ranking[] = array('user_id' => $student->ID, 'xp' => $xp);
    }
    
    usort($ranking, function($a, $b) {
        return $b['xp'] - $a['xp'];
    });
    
    $position = 0;
    $total = count($ranking);
    $total_xp = 0;
    
    foreach ($ranking as $i => $r) {
        $total_xp += $r['xp'];
        if ($r['user_id'] == $user_id) {
            $position = $i + 1;
        }
    }
    
    $average = $total > 0 ? round($total_xp / $total) : 0;
    $my_xp = bm_get_xp($user_id);
    $percentile = $total > 0 ? round((($total - $position) / $total) * 100) : 0;
    
    return array(
        'position' => $position,
        'total' => $total,
        'xp' => $my_xp,
        'average' => $average,
        'percentile' => $percentile,
    );
}

function bm_get_xp($user_id) {
    return intval(get_user_meta($user_id, '_bm_xp', true));
}

function bm_check_badges($user_id) {
    $badges = get_user_meta($user_id, '_bm_badges', true) ?: array();
    $new_badges = array();
    
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    $books_read = 0;
    $discipline_counts = array();
    
    foreach ($loan_history as $loan) {
        if ($loan['status'] === 'returned') {
            $books_read++;
            $disciplines = wp_get_post_terms($loan['book_id'], 'bm_discipline', array('fields' => 'names'));
            foreach ($disciplines as $d) {
                $discipline_counts[$d] = isset($discipline_counts[$d]) ? $discipline_counts[$d] + 1 : 1;
            }
        }
    }
    
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    $video_count = 0;
    foreach ($reading_log as $log) {
        if ($log['status'] === 'approved' && !empty($log['video_url'])) {
            $video_count++;
        }
    }
    
    if ($books_read >= 5 && !in_array('rato_biblioteca', $badges)) {
        $badges[] = 'rato_biblioteca';
        $new_badges[] = 'rato_biblioteca';
    }
    
    if ($books_read >= 15 && !in_array('leitor_voraz', $badges)) {
        $badges[] = 'leitor_voraz';
        $new_badges[] = 'leitor_voraz';
    }
    
    foreach ($discipline_counts as $discipline => $count) {
        $badge_key = 'mestre_' . sanitize_key($discipline);
        if ($count >= 10 && !in_array($badge_key, $badges)) {
            $badges[] = $badge_key;
            $new_badges[] = $badge_key;
        }
    }
    
    if ($video_count >= 5 && !in_array('critico_cinema', $badges)) {
        $badges[] = 'critico_cinema';
        $new_badges[] = 'critico_cinema';
    }
    
    if (!empty($new_badges)) {
        update_user_meta($user_id, '_bm_badges', $badges);
    }
    
    return $new_badges;
}

function bm_get_badge_info($badge_key) {
    $badges = array(
        'rato_biblioteca' => array('name' => __('Rato de Biblioteca', 'book-manager'), 'icon' => '🐭', 'desc' => __('Leu 5 livros', 'book-manager')),
        'leitor_voraz' => array('name' => __('Leitor Voraz', 'book-manager'), 'icon' => '📚', 'desc' => __('Leu 15 livros', 'book-manager')),
        'critico_cinema' => array('name' => __('Crítico de Cinema', 'book-manager'), 'icon' => '🎬', 'desc' => __('5 vídeo-resenhas', 'book-manager')),
    );
    
    if (strpos($badge_key, 'mestre_') === 0) {
        return array('name' => __('Mestre das Ciências', 'book-manager'), 'icon' => '🏆', 'desc' => __('10 livros na mesma disciplina', 'book-manager'));
    }
    
    return isset($badges[$badge_key]) ? $badges[$badge_key] : array('name' => $badge_key, 'icon' => '🏅', 'desc' => '');
}

function bm_badges_shortcode() {
    if (!is_user_logged_in()) return '';
    $user_id = get_current_user_id();
    $badges = get_user_meta($user_id, '_bm_badges', true) ?: array();
    
    if (empty($badges)) return '<p>' . __('Nenhuma medalha conquistada ainda.', 'book-manager') . '</p>';
    
    ob_start();
    ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0;">
        <?php foreach ($badges as $badge_key): 
            $info = bm_get_badge_info($badge_key);
        ?>
            <div style="background:#fff8e1;padding:10px 15px;border-radius:8px;text-align:center;border:1px solid #ffc107;" title="<?php echo esc_attr($info['desc']); ?>">
                <div style="font-size:28px;"><?php echo $info['icon']; ?></div>
                <div style="font-size:11px;font-weight:bold;margin-top:3px;"><?php echo esc_html($info['name']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bm_badges', 'bm_badges_shortcode');

// ==========================================
// FASE 27: PERFIL PÚBLICO DO LEITOR
// ==========================================
function bm_reader_profile_shortcode($atts) {
    $atts = shortcode_atts(array('id' => 0), $atts);
    $user_id = intval($atts['id']);
    
    if (!$user_id && isset($_GET['id'])) {
        $user_id = intval($_GET['id']);
    }
    
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $student = get_userdata($user_id);
    if (!$student || !in_array('bm_student', (array) $student->roles)) {
        return '<p>' . __('Leitor não encontrado.', 'book-manager') . '</p>';
    }
    
    
    $profile_public = get_user_meta($user_id, '_bm_profile_public', true);
    if ($profile_public !== '1' && get_current_user_id() !== $user_id && !current_user_can('manage_options')) {
        return '<p>' . __('Este perfil é privado.', 'book-manager') . '</p>';
    }

    $xp = bm_get_xp($user_id);
    $badges = get_user_meta($user_id, '_bm_badges', true) ?: array();
    $group = get_user_meta($user_id, 'bm_student_group', true);
    $avatar = get_avatar_url($user_id, array('size' => 100));
    
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    $read_books = array();
    foreach ($loan_history as $loan) {
        if ($loan['status'] === 'returned') {
            $read_books[] = array(
                'id' => $loan['book_id'],
                'title' => get_the_title($loan['book_id']),
                'cover' => get_the_post_thumbnail_url($loan['book_id'], 'thumbnail'),
            );
        }
    }
    
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    $approved_reviews = array();
    foreach ($reading_log as $log) {
        if ($log['status'] === 'approved' && !empty($log['review'])) {
            $log['book_title'] = get_the_title($log['book_id']);
            $approved_reviews[] = $log;
        }
    }
    
    ob_start();
    ?>
    <div style="max-width:700px;margin:20px auto;">
        <div style="text-align:center;margin-bottom:20px;">
            <?php $profile_photo = get_user_meta($user_id, '_bm_profile_photo', true); ?>
            <?php if ($profile_photo): ?>
                <img src="<?php echo esc_url($profile_photo); ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;" alt="" />
            <?php elseif ($avatar): ?>
                <img src="<?php echo esc_url($avatar); ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;" alt="" />
            <?php else: ?>
                <div style="width:100px;height:100px;border-radius:50%;background:#eee;display:inline-flex;align-items:center;justify-content:center;font-size:40px;">👤</div>
            <?php endif; ?>
            <h2 style="margin:10px 0 5px 0;"><?php echo esc_html($student->display_name); ?></h2>
            <?php if ($group): ?><p style="color:#666;"><?php echo esc_html($group); ?></p><?php endif; ?>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:20px;">
            <div style="background:#f9f9f9;padding:10px;border-radius:6px;text-align:center;">
                <strong style="font-size:22px;"><?php echo $xp; ?></strong><br><small>XP</small>
            </div>
            <div style="background:#f9f9f9;padding:10px;border-radius:6px;text-align:center;">
                <strong style="font-size:22px;"><?php echo count($badges); ?></strong><br><small>Medalhas</small>
            </div>
            <div style="background:#f9f9f9;padding:10px;border-radius:6px;text-align:center;">
                <strong style="font-size:22px;"><?php echo count($read_books); ?></strong><br><small>Livros lidos</small>
            </div>
            <div style="background:#f9f9f9;padding:10px;border-radius:6px;text-align:center;">
                <strong style="font-size:22px;"><?php echo count($approved_reviews); ?></strong><br><small>Resenhas</small>
            </div>
        </div>
        
        <?php if (!empty($badges)): ?>
            <h3><?php _e('Medalhas', 'book-manager'); ?></h3>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
                <?php foreach ($badges as $badge_key): $info = bm_get_badge_info($badge_key); ?>
                    <span style="background:#fff8e1;padding:5px 12px;border-radius:20px;font-size:13px;border:1px solid #ffc107;"><?php echo $info['icon']; ?> <?php echo esc_html($info['name']); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($read_books)): ?>
            <h3><?php _e('Livros Lidos', 'book-manager'); ?></h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:10px;margin-bottom:20px;">
                <?php foreach (array_slice($read_books, 0, 20) as $book): ?>
                    <a href="<?php echo get_permalink($book['id']); ?>" title="<?php echo esc_attr($book['title']); ?>" style="text-decoration:none;">
                        <?php if ($book['cover']): ?>
                            <img src="<?php echo esc_url($book['cover']); ?>" style="width:100%;height:110px;object-fit:cover;border-radius:4px;" alt="<?php echo esc_attr($book['title']); ?>" />
                        <?php else: ?>
                            <div style="width:100%;height:110px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:11px;"><?php _e('Sem capa', 'book-manager'); ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($approved_reviews)): ?>
            <h3><?php _e('Resenhas', 'book-manager'); ?></h3>
            <?php foreach (array_reverse($approved_reviews) as $review): 
                $cover = get_the_post_thumbnail_url($review['book_id'], 'thumbnail');
            ?>
                <div style="background:#f9f9f9;padding:12px;border-radius:6px;margin-bottom:10px;display:flex;gap:12px;align-items:start;">
                    <a href="<?php echo get_permalink($review['book_id']); ?>" style="flex-shrink:0;">
                        <?php if ($cover): ?>
                            <img src="<?php echo esc_url($cover); ?>" style="width:60px;height:85px;object-fit:cover;border-radius:4px;" alt="" />
                        <?php else: ?>
                            <div style="width:60px;height:85px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:10px;"><?php _e('Sem capa', 'book-manager'); ?></div>
                        <?php endif; ?>
                    </a>
                    <div style="flex:1;">
                        <strong><a href="<?php echo get_permalink($review['book_id']); ?>" style="color:#111;text-decoration:none;"><?php echo esc_html($review['book_title']); ?></a></strong>
                        <?php if ($review['rating'] > 0): ?>
                            <span style="color:#ffc107;display:block;"><?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?></span>
                        <?php endif; ?>
                        <p style="margin:5px 0;color:#555;"><?php echo esc_html($review['review']); ?></p>
                        <small style="color:#999;"><?php echo date('d/m/Y', strtotime($review['date'])); ?></small>
                    </div>
                    <?php if (!empty($review['video_url'])): ?>
                        <?php 
                        $embed_url = '';
                        if (strpos($review['video_url'], 'youtube.com') !== false || strpos($review['video_url'], 'youtu.be') !== false) {
                            preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $review['video_url'], $matches);
                            if (!empty($matches[1])) $embed_url = 'https://www.youtube.com/embed/' . $matches[1];
                        } elseif (strpos($review['video_url'], 'tiktok.com') !== false) {
                            preg_match('/video\/(\d+)/', $review['video_url'], $matches);
                            if (!empty($matches[1])) $embed_url = 'https://www.tiktok.com/embed/v2/' . $matches[1];
                        } elseif (strpos($review['video_url'], 'instagram.com') !== false) {
                            $embed_url = $review['video_url'] . 'embed/';
                        }
                        ?>
                        <?php if ($embed_url): ?>
                            <iframe src="<?php echo esc_url($embed_url); ?>" style="width:100%;aspect-ratio:16/9;border:none;border-radius:4px;margin-top:8px;" allowfullscreen></iframe>
                        <?php else: ?>
                            <p style="margin:5px 0;font-size:13px;">🎬 <a href="<?php echo esc_url($review['video_url']); ?>" target="_blank"><?php _e('Ver vídeo-resenha', 'book-manager'); ?></a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bm_reader_profile', 'bm_reader_profile_shortcode');

// ==========================================
// FASE 12J-T4/T5/T6: PÁGINA INDIVIDUAL DO ALUNO
// ==========================================
function bm_handle_student_export() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!isset($_POST['bm_export_student']) || !isset($_POST['bm_student_detail_nonce'])) return;
    if (!wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) return;
    
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $student = get_userdata($student_id);
    if (!$student) return;
    
    $loan_history = get_user_meta($student_id, '_bm_loan_history', true) ?: array();
    $reading_log = get_user_meta($student_id, '_bm_reading_log', true) ?: array();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="historico_' . sanitize_user($student->display_name) . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Tipo', 'Livro', 'Data', 'Status', 'Detalhes'), ';');
    
    foreach ($loan_history as $loan) {
        fputcsv($output, array(
            'Empréstimo',
            get_the_title($loan['book_id']),
            $loan['loan_date'],
            $loan['status'],
            isset($loan['due_date']) ? 'Devolução: ' . $loan['due_date'] : '',
        ), ';');
    }
    foreach ($reading_log as $log) {
        fputcsv($output, array(
            'Ficha de Leitura',
            get_the_title($log['book_id']),
            $log['date'],
            $log['status'],
            'Nota: ' . ($log['rating'] ?? '—'),
        ), ';');
    }
    fclose($output);
    exit;
}
add_action('admin_init', 'bm_handle_student_export');

function bm_add_student_detail_page() {
    add_submenu_page(null, __('Detalhes do Aluno', 'book-manager'), __('Detalhes do Aluno', 'book-manager'), 'edit_bm_books', 'bm_student_detail', 'bm_render_student_detail_page');
}
add_action('admin_menu', 'bm_add_student_detail_page');

function bm_render_student_detail_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $student = get_userdata($student_id);
    if (!$student) {
        echo '<div class="wrap"><p>' . __('Aluno não encontrado.', 'book-manager') . '</p></div>';
        return;
    }
    
    $msg = '';
    
    if (isset($_POST['bm_return_from_detail']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        $return_book_id = intval($_POST['bm_return_book_id']);
        $return_user_id = intval($_POST['bm_return_user_id']);
        $result = bm_return_book($return_book_id, $return_user_id);
        if (isset($result['error'])) {
            $msg = '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        } else {
            $msg = '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }

    if (isset($_POST['bm_apply_manual_penalty']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        $type = sanitize_text_field($_POST['bm_manual_penalty_type']);
        $value = floatval($_POST['bm_manual_penalty_value']);
        $note = sanitize_text_field($_POST['bm_manual_penalty_note']);
        $penalty = array('type' => $type, 'value' => $value, 'note' => $note);
        bm_apply_penalty($student_id, $penalty);
        $msg = '<div class="notice notice-success"><p>' . __('Penalidade aplicada!', 'book-manager') . '</p></div>';
    }

    if (isset($_POST['bm_save_notes']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        update_user_meta($student_id, '_bm_internal_notes', sanitize_textarea_field($_POST['bm_internal_notes']));
        $msg = '<div class="notice notice-success"><p>' . __('Observações salvas.', 'book-manager') . '</p></div>';
    }

    if (isset($_POST['bm_save_student_data']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        $user_fields = get_option('bm_user_dynamic_fields', array());
        foreach ($user_fields as $field_name => $info) {
            $meta_key = '_bm_user_' . sanitize_key($field_name);
            $post_key = 'bm_edit_' . $meta_key;
            if (isset($_POST[$post_key])) {
                update_user_meta($student_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }
        $nome_key = '_bm_user_' . sanitize_key('Nome completo');
        $email_key = '_bm_user_' . sanitize_key('E-mail');
        $novo_nome = get_user_meta($student_id, $nome_key, true);
        $novo_email = get_user_meta($student_id, $email_key, true);
        $wp_update = array('ID' => $student_id);
        if (!empty($novo_nome)) $wp_update['display_name'] = $novo_nome;
        if (!empty($novo_email)) $wp_update['user_email'] = $novo_email;
        if (count($wp_update) > 1) wp_update_user($wp_update);
        $msg = '<div class="notice notice-success"><p>' . __('Dados do aluno atualizados!', 'book-manager') . '</p></div>';
    }
    
    $user_fields = get_option('bm_user_dynamic_fields', array());
    $notes = get_user_meta($student_id, '_bm_internal_notes', true);
    $xp = bm_get_xp($student_id);
    $badges = get_user_meta($student_id, '_bm_badges', true) ?: array();
    $loan_history = get_user_meta($student_id, '_bm_loan_history', true) ?: array();
    $reading_log = get_user_meta($student_id, '_bm_reading_log', true) ?: array();
    $status = get_user_meta($student_id, 'bm_approval_status', true) ?: 'approved';
    $phone = get_user_meta($student_id, '_bm_user_' . sanitize_key('Telefone'), true);
    
    $active_loans = 0; $overdue_count = 0;
    $loan_details = array();
    foreach ($loan_history as $loan) {
        $loan['book_title'] = get_the_title($loan['book_id']);
        $loan['is_overdue'] = ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < time());
        if ($loan['status'] === 'active') {
            $active_loans++;
            if ($loan['is_overdue']) $overdue_count++;
        }
        $loan_details[] = $loan;
    }
    usort($loan_details, function($a, $b) {
        $date_a = isset($a['loan_date']) ? strtotime($a['loan_date']) : (isset($a['date']) ? strtotime($a['date']) : 0);
        $date_b = isset($b['loan_date']) ? strtotime($b['loan_date']) : (isset($b['date']) ? strtotime($b['date']) : 0);
        return $date_b - $date_a;
    });
    
    ?>
    <div class="wrap" style="max-width:900px;">
        <h1><?php _e('Detalhes do Aluno', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        
        <p><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students'); ?>">← <?php _e('Voltar para lista', 'book-manager'); ?></a></p>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin:15px 0;">
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo $xp; ?></h3>
                <p style="margin:5px 0 0 0;color:#666;">XP</p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo count($badges); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;">Medalhas</p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo $active_loans; ?></h3>
                <p style="margin:5px 0 0 0;color:#666;">Empréstimos ativos</p>
            </div>
            <div style="background:<?php echo $overdue_count > 0 ? '#fff3f3' : '#f9f9f9'; ?>;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;color:<?php echo $overdue_count > 0 ? '#dc3545' : '#111'; ?>;"><?php echo $overdue_count; ?></h3>
                <p style="margin:5px 0 0 0;color:#666;">Em atraso</p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo count($reading_log); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;">Fichas de leitura</p>
            </div>
        </div>
        
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:300px;">
                <h2>👤 <?php echo esc_html($student->display_name); ?></h2>
                <?php $profile_photo = get_user_meta($student_id, '_bm_profile_photo', true); ?>
                <?php if ($profile_photo): ?>
                    <div style="text-align:center;margin-bottom:10px;">
                        <img src="<?php echo esc_url($profile_photo); ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:2px solid #ddd;" alt="" />
                    </div>
                <?php endif; ?>
                <p><strong>E-mail:</strong> <?php echo esc_html($student->user_email); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html($status); ?></p>
                
                <form method="post" id="bm-edit-student-form">
                    <?php wp_nonce_field('bm_student_detail_action', 'bm_student_detail_nonce'); ?>
                    <?php foreach ($user_fields as $field_name => $info): 
                        $meta_key = '_bm_user_' . sanitize_key($field_name);
                        $value = get_user_meta($student_id, $meta_key, true);
                        $name_lower = mb_strtolower(trim($field_name));
                        if (empty($value) && in_array($name_lower, array('nome completo', 'nome'))) $value = $student->display_name;
                        if (empty($value) && in_array($name_lower, array('e-mail', 'email'))) $value = $student->user_email;
                    ?>
                        <p>
                            <strong><?php echo esc_html($field_name); ?>:</strong>
                            <?php if ($info['type'] === 'textarea'): ?>
                                <textarea name="bm_edit_<?php echo esc_attr($meta_key); ?>" style="width:100%;max-width:300px;padding:4px 8px;margin-top:2px;" rows="3"><?php echo esc_textarea($value); ?></textarea>
                            <?php else: ?>
                                <input type="<?php echo $info['type'] === 'email' ? 'email' : 'text'; ?>" name="bm_edit_<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>" style="width:100%;max-width:300px;padding:4px 8px;margin-top:2px;" />
                            <?php endif; ?>
                        </p>
                    <?php endforeach; ?>
                    <p style="margin-top:10px;">
                        <button type="submit" name="bm_save_student_data" class="button button-primary"><?php _e('Salvar Alterações', 'book-manager'); ?></button>
                    </p>
                </form>
                
                <?php if ($phone): ?>
                    <p><?php echo bm_whatsapp_button($phone, '', '📱 WhatsApp'); ?></p>
                <?php endif; ?>
            </div>
            
        <?php
        $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
        $return_logs = array();
        foreach ($all_books as $book) {
            $logs = get_post_meta($book->ID, '_bm_return_log', true) ?: array();
            foreach ($logs as $log) {
                if ($log['user_id'] == $student_id) {
                    $log['book_title'] = $book->post_title;
                    $return_logs[] = $log;
                }
            }
        }
        ?>
        <?php if (!empty($return_logs)): ?>
            <h2>📋 <?php _e('Histórico de Devoluções', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Condição', 'book-manager'); ?></th>
                        <th><?php _e('Observação', 'book-manager'); ?></th>
                        <th><?php _e('Data', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($return_logs) as $log): 
                        $condition_label = $log['condition'] === 'good' ? '✅ ' . __('Bom', 'book-manager') : ($log['condition'] === 'acceptable' ? '⚠️ ' . __('Aceitável', 'book-manager') : '❌ ' . __('Danificado', 'book-manager'));
                    ?>
                        <tr>
                            <td><?php echo esc_html($log['book_title']); ?></td>
                            <td><?php echo $condition_label; ?></td>
                            <td><?php echo esc_html(isset($log['note']) ? $log['note'] : '—'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($log['date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php $penalties = get_user_meta($student_id, '_bm_penalties', true) ?: array(); ?>
        <?php if (!empty($penalties)): ?>
            <h2>🚫 <?php _e('Penalidades', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Tipo', 'book-manager'); ?></th>
                        <th><?php _e('Valor', 'book-manager'); ?></th>
                        <th><?php _e('Data', 'book-manager'); ?></th>
                        <th><?php _e('Status', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($penalties) as $p): 
                        $type_label = $p['type'] === 'warning' ? __('Advertência', 'book-manager') : ($p['type'] === 'suspension' ? __('Suspensão', 'book-manager') : __('Multa', 'book-manager'));
                        $value_display = $p['type'] === 'fine' ? 'R$ ' . number_format($p['value'], 2, ',', '.') : ($p['type'] === 'suspension' ? $p['value'] . ' ' . __('dias', 'book-manager') : '—');
                        $is_active = get_user_meta($student_id, '_bm_penalty_active', true) === '1';
                    ?>
                        <tr>
                            <td><?php echo $type_label; ?></td>
                            <td><?php echo esc_html(isset($p['note']) ? $p['note'] : '—'); ?></td>
                            <td><?php echo $value_display; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['date'])); ?></td>
                            <td>
                                <?php echo $is_active ? '🚫 ' . __('Ativa', 'book-manager') : '✅ ' . __('Cumprida', 'book-manager'); ?>
                                <?php if ($is_active): ?>
                                    <div style="margin-top:5px;">
                                        <button type="button" class="button button-small bm-revoke-penalty" data-student="<?php echo $student_id; ?>" data-index="<?php echo count($penalties) - 1; ?>" style="background:#dc3545;color:#fff;border-color:#dc3545;"><?php _e('Revogar', 'book-manager'); ?></button>
                                        <button type="button" class="button button-small bm-alter-penalty" data-student="<?php echo $student_id; ?>" data-index="<?php echo count($penalties) - 1; ?>" data-type="<?php echo $p['type']; ?>" data-value="<?php echo $p['value']; ?>" style="background:#ffc107;color:#111;border-color:#ffc107;"><?php _e('Alterar', 'book-manager'); ?></button>
                                        <?php if ($p['type'] === 'fine'): ?>
                                            <button type="button" class="button button-small bm-pay-penalty" data-student="<?php echo $student_id; ?>" data-index="<?php echo count($penalties) - 1; ?>" style="background:#46b450;color:#fff;border-color:#46b450;"><?php _e('Quitar', 'book-manager'); ?></button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>    

    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bm-revoke-penalty')) {
            if (!confirm('<?php _e('Revogar esta penalidade? O aluno será liberado imediatamente.', 'book-manager'); ?>')) return;
            var btn = e.target;
            btn.disabled = true;
            btn.textContent = '...';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) { location.reload(); }
                else { alert(r.message); btn.disabled = false; btn.textContent = '<?php _e('Revogar', 'book-manager'); ?>'; }
            };
            xhr.send('action=bm_manage_penalty&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>&student_id=' + btn.getAttribute('data-student') + '&penalty_index=' + btn.getAttribute('data-index') + '&penalty_action=revoke');
        }
        
        if (e.target.classList.contains('bm-pay-penalty')) {
            if (!confirm('<?php _e('Confirmar quitação da multa? O aluno será liberado.', 'book-manager'); ?>')) return;
            var btn = e.target;
            btn.disabled = true;
            btn.textContent = '...';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) { location.reload(); }
                else { alert(r.message); btn.disabled = false; btn.textContent = '<?php _e('Quitar', 'book-manager'); ?>'; }
            };
            xhr.send('action=bm_manage_penalty&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>&student_id=' + btn.getAttribute('data-student') + '&penalty_index=' + btn.getAttribute('data-index') + '&penalty_action=pay');
        }
        
        if (e.target.classList.contains('bm-alter-penalty')) {
            var btn = e.target;
            var type = btn.getAttribute('data-type');
            var current = btn.getAttribute('data-value');
            var label = type === 'suspension' ? '<?php _e('Nova duração (dias):', 'book-manager'); ?>' : '<?php _e('Novo valor (R$):', 'book-manager'); ?>';
            var newVal = prompt(label, current);
            if (!newVal || isNaN(newVal) || parseFloat(newVal) <= 0) return;
            if (!confirm('<?php _e('Alterar penalidade?', 'book-manager'); ?>')) return;
            btn.disabled = true;
            btn.textContent = '...';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) { location.reload(); }
                else { alert(r.message); btn.disabled = false; btn.textContent = '<?php _e('Alterar', 'book-manager'); ?>'; }
            };
            xhr.send('action=bm_manage_penalty&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>&student_id=' + btn.getAttribute('data-student') + '&penalty_index=' + btn.getAttribute('data-index') + '&penalty_action=alter&new_value=' + newVal);
        }
    });
    </script>

            <div style="flex:1;min-width:300px;">

        <h2>🚫 <?php _e('Aplicar Penalidade Manual', 'book-manager'); ?></h2>
        <form method="post" style="background:#fff8e1;padding:15px;border-radius:8px;border:1px solid #ffc107;margin-bottom:15px;">
            <?php wp_nonce_field('bm_student_detail_action', 'bm_student_detail_nonce'); ?>
            <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                <div>
                    <label><strong><?php _e('Tipo:', 'book-manager'); ?></strong></label>
                    <select name="bm_manual_penalty_type">
                        <option value="warning"><?php _e('Advertência', 'book-manager'); ?></option>
                        <option value="suspension"><?php _e('Suspensão (dias)', 'book-manager'); ?></option>
                        <option value="fine"><?php _e('Multa (R$)', 'book-manager'); ?></option>
                    </select>
                </div>
                <div>
                    <label><strong><?php _e('Valor:', 'book-manager'); ?></strong></label>
                    <input type="number" name="bm_manual_penalty_value" min="0" step="0.01" style="width:100px;" placeholder="0" />
                </div>
                <div>
                    <label><strong><?php _e('Descrição:', 'book-manager'); ?></strong></label>
                    <input type="text" name="bm_manual_penalty_note" style="width:250px;" placeholder="<?php _e('Ex: Livro danificado na página 32', 'book-manager'); ?>" />
                </div>
                <div>
                    <button type="submit" name="bm_apply_manual_penalty" class="button" style="background:#ff9800;color:#fff;border-color:#ff9800;"><?php _e('Aplicar Penalidade', 'book-manager'); ?></button>
                </div>
            </div>
        </form>

                <h2>📝 <?php _e('Observações Internas', 'book-manager'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('bm_student_detail_action', 'bm_student_detail_nonce'); ?>
                    <textarea name="bm_internal_notes" rows="5" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
                    <p style="margin-top:5px;">
                        <button type="submit" name="bm_save_notes" class="button"><?php _e('Salvar Observações', 'book-manager'); ?></button>
                        <button type="submit" name="bm_export_student" class="button" style="float:right;">📥 <?php _e('Exportar Histórico (CSV)', 'book-manager'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        
        <?php if (!empty($loan_details)): ?>
            <h2>📋 <?php _e('Histórico de Empréstimos', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Empréstimo', 'book-manager'); ?></th>
                        <th><?php _e('Devolução', 'book-manager'); ?></th>
                        <th><?php _e('Status', 'book-manager'); ?></th>
                        <th><?php _e('Ação', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loan_details as $loan): 
                        $status_label = '';
                        $status_color = '#666';
                        $row_style = '';
                        if ($loan['status'] === 'active') {
                            if ($loan['is_overdue']) {
                                $status_label = '🔴 ' . __('Atrasado', 'book-manager');
                                $status_color = '#dc3545';
                                $row_style = 'background:#fff3f3;';
                            } else {
                                $status_label = '🔵 ' . __('Emprestado', 'book-manager');
                                $status_color = '#0073aa';
                            }
                        } elseif ($loan['status'] === 'returned') {
                            $status_label = '✅ ' . __('Devolvido', 'book-manager');
                            $status_color = '#46b450';
                        } elseif ($loan['status'] === 'cancelled') {
                            $status_label = '❌ ' . __('Cancelado', 'book-manager');
                            $status_color = '#6c757d';
                        } elseif ($loan['status'] === 'rejected') {
                            $status_label = '⛔ ' . __('Rejeitado', 'book-manager');
                            $status_color = '#dc3545';
                        }
                    ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td><?php echo esc_html($loan['book_title']); ?></td>
                            <td><?php echo isset($loan['loan_date']) ? date('d/m/Y', strtotime($loan['loan_date'])) : '—'; ?></td>
                            <td><?php echo isset($loan['due_date']) ? date('d/m/Y', strtotime($loan['due_date'])) : '—'; ?></td>
                            <td style="color:<?php echo $status_color; ?>;font-weight:bold;"><?php echo $status_label; ?></td>
                            <td>
                                <?php if ($loan['status'] === 'active'): ?>
                                    <button type="button" class="button button-small bm-return-detail-btn" style="background:#46b450;color:#fff;border-color:#46b450;" data-book="<?php echo $loan['book_id']; ?>" data-user="<?php echo $student_id; ?>">📥 <?php _e('Devolver', 'book-manager'); ?></button>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <script>
        document.addEventListener('click', function(e) {
            if (!e.target.classList.contains('bm-return-detail-btn')) return;
            e.preventDefault();
            var self = e.target;
            if (!confirm('Confirmar devolução?')) return;
            self.disabled = true;
            self.textContent = '...';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) {
                    var row = self.closest('tr');
                    if (row) {
                        row.style.opacity = '0.5';
                        var situacaoCell = row.querySelector('td:nth-child(4)');
                        if (situacaoCell) situacaoCell.innerHTML = '<span style="color:#46b450;font-weight:bold;">✅ Devolvido</span>';
                    }
                    self.remove();
                } else {
                    alert(r.message || 'Erro');
                    self.disabled = false;
                    self.textContent = '📥 Devolver';
                }
            };
            xhr.send('action=bm_service_return&book_id=' + self.getAttribute('data-book') + '&user_id=' + self.getAttribute('data-user') + '&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>');
        });
        </script>

        <?php if (!empty($badges)): ?>
            <h2>🏅 <?php _e('Medalhas', 'book-manager'); ?></h2>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($badges as $badge_key): 
                    $info = bm_get_badge_info($badge_key);
                ?>
                    <div style="background:#fff8e1;padding:10px 15px;border-radius:8px;text-align:center;border:1px solid #ffc107;" title="<?php echo esc_attr($info['desc']); ?>">
                        <div style="font-size:28px;"><?php echo $info['icon']; ?></div>
                        <div style="font-size:11px;font-weight:bold;"><?php echo esc_html($info['name']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php 
        $recent_logs = array_slice(array_reverse($reading_log), 0, 5);
        if (!empty($recent_logs)): 
        ?>
            <h2>📝 <?php _e('Últimas Fichas de Leitura', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Data', 'book-manager'); ?></th>
                        <th><?php _e('Nota', 'book-manager'); ?></th>
                        <th><?php _e('XP', 'book-manager'); ?></th>
                        <th><?php _e('Status', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(get_the_title($log['book_id'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($log['date'])); ?></td>
                            <td><?php echo $log['rating'] > 0 ? str_repeat('★', $log['rating']) : '—'; ?></td>
                            <td>
                                <?php 
                                if (isset($log['xp_total'])) {
                                    echo esc_html($log['xp_total']) . ' XP';
                                } elseif (isset($log['xp_awarded']) && $log['xp_awarded']) {
                                    echo __('Sim', 'book-manager');
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo $log['status'] === 'approved' ? '✅' : '⏳'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// FASE 36.1: CONCEDER XP MANUALMENTE
// ==========================================
function bm_award_xp_on_approval($user_id, $book_id, $xp_reading = 0, $xp_review = 0, $xp_video = 0) {
    $total_xp = intval($xp_reading) + intval($xp_review) + intval($xp_video);
    if ($total_xp <= 0) return;
    
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    $already_awarded = false;
    
    foreach ($reading_log as $log) {
        if ($log['book_id'] == $book_id && isset($log['xp_awarded']) && $log['xp_awarded']) {
            $already_awarded = true;
            break;
        }
    }
    
    if (!$already_awarded) {
        foreach ($reading_log as &$log) {
            if ($log['book_id'] == $book_id) {
                $log['xp_awarded'] = true;
                $log['xp_reading'] = intval($xp_reading);
                $log['xp_review'] = intval($xp_review);
                $log['xp_video'] = intval($xp_video);
                $log['xp_total'] = $total_xp;
                break;
            }
        }
        update_user_meta($user_id, '_bm_reading_log', $reading_log);
        
        $reason = __('Livro lido', 'book-manager');
        if ($xp_review > 0) $reason .= ' + resenha';
        if ($xp_video > 0) $reason .= ' + vídeo';
        
        bm_add_xp($user_id, $total_xp, $reason . ': ' . get_the_title($book_id));
    }
}