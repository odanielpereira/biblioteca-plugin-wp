<?php
get_header();
$wl = bm_get_white_label();
?>

<div class="bm-book-single" style="max-width:800px;margin:0 auto;padding:20px;">
    <?php if ($wl['enabled'] === '1' && !empty($wl['school_logo'])): ?>
        <div style="text-align:center;margin-bottom:15px;">
            <img src="<?php echo esc_url($wl['school_logo']); ?>" style="max-width:200px;max-height:80px;" alt="<?php echo esc_attr($wl['school_name']); ?>" />
        </div>
    <?php endif; ?>

    <?php while (have_posts()): the_post(); ?>
        <div class="bm-book-header" style="display:flex;gap:30px;margin-bottom:30px;flex-wrap:wrap;">
            <?php if (has_post_thumbnail()): ?>
                <div class="bm-book-cover" style="flex:0 0 200px;">
                    <?php the_post_thumbnail('medium', array('style' => 'width:100%;height:auto;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.15);')); ?>
                </div>
            <?php else: ?>
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

                <?php if (function_exists('bm_display_stock_info')) echo bm_display_stock_info(); ?>
                <?php if (function_exists('bm_reserve_button')) bm_reserve_button(); ?>
                <?php if (function_exists('bm_label_button')) bm_label_button(); ?>

                <?php
                $user_role = function_exists('bm_get_user_role') ? bm_get_user_role() : 'guest';
                $settings = function_exists('bm_get_settings') ? bm_get_settings() : array();
                $visibility = isset($settings['field_visibility']) ? $settings['field_visibility'] : array();
                
                $can_see = function($field) use ($user_role, $visibility) {
                    if ($user_role === 'admin') return true;
                    $role_map = array('student' => 'student', 'teacher' => 'teacher', 'librarian' => 'librarian');
                    $mapped_role = isset($role_map[$user_role]) ? $role_map[$user_role] : 'guest';
                    if ($mapped_role === 'guest') return false;
                    return isset($visibility[$field][$mapped_role]) && $visibility[$field][$mapped_role];
                };
                
                $isbn = get_post_meta(get_the_ID(), '_bm_isbn', true);
                $location = get_post_meta(get_the_ID(), '_bm_location', true);
                $copies = get_post_meta(get_the_ID(), '_bm_copies', true);
                $audit_log = get_post_meta(get_the_ID(), '_bm_audit_log', true);
                
                $has_visible = ($isbn && $can_see('isbn')) || ($location && $can_see('location')) || ($copies && $can_see('copies')) || (!empty($audit_log) && $can_see('audit_log'));
                ?>
                <?php if ($has_visible): ?>
                    <hr>
                    <h3>Informações Administrativas</h3>
                    <?php if ($isbn && $can_see('isbn')): ?><p><strong>ISBN:</strong> <?php echo esc_html($isbn); ?></p><?php endif; ?>
                    <?php if ($location && $can_see('location')): ?><p><strong>Localização:</strong> <?php echo esc_html($location); ?></p><?php endif; ?>
                    <?php if ($copies && $can_see('copies')): ?><p><strong>Exemplares:</strong> <?php echo esc_html($copies); ?></p><?php endif; ?>

                    <?php if (!empty($audit_log) && $can_see('audit_log')): ?>
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

        $official_review = get_post_meta(get_the_ID(), '_bm_official_review', true);
        $official_link = get_post_meta(get_the_ID(), '_bm_official_link', true);
        $official_embed = '';
        if (!empty($official_link)) {
            if (strpos($official_link, 'youtube.com') !== false || strpos($official_link, 'youtu.be') !== false) {
                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $official_link, $matches);
                if (!empty($matches[1])) $official_embed = 'https://www.youtube.com/embed/' . $matches[1];
            } elseif (strpos($official_link, 'tiktok.com') !== false) {
                preg_match('/video\/(\d+)/', $official_link, $matches);
                if (!empty($matches[1])) $official_embed = 'https://www.tiktok.com/embed/v2/' . $matches[1];
            } elseif (strpos($official_link, 'instagram.com') !== false) {
                $official_embed = $official_link . 'embed/';
            }
        }
        if (!empty($official_review) || !empty($official_link)):
        ?>
            <hr>
            <h2><?php _e('Resenha da Biblioteca', 'book-manager'); ?></h2>
            <div style="background:#fff8e1;padding:20px;border-radius:8px;border-left:4px solid #ffc107;margin-bottom:20px;">
                <?php if (!empty($official_embed)): ?>
                    <iframe src="<?php echo esc_url($official_embed); ?>" style="width:100%;aspect-ratio:16/9;border:none;border-radius:4px;margin-bottom:15px;" allowfullscreen></iframe>
                <?php elseif (!empty($official_link)): ?>
                    <p><a href="<?php echo esc_url($official_link); ?>" target="_blank">🔗 Link oficial</a></p>
                <?php endif; ?>
                <?php if (!empty($official_review)): ?>
                    <p style="margin:0;font-style:italic;color:#555;"><?php echo nl2br(esc_html($official_review)); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
        if (current_user_can('edit_bm_book') || current_user_can('manage_options')):
            $activities = get_post_meta(get_the_ID(), '_bm_activities', true);
            $btn_label = $activities ? __('Regenerar Atividades', 'book-manager') : __('Gerar Atividades', 'book-manager');
            $nonce = wp_create_nonce('bm_activities_nonce');
        ?>
            <hr>
            <h2>📝 <?php _e('Atividades Pedagógicas', 'book-manager'); ?></h2>
            <?php if ($activities): ?>
                <div style="background:#f0f7ff;padding:20px;border-radius:8px;border-left:4px solid #2196f3;margin-bottom:10px;">
                    <?php echo nl2br(esc_html($activities)); ?>
                </div>
            <?php endif; ?>
            <button type="button" id="bm-gen-activities" class="bm-btn-filter" style="padding:8px 16px;background:#2196f3;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                🤖 <?php echo $btn_label; ?>
            </button>
            <span id="bm-gen-loading" style="display:none;margin-left:10px;color:#666;">Gerando...</span>
            <script>
            document.getElementById('bm-gen-activities').addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;
                document.getElementById('bm-gen-loading').style.display = 'inline';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    document.getElementById('bm-gen-loading').style.display = 'none';
                    if (xhr.responseText.indexOf('sucesso') !== -1) { location.reload(); }
                    else { alert(xhr.responseText); btn.disabled = false; }
                };
                xhr.send('action=bm_generate_activities&nonce=<?php echo $nonce; ?>&post_id=<?php echo get_the_ID(); ?>');
            });
            </script>
        <?php endif; ?>

        <?php if (function_exists('bm_display_call_number')) echo bm_display_call_number(); ?>

        <?php
        $disciplines = wp_get_post_terms(get_the_ID(), 'bm_discipline', array('fields' => 'all'));
        $justifications = get_post_meta(get_the_ID(), '_bm_discipline_justifications', true);
        if (!empty($disciplines)):
        ?>
            <hr>
            <h2>📚 <?php _e('Relação com as Disciplinas', 'book-manager'); ?></h2>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;">
                <?php foreach ($disciplines as $discipline): ?>
                    <div style="background:#e3f2fd;padding:8px 15px;border-radius:20px;font-size:14px;font-weight:bold;color:#1565c0;">
                        <?php echo esc_html($discipline->name); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($justifications)): ?>
                <div style="background:#f9f9f9;padding:15px;border-radius:8px;">
                    <h3 style="margin-top:0;"><?php _e('Por que este livro se relaciona com cada disciplina?', 'book-manager'); ?></h3>
                    <?php foreach ($justifications as $discipline_name => $justification): ?>
                        <p style="margin:5px 0;"><strong><?php echo esc_html($discipline_name); ?>:</strong> <?php echo esc_html($justification); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        $book_id = get_the_ID();
        $all_users = get_users(array('role__in' => array('bm_student', 'bm_teacher')));
        $approved_reviews = array();
        $has_videos = false;
        foreach ($all_users as $user) {
            $reading_log = get_user_meta($user->ID, '_bm_reading_log', true) ?: array();
            foreach ($reading_log as $log) {
                if ($log['book_id'] == $book_id && $log['status'] === 'approved') {
                    $log['user_name'] = $user->display_name;
                    $log['user_avatar'] = get_avatar_url($user->ID, array('size' => 40));
                    $approved_reviews[] = $log;
                    if (!empty($log['video_url'])) $has_videos = true;
                }
            }
        }
        if (!empty($approved_reviews)):
            $approved_reviews = array_reverse($approved_reviews);
        ?>
            <hr>
            <h2><?php _e('Resenhas dos Leitores', 'book-manager'); ?></h2>
            <?php if ($has_videos): ?>
                <h3>🎬 <?php _e('Vídeo-Resenhas', 'book-manager'); ?></h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;margin-bottom:20px;">
                    <?php foreach ($approved_reviews as $review): ?>
                        <?php if (!empty($review['video_url'])): ?>
                            <div style="background:#f9f9f9;padding:10px;border-radius:8px;">
                                <?php
                                $embed_url = '';
                                if (strpos($review['video_url'], 'youtube.com') !== false || strpos($review['video_url'], 'youtu.be') !== false) {
                                    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $review['video_url'], $matches);
                                    if (!empty($matches[1])) $embed_url = 'https://www.youtube.com/embed/' . $matches[1];
                                } elseif (strpos($review['video_url'], 'tiktok.com') !== false) {
                                    preg_match('/video\/(\d+)/', $review['video_url'], $matches);
                                    if (!empty($matches[1])) $embed_url = 'https://www.tiktok.com/embed/v2/' . $matches[1];
                                } elseif (strpos($review['video_url'], 'instagram.com') !== false) {
                                    $embed_url = $review['video_url'] . 'embed/';
                                }
                                ?>
                                <?php if ($embed_url): ?>
                                    <iframe src="<?php echo esc_url($embed_url); ?>" style="width:100%;aspect-ratio:16/9;border:none;border-radius:4px;" allowfullscreen></iframe>
                                <?php else: ?>
                                    <p><a href="<?php echo esc_url($review['video_url']); ?>" target="_blank">🔗 Ver vídeo</a></p>
                                <?php endif; ?>
                                <div style="display:flex;align-items:center;gap:8px;margin-top:8px;">
                                    <?php if ($review['user_avatar']): ?>
                                        <img src="<?php echo esc_url($review['user_avatar']); ?>" style="width:30px;height:30px;border-radius:50%;" alt="" />
                                    <?php endif; ?>
                                    <small style="color:#666;"><?php echo esc_html($review['user_name']); ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h3>📝 <?php _e('Resenhas', 'book-manager'); ?></h3>
            <?php foreach ($approved_reviews as $review): ?>
                <div style="background:#f9f9f9;padding:15px;border-radius:8px;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;">
                        <?php if ($review['user_avatar']): ?>
                            <img src="<?php echo esc_url($review['user_avatar']); ?>" style="width:30px;height:30px;border-radius:50%;" alt="" />
                        <?php endif; ?>
                        <strong><?php echo esc_html($review['user_name']); ?></strong>
                        <span style="color:#ffc107;"><?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?></span>
                        <small style="color:#999;"><?php echo date('d/m/Y', strtotime($review['date'])); ?></small>
                    </div>
                    <p style="margin:5px 0;color:#444;"><?php echo esc_html($review['review']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>