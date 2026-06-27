<?php
/**
 * Book Manager — Módulo de Dashboards
 * Dashboards do Aluno, Professor e Gestor
 */

defined('ABSPATH') || exit;

// ==========================================
// FASE 9G: DASHBOARD POR PERFIL
// ==========================================
function bm_user_dashboard() {
    if (!is_user_logged_in()) {
        return '<p>' . __('Faça login para acessar seu painel.', 'book-manager') . '</p>';
    }
    
    $role = bm_get_user_role();
    
    if ($role === 'student') return bm_student_dashboard_content();
    if ($role === 'teacher') return bm_teacher_dashboard_content();
    if ($role === 'librarian' || $role === 'admin') return bm_librarian_dashboard_content();
    
    return '<p>' . __('Acesso restrito.', 'book-manager') . '</p>';
}
add_shortcode('bm_dashboard', 'bm_user_dashboard');

function bm_ajax_dashboard_period() {
    if (!is_user_logged_in()) wp_die(json_encode(array('success' => false)));
    
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'all';
    $user_id = get_current_user_id();
    $role = bm_get_user_role();
    
    $now = current_time('timestamp');
    $since = 0;
    switch ($period) {
        case 'month': $since = strtotime('-30 days', $now); break;
        case 'bimester': $since = strtotime('-60 days', $now); break;
        case 'year': $since = strtotime('-365 days', $now); break;
        default: $since = 0;
    }
    
    $cache_key = 'dashboard_' . $role . '_' . $user_id . '_' . $period;
    $cached = bm_get_cached($cache_key);
    if ($cached) wp_die(json_encode($cached));
    
    $data = array();
    
    if ($role === 'student') {
        $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
        $active_loans = 0;
        foreach ($loan_history as $loan) {
            if ($loan['status'] === 'active') $active_loans++;
        }
        $data['active_loans'] = $active_loans;
        $data['xp'] = bm_get_xp($user_id);
        $data['badges'] = get_user_meta($user_id, '_bm_badges', true) ?: array();
    }
    
    if ($role === 'teacher' || $role === 'librarian' || $role === 'admin') {
        $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
        $active_count = 0; $overdue_count = 0;
        foreach ($all_books as $book) {
            $reservations = get_post_meta($book->ID, '_bm_reservations', true);
            if (!is_array($reservations)) continue;
            foreach ($reservations as $r) {
                if ($r['status'] === 'active') {
                    $active_count++;
                    if (isset($r['due_date']) && strtotime($r['due_date']) < time()) $overdue_count++;
                }
            }
        }
        $data['active_loans'] = $active_count;
        $data['overdue_loans'] = $overdue_count;
        $data['total_books'] = count($all_books);
    }
    
    if ($role === 'librarian' || $role === 'admin') {
        $pending = get_users(array('meta_key' => 'bm_approval_status', 'meta_value' => 'pending'));
        $data['pending_approvals'] = count($pending);
    }
    
    $data['success'] = true;
    bm_set_cached($cache_key, $data);
    wp_die(json_encode($data));
}
add_action('wp_ajax_bm_dashboard_period', 'bm_ajax_dashboard_period');

function bm_student_dashboard_content() {
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $period = isset($_GET['bm_period']) ? sanitize_text_field($_GET['bm_period']) : 'all';
    
    $cache_key = 'student_dashboard_' . $user_id . '_' . $period;
    $cached = bm_get_cached($cache_key);
    
    if ($cached) {
        $active_count = $cached['active_count'];
        $xp = $cached['xp'];
        $badges = $cached['badges'];
        $active_loans = $cached['active_loans'];
        $user_reservations = $cached['user_reservations'];
    } else {
        $active_count = bm_get_active_reservation_count($user_id);
        $xp = bm_get_xp($user_id);
        $badges = get_user_meta($user_id, '_bm_badges', true) ?: array();
        
        $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
        $active_loans = array();
        foreach ($loan_history as $loan) {
            if ($loan['status'] === 'active') {
                $loan['book_title'] = get_the_title($loan['book_id']);
                $loan['days_remaining'] = bm_get_days_remaining($loan['due_date']);
                $active_loans[] = $loan;
            }
        }
        
        $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1));
        $user_reservations = array();
        foreach ($all_books as $book) {
            $reservations = get_post_meta($book->ID, '_bm_reservations', true);
            if (!is_array($reservations)) continue;
            foreach ($reservations as $r) {
                if ($r['user_id'] == $user_id && $r['status'] === 'waiting') {
                    $r['book_title'] = $book->post_title;
                    $user_reservations[] = $r;
                }
            }
        }
        
        bm_set_cached($cache_key, array(
            'active_count' => $active_count,
            'xp' => $xp,
            'badges' => $badges,
            'active_loans' => $active_loans,
            'user_reservations' => $user_reservations,
        ));
    }
    
    ob_start();
    ?>
    <div class="bm-dashboard" style="max-width:800px;margin:0 auto;padding:20px;">
        <h1><?php _e('Painel do Aluno', 'book-manager'); ?></h1>
        <p><?php printf(__('Bem-vindo, %s!', 'book-manager'), esc_html($user->display_name)); ?></p>
                <?php
        $profile_photo = get_user_meta($user_id, '_bm_profile_photo', true);
        ?>
        <div style="text-align:center;margin-bottom:15px;">
            <?php if ($profile_photo): ?>
                <img src="<?php echo esc_url($profile_photo); ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #111;" alt="<?php echo esc_attr($user->display_name); ?>" />
            <?php else: ?>
                <div style="width:100px;height:100px;border-radius:50%;background:#eee;display:inline-flex;align-items:center;justify-content:center;font-size:40px;color:#999;">👤</div>
            <?php endif; ?>
            <form id="bm-photo-form" enctype="multipart/form-data" style="margin-top:8px;">
                <?php wp_nonce_field('bm_photo_upload', 'bm_photo_nonce'); ?>
                <input type="file" id="bm-photo-input" name="bm_photo" accept="image/jpeg,image/png,image/webp" style="display:none;" />
                <button type="button" id="bm-photo-btn" class="bm-btn-filter" style="padding:4px 12px;font-size:12px;">📷 <?php echo $profile_photo ? __('Trocar foto', 'book-manager') : __('Adicionar foto', 'book-manager'); ?></button>
                <span id="bm-photo-status" style="display:none;margin-left:8px;font-size:12px;"></span>
            </form>
                    <script>
        document.getElementById('bm-photo-btn').addEventListener('click', function() {
            document.getElementById('bm-photo-input').click();
        });
        document.getElementById('bm-photo-input').addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            var formData = new FormData();
            formData.append('bm_photo', file);
            formData.append('action', 'bm_upload_photo');
            formData.append('nonce', document.querySelector('#bm-photo-form [name="bm_photo_nonce"]').value);
            
            var status = document.getElementById('bm-photo-status');
            status.style.display = 'inline';
            status.textContent = 'Enviando...';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) {
                    status.textContent = '✅ ' + r.message;
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    status.textContent = '❌ ' + r.message;
                }
            };
            xhr.send(formData);
        });
        </script>
        </div>

        <?php
        // FASE 36.4: Notificação de suspensão encerrada
        $penalty_active = get_user_meta($user_id, '_bm_penalty_active', true);
        $penalty_until = get_user_meta($user_id, '_bm_penalty_until', true);
        if ($penalty_active === '1' && !empty($penalty_until) && strtotime($penalty_until) < time()) {
            update_user_meta($user_id, '_bm_penalty_active', '0');
            delete_user_meta($user_id, '_bm_penalty_until');
            ?>
            <div style="background:#e8f5e9;padding:15px;border-radius:8px;border-left:4px solid #46b450;margin-bottom:15px;">
                <strong>✅ <?php _e('Sua suspensão foi encerrada!', 'book-manager'); ?></strong>
                <p style="margin:5px 0 0 0;"><?php _e('Você já pode pegar livros novamente.', 'book-manager'); ?></p>
            </div>
            <?php
        }
        ?>
        
        <div style="margin:10px 0;">
            <select id="bm-period-select" onchange="bmLoadPeriod()" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                <option value="all" <?php selected($period, 'all'); ?>><?php _e('Todo o período', 'book-manager'); ?></option>
                <option value="month" <?php selected($period, 'month'); ?>><?php _e('Último mês', 'book-manager'); ?></option>
                <option value="bimester" <?php selected($period, 'bimester'); ?>><?php _e('Último bimestre', 'book-manager'); ?></option>
                <option value="year" <?php selected($period, 'year'); ?>><?php _e('Este ano', 'book-manager'); ?></option>
            </select>
            <span id="bm-period-loading" style="display:none;margin-left:8px;color:#666;"><?php _e('Carregando...', 'book-manager'); ?></span>
        </div>
                
        <div style="background:#f0f7ff;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #2196f3;">
            <h3 style="margin:0 0 10px 0;">🔍 <?php _e('Buscar livro no acervo', 'book-manager'); ?></h3>
            <div style="display:flex;gap:10px;">
                <input type="text" id="bm-quick-search" placeholder="<?php _e('Digite o nome do livro...', 'book-manager'); ?>" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;" />
                <button type="button" id="bm-quick-search-btn" class="bm-btn-filter" style="padding:8px 16px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;"><?php _e('Buscar', 'book-manager'); ?></button>
            </div>
            <div id="bm-quick-results" style="margin-top:10px;display:none;"></div>
        </div>
        
        <script>
        document.getElementById('bm-quick-search-btn').addEventListener('click', bmQuickSearch);
        document.getElementById('bm-quick-search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') bmQuickSearch();
        });
        
        function bmQuickSearch() {
            var query = document.getElementById('bm-quick-search').value.trim();
            if (!query) return;
            
            var results = document.getElementById('bm-quick-results');
            results.style.display = 'block';
            results.innerHTML = '<p style="color:#666;">Buscando...</p>';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.success && r.books.length > 0) {
                        var html = '';
                        r.books.forEach(function(book) {
                            var stockColor = book.available > 0 ? '#46b450' : '#dc3545';
                            var stockIcon = book.available > 0 ? '✅' : '❌';
                            html += '<div style="background:#fff;padding:10px;border-radius:4px;margin-bottom:5px;border:1px solid #eee;">';
                            html += '<strong>' + book.title + '</strong>';
                            if (book.author) html += ' — ' + book.author;
                            html += ' <span style="color:' + stockColor + ';float:right;">' + stockIcon + ' ' + book.available + '/' + book.total + ' disponível(is)</span>';
                            html += '<br><a href="' + book.url + '" style="font-size:12px;">Ver detalhes →</a>';
                            html += '</div>';
                        });
                        results.innerHTML = html;
                    } else {
                        results.innerHTML = '<p style="color:#999;">Nenhum livro encontrado.</p>';
                    }
                } catch(e) {
                    results.innerHTML = '<p style="color:red;">Erro na busca.</p>';
                }
            };
            xhr.send('action=bm_quick_search&query=' + encodeURIComponent(query));
        }
        </script>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin:20px 0;">
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo count($active_loans); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Empréstimos ativos', 'book-manager'); ?></p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo count($user_reservations); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Reservas na fila', 'book-manager'); ?></p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo max(0, 3 - $active_count); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Reservas disponíveis', 'book-manager'); ?></p>
            </div>
            <?php $settings = bm_get_settings(); ?>
            <?php if ($settings['xp_enabled'] === '1'): ?>
            <div style="background:#fff8e1;padding:15px;border-radius:6px;text-align:center;border:1px solid #ffc107;">
                <h3 style="margin:0;font-size:28px;color:#f0ad4e;"><?php echo $xp; ?> XP</h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Pontos acumulados', 'book-manager'); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($settings['xp_enabled'] === '1'): ?>
        <?php $rank = bm_get_student_rank($user_id); ?>
        <?php 
        $medal = ''; $bg_rank = '#e8f5e9'; $border_rank = '#4caf50';
        if ($rank['position'] === 1) { $medal = '🥇'; $bg_rank = '#fff8e1'; $border_rank = '#ffc107'; }
        elseif ($rank['position'] === 2) { $medal = '🥈'; $bg_rank = '#f5f5f5'; $border_rank = '#9e9e9e'; }
        elseif ($rank['position'] === 3) { $medal = '🥉'; $bg_rank = '#fff3e0'; $border_rank = '#ff9800'; }
        ?>
        <div style="background:<?php echo $bg_rank; ?>;padding:15px;border-radius:6px;text-align:center;border:2px solid <?php echo $border_rank; ?>;margin-top:15px;">
            <h3 style="margin:0;font-size:28px;"><?php echo $medal; ?> <?php printf(__('%dº de %d alunos', 'book-manager'), $rank['position'], $rank['total']); ?></h3>
            <p style="margin:5px 0 0 0;color:#666;"><?php _e('Sua posição no ranking', 'book-manager'); ?></p>
            <p style="margin:8px 0 0 0;font-size:13px;color:#333;"><?php printf(__('Você leu mais que %d%% dos alunos!', 'book-manager'), $rank['percentile']); ?></p>
            <div style="background:#ddd;border-radius:10px;height:8px;margin-top:8px;">
                <div style="background:#4caf50;height:8px;border-radius:10px;width:<?php echo $rank['percentile']; ?>%;"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $user_dynamic_fields = get_option('bm_user_dynamic_fields', array());
        $has_user_data = false;
        foreach ($user_dynamic_fields as $field_name => $info) {
            $meta_key = '_bm_user_' . sanitize_key($field_name);
            $value = get_user_meta($user_id, $meta_key, true);
            if (!empty($value)) {
                if (!$has_user_data) {
                    echo '<h2>' . __('Meus Dados', 'book-manager') . '</h2>';
                    echo '<div style="background:#f9f9f9;padding:15px;border-radius:8px;margin-bottom:20px;">';
                    $has_user_data = true;
                }
                echo '<p><strong>' . esc_html($field_name) . ':</strong> ' . esc_html($value) . '</p>';
            }
        }
        if ($has_user_data) echo '</div>';
        ?>
        
        <?php if ($settings['xp_enabled'] === '1' && !empty($badges)): ?>
            <h2><?php _e('Minhas Medalhas', 'book-manager'); ?></h2>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin:10px 0 20px 0;">
                <?php foreach ($badges as $badge_key): 
                    $info = bm_get_badge_info($badge_key);
                ?>
                    <div style="background:#fff8e1;padding:10px 15px;border-radius:8px;text-align:center;border:1px solid #ffc107;" title="<?php echo esc_attr($info['desc']); ?>">
                        <div style="font-size:28px;"><?php echo $info['icon']; ?></div>
                        <div style="font-size:11px;font-weight:bold;margin-top:3px;"><?php echo esc_html($info['name']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($active_loans)): ?>
            <h2><?php _e('Meus Empréstimos', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th><?php _e('Livro', 'book-manager'); ?></th><th><?php _e('Empréstimo', 'book-manager'); ?></th><th><?php _e('Devolução', 'book-manager'); ?></th><th><?php _e('Prazo', 'book-manager'); ?></th><th><?php _e('Ação', 'book-manager'); ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($active_loans as $loan): 
                        $days = $loan['days_remaining'];
                        if ($days > 3) $color = '#46b450'; elseif ($days >= 1) $color = '#f0ad4e'; elseif ($days == 0) $color = '#e6c300'; else $color = '#dc3545';
                    ?>
                        <tr>
                            <td><?php echo esc_html($loan['book_title']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($loan['loan_date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($loan['due_date'])); ?></td>
                            <td style="color:<?php echo $color; ?>;font-weight:bold;">
                                <?php if ($days > 0) printf(__('%d dias restantes', 'book-manager'), $days); elseif ($days == 0) _e('Vence hoje!', 'book-manager'); else printf(__('%d dias atrasado', 'book-manager'), abs($days)); ?>
                            </td>
                            <td>
                                <?php 
                                $queue = 0;
                                $reservations = get_post_meta($loan['book_id'], '_bm_reservations', true) ?: array();
                                foreach ($reservations as $r) {
                                    if ($r['status'] === 'waiting' && $r['user_id'] != $user_id) $queue++;
                                }
                                if ($queue === 0): ?>
                                    <button type="button" class="bm-btn-filter" onclick="bmRenewLoan(<?php echo $loan['book_id']; ?>, <?php echo $user_id; ?>)" style="padding:4px 10px;font-size:12px;">🔄 <?php _e('Renovar', 'book-manager'); ?></button>
                                <?php else: ?>
                                    <span style="font-size:11px;color:#999;"><?php printf(__('%d na fila', 'book-manager'), $queue); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if (!empty($user_reservations)): ?>
            <h2><?php _e('Minhas Reservas', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th><?php _e('Livro', 'book-manager'); ?></th><th><?php _e('Posição', 'book-manager'); ?></th><th><?php _e('Data', 'book-manager'); ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($user_reservations as $res): ?>
                        <tr>
                            <td><?php echo esc_html($res['book_title']); ?></td>
                            <td><?php echo isset($res['position']) ? $res['position'] . 'º' : '—'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($res['date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php 
        // FASE 36.6: Exibir lista de leitura da turma do aluno
        $reading_lists = get_option('bm_reading_lists', array());
        $student_group = get_user_meta($user_id, '_bm_user_' . sanitize_key('Turma'), true);
        if (!empty($student_group) && isset($reading_lists[$student_group])):
            $my_list = $reading_lists[$student_group];
        ?>
            <h2>📚 <?php printf(__('Lista de Leitura — %s', 'book-manager'), esc_html($student_group)); ?></h2>
            <?php if (!empty($my_list['description'])): ?>
                <p style="color:#555;"><?php echo esc_html($my_list['description']); ?></p>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:20px;">
                <?php foreach ($my_list['books'] as $list_book_id):
                    $list_book = get_post($list_book_id);
                    if (!$list_book) continue;
                    $list_cover = get_the_post_thumbnail_url($list_book_id, 'thumbnail');
                    $list_author = get_post_meta($list_book_id, '_bm_author', true);
                ?>
                    <a href="<?php echo get_permalink($list_book_id); ?>" style="text-decoration:none;color:inherit;">
                        <div style="background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                            <?php if ($list_cover): ?>
                                <img src="<?php echo esc_url($list_cover); ?>" style="width:100%;height:160px;object-fit:cover;" alt="<?php echo esc_attr($list_book->post_title); ?>" />
                            <?php else: ?>
                                <div style="width:100%;height:160px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;"><?php _e('Sem capa', 'book-manager'); ?></div>
                            <?php endif; ?>
                            <div style="padding:8px;">
                                <strong style="font-size:12px;"><?php echo esc_html($list_book->post_title); ?></strong>
                                <?php if ($list_author): ?><p style="font-size:10px;color:#666;margin:2px 0;"><?php echo esc_html($list_author); ?></p><?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
        $profile_public = get_user_meta($user_id, '_bm_profile_public', true);
        if (isset($_POST['bm_toggle_profile']) && wp_verify_nonce($_POST['bm_profile_nonce'], 'bm_profile_action')) {
            $new_status = $profile_public === '1' ? '0' : '1';
            update_user_meta($user_id, '_bm_profile_public', $new_status);
            $profile_public = $new_status;
        }
        ?>
        <div style="background:#f9f9f9;padding:12px;border-radius:6px;margin:15px 0;">
            <form method="post" style="display:flex;align-items:center;gap:10px;">
                <?php wp_nonce_field('bm_profile_action', 'bm_profile_nonce'); ?>
                <span><?php echo $profile_public === '1' ? '🌐 ' . __('Seu perfil é público', 'book-manager') : '🔒 ' . __('Seu perfil é privado', 'book-manager'); ?></span>
                <button type="submit" name="bm_toggle_profile" class="bm-btn-filter" style="padding:4px 10px;font-size:12px;">
                    <?php echo $profile_public === '1' ? __('Tornar privado', 'book-manager') : __('Tornar público', 'book-manager'); ?>
                </button>
            </form>
        </div>
        
        <?php $my_penalties = get_user_meta($user_id, '_bm_penalties', true) ?: array(); ?>
        <?php if (!empty($my_penalties)): ?>
            <h2>🚫 <?php _e('Minhas Ocorrências', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th><?php _e('Tipo', 'book-manager'); ?></th><th><?php _e('Descrição', 'book-manager'); ?></th><th><?php _e('Data', 'book-manager'); ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($my_penalties) as $p): 
                        $type_label = $p['type'] === 'warning' ? __('Advertência', 'book-manager') : ($p['type'] === 'suspension' ? __('Suspensão', 'book-manager') : __('Multa', 'book-manager'));
                    ?>
                        <tr>
                            <td><?php echo $type_label; ?></td>
                            <td><?php echo esc_html(isset($p['note']) ? $p['note'] : '—'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
                
        <p style="margin-top:20px;">
            <a href="<?php echo site_url('/sugerir-livro'); ?>" style="color:#111;text-decoration:underline;">📚 <?php _e('Minhas Sugestões de Aquisição', 'book-manager'); ?></a>
        </p>

        <p style="margin-top:5px;">
            <a href="<?php echo site_url('/perfil-do-leitor'); ?>" style="color:#111;text-decoration:underline;">👤 <?php _e('Meu Perfil de Leitor', 'book-manager'); ?></a>
        </p>

        <p style="margin-top:5px;">
            <a href="<?php echo site_url('/minhas-fichas'); ?>" style="color:#111;text-decoration:underline;">📝 <?php _e('Minhas Fichas', 'book-manager'); ?></a>
        </p>

        <script>
    document.querySelectorAll('.bm-return-detail-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var self = this;
            if (!confirm('<?php _e('Confirmar devolução deste livro?', 'book-manager'); ?>')) return;
            self.disabled = true;
            self.textContent = '...';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) {
                    var row = self.closest('tr');
                    if (row) {
                        row.style.opacity = '0.5';
                        var situacaoCell = row.querySelector('td:nth-child(4)');
                        if (situacaoCell) situacaoCell.innerHTML = '<span style="color:#46b450;font-weight:bold;">✅ ' + '<?php _e('Devolvido', 'book-manager'); ?>' + '</span>';
                    }
                    self.remove();
                } else {
                    alert(r.message || 'Erro');
                    self.disabled = false;
                    self.textContent = '📥 <?php _e('Devolver', 'book-manager'); ?>';
                }
            };
            xhr.send('action=bm_service_return&book_id=' + self.getAttribute('data-book') + '&user_id=' + self.getAttribute('data-user') + '&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>');
        });
    });
    </script>

    <script>
    function bmLoadPeriod() {
        var period = document.getElementById('bm-period-select').value;
        var url = new URL(window.location.href);
        url.searchParams.set('bm_period', period);
        window.location.href = url.toString();
    }
    </script>

        <?php if (empty($active_loans) && empty($user_reservations)): ?>
            <p><?php _e('Você não tem empréstimos ou reservas ativas.', 'book-manager'); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bm_teacher_view_student($student_id) {
    if (!is_user_logged_in()) return '';
    
    $user = wp_get_current_user();
    if (!in_array('bm_teacher', (array) $user->roles) && !in_array('bm_librarian', (array) $user->roles) && !current_user_can('manage_options')) return '';
    
    $student = get_userdata($student_id);
    if (!$student || !in_array('bm_student', (array) $student->roles)) return '<p>' . __('Aluno não encontrado.', 'book-manager') . '</p>';
    
    ob_start();
    ?>
    <div style="background:#f9f9f9;padding:15px;border-radius:8px;margin:10px 0;border:1px solid #ddd;">
        <h3 style="margin-top:0;">👤 <?php echo esc_html($student->display_name); ?></h3>
        <?php
        $user_fields = get_option('bm_user_dynamic_fields', array());
        foreach ($user_fields as $field_name => $info):
            $meta_key = '_bm_user_' . sanitize_key($field_name);
            $value = get_user_meta($student_id, $meta_key, true);
            if (!empty($value)):
        ?>
            <p style="margin:5px 0;"><strong><?php echo esc_html($field_name); ?>:</strong> <?php echo esc_html($value); ?></p>
        <?php 
            endif;
        endforeach;
        
        $xp = bm_get_xp($student_id);
        $badges = get_user_meta($student_id, '_bm_badges', true) ?: array();
        ?>
        <p style="margin:5px 0;"><strong>XP:</strong> <?php echo $xp; ?></p>
        <?php if (!empty($badges)): ?>
            <p style="margin:5px 0;"><strong>Medalhas:</strong> <?php echo count($badges); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bm_teacher_dashboard_content() {
    $user = wp_get_current_user();
    $period = isset($_GET['bm_period']) ? sanitize_text_field($_GET['bm_period']) : 'all';
    
    $cache_key = 'teacher_dashboard_' . $period;
    $cached = bm_get_cached($cache_key);
    
    if ($cached) {
        $students_count = $cached['students_count'];
        $active_loans = $cached['active_loans'];
        $overdue_loans = $cached['overdue_loans'];
        $total_books = $cached['total_books'];
    } else {
        $students = get_users(array('role' => 'bm_student'));
        $students_count = count($students);
        
        $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
        $total_books = count($all_books);
        $active_loans = array();
        $overdue_loans = array();
        
        foreach ($all_books as $book) {
            $reservations = get_post_meta($book->ID, '_bm_reservations', true);
            if (!is_array($reservations)) continue;
            foreach ($reservations as $r) {
                if ($r['status'] === 'active') {
                    $student = get_userdata($r['user_id']);
                    $r['book_title'] = $book->post_title;
                    $r['student_name'] = $student ? $student->display_name : '#' . $r['user_id'];
                    $r['student_phone'] = $student ? get_user_meta($student->ID, '_bm_user_' . sanitize_key('Telefone'), true) : '';
                    $r['days_remaining'] = isset($r['due_date']) ? bm_get_days_remaining($r['due_date']) : 0;
                    $r['due_date_formatted'] = isset($r['due_date']) ? date('d/m/Y', strtotime($r['due_date'])) : '—';
                    $active_loans[] = $r;
                    if ($r['days_remaining'] < 0) {
                        $overdue_loans[] = $r;
                    }
                }
            }
        }
        
        usort($active_loans, function($a, $b) {
            return $a['days_remaining'] - $b['days_remaining'];
        });
        
        $cache_data = array(
            'students_count' => $students_count,
            'active_loans' => $active_loans,
            'overdue_loans' => $overdue_loans,
            'total_books' => $total_books,
        );
        bm_set_cached($cache_key, $cache_data);
        $cached = $cache_data;
    }
    
    ob_start();
    ?>
    <div class="bm-dashboard" style="max-width:900px;margin:0 auto;padding:20px;">
        <h1><?php _e('Painel do Professor', 'book-manager'); ?></h1>
        <p><?php printf(__('Bem-vindo, %s!', 'book-manager'), esc_html($user->display_name)); ?></p>
        
        <div style="margin:10px 0;">
            <select id="bm-period-select" onchange="bmLoadPeriodTeacher()" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                <option value="all" <?php selected($period, 'all'); ?>><?php _e('Todo o período', 'book-manager'); ?></option>
                <option value="month" <?php selected($period, 'month'); ?>><?php _e('Último mês', 'book-manager'); ?></option>
                <option value="bimester" <?php selected($period, 'bimester'); ?>><?php _e('Último bimestre', 'book-manager'); ?></option>
                <option value="year" <?php selected($period, 'year'); ?>><?php _e('Este ano', 'book-manager'); ?></option>
            </select>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin:20px 0;">
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo $students_count; ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Alunos', 'book-manager'); ?></p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo count($active_loans); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Empréstimos ativos', 'book-manager'); ?></p>
            </div>
            <div style="background:#fff3f3;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;color:#dc3545;"><?php echo count($overdue_loans); ?></h3>
                <p style="margin:5px 0 0 0;color:#dc3545;"><?php _e('Em atraso', 'book-manager'); ?></p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo $total_books; ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Livros no acervo', 'book-manager'); ?></p>
            </div>
        </div>
        <script>
    function bmLoadPeriodTeacher() {
        var period = document.getElementById('bm-period-select').value;
        var url = new URL(window.location.href);
        url.searchParams.set('bm_period', period);
        window.location.href = url.toString();
    }
    </script>
        <?php if (!empty($active_loans)): ?>
            <h2><?php _e('Monitoramento de Empréstimos', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Aluno', 'book-manager'); ?></th>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Empréstimo', 'book-manager'); ?></th>
                        <th><?php _e('Devolução', 'book-manager'); ?></th>
                        <th><?php _e('Prazo', 'book-manager'); ?></th>
                        <th><?php _e('WhatsApp', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_loans as $loan): 
                        $days = $loan['days_remaining'];
                        if ($days > 3) $color = '#46b450'; elseif ($days >= 1) $color = '#f0ad4e'; elseif ($days == 0) $color = '#e6c300'; else $color = '#dc3545';
                        $row_style = $days < 0 ? 'background:#fff3f3;' : '';
                    ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td><?php echo esc_html($loan['student_name']); ?></td>
                            <td><?php echo esc_html($loan['book_title']); ?></td>
                            <td><?php echo isset($loan['loan_date']) ? date('d/m/Y', strtotime($loan['loan_date'])) : '—'; ?></td>
                            <td><?php echo $loan['due_date_formatted']; ?></td>
                            <td style="color:<?php echo $color; ?>;font-weight:bold;">
                                <?php if ($days > 0) printf(__('%d dias', 'book-manager'), $days); elseif ($days == 0) _e('Vence hoje!', 'book-manager'); else printf(__('%d dias atrasado', 'book-manager'), abs($days)); ?>
                            </td>
                            <td>
                                <?php if (!empty($loan['student_phone'])): ?>
                                    <?php 
                                    $wa_msg = $days < 0 ? bm_get_loan_message($loan['student_name'], $loan['book_title'], $loan['due_date_formatted'], 'overdue') : bm_get_loan_message($loan['student_name'], $loan['book_title'], $loan['due_date_formatted'], 'reminder');
                                    echo bm_whatsapp_button($loan['student_phone'], $wa_msg, 'WhatsApp');
                                    ?>
                                <?php else: ?>
                                    <span style="color:#999;font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('Nenhum empréstimo ativo no momento.', 'book-manager'); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bm_librarian_dashboard_content() {
    $user = wp_get_current_user();
    
    $cache_key = 'librarian_dashboard';
    $cached = bm_get_cached($cache_key);
    
    if ($cached) {
        $total = $cached['total'];
        $active_loans = $cached['active_loans'];
        $overdue_loans = $cached['overdue_loans'];
        $pending_reservations = $cached['pending_reservations'];
        $pending_approvals_count = $cached['pending_approvals_count'];
        $scheduled_count = isset($cached['scheduled_count']) ? $cached['scheduled_count'] : 0;
        $pending_readings_count = isset($cached['pending_readings_count']) ? $cached['pending_readings_count'] : 0;
    } else {
        $total_books = wp_count_posts('bm_book');
        $total = $total_books->publish + $total_books->draft;
        
        $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
        
        $active_loans = array();
        $overdue_loans = array();
        $pending_reservations = array();
        $pending_approvals = get_users(array('meta_key' => 'bm_approval_status', 'meta_value' => 'pending'));
        $pending_approvals_count = count($pending_approvals);
        
        $scheduled_count = 0;
        foreach ($all_books as $book) {
            $bulk = get_post_meta($book->ID, '_bm_bulk_reservation', true);
            if (is_array($bulk)) {
                foreach ($bulk as $br) {
                    if ($br['status'] === 'active' || $br['status'] === 'separated') {
                        $scheduled_count++;
                    }
                }
            }
        }
        
        $pending_readings_count = 0;
        $all_students_for_count = get_users(array('role' => 'bm_student', 'number' => 200));
        foreach ($all_students_for_count as $student) {
            $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
            foreach ($reading_log as $log) {
                if ($log['status'] === 'pending') $pending_readings_count++;
            }
        }
        
        foreach ($all_books as $book) {
            $reservations = get_post_meta($book->ID, '_bm_reservations', true);
            if (!is_array($reservations)) continue;
            foreach ($reservations as $r) {
                $user_data = get_userdata($r['user_id']);
                $r['book_title'] = $book->post_title;
                $r['user_name'] = $user_data ? $user_data->display_name : '#' . $r['user_id'];
                $r['user_phone'] = $user_data ? get_user_meta($user_data->ID, '_bm_user_' . sanitize_key('Telefone'), true) : '';
                
                if ($r['status'] === 'active') {
                    $r['days_remaining'] = isset($r['due_date']) ? bm_get_days_remaining($r['due_date']) : 0;
                    $r['due_date_formatted'] = isset($r['due_date']) ? date('d/m/Y', strtotime($r['due_date'])) : '—';
                    $active_loans[] = $r;
                    if ($r['days_remaining'] < 0) {
                        $overdue_loans[] = $r;
                    }
                } elseif ($r['status'] === 'waiting') {
                    $pending_reservations[] = $r;
                }
            }
        }
        
        usort($active_loans, function($a, $b) {
            return $a['days_remaining'] - $b['days_remaining'];
        });
        
        $cache_data = array(
            'total' => $total,
            'active_loans' => $active_loans,
            'overdue_loans' => $overdue_loans,
            'pending_reservations' => $pending_reservations,
            'pending_approvals_count' => $pending_approvals_count,
            'scheduled_count' => $scheduled_count,
            'pending_readings_count' => $pending_readings_count,
        );
        bm_set_cached($cache_key, $cache_data);
        $cached = $cache_data;
    }
    
    ob_start();
    ?>
    <div class="bm-dashboard" style="max-width:1000px;margin:0 auto;padding:20px;">

        <div style="margin:10px 0;">
            <select id="bm-period-select" onchange="bmLoadPeriodGestor()" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                <option value="all"><?php _e('Todo o período', 'book-manager'); ?></option>
                <option value="month"><?php _e('Último mês', 'book-manager'); ?></option>
                <option value="bimester"><?php _e('Último bimestre', 'book-manager'); ?></option>
                <option value="year"><?php _e('Este ano', 'book-manager'); ?></option>
            </select>
        </div>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:15px;margin:20px 0;">
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo $total; ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Livros no acervo', 'book-manager'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=bm_service_desk&tab=loans&bm_status=active'); ?>" style="text-decoration:none;">
                <div class="bm-dash-card" style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;cursor:pointer;transition:transform 0.15s ease, box-shadow 0.15s ease;">
                    <h3 style="margin:0;font-size:28px;"><?php echo count($active_loans); ?></h3>
                    <p style="margin:5px 0 0 0;color:#666;"><?php _e('Empréstimos ativos', 'book-manager'); ?></p>
                </div>
            </a>
            <a href="<?php echo admin_url('admin.php?page=bm_service_desk&tab=loans&bm_status=overdue'); ?>" style="text-decoration:none;">
                <div class="bm-dash-card" style="background:#fff3f3;padding:15px;border-radius:6px;text-align:center;cursor:pointer;transition:transform 0.15s ease, box-shadow 0.15s ease;">
                    <h3 style="margin:0;font-size:28px;color:#dc3545;"><?php echo count($overdue_loans); ?></h3>
                    <p style="margin:5px 0 0 0;color:#dc3545;"><?php _e('Em atraso', 'book-manager'); ?></p>
                </div>
            </a>
            <a href="<?php echo admin_url('admin.php?page=bm_service_desk&tab=loans&bm_status=waiting'); ?>" style="text-decoration:none;">
                <div class="bm-dash-card" style="background:#fff8e1;padding:15px;border-radius:6px;text-align:center;cursor:pointer;transition:transform 0.15s ease, box-shadow 0.15s ease;">
                    <h3 style="margin:0;font-size:28px;color:#f0ad4e;"><?php echo count($pending_reservations); ?></h3>
                    <p style="margin:5px 0 0 0;color:#f0ad4e;"><?php _e('Reservas pendentes', 'book-manager'); ?></p>
                </div>
            </a>
            <a href="<?php echo admin_url('admin.php?page=bm_students&tab=approve_users'); ?>" style="text-decoration:none;">
                <div class="bm-dash-card" style="background:#e8f5e9;padding:15px;border-radius:6px;text-align:center;cursor:pointer;transition:transform 0.15s ease, box-shadow 0.15s ease;">
                    <h3 style="margin:0;font-size:28px;color:#46b450;"><?php echo $pending_approvals_count; ?></h3>
                    <p style="margin:5px 0 0 0;color:#46b450;"><?php _e('Cadastros pendentes', 'book-manager'); ?></p>
                </div>
            </a>
            <a href="<?php echo admin_url('admin.php?page=bm_service_desk&tab=loans&bm_status=scheduled'); ?>" style="text-decoration:none;">
                <div class="bm-dash-card" style="background:#e3f2fd;padding:15px;border-radius:6px;text-align:center;cursor:pointer;transition:transform 0.15s ease, box-shadow 0.15s ease;">
                    <h3 style="margin:0;font-size:28px;color:#1565c0;"><?php echo $scheduled_count; ?></h3>
                    <p style="margin:5px 0 0 0;color:#1565c0;"><?php _e('Livros Agendados', 'book-manager'); ?></p>
                </div>
            </a>
            <a href="<?php echo admin_url('admin.php?page=bm_students&tab=approve_readings'); ?>" style="text-decoration:none;">
                <div class="bm-dash-card" style="background:#fce4ec;padding:15px;border-radius:6px;text-align:center;cursor:pointer;transition:transform 0.15s ease, box-shadow 0.15s ease;">
                    <h3 style="margin:0;font-size:28px;color:#c62828;"><?php echo $pending_readings_count; ?></h3>
                    <p style="margin:5px 0 0 0;color:#c62828;"><?php _e('Fichas Pendentes', 'book-manager'); ?></p>
                </div>
            </a>
        </div>
        
        <style>
        .bm-dash-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
        </style>        

        <?php
        $current_month = date('m');
        $all_students = get_users(array('role' => 'bm_student', 'number' => 200));
        $birthdays = array();
        foreach ($all_students as $student) {
            $birthdate = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Data de Nascimento'), true);
            if (!empty($birthdate)) {
                $parts = explode('/', $birthdate);
                if (count($parts) === 3 && $parts[1] === $current_month) {
                    $birthdays[] = array(
                        'name' => $student->display_name,
                        'date' => $parts[0] . '/' . $parts[1],
                        'id' => $student->ID,
                    );
                }
            }
        }
        if (!empty($birthdays)): usort($birthdays, function($a, $b) { return intval($a['date']) - intval($b['date']); });
        ?>
        <div style="background:#fff3e0;padding:15px;border-radius:8px;grid-column:1/-1;margin-top:10px;">
            <h3 style="margin:0 0 10px 0;">🎂 <?php _e('Aniversariantes de', 'book-manager'); ?> <?php echo date_i18n('F'); ?></h3>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($birthdays as $b): ?>
                    <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $b['id']); ?>" style="background:#fff;padding:8px 12px;border-radius:20px;text-decoration:none;color:#111;font-size:13px;border:1px solid #ff9800;">
                        <?php echo esc_html($b['name']); ?> <span style="color:#ff9800;"><?php echo $b['date']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin:20px 0;">
    <script>
    function bmLoadPeriodGestor() {
        var period = document.getElementById('bm-period-select').value;
        var url = new URL(window.location.href);
        url.searchParams.set('bm_period', period);
        window.location.href = url.toString();
    }
    </script>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">📚 <?php _e('Gerenciar Livros', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_loans'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">📋 <?php _e('Empréstimos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('users.php?page=bm_approve_users'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">✅ <?php _e('Aprovar Cadastros', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_csv_import'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">📥 <?php _e('Importar CSV', 'book-manager'); ?></a>
                    <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_acquisition_suggestions'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">💡 <?php _e('Sugestões de Aquisição', 'book-manager'); ?></a>
        </div>
        
        <?php if (!empty($overdue_loans)): ?>
            <h2 style="color:#dc3545;">🔴 <?php _e('Em Atraso', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Aluno', 'book-manager'); ?></th>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Devolução', 'book-manager'); ?></th>
                        <th><?php _e('Atraso', 'book-manager'); ?></th>
                        <th><?php _e('WhatsApp', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdue_loans as $loan): ?>
                        <tr style="background:#fff3f3;">
                            <td><?php echo esc_html($loan['user_name']); ?></td>
                            <td><?php echo esc_html($loan['book_title']); ?></td>
                            <td><?php echo $loan['due_date_formatted']; ?></td>
                            <td style="color:#dc3545;font-weight:bold;"><?php printf(__('%d dias atrasado', 'book-manager'), abs($loan['days_remaining'])); ?></td>
                            <td>
                                <?php if (!empty($loan['user_phone'])): ?>
                                    <?php 
                                    $wa_msg = bm_get_loan_message($loan['user_name'], $loan['book_title'], $loan['due_date_formatted'], 'overdue');
                                    echo bm_whatsapp_button($loan['user_phone'], $wa_msg, 'WhatsApp');
                                    ?>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if (!empty($pending_reservations)): ?>
            <h2 style="color:#f0ad4e;">🟡 <?php _e('Reservas Pendentes', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Aluno', 'book-manager'); ?></th>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Posição', 'book-manager'); ?></th>
                        <th><?php _e('Data', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_reservations as $res): ?>
                        <tr>
                            <td><?php echo esc_html($res['user_name']); ?></td>
                            <td><?php echo esc_html($res['book_title']); ?></td>
                            <td><?php echo isset($res['position']) ? $res['position'] . 'º' : '—'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($res['date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if (empty($overdue_loans) && empty($pending_reservations)): ?>
            <p><?php _e('Tudo em dia! Nenhum atraso ou reserva pendente.', 'book-manager'); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}