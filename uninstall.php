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

// 3.1. Remove as opções e transients criados pelo plugin (DeepSeek e afins).
delete_option('bm_api_settings');
delete_option('bm_import_log');
delete_option('bm_import_preview');
delete_option('bm_export_all_message');
delete_option('bm_import_report');
delete_option('bm_settings');
delete_option('bm_white_label');
delete_option('bm_year_transition');
delete_option('bm_penalty_rules');
delete_option('bm_penalty_rules');
delete_option('bm_user_dynamic_fields');
delete_option('bm_dynamic_fields');
delete_option('bm_dynamic_taxonomies');
delete_option('bm_field_order');
delete_option('bm_field_visibility');
delete_option('bm_user_field_order');
delete_option('bm_user_field_visibility');
delete_option('bm_reading_lists');
delete_option('bm_acquisition_suggestions');
delete_option('bm_groq_call_count');
delete_option('bm_groq_success_count');
delete_option('bm_last_year_transition');
delete_option('bm_recadastro_required');
delete_option('bm_recadastro_year');
delete_option('bm_ranking_archive_' . date('Y'));
delete_option('bm_admin_audit_log');
delete_option('bm_year_transition_log');
delete_option('bm_stats_cache');
delete_option('bm_error_log');

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