<?php
/**
 * Plugin Name:       Gestão de Livros
 * Plugin URI:        https://github.com/odanielpereira/biblioteca-plugin-wp
 * Description:       Gerenciador de livros para o tema Biblioteca.
 * Version:           0.1.0
 * Author:            Daniel Pereira
 * Author URI:        https://odanielpereira.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       book-manager
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

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
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'bm_book',
        'map_meta_cap'       => true,
        'supports'           => array( 'title' ),
        'delete_with_user'   => false,
        'menu_icon'          => 'dashicons-book',
    );

    register_post_type( 'bm_book', $args );
}
add_action( 'init', 'bm_register_book_cpt' );

function bm_add_admin_caps() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $caps = [
            'edit_bm_book',
            'read_bm_book',
            'delete_bm_book',
            'edit_bm_books',
            'edit_others_bm_books',
            'publish_bm_books',
            'read_private_bm_books',
            'delete_bm_books',
            'delete_private_bm_books',
            'delete_published_bm_books',
            'delete_others_bm_books',
            'edit_private_bm_books',
            'edit_published_bm_books',
        ];
        foreach ($caps as $cap) {
            $admin_role->add_cap($cap);
        }
    }
}

function bm_remove_admin_caps() {
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $caps = [
            'edit_bm_book',
            'read_bm_book',
            'delete_bm_book',
            'edit_bm_books',
            'edit_others_bm_books',
            'publish_bm_books',
            'read_private_bm_books',
            'delete_bm_books',
            'delete_private_bm_books',
            'delete_published_bm_books',
            'delete_others_bm_books',
            'edit_private_bm_books',
            'edit_published_bm_books',
        ];
        foreach ($caps as $cap) {
            $admin_role->remove_cap($cap);
        }
    }
}

function bm_plugin_activation() {
    bm_register_book_cpt();
    bm_add_admin_caps();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bm_plugin_activation' );

function bm_plugin_deactivation() {
    // Conforme o escopo.md, na desativação apenas flush_rewrite_rules() é executado.
    // A remoção de capabilities deve ocorrer apenas em uninstall.php.
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bm_plugin_deactivation' );