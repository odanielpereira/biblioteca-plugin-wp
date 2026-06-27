<?php
/**
 * Book Manager — Módulo de Configurações e Funções Globais
 * Configurações, APIs, White Label, Virada de Ano, Status, Taxonomias Dinâmicas
 */

defined('ABSPATH') || exit;

// ==========================================
// FUNÇÕES GLOBAIS DE CONFIGURAÇÃO
// ==========================================

function bm_get_settings() {
    $defaults = array(
        'max_reservations_student' => 3,
        'max_loans_student' => 1,
        'default_loan_days' => 14,
        'reservation_hours' => 24,
        'classification_system' => 'cdu',
        'loan_archive_days' => 1461,
        'xp_max_reading' => 10,
        'xp_max_review' => 10,
        'xp_max_video' => 10,
        'xp_enabled' => '1',
        'field_visibility' => array(
            'isbn'      => array('student' => 0, 'teacher' => 0, 'librarian' => 1),
            'location'  => array('student' => 0, 'teacher' => 1, 'librarian' => 1),
            'copies'    => array('student' => 0, 'teacher' => 0, 'librarian' => 1),
            'audit_log' => array('student' => 0, 'teacher' => 0, 'librarian' => 1),
        ),
    );
    $saved = get_option('bm_settings', array());
    if (!is_array($saved)) $saved = array();
    foreach ($defaults as $key => $default) {
        if (!isset($saved[$key])) $saved[$key] = $default;
    }
    return $saved;
}

function bm_get_api_keys() {
    $saved = get_option('bm_api_settings', array());
    if (!is_array($saved)) $saved = array();
    if (!isset($saved['google_books_key'])) $saved['google_books_key'] = '';
    if (!isset($saved['groq_key'])) $saved['groq_key'] = '';
    if (!isset($saved['groq_active'])) $saved['groq_active'] = '1';
    if (!isset($saved['groq_persona'])) $saved['groq_persona'] = '';
    if (!isset($saved['chatbot_active'])) $saved['chatbot_active'] = '1';
    if (!isset($saved['chatbot_name']) || empty($saved['chatbot_name'])) $saved['chatbot_name'] = 'Bibliotecária Virtual';
    if (!isset($saved['youtube_key'])) $saved['youtube_key'] = '';
    return $saved;
}

function bm_get_api_key($provider) {
    $keys = bm_get_api_keys();
    if ($provider === 'google_books' && defined('BM_GOOGLE_BOOKS_API_KEY') && empty($keys['google_books_key'])) {
        return BM_GOOGLE_BOOKS_API_KEY;
    }
    return isset($keys[$provider . '_key']) ? $keys[$provider . '_key'] : '';
}

function bm_get_white_label() {
    $defaults = array(
        'enabled' => '0',
        'school_name' => '',
        'school_logo' => '',
        'footer_text' => '',
        'school_url' => '',
    );
    $saved = get_option('bm_white_label', array());
    if (!is_array($saved)) $saved = array();
    foreach ($defaults as $key => $default) {
        if (!isset($saved[$key])) $saved[$key] = $default;
    }
    return $saved;
}

function bm_get_year_transition_settings() {
    $defaults = array(
        'enabled' => '0',
        'transition_month' => '12',
        'transition_day' => '31',
        'reset_xp' => '0',
        'reset_badges' => '0',
        'clear_reservations' => '1',
        'activate_recadastro' => '1',
        'history_enabled' => '0',
        'clear_reading_log' => '0',
        'clear_reviews' => '0',
        'clear_videos' => '0',
        'clear_ratings' => '0',
        'clear_loan_history' => '0',
        'clear_before_year' => '',
    );
    $saved = get_option('bm_year_transition', array());
    if (!is_array($saved)) $saved = array();
    foreach ($defaults as $key => $default) {
        if (!isset($saved[$key])) $saved[$key] = $default;
    }
    return $saved;
}

// ==========================================
// BLOQUEIO DE PÁGINAS DO GESTOR
// ==========================================

function bm_block_librarian_pages() {
    if (current_user_can('manage_options')) return;
    if (!current_user_can('edit_bm_books')) return;
    
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
    
    $page_permissions = array(
        'bm_dynamic_fields' => 'dynamic_fields',
        'bm_taxonomies' => 'taxonomies',
        'bm_labels' => 'labels',
        'bm_acquisition_suggestions' => 'students',
        'bm_library_cards' => 'students',
    );
    
    if (isset($page_permissions[$page])) {
        if (!bm_librarian_can($page_permissions[$page])) {
            wp_die(__('Acesso negado. Você não tem permissão para acessar esta página.', 'book-manager'));
        }
    }
    
    if ($page === 'bm_data_io') {
        $tab_permissions = array(
            'import_books' => 'import_csv',
            'export_books' => 'export_csv',
            'import_students' => 'student_import',
            'import_call_number' => 'import_csv',
            'export_call_number' => 'export_csv',
            'export_import_all' => 'import_csv',
        );
        if ($tab && isset($tab_permissions[$tab])) {
            if (!bm_librarian_can($tab_permissions[$tab])) {
                wp_die(__('Acesso negado. Você não tem permissão para acessar esta página.', 'book-manager'));
            }
        }
    }
    
    if ($page === 'bm_students') {
        if (!bm_librarian_can('students')) {
            wp_die(__('Acesso negado. Você não tem permissão para acessar esta página.', 'book-manager'));
        }
        if ($tab === 'approve_users' && !bm_librarian_can('approve_users')) {
            wp_die(__('Acesso negado. Você não tem permissão para acessar esta página.', 'book-manager'));
        }
        if ($tab === 'approve_readings' && !bm_librarian_can('approve_readings')) {
            wp_die(__('Acesso negado. Você não tem permissão para acessar esta página.', 'book-manager'));
        }
    }
    
    if ($page === 'bm_service_desk') {
        if ($tab === 'loans' && !bm_librarian_can('loans')) {
            wp_die(__('Acesso negado. Você não tem permissão para acessar esta página.', 'book-manager'));
        }
        if ($tab !== 'loans' && !bm_librarian_can('service')) {
            wp_die(__('Acesso negado. Você não tem permissão para acessar esta página.', 'book-manager'));
        }
    }
}
add_action('admin_init', 'bm_block_librarian_pages');

// ==========================================
// VERIFICAÇÃO DE PERMISSÃO DO GESTOR
// ==========================================

function bm_librarian_can($action) {
    if (current_user_can('manage_options')) return true;
    if (!current_user_can('edit_bm_books')) return false;
    $settings = bm_get_settings();
    $permissions = isset($settings['librarian_permissions']) ? $settings['librarian_permissions'] : array();
    return isset($permissions[$action]) && $permissions[$action] === '1';
}

// ==========================================
// AUDITORIA DE AÇÕES ADMINISTRATIVAS
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
    
    if (count($log) > 100) {
        $log = array_slice($log, -100);
    }
    
    update_option('bm_admin_audit_log', $log);
}

// ==========================================
// PÁGINA UNIFICADA DE CONFIGURAÇÕES
// ==========================================

function bm_render_access_settings_page() {
    if (!current_user_can('manage_options')) return;
    $settings = bm_get_settings();
    $msg = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_access_settings'])) {
        if (isset($_POST['librarian_permissions']) && is_array($_POST['librarian_permissions'])) {
            $settings['librarian_permissions'] = array();
            foreach (array('import_csv', 'export_csv', 'dynamic_fields', 'taxonomies', 'loans', 'approve_users', 'approve_readings', 'labels', 'service', 'students', 'student_import') as $perm) {
                $settings['librarian_permissions'][$perm] = isset($_POST['librarian_permissions'][$perm]) ? '1' : '0';
            }
        }
        if (isset($_POST['field_visibility']) && is_array($_POST['field_visibility'])) {
            $settings['field_visibility'] = array();
            foreach (array('isbn', 'location', 'copies', 'audit_log') as $field) {
                $settings['field_visibility'][$field] = array(
                    'student'   => isset($_POST['field_visibility'][$field]['student']) ? 1 : 0,
                    'teacher'   => isset($_POST['field_visibility'][$field]['teacher']) ? 1 : 0,
                    'librarian' => isset($_POST['field_visibility'][$field]['librarian']) ? 1 : 0,
                );
            }
        }
        update_option('bm_settings', $settings);
        $msg = '<div class="notice notice-success"><p>' . __('Configurações de acesso salvas!', 'book-manager') . '</p></div>';
    }
    ?>
    <h2><?php _e('Acessos e Visibilidade', 'book-manager'); ?></h2>
    <?php echo $msg; ?>
    
    <form method="post" style="max-width:600px;">
        <h3><?php _e('Permissões do Gestor', 'book-manager'); ?></h3>
    <p class="description"><?php _e('Marque quais funcionalidades o Gestor da Biblioteca pode acessar.', 'book-manager'); ?></p>
    <?php 
    $librarian_perms = isset($settings['librarian_permissions']) ? $settings['librarian_permissions'] : array(
        'import_csv' => '1', 'export_csv' => '1', 'dynamic_fields' => '1',
        'taxonomies' => '1', 'loans' => '1', 'approve_users' => '1',
        'approve_readings' => '1', 'labels' => '1', 'service' => '1',
        'students' => '1', 'student_import' => '1',
    );
    $perm_options = array(
        'import_csv' => 'Importar CSV',
        'export_csv' => 'Exportar CSV',
        'dynamic_fields' => 'Gerenciar Campos',
        'taxonomies' => 'Taxonomias',
        'loans' => 'Empréstimos',
        'approve_users' => 'Aprovar Cadastros',
        'approve_readings' => 'Aprovar Fichas',
        'labels' => 'Etiquetas',
        'service' => 'Atendimento',
        'students' => 'Alunos',
        'student_import' => 'Importar Alunos',
    );
    ?>
    <table class="form-table">
        <?php foreach ($perm_options as $key => $label): ?>
        <tr>
            <th><label><?php echo $label; ?></label></th>
            <td><label><input type="checkbox" name="librarian_permissions[<?php echo $key; ?>]" value="1" <?php checked(isset($librarian_perms[$key]) && $librarian_perms[$key] === '1'); ?> /> <?php _e('Permitir', 'book-manager'); ?></label></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h3><?php _e('Visibilidade de Campos por Perfil', 'book-manager'); ?></h3>
    <p class="description"><?php _e('Defina quais informações administrativas cada perfil vê na página pública do livro.', 'book-manager'); ?></p>
    <table class="form-table">
        <tr>
            <th></th>
            <th style="text-align:center;"><?php _e('Aluno', 'book-manager'); ?></th>
            <th style="text-align:center;"><?php _e('Professor', 'book-manager'); ?></th>
            <th style="text-align:center;"><?php _e('Gestor', 'book-manager'); ?></th>
        </tr>
        <?php
        $fields = array(
            'isbn'      => 'ISBN',
            'location'  => 'Localização',
            'copies'    => 'Exemplares',
            'audit_log' => 'Histórico de Ações',
        );
        $visibility = isset($settings['field_visibility']) ? $settings['field_visibility'] : array();
        foreach ($fields as $key => $label):
        ?>
        <tr>
            <th><label><?php echo $label; ?></label></th>
            <td style="text-align:center;"><input type="checkbox" name="field_visibility[<?php echo $key; ?>][student]" value="1" <?php checked(isset($visibility[$key]['student']) && $visibility[$key]['student']); ?> /></td>
            <td style="text-align:center;"><input type="checkbox" name="field_visibility[<?php echo $key; ?>][teacher]" value="1" <?php checked(isset($visibility[$key]['teacher']) && $visibility[$key]['teacher']); ?> /></td>
            <td style="text-align:center;"><input type="checkbox" name="field_visibility[<?php echo $key; ?>][librarian]" value="1" <?php checked(isset($visibility[$key]['librarian']) && $visibility[$key]['librarian']); ?> /></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p><input type="submit" name="save_access_settings" class="button button-primary" value="<?php _e('Salvar', 'book-manager'); ?>" /></p>
    </form>
    <?php
}

function bm_render_call_number_settings_page() {
    if (!current_user_can('manage_options')) return;
    $settings = bm_get_settings();
    $msg = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_call_number_settings'])) {
        $settings['classification_system'] = isset($_POST['classification_system']) && $_POST['classification_system'] === 'cdd' ? 'cdd' : 'cdu';
        if (isset($_POST['call_number_order']) && is_array($_POST['call_number_order'])) {
            $settings['call_number_order'] = array_map('sanitize_text_field', $_POST['call_number_order']);
        }
        update_option('bm_settings', $settings);
        $msg = '<div class="notice notice-success"><p>' . __('Configurações do Número de Chamada salvas!', 'book-manager') . '</p></div>';
    }
    ?>
    <h2><?php _e('Número de Chamada e Classificação', 'book-manager'); ?></h2>
    <?php echo $msg; ?>
    
    <form method="post" style="max-width:600px;">
    
    <h3><?php _e('Ordem do Número de Chamada', 'book-manager'); ?></h3>
    <p class="description"><?php _e('Arraste para definir a ordem de exibição.', 'book-manager'); ?></p>
    <?php 
    $call_number_order = isset($settings['call_number_order']) ? $settings['call_number_order'] : array('cdu', 'cutter', 'author', 'title', 'edition', 'volume', 'copies');
    $order_labels = array(
        'cdu' => __('Classificação', 'book-manager'),
        'cutter' => __('Cutter', 'book-manager'),
        'author' => __('Autor', 'book-manager'),
        'title' => __('Título', 'book-manager'),
        'edition' => __('Edição', 'book-manager'),
        'volume' => __('Volume', 'book-manager'),
        'copies' => __('Exemplares', 'book-manager'),
    );
    ?>
    <ul id="bm-call-number-order" style="max-width:300px;list-style:none;padding:0;">
        <?php foreach ($call_number_order as $field): ?>
            <li style="background:#f9f9f9;padding:8px 12px;margin:3px 0;border:1px solid #ddd;border-radius:4px;cursor:move;">
                <span class="dashicons dashicons-menu" style="color:#999;margin-right:8px;"></span>
                <?php echo esc_html($order_labels[$field]); ?>
                <input type="hidden" name="call_number_order[]" value="<?php echo esc_attr($field); ?>" />
            </li>
        <?php endforeach; ?>
    </ul>
    <script>
    jQuery(document).ready(function($) {
        $('#bm-call-number-order').sortable({handle: '.dashicons-menu'});
    });
    </script>
    
    <h3><?php _e('Sistema de Classificação', 'book-manager'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label><?php _e('CDU ou CDD', 'book-manager'); ?></label></th>
            <td>
                <label><input type="radio" name="classification_system" value="cdu" <?php checked($settings['classification_system'], 'cdu'); ?> /> <?php _e('Classificação CDU', 'book-manager'); ?></label><br>
                <label><input type="radio" name="classification_system" value="cdd" <?php checked($settings['classification_system'], 'cdd'); ?> /> <?php _e('Classificação CDD', 'book-manager'); ?></label>
                <p class="description"><?php _e('Define qual sistema de classificação a IA usará ao gerar o Número de Chamada.', 'book-manager'); ?></p>
            </td>
        </tr>
    </table>
    <p><input type="submit" name="save_call_number_settings" class="button button-primary" value="<?php _e('Salvar', 'book-manager'); ?>" /></p>
    </form>
    <?php
}

function bm_render_api_settings_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $keys = bm_get_api_keys();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_keys'])) {
        $new = array();
        $new['google_books_key'] = trim(sanitize_text_field($_POST['google_books_key']));
        $new['groq_key'] = trim(sanitize_text_field($_POST['groq_key']));
        $new['groq_active'] = isset($_POST['groq_active']) ? '1' : '0';
        $new['youtube_key'] = trim(sanitize_text_field($_POST['youtube_key']));
        $new['groq_persona'] = sanitize_textarea_field(wp_unslash($_POST['groq_persona']));
        $new['chatbot_active'] = isset($_POST['chatbot_active']) ? '1' : '0';
        $new['chatbot_name'] = sanitize_text_field(wp_unslash($_POST['chatbot_name']));
        
        update_option('bm_api_settings', $new);
        $keys = $new;
        $msg = '<div class="notice notice-success"><p>Salvo! Groq: ' . (empty($new['groq_key']) ? 'VAZIO' : 'OK') . ' | Ativo: ' . $new['groq_active'] . '</p></div>';
    }
    
    $groq_status = !empty($keys['groq_key']) && $keys['groq_active'] === '1' ? 'Groq ✅' : 'Nenhuma IA ativa';
    ?>
    <div class="wrap">
        <h1>APIs e Configurações</h1>
        <?php echo $msg; ?>
        
        <div style="background:#f9f9f9;padding:10px 15px;border-radius:4px;margin-bottom:15px;">
            <strong>IA Ativa:</strong> <?php echo $groq_status; ?>
        </div>
        
        <form method="post">
            <h2>📚 Google Books API</h2>
            <p><input type="text" name="google_books_key" value="<?php echo esc_attr($keys['google_books_key']); ?>" style="width:100%;" placeholder="AIza..." /></p>
            <p class="description">Busca automática de capas e sinopses.</p>
            
            <h2>▶️ YouTube Data API</h2>
            <p><input type="text" name="youtube_key" value="<?php echo esc_attr(isset($keys['youtube_key']) ? $keys['youtube_key'] : ''); ?>" style="width:100%;" placeholder="AIza..." /></p>
            <p class="description">Busca automática de vídeo-resenhas oficiais na importação CSV.</p>
            
            <h2>🤖 Groq (IA Gratuita)</h2>
            <p><input type="text" name="groq_key" value="<?php echo esc_attr($keys['groq_key']); ?>" style="width:100%;" placeholder="gsk_..." /></p>
            <p><label><input type="checkbox" name="groq_active" <?php checked($keys['groq_active'], '1'); ?> /> Ativar Groq</label></p>
            <p class="description">1.500 req/dia grátis · Llama 3 · <a href="https://console.groq.com" target="_blank">console.groq.com</a></p>
            <p>
                <label><strong>Tom e personalidade para classificação e atividades:</strong></label>
                <textarea name="groq_persona" rows="4" style="width:100%;max-width:600px;" placeholder="Ex: Você é um educador brasileiro, use tom lúdico e acessível..."><?php echo esc_textarea(isset($keys['groq_persona']) ? $keys['groq_persona'] : ''); ?></textarea>
            </p>
            <p class="description">Define como a IA se comporta ao classificar livros e gerar atividades. Se vazio, usa o tom padrão.</p>
                        
            <h2>💬 Chatbot</h2>
            <p><label><input type="checkbox" name="chatbot_active" <?php checked(isset($keys['chatbot_active']) ? $keys['chatbot_active'] : '1', '1'); ?> /> Ativar chatbot da Diva no site</label></p>
            <p class="description">Mostra o botão flutuante no canto inferior direito do site.</p>
            <h2>💬 Nome do Chatbot</h2>
            <p><input type="text" name="chatbot_name" value="<?php echo esc_attr(isset($keys['chatbot_name']) ? $keys['chatbot_name'] : 'Bibliotecária Virtual'); ?>" style="width:100%;max-width:400px;" placeholder="Bibliotecária Virtual" /></p>
            <p class="description">Nome exibido no cabeçalho do chat. Padrão: "Bibliotecária Virtual".</p>
            <p><input type="submit" name="save_keys" class="button button-primary" value="Salvar" /></p>
        </form>
    </div>
    <?php
}

function bm_render_white_label_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $wl = bm_get_white_label();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_white_label'])) {
        $wl['enabled'] = isset($_POST['wl_enabled']) ? '1' : '0';
        $wl['school_name'] = sanitize_text_field(wp_unslash($_POST['school_name']));
        $wl['school_logo'] = esc_url_raw($_POST['school_logo']);
        $wl['footer_text'] = sanitize_text_field(wp_unslash($_POST['footer_text']));
        $wl['school_url'] = esc_url_raw($_POST['school_url']);
        update_option('bm_white_label', $wl);
        $msg = '<div class="notice notice-success"><p>Salvo! Escola: ' . $wl['school_name'] . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Identidade Visual</h1>
        <?php echo $msg; ?>
        
        <form method="post" style="max-width:600px;">
            <p>
                <label><input type="checkbox" name="wl_enabled" <?php checked($wl['enabled'], '1'); ?> /> <strong>Ativar identidade visual personalizada</strong></label>
            </p>
            
            <h2>Personalização da Escola</h2>
            <table class="form-table">
                <tr>
                    <th><label>Nome da escola</label></th>
                    <td>
                        <input type="text" name="school_name" value="<?php echo esc_attr($wl['school_name']); ?>" style="width:100%;" placeholder="Ex: Escola Municipal Paulo Freire" />
                        <p class="description">Substitui "Catálogo de Livros" no título da vitrine.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>URL da escola</label></th>
                    <td>
                        <input type="url" name="school_url" value="<?php echo esc_attr($wl['school_url']); ?>" style="width:100%;" placeholder="https://..." />
                    </td>
                </tr>
                <tr>
                    <th><label>Logo da escola</label></th>
                    <td>
                        <input type="text" name="school_logo" id="bm_school_logo" value="<?php echo esc_attr($wl['school_logo']); ?>" style="width:80%;" placeholder="https://..." />
                        <button type="button" class="button" id="bm_upload_logo" style="margin-left:5px;">Upload</button>
                        <?php if ($wl['school_logo']): ?>
                            <br><img src="<?php echo esc_url($wl['school_logo']); ?>" style="max-width:200px;max-height:80px;margin-top:5px;" />
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label>Texto do rodapé</label></th>
                    <td>
                        <input type="text" name="footer_text" value="<?php echo esc_attr($wl['footer_text']); ?>" style="width:100%;" placeholder="Ex: Biblioteca Central — 2024" />
                        <p class="description">Exibido no rodapé da vitrine e páginas do livro.</p>
                    </td>
                </tr>
            </table>
            
            <p><input type="submit" name="save_white_label" class="button button-primary" value="Salvar" /></p>
        </form>
    </div>
    
    <script>
    document.getElementById('bm_upload_logo').addEventListener('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Selecionar logo', button: { text: 'Usar esta imagem' }, multiple: false });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('bm_school_logo').value = attachment.url;
        });
        frame.open();
    });
    </script>
    <?php
}

function bm_render_year_transition_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $settings = bm_get_year_transition_settings();
    $current_year = date('Y');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $settings['enabled'] = isset($_POST['yt_enabled']) ? '1' : '0';
        $settings['transition_month'] = absint($_POST['transition_month']);
        $settings['transition_day'] = absint($_POST['transition_day']);
        $settings['reset_xp'] = isset($_POST['reset_xp']) ? '1' : '0';
        $settings['reset_badges'] = isset($_POST['reset_badges']) ? '1' : '0';
        $settings['clear_reservations'] = isset($_POST['clear_reservations']) ? '1' : '0';
        $settings['activate_recadastro'] = isset($_POST['activate_recadastro']) ? '1' : '0';
        update_option('bm_year_transition', $settings);
        $msg = '<div class="notice notice-success"><p>Configurações salvas! Virada ' . ($settings['enabled'] === '1' ? 'ATIVADA' : 'DESATIVADA') . '.</p></div>';
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_history'])) {
        $settings['history_enabled'] = isset($_POST['history_enabled']) ? '1' : '0';
        $settings['clear_reading_log'] = isset($_POST['clear_reading_log']) ? '1' : '0';
        $settings['clear_reviews'] = isset($_POST['clear_reviews']) ? '1' : '0';
        $settings['clear_videos'] = isset($_POST['clear_videos']) ? '1' : '0';
        $settings['clear_ratings'] = isset($_POST['clear_ratings']) ? '1' : '0';
        $settings['clear_loan_history'] = isset($_POST['clear_loan_history']) ? '1' : '0';
        $settings['clear_before_year'] = sanitize_text_field($_POST['clear_before_year']);
        update_option('bm_year_transition', $settings);
        $msg = '<div class="notice notice-success"><p>Configurações de histórico salvas!</p></div>';
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_transition'])) {
        $students = get_users(array('role' => 'bm_student'));
        
        $rankings_backup = array();
        foreach ($students as $student) {
            $xp = get_user_meta($student->ID, '_bm_xp', true);
            $badges = get_user_meta($student->ID, '_bm_badges', true);
            $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
            $rankings_backup[] = array(
                'user_id' => $student->ID, 'name' => $student->display_name, 'email' => $student->user_email,
                'xp' => $xp, 'badges' => $badges, 'total_loans' => count($loan_history), 'year' => $current_year,
            );
        }
        update_option('bm_ranking_archive_' . $current_year, $rankings_backup);
        
        if ($settings['reset_xp'] === '1') {
            foreach ($students as $student) {
                delete_user_meta($student->ID, '_bm_xp');
                delete_user_meta($student->ID, '_bm_xp_history');
            }
        }
        
        if ($settings['reset_badges'] === '1') {
            foreach ($students as $student) {
                delete_user_meta($student->ID, '_bm_badges');
            }
        }
        
        if ($settings['clear_reservations'] === '1') {
            $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
            foreach ($all_books as $book) {
                $reservations = get_post_meta($book->ID, '_bm_reservations', true);
                if (!is_array($reservations)) continue;
                $cleaned = array();
                foreach ($reservations as $r) { if ($r['status'] === 'active') $cleaned[] = $r; }
                update_post_meta($book->ID, '_bm_reservations', $cleaned);
                update_post_meta($book->ID, '_bm_borrowed_count', count(array_filter($cleaned, function($r) { return $r['status'] === 'active'; })));
            }
            foreach ($students as $student) {
                delete_user_meta($student->ID, '_bm_active_reservations');
                delete_user_meta($student->ID, '_bm_reservation_count');
            }
        }
        
        if ($settings['history_enabled'] === '1') {
            $before_year = !empty($settings['clear_before_year']) ? intval($settings['clear_before_year']) : $current_year;
            
            foreach ($students as $student) {
                if ($settings['clear_reading_log'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    $cleaned = array();
                    foreach ($reading_log as $log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year >= $before_year) $cleaned[] = $log;
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $cleaned);
                }
                
                if ($settings['clear_reviews'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    foreach ($reading_log as &$log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year < $before_year) $log['review'] = '';
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $reading_log);
                }
                
                if ($settings['clear_videos'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    foreach ($reading_log as &$log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year < $before_year) $log['video_url'] = '';
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $reading_log);
                }
                
                if ($settings['clear_ratings'] === '1') {
                    $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
                    foreach ($reading_log as &$log) {
                        $log_year = date('Y', strtotime($log['date']));
                        if ($log_year < $before_year) $log['rating'] = 0;
                    }
                    update_user_meta($student->ID, '_bm_reading_log', $reading_log);
                }
                
                if ($settings['clear_loan_history'] === '1') {
                    $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
                    $cleaned = array();
                    foreach ($loan_history as $loan) {
                        $loan_year = date('Y', strtotime($loan['loan_date']));
                        if ($loan_year >= $before_year) $cleaned[] = $loan;
                    }
                    update_user_meta($student->ID, '_bm_loan_history', $cleaned);
                }
            }
        }
        
        if ($settings['activate_recadastro'] === '1') {
            update_option('bm_recadastro_required', '1');
            update_option('bm_recadastro_year', $current_year + 1);
        } else {
            update_option('bm_recadastro_required', '0');
        }
        
        $log = get_option('bm_year_transition_log', array());
        $log[] = array('date' => current_time('mysql'), 'user' => get_current_user_id(), 'settings' => $settings);
        update_option('bm_year_transition_log', $log);
        
        update_option('bm_last_year_transition', $current_year);
        
        $msg = '<div class="notice notice-success"><p><strong>✅ Virada de Ano Letivo concluída!</strong> Backup salvo como bm_ranking_archive_' . $current_year . '.</p></div>';
    }
    
    $last_transition = get_option('bm_last_year_transition', 'Nunca');
    $recadastro_active = get_option('bm_recadastro_required', '0');
    $transition_date = $settings['transition_day'] . '/' . $settings['transition_month'];
    ?>
    <div class="wrap">
        <h1>🔄 Virada de Ano Letivo</h1>
        <?php echo $msg; ?>
        
        <form method="post" style="max-width:700px;">
            <h2>Configurações</h2>
            <table class="form-table">
                <tr><th><label>Ativar sistema de virada de ano letivo</label></th><td><label><input type="checkbox" name="yt_enabled" <?php checked($settings['enabled'], '1'); ?> /> Habilitar</label><p class="description">Se desativado, todo histórico continua indefinidamente.</p></td></tr>
                <tr><th><label>Data da virada (mês/dia)</label></th><td>
                    <select name="transition_month" style="width:100px;"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>" <?php selected($settings['transition_month'], $m); ?>><?php echo date_i18n('F', mktime(0, 0, 0, $m, 1)); ?></option><?php endfor; ?></select>
                    <select name="transition_day" style="width:80px;margin-left:5px;"><?php for ($d = 1; $d <= 31; $d++): ?><option value="<?php echo $d; ?>" <?php selected($settings['transition_day'], $d); ?>><?php echo $d; ?></option><?php endfor; ?></select>
                    <p class="description">Define quando a virada acontece. Ex: 31/Dezembro (Brasil) ou 30/Junho (Austrália).</p></td></tr>
            </table>
            
            <h2>Ações da Virada</h2>
            <table class="form-table">
                <tr><th><label>Resetar pontuações (XP)</label></th><td><label><input type="checkbox" name="reset_xp" <?php checked($settings['reset_xp'], '1'); ?> /> Zerar pontuações de todos os alunos</label></td></tr>
                <tr><th><label>Resetar medalhas</label></th><td><label><input type="checkbox" name="reset_badges" <?php checked($settings['reset_badges'], '1'); ?> /> Zerar medalhas de todos os alunos</label></td></tr>
                <tr><th><label>Limpar reservas pendentes</label></th><td><label><input type="checkbox" name="clear_reservations" <?php checked($settings['clear_reservations'], '1'); ?> /> Remover todas as reservas não confirmadas</label><p class="description">Empréstimos ativos não serão afetados.</p></td></tr>
                <tr><th><label>Ativar recadastramento de alunos</label></th><td><label><input type="checkbox" name="activate_recadastro" <?php checked($settings['activate_recadastro'], '1'); ?> /> Exigir que alunos confirmem dados no próximo login</label><p class="description">Apenas alunos (bm_student) serão afetados.</p></td></tr>
            </table>
            
            <p><input type="submit" name="save_settings" class="button" value="Salvar Configurações" /></p>
        </form>
        
        <form method="post" style="max-width:700px;">
            <p><input type="submit" name="export_students_csv" class="button" value="📥 Exportar dados dos alunos (CSV)" /></p>
        </form>
        
        <form method="post" style="max-width:700px;">
        </form>
        
        <hr style="margin:30px 0;" />
        
        <form method="post" style="max-width:700px;">
            <h2>🗑️ Limpeza de Histórico</h2>
            <p class="description">⚠️ Esta seção controla a exclusão permanente de dados históricos dos alunos. Por padrão, o histórico NUNCA é apagado.</p>
            
            <table class="form-table">
                <tr><th><label>Habilitar limpeza de histórico</label></th><td><label><input type="checkbox" name="history_enabled" id="bm_history_toggle" <?php checked($settings['history_enabled'], '1'); ?> /> Permitir configurar limpeza de dados históricos</label></td></tr>
            </table>
            
            <div id="bm_history_options" style="<?php echo $settings['history_enabled'] === '1' ? '' : 'opacity:0.5;pointer-events:none;'; ?>">
                <table class="form-table">
                    <tr><th><label>Apagar fichas de leitura</label></th><td><label><input type="checkbox" name="clear_reading_log" <?php checked($settings['clear_reading_log'], '1'); ?> /> Remove fichas de leitura (_bm_reading_log)</label></td></tr>
                    <tr><th><label>Apagar resenhas (texto)</label></th><td><label><input type="checkbox" name="clear_reviews" <?php checked($settings['clear_reviews'], '1'); ?> /> Remove textos das resenhas</label></td></tr>
                    <tr><th><label>Apagar vídeo-resenhas (links)</label></th><td><label><input type="checkbox" name="clear_videos" <?php checked($settings['clear_videos'], '1'); ?> /> Remove links de vídeos</label></td></tr>
                    <tr><th><label>Apagar avaliações (estrelas)</label></th><td><label><input type="checkbox" name="clear_ratings" <?php checked($settings['clear_ratings'], '1'); ?> /> Remove notas com estrelas</label></td></tr>
                    <tr><th><label>Apagar histórico de empréstimos</label></th><td><label><input type="checkbox" name="clear_loan_history" <?php checked($settings['clear_loan_history'], '1'); ?> /> Remove histórico (_bm_loan_history)</label></td></tr>
                    <tr><th><label>Apagar apenas dados anteriores a (ano)</label></th><td><input type="number" name="clear_before_year" value="<?php echo esc_attr($settings['clear_before_year']); ?>" style="width:80px;" placeholder="<?php echo $current_year; ?>" /><p class="description">Deixe vazio para apagar tudo. Ex: "2024" apaga apenas dados de 2023 para trás.</p></td></tr>
                </table>
                <p><input type="submit" name="save_history" class="button" value="Salvar Configurações de Histórico" /></p>
            </div>
        </form>
        
        <hr style="margin:30px 0;" />
        
        <?php if ($settings['enabled'] === '1'): ?>
            <div style="background:#fff3f3;padding:15px;border-radius:8px;border:2px solid #dc3545;margin-bottom:20px;">
                <h2 style="color:#dc3545;margin-top:0;">⚠️ Executar Virada de Ano Letivo</h2>
                <p>Esta ação é <strong>irreversível</strong>. Um backup automático dos rankings será salvo antes.</p>
                <p><strong>Data configurada:</strong> <?php echo $transition_date; ?> | <strong>Última virada:</strong> <?php echo esc_html($last_transition); ?></p>
                <?php if ($recadastro_active === '1'): ?><p style="color:#dc3545;">Recadastramento ATIVO para <?php echo get_option('bm_recadastro_year', date('Y')); ?>.</p><?php endif; ?>
                
                <form method="post">
                    <p><strong>Digite "VIRADA <?php echo $current_year; ?>" para confirmar:</strong></p>
                    <input type="text" id="bm_confirm_text" style="width:300px;padding:8px;font-size:16px;" placeholder="VIRADA <?php echo $current_year; ?>" />
                    <br><br>
                    <input type="submit" name="execute_transition" id="bm_transition_btn" class="button button-primary" value="🔄 Executar Virada de Ano Letivo" disabled style="background:#dc3545;border-color:#dc3545;color:#fff;" />
                </form>
            </div>
            <script>document.getElementById('bm_confirm_text').addEventListener('input', function() { document.getElementById('bm_transition_btn').disabled = this.value !== 'VIRADA <?php echo $current_year; ?>'; });</script>
        <?php else: ?>
            <p style="color:#666;">O sistema de virada de ano letivo está <strong>desativado</strong>. Ative-o nas configurações acima.</p>
        <?php endif; ?>
    </div>
    
    <script>
    document.getElementById('bm_history_toggle').addEventListener('change', function() {
        if (this.checked) {
            var confirmed = confirm('⚠️ Atenção: Você está prest a acessar opções que podem apagar permanentemente o histórico pedagógico dos alunos. Recomendamos fortemente exportar esses dados via CSV antes de prosseguir. Deseja continuar?');
            if (!confirmed) { this.checked = false; return; }
        }
        var options = document.getElementById('bm_history_options');
        options.style.opacity = this.checked ? '1' : '0.5';
        options.style.pointerEvents = this.checked ? 'auto' : 'none';
    });
    </script>
    <?php
}

function bm_render_status_page() {
    if (!current_user_can('manage_options')) return;
    
    $plugin_data = get_plugin_data(plugin_dir_path(__FILE__) . '../book-manager.php');
    $keys = bm_get_api_keys();
    $settings = bm_get_settings();
    $total_books = wp_count_posts('bm_book');
    $total = $total_books->publish + $total_books->draft;
    $students = count(get_users(array('role' => 'bm_student')));
    
    $audit_log = get_option('bm_admin_audit_log', array());
    $last_actions = array_slice(array_reverse($audit_log), 0, 10);
    ?>
    <div class="wrap">
        <h1><?php _e('Status do Sistema', 'book-manager'); ?></h1>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:15px;margin-top:15px;">
            
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">🖥️ <?php _e('Ambiente', 'book-manager'); ?></h3>
                <p><strong>Plugin:</strong> <?php echo esc_html($plugin_data['Version']); ?></p>
                <p><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong>PHP:</strong> <?php echo phpversion(); ?></p>
                <p><strong>Memória:</strong> <?php echo ini_get('memory_limit'); ?></p>
            </div>
            
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">🔌 <?php _e('APIs', 'book-manager'); ?></h3>
                <p><strong>Google Books:</strong> <?php echo !empty($keys['google_books_key']) ? '✅ Configurada' : '❌ Não configurada'; ?></p>
                <p><strong>Groq (IA):</strong> <?php echo !empty($keys['groq_key']) ? '✅ Configurada' : '❌ Não configurada'; ?></p>
                <p><strong>IA Ativa:</strong> <?php echo ($keys['groq_active'] === '1' && !empty($keys['groq_key'])) ? '✅ Sim' : '❌ Não'; ?></p>
            </div>
            
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">📚 <?php _e('Acervo', 'book-manager'); ?></h3>
                <p><strong>Total de livros:</strong> <?php echo $total; ?></p>
                <p><strong>Alunos cadastrados:</strong> <?php echo $students; ?></p>
                <p><strong>Sistema:</strong> <?php echo $settings['classification_system'] === 'cdd' ? 'CDD' : 'CDU'; ?></p>
            </div>

            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">🤖 <?php _e('Uso da IA (Groq)', 'book-manager'); ?></h3>
                <?php 
                $groq_count = intval(get_option('bm_groq_call_count', 0));
                $groq_success = intval(get_option('bm_groq_success_count', 0));
                ?>
                <p><strong>Total de chamadas:</strong> <?php echo $groq_count; ?></p>
                <p><strong>Bem-sucedidas:</strong> <?php echo $groq_success; ?></p>
                <p><strong>Falhas:</strong> <?php echo max(0, $groq_count - $groq_success); ?></p>
            </div>
            
            <div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #ddd;">
                <h3 style="margin-top:0;">📋 <?php _e('Últimas Ações', 'book-manager'); ?></h3>
                <?php if (empty($last_actions)): ?>
                    <p style="color:#999;"><?php _e('Nenhuma ação registrada.', 'book-manager'); ?></p>
                <?php else: ?>
                    <ul style="margin:0;padding-left:15px;font-size:12px;">
                        <?php foreach ($last_actions as $action): ?>
                            <li style="margin:3px 0;">
                                <?php echo esc_html($action['time']); ?> — 
                                <strong><?php echo esc_html($action['admin_user']); ?></strong>: 
                                <?php echo esc_html($action['action']); ?> 
                                <?php echo esc_html($action['target_user']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
    <?php
}

function bm_render_penalty_rules_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $rules = get_option('bm_penalty_rules', array());
    if (!is_array($rules)) $rules = array();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_penalty_rules'])) {
        $new_rules = array();
        if (isset($_POST['rules']) && is_array($_POST['rules'])) {
            foreach ($_POST['rules'] as $rule) {
                if (!empty($rule['min_days']) || !empty($rule['penalty_value'])) {
                    $new_rules[] = array(
                        'min_days' => intval($rule['min_days']),
                        'max_days' => !empty($rule['max_days']) ? intval($rule['max_days']) : null,
                        'occurrence' => !empty($rule['occurrence']) ? intval($rule['occurrence']) : 0,
                        'penalty_type' => sanitize_text_field($rule['penalty_type']),
                        'penalty_value' => floatval($rule['penalty_value']),
                    );
                }
            }
        }
        update_option('bm_penalty_rules', $new_rules);
        $rules = $new_rules;
        $msg = '<div class="notice notice-success"><p>' . __('Regras de multa salvas!', 'book-manager') . '</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Regras de Multa', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        <p class="description"><?php _e('Configure as penalidades aplicadas automaticamente quando um aluno devolve um livro com atraso. Deixe vazio para não aplicar multas automáticas.', 'book-manager'); ?></p>
        
        <form method="post">
            <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th><?php _e('Atraso (dias)', 'book-manager'); ?></th>
                        <th><?php _e('Ocorrência', 'book-manager'); ?></th>
                        <th><?php _e('Tipo', 'book-manager'); ?></th>
                        <th><?php _e('Valor', 'book-manager'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="bm-penalty-rules">
                    <?php if (!empty($rules)): ?>
                        <?php foreach ($rules as $i => $rule): ?>
                        <tr>
                            <td><input type="number" name="rules[<?php echo $i; ?>][min_days]" value="<?php echo esc_attr($rule['min_days']); ?>" min="1" style="width:70px;" placeholder="<?php _e('Mín.', 'book-manager'); ?>" /> — <input type="number" name="rules[<?php echo $i; ?>][max_days]" value="<?php echo esc_attr($rule['max_days']); ?>" min="1" style="width:70px;" placeholder="<?php _e('Máx.', 'book-manager'); ?>" /></td>
                            <td><input type="number" name="rules[<?php echo $i; ?>][occurrence]" value="<?php echo esc_attr($rule['occurrence']); ?>" min="0" style="width:70px;" placeholder="0" /></td>
                            <td>
                                <select name="rules[<?php echo $i; ?>][penalty_type]" style="width:130px;">
                                    <option value="warning" <?php selected($rule['penalty_type'], 'warning'); ?>><?php _e('Advertência', 'book-manager'); ?></option>
                                    <option value="suspension" <?php selected($rule['penalty_type'], 'suspension'); ?>><?php _e('Suspensão (dias)', 'book-manager'); ?></option>
                                    <option value="fine" <?php selected($rule['penalty_type'], 'fine'); ?>><?php _e('Multa (R$)', 'book-manager'); ?></option>
                                </select>
                            </td>
                            <td><input type="number" name="rules[<?php echo $i; ?>][penalty_value]" value="<?php echo esc_attr($rule['penalty_value']); ?>" min="0" step="0.01" style="width:90px;" /></td>
                            <td><button type="button" class="button button-small" onclick="this.closest('tr').remove()">✕</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="bm-add-penalty-rule">+ <?php _e('Adicionar Regra', 'book-manager'); ?></button></p>
            <p><input type="submit" name="save_penalty_rules" class="button button-primary" value="<?php _e('Salvar Regras', 'book-manager'); ?>" /></p>
        </form>
    </div>
    <script>
    document.getElementById('bm-add-penalty-rule').addEventListener('click', function() {
        var tbody = document.getElementById('bm-penalty-rules');
        var rows = tbody.querySelectorAll('tr');
        var i = rows.length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="number" name="rules[' + i + '][min_days]" min="1" style="width:70px;" placeholder="Mín." /> — <input type="number" name="rules[' + i + '][max_days]" min="1" style="width:70px;" placeholder="Máx." /></td>' +
            '<td><input type="number" name="rules[' + i + '][occurrence]" min="0" style="width:70px;" placeholder="0" /></td>' +
            '<td><select name="rules[' + i + '][penalty_type]" style="width:130px;"><option value="warning">Advertência</option><option value="suspension">Suspensão (dias)</option><option value="fine">Multa (R$)</option></select></td>' +
            '<td><input type="number" name="rules[' + i + '][penalty_value]" min="0" step="0.01" style="width:90px;" /></td>' +
            '<td><button type="button" class="button button-small" onclick="this.closest(\'tr\').remove()">✕</button></td>';
        tbody.appendChild(tr);
    });
    </script>
    <?php
}

function bm_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    
    $msg = '';
    $settings = bm_get_settings();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
        $settings['max_reservations_student'] = absint($_POST['max_reservations_student']);
        $settings['max_loans_student'] = absint($_POST['max_loans_student']);
        $settings['default_loan_days'] = absint($_POST['default_loan_days']);
        $settings['reservation_hours'] = absint($_POST['reservation_hours']);
        $settings['loan_archive_days'] = absint($_POST['loan_archive_days']);
        $settings['xp_max_reading'] = absint($_POST['xp_max_reading']);
        $settings['xp_max_review'] = absint($_POST['xp_max_review']);
        $settings['xp_max_video'] = absint($_POST['xp_max_video']);
        $settings['xp_enabled'] = isset($_POST['xp_enabled']) ? '1' : '0';
        $settings['classification_system'] = isset($_POST['classification_system']) && $_POST['classification_system'] === 'cdd' ? 'cdd' : 'cdu';
        if (isset($_POST['call_number_order']) && is_array($_POST['call_number_order'])) {
            $settings['call_number_order'] = array_map('sanitize_text_field', $_POST['call_number_order']);
        }
        if (isset($_POST['cover_mode'])) {
            $settings['cover_mode'] = $_POST['cover_mode'] === 'hotlink' ? 'hotlink' : 'download';
        }    
        if (isset($_POST['librarian_permissions']) && is_array($_POST['librarian_permissions'])) {
            $settings['librarian_permissions'] = array();
            foreach (array('import_csv', 'export_csv', 'dynamic_fields', 'taxonomies', 'loans', 'approve_users', 'approve_readings', 'labels', 'service', 'students', 'student_import') as $perm) {
                $settings['librarian_permissions'][$perm] = isset($_POST['librarian_permissions'][$perm]) ? '1' : '0';
            }
        }
        if (isset($_POST['per_profile_limits']) && is_array($_POST['per_profile_limits'])) {
            $settings['per_profile_limits'] = array();
            foreach ($_POST['per_profile_limits'] as $limit) {
                if (!empty($limit['group'])) {
                    $settings['per_profile_limits'][] = array(
                        'group' => sanitize_text_field($limit['group']),
                        'max_reservations' => absint($limit['max_reservations']),
                        'max_loans' => absint($limit['max_loans']),
                    );
                }
            }
        }
        if (isset($_POST['field_visibility']) && is_array($_POST['field_visibility'])) {
            $settings['field_visibility'] = array();
            foreach (array('isbn', 'location', 'copies', 'audit_log') as $field) {
                $settings['field_visibility'][$field] = array(
                    'student'   => isset($_POST['field_visibility'][$field]['student']) ? 1 : 0,
                    'teacher'   => isset($_POST['field_visibility'][$field]['teacher']) ? 1 : 0,
                    'librarian' => isset($_POST['field_visibility'][$field]['librarian']) ? 1 : 0,
                );
            }
        }
        update_option('bm_settings', $settings);
        $msg = '<div class="notice notice-success"><p>Salvo! Reservas: ' . $settings['max_reservations_student'] . ' | Empréstimos: ' . $settings['max_loans_student'] . ' | Prazo: ' . $settings['default_loan_days'] . 'd | Reserva: ' . $settings['reservation_hours'] . 'h</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Limites e Prazos', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        
        <form method="post" style="max-width:600px;">
            <h3><?php _e('Limites Globais', 'book-manager'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Máximo de reservas por aluno', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="max_reservations_student" value="<?php echo esc_attr($settings['max_reservations_student']); ?>" min="1" max="10" style="width:80px;" />
                        <p class="description"><?php _e('Quantos livros um aluno pode reservar simultaneamente.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Máximo de empréstimos por aluno', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="max_loans_student" value="<?php echo esc_attr($settings['max_loans_student']); ?>" min="1" max="10" style="width:80px;" />
                        <p class="description"><?php _e('Quantos livros um aluno pode pegar emprestado simultaneamente.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Prazo padrão de empréstimo (dias)', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="default_loan_days" value="<?php echo esc_attr($settings['default_loan_days']); ?>" min="1" max="60" style="width:80px;" />
                        <p class="description"><?php _e('Prazo padrão ao confirmar um empréstimo.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Prazo de reserva (horas)', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="reservation_hours" value="<?php echo esc_attr($settings['reservation_hours']); ?>" min="1" max="72" style="width:80px;" />
                        <p class="description"><?php _e('Tempo máximo que uma reserva aguarda retirada.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Máx. pontos por leitura', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="xp_max_reading" value="<?php echo esc_attr($settings['xp_max_reading']); ?>" min="0" max="100" style="width:80px;" />
                        <p class="description"><?php _e('Nota máxima que o Gestor pode dar pela leitura.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Máx. pontos por resenha', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="xp_max_review" value="<?php echo esc_attr($settings['xp_max_review']); ?>" min="0" max="100" style="width:80px;" />
                        <p class="description"><?php _e('Nota máxima que o Gestor pode dar pela resenha.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Máx. pontos por vídeo', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="xp_max_video" value="<?php echo esc_attr($settings['xp_max_video']); ?>" min="0" max="100" style="width:80px;" />
                        <p class="description"><?php _e('Nota máxima que o Gestor pode dar pelo vídeo.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Ativar sistema de pontuação', 'book-manager'); ?></label></th>
                    <td>
                        <label><input type="checkbox" name="xp_enabled" <?php checked($settings['xp_enabled'], '1'); ?> /> <?php _e('Habilitar gamificação (XP, ranking, medalhas)', 'book-manager'); ?></label>
                        <p class="description"><?php _e('Se desativado, alunos continuam lendo e resenhando, mas sem ganhar pontos.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Dias para arquivamento', 'book-manager'); ?></label></th>
                    <td>
                        <input type="number" name="loan_archive_days" value="<?php echo esc_attr($settings['loan_archive_days']); ?>" min="30" max="3650" style="width:80px;" />
                        <p class="description"><?php _e('Empréstimos devolvidos há mais de X dias podem ser arquivados. Padrão: 1461 (4 anos).', 'book-manager'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Limites por Grupo (opcional)', 'book-manager'); ?></h3>
            <p class="description"><?php _e('Defina limites diferentes para grupos específicos de alunos. Se vazio, usa o limite global acima.', 'book-manager'); ?></p>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Grupo', 'book-manager'); ?></label></th>
                    <th><label><?php _e('Máx. Reservas', 'book-manager'); ?></label></th>
                    <th><label><?php _e('Máx. Empréstimos', 'book-manager'); ?></label></th>
                    <th></th>
                </tr>
                <?php 
                $per_profile = isset($settings['per_profile_limits']) ? $settings['per_profile_limits'] : array();
                if (!empty($per_profile)):
                    foreach ($per_profile as $i => $limit):
                ?>
                <tr>
                    <td><input type="text" name="per_profile_limits[<?php echo $i; ?>][group]" value="<?php echo esc_attr($limit['group']); ?>" placeholder="Ex: 1º Ano" style="width:120px;" /></td>
                    <td><input type="number" name="per_profile_limits[<?php echo $i; ?>][max_reservations]" value="<?php echo esc_attr($limit['max_reservations']); ?>" min="0" max="10" style="width:80px;" /></td>
                    <td><input type="number" name="per_profile_limits[<?php echo $i; ?>][max_loans]" value="<?php echo esc_attr($limit['max_loans']); ?>" min="0" max="10" style="width:80px;" /></td>
                    <td><button type="button" class="button button-small" onclick="this.closest('tr').remove()">✕</button></td>
                </tr>
                <?php 
                    endforeach;
                endif;
                ?>
                <tr id="bm-new-limit-row">
                    <td colspan="4">
                        <button type="button" class="button" id="bm-add-limit">+ <?php _e('Adicionar limite por grupo', 'book-manager'); ?></button>
                    </td>
                </tr>
            </table>
            <script>
            document.getElementById('bm-add-limit').addEventListener('click', function() {
                var tbody = this.closest('table').querySelector('tbody') || this.closest('table');
                var rows = tbody.querySelectorAll('tr');
                var count = rows.length;
                var newRow = document.createElement('tr');
                newRow.innerHTML = '<td><input type="text" name="per_profile_limits[' + count + '][group]" placeholder="Ex: 1º Ano" style="width:120px;" /></td>' +
                    '<td><input type="number" name="per_profile_limits[' + count + '][max_reservations]" value="" min="0" max="10" style="width:80px;" /></td>' +
                    '<td><input type="number" name="per_profile_limits[' + count + '][max_loans]" value="" min="0" max="10" style="width:80px;" /></td>' +
                    '<td><button type="button" class="button button-small" onclick="this.closest(\'tr\').remove()">✕</button></td>';
                var addRow = document.getElementById('bm-new-limit-row');
                addRow.parentNode.insertBefore(newRow, addRow);
            });
            </script>
            
            <h3><?php _e('Armazenamento de Capas', 'book-manager'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Modo de capa', 'book-manager'); ?></label></th>
                    <td>
                        <?php $cover_mode = isset($settings['cover_mode']) ? $settings['cover_mode'] : 'download'; ?>
                        <label><input type="radio" name="cover_mode" value="download" <?php checked($cover_mode, 'download'); ?> /> <?php _e('Baixar para o servidor (recomendado)', 'book-manager'); ?></label><br>
                        <label><input type="radio" name="cover_mode" value="hotlink" <?php checked($cover_mode, 'hotlink'); ?> /> <?php _e('Hotlink do Google Books (não ocupa espaço)', 'book-manager'); ?></label>
                        <p class="description"><?php _e('Hotlink exibe a imagem direto do Google. Se o Google alterar a URL, a capa pode sumir.', 'book-manager'); ?></p>
                    </td>
                </tr>
            </table>

            <p><input type="submit" name="save_settings" class="button button-primary" value="<?php _e('Salvar Configurações', 'book-manager'); ?>" /></p>
        </form>
    </div>
    <?php
}

function bm_render_settings_unified_page() {
    if (!current_user_can('manage_options')) return;
    
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    ?>
    <div class="wrap">
        <h1><?php _e('Configurações', 'book-manager'); ?></h1>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=general'); ?>" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">⚙️ <?php _e('Limites e Prazos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=access'); ?>" class="nav-tab <?php echo $tab === 'access' ? 'nav-tab-active' : ''; ?>">🔒 <?php _e('Acessos e Visibilidade', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=call_number'); ?>" class="nav-tab <?php echo $tab === 'call_number' ? 'nav-tab-active' : ''; ?>">📋 <?php _e('Nº Chamada e Classificação', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=apis'); ?>" class="nav-tab <?php echo $tab === 'apis' ? 'nav-tab-active' : ''; ?>">🔌 <?php _e('APIs', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=white_label'); ?>" class="nav-tab <?php echo $tab === 'white_label' ? 'nav-tab-active' : ''; ?>">🎨 <?php _e('Identidade Visual', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=year_transition'); ?>" class="nav-tab <?php echo $tab === 'year_transition' ? 'nav-tab-active' : ''; ?>">🔄 <?php _e('Virada de Ano', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=status'); ?>" class="nav-tab <?php echo $tab === 'status' ? 'nav-tab-active' : ''; ?>">📊 <?php _e('Status', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_settings&tab=penalties'); ?>" class="nav-tab <?php echo $tab === 'penalties' ? 'nav-tab-active' : ''; ?>">🚫 <?php _e('Regras de Multa', 'book-manager'); ?></a>
        </nav>
        
        <?php
        if ($tab === 'apis') {
            bm_render_api_settings_page();
        } elseif ($tab === 'access') {
            bm_render_access_settings_page();
        } elseif ($tab === 'call_number') {
            bm_render_call_number_settings_page();
        } elseif ($tab === 'white_label') {
            bm_render_white_label_page();
        } elseif ($tab === 'year_transition') {
            bm_render_year_transition_page();
        } elseif ($tab === 'status') {
            bm_render_status_page();
        } elseif ($tab === 'penalties') {
            bm_render_penalty_rules_page();
        } else {
            bm_render_settings_page();
        }
        ?>
    </div>
    <?php
}

// ==========================================
// TAXONOMIAS DINÂMICAS
// ==========================================

function bm_register_dynamic_taxonomies() {
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) $taxonomies = array();
    bm_install_default_taxonomies();
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) $taxonomies = array();
    
    $skip = array('bm_discipline');
    
    $default_labels = array(
        'bm_genre' => array(
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
        'bm_category' => array(
            'name'              => __('Categorias', 'book-manager'),
            'singular_name'     => __('Categoria', 'book-manager'),
            'search_items'      => __('Buscar Categorias', 'book-manager'),
            'all_items'         => __('Todas as Categorias', 'book-manager'),
            'parent_item'       => __('Categoria Pai', 'book-manager'),
            'edit_item'         => __('Editar Categoria', 'book-manager'),
            'update_item'       => __('Atualizar Categoria', 'book-manager'),
            'add_new_item'      => __('Adicionar Nova Categoria', 'book-manager'),
            'new_item_name'     => __('Nome da Nova Categoria', 'book-manager'),
            'menu_name'         => __('Categorias', 'book-manager'),
        ),
    );
    
    foreach ($taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
        
        $args = array(
            'label'        => $info['label'],
            'labels'       => isset($default_labels[$slug]) ? $default_labels[$slug] : array(),
            'rewrite'      => false,
            'hierarchical' => !empty($info['hierarchical']),
            'show_ui'      => true,
            'show_in_menu' => false,
            'map_meta_cap' => true,
            'show_admin_column' => true,
            'capabilities' => array(
                'manage_terms' => 'edit_bm_books',
                'edit_terms'   => 'edit_bm_books',
                'delete_terms' => 'edit_bm_books',
                'assign_terms' => 'edit_bm_books',
            ),
        );
        register_taxonomy($slug, 'bm_book', $args);
    }
}
add_action('init', 'bm_register_dynamic_taxonomies', 11);

function bm_render_taxonomies_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $msg = '';
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($taxonomies)) $taxonomies = array();
    
    if (isset($_POST['bm_add_taxonomy']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) {
        $name = sanitize_text_field($_POST['bm_taxonomy_name']);
        $slug = sanitize_key($_POST['bm_taxonomy_slug'] ?: $name);
        $hierarchical = isset($_POST['bm_taxonomy_hierarchical']);
        
        if (empty($name)) {
            $msg = '<div class="notice notice-error"><p>' . __('Nome é obrigatório.', 'book-manager') . '</p></div>';
        } elseif (taxonomy_exists($slug) || isset($taxonomies[$slug])) {
            $msg = '<div class="notice notice-error"><p>' . __('Já existe uma taxonomia com este slug.', 'book-manager') . '</p></div>';
        } else {
            $taxonomies[$slug] = array('label' => $name, 'hierarchical' => $hierarchical);
            update_option('bm_dynamic_taxonomies', $taxonomies);
            flush_rewrite_rules();
            $msg = '<div class="notice notice-success"><p>' . sprintf(__('Taxonomia "%s" criada!', 'book-manager'), $name) . '</p></div>';
        }
    }
    
    if (isset($_POST['bm_rename_taxonomies']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) {
        $taxonomies = get_option('bm_dynamic_taxonomies', array());
        if (isset($_POST['rename_taxonomy']) && is_array($_POST['rename_taxonomy'])) {
            foreach ($_POST['rename_taxonomy'] as $slug => $new_label) {
                $slug = sanitize_key($slug);
                $new_label = sanitize_text_field($new_label);
                if (isset($taxonomies[$slug]) && !empty($new_label)) {
                    if (empty($taxonomies[$slug]['protected'])) {
                        $taxonomies[$slug]['label'] = $new_label;
                    }
                }
            }
            update_option('bm_dynamic_taxonomies', $taxonomies);
            $msg = '<div class="notice notice-success"><p>' . __('Taxonomias renomeadas.', 'book-manager') . '</p></div>';
        }
    }
    
    if (isset($_POST['bm_delete_taxonomy']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) {
        $delete_slug = sanitize_key($_POST['bm_delete_slug']);
        if (isset($taxonomies[$delete_slug])) {
            if (!empty($taxonomies[$delete_slug]['protected'])) {
                $msg = '<div class="notice notice-error"><p>' . __('Taxonomias protegidas não podem ser removidas.', 'book-manager') . '</p></div>';
            } else {
                unset($taxonomies[$delete_slug]);
                update_option('bm_dynamic_taxonomies', $taxonomies);
                flush_rewrite_rules();
                $msg = '<div class="notice notice-success"><p>' . __('Taxonomia removida.', 'book-manager') . '</p></div>';
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Taxonomias Dinâmicas', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        
        <h2><?php _e('Criar Nova Taxonomia', 'book-manager'); ?></h2>
        <form method="post" style="max-width:500px;">
            <?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Nome', 'book-manager'); ?></label></th>
                    <td><input type="text" name="bm_taxonomy_name" required style="width:100%;" placeholder="<?php _e('Ex: Séries', 'book-manager'); ?>" /></td>
                </tr>
                <tr>
                    <th><label><?php _e('Slug', 'book-manager'); ?></label></th>
                    <td><input type="text" name="bm_taxonomy_slug" style="width:100%;" placeholder="<?php _e('Gerado automaticamente', 'book-manager'); ?>" /><p class="description"><?php _e('Deixe em branco para gerar a partir do nome.', 'book-manager'); ?></p></td>
                </tr>
                <tr>
                    <th><label><?php _e('Hierárquica', 'book-manager'); ?></label></th>
                    <td><label><input type="checkbox" name="bm_taxonomy_hierarchical" checked /> <?php _e('Permitir subcategorias (ex: pai/filho)', 'book-manager'); ?></label></td>
                </tr>
            </table>
            <p><input type="submit" name="bm_add_taxonomy" class="button button-primary" value="<?php _e('Criar Taxonomia', 'book-manager'); ?>" /></p>
        </form>
        
        <h2><?php _e('Taxonomias Existentes', 'book-manager'); ?></h2>
        <?php if (empty($taxonomies)): ?>
            <p><?php _e('Nenhuma taxonomia criada.', 'book-manager'); ?></p>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Nome', 'book-manager'); ?></th>
                            <th><?php _e('Slug', 'book-manager'); ?></th>
                            <th><?php _e('Hierárquica', 'book-manager'); ?></th>
                            <th><?php _e('Termos', 'book-manager'); ?></th>
                            <th><?php _e('Ações', 'book-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taxonomies as $slug => $info): ?>
                            <tr>
                                <td>
                                <?php if (isset($info['protected']) && $info['protected']): ?>
                                    <strong><?php echo esc_html($info['label']); ?></strong> 🔒
                                <?php else: ?>
                                    <input type="text" name="rename_taxonomy[<?php echo esc_attr($slug); ?>]" value="<?php echo esc_attr($info['label']); ?>" style="width:100%;" />
                                <?php endif; ?>
                            </td>
                                <td><code><?php echo esc_html($slug); ?></code></td>
                                <td><?php echo $info['hierarchical'] ? '✅' : '❌'; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('edit-tags.php?taxonomy=' . $slug . '&post_type=bm_book'); ?>" class="button button-small"><?php _e('Gerenciar Termos', 'book-manager'); ?></a>
                                </td>
                                <td>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('<?php _e('Remover esta taxonomia? Os termos criados serão perdidos.', 'book-manager'); ?>');">
                                        <?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?>
                                        <input type="hidden" name="bm_delete_slug" value="<?php echo esc_attr($slug); ?>">
                                        <button type="submit" name="bm_delete_taxonomy" class="button button-small"><?php _e('Remover', 'book-manager'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><input type="submit" name="bm_rename_taxonomies" class="button button-primary" value="<?php _e('Salvar Alterações', 'book-manager'); ?>" /></p>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// EXPORTAÇÃO CSV DE ALUNOS (admin_init)
// ==========================================
function bm_handle_students_csv_export() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_POST['export_students_csv'])) return;
    
    $current_year = date('Y');
    $students = get_users(array('role' => 'bm_student'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alunos_historico_' . $current_year . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Nome', 'E-mail', 'XP', 'Medalhas', 'Livros Lidos', 'Fichas', 'Resenhas', 'Vídeos', 'Empréstimos Ativos'), ';');
    foreach ($students as $student) {
        $xp = get_user_meta($student->ID, '_bm_xp', true) ?: '0';
        $badges = get_user_meta($student->ID, '_bm_badges', true) ?: array();
        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
        $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
        $active_loans = count(array_filter($loan_history, function($l) { return $l['status'] === 'active'; }));
        fputcsv($output, array(
            $student->display_name, $student->user_email, $xp, count($badges),
            count($loan_history), count($reading_log),
            count(array_filter($reading_log, function($l) { return !empty($l['review']); })),
            count(array_filter($reading_log, function($l) { return !empty($l['video_url']); })),
            $active_loans,
        ), ';');
    }
    fclose($output);
    exit;
}
add_action('admin_init', 'bm_handle_students_csv_export');

// ==========================================
// REGISTRO DE SUBPÁGINAS DE CONFIGURAÇÕES
// ==========================================
function bm_add_settings_page() {
    add_submenu_page('edit.php?post_type=bm_book', 'Configurações', 'Configurações', 'manage_options', 'bm_settings', 'bm_render_settings_unified_page');
}
add_action('admin_menu', 'bm_add_settings_page');

function bm_add_taxonomies_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Taxonomias', 'book-manager'), __('Taxonomias', 'book-manager'), 'edit_bm_books', 'bm_taxonomies', 'bm_render_taxonomies_page');
}
add_action('admin_menu', 'bm_add_taxonomies_page');

function bm_admin_media_scripts($hook) {
    if (strpos($hook, 'bm_white_label') === false) return;
    wp_enqueue_media();
}
add_action('admin_enqueue_scripts', 'bm_admin_media_scripts');