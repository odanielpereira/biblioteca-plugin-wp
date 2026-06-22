<?php
/**
 * Book Manager — Módulo de Serviços Administrativos
 * Balcão, alunos, relatórios, taxonomias, etiquetas, carteirinhas, filtros
 */

defined('ABSPATH') || exit;

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
// LISTAGEM E FILTROS ADMIN
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
    <div class="bm-filter-form">
    <?php
    echo '<input type="hidden" name="post_type" value="bm_book">';
    if (isset($_GET['orderby']) && !empty($_GET['orderby'])) echo '<input type="hidden" name="orderby" value="'.esc_attr(sanitize_text_field($_GET['orderby'])).'">';
    if (isset($_GET['order']) && !empty($_GET['order'])) echo '<input type="hidden" name="order" value="'.esc_attr(sanitize_text_field($_GET['order'])).'">';
    if (isset($_GET['s'])&&!empty($_GET['s'])) echo '<input type="hidden" name="s" value="'.esc_attr(sanitize_text_field($_GET['s'])).'">';
    ?>
    <p><label for="_bm_author"><?php _e('Autor:','book-manager'); ?></label><input type="text" id="_bm_author" name="_bm_author" value="<?php echo esc_attr($fa); ?>" placeholder="<?php _e('Filtrar por autor','book-manager'); ?>"></p>
    <p><label for="_bm_publisher"><?php _e('Editora:','book-manager'); ?></label><input type="text" id="_bm_publisher" name="_bm_publisher" value="<?php echo esc_attr($fp); ?>" placeholder="<?php _e('Filtrar por editora','book-manager'); ?>"></p>
    <?php
  
    wp_dropdown_categories(array('show_option_all'=>__('Todos os Gêneros','book-manager'),'taxonomy'=>'bm_genre','name'=>'bm_genre_filter','selected'=>isset($_GET['bm_genre_filter'])?$_GET['bm_genre_filter']:''));
    wp_dropdown_categories(array('show_option_all'=>__('Todas as Categorias','book-manager'),'taxonomy'=>'bm_category','name'=>'bm_category_filter','selected'=>isset($_GET['bm_category_filter'])?$_GET['bm_category_filter']:''));


    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($dynamic_taxonomies)) $dynamic_taxonomies = array();
    $skip = array('bm_genre', 'bm_category', 'bm_discipline');
    foreach ($dynamic_taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
        wp_dropdown_categories(array(
            'show_option_all' => $info['label'],
            'taxonomy' => $slug,
            'name' => $slug . '_filter',
            'selected' => isset($_GET[$slug . '_filter']) ? $_GET[$slug . '_filter'] : '',
        ));
    }
    ?>
    <input type="submit" name="filter_action" class="button" value="<?php _e('Filtrar','book-manager'); ?>">
    <a href="<?php echo admin_url('edit.php?post_type=bm_book'); ?>" class="button"><?php _e('Limpar Filtros','book-manager'); ?></a>
    </div><?php
}
add_action('restrict_manage_posts','bm_add_book_filter_form');
function bm_filter_books_by_metadata($query) {
    if (!is_admin()||!$query->is_main_query()||'bm_book'!==$query->get('post_type')) return;
    
    if (isset($_GET['action']) || isset($_GET['action2'])) return;
    
    $meta = array();
    if (isset($_GET['_bm_author'])&&!empty($_GET['_bm_author'])) $meta[]=array('key'=>'_bm_author','value'=>sanitize_text_field($_GET['_bm_author']),'compare'=>'LIKE');
    if (isset($_GET['_bm_publisher'])&&!empty($_GET['_bm_publisher'])) $meta[]=array('key'=>'_bm_publisher','value'=>sanitize_text_field($_GET['_bm_publisher']),'compare'=>'LIKE');
    if (!empty($meta)) { $meta['relation']='AND'; $query->set('meta_query',$meta); }
    if (isset($_GET['bm_genre_filter'])&&!empty($_GET['bm_genre_filter'])) { $tq=$query->get('tax_query')?:array(); $tq[]=array('taxonomy'=>'bm_genre','field'=>'term_id','terms'=>intval($_GET['bm_genre_filter'])); $query->set('tax_query',$tq); }
    if (isset($_GET['bm_category_filter'])&&!empty($_GET['bm_category_filter'])) { $tq=$query->get('tax_query')?:array(); $tq[]=array('taxonomy'=>'bm_category','field'=>'term_id','terms'=>intval($_GET['bm_category_filter'])); $query->set('tax_query',$tq); }

    $dynamic_taxonomies = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($dynamic_taxonomies)) $dynamic_taxonomies = array();
    foreach ($dynamic_taxonomies as $slug => $info) {
        if (isset($_GET[$slug . '_filter']) && !empty($_GET[$slug . '_filter'])) {
            $tq = $query->get('tax_query') ?: array();
            $tq[] = array('taxonomy' => $slug, 'field' => 'term_id', 'terms' => intval($_GET[$slug . '_filter']));
            $query->set('tax_query', $tq);
        }
    }
}
add_action('pre_get_posts','bm_filter_books_by_metadata');

// ==========================================
// BALCÃO DE ATENDIMENTO
// ==========================================
function bm_add_service_desk_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Balcão de Atendimento', 'book-manager'), __('Balcão de Atendimento', 'book-manager'), 'edit_bm_books', 'bm_service_desk', 'bm_render_service_desk_page');
}
add_action('admin_menu', 'bm_add_service_desk_page');

function bm_render_service_desk_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'loans';
    ?>
    <div class="wrap">
        <h1><?php _e('Balcão de Atendimento', 'book-manager'); ?></h1>
        
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_service_desk&tab=loans'); ?>" class="nav-tab <?php echo $tab === 'loans' ? 'nav-tab-active' : ''; ?>">📋 <?php _e('Empréstimos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_service_desk&tab=service'); ?>" class="nav-tab <?php echo $tab === 'service' ? 'nav-tab-active' : ''; ?>">📤 <?php _e('Atendimento', 'book-manager'); ?></a>
        </nav>
        
        <?php
        if ($tab === 'service') {
            bm_render_service_page_content();
        } else {
            bm_render_loans_page_content();
        }
        ?>
    </div>
    <?php
}

function bm_render_service_page_content() {
    $nonce = wp_create_nonce('bm_service_nonce');
    ?>
    <div class="wrap" style="max-width:1100px;">
        <h1>📋 <?php _e('Atendimento — Balcão', 'book-manager'); ?></h1>
        
        <div style="background:#f0f7ff;padding:10px 15px;border-radius:6px;margin-bottom:15px;border-left:4px solid #2196f3;">
            <label style="font-weight:bold;">📷 <?php _e('Leitor de Código de Barras', 'book-manager'); ?></label>
            <input type="text" id="bm-barcode-input" placeholder="<?php _e('Escaneie o ISBN ou digite...', 'book-manager'); ?>" style="width:100%;padding:10px;font-size:16px;margin-top:5px;border:2px solid #2196f3;border-radius:4px;" autofocus />
            <p class="description" style="margin:5px 0 0 0;"><?php _e('Escaneie o código de barras ou digite o ISBN e pressione Enter para buscar o livro.', 'book-manager'); ?></p>
        </div>
        
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:350px;background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;">
                <h2 style="margin-top:0;">📖 <?php _e('Livro', 'book-manager'); ?></h2>
                <div style="display:flex;gap:10px;margin-bottom:10px;">
                    <input type="text" id="bm-book-search" placeholder="<?php _e('Buscar por título, autor ou ISBN...', 'book-manager'); ?>" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;" />
                    <button type="button" id="bm-book-search-btn" class="button">🔍</button>
                </div>
                <div id="bm-book-result" style="min-height:100px;padding:10px;background:#f9f9f9;border-radius:4px;">
                    <p style="color:#999;"><?php _e('Busque um livro ou escaneie o código de barras.', 'book-manager'); ?></p>
                </div>
                <div id="bm-book-queue" style="margin-top:10px;display:none;"></div>
            </div>
            
            <div style="flex:1;min-width:350px;background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;">
                <h2 style="margin-top:0;">👤 <?php _e('Aluno', 'book-manager'); ?></h2>
                <div style="display:flex;gap:10px;margin-bottom:10px;">
                    <input type="text" id="bm-student-search" placeholder="<?php _e('Buscar por nome ou e-mail...', 'book-manager'); ?>" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;" />
                    <button type="button" id="bm-student-search-btn" class="button">🔍</button>
                </div>
                <div id="bm-student-result" style="min-height:100px;padding:10px;background:#f9f9f9;border-radius:4px;">
                    <p style="color:#999;"><?php _e('Busque um aluno.', 'book-manager'); ?></p>
                </div>
                <div style="margin-top:10px;">
                    <button type="button" id="bm-new-student-btn" class="button" style="width:100%;">➕ <?php _e('Cadastrar Novo Aluno', 'book-manager'); ?></button>
                </div>
            </div>
        </div>
        
        <div id="bm-action-area" style="margin-top:20px;text-align:center;display:none;">
            <button type="button" id="bm-loan-btn" class="button button-primary" style="font-size:18px;padding:15px 40px;">📤 <?php _e('EMPRESTAR', 'book-manager'); ?></button>
            <button type="button" id="bm-return-btn" class="button" style="font-size:18px;padding:15px 40px;background:#46b450;color:#fff;border-color:#46b450;">📥 <?php _e('DEVOLVER', 'book-manager'); ?></button>
            <button type="button" id="bm-renew-btn" class="button" style="font-size:16px;padding:12px 30px;background:#ffc107;color:#111;border-color:#ffc107;">🔄 <?php _e('Renovar +7 dias', 'book-manager'); ?></button>
        </div>
        
        <div id="bm-action-result" style="margin-top:15px;display:none;"></div>
    </div>
    
    <!-- Modal de cadastro rápido de aluno -->
    <div id="bm-quick-register-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:25px;border-radius:8px;max-width:450px;width:90%;max-height:80vh;overflow-y:auto;">
            <h2 style="margin-top:0;" id="bm-modal-title">➕ <?php _e('Cadastro Rápido de Aluno', 'book-manager'); ?></h2>
            <form id="bm-quick-register-form" onsubmit="return false;">
                <?php wp_nonce_field('bm_service_nonce', 'bm_quick_register_nonce'); ?>
                <p><label><strong><?php _e('Nome completo', 'book-manager'); ?> *</strong></label><input type="text" name="bm_quick_name" required style="width:100%;padding:8px;margin-top:4px;" /></p>
                <p><label><strong><?php _e('E-mail', 'book-manager'); ?> *</strong></label><input type="email" name="bm_quick_email" required style="width:100%;padding:8px;margin-top:4px;" /></p>
                <p><label><strong><?php _e('Telefone', 'book-manager'); ?></strong></label><input type="text" name="bm_quick_phone" style="width:100%;padding:8px;margin-top:4px;" placeholder="5511999999999" /></p>
                <?php
                $user_fields = get_option('bm_user_dynamic_fields', array());
                $user_field_order = get_option('bm_user_field_order', array());
                $ordered_fields = array();
                foreach ($user_field_order as $key) { if (isset($user_fields[$key])) $ordered_fields[$key] = $user_fields[$key]; }
                foreach ($user_fields as $key => $info) { if (!isset($ordered_fields[$key])) $ordered_fields[$key] = $info; }
                foreach ($ordered_fields as $field_name => $info):
                    $name_lower = mb_strtolower(trim($field_name));
                    if (in_array($name_lower, array('nome completo', 'e-mail', 'email', 'telefone'))) continue;
                    $meta_key = '_bm_user_' . sanitize_key($field_name);
                ?>
                <p><label><strong><?php echo esc_html($field_name); ?></strong></label><input type="text" name="<?php echo esc_attr($meta_key); ?>" style="width:100%;padding:8px;margin-top:4px;" /></p>
                <?php endforeach; ?>
                <p style="margin-top:15px;display:flex;gap:10px;">
                    <button type="submit" class="button button-primary" style="flex:1;" id="bm-modal-submit-btn"><?php _e('Cadastrar', 'book-manager'); ?></button>
                    <button type="button" class="button" onclick="document.getElementById('bm-quick-register-modal').style.display='none'" style="flex:1;"><?php _e('Cancelar', 'book-manager'); ?></button>
                </p>
            </form>
        </div>
    </div>

    <!-- Modal de danos na devolução -->
    <div id="bm-damage-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:25px;border-radius:8px;max-width:400px;width:90%;">
            <h3 style="margin-top:0;">📋 <?php _e('Registro de Devolução', 'book-manager'); ?></h3>
            <p><label><strong><?php _e('Estado do livro:', 'book-manager'); ?></strong></label><select id="bm-damage-status" style="width:100%;padding:8px;margin-top:4px;"><option value="good">✅ <?php _e('Bom', 'book-manager'); ?></option><option value="acceptable">⚠️ <?php _e('Aceitável', 'book-manager'); ?></option><option value="damaged">❌ <?php _e('Danificado', 'book-manager'); ?></option></select></p>
            <p><label><strong><?php _e('Observação:', 'book-manager'); ?></strong></label><textarea id="bm-damage-note" rows="3" style="width:100%;margin-top:4px;" placeholder="<?php _e('Descreva o dano...', 'book-manager'); ?>"></textarea></p>
            <p style="display:flex;gap:10px;">
                <button type="button" id="bm-confirm-return" class="button button-primary" style="flex:1;"><?php _e('Confirmar Devolução', 'book-manager'); ?></button>
                <button type="button" class="button" onclick="document.getElementById('bm-damage-modal').style.display='none'" style="flex:1;"><?php _e('Cancelar', 'book-manager'); ?></button>
            </p>
        </div>
    </div>
    
    <script>
    var bmNonce = '<?php echo $nonce; ?>';
    var bmAjaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
    var bmSelectedBook = null;
    var bmSelectedStudent = null;
    
    document.getElementById('bm-barcode-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            var isbn = this.value.trim();
            if (isbn) bmSearchBookByISBN(isbn);
        }
    });
    
    document.getElementById('bm-book-search-btn').addEventListener('click', function() {
        var query = document.getElementById('bm-book-search').value.trim();
        if (query) bmSearchBook(query);
    });
    document.getElementById('bm-book-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            var query = this.value.trim();
            if (query) bmSearchBook(query);
        }
    });
    
    document.getElementById('bm-student-search-btn').addEventListener('click', function() {
        var query = document.getElementById('bm-student-search').value.trim();
        if (query) bmSearchStudent(query);
    });
    document.getElementById('bm-student-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            var query = this.value.trim();
            if (query) bmSearchStudent(query);
        }
    });
    
    document.getElementById('bm-new-student-btn').addEventListener('click', function() {
        document.getElementById('bm-quick-register-modal').style.display = 'flex';
    });
    
    function bmSearchBookByISBN(isbn) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.found) { bmDisplayBook(r.book); }
                else if (r.can_register) { bmShowBookNotFound(isbn, r.isbn); }
                else { document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>'; }
            } catch(e) { document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>'; }
        };
        xhr.send('action=bm_service_search_book&isbn=' + encodeURIComponent(isbn) + '&nonce=' + bmNonce);
    }
    
    function bmSearchBook(query) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.found) { bmDisplayBook(r.book); }
                else { document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>'; }
            } catch(e) { document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>'; }
        };
        xhr.send('action=bm_service_search_book&query=' + encodeURIComponent(query) + '&nonce=' + bmNonce);
    }
    
    function bmDisplayBook(book) {
        bmSelectedBook = book;
        var stockColor = book.available > 0 ? '#46b450' : '#dc3545';
        var html = '<div style="padding:10px;">';
        html += '<h3 style="margin:0 0 5px 0;">' + book.title + '</h3>';
        if (book.author) html += '<p style="margin:3px 0;"><strong>Autor:</strong> ' + book.author + '</p>';
        if (book.cdu) html += '<p style="margin:3px 0;"><strong>Classificação:</strong> ' + book.cdu + '</p>';
        html += '<p style="margin:3px 0;"><strong>Disponível:</strong> <span style="color:' + stockColor + ';font-weight:bold;">' + book.available + '/' + book.total + '</span></p>';
        if (book.consulta_local) html += '<p style="margin:3px 0;color:#dc3545;">📌 <strong>Consulta local</strong> — não pode sair da biblioteca</p>';
        if (book.overdue) html += '<p style="margin:3px 0;color:#dc3545;">⚠️ Este livro está com devolução atrasada</p>';
        html += '</div>';
        document.getElementById('bm-book-result').innerHTML = html;
        
        if (book.queue && book.queue.length > 0) {
            var qHtml = '<div style="margin-top:10px;padding:10px;background:#fff8e1;border-radius:4px;"><strong>📋 Fila de espera:</strong><ol style="margin:5px 0;padding-left:20px;">';
            book.queue.forEach(function(q) { qHtml += '<li>' + q.name + ' (desde ' + q.date + ')</li>'; });
            qHtml += '</ol></div>';
            document.getElementById('bm-book-queue').style.display = 'block';
            document.getElementById('bm-book-queue').innerHTML = qHtml;
        } else {
            document.getElementById('bm-book-queue').style.display = 'none';
        }
        bmCheckActionReady();
    }
    
    function bmShowBookNotFound(isbn, cleanIsbn) {
        var html = '<p style="color:#dc3545;">Livro não encontrado no acervo.</p>';
        html += '<button type="button" class="button button-primary" onclick="bmRegisterBookByISBN(\'' + cleanIsbn + '\')">📚 Cadastrar este livro via Google Books</button>';
        document.getElementById('bm-book-result').innerHTML = html;
    }
    
    function bmSearchStudent(query) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            try {
                var r = JSON.parse(xhr.responseText);
                if (r.found) { bmDisplayStudent(r.student); }
                else if (r.multiple) { bmDisplayStudentList(r.students); }
                else { document.getElementById('bm-student-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>'; }
            } catch(e) { document.getElementById('bm-student-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>'; }
        };
        xhr.send('action=bm_service_search_student&query=' + encodeURIComponent(query) + '&nonce=' + bmNonce);
    }
    
    function bmDisplayStudent(student) {
        bmSelectedStudent = student;
        var html = '<div style="padding:10px;">';
        html += '<h3 style="margin:0 0 5px 0;"><a href="<?php echo admin_url("edit.php?post_type=bm_book&page=bm_student_detail"); ?>&student_id=' + student.id + '" style="text-decoration:none;color:#111;" target="_blank">' + student.name + '</a> <button type="button" class="button button-small" onclick="bmEditStudent(' + student.id + ')" style="margin-left:10px;font-size:11px;">✏️ Editar</button></h3>';
        html += '<p style="margin:3px 0;"><strong>E-mail:</strong> ' + student.email + '</p>';
        if (student.group) html += '<p style="margin:3px 0;"><strong>Grupo:</strong> ' + student.group + '</p>';
        html += '<p style="margin:3px 0;"><strong>Empréstimos ativos:</strong> ' + student.active_loans + '/' + student.max_loans + '</p>';
        if (student.has_overdue) html += '<p style="margin:3px 0;color:#dc3545;">⚠️ <strong>Possui livro em atraso</strong></p>';
        if (student.blocked) html += '<p style="margin:3px 0;color:#dc3545;">🚫 <strong>Empréstimo bloqueado</strong> — aluno com atraso</p>';
        if (student.recent_books && student.recent_books.length > 0) {
            html += '<p style="margin:5px 0 3px 0;"><strong>Últimos livros:</strong></p>';
            student.recent_books.forEach(function(b) { html += '<span style="display:inline-block;background:#e3f2fd;padding:2px 8px;border-radius:10px;font-size:11px;margin:2px;">' + b + '</span> '; });
        }
        html += '</div>';
        document.getElementById('bm-student-result').innerHTML = html;
        bmCheckActionReady();
    }
    
    function bmDisplayStudentList(students) {
        var html = '<p style="margin:0 0 10px 0;">Múltiplos alunos encontrados:</p>';
        students.forEach(function(s) {
            html += '<div style="padding:8px;margin:3px 0;background:#fff;border:1px solid #eee;border-radius:4px;cursor:pointer;" onclick="bmSelectStudent(' + s.id + ')">';
            html += '<strong>' + s.name + '</strong> — ' + s.email;
            if (s.group) html += ' | ' + s.group;
            html += '</div>';
        });
        document.getElementById('bm-student-result').innerHTML = html;
    }
    
    function bmSelectStudent(id) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() { var r = JSON.parse(xhr.responseText); if (r.found) bmDisplayStudent(r.student); };
        xhr.send('action=bm_service_search_student&student_id=' + id + '&nonce=' + bmNonce);
    }
    
    function bmCheckActionReady() {
        if (bmSelectedBook && bmSelectedStudent) {
            document.getElementById('bm-action-area').style.display = 'block';
        }
    }
    
    document.getElementById('bm-loan-btn').addEventListener('click', function() {
        if (!bmSelectedBook || !bmSelectedStudent) return;
        if (bmSelectedStudent.blocked) { alert('Aluno com atraso — empréstimo bloqueado.'); return; }
        if (bmSelectedBook.consulta_local) { alert('Este livro é de consulta local e não pode ser emprestado.'); return; }
        if (bmSelectedBook.available <= 0) { alert('Não há exemplares disponíveis.'); return; }
        var days = prompt('Prazo do empréstimo (dias):', '14');
        if (!days) return;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            var area = document.getElementById('bm-action-result');
            area.style.display = 'block';
            area.innerHTML = '<div class="notice notice-' + (r.success ? 'success' : 'error') + '"><p>' + r.message + '</p></div>';
            if (r.success) {
                bmSelectedBook = null; bmSelectedStudent = null;
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#999;">Busque um livro.</p>';
                document.getElementById('bm-student-result').innerHTML = '<p style="color:#999;">Busque um aluno.</p>';
                document.getElementById('bm-action-area').style.display = 'none';
                document.getElementById('bm-book-queue').style.display = 'none';
            }
            if (r.success && r.receipt_url) {
                area.innerHTML += ' <a href="' + r.receipt_url + '" target="_blank" class="button" style="margin-left:10px;background:#111;color:#fff;border:none;">🧾 Imprimir Comprovante</a>';
            }
        };
        xhr.send('action=bm_service_loan&book_id=' + bmSelectedBook.id + '&user_id=' + bmSelectedStudent.id + '&days=' + days + '&nonce=' + bmNonce);
    });
    
    document.getElementById('bm-return-btn').addEventListener('click', function() {
        if (!bmSelectedBook || !bmSelectedStudent) return;
        document.getElementById('bm-damage-modal').style.display = 'flex';
    });
    
    document.getElementById('bm-confirm-return').addEventListener('click', function() {
        var condition = document.getElementById('bm-damage-status').value;
        var note = document.getElementById('bm-damage-note').value;
        document.getElementById('bm-damage-modal').style.display = 'none';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            var area = document.getElementById('bm-action-result');
            area.style.display = 'block';
            area.innerHTML = '<div class="notice notice-' + (r.success ? 'success' : 'error') + '"><p>' + r.message + '</p></div>';
            if (r.success) {
                bmSelectedBook = null; bmSelectedStudent = null;
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#999;">Busque um livro.</p>';
                document.getElementById('bm-student-result').innerHTML = '<p style="color:#999;">Busque um aluno.</p>';
                document.getElementById('bm-action-area').style.display = 'none';
                document.getElementById('bm-book-queue').style.display = 'none';
            }
        };
        xhr.send('action=bm_service_return&book_id=' + bmSelectedBook.id + '&user_id=' + bmSelectedStudent.id + '&condition=' + condition + '&note=' + encodeURIComponent(note) + '&nonce=' + bmNonce);
    });
    
    document.getElementById('bm-renew-btn').addEventListener('click', function() {
        if (!bmSelectedBook || !bmSelectedStudent) return;
        var days = prompt('Renovar por quantos dias?', '7');
        if (!days) return;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            var area = document.getElementById('bm-action-result');
            area.style.display = 'block';
            area.innerHTML = '<div class="notice notice-' + (r.success ? 'success' : 'error') + '"><p>' + r.message + '</p></div>';
        };
        xhr.send('action=bm_service_renew&book_id=' + bmSelectedBook.id + '&user_id=' + bmSelectedStudent.id + '&days=' + days + '&nonce=' + bmNonce);
    });
    
    document.getElementById('bm-quick-register-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var editId = form.getAttribute('data-edit-id');
        var params = 'nonce=' + bmNonce;
        params += '&name=' + encodeURIComponent(form.querySelector('[name="bm_quick_name"]').value);
        params += '&email=' + encodeURIComponent(form.querySelector('[name="bm_quick_email"]').value);
        params += '&phone=' + encodeURIComponent(form.querySelector('[name="bm_quick_phone"]').value);
        var dynamicInputs = form.querySelectorAll('input[name^="_bm_user_"]');
        dynamicInputs.forEach(function(input) { params += '&' + input.name + '=' + encodeURIComponent(input.value); });
        if (editId) { params = 'action=bm_service_edit_student&student_id=' + editId + '&' + params; }
        else { params = 'action=bm_service_quick_register&' + params; }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.success) {
                document.getElementById('bm-quick-register-modal').style.display = 'none';
                form.removeAttribute('data-edit-id');
                document.getElementById('bm-modal-title').textContent = '➕ Cadastro Rápido de Aluno';
                document.getElementById('bm-modal-submit-btn').textContent = 'Cadastrar';
                if (editId) { bmSelectStudent(editId); }
                else {
                    bmSelectedStudent = { id: r.student_id, name: r.student_name };
                    document.getElementById('bm-student-result').innerHTML = '<h3>' + r.student_name + '</h3><p style="color:green;">' + r.message + '</p>';
                    bmCheckActionReady();
                }
            } else { alert(r.message); }
        };
        xhr.send(params);
    });
    
    function bmRegisterBookByISBN(isbn) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.success) {
                document.getElementById('bm-book-result').innerHTML = '<div style="padding:10px;"><h3>' + r.book_title + '</h3><p style="color:green;">' + r.message + '</p></div>';
                bmSelectedBook = { id: r.book_id, title: r.book_title, author: r.book_author, available: 1, total: 1, consulta_local: false };
                bmCheckActionReady();
            } else { alert(r.message); }
        };
        xhr.send('action=bm_service_register_book_by_isbn&isbn=' + isbn + '&nonce=' + bmNonce);
    }
    
    function bmEditStudent(id) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', bmAjaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.found) {
                var s = r.student;
                var form = document.querySelector('#bm-quick-register-form');
                form.querySelector('[name="bm_quick_name"]').value = s.name || '';
                form.querySelector('[name="bm_quick_email"]').value = s.email || '';
                form.querySelector('[name="bm_quick_phone"]').value = s.phone || '';
                if (s.dynamic_fields) {
                    for (var key in s.dynamic_fields) {
                        var input = form.querySelector('[name="' + key + '"]');
                        if (input) input.value = s.dynamic_fields[key] || '';
                    }
                }
                form.setAttribute('data-edit-id', id);
                document.getElementById('bm-modal-title').textContent = '✏️ Editar Aluno';
                document.getElementById('bm-modal-submit-btn').textContent = 'Salvar Alterações';
                document.getElementById('bm-quick-register-modal').style.display = 'flex';
            }
        };
        xhr.send('action=bm_service_search_student&student_id=' + id + '&nonce=' + bmNonce);
    }
    </script>
    <?php
}

// ==========================================
// ADMINISTRAÇÃO DE ALUNOS
// ==========================================
function bm_add_students_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Alunos', 'book-manager'), __('Alunos', 'book-manager'), 'edit_bm_books', 'bm_students', 'bm_render_students_unified_page');
}
add_action('admin_menu', 'bm_add_students_page');

function bm_render_students_unified_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';
    ?>
    <div class="wrap">
        <h1><?php _e('Alunos', 'book-manager'); ?></h1>
        <nav class="nav-tab-wrapper" style="margin-bottom:15px;">
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=list'); ?>" class="nav-tab <?php echo $tab === 'list' ? 'nav-tab-active' : ''; ?>">👥 <?php _e('Lista de Alunos', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=approve_users'); ?>" class="nav-tab <?php echo $tab === 'approve_users' ? 'nav-tab-active' : ''; ?>">✅ <?php _e('Aprovar Cadastros', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=approve_readings'); ?>" class="nav-tab <?php echo $tab === 'approve_readings' ? 'nav-tab-active' : ''; ?>">📝 <?php _e('Aprovar Fichas', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=reading_lists'); ?>" class="nav-tab <?php echo $tab === 'reading_lists' ? 'nav-tab-active' : ''; ?>">📚 <?php _e('Listas de Leitura', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=library_cards'); ?>" class="nav-tab <?php echo $tab === 'library_cards' ? 'nav-tab-active' : ''; ?>">🪪 <?php _e('Carteirinhas', 'book-manager'); ?></a>
            <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students&tab=acquisition_suggestions'); ?>" class="nav-tab <?php echo $tab === 'acquisition_suggestions' ? 'nav-tab-active' : ''; ?>">🛒 <?php _e('Sugestões de Aquisição', 'book-manager'); ?></a>
        </nav>
        <?php
        if ($tab === 'reading_lists') {
            bm_render_reading_lists_page_content();
        } elseif ($tab === 'library_cards') {
            bm_render_library_cards_page();
        } elseif ($tab === 'acquisition_suggestions') {
            bm_render_acquisition_suggestions_page();
        } elseif ($tab === 'approve_users') {
            bm_render_approval_page_content();
        } elseif ($tab === 'approve_readings') {
            bm_render_reading_approval_page_content();
        } else {
            bm_render_students_page_content();
        }
        ?>
    </div>
    <?php
}

function bm_render_students_page_content() {
    $msg = '';
    if (isset($_POST['bm_bulk_action']) && wp_verify_nonce($_POST['bm_students_nonce'], 'bm_students_action')) {
        $action = sanitize_text_field($_POST['bm_bulk_action']);
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        if (!empty($user_ids) && in_array($action, array('approve', 'suspend', 'delete'))) {
            $count = 0;
            foreach ($user_ids as $uid) {
                $user = get_userdata($uid);
                if (!$user || user_can($uid, 'manage_options')) continue;
                if ($action === 'approve') {
                    $requested_role = get_user_meta($uid, 'bm_requested_role', true) ?: 'bm_student';
                    wp_update_user(array('ID' => $uid, 'role' => $requested_role));
                    update_user_meta($uid, 'bm_approval_status', 'approved');
                    bm_log_admin_action('Aprovou aluno (lote)', $uid);
                    $count++;
                } elseif ($action === 'suspend') {
                    wp_update_user(array('ID' => $uid, 'role' => 'subscriber'));
                    update_user_meta($uid, 'bm_approval_status', 'suspended');
                    bm_log_admin_action('Suspendeu aluno', $uid);
                    $count++;
                } elseif ($action === 'delete') {
                    if (get_current_user_id() !== $uid) {
                        bm_log_admin_action('Excluiu aluno', $uid);
                        wp_delete_user($uid);
                        $count++;
                    }
                }
            }
            $msg = '<div class="notice notice-success"><p>' . sprintf(__('%d aluno(s) afetado(s).', 'book-manager'), $count) . '</p></div>';
        }
    }
    
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_group = isset($_GET['filter_group']) ? sanitize_text_field($_GET['filter_group']) : '';
    $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
    $filter_overdue = isset($_GET['filter_overdue']) ? true : false;
    
    $args = array('role' => 'bm_student', 'number' => 50);
    if ($filter_search) $args['search'] = '*' . $filter_search . '*';
    if ($filter_status === 'pending') { $args['meta_key'] = 'bm_approval_status'; $args['meta_value'] = 'pending'; }
    elseif ($filter_status === 'suspended') { $args['role'] = 'subscriber'; $args['meta_key'] = 'bm_approval_status'; $args['meta_value'] = 'suspended'; }
    if ($filter_group) { $args['meta_query'][] = array('key' => 'bm_student_group', 'value' => $filter_group, 'compare' => 'LIKE'); }
    
    $students = get_users($args);
    if ($filter_overdue) {
        $filtered = array();
        foreach ($students as $student) {
            $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
            $has_overdue = false;
            foreach ($loan_history as $loan) { if ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < time()) { $has_overdue = true; break; } }
            if ($has_overdue) $filtered[] = $student;
        }
        $students = $filtered;
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Alunos', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        <button type="button" class="button button-primary" id="bm-add-student-btn" style="margin-bottom:10px;">➕ <?php _e('Adicionar Novo Aluno', 'book-manager'); ?></button>

        <!-- Modal de cadastro rápido de aluno (mesmo código do balcão, omitido por brevidade mas presente no admin.php residual) -->
        <div id="bm-quick-register-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
            <div style="background:#fff;padding:25px;border-radius:8px;max-width:450px;width:90%;max-height:80vh;overflow-y:auto;">
                <h2 style="margin-top:0;">➕ <?php _e('Cadastro Rápido de Aluno', 'book-manager'); ?></h2>
                <form id="bm-quick-register-form" onsubmit="return false;">
                    <?php wp_nonce_field('bm_service_nonce', 'bm_quick_register_nonce'); ?>
                    <p><label><strong><?php _e('Nome completo', 'book-manager'); ?> *</strong></label><input type="text" name="bm_quick_name" required style="width:100%;padding:8px;margin-top:4px;" /></p>
                    <p><label><strong><?php _e('E-mail', 'book-manager'); ?> *</strong></label><input type="email" name="bm_quick_email" required style="width:100%;padding:8px;margin-top:4px;" /></p>
                    <p><label><strong><?php _e('Telefone', 'book-manager'); ?></strong></label><input type="text" name="bm_quick_phone" style="width:100%;padding:8px;margin-top:4px;" placeholder="5511999999999" /></p>
                    <?php
                    $user_fields = get_option('bm_user_dynamic_fields', array());
                    $user_field_order = get_option('bm_user_field_order', array());
                    $ordered_fields = array();
                    foreach ($user_field_order as $key) { if (isset($user_fields[$key])) $ordered_fields[$key] = $user_fields[$key]; }
                    foreach ($user_fields as $key => $info) { if (!isset($ordered_fields[$key])) $ordered_fields[$key] = $info; }
                    foreach ($ordered_fields as $field_name => $info):
                        $name_lower = mb_strtolower(trim($field_name));
                        if (in_array($name_lower, array('nome completo', 'e-mail', 'email', 'telefone'))) continue;
                        $meta_key = '_bm_user_' . sanitize_key($field_name);
                    ?>
                    <p><label><strong><?php echo esc_html($field_name); ?></strong></label><input type="text" name="<?php echo esc_attr($meta_key); ?>" style="width:100%;padding:8px;margin-top:4px;" /></p>
                    <?php endforeach; ?>
                    <p style="margin-top:15px;display:flex;gap:10px;">
                        <button type="submit" class="button button-primary" style="flex:1;"><?php _e('Cadastrar', 'book-manager'); ?></button>
                        <button type="button" class="button" onclick="document.getElementById('bm-quick-register-modal').style.display='none'" style="flex:1;"><?php _e('Cancelar', 'book-manager'); ?></button>
                    </p>
                </form>
            </div>
        </div>

        <script>
        document.getElementById('bm-add-student-btn').addEventListener('click', function() { document.getElementById('bm-quick-register-modal').style.display = 'flex'; });
        document.getElementById('bm-quick-register-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var form = this;
            var params = 'nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>';
            params += '&name=' + encodeURIComponent(form.querySelector('[name="bm_quick_name"]').value);
            params += '&email=' + encodeURIComponent(form.querySelector('[name="bm_quick_email"]').value);
            params += '&phone=' + encodeURIComponent(form.querySelector('[name="bm_quick_phone"]').value);
            var dynamicInputs = form.querySelectorAll('input[name^="_bm_user_"]');
            dynamicInputs.forEach(function(input) { params += '&' + input.name + '=' + encodeURIComponent(input.value); });
            params = 'action=bm_service_quick_register&' + params;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) { alert(r.message); location.reload(); }
                else { alert(r.message); }
            };
            xhr.send(params);
        });
        </script>

        <form method="get" style="margin-bottom:15px;">
            <input type="hidden" name="post_type" value="bm_book">
            <input type="hidden" name="page" value="bm_students">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <div><label><?php _e('Buscar', 'book-manager'); ?></label><input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Nome ou e-mail', 'book-manager'); ?>" style="padding:4px 8px;" /></div>
                <div><label><?php _e('Status', 'book-manager'); ?></label><select name="filter_status" style="padding:4px 8px;"><option value=""><?php _e('Todos', 'book-manager'); ?></option><option value="approved" <?php selected($filter_status, 'approved'); ?>><?php _e('Aprovado', 'book-manager'); ?></option><option value="pending" <?php selected($filter_status, 'pending'); ?>><?php _e('Pendente', 'book-manager'); ?></option><option value="suspended" <?php selected($filter_status, 'suspended'); ?>><?php _e('Suspenso', 'book-manager'); ?></option></select></div>
                <div><label><?php _e('Grupo', 'book-manager'); ?></label><input type="text" name="filter_group" value="<?php echo esc_attr($filter_group); ?>" placeholder="<?php _e('Ex: 1º Ano', 'book-manager'); ?>" style="padding:4px 8px;width:80px;" /></div>
                <div><label><input type="checkbox" name="filter_overdue" <?php checked($filter_overdue); ?> /> <?php _e('Apenas em atraso', 'book-manager'); ?></label></div>
                <div><button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button> <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students'); ?>" class="button"><?php _e('Limpar', 'book-manager'); ?></a></div>
            </div>
        </form>
        
        <form method="post">
            <?php wp_nonce_field('bm_students_action', 'bm_students_nonce'); ?>
            <div style="margin-bottom:10px;">
                <select name="bm_bulk_action" style="padding:4px 8px;">
                    <option value=""><?php _e('— Ações em lote —', 'book-manager'); ?></option>
                    <option value="approve"><?php _e('Aprovar', 'book-manager'); ?></option>
                    <option value="suspend"><?php _e('Suspender', 'book-manager'); ?></option>
                    <option value="delete"><?php _e('Excluir', 'book-manager'); ?></option>
                </select>
                <button type="submit" class="button" onclick="return confirm('<?php _e('Confirmar ação em lote?', 'book-manager'); ?>')"><?php _e('Aplicar', 'book-manager'); ?></button>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th style="width:30px;"><input type="checkbox" id="bm-select-all-students" /></th><th><?php _e('Aluno', 'book-manager'); ?></th><th><?php _e('E-mail', 'book-manager'); ?></th><th><?php _e('Status', 'book-manager'); ?></th><th><?php _e('Grupo', 'book-manager'); ?></th><th><?php _e('XP', 'book-manager'); ?></th><th><?php _e('Empréstimos', 'book-manager'); ?></th><th><?php _e('Ações', 'book-manager'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($students)): ?><tr><td colspan="8"><?php _e('Nenhum aluno encontrado.', 'book-manager'); ?></td></tr>
                    <?php else: foreach ($students as $student): 
                        $status = get_user_meta($student->ID, 'bm_approval_status', true) ?: 'approved';
                        $group = get_user_meta($student->ID, 'bm_student_group', true);
                        $xp = bm_get_xp($student->ID);
                        $phone = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Telefone'), true);
                        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
                        $active_loans = 0; $has_overdue = false;
                        foreach ($loan_history as $loan) { if ($loan['status'] === 'active') { $active_loans++; if (isset($loan['due_date']) && strtotime($loan['due_date']) < time()) $has_overdue = true; } }
                        $row_style = $has_overdue ? 'background:#fff3f3;' : '';
                        $penalty_check = bm_check_penalty_block($student->ID);
                        if ($penalty_check) $row_style = 'background:#fff3e0;';
                        $status_labels = array('approved' => '✅', 'pending' => '⏳', 'suspended' => '🚫');
                        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : '✅';
                    ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td><input type="checkbox" name="user_ids[]" value="<?php echo $student->ID; ?>" /></td>
                            <td><strong><?php echo esc_html($student->display_name); ?></strong> <?php if ($has_overdue): ?><span style="color:#dc3545;" title="<?php _e('Em atraso', 'book-manager'); ?>">🔴</span><?php endif; ?> <?php if ($penalty_check): ?><span style="color:#ff9800;" title="<?php _e('Penalidade ativa', 'book-manager'); ?>">🚫</span><?php endif; ?></td>
                            <td><?php echo esc_html($student->user_email); ?></td>
                            <td><?php echo $status_label . ' ' . $status; ?></td>
                            <td><?php echo esc_html($group); ?></td>
                            <td><?php echo $xp; ?></td>
                            <td><?php echo $active_loans; ?> ativo(s)</td>
                            <td><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $student->ID); ?>" class="button button-small"><?php _e('Ver', 'book-manager'); ?></a> <?php if ($phone) echo bm_whatsapp_button($phone, '', 'WhatsApp'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </form>
    </div>
    <?php
}

// ==========================================
// LISTA DE LEITURA OBRIGATÓRIA
// ==========================================
function bm_render_reading_lists_page_content() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $msg = '';
    $reading_lists = get_option('bm_reading_lists', array());
    if (!is_array($reading_lists)) $reading_lists = array();
    
    if (isset($_POST['bm_save_reading_list']) && wp_verify_nonce($_POST['bm_reading_list_nonce'], 'bm_reading_list_action')) {
        $group = sanitize_text_field($_POST['bm_list_group']);
        $book_ids = isset($_POST['bm_list_books']) ? array_map('intval', $_POST['bm_list_books']) : array();
        $description = sanitize_textarea_field($_POST['bm_list_description']);
        if (empty($group)) { $msg = '<div class="notice notice-error"><p>' . __('Informe a turma.', 'book-manager') . '</p></div>'; }
        elseif (empty($book_ids)) { $msg = '<div class="notice notice-error"><p>' . __('Selecione pelo menos um livro.', 'book-manager') . '</p></div>'; }
        else {
            $reading_lists[$group] = array('books' => $book_ids, 'description' => $description, 'created_by' => get_current_user_id(), 'created_at' => current_time('mysql'));
            update_option('bm_reading_lists', $reading_lists);
            $msg = '<div class="notice notice-success"><p>' . sprintf(__('Lista de leitura para %s salva!', 'book-manager'), $group) . '</p></div>';
        }
    }
    if (isset($_POST['bm_delete_list']) && wp_verify_nonce($_POST['bm_reading_list_nonce'], 'bm_reading_list_action')) {
        $delete_group = sanitize_text_field($_POST['bm_delete_group']);
        if (isset($reading_lists[$delete_group])) { unset($reading_lists[$delete_group]); update_option('bm_reading_lists', $reading_lists); $msg = '<div class="notice notice-success"><p>' . sprintf(__('Lista da turma %s removida.', 'book-manager'), $delete_group) . '</p></div>'; }
    }
    
    $all_students = get_users(array('role' => 'bm_student', 'number' => 200));
    $existing_groups = array();
    foreach ($all_students as $student) { $student_group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true); if (!empty($student_group) && !in_array($student_group, $existing_groups)) $existing_groups[] = $student_group; }
    sort($existing_groups);
    ?>
    <div class="wrap">
        <h1><?php _e('Listas de Leitura Obrigatória', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        <h2><?php _e('Nova Lista', 'book-manager'); ?></h2>
        <form method="post" style="max-width:600px;">
            <?php wp_nonce_field('bm_reading_list_action', 'bm_reading_list_nonce'); ?>
            <table class="form-table">
                <tr><th><label><?php _e('Turma', 'book-manager'); ?></label></th><td><select name="bm_list_group" required style="width:200px;"><option value=""><?php _e('— Selecione —', 'book-manager'); ?></option><?php foreach ($existing_groups as $group): ?><option value="<?php echo esc_attr($group); ?>"><?php echo esc_html($group); ?></option><?php endforeach; ?></select></td></tr>
                <tr><th><label><?php _e('Descrição', 'book-manager'); ?></label></th><td><input type="text" name="bm_list_description" style="width:100%;" placeholder="<?php _e('Ex: Leituras obrigatórias do 2º bimestre', 'book-manager'); ?>" /></td></tr>
                <tr><th><label><?php _e('Livros', 'book-manager'); ?></label></th><td><div id="bm-list-selected" style="margin-bottom:10px;"></div><input type="text" id="bm-list-book-search" placeholder="<?php _e('Digite o título do livro e pressione Enter...', 'book-manager'); ?>" style="width:100%;" /><div id="bm-list-search-results" style="margin-top:5px;"></div></td></tr>
            </table>
            <p><input type="submit" name="bm_save_reading_list" class="button button-primary" value="<?php _e('Salvar Lista', 'book-manager'); ?>" /></p>
        </form>
        <h2><?php _e('Listas Existentes', 'book-manager'); ?></h2>
        <?php if (empty($reading_lists)): ?><p><?php _e('Nenhuma lista criada.', 'book-manager'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php _e('Turma', 'book-manager'); ?></th><th><?php _e('Descrição', 'book-manager'); ?></th><th><?php _e('Livros', 'book-manager'); ?></th><th><?php _e('Criado em', 'book-manager'); ?></th><th><?php _e('Ações', 'book-manager'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($reading_lists as $group => $list): ?>
                        <tr><td><strong><?php echo esc_html($group); ?></strong></td><td><?php echo esc_html($list['description']); ?></td><td><?php echo count($list['books']); ?> livro(s)</td><td><?php echo date('d/m/Y', strtotime($list['created_at'])); ?></td><td><form method="post" style="display:inline;" onsubmit="return confirm('<?php _e('Remover esta lista?', 'book-manager'); ?>');"><?php wp_nonce_field('bm_reading_list_action', 'bm_reading_list_nonce'); ?><input type="hidden" name="bm_delete_group" value="<?php echo esc_attr($group); ?>"><button type="submit" name="bm_delete_list" class="button button-small"><?php _e('Remover', 'book-manager'); ?></button></form></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    var bmListBooks = [];
    document.getElementById('bm-list-book-search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var query = this.value.trim();
            if (query.length < 2) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                var html = '';
                if (r.found) { html += '<div style="padding:6px;background:#e8f5e9;border-radius:4px;cursor:pointer;margin:3px 0;" onclick="bmAddBookToList(' + r.book.id + ', \'' + r.book.title.replace(/'/g, "\\'") + '\')">' + r.book.title + ' — ' + (r.book.author || '') + '</div>'; }
                else if (r.can_register) { html += '<p style="color:#999;">Livro não encontrado.</p>'; }
                else { html += '<p style="color:#999;">Nenhum resultado.</p>'; }
                document.getElementById('bm-list-search-results').innerHTML = html;
            };
            xhr.send('action=bm_service_search_book&query=' + encodeURIComponent(query) + '&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>');
        }
    });
    function bmAddBookToList(id, title) {
        if (bmListBooks.indexOf(id) >= 0) return;
        bmListBooks.push(id);
        var div = document.getElementById('bm-list-selected');
        var bookDiv = document.createElement('div');
        bookDiv.setAttribute('data-id', id);
        bookDiv.style.cssText = 'display:inline-block;background:#e3f2fd;padding:4px 10px;border-radius:12px;margin:3px;font-size:12px;';
        bookDiv.innerHTML = title + ' <span onclick="bmRemoveBookFromList(' + id + ', this)" style="cursor:pointer;color:#dc3545;">✕</span>';
        div.appendChild(bookDiv);
        var input = document.createElement('input'); input.type = 'hidden'; input.name = 'bm_list_books[]'; input.value = id; input.id = 'book-input-' + id; div.appendChild(input);
    }
    function bmRemoveBookFromList(id, el) { bmListBooks = bmListBooks.filter(function(item) { return item !== id; }); el.parentElement.remove(); var input = document.getElementById('book-input-' + id); if (input) input.remove(); }
    </script>
    <?php
}

// ==========================================
// DETALHES DO ALUNO
// ==========================================
function bm_add_student_detail_page() {
    add_submenu_page(null, __('Detalhes do Aluno', 'book-manager'), __('Detalhes do Aluno', 'book-manager'), 'edit_bm_books', 'bm_student_detail', 'bm_render_student_detail_page');
}
add_action('admin_menu', 'bm_add_student_detail_page');

function bm_render_student_detail_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    $student = get_userdata($student_id);
    if (!$student) { echo '<div class="wrap"><p>' . __('Aluno não encontrado.', 'book-manager') . '</p></div>'; return; }
    $msg = '';
    
    if (isset($_POST['bm_return_from_detail']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        $return_book_id = intval($_POST['bm_return_book_id']); $return_user_id = intval($_POST['bm_return_user_id']);
        $result = bm_return_book($return_book_id, $return_user_id);
        $msg = isset($result['error']) ? '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>' : '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
    }
    if (isset($_POST['bm_apply_manual_penalty']) && wp_verify_nonce($_POST['bm_student_detail_nonce'], 'bm_student_detail_action')) {
        $type = sanitize_text_field($_POST['bm_manual_penalty_type']); $value = floatval($_POST['bm_manual_penalty_value']); $note = sanitize_text_field($_POST['bm_manual_penalty_note']);
        bm_apply_penalty($student_id, array('type' => $type, 'value' => $value, 'note' => $note));
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
            if (isset($_POST['bm_edit_' . $meta_key])) update_user_meta($student_id, $meta_key, sanitize_text_field($_POST['bm_edit_' . $meta_key]));
        }
        $nome_key = '_bm_user_' . sanitize_key('Nome completo'); $email_key = '_bm_user_' . sanitize_key('E-mail');
        $novo_nome = get_user_meta($student_id, $nome_key, true); $novo_email = get_user_meta($student_id, $email_key, true);
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
        if ($loan['status'] === 'active') { $active_loans++; if ($loan['is_overdue']) $overdue_count++; }
        $loan_details[] = $loan;
    }
    usort($loan_details, function($a, $b) { $date_a = isset($a['loan_date']) ? strtotime($a['loan_date']) : (isset($a['date']) ? strtotime($a['date']) : 0); $date_b = isset($b['loan_date']) ? strtotime($b['loan_date']) : (isset($b['date']) ? strtotime($b['date']) : 0); return $date_b - $date_a; });
    ?>
    <div class="wrap" style="max-width:900px;">
        <h1><?php _e('Detalhes do Aluno', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        <p><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students'); ?>">← <?php _e('Voltar para lista', 'book-manager'); ?></a></p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin:15px 0;">
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;"><h3 style="margin:0;font-size:28px;"><?php echo $xp; ?></h3><p style="margin:5px 0 0 0;color:#666;">XP</p></div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;"><h3 style="margin:0;font-size:28px;"><?php echo count($badges); ?></h3><p style="margin:5px 0 0 0;color:#666;">Medalhas</p></div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;"><h3 style="margin:0;font-size:28px;"><?php echo $active_loans; ?></h3><p style="margin:5px 0 0 0;color:#666;">Empréstimos ativos</p></div>
            <div style="background:<?php echo $overdue_count > 0 ? '#fff3f3' : '#f9f9f9'; ?>;padding:15px;border-radius:6px;text-align:center;"><h3 style="margin:0;font-size:28px;color:<?php echo $overdue_count > 0 ? '#dc3545' : '#111'; ?>;"><?php echo $overdue_count; ?></h3><p style="margin:5px 0 0 0;color:#666;">Em atraso</p></div>
            <div style="background:#f9f9f9;padding:15px;border-radius:6px;text-align:center;"><h3 style="margin:0;font-size:28px;"><?php echo count($reading_log); ?></h3><p style="margin:5px 0 0 0;color:#666;">Fichas de leitura</p></div>
        </div>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:300px;">
                <h2>👤 <?php echo esc_html($student->display_name); ?></h2>
                <?php $profile_photo = get_user_meta($student_id, '_bm_profile_photo', true); if ($profile_photo): ?><div style="text-align:center;margin-bottom:10px;"><img src="<?php echo esc_url($profile_photo); ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:2px solid #ddd;" alt="" /></div><?php endif; ?>
                <p><strong>E-mail:</strong> <?php echo esc_html($student->user_email); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html($status); ?></p>
                <form method="post" id="bm-edit-student-form">
                    <?php wp_nonce_field('bm_student_detail_action', 'bm_student_detail_nonce'); ?>
                    <?php foreach ($user_fields as $field_name => $info): $meta_key = '_bm_user_' . sanitize_key($field_name); $value = get_user_meta($student_id, $meta_key, true); $name_lower = mb_strtolower(trim($field_name)); if (empty($value) && in_array($name_lower, array('nome completo', 'nome'))) $value = $student->display_name; if (empty($value) && in_array($name_lower, array('e-mail', 'email'))) $value = $student->user_email; ?>
                    <p><strong><?php echo esc_html($field_name); ?>:</strong> <?php if ($info['type'] === 'textarea'): ?><textarea name="bm_edit_<?php echo esc_attr($meta_key); ?>" style="width:100%;max-width:300px;padding:4px 8px;margin-top:2px;" rows="3"><?php echo esc_textarea($value); ?></textarea><?php else: ?><input type="<?php echo $info['type'] === 'email' ? 'email' : 'text'; ?>" name="bm_edit_<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>" style="width:100%;max-width:300px;padding:4px 8px;margin-top:2px;" /><?php endif; ?></p>
                    <?php endforeach; ?>
                    <p style="margin-top:10px;"><button type="submit" name="bm_save_student_data" class="button button-primary"><?php _e('Salvar Alterações', 'book-manager'); ?></button></p>
                </form>
                <?php if ($phone) echo bm_whatsapp_button($phone, '', '📱 WhatsApp'); ?>
            </div>
            <div style="flex:1;min-width:300px;">
                <h2>🚫 <?php _e('Aplicar Penalidade Manual', 'book-manager'); ?></h2>
                <form method="post" style="background:#fff8e1;padding:15px;border-radius:8px;border:1px solid #ffc107;margin-bottom:15px;">
                    <?php wp_nonce_field('bm_student_detail_action', 'bm_student_detail_nonce'); ?>
                    <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                        <div><label><strong><?php _e('Tipo:', 'book-manager'); ?></strong></label><select name="bm_manual_penalty_type"><option value="warning"><?php _e('Advertência', 'book-manager'); ?></option><option value="suspension"><?php _e('Suspensão (dias)', 'book-manager'); ?></option><option value="fine"><?php _e('Multa (R$)', 'book-manager'); ?></option></select></div>
                        <div><label><strong><?php _e('Valor:', 'book-manager'); ?></strong></label><input type="number" name="bm_manual_penalty_value" min="0" step="0.01" style="width:100px;" placeholder="0" /></div>
                        <div><label><strong><?php _e('Descrição:', 'book-manager'); ?></strong></label><input type="text" name="bm_manual_penalty_note" style="width:250px;" placeholder="<?php _e('Ex: Livro danificado na página 32', 'book-manager'); ?>" /></div>
                        <div><button type="submit" name="bm_apply_manual_penalty" class="button" style="background:#ff9800;color:#fff;border-color:#ff9800;"><?php _e('Aplicar Penalidade', 'book-manager'); ?></button></div>
                    </div>
                </form>
                <h2>📝 <?php _e('Observações Internas', 'book-manager'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('bm_student_detail_action', 'bm_student_detail_nonce'); ?>
                    <textarea name="bm_internal_notes" rows="5" style="width:100%;"><?php echo esc_textarea($notes); ?></textarea>
                    <p style="margin-top:5px;"><button type="submit" name="bm_save_notes" class="button"><?php _e('Salvar Observações', 'book-manager'); ?></button> <button type="submit" name="bm_export_student" class="button" style="float:right;">📥 <?php _e('Exportar Histórico (CSV)', 'book-manager'); ?></button></p>
                </form>
            </div>
        </div>
        <?php if (!empty($loan_details)): ?>
            <h2>📋 <?php _e('Histórico de Empréstimos', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php _e('Livro', 'book-manager'); ?></th><th><?php _e('Empréstimo', 'book-manager'); ?></th><th><?php _e('Devolução', 'book-manager'); ?></th><th><?php _e('Status', 'book-manager'); ?></th><th><?php _e('Ação', 'book-manager'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($loan_details as $loan): $status_label = ''; $status_color = '#666'; $row_style = '';
                        if ($loan['status'] === 'active') { if ($loan['is_overdue']) { $status_label = '🔴 ' . __('Atrasado', 'book-manager'); $status_color = '#dc3545'; $row_style = 'background:#fff3f3;'; } else { $status_label = '🔵 ' . __('Emprestado', 'book-manager'); $status_color = '#0073aa'; } }
                        elseif ($loan['status'] === 'returned') { $status_label = '✅ ' . __('Devolvido', 'book-manager'); $status_color = '#46b450'; }
                        elseif ($loan['status'] === 'cancelled') { $status_label = '❌ ' . __('Cancelado', 'book-manager'); $status_color = '#6c757d'; }
                        elseif ($loan['status'] === 'rejected') { $status_label = '⛔ ' . __('Rejeitado', 'book-manager'); $status_color = '#dc3545'; }
                    ?>
                        <tr style="<?php echo $row_style; ?>">
                            <td><?php echo esc_html($loan['book_title']); ?></td>
                            <td><?php echo isset($loan['loan_date']) ? date('d/m/Y', strtotime($loan['loan_date'])) : '—'; ?></td>
                            <td><?php echo isset($loan['due_date']) ? date('d/m/Y', strtotime($loan['due_date'])) : '—'; ?></td>
                            <td style="color:<?php echo $status_color; ?>;font-weight:bold;"><?php echo $status_label; ?></td>
                            <td><?php if ($loan['status'] === 'active'): ?><button type="button" class="button button-small bm-return-detail-btn" style="background:#46b450;color:#fff;border-color:#46b450;" data-book="<?php echo $loan['book_id']; ?>" data-user="<?php echo $student_id; ?>">📥 <?php _e('Devolver', 'book-manager'); ?></button><?php else: ?><span style="color:#999;">—</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <script>
        document.addEventListener('click', function(e) { if (!e.target.classList.contains('bm-return-detail-btn')) return; e.preventDefault(); var self = e.target; if (!confirm('Confirmar devolução?')) return; self.disabled = true; self.textContent = '...'; var xhr = new XMLHttpRequest(); xhr.open('POST', ajaxurl); xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); xhr.onload = function() { var r = JSON.parse(xhr.responseText); if (r.success) { var row = self.closest('tr'); if (row) { row.style.opacity = '0.5'; var situacaoCell = row.querySelector('td:nth-child(4)'); if (situacaoCell) situacaoCell.innerHTML = '<span style="color:#46b450;font-weight:bold;">✅ Devolvido</span>'; } self.remove(); } else { alert(r.message || 'Erro'); self.disabled = false; self.textContent = '📥 Devolver'; } }; xhr.send('action=bm_service_return&book_id=' + self.getAttribute('data-book') + '&user_id=' + self.getAttribute('data-user') + '&nonce=<?php echo wp_create_nonce("bm_service_nonce"); ?>'); });
        </script>
        <?php if (!empty($badges)): ?>
            <h2>🏅 <?php _e('Medalhas', 'book-manager'); ?></h2>
            <div style="display:flex;flex-wrap:wrap;gap:10px;"><?php foreach ($badges as $badge_key): $info = bm_get_badge_info($badge_key); ?><div style="background:#fff8e1;padding:10px 15px;border-radius:8px;text-align:center;border:1px solid #ffc107;" title="<?php echo esc_attr($info['desc']); ?>"><div style="font-size:28px;"><?php echo $info['icon']; ?></div><div style="font-size:11px;font-weight:bold;"><?php echo esc_html($info['name']); ?></div></div><?php endforeach; ?></div>
        <?php endif; ?>
        <?php $recent_logs = array_slice(array_reverse($reading_log), 0, 5); if (!empty($recent_logs)): ?>
            <h2>📝 <?php _e('Últimas Fichas de Leitura', 'book-manager'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php _e('Livro', 'book-manager'); ?></th><th><?php _e('Data', 'book-manager'); ?></th><th><?php _e('Nota', 'book-manager'); ?></th><th><?php _e('XP', 'book-manager'); ?></th><th><?php _e('Status', 'book-manager'); ?></th></tr></thead>
                <tbody><?php foreach ($recent_logs as $log): ?><tr><td><?php echo esc_html(get_the_title($log['book_id'])); ?></td><td><?php echo date('d/m/Y', strtotime($log['date'])); ?></td><td><?php echo $log['rating'] > 0 ? str_repeat('★', $log['rating']) : '—'; ?></td><td><?php echo isset($log['xp_total']) ? esc_html($log['xp_total']) . ' XP' : (isset($log['xp_awarded']) && $log['xp_awarded'] ? __('Sim', 'book-manager') : '—'); ?></td><td><?php echo $log['status'] === 'approved' ? '✅' : '⏳'; ?></td></tr><?php endforeach; ?></tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// DETALHES DO EMPRÉSTIMO
// ==========================================
function bm_add_loan_detail_page() {
    add_submenu_page(null, __('Detalhes do Empréstimo', 'book-manager'), __('Detalhes do Empréstimo', 'book-manager'), 'edit_bm_books', 'bm_loan_detail', 'bm_render_loan_detail_page');
}
add_action('admin_menu', 'bm_add_loan_detail_page');

function bm_render_loan_detail_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $loan_id = isset($_GET['loan_id']) ? sanitize_text_field($_GET['loan_id']) : '';
    $msg = '';
    
    if (isset($_POST['bm_loan_action']) && wp_verify_nonce($_POST['bm_loan_nonce'], 'bm_loan_action')) {
        $action = sanitize_text_field($_POST['bm_loan_action']);
        $settings = bm_get_settings();
        if ($action === 'confirm') { $days = isset($_POST['loan_days']) ? intval($_POST['loan_days']) : $settings['default_loan_days']; $result = bm_confirm_loan($book_id, $user_id, $days); }
        elseif ($action === 'return') { $result = bm_return_book($book_id, $user_id); }
        elseif ($action === 'undo') { $result = bm_undo_loan($book_id, $user_id); }
        elseif ($action === 'reject') { $result = bm_reject_reservation($book_id, $user_id); }
        elseif ($action === 'renew') {
            $reservations = get_post_meta($book_id, '_bm_reservations', true) ?: array(); $found = false;
            foreach ($reservations as &$r) { if ($r['user_id'] == $user_id && $r['status'] === 'active') { $r['due_date'] = date('Y-m-d H:i:s', strtotime('+7 days', strtotime($r['due_date']))); $found = true; break; } }
            if ($found) { update_post_meta($book_id, '_bm_reservations', $reservations); $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array(); foreach ($loan_history as &$loan) { if ($loan['book_id'] == $book_id && $loan['status'] === 'active') { $loan['due_date'] = date('Y-m-d H:i:s', strtotime('+7 days', strtotime($loan['due_date']))); break; } } update_user_meta($user_id, '_bm_loan_history', $loan_history); $result = array('success' => true, 'message' => __('Renovado por mais 7 dias!', 'book-manager')); }
            else { $result = array('error' => __('Empréstimo não encontrado.', 'book-manager')); }
        }
        $msg = isset($result['error']) ? '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>' : '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
    }
    
    if (!$book_id || !$user_id) { echo '<div class="wrap"><p>' . __('Empréstimo não encontrado.', 'book-manager') . '</p></div>'; return; }
    $book = get_post($book_id); $student = get_userdata($user_id);
    if (!$book || !$student) { echo '<div class="wrap"><p>' . __('Empréstimo não encontrado.', 'book-manager') . '</p></div>'; return; }
    
    $reservations = get_post_meta($book_id, '_bm_reservations', true) ?: array();
    $loan_data = null;
    if (!empty($loan_id)) { foreach ($reservations as $r) { if (isset($r['loan_id']) && $r['loan_id'] === $loan_id) { $loan_data = $r; break; } } }
    if (!$loan_data) { foreach ($reservations as $r) { if ($r['user_id'] == $user_id) { $loan_data = $r; break; } } }
    if (!$loan_data) { $loan_data = array('status' => 'unknown', 'user_id' => $user_id, 'date' => '', 'loan_date' => '', 'due_date' => '', 'returned_date' => '', 'loan_id' => ''); }
    ?>
    <div class="wrap" style="max-width:800px;">
        <h1><?php _e('Detalhes do Empréstimo', 'book-manager'); ?></h1>
        <p><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_service_desk'); ?>">← <?php _e('Voltar para Empréstimos', 'book-manager'); ?></a></p>
        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:15px;">
            <div style="flex:0 0 150px;"><a href="<?php echo admin_url('post.php?post=' . $book_id . '&action=edit'); ?>"><?php if (has_post_thumbnail($book_id)) { echo get_the_post_thumbnail($book_id, 'medium', array('style' => 'width:100%;height:auto;border-radius:4px;')); } else { echo '<div style="width:100%;height:200px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;border-radius:4px;">' . __('Sem capa', 'book-manager') . '</div>'; } ?></a></div>
            <div style="flex:1;min-width:300px;">
                <h2 style="margin:0;"><a href="<?php echo admin_url('post.php?post=' . $book_id . '&action=edit'); ?>"><?php echo esc_html($book->post_title); ?></a></h2>
                <?php $author = get_post_meta($book_id, '_bm_author', true); if ($author): ?><p><strong><?php _e('Autor:', 'book-manager'); ?></strong> <?php echo esc_html($author); ?></p><?php endif; ?>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ($loan_data['status'] === 'waiting'): ?>
                        <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="number" name="loan_days" value="14" min="0" max="60" style="width:50px;padding:4px 6px;font-size:13px;text-align:center;" /><input type="hidden" name="bm_loan_action" value="confirm"><button type="submit" class="button button-small" style="background:#0073aa;color:#fff;border-color:#0073aa;">✅ <?php _e('Emprestar', 'book-manager'); ?></button></form>
                        <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="reject"><button type="submit" class="button button-small" style="background:#dc3545;color:#fff;border-color:#dc3545;">❌ <?php _e('Rejeitar', 'book-manager'); ?></button></form>
                    <?php elseif ($loan_data['status'] === 'active'): ?>
                        <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="return"><button type="submit" class="button button-small" style="background:#46b450;color:#fff;border-color:#46b450;">📥 <?php _e('Devolver', 'book-manager'); ?></button></form>
                        <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="renew"><button type="submit" class="button button-small" style="background:#ffc107;color:#111;border-color:#ffc107;">🔄 <?php _e('Renovar +7', 'book-manager'); ?></button></form>
                        <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="undo"><button type="submit" class="button button-small" style="background:#dc3545;color:#fff;border-color:#dc3545;">↩️ <?php _e('Desfazer', 'book-manager'); ?></button></form>
                    <?php elseif (in_array($loan_data['status'], array('returned', 'cancelled', 'rejected'))): ?>
                        <button type="button" class="button button-small" id="bm-archive-btn-top" data-book="<?php echo $book_id; ?>" data-user="<?php echo $user_id; ?>" data-loan="<?php echo esc_attr($loan_id); ?>">🗄️ <?php _e('Arquivar', 'book-manager'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;"><h3 style="margin:0 0 10px 0;">👤 <?php _e('Aluno', 'book-manager'); ?></h3><p><strong><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $user_id); ?>"><?php echo esc_html($student->display_name); ?></a></strong></p><?php $student_group = get_user_meta($user_id, '_bm_user_' . sanitize_key('Turma'), true); if ($student_group): ?><p><strong><?php _e('Turma:', 'book-manager'); ?></strong> <?php echo esc_html($student_group); ?></p><?php endif; ?><?php $student_phone = get_user_meta($user_id, '_bm_user_' . sanitize_key('Telefone'), true); if ($student_phone) echo '<p>' . bm_whatsapp_button($student_phone, '', '📱 WhatsApp') . '</p>'; ?></div>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;"><h3 style="margin:0 0 10px 0;">📅 <?php _e('Linha do Tempo', 'book-manager'); ?></h3><table class="widefat fixed" style="border:none;"><tr><td style="width:200px;padding:5px;border:none;"><?php _e('Data da reserva:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['date']) ? date('d/m/Y H:i', strtotime($loan_data['date'])) : '—'; ?></strong></td></tr><tr><td style="padding:5px;border:none;"><?php _e('Data do empréstimo:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['loan_date']) ? date('d/m/Y H:i', strtotime($loan_data['loan_date'])) : '—'; ?></strong></td></tr><tr><td style="padding:5px;border:none;"><?php _e('Devolução prevista:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['due_date']) ? date('d/m/Y', strtotime($loan_data['due_date'])) : '—'; ?></strong></td></tr><tr><td style="padding:5px;border:none;"><?php _e('Devolução real:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['returned_date']) ? date('d/m/Y H:i', strtotime($loan_data['returned_date'])) : '—'; ?></strong></td></tr></table></div>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;"><h3 style="margin:0 0 10px 0;">⚠️ <?php _e('Atraso e Multa', 'book-manager'); ?></h3><?php $days_late = 0; if (isset($loan_data['due_date']) && isset($loan_data['returned_date'])) { $due_time = strtotime($loan_data['due_date']); $return_time = strtotime($loan_data['returned_date']); if ($return_time > $due_time) $days_late = ceil(($return_time - $due_time) / DAY_IN_SECONDS); } if ($days_late > 0) echo '<p><strong>' . __('Dias de atraso:', 'book-manager') . '</strong> <span style="color:#dc3545;">' . $days_late . '</span></p>'; else echo '<p>' . __('Sem atraso.', 'book-manager') . '</p>'; ?></div>
        
        <div style="margin-top:20px;display:flex;gap:10px;border-top:1px solid #ddd;padding-top:15px;">
            <?php if ($loan_data['status'] === 'waiting'): ?>
                <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="number" name="loan_days" value="14" min="0" max="60" style="width:60px;padding:4px 8px;font-size:14px;text-align:center;" /><input type="hidden" name="bm_loan_action" value="confirm"><button type="submit" class="button" style="background:#0073aa;color:#fff;border-color:#0073aa;">✅ <?php _e('Confirmar Empréstimo', 'book-manager'); ?></button></form>
                <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="reject"><button type="submit" class="button" style="background:#dc3545;color:#fff;border-color:#dc3545;">❌ <?php _e('Rejeitar', 'book-manager'); ?></button></form>
            <?php elseif ($loan_data['status'] === 'active'): ?>
                <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="return"><button type="submit" class="button" style="background:#46b450;color:#fff;border-color:#46b450;">📥 <?php _e('Devolver', 'book-manager'); ?></button></form>
                <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="renew"><button type="submit" class="button" style="background:#ffc107;color:#111;border-color:#ffc107;">🔄 <?php _e('Renovar +7 dias', 'book-manager'); ?></button></form>
                <form method="post" style="display:inline;"><?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?><input type="hidden" name="book_id" value="<?php echo $book_id; ?>"><input type="hidden" name="user_id" value="<?php echo $user_id; ?>"><input type="hidden" name="bm_loan_action" value="undo"><button type="submit" class="button" style="background:#dc3545;color:#fff;border-color:#dc3545;">↩️ <?php _e('Desfazer', 'book-manager'); ?></button></form>
            <?php elseif (in_array($loan_data['status'], array('returned', 'cancelled', 'rejected'))): ?>
                <button type="button" class="button" id="bm-archive-btn-top" data-book="<?php echo $book_id; ?>" data-user="<?php echo $user_id; ?>" data-loan="<?php echo esc_attr($loan_id); ?>">🗄️ <?php _e('Arquivar', 'book-manager'); ?></button>
            <?php endif; ?>
        </div>
        <script>
        var bmArchiveNonce = '<?php echo wp_create_nonce("bm_service_nonce"); ?>';
        document.getElementById('bm-archive-btn-top')?.addEventListener('click', function() { if (!confirm('<?php _e('Arquivar este registro?', 'book-manager'); ?>')) return; var btn = this; btn.disabled = true; btn.textContent = '...'; var xhr = new XMLHttpRequest(); xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>'); xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); xhr.onload = function() { var r = JSON.parse(xhr.responseText); if (r.success) { btn.textContent = '✅ <?php _e('Arquivado', 'book-manager'); ?>'; btn.style.background = '#6c757d'; } else { alert(r.message || 'Erro'); btn.disabled = false; btn.textContent = '🗄️ <?php _e('Arquivar', 'book-manager'); ?>'; } }; xhr.send('action=bm_archive_loan&book_id=' + btn.getAttribute('data-book') + '&loan_id=' + btn.getAttribute('data-loan') + '&nonce=' + bmArchiveNonce); });
        </script>
    </div>
    <?php
}

// ==========================================
// RELATÓRIOS
// ==========================================
function bm_add_reports_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Relatórios', 'book-manager'), __('Relatórios', 'book-manager'), 'manage_options', 'bm_reports', 'bm_render_reports_page');
}
add_action('admin_menu', 'bm_add_reports_page');

function bm_render_reports_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $type = isset($_GET['bm_report_type']) ? sanitize_text_field($_GET['bm_report_type']) : 'overview';
    $period = isset($_GET['bm_period']) ? sanitize_text_field($_GET['bm_period']) : 'month';
    $date_start = isset($_GET['bm_date_start']) ? sanitize_text_field($_GET['bm_date_start']) : '';
    $date_end = isset($_GET['bm_date_end']) ? sanitize_text_field($_GET['bm_date_end']) : '';
    $subject = isset($_GET['bm_subject']) ? sanitize_text_field($_GET['bm_subject']) : 'all';
    $subject_id = (isset($_GET['bm_subject_id']) && $subject !== 'all') ? intval($_GET['bm_subject_id']) : 0;
    $group = isset($_GET['bm_group']) ? sanitize_text_field($_GET['bm_group']) : '';
    ?>
    <div class="wrap">
        <h1><?php _e('Relatórios', 'book-manager'); ?></h1>
        <form method="get" style="background:#fff;padding:15px;border:1px solid #ddd;border-radius:6px;margin-bottom:20px;">
            <input type="hidden" name="post_type" value="bm_book"><input type="hidden" name="page" value="bm_reports">
            <div style="display:flex;gap:15px;flex-wrap:wrap;align-items:end;">
                <div><label><strong><?php _e('Tipo de Relatório', 'book-manager'); ?></strong></label><select name="bm_report_type" style="width:200px;"><option value="overview" <?php selected($type, 'overview'); ?>><?php _e('Visão Geral', 'book-manager'); ?></option><option value="student_performance" <?php selected($type, 'student_performance'); ?>><?php _e('Desempenho do Aluno', 'book-manager'); ?></option><option value="class_reading" <?php selected($type, 'class_reading'); ?>><?php _e('Leitura por Turma', 'book-manager'); ?></option><option value="active_penalties" <?php selected($type, 'active_penalties'); ?>><?php _e('Multas Ativas', 'book-manager'); ?></option><option value="genre_ranking" <?php selected($type, 'genre_ranking'); ?>><?php _e('Ranking por Gênero', 'book-manager'); ?></option><option value="top_books" <?php selected($type, 'top_books'); ?>><?php _e('Livros Mais Emprestados', 'book-manager'); ?></option><option value="reading_trend" <?php selected($type, 'reading_trend'); ?>><?php _e('Tendência de Leitura', 'book-manager'); ?></option><option value="custom" <?php selected($type, 'custom'); ?>><?php _e('Relatório Configurável', 'book-manager'); ?></option></select></div>
                <div><label><strong><?php _e('Período', 'book-manager'); ?></strong></label><select name="bm_period" style="width:150px;"><option value="week" <?php selected($period, 'week'); ?>><?php _e('Última Semana', 'book-manager'); ?></option><option value="month" <?php selected($period, 'month'); ?>><?php _e('Último Mês', 'book-manager'); ?></option><option value="bimester" <?php selected($period, 'bimester'); ?>><?php _e('Último Bimestre', 'book-manager'); ?></option><option value="semester" <?php selected($period, 'semester'); ?>><?php _e('Último Semestre', 'book-manager'); ?></option><option value="year" <?php selected($period, 'year'); ?>><?php _e('Último Ano', 'book-manager'); ?></option><option value="custom" <?php selected($period, 'custom'); ?>><?php _e('Personalizado', 'book-manager'); ?></option></select></div>
                <div id="bm-custom-dates" style="display:<?php echo $period === 'custom' ? 'flex' : 'none'; ?>;gap:10px;"><div><label><strong><?php _e('De', 'book-manager'); ?></strong></label><input type="date" name="bm_date_start" value="<?php echo esc_attr($date_start); ?>" style="width:140px;" /></div><div><label><strong><?php _e('Até', 'book-manager'); ?></strong></label><input type="date" name="bm_date_end" value="<?php echo esc_attr($date_end); ?>" style="width:140px;" /></div></div>
                <div><button type="submit" class="button button-primary"><?php _e('Gerar Relatório', 'book-manager'); ?></button></div>
            </div>
        </form>
        <div id="bm-report-result">
            <?php if (isset($_GET['bm_report_type'])): $args = array('type' => $type, 'period' => $period, 'date_start' => $date_start, 'date_end' => $date_end, 'subject' => $subject, 'subject_id' => $subject === 'student' ? $subject_id : 0, 'group' => $subject === 'class' ? $group : ''); $report = bm_generate_report($args); echo bm_render_report_html($report); endif; ?>
        </div>
        <div style="margin-top:15px;display:flex;gap:10px;"><button type="button" class="button" id="bm-export-pdf">📄 <?php _e('Exportar PDF', 'book-manager'); ?></button></div>
    </div>
    <script>
    function bmExportPDF() { var url = '<?php echo admin_url("admin-ajax.php"); ?>?action=bm_export_report_pdf'; var params = new URLSearchParams(window.location.search); url += '&type=' + (params.get('bm_report_type') || 'overview'); url += '&period=' + (params.get('bm_period') || 'month'); url += '&date_start=' + (params.get('bm_date_start') || ''); url += '&date_end=' + (params.get('bm_date_end') || ''); url += '&subject_id=' + (params.get('bm_subject_id') || '0'); url += '&group=' + (params.get('bm_group') || ''); window.open(url, '_blank'); }
    document.querySelector('select[name="bm_period"]').addEventListener('change', function() { document.getElementById('bm-custom-dates').style.display = this.value === 'custom' ? 'flex' : 'none'; });
    document.getElementById('bm-export-pdf').addEventListener('click', bmExportPDF);
    </script>
    <?php
}

// ==========================================
// TAXONOMIAS DINÂMICAS
// ==========================================
function bm_register_dynamic_taxonomies() {
    $taxonomies = get_option('bm_dynamic_taxonomies', array()); if (!is_array($taxonomies)) $taxonomies = array();
    bm_install_default_taxonomies(); $taxonomies = get_option('bm_dynamic_taxonomies', array()); if (!is_array($taxonomies)) $taxonomies = array();
    $skip = array('bm_discipline');
    $default_labels = array('bm_genre' => array('name' => __('Gêneros', 'book-manager'), 'singular_name' => __('Gênero', 'book-manager'), 'search_items' => __('Buscar Gêneros', 'book-manager'), 'all_items' => __('Todos os Gêneros', 'book-manager'), 'parent_item' => __('Gênero Pai', 'book-manager'), 'edit_item' => __('Editar Gênero', 'book-manager'), 'update_item' => __('Atualizar Gênero', 'book-manager'), 'add_new_item' => __('Adicionar Novo Gênero', 'book-manager'), 'new_item_name' => __('Nome do Novo Gênero', 'book-manager'), 'menu_name' => __('Gêneros', 'book-manager')), 'bm_category' => array('name' => __('Categorias', 'book-manager'), 'singular_name' => __('Categoria', 'book-manager'), 'search_items' => __('Buscar Categorias', 'book-manager'), 'all_items' => __('Todas as Categorias', 'book-manager'), 'parent_item' => __('Categoria Pai', 'book-manager'), 'edit_item' => __('Editar Categoria', 'book-manager'), 'update_item' => __('Atualizar Categoria', 'book-manager'), 'add_new_item' => __('Adicionar Nova Categoria', 'book-manager'), 'new_item_name' => __('Nome da Nova Categoria', 'book-manager'), 'menu_name' => __('Categorias', 'book-manager')));
    foreach ($taxonomies as $slug => $info) {
        if (in_array($slug, $skip)) continue;
        register_taxonomy($slug, 'bm_book', array('label' => $info['label'], 'labels' => isset($default_labels[$slug]) ? $default_labels[$slug] : array(), 'rewrite' => false, 'hierarchical' => !empty($info['hierarchical']), 'show_ui' => true, 'show_in_menu' => false, 'map_meta_cap' => true, 'show_admin_column' => true, 'capabilities' => array('manage_terms' => 'edit_bm_books', 'edit_terms' => 'edit_bm_books', 'delete_terms' => 'edit_bm_books', 'assign_terms' => 'edit_bm_books')));
    }
}
add_action('init', 'bm_register_dynamic_taxonomies', 11);

function bm_add_taxonomies_page() { add_submenu_page('edit.php?post_type=bm_book', __('Taxonomias', 'book-manager'), __('Taxonomias', 'book-manager'), 'edit_bm_books', 'bm_taxonomies', 'bm_render_taxonomies_page'); }
add_action('admin_menu', 'bm_add_taxonomies_page');

function bm_render_taxonomies_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    $msg = ''; $taxonomies = get_option('bm_dynamic_taxonomies', array()); if (!is_array($taxonomies)) $taxonomies = array();
    if (isset($_POST['bm_add_taxonomy']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) { $name = sanitize_text_field($_POST['bm_taxonomy_name']); $slug = sanitize_key($_POST['bm_taxonomy_slug'] ?: $name); $hierarchical = isset($_POST['bm_taxonomy_hierarchical']); if (empty($name)) $msg = '<div class="notice notice-error"><p>' . __('Nome é obrigatório.', 'book-manager') . '</p></div>'; elseif (taxonomy_exists($slug) || isset($taxonomies[$slug])) $msg = '<div class="notice notice-error"><p>' . __('Já existe uma taxonomia com este slug.', 'book-manager') . '</p></div>'; else { $taxonomies[$slug] = array('label' => $name, 'hierarchical' => $hierarchical); update_option('bm_dynamic_taxonomies', $taxonomies); flush_rewrite_rules(); $msg = '<div class="notice notice-success"><p>' . sprintf(__('Taxonomia "%s" criada!', 'book-manager'), $name) . '</p></div>'; } }
    if (isset($_POST['bm_rename_taxonomies']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) { $taxonomies = get_option('bm_dynamic_taxonomies', array()); if (isset($_POST['rename_taxonomy']) && is_array($_POST['rename_taxonomy'])) { foreach ($_POST['rename_taxonomy'] as $slug => $new_label) { $slug = sanitize_key($slug); $new_label = sanitize_text_field($new_label); if (isset($taxonomies[$slug]) && !empty($new_label) && empty($taxonomies[$slug]['protected'])) { $taxonomies[$slug]['label'] = $new_label; } } update_option('bm_dynamic_taxonomies', $taxonomies); $msg = '<div class="notice notice-success"><p>' . __('Taxonomias renomeadas.', 'book-manager') . '</p></div>'; } }
    if (isset($_POST['bm_delete_taxonomy']) && wp_verify_nonce($_POST['bm_taxonomy_nonce'], 'bm_taxonomy_action')) { $delete_slug = sanitize_key($_POST['bm_delete_slug']); if (isset($taxonomies[$delete_slug])) { if (!empty($taxonomies[$delete_slug]['protected'])) $msg = '<div class="notice notice-error"><p>' . __('Taxonomias protegidas não podem ser removidas.', 'book-manager') . '</p></div>'; else { unset($taxonomies[$delete_slug]); update_option('bm_dynamic_taxonomies', $taxonomies); flush_rewrite_rules(); $msg = '<div class="notice notice-success"><p>' . __('Taxonomia removida.', 'book-manager') . '</p></div>'; } } }
    ?>
    <div class="wrap"><h1><?php _e('Taxonomias Dinâmicas', 'book-manager'); ?></h1><?php echo $msg; ?><h2><?php _e('Criar Nova Taxonomia', 'book-manager'); ?></h2><form method="post" style="max-width:500px;"><?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?><table class="form-table"><tr><th><label><?php _e('Nome', 'book-manager'); ?></label></th><td><input type="text" name="bm_taxonomy_name" required style="width:100%;" placeholder="<?php _e('Ex: Séries', 'book-manager'); ?>" /></td></tr><tr><th><label><?php _e('Slug', 'book-manager'); ?></label></th><td><input type="text" name="bm_taxonomy_slug" style="width:100%;" placeholder="<?php _e('Gerado automaticamente', 'book-manager'); ?>" /></td></tr><tr><th><label><?php _e('Hierárquica', 'book-manager'); ?></label></th><td><label><input type="checkbox" name="bm_taxonomy_hierarchical" checked /> <?php _e('Permitir subcategorias', 'book-manager'); ?></label></td></tr></table><p><input type="submit" name="bm_add_taxonomy" class="button button-primary" value="<?php _e('Criar Taxonomia', 'book-manager'); ?>" /></p></form><h2><?php _e('Taxonomias Existentes', 'book-manager'); ?></h2><?php if (empty($taxonomies)): ?><p><?php _e('Nenhuma taxonomia criada.', 'book-manager'); ?></p><?php else: ?><form method="post"><?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?><table class="wp-list-table widefat fixed striped"><thead><tr><th><?php _e('Nome', 'book-manager'); ?></th><th><?php _e('Slug', 'book-manager'); ?></th><th><?php _e('Hierárquica', 'book-manager'); ?></th><th><?php _e('Termos', 'book-manager'); ?></th><th><?php _e('Ações', 'book-manager'); ?></th></tr></thead><tbody><?php foreach ($taxonomies as $slug => $info): ?><tr><td><?php if (isset($info['protected']) && $info['protected']): ?><strong><?php echo esc_html($info['label']); ?></strong> 🔒<?php else: ?><input type="text" name="rename_taxonomy[<?php echo esc_attr($slug); ?>]" value="<?php echo esc_attr($info['label']); ?>" style="width:100%;" /><?php endif; ?></td><td><code><?php echo esc_html($slug); ?></code></td><td><?php echo $info['hierarchical'] ? '✅' : '❌'; ?></td><td><a href="<?php echo admin_url('edit-tags.php?taxonomy=' . $slug . '&post_type=bm_book'); ?>" class="button button-small"><?php _e('Gerenciar Termos', 'book-manager'); ?></a></td><td><form method="post" style="display:inline;" onsubmit="return confirm('<?php _e('Remover esta taxonomia?', 'book-manager'); ?>');"><?php wp_nonce_field('bm_taxonomy_action', 'bm_taxonomy_nonce'); ?><input type="hidden" name="bm_delete_slug" value="<?php echo esc_attr($slug); ?>"><button type="submit" name="bm_delete_taxonomy" class="button button-small"><?php _e('Remover', 'book-manager'); ?></button></form></td></tr><?php endforeach; ?></tbody></table><p><input type="submit" name="bm_rename_taxonomies" class="button button-primary" value="<?php _e('Salvar Alterações', 'book-manager'); ?>" /></p></form><?php endif; ?></div>
    <?php
}

// ==========================================
// ETIQUETAS
// ==========================================
function bm_add_labels_page() { add_submenu_page('edit.php?post_type=bm_book', __('Etiquetas', 'book-manager'), __('Etiquetas', 'book-manager'), 'edit_bm_books', 'bm_labels', 'bm_render_labels_page'); }
add_action('admin_menu', 'bm_add_labels_page');
function bm_labels_init_session() { if (!session_id() && !headers_sent()) session_start(); if (!isset($_SESSION['bm_labels_cart'])) $_SESSION['bm_labels_cart'] = array(); }
add_action('init', 'bm_labels_init_session');
function bm_ajax_toggle_label() { if (!session_id()) session_start(); if (!isset($_SESSION['bm_labels_cart'])) $_SESSION['bm_labels_cart'] = array(); $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0; if (!$book_id) wp_die(json_encode(array('success' => false))); if (in_array($book_id, $_SESSION['bm_labels_cart'])) { $_SESSION['bm_labels_cart'] = array_diff($_SESSION['bm_labels_cart'], array($book_id)); $action = 'removed'; } else { $_SESSION['bm_labels_cart'][] = $book_id; $action = 'added'; } wp_die(json_encode(array('success' => true, 'action' => $action, 'count' => count($_SESSION['bm_labels_cart'])))); }
function bm_label_button() { if (!is_singular('bm_book') || (!current_user_can('edit_bm_books') && !current_user_can('manage_options'))) return; $book_id = get_the_ID(); if (!session_id()) session_start(); $in_cart = isset($_SESSION['bm_labels_cart']) && in_array($book_id, $_SESSION['bm_labels_cart']); $label = $in_cart ? '➖ ' . __('Remover etiqueta', 'book-manager') : '➕ ' . __('Adicionar etiqueta', 'book-manager'); $color = $in_cart ? '#dc3545' : '#111'; ?><div style="margin:10px 0;"><button type="button" class="bm-label-toggle" data-book="<?php echo $book_id; ?>" style="padding:6px 12px;background:<?php echo $color; ?>;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;"><?php echo $label; ?></button></div><script>document.addEventListener('click', function(e) { if (e.target.classList.contains('bm-label-toggle')) { var btn = e.target; var bookId = btn.getAttribute('data-book'); btn.disabled = true; var xhr = new XMLHttpRequest(); xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>'); xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); xhr.onload = function() { var r = JSON.parse(xhr.responseText); if (r.success) { btn.textContent = r.action === 'added' ? '➖ Remover etiqueta' : '➕ Adicionar etiqueta'; btn.style.background = r.action === 'added' ? '#dc3545' : '#111'; } btn.disabled = false; }; xhr.send('action=bm_toggle_label&book_id=' + bookId); } });</script><?php }
function bm_render_labels_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!session_id()) session_start(); $cart = isset($_SESSION['bm_labels_cart']) ? $_SESSION['bm_labels_cart'] : array();
    if (isset($_POST['clear_cart'])) { $_SESSION['bm_labels_cart'] = array(); $cart = array(); echo '<div class="notice notice-success"><p>' . __('Etiquetas removidas.', 'book-manager') . '</p></div>'; }
    $filter_genre = isset($_GET['filter_genre']) ? intval($_GET['filter_genre']) : 0; $filter_discipline = isset($_GET['filter_discipline']) ? intval($_GET['filter_discipline']) : 0; $filter_cdu = isset($_GET['filter_cdu']) ? sanitize_text_field($_GET['filter_cdu']) : ''; $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
    $args = array('post_type' => 'bm_book', 'posts_per_page' => 50, 'post_status' => 'publish'); if ($filter_genre) $args['tax_query'][] = array('taxonomy' => 'bm_genre', 'field' => 'term_id', 'terms' => $filter_genre); if ($filter_discipline) $args['tax_query'][] = array('taxonomy' => 'bm_discipline', 'field' => 'term_id', 'terms' => $filter_discipline); if ($filter_search) $args['s'] = $filter_search; if ($filter_cdu) $args['meta_query'][] = array('key' => '_bm_cdu', 'value' => $filter_cdu, 'compare' => 'LIKE');
    $books = get_posts($args);
    if (isset($_POST['add_selected']) && isset($_POST['book_ids'])) { foreach ($_POST['book_ids'] as $id) { if (!in_array($id, $cart)) $cart[] = intval($id); } $_SESSION['bm_labels_cart'] = $cart; }
    ?>
    <div class="wrap"><h1><?php _e('Geração de Etiquetas', 'book-manager'); ?></h1><div style="display:flex;gap:20px;flex-wrap:wrap;"><div style="flex:1;min-width:400px;"><h2><?php _e('Selecionar Livros', 'book-manager'); ?></h2><form method="get" style="margin-bottom:15px;"><input type="hidden" name="post_type" value="bm_book"><input type="hidden" name="page" value="bm_labels"><div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;"><div><label><?php _e('Buscar', 'book-manager'); ?></label><input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Título ou autor', 'book-manager'); ?>" style="padding:4px 8px;" /></div><div><label><?php _e('Gênero', 'book-manager'); ?></label><?php wp_dropdown_categories(array('show_option_all' => __('Todos', 'book-manager'), 'taxonomy' => 'bm_genre', 'name' => 'filter_genre', 'selected' => $filter_genre, 'hide_empty' => true)); ?></div><div><label><?php _e('Disciplina', 'book-manager'); ?></label><?php wp_dropdown_categories(array('show_option_all' => __('Todas', 'book-manager'), 'taxonomy' => 'bm_discipline', 'name' => 'filter_discipline', 'selected' => $filter_discipline, 'hide_empty' => true)); ?></div><div><label><?php _e('Classif.', 'book-manager'); ?></label><input type="text" name="filter_cdu" value="<?php echo esc_attr($filter_cdu); ?>" style="width:80px;padding:4px 8px;" /></div><div><button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button></div></div></form><form method="post"><table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:30px;"><input type="checkbox" id="bm-select-all" onclick="var cbs=document.querySelectorAll('input[name=\'book_ids[]\']');for(var i=0;i<cbs.length;i++)cbs[i].checked=this.checked;" /></th><th><?php _e('Título', 'book-manager'); ?></th><th><?php _e('Autor', 'book-manager'); ?></th><th><?php _e('Classif.', 'book-manager'); ?></th><th><?php _e('Ex.', 'book-manager'); ?></th></tr></thead><tbody><?php foreach ($books as $book): $author = get_post_meta($book->ID, '_bm_author', true); $cdu = get_post_meta($book->ID, '_bm_cdu', true); $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true))); ?><tr><td><input type="checkbox" name="book_ids[]" value="<?php echo $book->ID; ?>" <?php checked(in_array($book->ID, $cart)); ?> /></td><td><?php echo esc_html($book->post_title); ?></td><td><?php echo esc_html($author); ?></td><td><?php echo esc_html($cdu); ?></td><td><?php echo $copies; ?></td></tr><?php endforeach; ?></tbody></table><p style="margin-top:10px;"><button type="submit" name="add_selected" class="button button-primary"><?php _e('Adicionar etiquetas', 'book-manager'); ?></button></p></form></div><div style="flex:0 0 350px;"><h2>🖨️ <?php _e('Etiquetas selecionadas', 'book-manager'); ?> (<?php echo count($cart); ?>)</h2><?php if (empty($cart)): ?><p><?php _e('Nenhuma etiqueta selecionada.', 'book-manager'); ?></p><?php else: ?><ul style="max-height:400px;overflow-y:auto;list-style:none;padding:0;margin:0;"><?php $cart_books = get_posts(array('post_type' => 'bm_book', 'post__in' => $cart, 'posts_per_page' => -1, 'orderby' => 'post__in')); foreach ($cart_books as $book): $author = get_post_meta($book->ID, '_bm_author', true); $cdu = get_post_meta($book->ID, '_bm_cdu', true); $cutter = get_post_meta($book->ID, '_bm_cutter', true); $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true))); ?><li style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #eee;"><button type="button" class="bm-remove-label" data-book="<?php echo $book->ID; ?>" style="background:#dc3545;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1;">✕</button><div style="flex:1;font-size:12px;"><strong><?php echo esc_html($book->post_title); ?></strong><?php if ($author): ?><br><small><?php echo esc_html($author); ?></small><?php endif; ?><?php if ($cdu): ?><br><small>Class: <?php echo esc_html($cdu); ?> | Cutter: <?php echo esc_html($cutter); ?></small><?php endif; ?><br><small><?php printf(__('%d exemplares', 'book-manager'), $copies); ?></small></div></li><?php endforeach; ?></ul><form method="post" style="margin-top:10px;display:flex;gap:10px;"><button type="submit" name="clear_cart" class="button"><?php _e('Limpar etiquetas', 'book-manager'); ?></button><button type="button" id="bm-preview-labels" class="button button-primary">🖨️ <?php _e('Visualizar Impressão', 'book-manager'); ?></button></form><?php endif; ?></div></div></div>
    <script>document.addEventListener('click', function(e) { if (e.target.classList.contains('bm-remove-label')) { var bookId = e.target.getAttribute('data-book'); var xhr = new XMLHttpRequest(); xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>'); xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); xhr.onload = function() { location.reload(); }; xhr.send('action=bm_toggle_label&book_id=' + bookId); } }); var previewBtn = document.getElementById('bm-preview-labels'); if (previewBtn) { previewBtn.addEventListener('click', function() { var cart = <?php echo json_encode(array_values($cart)); ?>; if (cart.length === 0) { alert('<?php _e("Nenhuma etiqueta selecionada.", "book-manager"); ?>'); return; } var url = '<?php echo admin_url("admin-ajax.php"); ?>?action=bm_print_labels&ids=' + cart.join(','); window.open(url, '_blank'); }); }</script>
    <?php
}
function bm_ajax_print_labels() { if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die('Sem permissão.'); $ids = isset($_GET['ids']) ? explode(',', sanitize_text_field($_GET['ids'])) : array(); if (empty($ids)) wp_die('Nenhum livro selecionado.'); $books = get_posts(array('post_type' => 'bm_book', 'post__in' => $ids, 'posts_per_page' => -1, 'orderby' => 'post__in')); ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title><?php _e('Etiquetas — Visualização', 'book-manager'); ?></title><style>@page{size:A4;margin:1.2cm 0.3cm 0.2cm 0.3cm}body{font-family:Arial,sans-serif;margin:0;padding:0}.labels-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.15cm;padding:0}.label{border:1px dashed #ccc;padding:0.2cm 0.15cm;text-align:center;height:2.4cm;display:flex;flex-direction:column;justify-content:center;page-break-inside:avoid}.label .author{font-weight:bold;font-size:12px;text-transform:uppercase;margin-bottom:2px}.label .title{font-size:10px;margin-bottom:3px}.label .cdu{font-weight:bold;font-size:16px;margin-bottom:2px}.label .cutter{font-weight:bold;font-size:16px;margin-bottom:2px}.label .info{font-size:9px;color:#666}.label .barcode{font-size:9px;letter-spacing:2px;margin-top:3px}.no-print{text-align:center;margin:20px}@media print{.no-print{display:none}.label{border:none}body{margin:0;padding:0}}</style></head><body><div class="no-print" style="padding:20px;background:#f9f9f9;margin-bottom:20px;"><h2><?php _e('Visualização de Etiquetas', 'book-manager'); ?> (<?php echo count($books); ?> livros)</h2><p><?php _e('Pressione Ctrl+P para imprimir. Ajuste as margens para "Mínimo".', 'book-manager'); ?></p><button onclick="window.print()" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px;">🖨️ <?php _e('Imprimir Agora', 'book-manager'); ?></button></div><div class="labels-grid"><?php foreach ($books as $book): $author = get_post_meta($book->ID, '_bm_author', true); $cdu = get_post_meta($book->ID, '_bm_cdu', true); $cutter = get_post_meta($book->ID, '_bm_cutter', true); $edition = get_post_meta($book->ID, '_bm_edition', true); $isbn = get_post_meta($book->ID, '_bm_isbn', true); $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true))); $author_formatted = ''; if ($author) { $parts = explode(' ', trim($author)); $author_formatted = count($parts) > 1 ? mb_strtoupper(array_pop($parts)) . ', ' . implode(' ', $parts) : mb_strtoupper($author); } $max_labels = max(1, $copies); for ($i = 1; $i <= $max_labels; $i++): ?><div class="label"><div class="author"><?php echo esc_html($author_formatted); ?></div><div class="title"><?php echo esc_html($book->post_title); ?></div><div class="cdu"><?php echo esc_html($cdu); ?></div><div class="cutter"><?php echo esc_html($cutter); ?></div><div class="info"><?php if ($edition) echo esc_html($edition) . ' '; if ($copies > 1) printf(__('Ex. %d/%d', 'book-manager'), $i, $copies); ?></div><?php if ($isbn): ?><div class="barcode">|||<?php echo esc_html($isbn); ?>|||</div><?php endif; ?></div><?php endfor; endforeach; ?></div></body></html><?php exit; }
add_action('wp_ajax_bm_print_labels', 'bm_ajax_print_labels');

// ==========================================
// SUGESTÕES DE AQUISIÇÃO
// ==========================================
function bm_add_acquisition_suggestions_page() { add_submenu_page('edit.php?post_type=bm_book', __('Sugestões de Aquisição', 'book-manager'), __('Sugestões de Aquisição', 'book-manager'), 'edit_bm_books', 'bm_acquisition_suggestions', 'bm_render_acquisition_suggestions_page'); }
add_action('admin_menu', 'bm_add_acquisition_suggestions_page');
function bm_render_acquisition_suggestions_page() { if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return; $suggestions = get_option('bm_acquisition_suggestions', array()); ?><div class="wrap"><h1><?php _e('Sugestões de Aquisição', 'book-manager'); ?></h1><?php if (empty($suggestions)): ?><p><?php _e('Nenhuma sugestão recebida.', 'book-manager'); ?></p><?php else: ?><table class="wp-list-table widefat fixed striped"><thead><tr><th><?php _e('Usuário', 'book-manager'); ?></th><th><?php _e('Título', 'book-manager'); ?></th><th><?php _e('Autor', 'book-manager'); ?></th><th><?php _e('Editora', 'book-manager'); ?></th><th><?php _e('Motivo', 'book-manager'); ?></th><th><?php _e('Data', 'book-manager'); ?></th></tr></thead><tbody><?php foreach (array_reverse($suggestions) as $s): ?><tr><td><?php echo esc_html($s['user_name']); ?></td><td><strong><?php echo esc_html($s['title']); ?></strong></td><td><?php echo esc_html($s['author'] ?: '—'); ?></td><td><?php echo esc_html($s['publisher'] ?: '—'); ?></td><td><?php echo esc_html($s['reason'] ?: '—'); ?></td><td><?php echo date('d/m/Y', strtotime($s['date'])); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div><?php }

// ==========================================
// CARTEIRINHAS
// ==========================================
function bm_render_library_cards_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    if (!session_id()) session_start(); $cart = isset($_SESSION['bm_library_cards_cart']) ? $_SESSION['bm_library_cards_cart'] : array();
    if (isset($_POST['clear_cart'])) { $_SESSION['bm_library_cards_cart'] = array(); $cart = array(); echo '<div class="notice notice-success"><p>' . __('Carteirinhas removidas.', 'book-manager') . '</p></div>'; }
    $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : ''; $filter_group = isset($_GET['filter_group']) ? sanitize_text_field($_GET['filter_group']) : '';
    $args = array('role' => 'bm_student', 'number' => 50); if ($filter_search) $args['search'] = '*' . $filter_search . '*'; $students = get_users($args);
    if ($filter_group) { $filtered = array(); foreach ($students as $student) { $group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true); if (mb_strtolower(trim($group)) === mb_strtolower(trim($filter_group))) $filtered[] = $student; } $students = $filtered; }
    if (isset($_POST['add_selected']) && isset($_POST['user_ids'])) { foreach ($_POST['user_ids'] as $id) { if (!in_array($id, $cart)) $cart[] = intval($id); } $_SESSION['bm_library_cards_cart'] = $cart; }
    ?>
    <div class="wrap"><h1><?php _e('Geração de Carteirinhas', 'book-manager'); ?></h1><div style="display:flex;gap:20px;flex-wrap:wrap;"><div style="flex:1;min-width:400px;"><h2><?php _e('Selecionar Alunos', 'book-manager'); ?></h2><form method="get" style="margin-bottom:15px;"><input type="hidden" name="post_type" value="bm_book"><input type="hidden" name="page" value="bm_library_cards"><div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;"><div><label><?php _e('Buscar', 'book-manager'); ?></label><input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Nome ou e-mail', 'book-manager'); ?>" style="padding:4px 8px;" /></div><div><label><?php _e('Turma', 'book-manager'); ?></label><input type="text" name="filter_group" value="<?php echo esc_attr($filter_group); ?>" placeholder="<?php _e('Ex: 1º Ano', 'book-manager'); ?>" style="width:80px;padding:4px 8px;" /></div><div><button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button></div></div></form><form method="post"><table class="wp-list-table widefat fixed striped"><thead><tr><th style="width:30px;"><input type="checkbox" id="bm-select-all" onclick="var cbs=document.querySelectorAll('input[name=\'user_ids[]\']');for(var i=0;i<cbs.length;i++)cbs[i].checked=this.checked;" /></th><th><?php _e('Aluno', 'book-manager'); ?></th><th><?php _e('Turma', 'book-manager'); ?></th><th><?php _e('E-mail', 'book-manager'); ?></th><th><?php _e('Foto', 'book-manager'); ?></th></tr></thead><tbody><?php foreach ($students as $student): $photo = get_user_meta($student->ID, '_bm_profile_photo', true); $group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true); ?><tr><td><input type="checkbox" name="user_ids[]" value="<?php echo $student->ID; ?>" <?php checked(in_array($student->ID, $cart)); ?> /></td><td><strong><?php echo esc_html($student->display_name); ?></strong></td><td><?php echo esc_html($group); ?></td><td><?php echo esc_html($student->user_email); ?></td><td><?php echo $photo ? '✅' : '❌'; ?></td></tr><?php endforeach; ?></tbody></table><p style="margin-top:10px;"><button type="submit" name="add_selected" class="button button-primary"><?php _e('Adicionar à carteirinha', 'book-manager'); ?></button></p></form></div><div style="flex:0 0 350px;"><h2>🖨️ <?php _e('Carteirinhas selecionadas', 'book-manager'); ?> (<?php echo count($cart); ?>)</h2><?php if (empty($cart)): ?><p><?php _e('Nenhuma carteirinha selecionada.', 'book-manager'); ?></p><?php else: ?><ul style="max-height:400px;overflow-y:auto;list-style:none;padding:0;margin:0;"><?php foreach ($cart as $uid): $u = get_userdata($uid); if (!$u) continue; $photo = get_user_meta($uid, '_bm_profile_photo', true); $group = get_user_meta($uid, '_bm_user_' . sanitize_key('Turma'), true); ?><li style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #eee;"><button type="button" class="bm-remove-card" data-user="<?php echo $uid; ?>" style="background:#dc3545;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1;">✕</button><div style="flex:1;font-size:12px;"><strong><?php echo esc_html($u->display_name); ?></strong><?php if ($group): ?><br><small><?php echo esc_html($group); ?></small><?php endif; ?><?php if (!$photo): ?><br><small style="color:#f0ad4e;">⚠️ <?php _e('Sem foto', 'book-manager'); ?></small><?php endif; ?></div></li><?php endforeach; ?></ul><form method="post" style="margin-top:10px;display:flex;gap:10px;"><button type="submit" name="clear_cart" class="button"><?php _e('Limpar todas', 'book-manager'); ?></button><button type="button" id="bm-preview-cards" class="button button-primary">🖨️ <?php _e('Visualizar Impressão', 'book-manager'); ?></button></form><?php endif; ?></div></div></div>
    <script>document.addEventListener('click', function(e) { if (e.target.classList.contains('bm-remove-card')) { var userId = e.target.getAttribute('data-user'); var xhr = new XMLHttpRequest(); xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>'); xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); xhr.onload = function() { location.reload(); }; xhr.send('action=bm_toggle_library_card&user_id=' + userId); } }); var previewCardsBtn = document.getElementById('bm-preview-cards'); if (previewCardsBtn) { previewCardsBtn.addEventListener('click', function() { var cart = <?php echo json_encode(array_values($cart)); ?>; if (cart.length === 0) { alert('<?php _e("Nenhuma carteirinha selecionada.", "book-manager"); ?>'); return; } var url = '<?php echo admin_url("admin-ajax.php"); ?>?action=bm_print_library_cards_bulk&ids=' + cart.join(','); window.open(url, '_blank'); }); }</script>
    <?php
}
function bm_ajax_toggle_library_card() { if (!session_id()) session_start(); if (!isset($_SESSION['bm_library_cards_cart'])) $_SESSION['bm_library_cards_cart'] = array(); $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0; if (!$user_id) wp_die(json_encode(array('success' => false))); if (in_array($user_id, $_SESSION['bm_library_cards_cart'])) { $_SESSION['bm_library_cards_cart'] = array_diff($_SESSION['bm_library_cards_cart'], array($user_id)); $action = 'removed'; } else { $_SESSION['bm_library_cards_cart'][] = $user_id; $action = 'added'; } wp_die(json_encode(array('success' => true, 'action' => $action, 'count' => count($_SESSION['bm_library_cards_cart'])))); }
add_action('wp_ajax_bm_toggle_library_card', 'bm_ajax_toggle_library_card');