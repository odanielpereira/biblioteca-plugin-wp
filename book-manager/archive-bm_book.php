<?php
get_header();
?>

<!-- FASE 8E: Estilos da vitrine visual -->
<style>
.bm-catalog { max-width:1000px; margin:0 auto; padding:20px; }
.bm-filters { display:flex; gap:10px; flex-wrap:wrap; margin:20px 0; align-items:end; }
.bm-filters label { display:block; font-size:12px; font-weight:bold; margin-bottom:3px; }
.bm-filters input[type="text"], .bm-filters select { padding:6px 10px; border:1px solid #ccc; border-radius:4px; }
.bm-filters input[type="text"] { min-width:200px; }
.bm-book-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:20px; margin-top:20px; }
.bm-book-card { background:#fff; border-radius:6px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1); transition:transform 0.2s ease, box-shadow 0.2s ease; }
.bm-book-card:hover { transform:translateY(-4px); box-shadow:0 6px 20px rgba(0,0,0,0.15); }
.bm-book-card a { text-decoration:none; color:inherit; }
.bm-card-cover img { width:100%; height:220px; object-fit:cover; display:block; }
.bm-card-no-cover { height:220px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999; font-size:14px; }
.bm-card-info { padding:10px; }
.bm-card-info h3 { font-size:14px; margin:0 0 5px 0; line-height:1.3; }
.bm-card-info p { font-size:12px; color:#666; margin:0; }
.bm-pagination { margin-top:30px; text-align:center; }
.bm-btn-filter { padding:6px 15px; background:#111; color:#fff; border:none; border-radius:4px; cursor:pointer; }
.bm-btn-filter:hover { background:#333; }
.bm-btn-clear { padding:6px 15px; background:#eee; color:#333; text-decoration:none; border-radius:4px; font-size:14px; }
.bm-btn-clear:hover { background:#ddd; }
@media (max-width:600px) {
    .bm-book-grid { grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:12px; }
    .bm-card-cover img, .bm-card-no-cover { height:180px; }
    .bm-filters { flex-direction:column; }
    .bm-filters input[type="text"] { min-width:100%; }
}
</style>

<div class="bm-catalog">
    <h1><?php _e('Catálogo de Livros', 'book-manager'); ?></h1>

    <!-- FASE 8D: Filtros Inteligentes -->
    <form method="get" class="bm-filters">
        <div>
            <label><?php _e('Buscar', 'book-manager'); ?></label>
            <input type="text" name="bm_search" value="<?php echo isset($_GET['bm_search']) ? esc_attr($_GET['bm_search']) : ''; ?>" placeholder="<?php _e('Título ou autor', 'book-manager'); ?>" />
        </div>
        <div>
            <label><?php _e('Gênero', 'book-manager'); ?></label>
            <?php
            wp_dropdown_categories(array(
                'show_option_all' => __('Todos os Gêneros', 'book-manager'),
                'taxonomy' => 'bm_genre',
                'name' => 'bm_genre',
                'selected' => isset($_GET['bm_genre']) ? $_GET['bm_genre'] : '',
                'hide_empty' => true,
            ));
            ?>
        </div>
        <div>
            <label><?php _e('Categoria', 'book-manager'); ?></label>
            <?php
            wp_dropdown_categories(array(
                'show_option_all' => __('Todas as Categorias', 'book-manager'),
                'taxonomy' => 'bm_category',
                'name' => 'bm_category',
                'selected' => isset($_GET['bm_category']) ? $_GET['bm_category'] : '',
                'hide_empty' => true,
            ));
            ?>
        </div>
        <div>
            <button type="submit" class="bm-btn-filter"><?php _e('Filtrar', 'book-manager'); ?></button>
            <a href="<?php echo get_post_type_archive_link('bm_book'); ?>" class="bm-btn-clear"><?php _e('Limpar', 'book-manager'); ?></a>
        </div>
    </form>

    <?php if (have_posts()): ?>
        <div class="bm-book-grid">
            <?php while (have_posts()): the_post(); ?>
                <!-- FASE 8E: Card com hover effect -->
                <div class="bm-book-card">
                    <a href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="bm-card-cover">
                                <?php the_post_thumbnail('medium', array('style' => 'width:100%;height:220px;object-fit:cover;')); ?>
                            </div>
                        <?php else: ?>
                            <div class="bm-card-no-cover">
                                <?php _e('Sem capa', 'book-manager'); ?>
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
                </div>
            <?php endwhile; ?>
        </div>

        <div class="bm-pagination">
            <?php
            the_posts_pagination(array(
                'mid_size' => 2,
                'prev_text' => '←',
                'next_text' => '→',
            ));
            ?>
        </div>
    <?php else: ?>
        <p><?php _e('Nenhum livro encontrado.', 'book-manager'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>