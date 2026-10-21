<?php
/**
 * Plugin Name:       Gestão de Livros
 * Plugin URI:        https://github.com/odanielpereira/biblioteca-plugin-wp
 * Description:       Gerenciador de livros para o tema Biblioteca.
 * Version:           13.0.0
 * Author:            Daniel Pereira
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       book-manager
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// ==========================================
// MODULE LOADING
// ==========================================
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-csv.php';
require_once plugin_dir_path(__FILE__) . 'includes/users-circulacao.php';
require_once plugin_dir_path(__FILE__) . 'includes/users-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'includes/users-gamificacao.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-service.php';
require_once plugin_dir_path(__FILE__) . 'includes/frontend.php';
require_once plugin_dir_path(__FILE__) . 'includes/reports.php';

// ==========================================
// ASSETS: ENQUEUE CSS AND JS
// ==========================================
function bm_enqueue_assets() {
    wp_enqueue_style('bm-book-manager', plugin_dir_url(__FILE__) . 'assets/css/book-manager.css', array(), '12.0.0');
    wp_enqueue_script('bm-book-manager', plugin_dir_url(__FILE__) . 'assets/js/book-manager.js', array('jquery'), '12.0.0', true);
}
add_action('wp_enqueue_scripts', 'bm_enqueue_assets');
add_action('admin_enqueue_scripts', 'bm_enqueue_assets');

// Force JS load on import history page (loaded via AJAX)
function bm_enqueue_on_import_history() {
    if (isset($_GET['page']) && $_GET['page'] === 'bm_data_io' && isset($_GET['subtab']) && $_GET['subtab'] === 'history') {
        wp_enqueue_script('bm-book-manager', plugin_dir_url(__FILE__) . 'assets/js/book-manager.js', array('jquery'), '12.0.0', true);
    }
}
add_action('admin_print_scripts', 'bm_enqueue_on_import_history');

// ==========================================
// CUSTOM POST TYPE
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
// DISCIPLINE TAXONOMY
// ==========================================
function bm_register_discipline_taxonomy() {
    $taxonomies = get_option('bm_dynamic_taxonomies', array());
    $label_plural = isset($taxonomies['bm_discipline']['label']) ? $taxonomies['bm_discipline']['label'] : esc_html__('Disciplinas', 'book-manager');
    $label_singular = rtrim($label_plural, 's');
    if (mb_substr($label_plural, -2) === 'as') {
        $label_singular = mb_substr($label_plural, 0, -1);
    }
    
    register_taxonomy('bm_discipline', 'bm_book', array(
        'label'        => $label_plural,
        'labels'       => array(
            'name'              => $label_plural,
            'singular_name'     => $label_singular,
            'search_items'      => sprintf(esc_html__('Buscar %s', 'book-manager'), $label_plural),
            'all_items'         => sprintf(esc_html__('Todas as %s', 'book-manager'), $label_plural),
            'parent_item'       => sprintf(esc_html__('%s Pai', 'book-manager'), $label_singular),
            'parent_item_colon' => sprintf(esc_html__('%s Pai:', 'book-manager'), $label_singular),
            'edit_item'         => sprintf(esc_html__('Editar %s', 'book-manager'), $label_singular),
            'update_item'       => sprintf(esc_html__('Atualizar %s', 'book-manager'), $label_singular),
            'add_new_item'      => sprintf(esc_html__('Adicionar Nova %s', 'book-manager'), $label_singular),
            'new_item_name'     => sprintf(esc_html__('Nome da Nova %s', 'book-manager'), $label_singular),
            'menu_name'         => $label_plural,
        ),
        'rewrite'      => false,
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => false,
        'capabilities' => array(
            'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options', 'assign_terms' => 'manage_options',
        ),
    ));
}
add_action('init', 'bm_register_discipline_taxonomy');

// ==========================================
// READING LEVEL TAXONOMY
// ==========================================
function bm_register_reading_level_taxonomy() {
    register_taxonomy('bm_reading_level', 'bm_book', array(
        'label'        => isset($taxonomies['bm_reading_level']['label']) ? $taxonomies['bm_reading_level']['label'] : esc_html__('Nível de Leitura', 'book-manager'),
        'labels'       => array(
            'name'              => esc_html__('Níveis de Leitura', 'book-manager'),
            'singular_name'     => esc_html__('Nível de Leitura', 'book-manager'),
            'search_items'      => esc_html__('Buscar Níveis de Leitura', 'book-manager'),
            'all_items'         => esc_html__('Todos os Níveis de Leitura', 'book-manager'),
            'edit_item'         => esc_html__('Editar Nível de Leitura', 'book-manager'),
            'update_item'       => esc_html__('Atualizar Nível de Leitura', 'book-manager'),
            'add_new_item'      => esc_html__('Adicionar Novo Nível de Leitura', 'book-manager'),
            'new_item_name'     => esc_html__('Nome do Novo Nível', 'book-manager'),
            'menu_name'         => esc_html__('Níveis de Leitura', 'book-manager'),
        ),
        'rewrite'      => false,
        'hierarchical' => true,
        'show_ui'      => true,
        'show_in_menu' => false,
        'capabilities' => array(
            'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options',
            'delete_terms' => 'manage_options', 'assign_terms' => 'manage_options',
        ),
    ));
}
add_action('init', 'bm_register_reading_level_taxonomy');

// ==========================================
// CAPABILITIES AND LIFECYCLE HOOKS
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
    add_role('bm_student', esc_html__('Aluno', 'book-manager'), array(
        'read' => true,
        'read_bm_book' => true,
        'read_private_bm_books' => true,
    ));

    add_role('bm_teacher', esc_html__('Professor', 'book-manager'), array(
        'read' => true,
        'read_bm_book' => true,
        'read_private_bm_books' => true,
        'edit_bm_book' => true,
    ));

    add_role('bm_librarian', esc_html__('Gestor da Biblioteca', 'book-manager'), array(
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

// Pre-install default dynamic user fields
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
    
    $default_keys_lower = array_map('mb_strtolower', array_keys($defaults));
    foreach ($existing as $key => $info) {
        if (in_array(mb_strtolower($key), $default_keys_lower) && !isset($defaults[$key])) {
            unset($existing[$key]);
        }
    }
    
    foreach ($defaults as $name => $info) {
        $existing[$name] = $info;
    }
    
    update_option('bm_user_dynamic_fields', $existing);
}

// Pre-install default taxonomies as protected dynamic
function bm_install_default_taxonomies() {
    $existing = get_option('bm_dynamic_taxonomies', array());
    if (!is_array($existing)) $existing = array();
    
    $defaults = array(
        'bm_genre'      => array('label' => esc_html__('Gêneros', 'book-manager'),    'hierarchical' => true, 'protected' => true),
        'bm_category'   => array('label' => esc_html__('Categorias', 'book-manager'),  'hierarchical' => true, 'protected' => true),
        'bm_discipline'     => array('label' => esc_html__('Disciplinas', 'book-manager'),       'hierarchical' => true, 'protected' => true),
        'bm_reading_level'  => array('label' => esc_html__('Nível de Leitura', 'book-manager'),  'hierarchical' => true, 'protected' => true),
    );
    
    foreach ($defaults as $slug => $info) {
        if (!isset($existing[$slug])) {
            $existing[$slug] = $info;
        }
    }
    
    update_option('bm_dynamic_taxonomies', $existing);
}

// Install default reading level terms
function bm_install_default_reading_level_terms() {
    $terms = array(
        'muito-facil'    => esc_html__('Muito fácil', 'book-manager'),
        'facil'          => esc_html__('Fácil', 'book-manager'),
        'intermediario'  => esc_html__('Intermediário', 'book-manager'),
        'avancado'       => esc_html__('Avançado', 'book-manager'),
        'muito-avancado' => esc_html__('Muito avançado', 'book-manager'),
    );
    
    foreach ($terms as $slug => $name) {
        if (!term_exists($slug, 'bm_reading_level')) {
            wp_insert_term($name, 'bm_reading_level', array('slug' => $slug));
        }
    }
}

// Clean dirty roles on activation
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
    bm_add_admin_caps();
    bm_register_roles();
    bm_clean_dirty_roles();
    bm_install_default_taxonomies();
    bm_install_default_user_fields();
    bm_install_default_reading_level_terms();    
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bm_plugin_activation');
function bm_plugin_deactivation() { flush_rewrite_rules(); }
register_deactivation_hook(__FILE__, 'bm_plugin_deactivation');

// ==========================================
// QUERY CACHE (TRANSIENTS)
// ==========================================
function bm_get_cached($key) {
    return get_transient('bm_cache_' . $key);
}

function bm_set_cached($key, $data, $expiry = 300) {
    set_transient('bm_cache_' . $key, $data, $expiry);
}

// Reorder admin submenus
function bm_reorder_submenus() {
    global $submenu;
    $parent = 'edit.php?post_type=bm_book';
    if (!isset($submenu[$parent])) return;
    
    $items = $submenu[$parent];
    
    $allowed = array(
        'edit.php?post_type=bm_book' => 'Livros',
        'post-new.php?post_type=bm_book' => 'Adicionar Novo',
        'bm_service_desk' => 'Balcão de Atendimento',
        'bm_students' => 'Alunos',
        'bm_reports' => 'Relatórios',
        'bm_taxonomias' => 'Taxonomias',
        'bm_dynamic_fields' => 'Gerenciar Campos',
        'bm_labels' => 'Etiquetas',
        'bm_data_io' => 'Importação/Exportação',
        'bm_settings' => 'Configurações',
    );
    
    $reordered = array();
    
    foreach ($allowed as $slug => $title) {
        foreach ($items as $item) {
            if ($item[2] === $slug) {
                $item[0] = $title;
                $reordered[] = $item;
                break;
            }
        }
    }
    
    $submenu[$parent] = $reordered;
}
add_action('admin_menu', 'bm_reorder_submenus', 1000);
add_action('admin_menu', 'bm_add_taxonomies_page', 1001);

function bm_hide_librarian_submenus() {
    if (current_user_can('manage_options')) return;
    if (!current_user_can('edit_bm_books')) return;
    
    if (!bm_librarian_can('dynamic_fields')) remove_submenu_page('edit.php?post_type=bm_book', 'bm_dynamic_fields');
    if (!bm_librarian_can('taxonomies')) remove_submenu_page('edit.php?post_type=bm_book', 'bm_taxonomies');
    if (!bm_librarian_can('labels')) remove_submenu_page('edit.php?post_type=bm_book', 'bm_labels');
    if (!bm_librarian_can('import_csv')) remove_submenu_page('edit.php?post_type=bm_book', 'bm_data_io');
    if (!bm_librarian_can('students')) {
        remove_submenu_page('edit.php?post_type=bm_book', 'bm_students');
        remove_submenu_page('edit.php?post_type=bm_book', 'bm_acquisition_suggestions');
        remove_submenu_page('edit.php?post_type=bm_book', 'bm_library_cards');
    }
    if (!bm_librarian_can('loans') && !bm_librarian_can('service')) remove_submenu_page('edit.php?post_type=bm_book', 'bm_service_desk');
    if (!bm_librarian_can('approve_users') && !bm_librarian_can('approve_readings')) {
        remove_submenu_page('edit.php?post_type=bm_book', 'bm_students');
    }
}
add_action('admin_menu', 'bm_hide_librarian_submenus', 999);

// ==========================================
// SOFT DELETE AND AUDIT LOG
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