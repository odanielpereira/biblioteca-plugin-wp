<?php
/**
 * Book Manager — Módulo de Usuários e Circulação
 * Roles, autocadastro, reservas, empréstimos, estoque, WhatsApp, dashboards
 */

defined('ABSPATH') || exit;

// ==========================================
// FASE 15: AUDITORIA DE AÇÕES ADMINISTRATIVAS
// ==========================================
function bm_log_admin_action($action, $target_user_id) {
    $user = wp_get_current_user();
    $log = get_option('bm_admin_audit_log', array());
    if (!is_array($log)) $log = array();
    
    $target_user = get_userdata($target_user_id);
    
    $log[] = array(
        'action' => $action,
        'admin_user' => $user ? $user->user_login : 'sistema',
        'admin_id' => get_current_user_id(),
        'target_user' => $target_user ? $target_user->display_name : '#' . $target_user_id,
        'target_id' => $target_user_id,
        'time' => current_time('mysql'),
    );
    
    // Manter apenas últimos 100 registros
    if (count($log) > 100) {
        $log = array_slice($log, -100);
    }
    
    update_option('bm_admin_audit_log', $log);
}


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
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        if (in_array('bm_student', (array) $user->roles) && get_option('bm_recadastro_required', '0') === '1') {
            return bm_recadastro_form($user_id);
        }
        $logout_url = wp_logout_url(home_url('/minha-conta/'));
        return '<div style="max-width:450px;margin:20px auto;text-align:center;">'
            . '<p>' . sprintf(__('Bem-vindo, %s!', 'book-manager'), esc_html($user->display_name)) . '</p>'
            . '<p><a href="' . esc_url($logout_url) . '" style="padding:10px 20px;background:#dc3545;color:#fff;border-radius:4px;text-decoration:none;">' . __('Sair', 'book-manager') . '</a></p>'
            . '</div>';
    }
    
    ob_start();
    ?>
    <style>
    .bm-account-tabs { display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid #ddd; }
    .bm-account-tab { flex:1; padding:12px; text-align:center; cursor:pointer; background:#f5f5f5; border:none; font-size:15px; font-weight:bold; color:#666; transition:all 0.2s; }
    .bm-account-tab.active { background:#fff; color:#111; border:2px solid #ddd; border-bottom:2px solid #fff; margin-bottom:-2px; }
    .bm-account-panel { display:none; }
    .bm-account-panel.active { display:block; }
    </style>
    <div style="max-width:450px;margin:20px auto;">
        <div class="bm-account-tabs">
            <button type="button" class="bm-account-tab active" onclick="bmSwitchTab('login')"><?php _e('Entrar', 'book-manager'); ?></button>
            <button type="button" class="bm-account-tab" onclick="bmSwitchTab('register')"><?php _e('Cadastrar', 'book-manager'); ?></button>
        </div>
        
        <div id="bm-panel-login" class="bm-account-panel active">
            <form method="post" action="<?php echo esc_url(wp_login_url(home_url())); ?>" style="background:#f9f9f9;padding:20px;border-radius:8px;">
                <p>
                    <label><strong><?php _e('Nome de usuário ou e-mail', 'book-manager'); ?></strong></label>
                    <input type="text" name="log" required style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <p>
                    <label><strong><?php _e('Senha', 'book-manager'); ?></strong></label>
                    <input type="password" name="pwd" required style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <p>
                    <label><input type="checkbox" name="rememberme" value="forever" /> <?php _e('Lembrar-me', 'book-manager'); ?></label>
                </p>
                <p>
                    <input type="submit" value="<?php _e('Acessar', 'book-manager'); ?>" style="padding:12px 24px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px;width:100%;" />
                </p>
            </form>
        </div>
        
        <div id="bm-panel-register" class="bm-account-panel">
    <form method="post" class="bm-register-form">
        <?php wp_nonce_field('bm_register_action', 'bm_register_nonce'); ?>
        
        <h2><?php _e('Criar Conta', 'book-manager'); ?></h2>
        
        <p>
            <label><strong><?php _e('Perfil', 'book-manager'); ?> *</strong></label>
            <select name="bm_role" id="bm_role_select" required style="width:100%;padding:10px;margin-top:4px;font-size:16px;">
                <option value=""><?php _e('— Selecione —', 'book-manager'); ?></option>
                <option value="bm_student"><?php _e('📚 Aluno', 'book-manager'); ?></option>
                <option value="bm_teacher"><?php _e('👨‍🏫 Professor', 'book-manager'); ?></option>
            </select>
        </p>
        
        <div id="bm_common_fields" style="display:none;">
            <p>
                <label><strong><?php _e('Nome completo', 'book-manager'); ?> *</strong></label>
                <input type="text" name="bm_full_name" required style="width:100%;padding:8px;margin-top:4px;" />
            </p>
            <p>
                <label><strong><?php _e('E-mail', 'book-manager'); ?> *</strong></label>
                <input type="email" name="bm_email" required style="width:100%;padding:8px;margin-top:4px;" />
            </p>
            <p>
                <label><strong><?php _e('Senha', 'book-manager'); ?> *</strong></label>
                <input type="password" name="bm_password" required minlength="6" style="width:100%;padding:8px;margin-top:4px;" />
            </p>
            
            <div id="bm_student_fields" style="display:none;">
                <?php
                $user_fields = get_option('bm_user_dynamic_fields', array());
                if (!is_array($user_fields)) $user_fields = array();
                foreach ($user_fields as $field_name => $info):
                    $name_lower = mb_strtolower(trim($field_name));
                    if (in_array($name_lower, array('nome completo', 'e-mail', 'email'))) continue;
                    $meta_key = '_bm_user_' . sanitize_key($field_name);
                ?>
                <p>
                    <label><strong><?php echo esc_html($field_name); ?></strong></label>
                    <?php if ($info['type'] === 'textarea'): ?>
                        <textarea name="<?php echo esc_attr($meta_key); ?>" rows="3" style="width:100%;padding:8px;margin-top:4px;"></textarea>
                    <?php else: ?>
                        <input type="<?php echo $info['type'] === 'email' ? 'email' : 'text'; ?>" name="<?php echo esc_attr($meta_key); ?>" style="width:100%;padding:8px;margin-top:4px;" />
                    <?php endif; ?>
                </p>
                <?php endforeach; ?>
            </div>
            
            <div id="bm_teacher_fields" style="display:none;">
                <p>
                    <label><strong><?php _e('Disciplina', 'book-manager'); ?></strong></label>
                    <input type="text" name="bm_info" style="width:100%;padding:8px;margin-top:4px;" placeholder="<?php _e('Ex: Matemática, História...', 'book-manager'); ?>" />
                </p>
            </div>
        </div>
        
        <p style="margin-top:15px;">
            <input type="submit" name="bm_register_submit" value="<?php _e('Cadastrar', 'book-manager'); ?>" style="padding:12px 24px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px;width:100%;" />
        </p>
    </form>
    
    <script>
    document.getElementById('bm_role_select').addEventListener('change', function() {
        var role = this.value;
        var common = document.getElementById('bm_common_fields');
        var studentFields = document.getElementById('bm_student_fields');
        var teacherFields = document.getElementById('bm_teacher_fields');
        
        if (role) {
            common.style.display = 'block';
            studentFields.style.display = role === 'bm_student' ? 'block' : 'none';
            teacherFields.style.display = role === 'bm_teacher' ? 'block' : 'none';
        } else {
            common.style.display = 'none';
        }
    });
    </script>
    <?php
        echo '</div>'; // Fecha bm-panel-register
        echo '</div>'; // Fecha div principal
    ?>
        
    <script>
    function bmSwitchTab(tab) {
        var tabs = document.querySelectorAll('.bm-account-tab');
        var panels = document.querySelectorAll('.bm-account-panel');
        tabs.forEach(function(t) { t.classList.remove('active'); });
        panels.forEach(function(p) { p.classList.remove('active'); });
        if (tab === 'login') {
            tabs[0].classList.add('active');
            document.getElementById('bm-panel-login').classList.add('active');
        } else {
            tabs[1].classList.add('active');
            document.getElementById('bm-panel-register').classList.add('active');
        }
    }
    </script>
        
    <?php

    if (isset($_POST['bm_register_submit']) && wp_verify_nonce($_POST['bm_register_nonce'], 'bm_register_action')) {
        $full_name = sanitize_text_field($_POST['bm_full_name']);
        $email = sanitize_email($_POST['bm_email']);
        $password = $_POST['bm_password'];
        $role = sanitize_text_field($_POST['bm_role']);
        $info = isset($_POST['bm_info']) ? sanitize_text_field($_POST['bm_info']) : '';
        
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
                update_user_meta($user_id, 'bm_approval_status', 'pending');
                update_user_meta($user_id, '_bm_user_' . sanitize_key('Nome completo'), $full_name);
                update_user_meta($user_id, '_bm_user_' . sanitize_key('E-mail'), $email);
                
                if ($role === 'bm_student') {
                    $user_fields = get_option('bm_user_dynamic_fields', array());
                    foreach ($user_fields as $field_name => $field_info) {
                        $name_lower = mb_strtolower(trim($field_name));
                        if (in_array($name_lower, array('nome completo', 'e-mail', 'email'))) continue;
                        $meta_key = '_bm_user_' . sanitize_key($field_name);
                        if (isset($_POST[$meta_key])) {
                            update_user_meta($user_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
                        }
                    }
                }
                
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

function bm_suggest_book_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('Faça login para sugerir um livro.', 'book-manager') . '</p>';
    }
    
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $msg = '';
    
    if (isset($_POST['bm_suggest_submit']) && wp_verify_nonce($_POST['bm_suggest_nonce'], 'bm_suggest_action')) {
        $title = sanitize_text_field($_POST['bm_suggest_title']);
        $author = sanitize_text_field($_POST['bm_suggest_author']);
        $publisher = sanitize_text_field($_POST['bm_suggest_publisher']);
        $reason = sanitize_textarea_field($_POST['bm_suggest_reason']);
        
        if (empty($title)) {
            $msg = '<p style="color:red;">' . __('O título é obrigatório.', 'book-manager') . '</p>';
        } else {
            $suggestions = get_option('bm_acquisition_suggestions', array());
            $suggestions[] = array(
                'user_id' => $user_id,
                'user_name' => $user->display_name,
                'title' => $title,
                'author' => $author,
                'publisher' => $publisher,
                'reason' => $reason,
                'date' => current_time('mysql'),
                'status' => 'pending',
            );
            update_option('bm_acquisition_suggestions', $suggestions);
            $msg = '<p style="color:green;">' . __('Sugestão enviada com sucesso! Obrigado.', 'book-manager') . '</p>';
        }
    }
    
    $my_suggestions = array();
    $all_suggestions = get_option('bm_acquisition_suggestions', array());
    foreach (array_reverse($all_suggestions) as $s) {
        if ($s['user_id'] == $user_id) {
            $my_suggestions[] = $s;
        }
    }
    
    ob_start();
    ?>
    <div style="max-width:500px;margin:20px auto;">
        <h2><?php _e('Sugerir Livro para Aquisição', 'book-manager'); ?></h2>
        <?php echo $msg; ?>
        
        <form method="post" style="background:#f9f9f9;padding:20px;border-radius:8px;">
            <?php wp_nonce_field('bm_suggest_action', 'bm_suggest_nonce'); ?>
            <p>
                <label><strong><?php _e('Título', 'book-manager'); ?> *</strong></label>
                <input type="text" name="bm_suggest_title" required style="width:100%;padding:8px;margin-top:4px;" />
            </p>
            <p>
                <label><strong><?php _e('Autor', 'book-manager'); ?></strong></label>
                <input type="text" name="bm_suggest_author" style="width:100%;padding:8px;margin-top:4px;" />
            </p>
            <p>
                <label><strong><?php _e('Editora', 'book-manager'); ?></strong></label>
                <input type="text" name="bm_suggest_publisher" style="width:100%;padding:8px;margin-top:4px;" />
            </p>
            <p>
                <label><strong><?php _e('Motivo', 'book-manager'); ?></strong></label>
                <textarea name="bm_suggest_reason" rows="4" style="width:100%;padding:8px;margin-top:4px;" placeholder="<?php _e('Por que este livro seria importante para o acervo?', 'book-manager'); ?>"></textarea>
            </p>
            <p>
                <input type="submit" name="bm_suggest_submit" value="<?php _e('Enviar Sugestão', 'book-manager'); ?>" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;" />
            </p>
        </form>
        
        <?php if (!empty($my_suggestions)): ?>
            <h2><?php _e('Minhas Sugestões', 'book-manager'); ?></h2>
            <?php foreach ($my_suggestions as $s): ?>
                <div style="background:#f9f9f9;padding:12px;border-radius:6px;margin-bottom:8px;">
                    <strong><?php echo esc_html($s['title']); ?></strong>
                    <?php if ($s['author']): ?> — <?php echo esc_html($s['author']); ?><?php endif; ?>
                    <?php if ($s['publisher']): ?><br><small><?php _e('Editora:', 'book-manager'); ?> <?php echo esc_html($s['publisher']); ?></small><?php endif; ?>
                    <?php if ($s['reason']): ?><p style="margin:5px 0;color:#555;"><?php echo esc_html($s['reason']); ?></p><?php endif; ?>
                    <small style="color:#999;"><?php echo date('d/m/Y', strtotime($s['date'])); ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bm_suggest_book', 'bm_suggest_book_shortcode');

// ==========================================
// FASE 12I-T3: CAMPOS DINÂMICOS NA EDIÇÃO DE USUÁRIO
// ==========================================
function bm_add_user_dynamic_fields_metabox($user) {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!in_array('bm_student', (array) $user->roles) && !in_array('bm_teacher', (array) $user->roles)) return;
    
    $user_fields = get_option('bm_user_dynamic_fields', array());
    if (empty($user_fields)) return;
    ?>
    <h2><?php _e('Dados da Biblioteca', 'book-manager'); ?></h2>
    <table class="form-table">
        <?php foreach ($user_fields as $field_name => $info): 
            $skip_fields = array('Nome completo', 'E-mail', 'Email', 'nome completo', 'e-mail', 'email');
            if (in_array($field_name, $skip_fields)) continue;
            $meta_key = '_bm_user_' . sanitize_key($field_name);
            $value = get_user_meta($user->ID, $meta_key, true);
        ?>
        <tr>
            <th><label><?php echo esc_html($field_name); ?></label></th>
            <td>
                <?php if ($info['type'] === 'textarea'): ?>
                    <textarea name="<?php echo esc_attr($meta_key); ?>" rows="3" style="width:100%;max-width:400px;"><?php echo esc_textarea($value); ?></textarea>
                <?php else: ?>
                    <input type="<?php echo $info['type'] === 'email' ? 'email' : 'text'; ?>" name="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>" style="width:100%;max-width:400px;" />
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        
        <tr>
            <th><label><?php _e('Status de Aprovação', 'book-manager'); ?></label></th>
            <td>
                <?php $status = get_user_meta($user->ID, 'bm_approval_status', true); ?>
                <select name="bm_approval_status">
                    <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pendente', 'book-manager'); ?></option>
                    <option value="approved" <?php selected($status, 'approved'); ?>><?php _e('Aprovado', 'book-manager'); ?></option>
                    <option value="rejected" <?php selected($status, 'rejected'); ?>><?php _e('Rejeitado', 'book-manager'); ?></option>
                </select>
            </td>
        </tr>
    </table>
    <?php
}


function bm_save_user_dynamic_fields($user_id) {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $user_fields = get_option('bm_user_dynamic_fields', array());
    foreach ($user_fields as $field_name => $info) {
        $meta_key = '_bm_user_' . sanitize_key($field_name);
        if (isset($_POST[$meta_key])) {
            update_user_meta($user_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
        }
    }
    
    if (isset($_POST['bm_approval_status'])) {
        update_user_meta($user_id, 'bm_approval_status', sanitize_text_field($_POST['bm_approval_status']));
    }
    
    // Sincronizar display_name
    $nome_key = '_bm_user_' . sanitize_key('Nome completo');
    $novo_nome = get_user_meta($user_id, $nome_key, true);
    if (!empty($novo_nome)) {
        wp_update_user(array('ID' => $user_id, 'display_name' => $novo_nome));
    }
}

// FASE 12I: Formulário de recadastramento obrigatório
function bm_recadastro_form($user_id) {
    $user = get_userdata($user_id);
    $recadastro_year = get_option('bm_recadastro_year', date('Y'));
    
    ob_start();
    
    if (isset($_POST['bm_recadastro_submit']) && wp_verify_nonce($_POST['bm_recadastro_nonce'], 'bm_recadastro_action')) {
        $user_fields = get_option('bm_user_dynamic_fields', array());
        foreach ($user_fields as $field_name => $info) {
            $meta_key = '_bm_user_' . sanitize_key($field_name);
            if (isset($_POST[$meta_key])) {
                update_user_meta($user_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
            }
        }
        // Sincronizar display_name
        $nome_key = '_bm_user_' . sanitize_key('Nome completo');
        $novo_nome = get_user_meta($user_id, $nome_key, true);
        if (!empty($novo_nome)) {
            wp_update_user(array('ID' => $user_id, 'display_name' => $novo_nome));
        }
        echo '<p style="color:green;text-align:center;">' . __('Dados atualizados com sucesso! Bem-vindo ao ano letivo de ', 'book-manager') . esc_html($recadastro_year) . '.</p>';
        return ob_get_clean();
    }
    ?>
    <div style="max-width:450px;margin:20px auto;background:#fff8e1;padding:20px;border-radius:8px;border:2px solid #ffc107;">
        <h2 style="text-align:center;">🔄 <?php _e('Recadastramento', 'book-manager'); ?> <?php echo esc_html($recadastro_year); ?></h2>
        <p style="text-align:center;"><?php _e('Bem-vindo ao novo ano letivo! Confirme ou atualize seus dados para continuar.', 'book-manager'); ?></p>
        
        <form method="post">
            <?php wp_nonce_field('bm_recadastro_action', 'bm_recadastro_nonce'); ?>
            
            <?php
            $user_fields = get_option('bm_user_dynamic_fields', array());
            foreach ($user_fields as $field_name => $info):
                $meta_key = '_bm_user_' . sanitize_key($field_name);
                $current_value = get_user_meta($user_id, $meta_key, true);
            ?>
            <p>
                <label><strong><?php echo esc_html($field_name); ?></strong></label>
                <?php if ($info['type'] === 'textarea'): ?>
                    <textarea name="<?php echo esc_attr($meta_key); ?>" rows="3" style="width:100%;padding:8px;margin-top:4px;"><?php echo esc_textarea($current_value); ?></textarea>
                <?php else: ?>
                    <input type="<?php echo $info['type'] === 'email' ? 'email' : 'text'; ?>" name="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($current_value); ?>" style="width:100%;padding:8px;margin-top:4px;" />
                <?php endif; ?>
            </p>
            <?php endforeach; ?>
            
            <p style="margin-top:15px;">
                <input type="submit" name="bm_recadastro_submit" value="<?php _e('Confirmar Dados', 'book-manager'); ?>" style="padding:12px 24px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px;width:100%;" />
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// FASE 18: Movido para página Alunos (aba Aprovar Cadastros)

function bm_render_approval_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    bm_render_approval_page_content();
}

function bm_render_approval_page_content() {
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
            bm_log_admin_action('Aprovou cadastro', $user_id);
            echo '<div class="notice notice-success"><p>' . __('Usuário aprovado!', 'book-manager') . '</p></div>';
        } elseif ($action === 'reject') {
            update_user_meta($user_id, 'bm_approval_status', 'rejected');
            bm_log_admin_action('Rejeitou cadastro', $user_id);
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
                            <td><?php echo esc_html(get_user_meta($user->ID, '_bm_user_' . sanitize_key('Telefone'), true)); ?></td>
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
// FASE 12A: LIMITES CONFIGURÁVEIS
// ==========================================
function bm_reserve_book($book_id, $user_id, $reserved_for = null) {
    $target_user_id = $reserved_for ? intval($reserved_for) : $user_id;
        
    $settings = bm_get_settings();
    $active_loans = bm_get_active_loan_count($target_user_id);
    if ($active_loans >= $settings['max_loans_student']) {
        return array('error' => sprintf(__('Limite de %d empréstimo(s) atingido. Devolva um livro antes de pegar outro.', 'book-manager'), $settings['max_loans_student']));
    }
    
    // Verificar atraso antes de reservar
    $loan_history = get_user_meta($target_user_id, '_bm_loan_history', true) ?: array();
            // Se for reserva feita por Professor/Gestor/Admin para aluno, não conta no limite
        $is_teacher_reserve = ($user_id != $target_user_id);
    foreach ($loan_history as $loan) {
        if ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < time()) {
            $overdue_title = get_the_title($loan['book_id']);
            $overdue_author = get_post_meta($loan['book_id'], '_bm_author', true);
            $overdue_date = date('d/m/Y', strtotime($loan['due_date']));
            $message = "🚫 RESERVAS E EMPRÉSTIMOS BLOQUEADOS\n\nVocê não entregou \"{$overdue_title}\"";
            if ($overdue_author) $message .= " de {$overdue_author}";
            $message .= ".\nDeveria ter sido devolvido em {$overdue_date}.\n\nQue tal passar na biblioteca para devolvê-lo? Assim você já pode reservar e pegar novos livros!";
            return array('error' => $message);
        }
    }
    
    $settings = bm_get_settings();
    
    if (bm_is_student_by_id($target_user_id) && !$is_teacher_reserve) {
        $active_count = bm_get_active_reservation_count($target_user_id);
        if ($active_count >= $settings['max_reservations_student']) {
            return array('error' => sprintf(__('Limite de %d reservas atingido.', 'book-manager'), $settings['max_reservations_student']));
        }
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
        'expires_at' => date('Y-m-d H:i:s', strtotime('+' . $settings['reservation_hours'] . ' hours')),
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
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    $count = 0;
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        foreach ($reservations as $r) {
            if ($r['user_id'] == $user_id && $r['status'] === 'waiting') {
                $count++;
            }
        }
    }
    return $count;
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
    $is_teacher = bm_is_teacher();
    
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
        <div style="display:flex;gap:5px;flex-wrap:wrap;">
            <button type="button" class="bm-btn-reserve" onclick="bmDoReserve(<?php echo $book_id; ?>, '<?php echo $nonce; ?>', <?php echo $can_reserve_for_others ? 'true' : 'false'; ?>)" style="padding:8px 16px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                <?php _e('Reservar', 'book-manager'); ?> <?php if ($waiting_count > 0) echo '(' . $waiting_count . ')'; ?>
            </button>
            <?php if ($is_teacher || $can_reserve_for_others): ?>
                <button type="button" class="bm-btn-filter" onclick="bmShowAdvanceReserveModal(<?php echo $book_id; ?>, '<?php echo $nonce; ?>')" style="padding:8px 16px;background:#ff9800;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                    📅 <?php _e('Reservar para aula', 'book-manager'); ?>
                </button>
            <?php endif; ?>
        </div>
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
        var modal = document.getElementById('bm-modal');
        var msgEl = document.getElementById('bm-modal-message');
        if (!msgEl) {
            modal.innerHTML = '<div style="background:#fff;padding:30px;border-radius:8px;max-width:400px;text-align:center;">' +
                '<p id="bm-modal-message"></p>' +
                '<button onclick="bmCloseModal()" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-top:10px;">OK</button>' +
                '</div>';
            msgEl = document.getElementById('bm-modal-message');
        }
        msgEl.textContent = msg;
        modal.style.display = 'flex';
    }
    function bmCloseModal() {
        var modal = document.getElementById('bm-modal');
        modal.style.display = 'none';
        modal.innerHTML = '<div style="background:#fff;padding:30px;border-radius:8px;max-width:400px;text-align:center;">' +
            '<p id="bm-modal-message"></p>' +
            '<button onclick="bmCloseModal()" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-top:10px;">OK</button>' +
            '</div>';
    }

    function bmRenewLoan(bookId, userId) {
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
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                bmShowModal(r.message);
                btn.disabled = false;
                btn.textContent = '🔄 <?php _e('Renovar', 'book-manager'); ?>';
            }
        };
        xhr.send('action=bm_renew_loan&book_id=' + bookId + '&user_id=' + userId + '&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>');
    }

    function bmShowAdvanceReserveModal(bookId, nonce) {
        var modal = document.getElementById('bm-modal');
        modal.innerHTML = '<div style="background:#fff;padding:30px;border-radius:8px;max-width:450px;text-align:left;">' +
            '<h3 style="margin-top:0;">📅 <?php _e('Reserva Antecipada para Aula', 'book-manager'); ?></h3>' +
            '<p><label><strong><?php _e('Aluno:', 'book-manager'); ?></strong></label><br>' +
                '<input type="text" id="bm-advance-student-input" placeholder="<?php _e('Digite o nome do aluno...', 'book-manager'); ?>" style="width:100%;padding:8px;margin-bottom:5px;border:1px solid #ccc;border-radius:4px;" />' +
                '<div id="bm-advance-student-results" style="max-height:150px;overflow-y:auto;margin-bottom:10px;"></div>' +
                '<input type="hidden" id="bm-advance-student-id" value="" /></p>' +
            '<p><label><strong><?php _e('Data da aula:', 'book-manager'); ?></strong></label><br><input type="date" id="bm-advance-start" style="width:100%;padding:8px;margin-top:4px;border:1px solid #ccc;border-radius:4px;" /></p>' +
            '<p><label><strong><?php _e('Data de devolução:', 'book-manager'); ?></strong></label><br><input type="date" id="bm-advance-end" style="width:100%;padding:8px;margin-top:4px;border:1px solid #ccc;border-radius:4px;" /></p>' +
            '<button onclick="bmConfirmAdvanceReserve(' + bookId + ', \'' + nonce + '\')" style="padding:10px 20px;background:#ff9800;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-top:10px;width:100%;"><?php _e('Confirmar Reserva Antecipada', 'book-manager'); ?></button>' +
            '<button onclick="bmCloseModal()" style="padding:8px 20px;background:#eee;color:#333;border:none;border-radius:4px;cursor:pointer;margin-top:5px;width:100%;"><?php _e('Cancelar', 'book-manager'); ?></button>' +
            '</div>';

        document.getElementById('bm-advance-student-input').addEventListener('keyup', function() {
            var query = this.value.trim();
            if (query.length < 2) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                var html = '';
                if (r.found) {
                    html += '<div style="padding:8px;background:#e8f5e9;border-radius:4px;cursor:pointer;margin:3px 0;" onclick="document.getElementById(\'bm-advance-student-id\').value=' + r.student.id + ';document.getElementById(\'bm-advance-student-results\').innerHTML=\'<strong>\' + \'' + r.student.name + '\' + \'</strong> selecionado\'">' + r.student.name + ' (' + r.student.email + ')</div>';
                } else if (r.multiple) {
                    r.students.forEach(function(s) {
                        html += '<div style="padding:8px;background:#f5f5f5;border-radius:4px;cursor:pointer;margin:3px 0;" onclick="document.getElementById(\'bm-advance-student-id\').value=' + s.id + ';document.getElementById(\'bm-advance-student-results\').innerHTML=\'<strong>\' + \'' + s.name + '\' + \'</strong> selecionado\'">' + s.name + ' (' + s.email + ')</div>';
                    });
                } else {
                    html = '<p style="color:#999;"><?php _e('Nenhum aluno encontrado.', 'book-manager'); ?></p>';
                }
                document.getElementById('bm-advance-student-results').innerHTML = html;
            };
            xhr.send('action=bm_service_search_student&query=' + encodeURIComponent(query) + '&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>');
        });
        
        modal.style.display = 'flex';
    }
    
    function bmConfirmAdvanceReserve(bookId, nonce) {

        modal.style.display = 'flex';
    }
    
    function bmConfirmAdvanceReserve(bookId, nonce) {
        var startDate = document.getElementById('bm-advance-start').value;
        var endDate = document.getElementById('bm-advance-end').value;
        var studentId = document.getElementById('bm-advance-student-id').value;
        
        if (!studentId || !startDate || !endDate) {
            alert('<?php _e('Selecione um aluno e preencha as datas.', 'book-manager'); ?>');
            return;
        }
        
        bmCloseModal();
        var data = 'action=bm_advance_reserve&book_id=' + bookId + '&nonce=' + nonce + '&group=Individual&start_date=' + startDate + '&end_date=' + endDate + '&student_id=' + studentId;
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.success) {
                bmShowModal(r.message);
            } else {
                bmShowModal(r.error || '<?php _e('Erro ao reservar.', 'book-manager'); ?>');
            }
        };
        xhr.send(data);
    }

    function bmShowReserveChoiceModal(bookId, nonce, reserveBtn) {
        var modal = document.getElementById('bm-modal');
        modal.innerHTML = '<div style="background:#fff;padding:30px;border-radius:8px;max-width:400px;text-align:center;">' +
            '<h3 style="margin-top:0;">📚 <?php _e('Reservar Livro', 'book-manager'); ?></h3>' +
            '<p><?php _e('Deseja reservar para você ou para um aluno?', 'book-manager'); ?></p>' +
            '<button onclick="bmReserveForSelf(' + bookId + ', \'' + nonce + '\', this)" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;margin:5px;width:100%;">👤 <?php _e('Reservar para mim', 'book-manager'); ?></button>' +
            '<button onclick="bmShowStudentSearchModal(' + bookId + ', \'' + nonce + '\', this)" style="padding:10px 20px;background:#2196f3;color:#fff;border:none;border-radius:4px;cursor:pointer;margin:5px;width:100%;">👥 <?php _e('Reservar para um aluno', 'book-manager'); ?></button>' +
            '<button onclick="bmCloseModal()" style="padding:8px 20px;background:#eee;color:#333;border:none;border-radius:4px;cursor:pointer;margin-top:10px;"><?php _e('Cancelar', 'book-manager'); ?></button>' +
            '</div>';
        modal._reserveBtn = reserveBtn;
        modal.style.display = 'flex';
    }
    
    function bmReserveForSelf(bookId, nonce) {
        var reserveBtn = document.getElementById('bm-modal')._reserveBtn;
        bmCloseModal();
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.error) {
                bmShowModal(r.error);
            } else {
                bmShowModal(r.message);
                if (reserveBtn) {
                    reserveBtn.textContent = '<?php _e('Cancelar reserva', 'book-manager'); ?>';
                    reserveBtn.className = 'bm-btn-cancel';
                    reserveBtn.style.background = '#c00';
                    reserveBtn.onclick = function() { bmCancelReserve(bookId, nonce); };
                }
            }
        };
        xhr.send('action=bm_reserve_book&book_id=' + bookId + '&nonce=' + nonce);
    }

    function bmShowStudentSearchModal(bookId, nonce, choiceBtn) {
        var modal = document.getElementById('bm-modal');
        modal.innerHTML = '<div style="background:#fff;padding:30px;border-radius:8px;max-width:450px;text-align:left;">' +
            '<h3 style="margin-top:0;">👤 <?php _e('Selecionar Aluno', 'book-manager'); ?></h3>' +
            '<input type="text" id="bm-student-search-input" placeholder="<?php _e('Digite o nome do aluno...', 'book-manager'); ?>" style="width:100%;padding:8px;margin-bottom:10px;border:1px solid #ccc;border-radius:4px;" />' +
            '<div id="bm-student-search-results" style="max-height:200px;overflow-y:auto;"></div>' +
            '<button onclick="bmCloseModal()" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-top:10px;"><?php _e('Cancelar', 'book-manager'); ?></button>' +
            '</div>';
        modal.style.display = 'flex';
        
        document.getElementById('bm-student-search-input').addEventListener('keyup', function() {
            var query = this.value.trim();
            if (query.length < 2) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                var html = '';
                if (r.found) {
                    html += '<div style="padding:10px;background:#e8f5e9;border-radius:4px;cursor:pointer;margin:3px 0;border:1px solid #4caf50;" onclick="bmSelectStudentForReserve(' + r.student.id + ', \'' + r.student.name.replace(/'/g, "\\'") + '\', ' + bookId + ', \'' + nonce + '\')"><strong>' + r.student.name + '</strong><br><small>' + r.student.email + '</small></div>';
                } else if (r.multiple) {
                    r.students.forEach(function(s) {
                        html += '<div style="padding:10px;background:#f5f5f5;border-radius:4px;cursor:pointer;margin:3px 0;border:1px solid #ddd;" onclick="bmSelectStudentForReserve(' + s.id + ', \'' + s.name.replace(/'/g, "\\'") + '\', ' + bookId + ', \'' + nonce + '\')"><strong>' + s.name + '</strong><br><small>' + s.email + '</small></div>';
                    });
                } else {
                    html = '<p style="color:#999;"><?php _e('Nenhum aluno encontrado.', 'book-manager'); ?></p>';
                }
                document.getElementById('bm-student-search-results').innerHTML = html;
            };
            xhr.send('action=bm_service_search_student&query=' + encodeURIComponent(query) + '&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>');
        });
    }
    
    function bmSelectStudentForReserve(studentId, studentName, bookId, nonce) {
        var reserveBtn = document.getElementById('bm-modal')._reserveBtn;
        bmCloseModal();
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.error) {
                bmShowModal(r.error);
            } else {
                bmShowModal(r.message);
                if (reserveBtn) {
                    reserveBtn.textContent = '<?php _e('Cancelar reserva', 'book-manager'); ?>';
                    reserveBtn.className = 'bm-btn-cancel';
                    reserveBtn.style.background = '#c00';
                    reserveBtn.onclick = function() { bmCancelReserve(bookId, nonce); };
                }
            }
        };
        xhr.send('action=bm_reserve_book&book_id=' + bookId + '&nonce=' + nonce + '&reserved_for=' + studentId);
    }

    function bmDoReserve(bookId, nonce, canReserveForOthers) {
        var reservedFor = null;
        var reserveBtn = event.target;
        if (canReserveForOthers) {
            bmShowReserveChoiceModal(bookId, nonce, reserveBtn);
            return;
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
// FASE 12A: VERIFICAÇÃO DE ESTOQUE + BOTÃO REJEITAR + FILA
// ==========================================
function bm_get_active_loan_count($user_id) {
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    $count = 0;
    foreach ($loan_history as $loan) {
        if ($loan['status'] === 'active') $count++;
    }
    return $count;
}

function bm_confirm_loan($book_id, $user_id, $days = null) {
    $settings = bm_get_settings();
    if ($days === null) $days = $settings['default_loan_days'];
        
    $active_loans = bm_get_active_loan_count($user_id);
    if ($active_loans >= $settings['max_loans_student']) {
        return array('error' => sprintf(__('Limite de %d empréstimo(s) atingido. Devolva um livro antes de pegar outro.', 'book-manager'), $settings['max_loans_student']));
    }
    
    // Verificar estoque disponível
    $stock = bm_get_stock_info($book_id);
    if ($stock['available'] <= 0) {
        return array('error' => __('Não há exemplares disponíveis para empréstimo.', 'book-manager'));
    }
    
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

function bm_reject_reservation($book_id, $user_id) {
    $reservations = get_post_meta($book_id, '_bm_reservations', true);
    if (!is_array($reservations)) return array('error' => __('Nenhuma reserva encontrada.', 'book-manager'));
    
    $found = false;
    foreach ($reservations as $key => $r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'waiting') {
            $reservations[$key]['status'] = 'rejected';
            $found = true;
            break;
        }
    }
    
    if (!$found) return array('error' => __('Reserva não encontrada.', 'book-manager'));
    
    // Recalcular posições
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
    
    bm_log_audit($book_id, "Reserva rejeitada para usuário #$user_id");
    
    return array('success' => true, 'message' => __('Reserva rejeitada.', 'book-manager'));
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
        
    // Verificar atraso e aplicar penalidade
    $days_late = 0;
    foreach ($reservations as $r) {
        if ($r['user_id'] == $user_id && $r['status'] === 'returned' && isset($r['due_date'])) {
            $due_time = strtotime($r['due_date']);
            $return_time = strtotime($r['returned_date']);
            if ($return_time > $due_time) {
                $days_late = ceil(($return_time - $due_time) / DAY_IN_SECONDS);
            }
            break;
        }
    }
    
    if ($days_late > 0) {
        $penalty = bm_calculate_penalty($user_id, $days_late);
        if ($penalty) {
            bm_apply_penalty($user_id, $penalty);
        }
    }
    $next_message = '';
    $next_phone = '';
    $next_user_id = 0;
    foreach ($reservations as $r) {
        if ($r['status'] === 'waiting') {
            $next_user = get_userdata($r['user_id']);
            $next_name = $next_user ? $next_user->display_name : '#' . $r['user_id'];
            $next_phone = $next_user ? get_user_meta($next_user->ID, '_bm_user_' . sanitize_key('Telefone'), true) : '';
            $next_user_id = $r['user_id'];
            $book_title = get_the_title($book_id);
            $wa_link = '';
            if ($next_phone) {
                $wa_msg = bm_get_loan_message($next_name, $book_title, '', 'available');
                $wa_link = bm_whatsapp_link($next_phone, $wa_msg);
            }
            $next_message = ' ' . sprintf(__('Próximo: %s.', 'book-manager'), $next_name);
            if ($wa_link) {
                $next_message .= ' <a href="' . esc_url($wa_link) . '" target="_blank" style="display:inline-block;padding:4px 10px;background:#25d366;color:#fff;border-radius:3px;text-decoration:none;font-size:12px;">📱 ' . __('Avisar', 'book-manager') . '</a>';
            }
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

function bm_check_penalty_block($user_id) {
    $active = get_user_meta($user_id, '_bm_penalty_active', true);
    if ($active !== '1') return false;
    
    $until = get_user_meta($user_id, '_bm_penalty_until', true);
    
    // Se tem data fim e já passou, liberar automaticamente
    if (!empty($until) && strtotime($until) < time()) {
        update_user_meta($user_id, '_bm_penalty_active', '0');
        delete_user_meta($user_id, '_bm_penalty_until');
        return false;
    }
    
    $penalties = get_user_meta($user_id, '_bm_penalties', true) ?: array();
    $last = end($penalties);
    
    return array(
        'blocked' => true,
        'type' => $last['type'],
        'value' => $last['value'],
        'until' => $until,
    );
}

function bm_apply_penalty($user_id, $penalty) {
    $penalties = get_user_meta($user_id, '_bm_penalties', true) ?: array();
    
    $note = isset($penalty['note']) ? $penalty['note'] : '';
    $entry = array(
        'type' => $penalty['type'],
        'value' => $penalty['value'],
        'date' => current_time('mysql'),
        'applied_by' => get_current_user_id(),
        'note' => $note,
    );
    
    $penalties[] = $entry;
    update_user_meta($user_id, '_bm_penalties', $penalties);
    
    // Ativar bloqueio
    update_user_meta($user_id, '_bm_penalty_active', '1');
    
    // Se for suspensão, definir data fim
    if ($penalty['type'] === 'suspension') {
        $days = intval($penalty['value']);
        $until = date('Y-m-d', strtotime('+' . $days . ' days'));
        update_user_meta($user_id, '_bm_penalty_until', $until);
    }
    
    bm_log_admin_action('Penalidade aplicada: ' . $penalty['type'] . ' - ' . $penalty['value'], $user_id);
    
    return $entry;
}

function bm_calculate_penalty($user_id, $days_late) {
    $rules = get_option('bm_penalty_rules', array());
    if (!is_array($rules) || empty($rules)) return false;
    
    $penalties = get_user_meta($user_id, '_bm_penalties', true) ?: array();
    $occurrence_count = count($penalties) + 1;
    
    $matched_rule = null;
    
    foreach ($rules as $rule) {
        $min_days = intval($rule['min_days']);
        $max_days = isset($rule['max_days']) && $rule['max_days'] !== '' ? intval($rule['max_days']) : null;
        $occurrence = intval($rule['occurrence']);
        $type = $rule['penalty_type'];
        $value = floatval($rule['penalty_value']);
        
        if ($occurrence > 0 && $occurrence !== $occurrence_count) continue;
        if ($days_late < $min_days) continue;
        if ($max_days !== null && $days_late > $max_days) continue;
        
        if ($occurrence > 0 && $occurrence === $occurrence_count) {
            $matched_rule = array('type' => $type, 'value' => $value, 'note' => sprintf(__('Atraso de %d dias — %dª ocorrência', 'book-manager'), $days_late, $occurrence_count), 'days_late' => $days_late);
            break;
        }
        
        if ($occurrence === 0 && !$matched_rule) {
            $matched_rule = array('type' => $type, 'value' => $value, 'note' => sprintf(__('Atraso de %d dias', 'book-manager'), $days_late), 'days_late' => $days_late);
        }
    }
    
    return $matched_rule;
}

function bm_get_days_remaining($due_date) {
    $due = strtotime($due_date);
    $now = current_time('timestamp');
    $diff = $due - $now;
    return intval(ceil($diff / DAY_IN_SECONDS));
}

// FASE 18: Movido para Balcão de Atendimento (aba Empréstimos)

function bm_render_loans_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    bm_render_loans_page_content();
}

function bm_render_loans_page_content() {
    $settings = bm_get_settings();
    $notice = '';
    
    if (isset($_POST['bm_loan_action']) && wp_verify_nonce($_POST['bm_loan_nonce'], 'bm_loan_action')) {
        $book_id = intval($_POST['book_id']);
        $user_id = intval($_POST['user_id']);
        $action = sanitize_text_field($_POST['bm_loan_action']);
        
        if ($action === 'confirm') {
            $days = isset($_POST['loan_days']) ? intval($_POST['loan_days']) : $settings['default_loan_days'];
            $result = bm_confirm_loan($book_id, $user_id, $days);
        } elseif ($action === 'return') {
            $result = bm_return_book($book_id, $user_id);
        } elseif ($action === 'undo') {
            $result = bm_undo_loan($book_id, $user_id);
        } elseif ($action === 'reject') {
            $result = bm_reject_reservation($book_id, $user_id);
        } elseif ($action === 'renew') {
            $reservations = get_post_meta($book_id, '_bm_reservations', true) ?: array();
            $found = false;
            foreach ($reservations as &$r) {
                if ($r['user_id'] == $user_id && $r['status'] === 'active') {
                    $r['due_date'] = date('Y-m-d H:i:s', strtotime('+7 days', strtotime($r['due_date'])));
                    $found = true;
                    break;
                }
            }
            if ($found) {
                update_post_meta($book_id, '_bm_reservations', $reservations);
                $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
                foreach ($loan_history as &$loan) {
                    if ($loan['book_id'] == $book_id && $loan['status'] === 'active') {
                        $loan['due_date'] = date('Y-m-d H:i:s', strtotime('+7 days', strtotime($loan['due_date'])));
                        break;
                    }
                }
                update_user_meta($user_id, '_bm_loan_history', $loan_history);
                $result = array('success' => true, 'message' => __('Renovado por mais 7 dias!', 'book-manager'));
            } else {
                $result = array('error' => __('Empréstimo não encontrado.', 'book-manager'));
            }
        }
        
        if (isset($result['error'])) {
            $notice = '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        } else {
            $notice = '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
    
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    
    $show_archived = isset($_GET['bm_status']) && $_GET['bm_status'] === 'archived';
    $active_reservations = array();
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true);
        if (!is_array($reservations)) continue;
        $archived_list = get_post_meta($book->ID, '_bm_archived', true);
        if (!is_array($archived_list)) $archived_list = array();
        foreach ($reservations as $r) {
            $is_archived = isset($r['loan_id']) && $r['loan_id'] !== '' && in_array($r['loan_id'], $archived_list);
            
            if ($show_archived) {
                // Modo Arquivado: mostrar apenas os que estão na lista de arquivados
                if (!$is_archived) continue;
            } else {
                // Modo normal: pular arquivados e filtrar por status
                if ($is_archived) continue;
                if (!in_array($r['status'], array('waiting', 'active', 'returned', 'rejected', 'cancelled'))) continue;
            }
            
            $r['book_id'] = $book->ID;
            $r['book_title'] = $book->post_title;
            $r['copies'] = intval(get_post_meta($book->ID, '_bm_copies', true));
            $r['borrowed'] = intval(get_post_meta($book->ID, '_bm_borrowed_count', true));
            $active_reservations[] = $r;
        }
    }

        
    // Reservas antecipadas (bulk)
    $advance_reservations = array();
    foreach ($all_books as $book) {
        $bulk = get_post_meta($book->ID, '_bm_bulk_reservation', true);
        if (!is_array($bulk)) continue;
        foreach ($bulk as $br) {
            if ($br['status'] === 'active' || $br['status'] === 'separated') {
                $br['book_id'] = $book->ID;
                $br['book_title'] = $book->post_title;
                $br['type'] = 'advance';
                $advance_reservations[] = $br;
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

    // Filtrar por data (datepicker)
    $date_from = isset($_GET['bm_date_from']) && $_GET['bm_date_from'] !== '' ? sanitize_text_field($_GET['bm_date_from']) : '';
    $date_to = isset($_GET['bm_date_to']) && $_GET['bm_date_to'] !== '' ? sanitize_text_field($_GET['bm_date_to']) : '';
    if ($date_from !== '' || $date_to !== '') {
        $from_time = $date_from !== '' ? strtotime($date_from . ' 00:00:00') : 0;
        $to_time = $date_to !== '' ? strtotime($date_to . ' 23:59:59') : PHP_INT_MAX;
        $filtered = array();
        foreach ($active_reservations as $r) {
            $check_date = !empty($r['loan_date']) ? $r['loan_date'] : (isset($r['date']) ? $r['date'] : '');
            if ($check_date === '') {
                $filtered[] = $r;
                continue;
            }
            $check_time = strtotime($check_date);
            if ($check_time >= $from_time && $check_time <= $to_time) {
                $filtered[] = $r;
            }
        }
        $active_reservations = $filtered;
    }
    
    ?>

        
        <?php if (empty($active_reservations) && empty($advance_reservations)): ?>
            <p><?php _e('Nenhum empréstimo ou reserva ativa.', 'book-manager'); ?></p>
        <?php else: ?>
            <div style="margin-bottom:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" id="bm-loan-filter" placeholder="<?php _e('🔍 Filtrar por livro ou aluno...', 'book-manager'); ?>" style="padding:6px 10px;width:250px;border:1px solid #ccc;border-radius:4px;" />
                <label><strong><?php _e('De:', 'book-manager'); ?></strong></label>
                <input type="date" id="bm-date-from" name="bm_date_from" value="<?php echo isset($_GET['bm_date_from']) ? esc_attr($_GET['bm_date_from']) : ''; ?>" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;" />
                <label><strong><?php _e('Até:', 'book-manager'); ?></strong></label>
                <input type="date" id="bm-date-to" name="bm_date_to" value="<?php echo isset($_GET['bm_date_to']) ? esc_attr($_GET['bm_date_to']) : ''; ?>" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;" />
                <button type="button" id="bm-filter-btn" class="button" style="padding:6px 12px;">🔍 <?php _e('Pesquisar', 'book-manager'); ?></button>
            </div>

            <div style="margin-bottom:10px;">
                <label><strong><?php _e('Status:', 'book-manager'); ?></strong></label>

                <?php $current_status = isset($_GET['bm_status']) ? sanitize_text_field($_GET['bm_status']) : 'all'; ?>
                <select id="bm-status-filter" style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;margin-left:5px;">
                    <option value="all" <?php selected($current_status, 'all'); ?>><?php _e('Todos', 'book-manager'); ?></option>
                    <option value="scheduled"><?php _e('📅 Agendado', 'book-manager'); ?></option>
                    <option value="waiting"><?php _e('Reservado', 'book-manager'); ?></option>
                    <option value="active"><?php _e('Emprestado', 'book-manager'); ?></option>
                    <option value="overdue"><?php _e('Atrasado', 'book-manager'); ?></option>
                    <option value="returned"><?php _e('Devolvido', 'book-manager'); ?></option>
                    <option value="cancelled"><?php _e('Cancelado', 'book-manager'); ?></option>
                    <option value="rejected"><?php _e('Rejeitado', 'book-manager'); ?></option>
                    <option value="separated"><?php _e('📚 Separado', 'book-manager'); ?></option>

                    <option value="archived" <?php selected($current_status, 'archived'); ?>><?php _e('🗄️ Arquivado', 'book-manager'); ?></option>

                </select>
            </div>
            <div style="margin-bottom:10px;display:none;" id="bm-archive-bulk-bar">
                <button type="button" class="button" id="bm-archive-selected-btn" style="background:#6c757d;color:#fff;border-color:#6c757d;">🗄️ <?php _e('Arquivar selecionados', 'book-manager'); ?></button>
                <span id="bm-archive-count" style="margin-left:8px;color:#666;"></span>
            </div>
            <table class="wp-list-table widefat fixed striped" id="bm-loans-table">
                <thead>
                    <tr>
                        <th><?php _e('Livro', 'book-manager'); ?></th>
                        <th><?php _e('Usuário', 'book-manager'); ?></th>
                        <th><?php _e('Status', 'book-manager'); ?></th>
                        <th><?php _e('Posição', 'book-manager'); ?></th>
                        <th><?php _e('Estoque', 'book-manager'); ?></th>
                        <th><?php _e('Prazo', 'book-manager'); ?></th>
                        <th><?php _e('WhatsApp', 'book-manager'); ?></th>
                        <th><?php _e('Ação', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_reservations as $r): 
                        $user = get_userdata($r['user_id']);
                        $user_name = $user ? $user->display_name : '#' . $r['user_id'];
                        $user_phone = $user ? get_user_meta($user->ID, '_bm_user_' . sanitize_key('Telefone'), true) : '';
                        $is_active = $r['status'] === 'active';
                        $status_labels = array(
                            'waiting' => array('label' => __('Reservado', 'book-manager'), 'color' => '#f0ad4e'),
                            'active' => array('label' => __('Emprestado', 'book-manager'), 'color' => '#0073aa'),
                            'returned' => array('label' => __('Devolvido', 'book-manager'), 'color' => '#46b450'),
                            'rejected' => array('label' => __('Rejeitado', 'book-manager'), 'color' => '#dc3545'),
                            'cancelled' => array('label' => __('Cancelado', 'book-manager'), 'color' => '#6c757d'),
                        );
                        $current_status = isset($status_labels[$r['status']]) ? $status_labels[$r['status']] : $status_labels['waiting'];
                        $status_label = $current_status['label'];
                        $status_color = $current_status['color'];
                        
                        $position_display = isset($r['position']) ? $r['position'] . 'º' : '—';
                        
                        $available = max(0, $r['copies'] - $r['borrowed']);
                        $stock_display = $available . '/' . $r['copies'];
                        $stock_color = $available > 0 ? '#46b450' : '#dc3545';
                        
                        $days_remaining = '';
                        $countdown_style = '';
                        if ($is_active && isset($r['due_date'])) {
                            $days = bm_get_days_remaining($r['due_date']);
                            if ($days > 3) { $days_remaining = $days . ' ' . __('dias restantes', 'book-manager'); $countdown_style = 'color:#46b450;font-weight:bold;'; }
                            elseif ($days >= 1) { $days_remaining = $days . ' ' . ($days == 1 ? __('dia restante', 'book-manager') : __('dias restantes', 'book-manager')); $countdown_style = 'color:#f0ad4e;font-weight:bold;'; }
                            elseif ($days == 0) { $days_remaining = __('Vence hoje!', 'book-manager'); $countdown_style = 'color:#e6c300;font-weight:bold;'; }
                            else { $days_remaining = abs($days) . ' ' . (abs($days) == 1 ? __('dia atrasado', 'book-manager') : __('dias atrasados', 'book-manager')); $countdown_style = 'color:#dc3545;font-weight:bold;'; }
                        }
                        
                        $due_date = isset($r['due_date']) ? date('d/m/Y', strtotime($r['due_date'])) : '—';
                        $is_overdue = isset($r['due_date']) && strtotime($r['due_date']) < time();
                        $loan_id = isset($r['loan_id']) ? $r['loan_id'] : '';
                        
                        $wa_overdue_msg = bm_get_loan_message($user_name, $r['book_title'], $due_date, 'overdue');
                        $wa_reminder_msg = bm_get_loan_message($user_name, $r['book_title'], $due_date, 'reminder');
                    ?>
                        <tr class="bm-loan-row bm-status-<?php echo esc_attr($r['status']); ?><?php echo $is_overdue && $is_active ? ' bm-status-overdue' : ''; ?>" style="<?php echo $is_overdue && $is_active ? 'background:#fff3f3;' : ''; ?>">
                            <td>
                                <?php if ($show_archived): ?>
                                    <button type="button" class="button button-small bm-unarchive-btn" data-book="<?php echo $r['book_id']; ?>" data-loan="<?php echo isset($r['loan_id']) ? esc_attr($r['loan_id']) : ''; ?>" style="margin-right:6px;color:#111;">↩️ <?php _e('Desarquivar', 'book-manager'); ?></button>
                                <?php elseif (in_array($r['status'], array('returned', 'cancelled', 'rejected'))): ?>
                                    <input type="checkbox" class="bm-archive-checkbox" data-book="<?php echo $r['book_id']; ?>" data-loan="<?php echo isset($r['loan_id']) ? esc_attr($r['loan_id']) : ''; ?>" style="margin-right:6px;" />
                                <?php endif; ?>
                                <strong><a href="<?php echo admin_url('post.php?post=' . $r['book_id'] . '&action=edit'); ?>"><?php echo esc_html($r['book_title']); ?></a></strong>
                            </td>



                            <td><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $r['user_id']); ?>"><?php echo esc_html($user_name); ?></a></td>
                            <td><span style="background:<?php echo $status_color; ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;"><?php echo $status_label; ?></span></td>
                            <td><?php echo $position_display; ?></td>
                            <td><span style="color:<?php echo $stock_color; ?>;font-weight:bold;"><?php echo $stock_display; ?></span></td>
                            <td>
                                <span style="display:block;<?php echo $countdown_style; ?>"><?php echo $due_date; ?></span>
                                <?php if ($days_remaining): ?><span style="font-size:11px;<?php echo $countdown_style; ?>"><?php echo $days_remaining; ?></span><?php endif; ?>
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
                                <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_loan_detail&book_id=' . $r['book_id'] . '&user_id=' . $r['user_id'] . '&loan_id=' . $loan_id); ?>" class="button button-small" style="margin-bottom:5px;">🔍 <?php _e('Ver detalhes', 'book-manager'); ?></a>
                                <?php if ($r['status'] === 'waiting' || $r['status'] === 'active'): ?>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                                    <input type="hidden" name="book_id" value="<?php echo $r['book_id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $r['user_id']; ?>">
                                    <?php if (!$is_active): ?>
                                        <input type="number" name="loan_days" value="<?php echo $settings['default_loan_days']; ?>" min="0" max="60" style="width:60px;padding:4px 8px;font-size:14px;text-align:center;" title="<?php _e('Dias de empréstimo', 'book-manager'); ?>" />
                                        <input type="hidden" name="bm_loan_action" value="confirm">
                                        <button type="button" class="button button-primary bm-confirm-btn" style="background:#0073aa;color:#fff;border-color:#0073aa;" data-book="<?php echo $r['book_id']; ?>" data-user="<?php echo $r['user_id']; ?>" <?php echo $available <= 0 ? 'disabled' : ''; ?>><?php _e('Confirmar', 'book-manager'); ?></button>
                                        <input type="hidden" name="bm_loan_action" value="reject" form="reject-<?php echo $r['book_id'] . '-' . $r['user_id']; ?>">
                                    <?php else: ?>
                                        <input type="hidden" name="bm_loan_action" value="return">
                                        <button type="button" class="button bm-return-btn" style="background:#46b450;color:#fff;border-color:#46b450;" data-book="<?php echo $r['book_id']; ?>" data-user="<?php echo $r['user_id']; ?>"><?php _e('Devolver', 'book-manager'); ?></button>
                                        <input type="hidden" name="bm_loan_action" value="undo" form="undo-<?php echo $r['book_id'] . '-' . $r['user_id']; ?>">
                                    <?php endif; ?>
                                </form>
                                <?php if (!$is_active): ?>
                                    <form method="post" style="display:inline;" id="reject-<?php echo $r['book_id'] . '-' . $r['user_id']; ?>">
                                        <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                                        <input type="hidden" name="book_id" value="<?php echo $r['book_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $r['user_id']; ?>">
                                        <input type="hidden" name="bm_loan_action" value="reject">
                                        <button type="button" class="button bm-reject-btn" style="background:#dc3545;color:#fff;border-color:#dc3545;" data-book="<?php echo $r['book_id']; ?>" data-user="<?php echo $r['user_id']; ?>" title="<?php _e('Rejeitar reserva', 'book-manager'); ?>"><?php _e('Rejeitar', 'book-manager'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($is_active): ?>
                                    <form method="post" style="display:inline;" id="undo-<?php echo $r['book_id'] . '-' . $r['user_id']; ?>">
                                        <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                                        <input type="hidden" name="book_id" value="<?php echo $r['book_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $r['user_id']; ?>">
                                        <input type="hidden" name="bm_loan_action" value="undo">
                                        <button type="button" class="button bm-undo-btn" style="background:#dc3545;color:#fff;border-color:#dc3545;" data-book="<?php echo $r['book_id']; ?>" data-user="<?php echo $r['user_id']; ?>" title="<?php _e('Desfazer empréstimo', 'book-manager'); ?>"><?php _e('Desfazer', 'book-manager'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($advance_reservations as $index => $br): 
                        $teacher = get_userdata($br['teacher_id']);
                        $teacher_name = $teacher ? $teacher->display_name : '#' . $br['teacher_id'];
                        $student_name = '';
                        if (!empty($br['student_id'])) {
                            $student = get_userdata($br['student_id']);
                            $student_name = $student ? $student->display_name : '';
                        }
                    ?>
                        <tr class="bm-loan-row bm-status-scheduled" style="background:#fff8e1;">
                            <td><strong><a href="<?php echo get_permalink($br['book_id']); ?>" target="_blank"><?php echo esc_html($br['book_title']); ?></a></strong></td>
                            <td>
                                <?php if ($student_name): ?>
                                    <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $br['student_id']); ?>"><?php echo esc_html($student_name); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html($br['group']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($br['status'] === 'separated'): ?>
                                    <span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;">📚 <?php _e('Separado', 'book-manager'); ?></span>
                                <?php elseif ($br['status'] === 'cancelled'): ?>
                                    <span style="background:#dc3545;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;">❌ <?php _e('Cancelado', 'book-manager'); ?></span>
                                <?php else: ?>
                                    <span style="background:#ff9800;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px;">📅 <?php _e('Agendado', 'book-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>—</td>
                            <td>—</td>
                            <td><?php echo date('d/m/Y', strtotime($br['start_date'])); ?> → <?php echo date('d/m/Y', strtotime($br['end_date'])); ?></td>
                            <td><small><?php _e('Por:', 'book-manager'); ?> <?php echo esc_html($teacher_name); ?></small></td>
                            <td>
                                <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_loan_detail&book_id=' . $br['book_id'] . '&user_id=' . ($br['student_id'] ?: $br['teacher_id']) . '&loan_id='); ?>" class="button button-small" style="margin-bottom:5px;">🔍 <?php _e('Ver detalhes', 'book-manager'); ?></a>
                                <?php if ($br['status'] === 'active'): ?>
                                    <button type="button" class="button button-small bm-separate-btn" data-book="<?php echo $br['book_id']; ?>" data-created="<?php echo esc_attr($br['created_at']); ?>" style="background:#4caf50;color:#fff;border-color:#4caf50;">✅ <?php _e('Separar', 'book-manager'); ?></button>
                                <?php endif; ?>

                                <?php if ($br['status'] === 'separated'): ?>
                                    <button type="button" class="button button-small bm-loan-advance-btn" data-book="<?php echo $br['book_id']; ?>" data-student="<?php echo !empty($br['student_id']) ? $br['student_id'] : '0'; ?>" data-created="<?php echo esc_attr($br['created_at']); ?>" style="background:#0073aa;color:#fff;border-color:#0073aa;">📤 <?php _e('Emprestar', 'book-manager'); ?></button>
                                <?php endif; ?>

                                <?php if ($br['status'] === 'active' || $br['status'] === 'separated'): ?>
                                    <button type="button" class="button button-small bm-cancel-advance-btn" data-book="<?php echo $br['book_id']; ?>" data-created="<?php echo esc_attr($br['created_at']); ?>" style="background:#dc3545;color:#fff;border-color:#dc3545;">❌ <?php _e('Cancelar', 'book-manager'); ?></button>
                                <?php endif; ?>
                                <?php if ($br['status'] === 'cancelled'): ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <script>
            var bmNonce = '<?php echo wp_create_nonce("bm_service_nonce"); ?>';
            
            function bmConfirm(message, callback) {
                var modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;display:flex;align-items:center;justify-content:center;';
                modal.innerHTML = '<div style="background:#fff;padding:25px;border-radius:8px;max-width:400px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.3);">' +
                    '<p style="font-size:15px;margin:0 0 15px 0;">' + message + '</p>' +
                    '<button id="bm-modal-ok" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-right:8px;">OK</button>' +
                    '<button id="bm-modal-cancel" style="padding:8px 20px;background:#eee;color:#333;border:none;border-radius:4px;cursor:pointer;">Cancelar</button>' +
                    '</div>';
                document.body.appendChild(modal);
                document.getElementById('bm-modal-ok').addEventListener('click', function() { document.body.removeChild(modal); callback(); });
                document.getElementById('bm-modal-cancel').addEventListener('click', function() { document.body.removeChild(modal); });
            }
            // Devolver via AJAX
                        function bmReloadKeepingFilters() {
                var statusFilter = document.getElementById('bm-status-filter').value;
                var url = new URL(window.location.href);
                if (statusFilter && statusFilter !== 'all') url.searchParams.set('bm_status', statusFilter);
                else url.searchParams.delete('bm_status');
                window.location.href = url.toString();
            }

            document.querySelectorAll('.bm-return-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookId = this.getAttribute('data-book');
                    var userId = this.getAttribute('data-user');
                    var self = this;
                    bmConfirm('<?php _e('Confirmar devolução?', 'book-manager'); ?>', function() {
                        var bookId = self.getAttribute('data-book');
                        var userId = self.getAttribute('data-user');
                        self.disabled = true;
                        self.textContent = '...';
                        var row = self.closest('tr');
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            var r = JSON.parse(xhr.responseText);
                            if (r.success) {
                                row.style.opacity = '0.5';
                                row.querySelector('.bm-return-btn').remove();
                                row.querySelector('.bm-undo-btn').remove();
                                var statusCell = row.querySelector('td:nth-child(3) span');
                                if (statusCell) statusCell.textContent = 'Devolvido';
                            } else {
                                bmConfirm(r.message, function(){});
                                self.disabled = false;
                                self.textContent = '<?php _e('Devolver', 'book-manager'); ?>';
                            }
                        };
                        xhr.send('action=bm_service_return&book_id=' + bookId + '&user_id=' + userId + '&nonce=' + bmNonce);
                    });
                });
            });
            
            // Confirmar via AJAX
            document.querySelectorAll('.bm-confirm-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookId = this.getAttribute('data-book');
                    var userId = this.getAttribute('data-user');
                    var daysInput = this.closest('td').querySelector('input[name="loan_days"]');
                    var days = daysInput ? daysInput.value : <?php echo $settings['default_loan_days']; ?>;
                    this.disabled = true;
                    this.textContent = '...';
                    var row = this.closest('tr');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            var statusCell = row.querySelector('td:nth-child(3) span');
                            if (statusCell) {
                                statusCell.textContent = 'Emprestado';
                                statusCell.style.background = '#0073aa';
                            }
                            // Exibir botão de comprovante
                            var actionCell = row.querySelector('td:last-child');
                            if (actionCell && r.receipt_url) {
                                var receiptBtn = document.createElement('a');
                                receiptBtn.href = r.receipt_url;
                                receiptBtn.target = '_blank';
                                receiptBtn.className = 'button button-small';
                                receiptBtn.style.cssText = 'margin-left:5px;background:#111;color:#fff;';
                                receiptBtn.textContent = '🧾 Comprovante';
                                actionCell.appendChild(receiptBtn);
                            }
                            btn.remove();
                            var rejectBtn = row.querySelector('.bm-reject-btn');
                            if (rejectBtn) rejectBtn.remove();
                        } else {
                            alert(r.message);
                            btn.disabled = false;
                            btn.textContent = '<?php _e('Confirmar', 'book-manager'); ?>';
                        }
                    };
                    xhr.send('action=bm_service_loan&book_id=' + bookId + '&user_id=' + userId + '&days=' + days + '&nonce=' + bmNonce);
                });
            });
            
                  
            
            // Desfazer via AJAX
            document.querySelectorAll('.bm-undo-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookId = this.getAttribute('data-book');
                    var userId = this.getAttribute('data-user');
                    var self = this;
                    bmConfirm('<?php _e('Desfazer este empréstimo?', 'book-manager'); ?>', function() {
                        var bookId = self.getAttribute('data-book');
                        var userId = self.getAttribute('data-user');
                        self.disabled = true;
                        self.textContent = '...';
                        var row = self.closest('tr');
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            var r = JSON.parse(xhr.responseText);
                            if (r.success) {
                                location.reload();
                            } else {
                                bmConfirm(r.message, function(){});
                                self.disabled = false;
                                self.textContent = '<?php _e('Desfazer', 'book-manager'); ?>';
                            }
                        };
                        xhr.send('action=bm_undo_loan&book_id=' + bookId + '&user_id=' + userId + '&nonce=' + bmNonce);
                    });
                });
            });


                       // Separar reserva antecipada
            document.querySelectorAll('.bm-separate-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookId = this.getAttribute('data-book');
                    var createdAt = this.getAttribute('data-created');
                    this.disabled = true;
                    this.textContent = '...';
                    var row = this.closest('tr');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            row.style.background = '#e8f5e9';
                            var statusCell = row.querySelector('td:nth-child(3) span');
                            if (statusCell) {
                                statusCell.textContent = '📚 Separado';
                                statusCell.style.background = '#4caf50';
                            }
                            btn.remove();
                        } else {
                            alert(r.message);
                            btn.disabled = false;
                            btn.textContent = '✅ Separar';
                        }
                    };
                    xhr.send('action=bm_separate_advance&book_id=' + bookId + '&created_at=' + encodeURIComponent(createdAt) + '&nonce=' + bmNonce);
                });
            });
            
            // Cancelar reserva antecipada
            document.querySelectorAll('.bm-cancel-advance-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookId = this.getAttribute('data-book');
                    var createdAt = this.getAttribute('data-created');
                    if (!confirm('<?php _e('Cancelar este agendamento?', 'book-manager'); ?>')) return;
                    this.disabled = true;
                    this.textContent = '...';
                    var row = this.closest('tr');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        row.style.opacity = '0.3';
                        setTimeout(function() { row.remove(); }, 800);
                    };
                    xhr.send('action=bm_cancel_advance&book_id=' + bookId + '&created_at=' + encodeURIComponent(createdAt) + '&nonce=' + bmNonce);
                });
            });
            

            // Emprestar agendamento separado
            document.querySelectorAll('.bm-loan-advance-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookId = this.getAttribute('data-book');
                    var studentId = this.getAttribute('data-student');
                    var createdAt = this.getAttribute('data-created');
                    if (!studentId || studentId === '0') {
                        alert('<?php _e('Este agendamento não tem um aluno associado.', 'book-manager'); ?>');
                        return;
                    }
                    var days = prompt('<?php _e('Prazo do empréstimo (dias):', 'book-manager'); ?>', '14');
                    if (!days) return;
                    var self = this;
                    self.disabled = true;
                    self.textContent = '...';
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            var row = self.closest('tr');
                            if (row) row.style.opacity = '0.3';
                            setTimeout(function() { bmReloadKeepingFilters(); }, 800);
                        } else {
                            alert(r.message || 'Erro');
                            self.disabled = false;
                            self.textContent = '📤 <?php _e('Emprestar', 'book-manager'); ?>';
                        }
                    };
                    xhr.send('action=bm_loan_advance&book_id=' + bookId + '&student_id=' + studentId + '&created_at=' + encodeURIComponent(createdAt) + '&days=' + days + '&nonce=' + bmNonce);
                });
            });

            // Rejeitar via AJAX
            document.querySelectorAll('.bm-reject-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var bookId = this.getAttribute('data-book');
                    var userId = this.getAttribute('data-user');
                    var self = this;
                    bmConfirm('<?php _e('Rejeitar esta reserva?', 'book-manager'); ?>', function() {
                        var bookId = self.getAttribute('data-book');
                        var userId = self.getAttribute('data-user');
                        self.disabled = true;
                        self.textContent = '...';
                        var row = self.closest('tr');
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            row.style.opacity = '0.3';
                            row.style.textDecoration = 'line-through';
                            setTimeout(function() { row.remove(); }, 1000);
                        };
                        xhr.send('action=bm_reject_reservation&book_id=' + bookId + '&user_id=' + userId + '&nonce=' + bmNonce);
                    });
                });
            });
            </script>
            <script>

            var bmArchiveCheckboxes = document.querySelectorAll('.bm-archive-checkbox');
            bmArchiveCheckboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var checked = document.querySelectorAll('.bm-archive-checkbox:checked');
                    var bar = document.getElementById('bm-archive-bulk-bar');
                    var count = document.getElementById('bm-archive-count');
                    if (checked.length > 0) {
                        bar.style.display = 'block';
                        count.textContent = checked.length + ' selecionado(s)';
                    } else {
                        bar.style.display = 'none';
                    }
                });
            });

            document.getElementById('bm-archive-selected-btn').addEventListener('click', function() {
                var checked = document.querySelectorAll('.bm-archive-checkbox:checked');
                if (checked.length === 0) return;
                if (!confirm(checked.length + ' registro(s) serão arquivados. Confirmar?')) return;
                var total = checked.length;
                var done = 0;
                checked.forEach(function(cb) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        done++;
                        if (done >= total) {
                            alert(done + ' registro(s) arquivado(s).');
                            bmReloadKeepingFilters();
                        }
                    };
                    xhr.send('action=bm_archive_loan&book_id=' + cb.getAttribute('data-book') + '&loan_id=' + cb.getAttribute('data-loan') + '&nonce=' + bmNonce + '&_wpnonce=' + bmNonce);
                });
            });

            document.querySelectorAll('.bm-unarchive-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (!confirm('<?php _e('Desarquivar este registro?', 'book-manager'); ?>')) return;
                    var self = this;
                    self.disabled = true;
                    self.textContent = '...';
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            var row = self.closest('tr');
                            if (row) row.style.display = 'none';
                        } else {
                            alert(r.message || 'Erro');
                            self.disabled = false;
                            self.textContent = '↩️ <?php _e('Desarquivar', 'book-manager'); ?>';
                        }
                    };
                    xhr.send('action=bm_unarchive_loan&book_id=' + self.getAttribute('data-book') + '&loan_id=' + self.getAttribute('data-loan') + '&nonce=' + bmNonce);
                });
            });

            document.getElementById('bm-loan-filter').addEventListener('keyup', function() {
                var filter = this.value.toLowerCase();
                var rows = document.querySelectorAll('#bm-loans-table tbody tr');
                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    var matchesSearch = text.indexOf(filter) > -1;
                    var statusDropdown = document.getElementById('bm-status-filter');
                    var currentStatus = statusDropdown ? statusDropdown.value : 'all';
                    if (currentStatus === 'all' || currentStatus === '') {
                        row.style.display = matchesSearch ? '' : 'none';
                    } else {
                        var statusMatch = row.classList.contains('bm-status-' + currentStatus) || (currentStatus === 'overdue' && row.classList.contains('bm-status-overdue'));
                        row.style.display = (matchesSearch && statusMatch) ? '' : 'none';
                    }
                });
            });

            function bmApplyFilters() {
                var url = new URL(window.location.href);
                var dateFrom = document.getElementById('bm-date-from').value;
                var dateTo = document.getElementById('bm-date-to').value;
                var statusFilter = document.getElementById('bm-status-filter').value;
                if (dateFrom) url.searchParams.set('bm_date_from', dateFrom);
                else url.searchParams.delete('bm_date_from');
                if (dateTo) url.searchParams.set('bm_date_to', dateTo);
                else url.searchParams.delete('bm_date_to');
                if (statusFilter && statusFilter !== 'all') url.searchParams.set('bm_status', statusFilter);
                else url.searchParams.delete('bm_status');
                window.location.href = url.toString();
            }

            document.getElementById('bm-filter-btn').addEventListener('click', bmApplyFilters);

            document.getElementById('bm-loan-filter').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') bmApplyFilters();
            });

            
            // Ao carregar a página, aplicar o filtro da URL (exceto Arquivado, que já vem filtrado pelo PHP)
            document.addEventListener('DOMContentLoaded', function() {
                var urlParams = new URLSearchParams(window.location.search);
                var urlStatus = urlParams.get('bm_status');
                if (urlStatus && urlStatus !== 'archived') {
                    var dropdown = document.getElementById('bm-status-filter');
                    if (dropdown) {
                        dropdown.value = urlStatus;
                        dropdown.dispatchEvent(new Event('change'));
                    }
                }
            });
            
            document.getElementById('bm-status-filter').addEventListener('change', function() {
                var status = this.value;
                // Se a página atual está no modo Arquivado, qualquer mudança de status precisa recarregar
                var urlParams = new URLSearchParams(window.location.search);
                var isArchivedMode = urlParams.get('bm_status') === 'archived';
                if (status === 'archived' || isArchivedMode) {
                    var url = new URL(window.location.href);
                    if (status === 'archived' || status === 'all') {
                        url.searchParams.delete('bm_status');
                    }
                    if (status !== 'all' && status !== 'archived') {
                        url.searchParams.set('bm_status', status);
                    }
                    if (status === 'archived') {
                        url.searchParams.set('bm_status', 'archived');
                    }
                    window.location.href = url.toString();
                    return;
                }
                var rows = document.querySelectorAll('#bm-loans-table tbody tr');
                rows.forEach(function(row) {
                    if (status === 'all') {
                        row.style.display = '';
                    } else if (status === 'overdue') {
                        row.style.display = row.classList.contains('bm-status-overdue') ? '' : 'none';
                    } else if (status === 'scheduled') {
                        row.style.display = row.classList.contains('bm-status-scheduled') ? '' : 'none';
                    } else if (status === 'separated') {
                        row.style.display = row.querySelector('td:nth-child(3) span') && row.querySelector('td:nth-child(3) span').textContent.indexOf('📚') > -1 ? '' : 'none';
                    } else {
                        var match = row.classList.contains('bm-status-' + status);
                        row.style.display = match ? '' : 'none';
                    }
                });
            });

            </script>
        <?php endif; ?>
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
    
    // Cache: contagens do aluno (5 minutos)
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
                
        <!-- Busca rápida de livros -->
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
        $profile_public = get_user_meta($user_id, '_bm_profile_public', true);

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

// ==========================================
// FASE 12I-T4: PROFESSOR VÊ DADOS DO ALUNO (LEITURA)
// ==========================================
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
    
    // Cache: dados do professor (5 minutos)
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
                        <th></th>
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
    
    // Cache: dados do gestor (5 minutos)
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
        // Aniversariantes do mês
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

// FASE 18: Movido para página Alunos (aba Aprovar Fichas)

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

// FASE 12J: Exportar histórico do aluno (admin_init)
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
    
    // Devolver livro direto da página de detalhes do aluno
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

    // Aplicar penalidade manual
    if (isset($_POST['bm_apply_manual_penalty']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        $type = sanitize_text_field($_POST['bm_manual_penalty_type']);
        $value = floatval($_POST['bm_manual_penalty_value']);
        $note = sanitize_text_field($_POST['bm_manual_penalty_note']);
        $penalty = array('type' => $type, 'value' => $value, 'note' => $note);
        bm_apply_penalty($student_id, $penalty);
        $msg = '<div class="notice notice-success"><p>' . __('Penalidade aplicada!', 'book-manager') . '</p></div>';
    }

    // Salvar observações
    if (isset($_POST['bm_save_notes']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        update_user_meta($student_id, '_bm_internal_notes', sanitize_textarea_field($_POST['bm_internal_notes']));
        $msg = '<div class="notice notice-success"><p>' . __('Observações salvas.', 'book-manager') . '</p></div>';
    }
    

    // Salvar dados do aluno (campos dinâmicos editáveis)
    if (isset($_POST['bm_save_student_data']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        $user_fields = get_option('bm_user_dynamic_fields', array());
        foreach ($user_fields as $field_name => $info) {
            $meta_key = '_bm_user_' . sanitize_key($field_name);
            $post_key = 'bm_edit_' . $meta_key;
            if (isset($_POST[$post_key])) {
                update_user_meta($student_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }
        // Sincronizar Nome e E-mail com o perfil nativo do WordPress
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

    // Exportar histórico — movido para bm_handle_student_export() via admin_init
    
    $user_fields = get_option('bm_user_dynamic_fields', array());
    $notes = get_user_meta($student_id, '_bm_internal_notes', true);
    $xp = bm_get_xp($student_id);
    $badges = get_user_meta($student_id, '_bm_badges', true) ?: array();
    $loan_history = get_user_meta($student_id, '_bm_loan_history', true) ?: array();
    $reading_log = get_user_meta($student_id, '_bm_reading_log', true) ?: array();
    $status = get_user_meta($student_id, 'bm_approval_status', true) ?: 'approved';
    $phone = get_user_meta($student_id, '_bm_user_' . sanitize_key('Telefone'), true);
    
    // Coletar todos os empréstimos para o histórico completo (Fase 36.3)
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
    // Ordenar por data de empréstimo (mais recentes primeiro)
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
        
        <!-- Cards de resumo -->
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
        
        <!-- Dados do aluno -->
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
                        // Fallback para alunos antigos (sem meta keys dinâmicas)
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
            
        <!-- Histórico de Devoluções -->
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

            <!-- Observações internas -->


    <script>
    // FASE 36.4: Gerenciamento manual de penalidades
    document.addEventListener('click', function(e) {
        // Botão Revogar
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
        
        // Botão Quitar
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
        
        // Botão Alterar
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
        
        <!-- Histórico completo de empréstimos -->
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
        
        <!-- Medalhas -->

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
        
        <!-- Últimas fichas de leitura -->
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


// Conceder XP manualmente ao aprovar ficha de leitura (Fase 36.1)
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