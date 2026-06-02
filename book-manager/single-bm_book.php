<?php
get_header();
?>

<div class="bm-book-single" style="max-width:800px;margin:0 auto;padding:20px;">
    <?php while (have_posts()): the_post(); ?>
        <div class="bm-book-header" style="display:flex;gap:30px;margin-bottom:30px;flex-wrap:wrap;">
            <?php if (has_post_thumbnail()): ?>
                <div class="bm-book-cover" style="flex:0 0 200px;">
                    <?php the_post_thumbnail('medium', array('style' => 'width:100%;height:auto;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.15);')); ?>
                </div>
            <?php else: ?>
                <!-- FASE 8C-B: Placeholder para livros sem capa -->
                <div class="bm-book-no-cover" style="flex:0 0 200px;height:280px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:14px;border-radius:4px;text-align:center;padding:10px;">
                    <?php _e('Sem capa', 'book-manager'); ?>
                </div>
            <?php endif; ?>
            <div class="bm-book-info" style="flex:1;min-width:250px;">
                <h1 style="margin-top:0;"><?php the_title(); ?></h1>
                <?php
                $author = get_post_meta(get_the_ID(), '_bm_author', true);
                $publisher = get_post_meta(get_the_ID(), '_bm_publisher', true);
                $genres = wp_get_post_terms(get_the_ID(), 'bm_genre', array('fields' => 'names'));
                $categories = wp_get_post_terms(get_the_ID(), 'bm_category', array('fields' => 'names'));
                ?>
                <?php if ($author): ?><p><strong>Autor:</strong> <?php echo esc_html($author); ?></p><?php endif; ?>
                <?php if ($publisher): ?><p><strong>Editora:</strong> <?php echo esc_html($publisher); ?></p><?php endif; ?>
                <?php if ($genres): ?><p><strong>Gênero:</strong> <?php echo esc_html(implode(', ', $genres)); ?></p><?php endif; ?>
                <?php if ($categories): ?><p><strong>Categoria:</strong> <?php echo esc_html(implode(', ', $categories)); ?></p><?php endif; ?>

                <?php if (current_user_can('manage_options')): ?>
                    <hr>
                    <h3>Informações Administrativas</h3>
                    <?php
                    $isbn = get_post_meta(get_the_ID(), '_bm_isbn', true);
                    $location = get_post_meta(get_the_ID(), '_bm_location', true);
                    $copies = get_post_meta(get_the_ID(), '_bm_copies', true);
                    ?>
                    <?php if ($isbn): ?><p><strong>ISBN:</strong> <?php echo esc_html($isbn); ?></p><?php endif; ?>
                    <?php if ($location): ?><p><strong>Localização:</strong> <?php echo esc_html($location); ?></p><?php endif; ?>
                    <?php if ($copies): ?><p><strong>Exemplares:</strong> <?php echo esc_html($copies); ?></p><?php endif; ?>

                    <?php
                    $audit_log = get_post_meta(get_the_ID(), '_bm_audit_log', true);
                    if (!empty($audit_log)): ?>
                        <h4>Histórico de Ações</h4>
                        <ul style="font-size:12px;color:#666;">
                            <?php foreach (array_reverse($audit_log) as $entry): ?>
                                <li><?php echo esc_html($entry['time'] . ' — ' . $entry['user'] . ': ' . $entry['action']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $dynamic_fields = get_option('bm_dynamic_fields', array());
        $has_dynamic = false;
        foreach ($dynamic_fields as $field => $info) {
            $key = '_bm_dynamic_' . sanitize_key($field);
            $value = get_post_meta(get_the_ID(), $key, true);
            if (!empty($value)) {
                if (!$has_dynamic) { echo '<hr><h3>Informações Adicionais</h3>'; $has_dynamic = true; }
                if ($info['type'] === 'textarea') {
                    echo '<p><strong>' . esc_html($field) . ':</strong></p>';
                    echo '<div style="background:#f9f9f9;padding:15px;border-radius:4px;">' . nl2br(esc_html($value)) . '</div>';
                } else {
                    echo '<p><strong>' . esc_html($field) . ':</strong> ' . esc_html($value) . '</p>';
                }
            }
        }
        ?>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>