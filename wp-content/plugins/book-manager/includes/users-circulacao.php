<?php
/**
 * Book Manager — Módulo de Circulação
 * Roles, autocadastro, reservas, empréstimos, estoque, WhatsApp
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

// ==========================================
// FASE 9B: APROVAÇÃO DE CADASTROS
// ==========================================
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
    
    update_user_meta($user_id, '_bm_penalty_active', '1');
    
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
                if (!$is_archived) continue;
            } else {
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