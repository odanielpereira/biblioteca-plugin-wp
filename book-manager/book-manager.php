<?php
/**
 * Plugin Name:       Gestão de Livros
 * Plugin URI:        https://github.com/odanielpereira/biblioteca-plugin-wp
 * Description:       Gerenciador de livros para o tema Biblioteca.
 * Version:           8.1.1
 * Author:            Daniel Pereira
 * Author URI:        https://odanielpereira.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       book-manager
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// ==========================================
// MÓDULOS DO PLUGIN
// ==========================================
require_once plugin_dir_path(__FILE__) . 'includes/admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/frontend.php';
require_once plugin_dir_path(__FILE__) . 'includes/users.php';
require_once plugin_dir_path(__FILE__) . 'includes/reports.php';

// ==========================================
// FASE 1: CUSTOM POST TYPE
// ==========================================
function bm_register_book_cpt() {
    $labels = array(
        'name'               => 'Livros',
        'all_items'          => 'Todos os Livros',
        'singular_name'      => 'Livro',
        'menu_name'          => 'Biblioteca',
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
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'livros' ),
        'show_in_rest'       => false,
        'exclude_from_search'=> false,
        'capability_type'    => 'bm_book',
        'map_meta_cap'       => true,
        'supports'           => array( 'title', 'thumbnail' ),
        'delete_with_user'   => false,
        'menu_icon'          => 'dashicons-book',
        'hierarchical'       => false,
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

// ==========================================
// FASE 1/5: CAPABILITIES E CICLO DE VIDA
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

function bm_register_roles() {
    add_role('bm_student', __('Aluno', 'book-manager'), array(
        'read' => true,
        'read_bm_book' => true,
        'read_private_bm_books' => true,
    ));

    add_role('bm_teacher', __('Professor', 'book-manager'), array(
        'read' => true,
        'read_bm_book' => true,
        'read_private_bm_books' => true,
        'edit_bm_book' => true,
    ));

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

    
}

function bm_remove_roles() {
    remove_role('bm_student');
    remove_role('bm_teacher');
    remove_role('bm_librarian');
}

// FASE 12G: Pré-instalar campos dinâmicos padrão para alunos
function bm_install_default_user_fields() {
    $existing = get_option('bm_user_dynamic_fields', array());
    if (!is_array($existing)) $existing = array();
    
    $defaults = array(
        'Nome completo' => array('type' => 'text', 'locked' => true),
        'E-mail' => array('type' => 'email', 'locked' => true),
        'Telefone' => array('type' => 'text', 'locked' => true),
        'Série/Ano' => array('type' => 'text', 'locked' => false),
        'Turno' => array('type' => 'text', 'locked' => false),
        'Turma' => array('type' => 'text', 'locked' => false),
    );
    
    // Remove duplicados antigos (case-insensitive)
    $default_keys_lower = array_map('mb_strtolower', array_keys($defaults));
    foreach ($existing as $key => $info) {
        if (in_array(mb_strtolower($key), $default_keys_lower) && !isset($defaults[$key])) {
            unset($existing[$key]);
        }
    }
    
    // Garante que os defaults existam com os valores corretos
    foreach ($defaults as $name => $info) {
        $existing[$name] = $info;
    }
    
    update_option('bm_user_dynamic_fields', $existing);
}


// FASE 12E-T4: Limpar roles sujas na ativação
function bm_clean_dirty_roles() {
    $dirty_roles = array(
        'gestor_biblioteca' => 'bm_librarian',
        'gestor da biblioteca' => 'bm_librarian',
        'professor' => 'bm_teacher',
        'aluno' => 'bm_student',
    );
    
    foreach ($dirty_roles as $dirty => $clean) {
        $users = get_users(array('role' => $dirty, 'number' => -1));
        if (!empty($users)) {
            foreach ($users as $user) {
                $user_obj = new WP_User($user->ID);
                $user_obj->set_role($clean);
            }
        }
        remove_role($dirty);
    }
}

function bm_plugin_activation() {
    bm_register_book_cpt();
    bm_register_taxonomies();
    bm_add_admin_caps();
    bm_register_roles();
    bm_clean_dirty_roles();
    bm_install_default_user_fields();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bm_plugin_activation');
function bm_plugin_deactivation() { flush_rewrite_rules(); }
register_deactivation_hook(__FILE__, 'bm_plugin_deactivation');


// ==========================================
// FASE 15: CACHE DE QUERIES (TRANSIENTS)
// ==========================================
function bm_get_cached($key) {
    return get_transient('bm_cache_' . $key);
}

function bm_set_cached($key, $data, $expiry = 300) {
    set_transient('bm_cache_' . $key, $data, $expiry);
}

// FASE 12E: Renomear submenu "Biblioteca" para "Livros"
function bm_rename_first_submenu() {
    global $submenu;
    if (isset($submenu['edit.php?post_type=bm_book'])) {
        foreach ($submenu['edit.php?post_type=bm_book'] as $key => $item) {
            if ($item[2] === 'edit.php?post_type=bm_book') {
                $submenu['edit.php?post_type=bm_book'][$key][0] = 'Livros';
            }
        }
    }
}
add_action('admin_menu', 'bm_rename_first_submenu', 999);

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