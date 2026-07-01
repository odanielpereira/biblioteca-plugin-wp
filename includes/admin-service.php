<?php
/**
 * Book Manager — Módulo de Serviços Administrativos
 * Balcão de Atendimento, Alunos, Empréstimos Admin, Etiquetas, Carteirinhas, Relatórios Admin
 */

defined('ABSPATH') || exit;

// ==========================================
// FASE 18: PÁGINA UNIFICADA — BALCÃO DE ATENDIMENTO
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

// ==========================================
// FASE 12K: ATENDIMENTO (EMPRÉSTIMO RÁPIDO NO BALCÃO)
// ==========================================
function bm_render_service_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    bm_render_service_page_content();
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
    
    <div id="bm-quick-register-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:25px;border-radius:8px;max-width:450px;width:90%;max-height:80vh;overflow-y:auto;">
            <h2 style="margin-top:0;" id="bm-modal-title">➕ <?php _e('Cadastro Rápido de Aluno', 'book-manager'); ?></h2>
            <form id="bm-quick-register-form" onsubmit="return false;">
                <?php wp_nonce_field('bm_service_nonce', 'bm_quick_register_nonce'); ?>
                <p>
                    <label><strong><?php _e('Nome completo', 'book-manager'); ?> *</strong></label>
                    <input type="text" name="bm_quick_name" required style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <p>
                    <label><strong><?php _e('E-mail', 'book-manager'); ?> *</strong></label>
                    <input type="email" name="bm_quick_email" required style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <p>
                    <label><strong><?php _e('Telefone', 'book-manager'); ?></strong></label>
                    <input type="text" name="bm_quick_phone" style="width:100%;padding:8px;margin-top:4px;" placeholder="5511999999999" />
                </p>
                <?php
                $user_fields = get_option('bm_user_dynamic_fields', array());
                $user_field_order = get_option('bm_user_field_order', array());
                $ordered_fields = array();
                foreach ($user_field_order as $key) {
                    if (isset($user_fields[$key])) $ordered_fields[$key] = $user_fields[$key];
                }
                foreach ($user_fields as $key => $info) {
                    if (!isset($ordered_fields[$key])) $ordered_fields[$key] = $info;
                }
                foreach ($ordered_fields as $field_name => $info):
                    $name_lower = mb_strtolower(trim($field_name));
                    if (in_array($name_lower, array('nome completo', 'e-mail', 'email', 'telefone'))) continue;
                    $meta_key = '_bm_user_' . sanitize_key($field_name);
                ?>
                <p>
                    <label><strong><?php echo esc_html($field_name); ?></strong></label>
                    <input type="text" name="<?php echo esc_attr($meta_key); ?>" style="width:100%;padding:8px;margin-top:4px;" />
                </p>
                <?php endforeach; ?>
                <p style="margin-top:15px;display:flex;gap:10px;">
                    <button type="submit" class="button button-primary" style="flex:1;" id="bm-modal-submit-btn"><?php _e('Cadastrar', 'book-manager'); ?></button>
                    <button type="button" class="button" onclick="document.getElementById('bm-quick-register-modal').style.display='none'" style="flex:1;"><?php _e('Cancelar', 'book-manager'); ?></button>
                </p>
            </form>
        </div>
    </div>
    
    <div id="bm-damage-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:25px;border-radius:8px;max-width:400px;width:90%;">
            <h3 style="margin-top:0;">📋 <?php _e('Registro de Devolução', 'book-manager'); ?></h3>
            <p>
                <label><strong><?php _e('Estado do livro:', 'book-manager'); ?></strong></label>
                <select id="bm-damage-status" style="width:100%;padding:8px;margin-top:4px;">
                    <option value="good">✅ <?php _e('Bom', 'book-manager'); ?></option>
                    <option value="acceptable">⚠️ <?php _e('Aceitável', 'book-manager'); ?></option>
                    <option value="damaged">❌ <?php _e('Danificado', 'book-manager'); ?></option>
                </select>
            </p>
            <p>
                <label><strong><?php _e('Observação:', 'book-manager'); ?></strong></label>
                <textarea id="bm-damage-note" rows="3" style="width:100%;margin-top:4px;" placeholder="<?php _e('Descreva o dano...', 'book-manager'); ?>"></textarea>
            </p>
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
                if (r.found) {
                    bmDisplayBook(r.book);
                } else if (r.can_register) {
                    bmShowBookNotFound(isbn, r.isbn);
                } else {
                    document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>';
                }
            } catch(e) {
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>';
            }
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
                if (r.found) {
                    bmDisplayBook(r.book);
                } else {
                    document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>';
                }
            } catch(e) {
                document.getElementById('bm-book-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>';
            }
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
            book.queue.forEach(function(q) {
                qHtml += '<li>' + q.name + ' (desde ' + q.date + ')</li>';
            });
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
                if (r.found) {
                    bmDisplayStudent(r.student);
                } else if (r.multiple) {
                    bmDisplayStudentList(r.students);
                } else {
                    document.getElementById('bm-student-result').innerHTML = '<p style="color:#dc3545;">' + r.message + '</p>';
                }
            } catch(e) {
                document.getElementById('bm-student-result').innerHTML = '<p style="color:#dc3545;">Erro na busca.</p>';
            }
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
            student.recent_books.forEach(function(b) {
                html += '<span style="display:inline-block;background:#e3f2fd;padding:2px 8px;border-radius:10px;font-size:11px;margin:2px;">' + b + '</span> ';
            });
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
        xhr.onload = function() {
            var r = JSON.parse(xhr.responseText);
            if (r.found) bmDisplayStudent(r.student);
        };
        xhr.send('action=bm_service_search_student&student_id=' + id + '&nonce=' + bmNonce);
    }
    
    function bmCheckActionReady() {
        if (bmSelectedBook && bmSelectedStudent) {
            document.getElementById('bm-action-area').style.display = 'block';
        }
    }
    
    document.getElementById('bm-loan-btn').addEventListener('click', function() {
        if (!bmSelectedBook || !bmSelectedStudent) return;
        if (bmSelectedStudent.blocked) {
            alert('Aluno com atraso — empréstimo bloqueado.');
            return;
        }
        if (bmSelectedBook.consulta_local) {
            alert('Este livro é de consulta local e não pode ser emprestado.');
            return;
        }
        if (bmSelectedBook.available <= 0) {
            alert('Não há exemplares disponíveis.');
            return;
        }
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
        dynamicInputs.forEach(function(input) {
            params += '&' + input.name + '=' + encodeURIComponent(input.value);
        });
        
        if (editId) {
            params = 'action=bm_service_edit_student&student_id=' + editId + '&' + params;
        } else {
            params = 'action=bm_service_quick_register&' + params;
        }
        
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
                if (editId) {
                    bmSelectStudent(editId);
                } else {
                    bmSelectedStudent = { id: r.student_id, name: r.student_name };
                    document.getElementById('bm-student-result').innerHTML = '<h3>' + r.student_name + '</h3><p style="color:green;">' + r.message + '</p>';
                    bmCheckActionReady();
                }
            } else {
                alert(r.message);
            }
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
            } else {
                alert(r.message);
            }
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
// FASE 12J: ADMINISTRAÇÃO DE ALUNOS
// ==========================================
function bm_add_students_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Alunos', 'book-manager'), __('Alunos', 'book-manager'), 'edit_bm_books', 'bm_students', 'bm_render_students_unified_page');
}
add_action('admin_menu', 'bm_add_students_page');

function bm_render_students_unified_page() {

function bm_render_reading_lists_page_content() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $msg = '';
    $reading_lists = get_option('bm_reading_lists', array());
    if (!is_array($reading_lists)) $reading_lists = array();
    
    if (isset($_POST['bm_save_reading_list']) && wp_verify_nonce($_POST['bm_reading_list_nonce'], 'bm_reading_list_action')) {
        $group = sanitize_text_field($_POST['bm_list_group']);
        $book_ids = isset($_POST['bm_list_books']) ? array_map('intval', $_POST['bm_list_books']) : array();
        $description = sanitize_textarea_field($_POST['bm_list_description']);
        
        if (empty($group)) {
            $msg = '<div class="notice notice-error"><p>' . __('Informe a turma.', 'book-manager') . '</p></div>';
        } elseif (empty($book_ids)) {
            $msg = '<div class="notice notice-error"><p>' . __('Selecione pelo menos um livro.', 'book-manager') . '</p></div>';
        } else {
            $reading_lists[$group] = array(
                'books' => $book_ids,
                'description' => $description,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            );
            update_option('bm_reading_lists', $reading_lists);
            $msg = '<div class="notice notice-success"><p>' . sprintf(__('Lista de leitura para %s salva!', 'book-manager'), $group) . '</p></div>';
        }
    }
    
    if (isset($_POST['bm_delete_list']) && wp_verify_nonce($_POST['bm_reading_list_nonce'], 'bm_reading_list_action')) {
        $delete_group = sanitize_text_field($_POST['bm_delete_group']);
        if (isset($reading_lists[$delete_group])) {
            unset($reading_lists[$delete_group]);
            update_option('bm_reading_lists', $reading_lists);
            $msg = '<div class="notice notice-success"><p>' . sprintf(__('Lista da turma %s removida.', 'book-manager'), $delete_group) . '</p></div>';
        }
    }
    
    $all_students = get_users(array('role' => 'bm_student', 'number' => 200));
    $existing_groups = array();
    foreach ($all_students as $student) {
        $student_group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true);
        if (!empty($student_group) && !in_array($student_group, $existing_groups)) {
            $existing_groups[] = $student_group;
        }
    }
    sort($existing_groups);
    
    ?>
    <div class="wrap">
        <h1><?php _e('Listas de Leitura Obrigatória', 'book-manager'); ?></h1>
        <?php echo $msg; ?>
        
        <h2><?php _e('Nova Lista', 'book-manager'); ?></h2>
        <form method="post" style="max-width:600px;">
            <?php wp_nonce_field('bm_reading_list_action', 'bm_reading_list_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Turma', 'book-manager'); ?></label></th>
                    <td>
                        <select name="bm_list_group" required style="width:200px;">
                            <option value=""><?php _e('— Selecione —', 'book-manager'); ?></option>
                            <?php foreach ($existing_groups as $group): ?>
                                <option value="<?php echo esc_attr($group); ?>"><?php echo esc_html($group); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Selecione uma turma existente. Se não aparecer, cadastre alunos nessa turma primeiro.', 'book-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Descrição', 'book-manager'); ?></label></th>
                    <td>
                        <input type="text" name="bm_list_description" style="width:100%;" placeholder="<?php _e('Ex: Leituras obrigatórias do 2º bimestre', 'book-manager'); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Livros', 'book-manager'); ?></label></th>
                    <td>
                        <div id="bm-list-selected" style="margin-bottom:10px;"></div>
                        <input type="text" id="bm-list-book-search" placeholder="<?php _e('Digite o título do livro e pressione Enter...', 'book-manager'); ?>" style="width:100%;" />
                        <div id="bm-list-search-results" style="margin-top:5px;"></div>
                        <p class="description"><?php _e('Busque e selecione os livros. Eles aparecerão na lista acima.', 'book-manager'); ?></p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="bm_save_reading_list" class="button button-primary" value="<?php _e('Salvar Lista', 'book-manager'); ?>" /></p>
        </form>
        
        <h2><?php _e('Listas Existentes', 'book-manager'); ?></h2>
        <?php if (empty($reading_lists)): ?>
            <p><?php _e('Nenhuma lista criada.', 'book-manager'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Turma', 'book-manager'); ?></th>
                        <th><?php _e('Descrição', 'book-manager'); ?></th>
                        <th><?php _e('Livros', 'book-manager'); ?></th>
                        <th><?php _e('Criado em', 'book-manager'); ?></th>
                        <th><?php _e('Ações', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reading_lists as $group => $list): ?>
                        <tr>
                            <td><strong><?php echo esc_html($group); ?></strong></td>
                            <td><?php echo esc_html($list['description']); ?></td>
                            <td><?php echo count($list['books']); ?> livro(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($list['created_at'])); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('<?php _e('Remover esta lista?', 'book-manager'); ?>');">
                                    <?php wp_nonce_field('bm_reading_list_action', 'bm_reading_list_nonce'); ?>
                                    <input type="hidden" name="bm_delete_group" value="<?php echo esc_attr($group); ?>">
                                    <button type="submit" name="bm_delete_list" class="button button-small"><?php _e('Remover', 'book-manager'); ?></button>
                                </form>
                            </td>
                        </tr>
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
                if (r.found) {
                    html += '<div style="padding:6px;background:#e8f5e9;border-radius:4px;cursor:pointer;margin:3px 0;" onclick="bmAddBookToList(' + r.book.id + ', \'' + r.book.title.replace(/'/g, "\\'") + '\')">' + r.book.title + ' — ' + (r.book.author || '') + '</div>';
                } else if (r.can_register) {
                    html += '<p style="color:#999;">Livro não encontrado.</p>';
                } else {
                    html += '<p style="color:#999;">Nenhum resultado.</p>';
                }
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
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bm_list_books[]';
        input.value = id;
        input.id = 'book-input-' + id;
        div.appendChild(input);
    }
    
    function bmRemoveBookFromList(id, el) {
        bmListBooks = bmListBooks.filter(function(item) { return item !== id; });
        el.parentElement.remove();
        var input = document.getElementById('book-input-' + id);
        if (input) input.remove();
    }
    </script>
    <?php
}

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
            if (function_exists('bm_render_reading_lists_page_content')) {
                bm_render_reading_lists_page_content();
            } else {
                echo '<p>' . __('Função não encontrada.', 'book-manager') . '</p>';
            }
        } elseif ($tab === 'library_cards') {
            if (function_exists('bm_render_library_cards_page')) {
                bm_render_library_cards_page();
            } else {
                echo '<p>' . __('Função não encontrada.', 'book-manager') . '</p>';
            }
        } elseif ($tab === 'acquisition_suggestions') {
            if (function_exists('bm_render_acquisition_suggestions_page')) {
                bm_render_acquisition_suggestions_page();
            } else {
                echo '<p>' . __('Função não encontrada.', 'book-manager') . '</p>';
            }
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

function bm_render_students_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    bm_render_students_page_content();
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
    
    if ($filter_status === 'pending') {
        $args['meta_key'] = 'bm_approval_status';
        $args['meta_value'] = 'pending';
    } elseif ($filter_status === 'suspended') {
        $args['role'] = 'subscriber';
        $args['meta_key'] = 'bm_approval_status';
        $args['meta_value'] = 'suspended';
    }
    
    if ($filter_group) {
        $args['meta_query'][] = array('key' => 'bm_student_group', 'value' => $filter_group, 'compare' => 'LIKE');
    }
    
    $students = get_users($args);
    
    if ($filter_overdue) {
        $filtered = array();
        foreach ($students as $student) {
            $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
            $has_overdue = false;
            foreach ($loan_history as $loan) {
                if ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < time()) {
                    $has_overdue = true;
                    break;
                }
            }
            if ($has_overdue) $filtered[] = $student;
        }
        $students = $filtered;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Alunos', 'book-manager'); ?></h1>
        <?php echo $msg; ?>

        <button type="button" class="button button-primary" id="bm-add-student-btn" style="margin-bottom:10px;">➕ <?php _e('Adicionar Novo Aluno', 'book-manager'); ?></button>

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
document.getElementById('bm-add-student-btn').addEventListener('click', function() {
    document.getElementById('bm-quick-register-modal').style.display = 'flex';
});

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
                <div>
                    <label><?php _e('Buscar', 'book-manager'); ?></label>
                    <input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Nome ou e-mail', 'book-manager'); ?>" style="padding:4px 8px;" />
                </div>
                <div>
                    <label><?php _e('Status', 'book-manager'); ?></label>
                    <select name="filter_status" style="padding:4px 8px;">
                        <option value=""><?php _e('Todos', 'book-manager'); ?></option>
                        <option value="approved" <?php selected($filter_status, 'approved'); ?>><?php _e('Aprovado', 'book-manager'); ?></option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php _e('Pendente', 'book-manager'); ?></option>
                        <option value="suspended" <?php selected($filter_status, 'suspended'); ?>><?php _e('Suspenso', 'book-manager'); ?></option>
                    </select>
                </div>
                <div>
                    <label><?php _e('Grupo', 'book-manager'); ?></label>
                    <input type="text" name="filter_group" value="<?php echo esc_attr($filter_group); ?>" placeholder="<?php _e('Ex: 1º Ano', 'book-manager'); ?>" style="padding:4px 8px;width:80px;" />
                </div>
                <div>
                    <label><input type="checkbox" name="filter_overdue" <?php checked($filter_overdue); ?> /> <?php _e('Apenas em atraso', 'book-manager'); ?></label>
                </div>
                <div>
                    <button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button>
                    <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_students'); ?>" class="button"><?php _e('Limpar', 'book-manager'); ?></a>
                </div>
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
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="bm-select-all-students" /></th>
                        <th><?php _e('Aluno', 'book-manager'); ?></th>
                        <th><?php _e('E-mail', 'book-manager'); ?></th>
                        <th><?php _e('Status', 'book-manager'); ?></th>
                        <th><?php _e('Grupo', 'book-manager'); ?></th>
                        <th><?php _e('XP', 'book-manager'); ?></th>
                        <th><?php _e('Empréstimos', 'book-manager'); ?></th>
                        <th><?php _e('Ações', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="8"><?php _e('Nenhum aluno encontrado.', 'book-manager'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): 
                            $status = get_user_meta($student->ID, 'bm_approval_status', true) ?: 'approved';
                            $group = get_user_meta($student->ID, 'bm_student_group', true);
                            $xp = bm_get_xp($student->ID);
                            $phone = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Telefone'), true);
                            
                            $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
                            $active_loans = 0; $has_overdue = false;
                            foreach ($loan_history as $loan) {
                                if ($loan['status'] === 'active') {
                                    $active_loans++;
                                    if (isset($loan['due_date']) && strtotime($loan['due_date']) < time()) {
                                        $has_overdue = true;
                                    }
                                }
                            }
                            
                            $row_style = $has_overdue ? 'background:#fff3f3;' : '';
                            $penalty_check = bm_check_penalty_block($student->ID);
                            if ($penalty_check) $row_style = 'background:#fff3e0;';
                            $status_labels = array('approved' => '✅', 'pending' => '⏳', 'suspended' => '🚫');
                            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : '✅';
                        ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td><input type="checkbox" name="user_ids[]" value="<?php echo $student->ID; ?>" /></td>
                                <td>
                                    <strong><?php echo esc_html($student->display_name); ?></strong>
                                    <?php if ($has_overdue): ?> <span style="color:#dc3545;" title="<?php _e('Em atraso', 'book-manager'); ?>">🔴</span><?php endif; ?>
                                    <?php if ($penalty_check): ?> <span style="color:#ff9800;" title="<?php _e('Penalidade ativa', 'book-manager'); ?>">🚫</span><?php endif; ?>
                                    </td>
                                <td><?php echo esc_html($student->user_email); ?></td>
                                <td><?php echo $status_label . ' ' . $status; ?></td>
                                <td><?php echo esc_html($group); ?></td>
                                <td><?php echo $xp; ?></td>
                                <td><?php echo $active_loans; ?> ativo(s)</td>
                                <td>
                                    <a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $student->ID); ?>" class="button button-small"><?php _e('Ver', 'book-manager'); ?></a>
                                    <?php if ($phone): ?>
                                        <?php echo bm_whatsapp_button($phone, '', 'WhatsApp'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>
    
    <script>
    (function() {
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'bm-select-all') {
                var checkboxes = document.querySelectorAll('input[name="book_ids[]"]');
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = e.target.checked;
                }
            }
        });
    })();
    </script>
    <?php
}

// ==========================================
// FASE 11C: GERAÇÃO DE ETIQUETAS
// ==========================================

function bm_add_acquisition_suggestions_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Sugestões de Aquisição', 'book-manager'), __('Sugestões de Aquisição', 'book-manager'), 'edit_bm_books', 'bm_acquisition_suggestions', 'bm_render_acquisition_suggestions_page');
}
add_action('admin_menu', 'bm_add_acquisition_suggestions_page');

function bm_render_acquisition_suggestions_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $suggestions = get_option('bm_acquisition_suggestions', array());
    ?>
    <div class="wrap">
        <h1><?php _e('Sugestões de Aquisição', 'book-manager'); ?></h1>
        <?php if (empty($suggestions)): ?>
            <p><?php _e('Nenhuma sugestão recebida.', 'book-manager'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Usuário', 'book-manager'); ?></th>
                        <th><?php _e('Título', 'book-manager'); ?></th>
                        <th><?php _e('Autor', 'book-manager'); ?></th>
                        <th><?php _e('Editora', 'book-manager'); ?></th>
                        <th><?php _e('Motivo', 'book-manager'); ?></th>
                        <th><?php _e('Data', 'book-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($suggestions) as $s): ?>
                        <tr>
                            <td><?php echo esc_html($s['user_name']); ?></td>
                            <td><strong><?php echo esc_html($s['title']); ?></strong></td>
                            <td><?php echo esc_html($s['author'] ?: '—'); ?></td>
                            <td><?php echo esc_html($s['publisher'] ?: '—'); ?></td>
                            <td><?php echo esc_html($s['reason'] ?: '—'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($s['date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

function bm_add_labels_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Etiquetas', 'book-manager'), __('Etiquetas', 'book-manager'), 'edit_bm_books', 'bm_labels', 'bm_render_labels_page');
}
add_action('admin_menu', 'bm_add_labels_page');

function bm_render_library_cards_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    if (!session_id()) session_start();
    $cart = isset($_SESSION['bm_library_cards_cart']) ? $_SESSION['bm_library_cards_cart'] : array();
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['bm_library_cards_cart'] = array();
        $cart = array();
        echo '<div class="notice notice-success"><p>' . __('Carteirinhas removidas.', 'book-manager') . '</p></div>';
    }
    
    $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
    $filter_group = isset($_GET['filter_group']) ? sanitize_text_field($_GET['filter_group']) : '';
    
    $args = array('role' => 'bm_student', 'number' => 50);
    if ($filter_search) $args['search'] = '*' . $filter_search . '*';
    
    $students = get_users($args);
    
    if ($filter_group) {
        $filtered = array();
        foreach ($students as $student) {
            $group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true);
            if (mb_strtolower(trim($group)) === mb_strtolower(trim($filter_group))) {
                $filtered[] = $student;
            }
        }
        $students = $filtered;
    }
    
    if (isset($_POST['add_selected']) && isset($_POST['user_ids'])) {
        foreach ($_POST['user_ids'] as $id) {
            if (!in_array($id, $cart)) $cart[] = intval($id);
        }
        $_SESSION['bm_library_cards_cart'] = $cart;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Geração de Carteirinhas', 'book-manager'); ?></h1>
        
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:400px;">
                <h2><?php _e('Selecionar Alunos', 'book-manager'); ?></h2>
                
                <form method="get" style="margin-bottom:15px;">
                    <input type="hidden" name="post_type" value="bm_book">
                    <input type="hidden" name="page" value="bm_library_cards">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                        <div><label><?php _e('Buscar', 'book-manager'); ?></label><input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Nome ou e-mail', 'book-manager'); ?>" style="padding:4px 8px;" /></div>
                        <div><label><?php _e('Turma', 'book-manager'); ?></label><input type="text" name="filter_group" value="<?php echo esc_attr($filter_group); ?>" placeholder="<?php _e('Ex: 1º Ano', 'book-manager'); ?>" style="width:80px;padding:4px 8px;" /></div>
                        <div><button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button></div>
                    </div>
                </form>
                
                <form method="post">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="bm-select-all" onclick="var cbs=document.querySelectorAll('input[name=\'user_ids[]\']');for(var i=0;i<cbs.length;i++)cbs[i].checked=this.checked;" /></th>
                                <th><?php _e('Aluno', 'book-manager'); ?></th>
                                <th><?php _e('Turma', 'book-manager'); ?></th>
                                <th><?php _e('E-mail', 'book-manager'); ?></th>
                                <th><?php _e('Foto', 'book-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): 
                                $photo = get_user_meta($student->ID, '_bm_profile_photo', true);
                                $group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true);
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="user_ids[]" value="<?php echo $student->ID; ?>" <?php checked(in_array($student->ID, $cart)); ?> /></td>
                                    <td><strong><?php echo esc_html($student->display_name); ?></strong></td>
                                    <td><?php echo esc_html($group); ?></td>
                                    <td><?php echo esc_html($student->user_email); ?></td>
                                    <td><?php echo $photo ? '✅' : '❌'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:10px;">
                        <button type="submit" name="add_selected" class="button button-primary"><?php _e('Adicionar à carteirinha', 'book-manager'); ?></button>
                    </p>
                </form>
            </div>
            
            <div style="flex:0 0 350px;">
                <h2>🖨️ <?php _e('Carteirinhas selecionadas', 'book-manager'); ?> (<?php echo count($cart); ?>)</h2>
                
                <?php if (empty($cart)): ?>
                    <p><?php _e('Nenhuma carteirinha selecionada.', 'book-manager'); ?></p>
                <?php else: ?>
                    <ul style="max-height:400px;overflow-y:auto;list-style:none;padding:0;margin:0;">
                        <?php 
                        foreach ($cart as $uid): 
                            $u = get_userdata($uid);
                            if (!$u) continue;
                            $photo = get_user_meta($uid, '_bm_profile_photo', true);
                            $group = get_user_meta($uid, '_bm_user_' . sanitize_key('Turma'), true);
                        ?>
                            <li style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #eee;">
                                <button type="button" class="bm-remove-card" data-user="<?php echo $uid; ?>" style="background:#dc3545;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1;">✕</button>
                                <div style="flex:1;font-size:12px;">
                                    <strong><?php echo esc_html($u->display_name); ?></strong>
                                    <?php if ($group): ?><br><small><?php echo esc_html($group); ?></small><?php endif; ?>
                                    <?php if (!$photo): ?><br><small style="color:#f0ad4e;">⚠️ <?php _e('Sem foto', 'book-manager'); ?></small><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="post" style="margin-top:10px;display:flex;gap:10px;">
                        <button type="submit" name="clear_cart" class="button"><?php _e('Limpar todas', 'book-manager'); ?></button>
                        <button type="button" id="bm-preview-cards" class="button button-primary">🖨️ <?php _e('Visualizar Impressão', 'book-manager'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bm-remove-card')) {
            var userId = e.target.getAttribute('data-user');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { location.reload(); };
            xhr.send('action=bm_toggle_library_card&user_id=' + userId);
        }
        if (e.target.classList.contains('bm-card-toggle')) {
            var userId = e.target.getAttribute('data-user');
            var btn = e.target;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) {
                    btn.textContent = r.action === 'added' ? '🪪 Remover da carteirinha' : '🪪 Adicionar à carteirinha';
                }
            };
            xhr.send('action=bm_toggle_library_card&user_id=' + userId);
        }
    });
    
    var previewCardsBtn = document.getElementById('bm-preview-cards');
    if (previewCardsBtn) {
        previewCardsBtn.addEventListener('click', function() {
            var cart = <?php echo json_encode(array_values($cart)); ?>;
            if (cart.length === 0) { alert('<?php _e("Nenhuma carteirinha selecionada.", "book-manager"); ?>'); return; }
            var url = '<?php echo admin_url("admin-ajax.php"); ?>?action=bm_print_library_cards_bulk&nonce=<?php echo wp_create_nonce("bm_library_cards_bulk_nonce"); ?>&ids=' + cart.join(',');
            window.open(url, '_blank');
        });
    }
    </script>
    <?php
}

function bm_labels_init_session() {
    if (!session_id() && !headers_sent()) session_start();
    if (!isset($_SESSION['bm_labels_cart'])) $_SESSION['bm_labels_cart'] = array();
}
add_action('init', 'bm_labels_init_session');

function bm_ajax_toggle_label() {
    check_ajax_referer('bm_toggle_label_nonce', 'nonce');
    if (!session_id()) session_start();
    if (!isset($_SESSION['bm_labels_cart'])) $_SESSION['bm_labels_cart'] = array();
    
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
    if (!$book_id) wp_die(json_encode(array('success' => false)));
    
    if (in_array($book_id, $_SESSION['bm_labels_cart'])) {
        $_SESSION['bm_labels_cart'] = array_diff($_SESSION['bm_labels_cart'], array($book_id));
        $action = 'removed';
    } else {
        $_SESSION['bm_labels_cart'][] = $book_id;
        $action = 'added';
    }
    
    wp_die(json_encode(array('success' => true, 'action' => $action, 'count' => count($_SESSION['bm_labels_cart']))));
}
add_action('wp_ajax_bm_toggle_label', 'bm_ajax_toggle_label');

function bm_ajax_toggle_library_card() {
    if (!session_id()) session_start();
    if (!isset($_SESSION['bm_library_cards_cart'])) $_SESSION['bm_library_cards_cart'] = array();
    
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if (!$user_id) wp_die(json_encode(array('success' => false)));
    
    if (in_array($user_id, $_SESSION['bm_library_cards_cart'])) {
        $_SESSION['bm_library_cards_cart'] = array_diff($_SESSION['bm_library_cards_cart'], array($user_id));
        $action = 'removed';
    } else {
        $_SESSION['bm_library_cards_cart'][] = $user_id;
        $action = 'added';
    }
    
    wp_die(json_encode(array('success' => true, 'action' => $action, 'count' => count($_SESSION['bm_library_cards_cart']))));
}
add_action('wp_ajax_bm_toggle_library_card', 'bm_ajax_toggle_library_card');

function bm_label_button() {
    if (!is_singular('bm_book')) return;
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    $book_id = get_the_ID();
    if (!session_id()) session_start();
    $in_cart = isset($_SESSION['bm_labels_cart']) && in_array($book_id, $_SESSION['bm_labels_cart']);
    $label = $in_cart ? '➖ ' . __('Remover etiqueta', 'book-manager') : '➕ ' . __('Adicionar etiqueta', 'book-manager');
    $color = $in_cart ? '#dc3545' : '#111';
    ?>
    <div style="margin:10px 0;">
        <button type="button" class="bm-label-toggle" data-book="<?php echo $book_id; ?>" style="padding:6px 12px;background:<?php echo $color; ?>;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;">
            <?php echo $label; ?>
        </button>
    </div>
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bm-label-toggle')) {
            var btn = e.target;
            var bookId = btn.getAttribute('data-book');
            btn.disabled = true;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var r = JSON.parse(xhr.responseText);
                if (r.success) {
                    btn.textContent = r.action === 'added' ? '➖ Remover etiqueta' : '➕ Adicionar etiqueta';
                    btn.style.background = r.action === 'added' ? '#dc3545' : '#111';
                }
                btn.disabled = false;
            };
            xhr.send('action=bm_toggle_label&nonce=<?php echo wp_create_nonce("bm_toggle_label_nonce"); ?>&book_id=' + bookId);
        }
    });
    </script>
    <?php
}

function bm_render_labels_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    if (!session_id()) session_start();
    $cart = isset($_SESSION['bm_labels_cart']) ? $_SESSION['bm_labels_cart'] : array();
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['bm_labels_cart'] = array();
        $cart = array();
        echo '<div class="notice notice-success"><p>' . __('Etiquetas removidas.', 'book-manager') . '</p></div>';
    }
    
    $filter_genre = isset($_GET['filter_genre']) ? intval($_GET['filter_genre']) : 0;
    $filter_discipline = isset($_GET['filter_discipline']) ? intval($_GET['filter_discipline']) : 0;
    $filter_cdu = isset($_GET['filter_cdu']) ? sanitize_text_field($_GET['filter_cdu']) : '';
    $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
    
    $args = array('post_type' => 'bm_book', 'posts_per_page' => 50, 'post_status' => 'publish');
    if ($filter_genre) { $args['tax_query'][] = array('taxonomy' => 'bm_genre', 'field' => 'term_id', 'terms' => $filter_genre); }
    if ($filter_discipline) { $args['tax_query'][] = array('taxonomy' => 'bm_discipline', 'field' => 'term_id', 'terms' => $filter_discipline); }
    if ($filter_search) $args['s'] = $filter_search;
    if ($filter_cdu) { $args['meta_query'][] = array('key' => '_bm_cdu', 'value' => $filter_cdu, 'compare' => 'LIKE'); }
    
    $books = get_posts($args);
    
    if (isset($_POST['add_selected']) && isset($_POST['book_ids'])) {
        foreach ($_POST['book_ids'] as $id) {
            if (!in_array($id, $cart)) $cart[] = intval($id);
        }
        $_SESSION['bm_labels_cart'] = $cart;
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Geração de Etiquetas', 'book-manager'); ?></h1>
        
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:400px;">
                <h2><?php _e('Selecionar Livros', 'book-manager'); ?></h2>
                
                <form method="get" style="margin-bottom:15px;">
                    <input type="hidden" name="post_type" value="bm_book">
                    <input type="hidden" name="page" value="bm_labels">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                        <div><label><?php _e('Buscar', 'book-manager'); ?></label><input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Título ou autor', 'book-manager'); ?>" style="padding:4px 8px;" /></div>
                        <div><label><?php _e('Gênero', 'book-manager'); ?></label><?php wp_dropdown_categories(array('show_option_all' => __('Todos', 'book-manager'), 'taxonomy' => 'bm_genre', 'name' => 'filter_genre', 'selected' => $filter_genre, 'hide_empty' => true)); ?></div>
                        <div><label><?php _e('Disciplina', 'book-manager'); ?></label><?php wp_dropdown_categories(array('show_option_all' => __('Todas', 'book-manager'), 'taxonomy' => 'bm_discipline', 'name' => 'filter_discipline', 'selected' => $filter_discipline, 'hide_empty' => true)); ?></div>
                        <div><label><?php _e('Classif.', 'book-manager'); ?></label><input type="text" name="filter_cdu" value="<?php echo esc_attr($filter_cdu); ?>" style="width:80px;padding:4px 8px;" /></div>
                        <div><button type="submit" class="button"><?php _e('Filtrar', 'book-manager'); ?></button></div>
                    </div>
                </form>
                
                <form method="post">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="bm-select-all" onclick="var cbs=document.querySelectorAll('input[name=\'book_ids[]\']');for(var i=0;i<cbs.length;i++)cbs[i].checked=this.checked;" /></th>
                                <th><?php _e('Título', 'book-manager'); ?></th>
                                <th><?php _e('Autor', 'book-manager'); ?></th>
                                <th><?php _e('Classif.', 'book-manager'); ?></th>
                                <th><?php _e('Ex.', 'book-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): 
                                $author = get_post_meta($book->ID, '_bm_author', true);
                                $cdu = get_post_meta($book->ID, '_bm_cdu', true);
                                $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true)));
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="book_ids[]" value="<?php echo $book->ID; ?>" <?php checked(in_array($book->ID, $cart)); ?> /></td>
                                    <td><?php echo esc_html($book->post_title); ?></td>
                                    <td><?php echo esc_html($author); ?></td>
                                    <td><?php echo esc_html($cdu); ?></td>
                                    <td><?php echo $copies; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:10px;">
                        <button type="submit" name="add_selected" class="button button-primary"><?php _e('Adicionar etiquetas', 'book-manager'); ?></button>
                    </p>
                </form>
            </div>
            
            <div style="flex:0 0 350px;">
                <h2>🖨️ <?php _e('Etiquetas selecionadas', 'book-manager'); ?> (<?php echo count($cart); ?>)</h2>
                
                <?php if (empty($cart)): ?>
                    <p><?php _e('Nenhuma etiqueta selecionada.', 'book-manager'); ?></p>
                <?php else: ?>
                    <ul style="max-height:400px;overflow-y:auto;list-style:none;padding:0;margin:0;">
                        <?php 
                        $cart_books = get_posts(array('post_type' => 'bm_book', 'post__in' => $cart, 'posts_per_page' => -1, 'orderby' => 'post__in'));
                        foreach ($cart_books as $book): 
                            $author = get_post_meta($book->ID, '_bm_author', true);
                            $cdu = get_post_meta($book->ID, '_bm_cdu', true);
                            $cutter = get_post_meta($book->ID, '_bm_cutter', true);
                            $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true)));
                        ?>
                            <li style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #eee;">
                                <button type="button" class="bm-remove-label" data-book="<?php echo $book->ID; ?>" style="background:#dc3545;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:14px;line-height:1;">✕</button>
                                <div style="flex:1;font-size:12px;">
                                    <strong><?php echo esc_html($book->post_title); ?></strong>
                                    <?php if ($author): ?><br><small><?php echo esc_html($author); ?></small><?php endif; ?>
                                    <?php if ($cdu): ?><br><small>Class: <?php echo esc_html($cdu); ?> | Cutter: <?php echo esc_html($cutter); ?></small><?php endif; ?>
                                    <br><small><?php printf(__('%d exemplares', 'book-manager'), $copies); ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="post" style="margin-top:10px;display:flex;gap:10px;">
                        <button type="submit" name="clear_cart" class="button"><?php _e('Limpar etiquetas', 'book-manager'); ?></button>
                        <button type="button" id="bm-preview-labels" class="button button-primary">🖨️ <?php _e('Visualizar Impressão', 'book-manager'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bm-remove-label')) {
            var bookId = e.target.getAttribute('data-book');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { location.reload(); };
            xhr.send('action=bm_toggle_label&book_id=' + bookId);
        }
    });
    
    var previewBtn = document.getElementById('bm-preview-labels');
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            var cart = <?php echo json_encode(array_values($cart)); ?>;
            if (cart.length === 0) { alert('<?php _e("Nenhuma etiqueta selecionada.", "book-manager"); ?>'); return; }
            var url = '<?php echo admin_url("admin-ajax.php"); ?>?action=bm_print_labels&nonce=<?php echo wp_create_nonce("bm_print_labels_nonce"); ?>&ids=' + cart.join(',');
            window.open(url, '_blank');
        });
    }
    </script>
    <?php
}

function bm_ajax_print_labels() {
    if (!session_id()) session_start();
    check_ajax_referer('bm_print_labels_nonce', 'nonce');
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die('Sem permissão.');
    
    $ids = isset($_GET['ids']) ? explode(',', sanitize_text_field($_GET['ids'])) : array();
    if (empty($ids)) wp_die('Nenhum livro selecionado.');
    
    $books = get_posts(array('post_type' => 'bm_book', 'post__in' => $ids, 'posts_per_page' => -1, 'orderby' => 'post__in'));
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php _e('Etiquetas — Visualização', 'book-manager'); ?></title>
        <style>
            @page { size: A4; margin: 1.2cm 0.3cm 0.2cm 0.3cm; }
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
            .labels-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.15cm; padding: 0; }
            .label { border: 1px dashed #ccc; padding: 0.2cm 0.15cm; text-align: center; height: 2.4cm; display: flex; flex-direction: column; justify-content: center; page-break-inside: avoid; }
            .label .author { font-weight: bold; font-size: 12px; text-transform: uppercase; margin-bottom: 2px; }
            .label .title { font-size: 10px; margin-bottom: 3px; }
            .label .cdu { font-weight: bold; font-size: 16px; margin-bottom: 2px; }
            .label .cutter { font-weight: bold; font-size: 16px; margin-bottom: 2px; }
            .label .info { font-size: 9px; color: #666; }
            .label .barcode { font-size: 9px; letter-spacing: 2px; margin-top: 3px; }
            .no-print { text-align: center; margin: 20px; }
            @media print {
                .no-print { display: none; }
                .label { border: none; }
                body { margin: 0; padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="padding:20px;background:#f9f9f9;margin-bottom:20px;">
            <h2><?php _e('Visualização de Etiquetas', 'book-manager'); ?> (<?php echo count($books); ?> livros)</h2>
            <p><?php _e('Pressione Ctrl+P para imprimir. Ajuste as margens para "Mínimo".', 'book-manager'); ?></p>
            <button onclick="window.print()" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px;">🖨️ <?php _e('Imprimir Agora', 'book-manager'); ?></button>
        </div>
        
        <div class="labels-grid">
            <?php foreach ($books as $book): 
                $author = get_post_meta($book->ID, '_bm_author', true);
                $cdu = get_post_meta($book->ID, '_bm_cdu', true);
                $cutter = get_post_meta($book->ID, '_bm_cutter', true);
                $edition = get_post_meta($book->ID, '_bm_edition', true);
                $isbn = get_post_meta($book->ID, '_bm_isbn', true);
                $copies = max(1, intval(get_post_meta($book->ID, '_bm_copies', true)));
                
                $author_formatted = '';
                if ($author) {
                    $parts = explode(' ', trim($author));
                    $author_formatted = count($parts) > 1 ? mb_strtoupper(array_pop($parts)) . ', ' . implode(' ', $parts) : mb_strtoupper($author);
                }
                
                $max_labels = max(1, $copies);
                for ($i = 1; $i <= $max_labels; $i++):
            ?>
                <div class="label">
                    <div class="author"><?php echo esc_html($author_formatted); ?></div>
                    <div class="title"><?php echo esc_html($book->post_title); ?></div>
                    <div class="cdu"><?php echo esc_html($cdu); ?></div>
                    <div class="cutter"><?php echo esc_html($cutter); ?></div>
                    <div class="info">
                        <?php if ($edition) echo esc_html($edition) . ' '; ?>
                        <?php if ($copies > 1) printf(__('Ex. %d/%d', 'book-manager'), $i, $copies); ?>
                    </div>
                    <?php if ($isbn): ?>
                        <div class="barcode">|||<?php echo esc_html($isbn); ?>|||</div>
                    <?php endif; ?>
                </div>
            <?php 
                endfor;
            endforeach; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
add_action('wp_ajax_bm_print_labels', 'bm_ajax_print_labels');

// ==========================================
// FASE 31: SUBPÁGINA DE RELATÓRIOS
// ==========================================
function bm_add_reports_page() {
    add_submenu_page('edit.php?post_type=bm_book', __('Relatórios', 'book-manager'), __('Relatórios', 'book-manager'), 'manage_options', 'bm_reports', 'bm_render_reports_page');
}
add_action('admin_menu', 'bm_add_reports_page');

function bm_render_reports_page() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) return;
    
    // Enqueues condicionais
    wp_enqueue_style('bm-tailwind', plugin_dir_url(__FILE__) . '../assets/css/tailwind.min.css', array(), '1.0');
    wp_enqueue_script('bm-reports-dashboard', plugin_dir_url(__FILE__) . '../assets/js/reports-dashboard.js', array(), '1.0', true);
    wp_localize_script('bm-reports-dashboard', 'bmReports', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('bm_reports_nonce'),
        'serviceNonce' => wp_create_nonce('bm_service_nonce'),
    ));
    
    $type = isset($_GET['bm_report_type']) ? sanitize_text_field($_GET['bm_report_type']) : 'overview';
    $period = isset($_GET['bm_period']) ? sanitize_text_field($_GET['bm_period']) : 'month';
    $date_start = isset($_GET['bm_date_start']) ? sanitize_text_field($_GET['bm_date_start']) : '';
    $date_end = isset($_GET['bm_date_end']) ? sanitize_text_field($_GET['bm_date_end']) : '';
    $subject = isset($_GET['bm_subject']) ? sanitize_text_field($_GET['bm_subject']) : 'all';
    $subject_id = (isset($_GET['bm_subject_id']) && $subject !== 'all') ? intval($_GET['bm_subject_id']) : 0;
    $group = isset($_GET['bm_group']) ? sanitize_text_field($_GET['bm_group']) : '';
    $genre = isset($_GET['bm_genre']) ? sanitize_text_field($_GET['bm_genre']) : '';
    $discipline = isset($_GET['bm_discipline']) ? sanitize_text_field($_GET['bm_discipline']) : '';
    $custom_columns = isset($_GET['bm_custom_columns']) ? array_map('sanitize_text_field', $_GET['bm_custom_columns']) : array('name', 'books_read');
    $custom_sort = isset($_GET['bm_custom_sort']) ? sanitize_text_field($_GET['bm_custom_sort']) : 'name';
    ?>
    <div class="wrap">
        <h1><?php _e('Relatórios', 'book-manager'); ?></h1>
        
        <form id="bm-report-form" class="bg-white border border-gray-200 p-4 rounded-lg mb-6 flex flex-wrap gap-4 items-end">
            <input type="hidden" name="post_type" value="bm_book">
            <input type="hidden" name="page" value="bm_reports">
            
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1"><?php _e('Tipo de Relatório', 'book-manager'); ?></label>
                <select name="bm_report_type" class="w-48 px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="overview" <?php selected($type, 'overview'); ?>><?php _e('Visão Geral', 'book-manager'); ?></option>
                    <option value="student_performance" <?php selected($type, 'student_performance'); ?>><?php _e('Desempenho do Aluno', 'book-manager'); ?></option>
                    <option value="class_reading" <?php selected($type, 'class_reading'); ?>><?php _e('Leitura por Turma', 'book-manager'); ?></option>
                    <option value="active_penalties" <?php selected($type, 'active_penalties'); ?>><?php _e('Multas Ativas', 'book-manager'); ?></option>
                    <option value="genre_ranking" <?php selected($type, 'genre_ranking'); ?>><?php _e('Ranking por Gênero', 'book-manager'); ?></option>
                    <option value="top_books" <?php selected($type, 'top_books'); ?>><?php _e('Livros Mais Emprestados', 'book-manager'); ?></option>
                    <option value="reading_trend" <?php selected($type, 'reading_trend'); ?>><?php _e('Tendência de Leitura', 'book-manager'); ?></option>
                    <option value="custom" <?php selected($type, 'custom'); ?>><?php _e('Relatório Configurável', 'book-manager'); ?></option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1"><?php _e('Período', 'book-manager'); ?></label>
                <select name="bm_period" class="w-36 px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="week" <?php selected($period, 'week'); ?>><?php _e('Última Semana', 'book-manager'); ?></option>
                    <option value="month" <?php selected($period, 'month'); ?>><?php _e('Último Mês', 'book-manager'); ?></option>
                    <option value="bimester" <?php selected($period, 'bimester'); ?>><?php _e('Último Bimestre', 'book-manager'); ?></option>
                    <option value="semester" <?php selected($period, 'semester'); ?>><?php _e('Último Semestre', 'book-manager'); ?></option>
                    <option value="year" <?php selected($period, 'year'); ?>><?php _e('Último Ano', 'book-manager'); ?></option>
                    <option value="custom" <?php selected($period, 'custom'); ?>><?php _e('Personalizado', 'book-manager'); ?></option>
                </select>
            </div>

            <div id="bm-custom-dates" class="<?php echo $period === 'custom' ? 'flex' : 'hidden'; ?> gap-2 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1"><?php _e('De', 'book-manager'); ?></label>
                    <input type="date" name="bm_date_start" value="<?php echo esc_attr($date_start); ?>" class="w-36 px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1"><?php _e('Até', 'book-manager'); ?></label>
                    <input type="date" name="bm_date_end" value="<?php echo esc_attr($date_end); ?>" class="w-36 px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1"><?php _e('Sujeito', 'book-manager'); ?></label>
                <select name="bm_subject" class="w-36 px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="all" <?php selected($subject, 'all'); ?>><?php _e('Todos', 'book-manager'); ?></option>
                    <option value="student" <?php selected($subject, 'student'); ?>><?php _e('Aluno Específico', 'book-manager'); ?></option>
                    <option value="class" <?php selected($subject, 'class'); ?>><?php _e('Turma', 'book-manager'); ?></option>
                </select>
            </div>

            <div id="bm-subject-options">
                <div id="bm-student-select" class="<?php echo $subject === 'student' ? '' : 'hidden'; ?>">
                    <label class="block text-xs font-bold text-gray-600 mb-1"><?php _e('Aluno', 'book-manager'); ?></label>
                    <input type="text" id="bm-student-search-input" placeholder="<?php _e('Digite o nome...', 'book-manager'); ?>" class="w-48 px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <div id="bm-student-search-results" class="max-h-32 overflow-y-auto mt-1"></div>
                    <input type="hidden" name="bm_subject_id" id="bm-subject-id" value="<?php echo $subject_id ?: ''; ?>">
                </div>
                <div id="bm-class-select" class="<?php echo $subject === 'class' ? '' : 'hidden'; ?>">
                    <label class="block text-xs font-bold text-gray-600 mb-1"><?php _e('Turma', 'book-manager'); ?></label>
                    <input type="text" name="bm_group" value="<?php echo esc_attr($group); ?>" placeholder="Ex: 1º Ano" class="w-28 px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
            </div>

            <div id="bm-custom-options" class="<?php echo $type === 'custom' ? '' : 'hidden'; ?> w-full mt-2 p-3 bg-gray-50 rounded-md">
                <label class="text-sm font-bold text-gray-700"><?php _e('Colunas:', 'book-manager'); ?></label>
                <div class="flex flex-wrap gap-4 mt-2">
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="name" <?php checked(in_array('name', $custom_columns)); ?>> <?php _e('Nome', 'book-manager'); ?></label>
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="group" <?php checked(in_array('group', $custom_columns)); ?>> <?php _e('Turma', 'book-manager'); ?></label>
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="books_read" <?php checked(in_array('books_read', $custom_columns)); ?>> <?php _e('Livros Lidos', 'book-manager'); ?></label>
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="reviews" <?php checked(in_array('reviews', $custom_columns)); ?>> <?php _e('Resenhas', 'book-manager'); ?></label>
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="videos" <?php checked(in_array('videos', $custom_columns)); ?>> <?php _e('Vídeos', 'book-manager'); ?></label>
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="xp" <?php checked(in_array('xp', $custom_columns)); ?>> <?php _e('XP', 'book-manager'); ?></label>
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="badges" <?php checked(in_array('badges', $custom_columns)); ?>> <?php _e('Medalhas', 'book-manager'); ?></label>
                    <label class="flex items-center gap-1 text-sm text-gray-700"><input type="checkbox" name="bm_custom_columns[]" value="penalties" <?php checked(in_array('penalties', $custom_columns)); ?>> <?php _e('Multas', 'book-manager'); ?></label>
                </div>
                <div class="mt-3">
                    <label class="text-sm font-bold text-gray-700"><?php _e('Ordenar por:', 'book-manager'); ?></label>
                    <select name="bm_custom_sort" class="ml-2 px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="name" <?php selected($custom_sort, 'name'); ?>><?php _e('Nome', 'book-manager'); ?></option>
                        <option value="xp" <?php selected($custom_sort, 'xp'); ?>><?php _e('XP', 'book-manager'); ?></option>
                        <option value="books_read" <?php selected($custom_sort, 'books_read'); ?>><?php _e('Livros Lidos', 'book-manager'); ?></option>
                    </select>
                </div>
            </div>

            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium text-sm transition-colors"><?php _e('Gerar Relatório', 'book-manager'); ?></button>
            <button type="button" id="bm-export-pdf" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-medium text-sm transition-colors"><?php _e('Exportar PDF', 'book-manager'); ?></button>
        </form>

        <div id="bm-report-result" class="space-y-6">
            <div id="bm-dashboard" class="hidden"></div>
            <div id="bm-welcome" class="text-center py-12 text-gray-400">
                <?php _e('Selecione os filtros e clique em Gerar Relatório', 'book-manager'); ?>
            </div>
            <div id="bm-loading" class="hidden text-center py-8">
                <span class="animate-pulse text-gray-500"><?php _e('Carregando...', 'book-manager'); ?></span>
            </div>
            <div id="bm-empty" class="hidden text-center py-8">
                <svg class="w-12 h-12 mx-auto text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 8l9-5 9 5v8l-9 5-9-5V8z" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M3 8l9 5 9-5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M12 13v8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <p class="mt-2 text-gray-500"><?php _e('Nenhum dado encontrado para este período.', 'book-manager'); ?></p>
            </div>

            <div data-section="report-title" class="hidden">
                <h2 class="text-xl font-bold text-gray-900"></h2>
                <p class="text-sm text-gray-500"></p>
            </div>

            <div data-section="kpi-cards" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-blue-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between"><div><p class="text-xs font-medium text-gray-500 uppercase tracking-wider"></p><p class="text-2xl font-bold text-gray-900 mt-1"></p></div><div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle></svg></div></div>
                    <div class="mt-3 flex items-center gap-1"><span class="text-xs font-medium text-green-600"></span><span class="text-xs text-gray-400"></span></div>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-emerald-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between"><div><p class="text-xs font-medium text-gray-500 uppercase tracking-wider"></p><p class="text-2xl font-bold text-gray-900 mt-1"></p></div><div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center"><svg class="w-5 h-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle></svg></div></div>
                    <div class="mt-3 flex items-center gap-1"><span class="text-xs font-medium text-green-600"></span><span class="text-xs text-gray-400"></span></div>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-red-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between"><div><p class="text-xs font-medium text-gray-500 uppercase tracking-wider"></p><p class="text-2xl font-bold text-gray-900 mt-1"></p></div><div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center"><svg class="w-5 h-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle></svg></div></div>
                    <div class="mt-3 flex items-center gap-1"><span class="text-xs font-medium text-green-600"></span><span class="text-xs text-gray-400"></span></div>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-amber-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between"><div><p class="text-xs font-medium text-gray-500 uppercase tracking-wider"></p><p class="text-2xl font-bold text-gray-900 mt-1"></p></div><div class="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center"><svg class="w-5 h-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle></svg></div></div>
                    <div class="mt-3 flex items-center gap-1"><span class="text-xs font-medium text-green-600"></span><span class="text-xs text-gray-400"></span></div>
                </div>
            </div>

            <div data-section="bar-chart" data-component="bm-chart" class="hidden bg-white rounded-xl p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-4"></h3>
                <div id="bm-chart-container" class="space-y-3 max-w-xl"></div>
            </div>
            <div data-section="pie-chart" class="hidden bg-white rounded-xl p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-4"></h3>
                <div class="flex flex-wrap items-start gap-6">
                    <div id="bm-pie-container" class="w-48 h-48"></div>
                    <div id="bm-pie-legend" class="flex-1 min-w-[200px] space-y-2"></div>
                </div>
            </div>
            <div data-section="line-chart" class="hidden bg-white rounded-xl p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-4"></h3>
                <div id="bm-line-container" class="w-full h-64"></div>
            </div>
            <div data-section="top-readers" class="hidden">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Top 3 Leitores</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div id="bm-reader-gold" class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-yellow-400">
                        <div class="text-center">
                            <span class="text-3xl">🥇</span>
                            <p class="text-lg font-bold text-gray-900 mt-1"></p>
                            <p class="text-sm text-gray-500"></p>
                            <div class="mt-2 bg-gray-100 rounded-full h-3 overflow-hidden">
                                <div class="bg-yellow-400 h-full rounded-full" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                    <div id="bm-reader-silver" class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-gray-300">
                        <div class="text-center">
                            <span class="text-3xl">🥈</span>
                            <p class="text-lg font-bold text-gray-900 mt-1"></p>
                            <p class="text-sm text-gray-500"></p>
                            <div class="mt-2 bg-gray-100 rounded-full h-3 overflow-hidden">
                                <div class="bg-gray-400 h-full rounded-full" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                    <div id="bm-reader-bronze" class="bg-white rounded-xl p-5 shadow-sm border-l-4 border-orange-400">
                        <div class="text-center">
                            <span class="text-3xl">🥉</span>
                            <p class="text-lg font-bold text-gray-900 mt-1"></p>
                            <p class="text-sm text-gray-500"></p>
                            <div class="mt-2 bg-gray-100 rounded-full h-3 overflow-hidden">
                                <div class="bg-orange-400 h-full rounded-full" style="width:0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div data-section="inactive-alerts" class="hidden bg-red-50 rounded-xl p-5 shadow-sm border-l-4 border-red-500">
                <h3 class="text-base font-semibold text-red-800 mb-2">⚠️ Alunos sem leitura no período</h3>
                <div id="bm-inactive-list" class="flex flex-wrap gap-2"></div>
            </div>

            <div data-section="data-table" class="hidden bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200"><tr></tr></thead>
                    <tbody class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>
    </div>
        
    <?php
}

// ==========================================
// FASE 32: PÁGINA DE DETALHES DO EMPRÉSTIMO
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
            $msg = '<div class="notice notice-error"><p>' . esc_html($result['error']) . '</p></div>';
        } else {
            $msg = '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
    
    if (!$book_id || !$user_id) {
        echo '<div class="wrap"><p>' . __('Empréstimo não encontrado.', 'book-manager') . '</p></div>';
        return;
    }
    
    $book = get_post($book_id);
    $student = get_userdata($user_id);
    
    if (!$book || !$student) {
        echo '<div class="wrap"><p>' . __('Empréstimo não encontrado.', 'book-manager') . '</p></div>';
        return;
    }
    
    ?>
    <div class="wrap" style="max-width:800px;">
        <h1><?php _e('Detalhes do Empréstimo', 'book-manager'); ?></h1>
        <p><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_service_desk'); ?>">← <?php _e('Voltar para Empréstimos', 'book-manager'); ?></a></p>
        
        <?php
        $reservations = get_post_meta($book_id, '_bm_reservations', true) ?: array();
        $loan_data = null;
        if (!empty($loan_id)) {
            foreach ($reservations as $r) {
                if (isset($r['loan_id']) && $r['loan_id'] === $loan_id) {
                    $loan_data = $r;
                    break;
                }
            }
        }
        if (!$loan_data) {
            foreach ($reservations as $r) {
                if ($r['user_id'] == $user_id) {
                    $loan_data = $r;
                    break;
                }
            }
        }
        if (!$loan_data) {
            $loan_data = array('status' => 'unknown', 'user_id' => $user_id, 'date' => '', 'loan_date' => '', 'due_date' => '', 'returned_date' => '', 'loan_id' => '');
        }
        ?>
        
        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:15px;">
            <div style="flex:0 0 150px;">
                <a href="<?php echo admin_url('post.php?post=' . $book_id . '&action=edit'); ?>">
                    <?php if (has_post_thumbnail($book_id)): ?>
                        <?php echo get_the_post_thumbnail($book_id, 'medium', array('style' => 'width:100%;height:auto;border-radius:4px;')); ?>
                    <?php else: ?>
                        <div style="width:100%;height:200px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;border-radius:4px;"><?php _e('Sem capa', 'book-manager'); ?></div>
                    <?php endif; ?>
                </a>
            </div>
            <div style="flex:1;min-width:300px;">
                <h2 style="margin:0;"><a href="<?php echo admin_url('post.php?post=' . $book_id . '&action=edit'); ?>"><?php echo esc_html($book->post_title); ?></a></h2>
                <?php $author = get_post_meta($book_id, '_bm_author', true); ?>
                <?php if ($author): ?><p><strong><?php _e('Autor:', 'book-manager'); ?></strong> <?php echo esc_html($author); ?></p><?php endif; ?>
                
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ($loan_data['status'] === 'waiting'): ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <input type="number" name="loan_days" value="14" min="0" max="60" style="width:50px;padding:4px 6px;font-size:13px;text-align:center;" />
                            <input type="hidden" name="bm_loan_action" value="confirm">
                            <button type="submit" class="button button-small" style="background:#0073aa;color:#fff;border-color:#0073aa;">✅ <?php _e('Emprestar', 'book-manager'); ?></button>
                        </form>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <input type="hidden" name="bm_loan_action" value="reject">
                            <button type="submit" class="button button-small" style="background:#dc3545;color:#fff;border-color:#dc3545;">❌ <?php _e('Rejeitar', 'book-manager'); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($loan_data['status'] === 'active'): ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <input type="hidden" name="bm_loan_action" value="return">
                            <button type="submit" class="button button-small" style="background:#46b450;color:#fff;border-color:#46b450;">📥 <?php _e('Devolver', 'book-manager'); ?></button>
                        </form>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <input type="hidden" name="bm_loan_action" value="renew">
                            <button type="submit" class="button button-small" style="background:#ffc107;color:#111;border-color:#ffc107;">🔄 <?php _e('Renovar +7', 'book-manager'); ?></button>
                        </form>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                            <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <input type="hidden" name="bm_loan_action" value="undo">
                            <button type="submit" class="button button-small" style="background:#dc3545;color:#fff;border-color:#dc3545;">↩️ <?php _e('Desfazer', 'book-manager'); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($loan_data['status'] === 'returned' || $loan_data['status'] === 'cancelled' || $loan_data['status'] === 'rejected'): ?>
                        <button type="button" class="button button-small" id="bm-archive-btn-top" data-book="<?php echo $book_id; ?>" data-user="<?php echo $user_id; ?>" data-loan="<?php echo esc_attr($loan_id); ?>">🗄️ <?php _e('Arquivar', 'book-manager'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">👤 <?php _e('Aluno', 'book-manager'); ?></h3>
            <p><strong><a href="<?php echo admin_url('edit.php?post_type=bm_book&page=bm_student_detail&student_id=' . $user_id); ?>"><?php echo esc_html($student->display_name); ?></a></strong></p>
            <?php $student_group = get_user_meta($user_id, '_bm_user_' . sanitize_key('Turma'), true); ?>
            <?php if ($student_group): ?><p><strong><?php _e('Turma:', 'book-manager'); ?></strong> <?php echo esc_html($student_group); ?></p><?php endif; ?>
            <?php $student_phone = get_user_meta($user_id, '_bm_user_' . sanitize_key('Telefone'), true); ?>
            <?php if ($student_phone): ?>
                <p><?php echo bm_whatsapp_button($student_phone, '', '📱 WhatsApp'); ?></p>
            <?php endif; ?>
        </div>
                
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">📅 <?php _e('Linha do Tempo', 'book-manager'); ?></h3>
            <table class="widefat fixed" style="border:none;">
                <tr><td style="width:200px;padding:5px;border:none;"><?php _e('Data da reserva:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['date']) ? date('d/m/Y H:i', strtotime($loan_data['date'])) : '—'; ?></strong></td></tr>
                <tr><td style="padding:5px;border:none;"><?php _e('Data do empréstimo:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['loan_date']) ? date('d/m/Y H:i', strtotime($loan_data['loan_date'])) : '—'; ?></strong></td></tr>
                <tr><td style="padding:5px;border:none;"><?php _e('Devolução prevista:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['due_date']) ? date('d/m/Y', strtotime($loan_data['due_date'])) : '—'; ?></strong></td></tr>
                <tr><td style="padding:5px;border:none;"><?php _e('Devolução real:', 'book-manager'); ?></td><td style="padding:5px;border:none;"><strong><?php echo isset($loan_data['returned_date']) ? date('d/m/Y H:i', strtotime($loan_data['returned_date'])) : '—'; ?></strong></td></tr>
            </table>
        </div>
        
        <?php
        $days_late = 0;
        if (isset($loan_data['due_date']) && isset($loan_data['returned_date'])) {
            $due_time = strtotime($loan_data['due_date']);
            $return_time = strtotime($loan_data['returned_date']);
            if ($return_time > $due_time) {
                $days_late = ceil(($return_time - $due_time) / DAY_IN_SECONDS);
            }
        }
        $penalties = get_user_meta($user_id, '_bm_penalties', true) ?: array();
        $penalty_info = null;
        foreach (array_reverse($penalties) as $p) {
            if (isset($p['note']) && strpos($p['note'], (string)$book_id) !== false) {
                $penalty_info = $p;
                break;
            }
        }
        ?>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">⚠️ <?php _e('Atraso e Multa', 'book-manager'); ?></h3>
            <?php if ($days_late > 0): ?>
                <p><strong><?php _e('Dias de atraso:', 'book-manager'); ?></strong> <span style="color:#dc3545;"><?php echo $days_late; ?></span></p>
            <?php else: ?>
                <p><?php _e('Sem atraso.', 'book-manager'); ?></p>
            <?php endif; ?>
            <?php if ($penalty_info): ?>
                <?php $type_label = $penalty_info['type'] === 'warning' ? __('Advertência', 'book-manager') : ($penalty_info['type'] === 'suspension' ? __('Suspensão', 'book-manager') : __('Multa', 'book-manager')); ?>
                <p><strong><?php _e('Multa aplicada:', 'book-manager'); ?></strong> <?php echo $type_label; ?> — <?php echo esc_html($penalty_info['note']); ?></p>
            <?php else: ?>
                <p><?php _e('Nenhuma multa aplicada.', 'book-manager'); ?></p>
            <?php endif; ?>
            
            <?php
            $return_log = get_post_meta($book_id, '_bm_return_log', true) ?: array();
            $condition_info = null;
            foreach (array_reverse($return_log) as $log) {
                if ($log['user_id'] == $user_id) {
                    $condition_info = $log;
                    break;
                }
            }
            ?>
            <?php if ($condition_info): ?>
                <p><strong><?php _e('Condição da devolução:', 'book-manager'); ?></strong> 
                <?php echo $condition_info['condition'] === 'good' ? '✅ ' . __('Bom', 'book-manager') : ($condition_info['condition'] === 'acceptable' ? '⚠️ ' . __('Aceitável', 'book-manager') : '❌ ' . __('Danificado', 'book-manager')); ?>
                </p>
                <?php if (!empty($condition_info['note'])): ?>
                    <p><strong><?php _e('Observação:', 'book-manager'); ?></strong> <?php echo esc_html($condition_info['note']); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php
        $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
        $student_review = null;
        foreach ($reading_log as $log) {
            if ($log['book_id'] == $book_id) {
                $student_review = $log;
                break;
            }
        }
        ?>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">📝 <?php _e('Resenha do Aluno', 'book-manager'); ?></h3>
            <?php if ($student_review && !empty($student_review['review'])): ?>
                <p><?php echo esc_html($student_review['review']); ?></p>
                <?php if ($student_review['rating'] > 0): ?>
                    <p style="color:#ffc107;"><?php echo str_repeat('★', $student_review['rating']) . str_repeat('☆', 5 - $student_review['rating']); ?></p>
                <?php endif; ?>
                <small style="color:#999;"><?php echo date('d/m/Y', strtotime($student_review['date'])); ?> — <?php echo $student_review['status'] === 'approved' ? '✅ ' . __('Aprovada', 'book-manager') : '⏳ ' . __('Pendente', 'book-manager'); ?></small>
            <?php else: ?>
                <p style="color:#999;"><?php _e('O aluno ainda não fez resenha deste livro.', 'book-manager'); ?></p>
            <?php endif; ?>
        </div>
      
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">🎬 <?php _e('Vídeo-Resenha', 'book-manager'); ?></h3>
            <?php if ($student_review && !empty($student_review['video_url'])): ?>
                <?php
                $embed_url = '';
                if (strpos($student_review['video_url'], 'youtube.com') !== false || strpos($student_review['video_url'], 'youtu.be') !== false) {
                    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $student_review['video_url'], $matches);
                    if (!empty($matches[1])) $embed_url = 'https://www.youtube.com/embed/' . $matches[1];
                } elseif (strpos($student_review['video_url'], 'tiktok.com') !== false) {
                    preg_match('/video\/(\d+)/', $student_review['video_url'], $matches);
                    if (!empty($matches[1])) $embed_url = 'https://www.tiktok.com/embed/v2/' . $matches[1];
                }
                ?>
                <?php if ($embed_url): ?>
                    <iframe src="<?php echo esc_url($embed_url); ?>" style="width:100%;aspect-ratio:16/9;border:none;border-radius:4px;" allowfullscreen></iframe>
                <?php else: ?>
                    <p><a href="<?php echo esc_url($student_review['video_url']); ?>" target="_blank">🔗 <?php _e('Ver vídeo-resenha', 'book-manager'); ?></a></p>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:#999;"><?php _e('O aluno ainda não fez vídeo-resenha deste livro.', 'book-manager'); ?></p>
            <?php endif; ?>
        </div>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">💬 <?php _e('Mensagens WhatsApp', 'book-manager'); ?></h3>
            <?php $whatsapp_count = isset($loan_data['loan_id']) ? bm_get_whatsapp_count($loan_data['loan_id']) : 0; ?>
            <p><strong><?php _e('Mensagens enviadas:', 'book-manager'); ?></strong> <?php echo $whatsapp_count; ?></p>
        </div>
        
        <?php
        $confirmed_by = isset($loan_data['reserved_by']) ? get_userdata($loan_data['reserved_by']) : null;
        $received_by = null;
        if (isset($loan_data['returned_date'])) {
            $audit_log = get_post_meta($book_id, '_bm_audit_log', true) ?: array();
            foreach (array_reverse($audit_log) as $entry) {
                if (strpos($entry['action'], 'Devolvido pelo usuário #' . $user_id) !== false) {
                    $received_by = get_user_by('login', $entry['user']);
                    break;
                }
            }
        }
        ?>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">👤 <?php _e('Gestores', 'book-manager'); ?></h3>
            <?php if ($confirmed_by): ?>
                <p><strong><?php _e('Empréstimo confirmado por:', 'book-manager'); ?></strong> <?php echo esc_html($confirmed_by->display_name); ?></p>
            <?php endif; ?>
            <?php if ($received_by): ?>
                <p><strong><?php _e('Devolução recebida por:', 'book-manager'); ?></strong> <?php echo esc_html($received_by->display_name); ?></p>
            <?php elseif (isset($loan_data['returned_date'])): ?>
                <p><strong><?php _e('Devolução recebida por:', 'book-manager'); ?></strong> <?php _e('Sistema', 'book-manager'); ?></p>
            <?php endif; ?>
        </div>
        
        <?php
        $queue = array();
        foreach ($reservations as $r) {
            if ($r['status'] === 'waiting') {
                $queue_user = get_userdata($r['user_id']);
                $queue[] = $queue_user ? $queue_user->display_name : '#' . $r['user_id'];
            }
        }
        ?>
        
        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">📋 <?php _e('Fila de Espera', 'book-manager'); ?></h3>
            <?php if (!empty($queue)): ?>
                <p><?php echo count($queue); ?> <?php _e('aluno(s) aguardando:', 'book-manager'); ?></p>
                <ol style="margin:5px 0;padding-left:20px;">
                    <?php foreach ($queue as $q_name): ?>
                        <li><?php echo esc_html($q_name); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p style="color:#999;"><?php _e('Nenhum aluno na fila de espera.', 'book-manager'); ?></p>
            <?php endif; ?>
        </div>
        
        <?php
        $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
        $other_overdue = array();
        foreach ($loan_history as $loan) {
            if ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < time() && $loan['book_id'] != $book_id) {
                $other_book = get_post($loan['book_id']);
                $other_overdue[] = array(
                    'title' => $other_book ? $other_book->post_title : __('Livro #', 'book-manager') . $loan['book_id'],
                    'due_date' => date('d/m/Y', strtotime($loan['due_date'])),
                );
            }
        }
        ?>
        
        <div style="background:#fff3f3;padding:15px;border-radius:6px;margin-top:15px;">
            <h3 style="margin:0 0 10px 0;">🔴 <?php _e('Outros Livros em Atraso', 'book-manager'); ?></h3>
            <?php if (!empty($other_overdue)): ?>
                <?php foreach ($other_overdue as $overdue): ?>
                    <p><strong><?php echo esc_html($overdue['title']); ?></strong> — <?php printf(__('Devolução: %s', 'book-manager'), $overdue['due_date']); ?></p>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:#999;"><?php _e('Nenhum outro livro em atraso.', 'book-manager'); ?></p>
            <?php endif; ?>
        </div>
                
        <div style="margin-top:20px;display:flex;gap:10px;border-top:1px solid #ddd;padding-top:15px;">
            <?php if ($loan_data['status'] === 'waiting'): ?>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="number" name="loan_days" value="14" min="0" max="60" style="width:60px;padding:4px 8px;font-size:14px;text-align:center;" />
                    <input type="hidden" name="bm_loan_action" value="confirm">
                    <button type="submit" class="button" style="background:#0073aa;color:#fff;border-color:#0073aa;">✅ <?php _e('Confirmar Empréstimo', 'book-manager'); ?></button>
                </form>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="bm_loan_action" value="reject">
                    <button type="submit" class="button" style="background:#dc3545;color:#fff;border-color:#dc3545;">❌ <?php _e('Rejeitar', 'book-manager'); ?></button>
                </form>
            <?php endif; ?>
            <?php if ($loan_data['status'] === 'active'): ?>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="bm_loan_action" value="return">
                    <button type="submit" class="button" style="background:#46b450;color:#fff;border-color:#46b450;">📥 <?php _e('Devolver', 'book-manager'); ?></button>
                </form>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="bm_loan_action" value="renew">
                    <button type="submit" class="button" style="background:#ffc107;color:#111;border-color:#ffc107;">🔄 <?php _e('Renovar +7 dias', 'book-manager'); ?></button>
                </form>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('bm_loan_action', 'bm_loan_nonce'); ?>
                    <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="bm_loan_action" value="undo">
                    <button type="submit" class="button" style="background:#dc3545;color:#fff;border-color:#dc3545;">↩️ <?php _e('Desfazer', 'book-manager'); ?></button>
                </form>
            <?php endif; ?>

            <script>
            var bmArchiveNonce = '<?php echo wp_create_nonce("bm_service_nonce"); ?>';
            document.getElementById('bm-archive-btn-top')?.addEventListener('click', function() {
                if (!confirm('<?php _e('Arquivar este registro?', 'book-manager'); ?>')) return;
                var btn = this;
                btn.disabled = true;
                btn.textContent = '...';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    var r = JSON.parse(xhr.responseText);
                    if (r.success) {
                        btn.textContent = '✅ <?php _e('Arquivado', 'book-manager'); ?>';
                        btn.style.background = '#6c757d';
                    } else {
                        alert(r.message || 'Erro');
                        btn.disabled = false;
                        btn.textContent = '🗄️ <?php _e('Arquivar', 'book-manager'); ?>';
                    }
                };
                xhr.send('action=bm_archive_loan&book_id=' + btn.getAttribute('data-book') + '&loan_id=' + btn.getAttribute('data-loan') + '&nonce=' + bmArchiveNonce);
            });
            </script>
            <script>
            document.getElementById('bm-archive-btn')?.addEventListener('click', function() {
                if (!confirm('<?php _e('Arquivar este registro?', 'book-manager'); ?>')) return;
                var btn = this;
                btn.disabled = true;
                btn.textContent = '...';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    var r = JSON.parse(xhr.responseText);
                    if (r.success) {
                        btn.textContent = '✅ <?php _e('Arquivado', 'book-manager'); ?>';
                        btn.style.background = '#6c757d';
                    } else {
                        alert(r.message || 'Erro');
                        btn.disabled = false;
                        btn.textContent = '🗄️ <?php _e('Arquivar', 'book-manager'); ?>';
                    }
                };
                xhr.send('action=bm_archive_loan&book_id=' + btn.getAttribute('data-book') + '&loan_id=' + btn.getAttribute('data-loan') + '&nonce=' + bmArchiveNonce);
            });
            </script>
            <?php if ($loan_data['status'] === 'returned' || $loan_data['status'] === 'cancelled' || $loan_data['status'] === 'rejected'): ?>
                <button type="button" class="button" id="bm-archive-btn-top" data-book="<?php echo $book_id; ?>" data-user="<?php echo $user_id; ?>" data-loan="<?php echo esc_attr($loan_id); ?>">🗄️ <?php _e('Arquivar', 'book-manager'); ?></button>
            <?php endif; ?>
        </div>

    </div>
    <?php
}