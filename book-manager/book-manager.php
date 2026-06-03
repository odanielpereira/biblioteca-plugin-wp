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

// ==========================================
// FASE 8B: FORÇAR TEMPLATES DO PLUGIN (SINGLE E ARCHIVE)
// ==========================================
function bm_force_templates($template) {
    if (is_singular('bm_book')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'single-bm_book.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    if (is_post_type_archive('bm_book')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'archive-bm_book.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}
add_filter('template_include', 'bm_force_templates', 99);

// ==========================================
// FASE 9A: VERIFICAÇÃO DE PERMISSÃO PARA NOVAS ROLES
// ==========================================
function bm_user_can_manage_books() {
    return current_user_can('manage_options') || current_user_can('edit_bm_books') || current_user_can('edit_bm_book');
}
function bm_user_can_view_admin_data() {
    return current_user_can('manage_options') || current_user_can('edit_bm_books');
}
function bm_is_student() {
    return current_user_can('read_bm_book') && !current_user_can('edit_bm_book');
}
function bm_is_teacher() {
    return current_user_can('edit_bm_book') && !current_user_can('edit_bm_books');
}

// ==========================================
// FASE 9B: AUTOCADASTRO E APROVAÇÃO
// ==========================================
function bm_registration_form() {
    if (is_user_logged_in()) return '<p>' . __('Você já está logado.', 'book-manager') . '</p>';
    
    ob_start();
    ?>
    <form method="post" class="bm-register-form" style="max-width:400px;margin:20px auto;">
        <?php wp_nonce_field('bm_register_action', 'bm_register_nonce'); ?>
        <p>
            <label><?php _e('Nome completo', 'book-manager'); ?> *</label>
            <input type="text" name="bm_full_name" required style="width:100%;padding:8px;margin-top:4px;" />
        </p>
        <p>
            <label><?php _e('E-mail', 'book-manager'); ?> *</label>
            <input type="email" name="bm_email" required style="width:100%;padding:8px;margin-top:4px;" />
        </p>
        <p>
            <label><?php _e('Senha', 'book-manager'); ?> *</label>
            <input type="password" name="bm_password" required style="width:100%;padding:8px;margin-top:4px;" />
        </p>
        <p>
            <label><?php _e('Perfil', 'book-manager'); ?> *</label>
            <select name="bm_role" required style="width:100%;padding:8px;margin-top:4px;">
                <option value=""><?php _e('— Selecione —', 'book-manager'); ?></option>
                <option value="bm_student"><?php _e('Aluno', 'book-manager'); ?></option>
                <option value="bm_teacher"><?php _e('Professor', 'book-manager'); ?></option>
            </select>
        </p>
        <p>
            <label><?php _e('Série/Ano (aluno) ou Disciplina (professor)', 'book-manager'); ?></label>
            <input type="text" name="bm_info" style="width:100%;padding:8px;margin-top:4px;" />
        </p>
        <p>
            <label><?php _e('Telefone/WhatsApp', 'book-manager'); ?></label>
            <input type="text" name="bm_phone" style="width:100%;padding:8px;margin-top:4px;" placeholder="5511999999999" />
        </p>
        <p>
            <input type="submit" name="bm_register_submit" value="<?php _e('Cadastrar', 'book-manager'); ?>" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;" />
        </p>
    </form>
    <?php
    
    if (isset($_POST['bm_register_submit']) && wp_verify_nonce($_POST['bm_register_nonce'], 'bm_register_action')) {
        $full_name = sanitize_text_field($_POST['bm_full_name']);
        $email = sanitize_email($_POST['bm_email']);
        $password = $_POST['bm_password'];
        $role = sanitize_text_field($_POST['bm_role']);
        $info = sanitize_text_field($_POST['bm_info']);
        $phone = sanitize_text_field($_POST['bm_phone']);
        
        $errors = array();
        if (empty($full_name)) $errors[] = __('Nome é obrigatório.', 'book-manager');
        if (!is_email($email)) $errors[] = __('E-mail inválido.', 'book-manager');
        if (email_exists($email)) $errors[] = __('E-mail já cadastrado.', 'book-manager');
        if (strlen($password) < 6) $errors[] = __('Senha deve ter no mínimo 6 caracteres.', 'book-manager');
        if (!in_array($role, array('bm_student', 'bm_teacher'))) $errors[] = __('Perfil inválido.', 'book-manager');
        
        if (empty($errors)) {
            $user_id = wp_insert_user(array(
                'user_login' => sanitize_user($email),
                'user_email' => $email,
                'user_pass' => $password,
                'display_name' => $full_name,
                'role' => 'subscriber',
            ));
            
            if (!is_wp_error($user_id)) {
                update_user_meta($user_id, 'bm_full_name', $full_name);
                update_user_meta($user_id, 'bm_requested_role', $role);
                update_user_meta($user_id, 'bm_info', $info);
                update_user_meta($user_id, 'bm_phone', $phone);
                update_user_meta($user_id, 'bm_approval_status', 'pending');
                echo '<p style="color:green;">' . __('Cadastro realizado! Aguarde aprovação.', 'book-manager') . '</p>';
            } else {
                echo '<p style="color:red;">' . $user_id->get_error_message() . '</p>';
            }
        } else {
            foreach ($errors as $error) {
                echo '<p style="color:red;">' . esc_html($error) . '</p>';
            }
        }
    }
    
    return ob_get_clean();
}
add_shortcode('bm_register', 'bm_registration_form');

function bm_add_approval_page() {
    add_submenu_page('users.php', __('Aprovar Cadastros', 'book-manager'), __('Aprovar Cadastros', 'book-manager'), 'edit_bm_books', 'bm_approve_users', 'bm_render_approval_page');
}
add_action('admin_menu', 'bm_add_approval_page');

function bm_render_approval_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    if (isset($_POST['bm_approve_user']) && wp_verify_nonce($_POST['bm_approval_nonce'], 'bm_approval_action')) {
        $user_id = intval($_POST['user_id']);
        $action = sanitize_text_field($_POST['bm_action']);
        $requested_role = get_user_meta($user_id, 'bm_requested_role', true);
        
        if ($action === 'approve') {
            $user = new WP_User($user_id);
            $user->set_role($requested_role);
            update_user_meta($user_id, 'bm_approval_status', 'approved');
            update_user_meta($user_id, 'bm_approved_by', get_current_user_id());
            update_user_meta($user_id, 'bm_approved_date', current_time('mysql'));
            echo '<div class="notice notice-success"><p>' . __('Usuário aprovado!', 'book-manager') . '</p></div>';
        } elseif ($action === 'reject') {
            update_user_meta($user_id, 'bm_approval_status', 'rejected');
            echo '<div class="notice notice-error"><p>' . __('Usuário rejeitado.', 'book-manager') . '</p></div>';
        }
    }
    
    $pending_users = get_users(array(
        'meta_key' => 'bm_approval_status',
        'meta_value' => 'pending',
    ));
    
    ?>
    <div class="wrap">
        <h1><?php _e('Aprovar Cadastros', 'book-manager'); ?></h1>
        <?php if (empty($pending_users)): ?>
            <p><?php _e('Nenhum cadastro pendente.', 'book-manager'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Nome', 'book-manager'); ?></th>
                        <th><?php _e('E-mail', 'book-manager'); ?></th>
                        <th><?php _e('Perfil Solicitado', 'book-manager'); ?></th>
                        <th><?php _e('Info', 'book-manager'); ?></th>
                        <th><?php _e('Telefone', 'book-manager'); ?></th>
                        <th><?php _e('Ações', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $user): ?>
                        <tr>
                            <td><?php echo esc_html(get_user_meta($user->ID, 'bm_full_name', true)); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(get_user_meta($user->ID, 'bm_requested_role', true)); ?></td>
                            <td><?php echo esc_html(get_user_meta($user->ID, 'bm_info', true)); ?></td>
                            <td><?php echo esc_html(get_user_meta($user->ID, 'bm_phone', true)); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('bm_approval_action', 'bm_approval_nonce'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                    <button type="submit" name="bm_approve_user" value="1" class="button button-primary"><?php _e('Aprovar', 'book-manager'); ?></button>
                                    <input type="hidden" name="bm_action" value="approve">
                                </form>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('bm_approval_action', 'bm_approval_nonce'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                    <button type="submit" name="bm_approve_user" value="1" class="button"><?php _e('Rejeitar', 'book-manager'); ?></button>
                                    <input type="hidden" name="bm_action" value="reject">
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
// FASE 9C: SISTEMA DE RESERVAS
// ==========================================
function bm_reserve_book($book_id, $user_id, $reserved_for = null) {
    $target_user_id = $reserved_for ? intval($reserved_for) : $user_id;
    
    if (bm_is_student_by_id($target_user_id)) {
        $active_count = bm_get_active_reservation_count($target_user_id);
        if ($active_count >= 3) return array('error' => __('Limite de 3 reservas atingido.', 'book-manager'));
    }
    
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) $reservations = array();
    
    foreach ($reservations as $r) {
        if ($r['user_id'] == $target_user_id && in_array($r['status'], array('waiting', 'active'))) {
            return array('error' => __('Você já reservou este livro.', 'book-manager'));
        }
    }
    
    $position = 0;
    foreach ($reservations as $r) {
        if ($r['status'] === 'waiting') $position++;
    }
    $position++;
    
    $reservation = array(
        'user_id' => $target_user_id,
        'reserved_by' => $user_id,
        'date' => current_time('mysql'),
        'status' => 'waiting',
        'position' => $position,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
    );
    
    $reservations[] = $reservation;
    update_post_meta($book_id, '_bm_reservations', $reservations);
    
    $user_reservations = get_user_meta($target_user_id, '_bm_active_reservations', true) ?: array();
    $user_reservations[] = $book_id;
    update_user_meta($target_user_id, '_bm_active_reservations', array_unique($user_reservations));
    update_user_meta($target_user_id, '_bm_reservation_count', count(array_unique($user_reservations)));
    
    bm_log_audit($book_id, "Reservado por usuário #$target_user_id (posição #$position)");
    
    return array(
        'success' => true,
        'position' => $position,
        'book_id' => $book_id,
        'message' => sprintf(__('Reserva confirmada! Você é o %dº da lista de espera.', 'book-manager'), $position),
    );
}

function bm_cancel_reservation($book_id, $user_id) {
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) return false;
    
    $found = false;
    foreach ($reservations as $key => $r) {
        if ($r['user_id'] == $user_id && in_array($r['status'], array('waiting', 'active'))) {
            unset($reservations[$key]);
            $found = true;
            break;
        }
    }
    
    if ($found) {
        $reservations = array_values($reservations);
        $pos = 0;
        foreach ($reservations as &$r) {
            if ($r['status'] === 'waiting') {
                $pos++;
                $r['position'] = $pos;
            }
        }
        update_post_meta($book_id, '_bm_reservations', $reservations);
        
        $user_reservations = get_user_meta($user_id, '_bm_active_reservations', true) ?: array();
        $user_reservations = array_diff($user_reservations, array($book_id));
        update_user_meta($user_id, '_bm_active_reservations', array_values($user_reservations));
        update_user_meta($user_id, '_bm_reservation_count', count($user_reservations));
        
        bm_log_audit($book_id, "Reserva cancelada pelo usuário #$user_id");
        return true;
    }
    
    return false;
}

function bm_user_has_reservation($book_id, $user_id = null) {
    if (!$user_id) $user_id = get_current_user_id();
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) return false;
    foreach ($reservations as $r) {
        if ($r['user_id'] == $user_id && in_array($r['status'], array('waiting', 'active'))) return true;
    }
    return false;
}

function bm_is_student_by_id($user_id) {
    $user = get_userdata($user_id);
    return $user && in_array('bm_student', $user->roles);
}

function bm_get_active_reservation_count($user_id) {
    return intval(get_user_meta($user_id, '_bm_reservation_count', true));
}

function bm_reserve_button($book_id = null) {
    if (!$book_id) $book_id = get_the_ID();
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) $reservations = array();
    $waiting_count = 0;
    foreach ($reservations as $r) { if ($r['status'] === 'waiting') $waiting_count++; }
    
    $user_id = get_current_user_id();
    $has_reservation = is_user_logged_in() && bm_user_has_reservation($book_id, $user_id);
    $can_reserve_for_others = current_user_can('edit_bm_books') || current_user_can('manage_options');
    
    $nonce = wp_create_nonce('bm_reserve_nonce');
    
    if (!is_user_logged_in()): ?>
        <button type="button" class="bm-btn-reserve" onclick="bmShowModal('<?php _e('Faça login ou crie uma conta para poder reservar', 'book-manager'); ?>')" style="padding:8px 16px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;">
            <?php _e('Reservar', 'book-manager'); ?> <?php if ($waiting_count > 0) echo '(' . $waiting_count . ')'; ?>
        </button>
    <?php elseif ($has_reservation): ?>
        <button type="button" class="bm-btn-cancel" onclick="bmCancelReserve(<?php echo $book_id; ?>, '<?php echo $nonce; ?>')" style="padding:8px 16px;background:#c00;color:#fff;border:none;border-radius:4px;cursor:pointer;">
            <?php _e('Cancelar reserva', 'book-manager'); ?>
        </button>
    <?php else: ?>
        <button type="button" class="bm-btn-reserve" onclick="bmDoReserve(<?php echo $book_id; ?>, '<?php echo $nonce; ?>', <?php echo $can_reserve_for_others ? 'true' : 'false'; ?>)" style="padding:8px 16px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;">
            <?php _e('Reservar', 'book-manager'); ?> <?php if ($waiting_count > 0) echo '(' . $waiting_count . ')'; ?>
        </button>
    <?php endif;
}

function bm_ajax_reserve_book() {
    if (!is_user_logged_in()) wp_die(json_encode(array('error' => __('Faça login para reservar.', 'book-manager'))));
    check_ajax_referer('bm_reserve_nonce', 'nonce');
    
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
    $user_id = get_current_user_id();
    $reserved_for = isset($_POST['reserved_for']) ? intval($_POST['reserved_for']) : null;
    
    if (!$book_id) wp_die(json_encode(array('error' => __('Livro inválido.', 'book-manager'))));
    
    $result = bm_reserve_book($book_id, $user_id, $reserved_for);
    wp_die(json_encode($result));
}
add_action('wp_ajax_bm_reserve_book', 'bm_ajax_reserve_book');

function bm_ajax_cancel_reservation() {
    if (!is_user_logged_in()) wp_die(json_encode(array('error' => __('Faça login.', 'book-manager'))));
    check_ajax_referer('bm_reserve_nonce', 'nonce');
    
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
    $user_id = get_current_user_id();
    
    if (!$book_id) wp_die(json_encode(array('error' => __('Livro inválido.', 'book-manager'))));
    
    $result = bm_cancel_reservation($book_id, $user_id);
    if ($result) {
        wp_die(json_encode(array('success' => true, 'message' => __('Reserva cancelada.', 'book-manager'))));
    } else {
        wp_die(json_encode(array('error' => __('Reserva não encontrada.', 'book-manager'))));
    }
}
add_action('wp_ajax_bm_cancel_reservation', 'bm_ajax_cancel_reservation');

function bm_reserve_scripts() {
    if (!is_admin()):
    ?>
    <div id="bm-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:30px;border-radius:8px;max-width:400px;text-align:center;">
            <p id="bm-modal-message"></p>
            <button onclick="bmCloseModal()" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-top:10px;">OK</button>
        </div>
    </div>
    <script>
    function bmShowModal(msg) {
        document.getElementById('bm-modal-message').textContent = msg;
        document.getElementById('bm-modal').style.display = 'flex';
    }
    function bmCloseModal() {
        document.getElementById('bm-modal').style.display = 'none';
    }
    function bmDoReserve(bookId, nonce, canReserveForOthers) {
        var reservedFor = null;
        if (canReserveForOthers) {
            var choice = confirm('<?php _e('Reservar para você?\\n\\nOK = Reservar para mim\\nCancelar = Reservar para um aluno', 'book-manager'); ?>');
            if (!choice) {
                reservedFor = prompt('<?php _e('Digite o ID do aluno:', 'book-manager'); ?>');
                if (!reservedFor) return;
            }
        }
        var btn = event.target;
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = '...';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.error) {
                bmShowModal(r.error);
                btn.disabled = false;
                btn.textContent = originalText;
            } else {
                bmShowModal(r.message);
                btn.textContent = '<?php _e('Cancelar reserva', 'book-manager'); ?>';
                btn.className = 'bm-btn-cancel';
                btn.style.background = '#c00';
                btn.onclick = function() { bmCancelReserve(bookId, nonce); };
                btn.disabled = false;
            }
        };
        var data = 'action=bm_reserve_book&book_id=' + bookId + '&nonce=' + nonce;
        if (reservedFor) data += '&reserved_for=' + reservedFor;
        xhr.send(data);
    }
    function bmCancelReserve(bookId, nonce) {
        var btn = event.target;
        btn.disabled = true;
        btn.textContent = '...';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.success) {
                bmShowModal(r.message);
                btn.textContent = '<?php _e('Reservar', 'book-manager'); ?>';
                btn.className = 'bm-btn-reserve';
                btn.style.background = '#111';
                btn.onclick = function() { bmDoReserve(bookId, nonce, false); };
                btn.disabled = false;
            } else {
                bmShowModal(r.error || 'Erro');
                btn.disabled = false;
                btn.textContent = '<?php _e('Cancelar reserva', 'book-manager'); ?>';
            }
        };
        xhr.send('action=bm_cancel_reservation&book_id=' + bookId + '&nonce=' + nonce);
    }
    </script>
    <?php
    endif;
}
add_action('wp_footer', 'bm_reserve_scripts');

// ==========================================
// FASE 9D: EMPRÉSTIMOS E DEVOLUÇÕES
// FASE 9F: BOTÃO WHATSAPP E CONTADOR REGRESSIVO (4 CORES)
// ==========================================

function bm_confirm_loan($book_id, $user_id, $days = 14) {
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) return array('error' => __('Nenhuma reserva encontrada.', 'book-manager'));
    
    $found = false;
    foreach ($reservations as $key => $r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'waiting') {
            $reservations[$key]['status'] = 'active';
            $reservations[$key]['loan_date'] = current_time('mysql');
            $reservations[$key]['due_date'] = date('Y-m-d H:i:s', strtotime("+$days days"));
            $reservations[$key]['loan_id'] = $book_id . '-' . $user_id . '-' . time();
            $found = true;
            break;
        }
    }
    
    if (!$found) return array('error' => __('Reserva não encontrada.', 'book-manager'));
    
    update_post_meta($book_id, '_bm_reservations', $reservations);
    
    $borrowed_count = intval(get_post_meta($book_id, '_bm_borrowed_count', true));
    update_post_meta($book_id, '_bm_borrowed_count', $borrowed_count + 1);
    
    $user_loans = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    $user_loans[] = array(
        'book_id' => $book_id,
        'loan_date' => current_time('mysql'),
        'due_date' => date('Y-m-d H:i:s', strtotime("+$days days")),
        'status' => 'active',
    );
    update_user_meta($user_id, '_bm_loan_history', $user_loans);
    
    bm_log_audit($book_id, "Empréstimo confirmado para usuário #$user_id ($days dias)");
    
    return array('success' => true, 'message' => __('Empréstimo confirmado!', 'book-manager'), 'due_date' => date('d/m/Y', strtotime("+$days days")));
}

function bm_return_book($book_id, $user_id) {
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) return array('error' => __('Nenhum registro encontrado.', 'book-manager'));
    
    $found = false;
    foreach ($reservations as $key => $r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'active') {
            $reservations[$key]['status'] = 'returned';
            $reservations[$key]['returned_date'] = current_time('mysql');
            $found = true;
            break;
        }
    }
    
    if (!$found) return array('error' => __('Empréstimo não encontrado.', 'book-manager'));
    
    update_post_meta($book_id, '_bm_reservations', $reservations);
    
    $borrowed_count = intval(get_post_meta($book_id, '_bm_borrowed_count', true));
    update_post_meta($book_id, '_bm_borrowed_count', max(0, $borrowed_count - 1));
    
    $user_loans = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    foreach ($user_loans as &$loan) {
        if ($loan['book_id'] == $book_id && $loan['status'] === 'active') {
            $loan['status'] = 'returned';
            $loan['returned_date'] = current_time('mysql');
            break;
        }
    }
    update_user_meta($user_id, '_bm_loan_history', $user_loans);
    
    $user_reservations = get_user_meta($user_id, '_bm_active_reservations', true) ?: array();
    $user_reservations = array_diff($user_reservations, array($book_id));
    update_user_meta($user_id, '_bm_active_reservations', array_values($user_reservations));
    update_user_meta($user_id, '_bm_reservation_count', count($user_reservations));
    
    bm_log_audit($book_id, "Devolvido pelo usuário #$user_id");
    
    $next_message = '';
    foreach ($reservations as $r) {
        if ($r['status'] === 'waiting') {
            $next_user = get_userdata($r['user_id']);
            $next_name = $next_user ? $next_user->display_name : '#' . $r['user_id'];
            $next_message = ' ' . sprintf(__('Próximo: %s.', 'book-manager'), $next_name);
            break;
        }
    }
    
    return array('success' => true, 'message' => __('Devolvido!', 'book-manager') . $next_message);
}

function bm_undo_loan($book_id, $user_id) {
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) return array('error' => __('Nenhum registro encontrado.', 'book-manager'));
    
    $found = false;
    foreach ($reservations as $key => $r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'active') {
            $reservations[$key]['status'] = 'waiting';
            unset($reservations[$key]['loan_date']);
            unset($reservations[$key]['due_date']);
            $found = true;
            break;
        }
    }
    
    if (!$found) return array('error' => __('Empréstimo não encontrado.', 'book-manager'));
    
    update_post_meta($book_id, '_bm_reservations', $reservations);
    
    $borrowed_count = intval(get_post_meta($book_id, '_bm_borrowed_count', true));
    update_post_meta($book_id, '_bm_borrowed_count', max(0, $borrowed_count - 1));
    
    $user_loans = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    foreach ($user_loans as &$loan) {
        if ($loan['book_id'] == $book_id && $loan['status'] === 'active') {
            $loan['status'] = 'cancelled';
            break;
        }
    }
    update_user_meta($user_id, '_bm_loan_history', $user_loans);
    
    bm_log_audit($book_id, "Empréstimo desfeito para usuário #$user_id");
    
    return array('success' => true, 'message' => __('Empréstimo desfeito.', 'book-manager'));
}

function bm_get_days_remaining($due_date) {
    $due = strtotime($due_date);
    $now = current_time('timestamp');
    $diff = $due - $now;
    return intval(ceil($diff / DAY_IN_SECONDS));
}

function bm_add_loans_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Empréstimos', 'book-manager'), __('Empréstimos', 'book-manager'), 'edit_bm_books', 'bm_loans', 'bm_render_loans_page');
}
add_action('admin_menu', 'bm_add_loans_page');

function bm_render_loans_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $notice = '';
    
    if (isset($_POST['bm_loan_action']) && wp_verify_nonce($_POST['bm_loan_nonce'], 'bm_loan_action')) {
        $book_id = intval($_POST['book_id']);
        $user_id = intval($_POST['user_id']);
        $action = sanitize_text_field($_POST['bm_loan_action']);
        
        if ($action === 'confirm') {
            $days = isset($_POST['loan_days']) ? intval($_POST['loan_days']) : 14;
            $result = bm_confirm_loan($book_id, $user_id, $days);
        } elseif ($action === 'return') {
            $result = bm_return_book($book_id, $user_id);
        } elseif ($action === 'undo') {
            $result = bm_undo_loan($book_id, $user_id);
        }
        
        if (isset($result['error'])) {
            $notice = '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        } else {
            $notice = '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
    
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    
    $active_reservations = array();
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true);
        if (!is_array($reservations)) continue;
        foreach ($reservations as $r) {
            if (in_array($r['status'], array('waiting', 'active'))) {
                $r['book_id'] = $book->ID;
                $r['book_title'] = $book->post_title;
                $active_reservations[] = $r;
            }
        }
    }
    
    usort($active_reservations, function($a, $b) {
        if ($a['status'] === 'active' && $b['status'] !== 'active') return -1;
        if ($a['status'] !== 'active' && $b['status'] === 'active') return 1;
        if ($a['status'] === 'active' && $b['status'] === 'active') {
            $due_a = isset($a['due_date']) ? strtotime($a['due_date']) : PHP_INT_MAX;
            $due_b = isset($b['due_date']) ? strtotime($b['due_date']) : PHP_INT_MAX;
            return $due_a - $due_b;
        }
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    ?>
    <div class="wrap">
        <h1><?php _e('Gestão de Empréstimos', 'book-manager'); ?></h1>
        <?php echo $notice; ?>
        
        <?php if (empty($active_reservations)): ?>
            <p><?php _e('Nenhum empréstimo ou reserva ativa.', 'book-manager'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Usuário', 'book-manager'); ?></th>
                        <th><?php _e('Status', 'book-manager'); ?></th>
                        <th><?php _e('Reserva em', 'book-manager'); ?></th>
                        <th><?php _e('Prazo', 'book-manager'); ?></th>
                        <th><?php _e('WhatsApp', 'book-manager'); ?></th>
                        <th><?php _e('Ação', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_reservations as $r): 
                        $user = get_userdata($r['user_id']);
                        $user_name = $user ? $user->display_name : '#' . $r['user_id'];
                        $user_phone = $user ? get_user_meta($user->ID, 'bm_phone', true) : '';
                        $is_active = $r['status'] === 'active';
                        $status_label = $is_active ? __('Emprestado', 'book-manager') : __('Reservado', 'book-manager');
                        $status_color = $is_active ? '#0073aa' : '#f0ad4e';
                        
                        // FASE 9F: Contador regressivo com 4 cores
                        $days_remaining = '';
                        $countdown_style = '';
                        if ($is_active && isset($r['due_date'])) {
                            $days = bm_get_days_remaining($r['due_date']);
                            if ($days > 3) {
                                $days_remaining = $days . ' ' . __('dias restantes', 'book-manager');
                                $countdown_style = 'color:#46b450;font-weight:bold;';
                            } elseif ($days >= 1) {
                                $days_remaining = $days . ' ' . ($days == 1 ? __('dia restante', 'book-manager') : __('dias restantes', 'book-manager'));
                                $countdown_style = 'color:#f0ad4e;font-weight:bold;';
                            } elseif ($days == 0) {
                                $days_remaining = __('Vence hoje!', 'book-manager');
                                $countdown_style = 'color:#e6c300;font-weight:bold;font-size:13px;';
                            } else {
                                $days_remaining = abs($days) . ' ' . (abs($days) == 1 ? __('dia atrasado', 'book-manager') : __('dias atrasados', 'book-manager'));
                                $countdown_style = 'color:#dc3545;font-weight:bold;';
                            }
                        }
                        
                        $due_date = isset($r['due_date']) ? date('d/m/Y', strtotime($r['due_date'])) : '—';
                        $is_overdue = isset($r['due_date']) && strtotime($r['due_date']) < time();
                        $loan_id = isset($r['loan_id']) ? $r['loan_id'] : '';
                        
                        $wa_overdue_msg = bm_get_loan_message($user_name, $r['book_title'], $due_date, 'overdue');
                        $wa_reminder_msg = bm_get_loan_message($user_name, $r['book_title'], $due_date, 'reminder');
                    ?>
                        <tr style="<?php echo $is_overdue && $is_active ? 'background:#fff3f3;' : ''; ?>">
                            <td><strong><?php echo esc_html($r['book_title']); ?></strong></td>
                            <td><?php echo esc_html($user_name); ?></td>
                            <td><span style="background:<?php echo $status_color; ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;"><?php echo $status_label; ?></span></td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($r['date']))); ?></td>
                            <td>
                                <span style="display:block;<?php echo $countdown_style; ?>"><?php echo $due_date; ?></span>
                                <?php if ($days_remaining): ?>
                                    <span style="font-size:11px;<?php echo $countdown_style; ?>"><?php echo $days_remaining; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user_phone && $is_active): ?>
                                    <?php echo bm_whatsapp_button($user_phone, $is_overdue ? $wa_overdue_msg : $wa_reminder_msg, 'WhatsApp', $loan_id); ?>
                                <?php elseif (!$user_phone && $is_active): ?>
                                    <span style="color:#999;font-size:11px;"><?php _e('Sem telefone', 'book-manager'); ?></span>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                                    <input type="hidden" name="book_id" value="<?php echo $r['book_id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $r['user_id']; ?>">
                                    <?php if (!$is_active): ?>
                                        <input type="number" name="loan_days" value="14" min="0" max="60" style="width:70px;padding:4px 8px;font-size:14px;text-align:center;" title="<?php _e('Dias de empréstimo', 'book-manager'); ?>" />
                                        <input type="hidden" name="bm_loan_action" value="confirm">
                                        <button type="submit" class="button button-primary" style="background:#0073aa;color:#fff;border-color:#0073aa;"><?php _e('Confirmar', 'book-manager'); ?></button>
                                    <?php else: ?>
                                        <input type="hidden" name="bm_loan_action" value="return">
                                        <button type="submit" class="button" style="background:#46b450;color:#fff;border-color:#46b450;"><?php _e('Devolver', 'book-manager'); ?></button>
                                        <input type="hidden" name="bm_loan_action" value="undo" form="undo-<?php echo $r['book_id'] . '-' . $r['user_id']; ?>">
                                    <?php endif; ?>
                                </form>
                                <?php if ($is_active): ?>
                                    <form method="post" style="display:inline;" id="undo-<?php echo $r['book_id'] . '-' . $r['user_id']; ?>">
                                        <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                                        <input type="hidden" name="book_id" value="<?php echo $r['book_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $r['user_id']; ?>">
                                        <input type="hidden" name="bm_loan_action" value="undo">
                                        <button type="submit" class="button" style="background:#dc3545;color:#fff;border-color:#dc3545;" title="<?php _e('Desfazer empréstimo', 'book-manager'); ?>"><?php _e('Desfazer', 'book-manager'); ?></button>
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
// FASE 9E: CONTROLE DE ESTOQUE MATEMÁTICO
// ==========================================

function bm_get_stock_info($book_id) {
    $total = intval(get_post_meta($book_id, '_bm_copies', true));
    $borrowed = intval(get_post_meta($book_id, '_bm_borrowed_count', true));
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) $reservations = array();
    
    $waiting = 0;
    foreach ($reservations as $r) {
        if ($r['status'] === 'waiting') $waiting++;
    }
    
    $available = max(0, $total - $borrowed);
    
    return array(
        'total' => $total,
        'borrowed' => $borrowed,
        'available' => $available,
        'waiting' => $waiting,
        'in_stock' => $available > 0,
    );
}

function bm_display_stock_info($book_id = null) {
    if (!$book_id) $book_id = get_the_ID();
    $stock = bm_get_stock_info($book_id);
    
    if ($stock['total'] <= 0) return '';
    
    $color = $stock['in_stock'] ? '#46b450' : '#dc3545';
    $icon = $stock['in_stock'] ? '✓' : '✗';
    
    $html = '<div class="bm-stock-info" style="margin:10px 0;padding:10px;background:#f9f9f9;border-radius:4px;font-size:14px;">';
    $html .= '<strong>' . __('Estoque:', 'book-manager') . '</strong> ';
    $html .= '<span style="color:' . $color . ';">' . $icon . ' ' . $stock['available'] . ' ' . __('disponível(is)', 'book-manager') . '</span>';
    $html .= ' (' . __('de', 'book-manager') . ' ' . $stock['total'] . ')';
    
    if ($stock['borrowed'] > 0) {
        $html .= ' — <span style="color:#dc3545;">' . $stock['borrowed'] . ' ' . __('emprestado(s)', 'book-manager') . '</span>';
    }
    if ($stock['waiting'] > 0) {
        $html .= ' — <span style="color:#f0ad4e;">' . $stock['waiting'] . ' ' . __('na fila', 'book-manager') . '</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// ==========================================
// FASE 9F: INTEGRAÇÃO COM WHATSAPP
// ==========================================
function bm_whatsapp_link($phone, $message = '') {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (empty($phone)) return '';
    $url = 'https://wa.me/55' . $phone;
    if (!empty($message)) $url .= '?text=' . urlencode($message);
    return $url;
}

function bm_get_whatsapp_count($loan_id) {
    return intval(get_post_meta($loan_id, '_bm_whatsapp_count', true));
}

function bm_increment_whatsapp_count($loan_id) {
    $count = bm_get_whatsapp_count($loan_id);
    update_post_meta($loan_id, '_bm_whatsapp_count', $count + 1);
}

function bm_whatsapp_button($phone, $message = '', $label = '', $loan_id = null) {
    if (empty($phone)) return '';
    $url = bm_whatsapp_link($phone, $message);
    if (empty($label)) $label = __('WhatsApp', 'book-manager');
    
    $count_html = '';
    if ($loan_id) {
        $count = bm_get_whatsapp_count($loan_id);
        if ($count > 0) {
            $count_html = ' <span style="background:#25d366;color:#fff;border-radius:10px;padding:0 6px;font-size:10px;margin-left:4px;">' . $count . '</span>';
        }
    }
    
    $onclick = '';
    if ($loan_id) {
        $onclick = ' onclick="bmTrackWhatsapp(this, ' . $loan_id . ')"';
    }
    
    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="bm-whatsapp-btn" style="display:inline-block;padding:4px 10px;background:#25d366;color:#fff;border-radius:3px;text-decoration:none;font-size:12px;"' . $onclick . '>' . esc_html($label) . $count_html . '</a>';
}

function bm_whatsapp_tracking_script() {
    if (!is_admin()) return;
    ?>
    <script>
    function bmTrackWhatsapp(link, loanId) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=bm_track_whatsapp&loan_id=' + loanId + '&nonce=<?php echo wp_create_nonce('bm_whatsapp_nonce'); ?>');
        setTimeout(function() {
            var span = link.querySelector('span');
            if (span) {
                var count = parseInt(span.textContent) || 0;
                span.textContent = count + 1;
            }
        }, 500);
    }
    </script>
    <?php
}
add_action('admin_footer', 'bm_whatsapp_tracking_script');

function bm_ajax_track_whatsapp() {
    check_ajax_referer('bm_whatsapp_nonce', 'nonce');
    $loan_id = isset($_POST['loan_id']) ? intval($_POST['loan_id']) : 0;
    if ($loan_id) bm_increment_whatsapp_count($loan_id);
    wp_die();
}
add_action('wp_ajax_bm_track_whatsapp', 'bm_ajax_track_whatsapp');

function bm_get_loan_message($user_name, $book_title, $due_date, $type = 'overdue') {
    $messages = array(
        'overdue' => sprintf(__('Olá %s! O livro "%s" estava com devolução prevista para %s e está atrasado. Poderia devolvê-lo? Obrigado!', 'book-manager'), $user_name, $book_title, $due_date),
        'reminder' => sprintf(__('Olá %s! Lembramos que o livro "%s" deve ser devolvido até %s. Obrigado!', 'book-manager'), $user_name, $book_title, $due_date),
        'available' => sprintf(__('Olá %s! O livro "%s" que você reservou já está disponível para retirada. Passe na biblioteca!', 'book-manager'), $user_name, $book_title),
        'reserved_for_student' => sprintf(__('Olá! O professor reservou o livro "%s" para você. Passe na biblioteca para retirá-lo!', 'book-manager'), $book_title),
    );
    return isset($messages[$type]) ? $messages[$type] : $messages['overdue'];
}

// ==========================================
// FASE 7H: SCRIPTS DO ADMIN (DRAG AND DROP)
// ==========================================
function bm_admin_scripts($hook) {
    if (strpos($hook, 'bm_dynamic_fields') === false && strpos($hook, 'bm_book') === false) return;
    wp_enqueue_script('jquery-ui-sortable');
}
add_action('admin_enqueue_scripts', 'bm_admin_scripts');

// ==========================================
// FASE 1: CUSTOM POST TYPE
// ==========================================
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
        'public'             => true, // FASE 8A: Tornar CPT público
        'show_ui'            => true,
        'show_in_menu'       => true,
        'has_archive'        => true, // FASE 8A: Habilitar archive
        'rewrite'            => array( 'slug' => 'livros' ), // FASE 8A: Slug público
        'show_in_rest'       => false, // FASE 8A: Segurança
        'exclude_from_search'=> false,
        'capability_type'    => 'bm_book',
        'map_meta_cap'       => true,
        'supports'           => array( 'title', 'thumbnail' ),
        'delete_with_user'   => false,
        'menu_icon'          => 'dashicons-book',
    );
    register_post_type( 'bm_book', $args );
}
add_action( 'init', 'bm_register_book_cpt' );

// ==========================================
// FASE 7C: TAXONOMIAS
// ==========================================
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

// ==========================================
// FASE 8G: TAXONOMIA DE DISCIPLINAS ESCOLARES
// ==========================================
function bm_register_discipline_taxonomy() {
    register_taxonomy('bm_discipline', 'bm_book', array(
        'label'        => __('Disciplinas', 'book-manager'),
        'labels'       => array(
            'name'              => __('Disciplinas', 'book-manager'),
            'singular_name'     => __('Disciplina', 'book-manager'),
            'search_items'      => __('Buscar Disciplinas', 'book-manager'),
            'all_items'         => __('Todas as Disciplinas', 'book-manager'),
            'parent_item'       => __('Disciplina Pai', 'book-manager'),
            'parent_item_colon' => __('Disciplina Pai:', 'book-manager'),
            'edit_item'         => __('Editar Disciplina', 'book-manager'),
            'update_item'       => __('Atualizar Disciplina', 'book-manager'),
            'add_new_item'      => __('Adicionar Nova Disciplina', 'book-manager'),
            'new_item_name'     => __('Nome da Nova Disciplina', 'book-manager'),
            'menu_name'         => __('Disciplinas', 'book-manager'),
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
}
add_action('init', 'bm_register_discipline_taxonomy');

// Adicionar metabox de disciplinas na edição do livro
function bm_add_discipline_metabox() {
    add_meta_box('bm_discipline_box', __('Disciplinas', 'book-manager'), 'bm_render_discipline_metabox', 'bm_book', 'side', 'default');
}
add_action('add_meta_boxes', 'bm_add_discipline_metabox');

function bm_render_discipline_metabox($post) {
    wp_nonce_field('bm_discipline_nonce', 'bm_discipline_nonce_field');
    $terms = wp_get_post_terms($post->ID, 'bm_discipline', array('fields' => 'ids'));
    $all_terms = get_terms(array('taxonomy' => 'bm_discipline', 'hide_empty' => false));
    if (!empty($all_terms)) {
        echo '<div style="max-height:200px;overflow-y:auto;">';
        foreach ($all_terms as $term) {
            $checked = in_array($term->term_id, $terms) ? 'checked' : '';
            echo '<label style="display:block;margin-bottom:3px;"><input type="checkbox" name="bm_discipline[]" value="' . $term->term_id . '" ' . $checked . '> ' . esc_html($term->name) . '</label>';
        }
        echo '</div>';
    } else {
        echo '<p>' . __('Nenhuma disciplina cadastrada.', 'book-manager') . '</p>';
    }
}

function bm_save_discipline_metabox($post_id) {
    if (!isset($_POST['bm_discipline_nonce_field']) || !wp_verify_nonce($_POST['bm_discipline_nonce_field'], 'bm_discipline_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('manage_options')) return;
    $terms = isset($_POST['bm_discipline']) ? array_map('intval', $_POST['bm_discipline']) : array();
    wp_set_post_terms($post_id, $terms, 'bm_discipline');
}
add_action('save_post_bm_book', 'bm_save_discipline_metabox');

// ==========================================
// FASE 1/5: CAPABILITIES E CICLO DE VIDA
// FASE 9A: ROLES CUSTOMIZADAS — bm_student, bm_teacher, bm_librarian, bm_super_admin
// ==========================================
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

// FASE 9A: Registrar roles customizadas
function bm_register_roles() {
    // Aluno — acesso básico, somente leitura
    add_role('bm_student', __('Aluno', 'book-manager'), array(
        'read' => true,
        'read_bm_book' => true,
        'read_private_bm_books' => true,
    ));

    // Professor — acesso pedagógico
    add_role('bm_teacher', __('Professor', 'book-manager'), array(
        'read' => true,
        'read_bm_book' => true,
        'read_private_bm_books' => true,
        'edit_bm_book' => true,
    ));

    // Gestor da Biblioteca — acesso operacional
    add_role('bm_librarian', __('Gestor da Biblioteca', 'book-manager'), array(
        'read' => true,
        'read_bm_book' => true,
        'edit_bm_book' => true,
        'edit_bm_books' => true,
        'edit_others_bm_books' => true,
        'publish_bm_books' => true,
        'read_private_bm_books' => true,
        'delete_bm_book' => true,
        'delete_bm_books' => true,
        'delete_private_bm_books' => true,
        'delete_published_bm_books' => true,
        'delete_others_bm_books' => true,
        'edit_private_bm_books' => true,
        'edit_published_bm_books' => true,
    ));

    // Super Admin — acesso total (equivale a manage_options)
    add_role('bm_super_admin', __('Super Administrador', 'book-manager'), array(
        'read' => true,
        'manage_options' => true,
        'read_bm_book' => true,
        'edit_bm_book' => true,
        'edit_bm_books' => true,
        'edit_others_bm_books' => true,
        'publish_bm_books' => true,
        'read_private_bm_books' => true,
        'delete_bm_book' => true,
        'delete_bm_books' => true,
        'delete_private_bm_books' => true,
        'delete_published_bm_books' => true,
        'delete_others_bm_books' => true,
        'edit_private_bm_books' => true,
        'edit_published_bm_books' => true,
    ));
}

// FASE 9A: Remover roles na desinstalação
function bm_remove_roles() {
    remove_role('bm_student');
    remove_role('bm_teacher');
    remove_role('bm_librarian');
    remove_role('bm_super_admin');
}

function bm_plugin_activation() {
    bm_register_book_cpt();
    bm_register_taxonomies();
    bm_add_admin_caps();
    bm_register_roles(); // FASE 9A
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bm_plugin_activation');
function bm_plugin_deactivation() { flush_rewrite_rules(); }
register_deactivation_hook(__FILE__, 'bm_plugin_deactivation');

// ==========================================
// FASE 2/7A/7B/7F: METABOX DETALHES DO LIVRO
// ==========================================
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
    // FASE 7F: Histórico de Auditoria
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

// ==========================================
// FASE 8D: FILTROS INTELIGENTES NO FRONT-END
// ==========================================
function bm_filter_books_frontend($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('bm_book')) return;

    // Filtro por gênero
    if (isset($_GET['bm_genre']) && !empty($_GET['bm_genre']) && $_GET['bm_genre'] !== '0') {
        $tax_query = $query->get('tax_query') ?: array();
        $tax_query[] = array(
            'taxonomy' => 'bm_genre',
            'field' => 'term_id',
            'terms' => intval($_GET['bm_genre']),
        );
        $query->set('tax_query', $tax_query);
    }

    // Filtro por categoria
    if (isset($_GET['bm_category']) && !empty($_GET['bm_category']) && $_GET['bm_category'] !== '0') {
        $tax_query = $query->get('tax_query') ?: array();
        $tax_query[] = array(
            'taxonomy' => 'bm_category',
            'field' => 'term_id',
            'terms' => intval($_GET['bm_category']),
        );
        $query->set('tax_query', $tax_query);
    }

    // Busca textual (título nativo + metadados)
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

    // Salvar como campo dinâmico "Sinopse"
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

    // Criar termos se não existirem e associar ao livro
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

// ==========================================
// FASE 6A/7G: IMPORTAÇÃO CSV COM MAPEAMENTO DINÂMICO
// FASE 8C-B: RELATÓRIO MELHORADO — CONTAGEM DE DUPLICADOS FORÇADOS
// FASE 8F: INTEGRAÇÃO DE BUSCA AUTOMÁTICA DE SINOPSE
// ==========================================
function bm_add_csv_import_submenu_page() { add_submenu_page('edit.php?post_type=bm_book','Importar CSV','Importar CSV','manage_options','bm_csv_import','bm_render_csv_import_page'); }
add_action('admin_menu','bm_add_csv_import_submenu_page');
function bm_render_csv_import_page() {
    if (!current_user_can('manage_options')) return;
    $message = ''; $preview = array(); $duplicates = array();
    $stage = isset($_POST['import_stage']) ? $_POST['import_stage'] : '';
    $headers = array();
    if ('process'===$stage && isset($_POST['bm_csv_import_nonce']) && wp_verify_nonce($_POST['bm_csv_import_nonce'],'bm_csv_import_action')) {
        $skip_duplicates = isset($_POST['skip_duplicates'])&&'1'===$_POST['skip_duplicates'];
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
                    // FASE 7D: Busca automática de capa durante importação
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
                    // FASE 8F: Busca automática de sinopse durante importação
                    $sinopse = bm_fetch_sinopse_from_google($title, $author);
                    if (!empty($sinopse)) {
                        $dynamic_fields = get_option('bm_dynamic_fields', array());
                        if (!isset($dynamic_fields['Sinopse'])) {
                            $dynamic_fields['Sinopse'] = array('type' => 'textarea');
                            update_option('bm_dynamic_fields', $dynamic_fields);
                        }
                        update_post_meta($post_id, '_bm_dynamic_sinopse', $sinopse);
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

// ==========================================
// FASE 6B/7E: EXPORTAÇÃO CSV FLEXÍVEL
// ==========================================
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

// ==========================================
// FASE 7B/7H: GERENCIAMENTO DE CAMPOS DINÂMICOS
// ==========================================
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
                        // FASE 7H: Migração de meta keys ao renomear
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

// ==========================================
// FASE 7D: BUSCA DE CAPA VIA GOOGLE BOOKS API (NÚCLEO COMUM)
// FASE 8C-B: UNIFICADA — USADA POR CSV E AJAX
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
                // FASE 8E: Aumentar resolução — trocar zoom=1 por zoom=2
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

// FASE 7D: Wrapper para importação CSV — retorna URL da capa
function bm_fetch_cover_from_google($title, $author, $publisher, $isbn = '') {
    return bm_google_books_search($title, $author, $publisher, $isbn);
}

// ==========================================
// FASE 7D: BUSCA DE CAPA VIA AJAX (BOTÃO NA EDIÇÃO)
// FASE 8C-B: CORREÇÃO DE SEGURANÇA — ADICIONADO NONCE
// ==========================================
function bm_search_book_cover() {
    if (!current_user_can('manage_options')) wp_die(__('Sem permissão.','book-manager'));
    check_ajax_referer('bm_search_cover', 'nonce'); // FASE 8C-B: Verificação de nonce (CSRF)
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
    $nonce = wp_create_nonce('bm_search_cover'); // FASE 8C-B: Gerar nonce para o AJAX
    ?><script>jQuery(document).ready(function($){$('#bm_search_cover').on('click',function(){var b=$(this);b.prop('disabled',true).val('Buscando...');$.post(ajaxurl,{action:'bm_search_book_cover',nonce:'<?php echo $nonce; ?>',post_id:$('#post_ID').val(),isbn:$('#_bm_isbn').val(),title:$('#title').val(),author:$('#_bm_author').val(),publisher:$('#_bm_publisher').val()},function(r){alert(r);location.reload();});});});</script>
    <input type="button" id="bm_search_cover" class="button" value="<?php _e('Buscar Capa','book-manager'); ?>" /><?php
}
add_action('edit_form_after_title','bm_add_cover_button');

// ==========================================
// FASE 7F: SOFT DELETE E AUDITORIA
// ==========================================
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