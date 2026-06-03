<?php
/**
 * Book Manager — Módulo de Usuários e Circulação
 * Roles, autocadastro, reservas, empréstimos, estoque, WhatsApp, dashboards
 */

defined('ABSPATH') || exit;

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
    $user = wp_get_current_user();
    return in_array('bm_student', (array) $user->roles);
}
function bm_is_teacher() {
    $user = wp_get_current_user();
    return in_array('bm_teacher', (array) $user->roles);
}
function bm_get_user_role() {
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    if (in_array('administrator', $roles)) return 'admin';
    if (in_array('bm_librarian', $roles) || in_array('gestor_biblioteca', $roles) || in_array('gestor da biblioteca', $roles)) return 'librarian';
    if (in_array('bm_teacher', $roles) || in_array('professor', $roles)) return 'teacher';
    if (in_array('bm_student', $roles) || in_array('aluno', $roles)) return 'student';
    return 'guest';
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
                                $countdown_style = 'color:#e6c300;font-weight:bold;';
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
// FASE 9F: FUNÇÕES DO WHATSAPP
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
    
    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="bm-whatsapp-btn" style="display:inline-block;padding:4px 10px;background:#25d366;color:#fff;border-radius:3px;text-decoration:none;font-size:12px;" onclick="bmTrackWhatsapp(this, \'' . esc_attr($loan_id) . '\')">' . esc_html($label) . $count_html . '</a>';
}

function bm_get_loan_message($user_name, $book_title, $due_date, $type = 'overdue') {
    $messages = array(
        'overdue' => sprintf(__('Olá %s! O livro "%s" estava com devolução prevista para %s e está atrasado. Poderia devolvê-lo? Obrigado!', 'book-manager'), $user_name, $book_title, $due_date),
        'reminder' => sprintf(__('Olá %s! Lembramos que o livro "%s" deve ser devolvido até %s. Obrigado!', 'book-manager'), $user_name, $book_title, $due_date),
        'available' => sprintf(__('Olá %s! O livro "%s" que você reservou já está disponível para retirada. Passe na biblioteca!', 'book-manager'), $user_name, $book_title),
        'reserved_for_student' => sprintf(__('Olá! O professor reservou o livro "%s" para você. Passe na biblioteca para retirá-lo!', 'book-manager'), $book_title),
    );
    return isset($messages[$type]) ? $messages[$type] : $messages['overdue'];
}

function bm_whatsapp_tracking_script() {
    if (!is_admin()) return;
    ?>
    <script>
    function bmTrackWhatsapp(link, loanId) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=bm_track_whatsapp&loan_id=' + loanId + '&nonce=<?php echo wp_create_nonce("bm_whatsapp_nonce"); ?>');
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

// ==========================================
// FASE 9G: DASHBOARD POR PERFIL
// FASE 10D: INTEGRAÇÃO DE XP E MEDALHAS
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

function bm_student_dashboard_content() {
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
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
    
    ob_start();
    ?>
    <div class="bm-dashboard" style="max-width:800px;margin:0 auto;padding:20px;">
        <h1><?php _e('Painel do Aluno', 'book-manager'); ?></h1>
        <p><?php printf(__('Bem-vindo, %s!', 'book-manager'), esc_html($user->display_name)); ?></p>
        
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
            <div style="background:#fff8e1;padding:15px;border-radius:6px;text-align:center;border:1px solid #ffc107;">
                <h3 style="margin:0;font-size:28px;color:#f0ad4e;"><?php echo $xp; ?> XP</h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Pontos acumulados', 'book-manager'); ?></p>
            </div>
        </div>
        
        <?php if (!empty($badges)): ?>
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
                    <tr><th><?php _e('Livro', 'book-manager'); ?></th><th><?php _e('Empréstimo', 'book-manager'); ?></th><th><?php _e('Devolução', 'book-manager'); ?></th><th><?php _e('Prazo', 'book-manager'); ?></th></tr>
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
        
        <?php if (empty($active_loans) && empty($user_reservations)): ?>
            <p><?php _e('Você não tem empréstimos ou reservas ativas.', 'book-manager'); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bm_teacher_dashboard_content() {
    $user = wp_get_current_user();
    
    $students = get_users(array('role' => 'bm_student'));
    
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
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
                $r['student_phone'] = $student ? get_user_meta($student->ID, 'bm_phone', true) : '';
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
    
    ob_start();
    ?>
    <div class="bm-dashboard" style="max-width:900px;margin:0 auto;padding:20px;">
        <h1><?php _e('Painel do Professor', 'book-manager'); ?></h1>
        <p><?php printf(__('Bem-vindo, %s!', 'book-manager'), esc_html($user->display_name)); ?></p>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin:20px 0;">
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo count($students); ?></h3>
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
                <h3 style="margin:0;font-size:28px;"><?php echo count($all_books); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Livros no acervo', 'book-manager'); ?></p>
            </div>
        </div>
        
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
    
    $total_books = wp_count_posts('bm_book');
    $total = $total_books->publish + $total_books->draft;
    
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    
    $active_loans = array();
    $overdue_loans = array();
    $pending_reservations = array();
    $pending_approvals = get_users(array('meta_key' => 'bm_approval_status', 'meta_value' => 'pending'));
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true);
        if (!is_array($reservations)) continue;
        foreach ($reservations as $r) {
            $user_data = get_userdata($r['user_id']);
            $r['book_title'] = $book->post_title;
            $r['user_name'] = $user_data ? $user_data->display_name : '#' . $r['user_id'];
            $r['user_phone'] = $user_data ? get_user_meta($user_data->ID, 'bm_phone', true) : '';
            
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
    
    ob_start();
    ?>
    <div class="bm-dashboard" style="max-width:1000px;margin:0 auto;padding:20px;">
        <h1><?php _e('Painel do Gestor', 'book-manager'); ?></h1>
        <p><?php printf(__('Bem-vindo, %s!', 'book-manager'), esc_html($user->display_name)); ?></p>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:15px;margin:20px 0;">
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo $total; ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Livros no acervo', 'book-manager'); ?></p>
            </div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;"><?php echo count($active_loans); ?></h3>
                <p style="margin:5px 0 0 0;color:#666;"><?php _e('Empréstimos ativos', 'book-manager'); ?></p>
            </div>
            <div style="background:#fff3f3;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;color:#dc3545;"><?php echo count($overdue_loans); ?></h3>
                <p style="margin:5px 0 0 0;color:#dc3545;"><?php _e('Em atraso', 'book-manager'); ?></p>
            </div>
            <div style="background:#fff8e1;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;color:#f0ad4e;"><?php echo count($pending_reservations); ?></h3>
                <p style="margin:5px 0 0 0;color:#f0ad4e;"><?php _e('Reservas pendentes', 'book-manager'); ?></p>
            </div>
            <div style="background:#e8f5e9;padding:15px;border-radius:6px;text-align:center;">
                <h3 style="margin:0;font-size:28px;color:#46b450;"><?php echo count($pending_approvals); ?></h3>
                <p style="margin:5px 0 0 0;color:#46b450;"><?php _e('Cadastros pendentes', 'book-manager'); ?></p>
            </div>
        </div>
        
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin:20px 0;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">📚 <?php _e('Gerenciar Livros', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_loans'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">📋 <?php _e('Empréstimos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('users.php?page=bm_approve_users'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">✅ <?php _e('Aprovar Cadastros', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_csv_import'); ?>" class="button" style="background:#111;color:#fff;border:none;padding:8px 16px;text-decoration:none;border-radius:4px;">📥 <?php _e('Importar CSV', 'book-manager'); ?></a>
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

// ==========================================
// FASE 10A: RANKING DE LEITORES
// ==========================================
function bm_get_ranking($period = 'month', $limit = 10) {
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
    ), $atts);
    
    $ranking = bm_get_ranking($atts['period'], intval($atts['limit']));
    
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
                    <textarea name="bm_review" rows="5" required style="width:100%;padding:8px;margin-top:4px;" placeholder="<?php _e('O que você achou do livro?', 'book-manager'); ?>"></textarea>
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
                    <strong><?php echo esc_html($log['book_title']); ?></strong>
                    <span style="color:<?php echo $status_color; ?>;float:right;"><?php echo $status_label; ?></span>
                    <?php if ($log['rating'] > 0): ?>
                        <div style="color:#ffc107;margin:5px 0;">
                            <?php echo str_repeat('★', $log['rating']) . str_repeat('☆', 5 - $log['rating']); ?>
                        </div>
                    <?php endif; ?>
                    <p style="margin:5px 0;color:#666;"><?php echo esc_html($log['review']); ?></p>
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

function bm_add_reading_approval_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Aprovar Fichas', 'book-manager'), __('Aprovar Fichas', 'book-manager'), 'edit_bm_books', 'bm_approve_readings', 'bm_render_reading_approval_page');
}
add_action('admin_menu', 'bm_add_reading_approval_page');

function bm_render_reading_approval_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
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
            bm_award_xp_on_approval($user_id, $book_id, $bonus_xp);
        }
        
        echo $action === 'approve' 
            ? '<div class="notice notice-success"><p>' . __('Ficha aprovada! XP concedido.', 'book-manager') . '</p></div>'
            : '<div class="notice notice-error"><p>' . __('Ficha rejeitada.', 'book-manager') . '</p></div>';
    }
    
    $all_users = get_users(array('role__in' => array('bm_student', 'bm_teacher')));
    $pending_readings = array();
    
    foreach ($all_users as $user) {
        $reading_log = get_user_meta($user->ID, '_bm_reading_log', true) ?: array();
        foreach ($reading_log as $log) {
            if ($log['status'] === 'pending') {
                $log['user_id'] = $user->ID;
                $log['user_name'] = $user->display_name;
                $log['book_title'] = get_the_title($log['book_id']);
                $pending_readings[] = $log;
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Aprovar Fichas de Leitura', 'book-manager'); ?></h1>
        <?php if (empty($pending_readings)): ?>
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
                        <th><?php _e('Bônus XP', 'book-manager'); ?></th>
                        <th><?php _e('Data', 'book-manager'); ?></th>
                        <th><?php _e('Ações', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_readings as $reading): ?>
                        <tr>
                            <td><?php echo esc_html($reading['user_name']); ?></td>
                            <td><?php echo esc_html($reading['book_title']); ?></td>
                            <td><?php echo $reading['rating'] > 0 ? str_repeat('★', $reading['rating']) . str_repeat('☆', 5 - $reading['rating']) : '—'; ?></td>
                            <td><?php echo esc_html($reading['review']); ?></td>
                            <td>
                                <?php if (!empty($reading['video_url'])): ?>
                                    <a href="<?php echo esc_url($reading['video_url']); ?>" target="_blank">🎬</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" name="bm_bonus_xp" value="0" min="0" max="100" style="width:60px;padding:4px;text-align:center;" title="<?php _e('Bônus XP', 'book-manager'); ?>" form="approve-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>" />
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($reading['date'])); ?></td>
                            <td>
                                <form method="post" style="display:inline;" id="approve-<?php echo $reading['user_id'] . '-' . $reading['book_id']; ?>">
                                    <?php wp_nonce_field('bm_reading_action', 'bm_reading_nonce'); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $reading['user_id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $reading['book_id']; ?>">
                                    <button type="submit" name="bm_reading_action" value="approve" class="button button-primary"><?php _e('Aprovar', 'book-manager'); ?></button>
                                    <button type="submit" name="bm_reading_action" value="reject" class="button"><?php _e('Rejeitar', 'book-manager'); ?></button>
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

function bm_get_xp($user_id) {
    return intval(get_user_meta($user_id, '_bm_xp', true));
}

function bm_check_badges($user_id) {
    $badges = get_user_meta($user_id, '_bm_badges', true) ?: array();
    $new_badges = array();
    
    // Contar livros lidos (empréstimos devolvidos)
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
    
    // Contar vídeo-resenhas
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    $video_count = 0;
    foreach ($reading_log as $log) {
        if ($log['status'] === 'approved' && !empty($log['video_url'])) {
            $video_count++;
        }
    }
    
    // Badge: Rato de Biblioteca (5 livros)
    if ($books_read >= 5 && !in_array('rato_biblioteca', $badges)) {
        $badges[] = 'rato_biblioteca';
        $new_badges[] = 'rato_biblioteca';
    }
    
    // Badge: Leitor Voraz (15 livros)
    if ($books_read >= 15 && !in_array('leitor_voraz', $badges)) {
        $badges[] = 'leitor_voraz';
        $new_badges[] = 'leitor_voraz';
    }
    
    // Badge: Mestre das Ciências (10 livros de uma mesma disciplina)
    foreach ($discipline_counts as $discipline => $count) {
        $badge_key = 'mestre_' . sanitize_key($discipline);
        if ($count >= 10 && !in_array($badge_key, $badges)) {
            $badges[] = $badge_key;
            $new_badges[] = $badge_key;
        }
    }
    
    // Badge: Crítico de Cinema (5 vídeo-resenhas)
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

// Conceder XP automaticamente ao aprovar ficha de leitura
function bm_award_xp_on_approval($user_id, $book_id) {
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    $already_awarded = false;
    
    foreach ($reading_log as $log) {
        if ($log['book_id'] == $book_id && isset($log['xp_awarded']) && $log['xp_awarded']) {
            $already_awarded = true;
            break;
        }
    }
    
    if (!$already_awarded) {
        $xp = 10;
        $has_review = false;
        $has_video = false;
        
        foreach ($reading_log as &$log) {
            if ($log['book_id'] == $book_id) {
                if (!empty($log['review'])) { $xp += 5; $has_review = true; }
                if (!empty($log['video_url'])) { $xp += 10; $has_video = true; }
                $log['xp_awarded'] = true;
                break;
            }
        }
        update_user_meta($user_id, '_bm_reading_log', $reading_log);
        
        $reason = __('Livro lido', 'book-manager');
        if ($has_review && $has_video) $reason .= ' + resenha + vídeo';
        elseif ($has_review) $reason .= ' + resenha';
        elseif ($has_video) $reason .= ' + vídeo';
        
        bm_add_xp($user_id, $xp, $reason . ': ' . get_the_title($book_id));
    }
}