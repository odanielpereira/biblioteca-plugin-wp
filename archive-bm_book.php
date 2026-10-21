<?php
defined('ABSPATH') || exit;

get_header();
$wl = bm_get_white_label();
?>

<div class="bm-catalog">
    <h1><?php echo ($wl['enabled'] === '1' && !empty($wl['school_name'])) ? esc_html($wl['school_name']) : esc_html__('Catálogo de Livros', 'book-manager'); ?></h1>

    <form method="get" class="bm-filters">
        <div>
            <label><?php esc_html_e('Buscar', 'book-manager'); ?></label>
            <input type="text" name="bm_search" value="<?php echo isset($_GET['bm_search']) ? esc_attr($_GET['bm_search']) : ''; ?>" placeholder="<?php esc_attr_e('Título ou autor', 'book-manager'); ?>" />
        </div>
        <?php 
        $taxonomies = get_option('bm_dynamic_taxonomies', array());
        $settings = function_exists('bm_get_settings') ? bm_get_settings() : array();
        $visibility = isset($settings['taxonomy_visibility']) ? $settings['taxonomy_visibility'] : array();
        
        $genre_label = isset($taxonomies['bm_genre']['label']) ? $taxonomies['bm_genre']['label'] : __('Gênero', 'book-manager');
        $category_label = isset($taxonomies['bm_category']['label']) ? $taxonomies['bm_category']['label'] : __('Categoria', 'book-manager');
        $discipline_label = isset($taxonomies['bm_discipline']['label']) ? $taxonomies['bm_discipline']['label'] : __('Disciplina', 'book-manager');
        $reading_level_label = isset($taxonomies['bm_reading_level']['label']) ? $taxonomies['bm_reading_level']['label'] : __('Nível de Leitura', 'book-manager');
        ?>
        <?php if (!isset($visibility['bm_genre']) || $visibility['bm_genre']): ?>
        <div>
            <label><?php echo esc_html($genre_label); ?></label>
            <?php
            wp_dropdown_categories(array(
                'show_option_all' => sprintf(esc_html__('Todos os %s', 'book-manager'), $genre_label),
                'taxonomy' => 'bm_genre',
                'name' => 'bm_genre',
                'selected' => isset($_GET['bm_genre']) ? $_GET['bm_genre'] : '',
                'hide_empty' => true,
            ));
            ?>
        </div>
        <?php endif; ?>
        <?php if (!isset($visibility['bm_category']) || $visibility['bm_category']): ?>
        <div>
            <label><?php echo esc_html($category_label); ?></label>
            <?php
            wp_dropdown_categories(array(
                'show_option_all' => sprintf(esc_html__('Todas as %s', 'book-manager'), $category_label),
                'taxonomy' => 'bm_category',
                'name' => 'bm_category',
                'selected' => isset($_GET['bm_category']) ? $_GET['bm_category'] : '',
                'hide_empty' => true,
            ));
            ?>
        </div>
        <?php endif; ?>
        <?php if (!isset($visibility['bm_discipline']) || $visibility['bm_discipline']): ?>
        <div>
            <label><?php echo esc_html($discipline_label); ?></label>
            <?php
            wp_dropdown_categories(array(
                'show_option_all' => sprintf(esc_html__('Todas as %s', 'book-manager'), $discipline_label),
                'taxonomy' => 'bm_discipline',
                'name' => 'bm_discipline',
                'selected' => isset($_GET['bm_discipline']) ? $_GET['bm_discipline'] : '',
                'hide_empty' => true,
            ));
            ?>
        </div>
        <?php endif; ?>
        <?php if (!isset($visibility['bm_reading_level']) || $visibility['bm_reading_level']): ?>
        <div>
            <label><?php echo esc_html($reading_level_label); ?></label>
            <?php
            wp_dropdown_categories(array(
                'show_option_all' => sprintf(esc_html__('Todos os %s', 'book-manager'), $reading_level_label),
                'taxonomy' => 'bm_reading_level',
                'name' => 'bm_reading_level',
                'selected' => isset($_GET['bm_reading_level']) ? $_GET['bm_reading_level'] : '',
                'hide_empty' => true,
            ));
            ?>
        </div>
        <?php endif; ?>
        <div>
            <button type="submit" class="bm-btn-filter"><?php esc_html_e('Filtrar', 'book-manager'); ?></button>
            <a href="<?php echo esc_url(get_post_type_archive_link('bm_book')); ?>" class="bm-btn-clear"><?php esc_html_e('Limpar', 'book-manager'); ?></a>
        </div>
    </form>
<?php
global $wp_query;
$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$args = array(
    'post_type' => 'bm_book',
    'posts_per_page' => 60,
    'paged' => $paged,
);

$tax_query = array();
$bm_genre = isset($_GET['bm_genre']) ? $_GET['bm_genre'] : '';
if ($bm_genre !== '' && $bm_genre !== '0') {
    $tax_query[] = array('taxonomy' => 'bm_genre', 'field' => 'term_id', 'terms' => intval($bm_genre));
}
$bm_category = isset($_GET['bm_category']) ? $_GET['bm_category'] : '';
if ($bm_category !== '' && $bm_category !== '0') {
    $tax_query[] = array('taxonomy' => 'bm_category', 'field' => 'term_id', 'terms' => intval($bm_category));
}
if (count($tax_query) > 1) $tax_query['relation'] = 'AND';
$bm_discipline = isset($_GET['bm_discipline']) ? $_GET['bm_discipline'] : '';
if ($bm_discipline !== '' && $bm_discipline !== '0') {
    $tax_query[] = array('taxonomy' => 'bm_discipline', 'field' => 'term_id', 'terms' => intval($bm_discipline));
}
$bm_reading_level = isset($_GET['bm_reading_level']) ? $_GET['bm_reading_level'] : '';
if ($bm_reading_level !== '' && $bm_reading_level !== '0') {
    $tax_query[] = array('taxonomy' => 'bm_reading_level', 'field' => 'term_id', 'terms' => intval($bm_reading_level));
}
if (!empty($tax_query)) $args['tax_query'] = $tax_query;

if (isset($_GET['bm_search']) && !empty($_GET['bm_search'])) {
    $args['s'] = sanitize_text_field($_GET['bm_search']);
}

$bm_query = new WP_Query($args);
?>
    <?php if ($bm_query->have_posts()): ?>
        <div class="bm-book-grid">
            <?php while ($bm_query->have_posts()): $bm_query->the_post(); ?>
                <div class="bm-book-card">
                    <a href="<?php the_permalink(); ?>">
                        <?php 
                        $hotlink = get_post_meta(get_the_ID(), '_bm_cover_hotlink', true);
                        if (has_post_thumbnail()): ?>
                            <div class="bm-card-cover">
                                <?php the_post_thumbnail('medium', array('style' => 'width:100%;height:220px;object-fit:cover;')); ?>
                            </div>
                        <?php elseif (!empty($hotlink)): ?>
                            <div class="bm-card-cover">
                                <img src="<?php echo esc_url($hotlink); ?>" style="width:100%;height:220px;object-fit:cover;" alt="<?php the_title_attribute(); ?>" />
                            </div>
                        <?php else: ?>
                            <div class="bm-card-no-cover">
                                <?php esc_html_e('Sem capa', 'book-manager'); ?>
                            </div>
                        <?php endif; ?>
                        <div class="bm-card-info">
                            <h3><?php the_title(); ?></h3>
                            <?php $author = get_post_meta(get_the_ID(), '_bm_author', true); ?>
                            <?php if ($author): ?>
                                <p><?php echo esc_html($author); ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php if (function_exists('bm_reserve_button')) bm_reserve_button(); ?>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="bm-pagination">
            <?php
            $big = 999999999;
            echo paginate_links(array(
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => max(1, $paged),
                'total' => $bm_query->max_num_pages,
                'prev_text' => '←',
                'next_text' => '→',
            ));
            ?>
        </div>
    <?php else: ?>
        <p><?php esc_html_e('Nenhum livro encontrado.', 'book-manager'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>