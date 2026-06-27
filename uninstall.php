<?php
/**
 * File: uninstall.php
 * Description: Script de desinstalação para o plugin Gestão de Livros.
 *              Remove todos os dados associados ao plugin de forma permanente.
 *              (Conforme Seção 6 do escopo.md)
 */

// 1. Segurança: Verifica se o arquivo está sendo chamado pelo WordPress.
defined('ABSPATH') || exit;

// 2. Inclui o arquivo principal do plugin para ter acesso às funções necessárias.
//    Esta abordagem garante que a função bm_remove_admin_caps() esteja disponível.
//    (Conforme Seção 6 do escopo.md e decisão registrada no changelog)
require_once 'book-manager.php';

// 3. Remove as capabilities customizadas do administrador.
//    (Conforme Seção 6 do escopo.md)
bm_remove_admin_caps();

// 4. Deleta permanentemente todos os posts do tipo 'bm_book'.
//    Usa get_posts com posts_per_page = -1 para buscar todos os posts de uma vez,
//    e wp_delete_post com 'true' para forçar a exclusão permanente.
//    (Conforme Seção 6 do escopo.md e decisão sobre performance)
$posts = get_posts(array(
    'post_type'      => 'bm_book',
    'posts_per_page' => -1, // Busca todos os posts
    'post_status'    => 'any', // Inclui todos os status (publish, draft, etc.)
    'fields'         => 'ids', // Retorna apenas os IDs para otimização
));

if (!empty($posts)) {
    foreach ($posts as $post_id) {
        // Força a exclusão permanente, pulando a lixeira.
        wp_delete_post($post_id, true); 
    }
}

// 5. Deleta todas as meta keys _bm_author e _bm_publisher.
//    Embora wp_delete_post(true) remova metadados, esta etapa garante a remoção explícita.
//    (Conforme Seção 6 do escopo.md)
if (!empty($posts)) {
    foreach ($posts as $post_id) {
        delete_post_meta($post_id, '_bm_author');
        delete_post_meta($post_id, '_bm_publisher');
    }
}
?>