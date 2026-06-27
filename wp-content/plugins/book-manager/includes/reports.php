<?php

defined('ABSPATH') || exit;

// ==========================================
// FASE 31: MOTOR DE RELATÓRIOS
// ==========================================

function bm_generate_report($args = array()) {
    $defaults = array(
        'type'       => 'overview',
        'period'     => 'month',
        'date_start' => '',
        'date_end'   => '',
        'subject'    => 'all',
        'subject_id' => 0,
        'group'      => '',
        'genre'      => '',
        'discipline' => '',
        'format'     => 'html',
    );
    $args = wp_parse_args($args, $defaults);
    
    $now = current_time('timestamp');
    
    if (!empty($args['date_start']) && !empty($args['date_end'])) {
        $since = strtotime($args['date_start']);
        $until = strtotime($args['date_end'] . ' 23:59:59');
    } else {
        switch ($args['period']) {
            case 'week':
                $since = strtotime('-7 days', $now);
                break;
            case 'bimester':
                $since = strtotime('-60 days', $now);
                break;
            case 'semester':
                $since = strtotime('-180 days', $now);
                break;
            case 'year':
                $since = strtotime('-365 days', $now);
                break;
            case 'month':
            default:
                $since = strtotime('-30 days', $now);
                break;
        }
        $until = $now;
    }
    
    switch ($args['type']) {
        case 'overview':
            return bm_report_dashboard_overview($since, $until);
        case 'student_performance':
            if ($args['subject_id'] > 0) {
                return bm_report_student_performance($args['subject_id'], $since, $until);
            }
            return bm_report_all_students_performance($since, $until);
        case 'class_reading':
            return bm_report_class_reading($args['group'], $since, $until);
        case 'active_penalties':
            return bm_report_active_penalties();
        case 'genre_ranking':
            return bm_report_genre_ranking($since, $until);
        case 'top_books':
            return bm_report_top_books($since, $until, $args['genre']);
        case 'reading_trend':
            return bm_report_reading_trend($args['group'], $since, $until);
        case 'custom':
            return bm_report_custom($args, $since, $until);
        default:
            return array('error' => __('Tipo de relatório inválido.', 'book-manager'));
    }
}

// ==========================================
// FASE 31: FUNÇÕES DE RELATÓRIO
// ==========================================

function bm_report_overview($since, $until) {
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    $total_loans = 0;
    $total_returns = 0;
    $total_overdue = 0;
    $total_reservations = 0;
    $student_loan_counts = array();
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        foreach ($reservations as $r) {
            $loan_time = isset($r['loan_date']) ? strtotime($r['loan_date']) : 0;
            $return_time = isset($r['returned_date']) ? strtotime($r['returned_date']) : 0;
            
            if ($r['status'] === 'active') {
                if ($loan_time === 0 || ($loan_time >= $since && $loan_time <= $until)) {
                    $total_loans++;
                    if (!empty($r['user_id'])) {
                        $student_loan_counts[$r['user_id']] = ($student_loan_counts[$r['user_id']] ?? 0) + 1;
                    }
                }
                if (isset($r['due_date']) && strtotime($r['due_date']) < current_time('timestamp')) {
                    $total_overdue++;
                }
            }
            if ($r['status'] === 'returned') {
                if ($return_time === 0 || ($return_time >= $since && $return_time <= $until)) {
                    $total_returns++;
                    if (!empty($r['user_id'])) {
                        $student_loan_counts[$r['user_id']] = ($student_loan_counts[$r['user_id']] ?? 0) + 1;
                    }
                }
            }
            if ($r['status'] === 'waiting') {
                $total_reservations++;
            }
        }
    }
    
    $inactive_students = array();
    $all_students = get_users(array('role' => 'bm_student', 'number' => 200));
    foreach ($all_students as $student) {
        $count = $student_loan_counts[$student->ID] ?? 0;
        if ($count === 0) {
            $inactive_students[] = $student->display_name;
        }
    }
    
    $period_length = $until - $since;
    $prev_since = $since - $period_length;
    $prev_until = $since;
    $prev_total_loans = 0;
    $prev_total_returns = 0;
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        foreach ($reservations as $r) {
            $loan_time = isset($r['loan_date']) ? strtotime($r['loan_date']) : 0;
            $return_time = isset($r['returned_date']) ? strtotime($r['returned_date']) : 0;
            
            if ($r['status'] === 'active' && $loan_time >= $prev_since && $loan_time <= $prev_until) {
                $prev_total_loans++;
            }
            if ($r['status'] === 'returned' && $return_time >= $prev_since && $return_time <= $prev_until) {
                $prev_total_returns++;
            }
        }
    }
    
    return array(
        'title' => __('Visão Geral', 'book-manager'),
        'total_loans' => $total_loans,
        'total_returns' => $total_returns,
        'total_overdue' => $total_overdue,
        'total_reservations' => $total_reservations,
        'total_loans_prev' => $prev_total_loans,
        'total_returns_prev' => $prev_total_returns,
        'inactive_students' => $inactive_students,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

function bm_report_dashboard_overview($since, $until) {
function bm_report_most_reviewed_books($since, $until, $limit = 5) {
    $all_students = get_users(array('role' => 'bm_student', 'number' => 200));
    $book_review_counts = array();
    
    foreach ($all_students as $student) {
        $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
        foreach ($reading_log as $log) {
            $log_time = isset($log['date']) ? strtotime($log['date']) : 0;
            if ($log_time >= $since && $log_time <= $until && !empty($log['review'])) {
                $book_id = $log['book_id'];
                if (!isset($book_review_counts[$book_id])) $book_review_counts[$book_id] = 0;
                $book_review_counts[$book_id]++;
            }
        }
    }
    
    arsort($book_review_counts);
    $result = array();
    $count = 0;
    foreach ($book_review_counts as $book_id => $reviews) {
        if ($count >= $limit) break;
        $book = get_post($book_id);
        if ($book) {
            $result[] = array(
                'name' => $book->post_title,
                'reviews' => $reviews,
            );
            $count++;
        }
    }
    return $result;
}

function bm_report_most_video_reviewed_books($since, $until, $limit = 5) {
function bm_report_never_borrowed_books() {
    function bm_report_recent_activity($limit = 5) {
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'publish'));
    $recent = array();
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        foreach (array_reverse($reservations) as $r) {
            if (count($recent) >= $limit) break 2;
            $user_name = '';
            if (!empty($r['user_id'])) {
                $user = get_userdata($r['user_id']);
                $user_name = $user ? $user->display_name : '#' . $r['user_id'];
            }
            $action = '';
            if ($r['status'] === 'returned') $action = '📥 ' . $user_name . ' devolveu ' . $book->post_title;
            elseif ($r['status'] === 'active' && !empty($r['loan_date'])) $action = '📤 ' . $user_name . ' pegou ' . $book->post_title;
            elseif ($r['status'] === 'waiting') $action = '📋 ' . $user_name . ' reservou ' . $book->post_title;
            if ($action) $recent[] = $action;
        }
    }
    return $recent;
}
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'publish'));
    $never_borrowed = array();
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        $has_loans = false;
        foreach ($reservations as $r) {
            if ($r['status'] === 'active' || $r['status'] === 'returned') {
                $has_loans = true;
                break;
            }
        }
        if (!$has_loans) {
            $never_borrowed[] = $book->post_title;
        }
    }
    return $never_borrowed;
}
    $all_students = get_users(array('role' => 'bm_student', 'number' => 200));
    $book_video_counts = array();
    
    foreach ($all_students as $student) {
        $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
        foreach ($reading_log as $log) {
            $log_time = isset($log['date']) ? strtotime($log['date']) : 0;
            if ($log_time >= $since && $log_time <= $until && !empty($log['video_url'])) {
                $book_id = $log['book_id'];
                if (!isset($book_video_counts[$book_id])) $book_video_counts[$book_id] = 0;
                $book_video_counts[$book_id]++;
            }
        }
    }
    
    arsort($book_video_counts);
    $result = array();
    $count = 0;
    foreach ($book_video_counts as $book_id => $videos) {
        if ($count >= $limit) break;
        $book = get_post($book_id);
        if ($book) {
            $result[] = array(
                'name' => $book->post_title,
                'videos' => $videos,
            );
            $count++;
        }
    }
    return $result;
}
    // KPIs básicos
    $overview = bm_report_overview($since, $until);
    
    // Desempenho dos alunos
    $performance = bm_report_all_students_performance($since, $until);
    
    // Ranking por gênero
    $genre_ranking = bm_report_genre_ranking($since, $until);
    
    // Livros mais emprestados
    $top_books = bm_report_top_books($since, $until);
    
    // Tendência de leitura
    $reading_trend = bm_report_reading_trend('', $since, $until);
    
    // Top resenhadores (calculado a partir do performance)
    $top_reviewers = array();
    if (!empty($performance['students'])) {
        $reviewers = $performance['students'];
        usort($reviewers, function($a, $b) { return ($b['reviews'] ?? 0) - ($a['reviews'] ?? 0); });
        foreach ($reviewers as $r) {
            if (($r['reviews'] ?? 0) > 0) {
                $top_reviewers[] = array('name' => $r['name'], 'reviews' => $r['reviews']);
            }
        }
    }
    
    // Top video-resenhadores
    $top_video_reviewers = array();
    if (!empty($performance['students'])) {
        $video_reviewers = $performance['students'];
        usort($video_reviewers, function($a, $b) { return ($b['videos'] ?? 0) - ($a['videos'] ?? 0); });
        foreach ($video_reviewers as $r) {
            if (($r['videos'] ?? 0) > 0) {
                $top_video_reviewers[] = array('name' => $r['name'], 'videos' => $r['videos']);
            }
        }
    }
    
    // Top autores (agrupado de top_books)
    $top_authors = array();
    if (!empty($top_books['books'])) {
        $author_counts = array();
        foreach ($top_books['books'] as $book) {
            $author = $book['author'] ?: __('Desconhecido', 'book-manager');
            if (!isset($author_counts[$author])) $author_counts[$author] = 0;
            $author_counts[$author] += $book['loans'];
        }
        arsort($author_counts);
        foreach ($author_counts as $author => $loans) {
            $top_authors[] = array('name' => $author, 'loans' => $loans);
        }
    }
    
    // Livros mais resenhados e com vídeos
    $most_reviewed_books = bm_report_most_reviewed_books($since, $until, 5);
    $most_video_books = bm_report_most_video_reviewed_books($since, $until, 5);
    $never_borrowed = bm_report_never_borrowed_books();
    $recent_activity = bm_report_recent_activity(5);
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'publish'));
    $all_students = get_users(array('role' => 'bm_student', 'number' => 200));
    
    // Livros com fila de espera
    $books_with_queue = array();
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        $waiting = 0;
        foreach ($reservations as $r) {
            if ($r['status'] === 'waiting') $waiting++;
        }
        if ($waiting >= 2) {
            $books_with_queue[] = $book->post_title . ' (' . $waiting . ')';
        }
    }
    
    // Alunos com atraso +7 dias
    $overdue_students = array();
    foreach ($all_students as $student) {
        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
        foreach ($loan_history as $loan) {
            if ($loan['status'] === 'active' && isset($loan['due_date'])) {
                $due_time = strtotime($loan['due_date']);
                $days_late = floor((current_time('timestamp') - $due_time) / DAY_IN_SECONDS);
                if ($days_late >= 7) {
                    $book_title = get_the_title($loan['book_id']);
                    $overdue_students[] = $student->display_name . ' — ' . $book_title . ' (' . $days_late . 'd)';
                    break;
                }
            }
        }
    }
    
    // Ranking de turmas
    $class_ranking = array();
    $groups = array();
    foreach ($all_students as $student) {
        $group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true);
        if ($group && !in_array($group, $groups)) $groups[] = $group;
    }
    foreach ($groups as $group) {
        $class_data = bm_report_class_reading($group, $since, $until);
        if (!isset($class_data['error'])) {
            $class_ranking[] = array('name' => $group, 'average' => $class_data['average']);
        }
    }
    usort($class_ranking, function($a, $b) { return ($b['average'] ?? 0) - ($a['average'] ?? 0); });
    
    // Sugestões de aquisição
    $suggestions = get_option('bm_acquisition_suggestions', array());
    $acquisition_count = count($suggestions);
    
    // Meta de leitura
    $reading_goal = array(
        'current' => $performance['total_books'] ?? 0,
        'target' => 500,
    );
    
    
    return array_merge($overview, array(
        'students' => $performance['students'] ?? array(),
        'total_students' => $performance['total_students'] ?? 0,
        'total_books' => $performance['total_books'] ?? 0,
        'total_reviews' => $performance['total_reviews'] ?? 0,
        'total_videos' => $performance['total_videos'] ?? 0,
        'total_penalties' => $performance['total_penalties'] ?? 0,
        'genres' => $genre_ranking['genres'] ?? array(),
        'books' => $top_books['books'] ?? array(),
        'months' => $reading_trend['months'] ?? array(),
        'top_reviewers' => $top_reviewers,
        'top_video_reviewers' => $top_video_reviewers,
        'top_authors' => $top_authors,
        'most_reviewed_books' => $most_reviewed_books,
        'most_video_books' => $most_video_books,
        'never_borrowed' => $never_borrowed,
        'books_with_queue' => $books_with_queue,
        'overdue_students' => $overdue_students,
        'class_ranking' => $class_ranking,
        'acquisition_suggestions_count' => $acquisition_count,
        'reading_goal' => $reading_goal,
        'recent_activity' => $recent_activity,
        'recent_books' => array(),
        'top_book' => !empty($top_books['books']) ? $top_books['books'][0] : null,
        'revelation_student' => null,
    ));
}

function bm_report_all_students_performance($since, $until) {
    $students = get_users(array('role' => 'bm_student', 'number' => 200));
    $all_data = array();
    $total_books = 0;
    $total_reviews = 0;
    $total_videos = 0;
    $total_penalties = 0;
    
    foreach ($students as $student) {
        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
        $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
        $penalties = get_user_meta($student->ID, '_bm_penalties', true) ?: array();
        $xp = bm_get_xp($student->ID);
        $badges = get_user_meta($student->ID, '_bm_badges', true) ?: array();
        
        $books_read = 0;
        $reviews = 0;
        $videos = 0;
        $penalty_count = 0;
        
        foreach ($loan_history as $loan) {
            $loan_time = isset($loan['loan_date']) ? strtotime($loan['loan_date']) : 0;
            if ($loan['status'] === 'returned' && $loan_time >= $since && $loan_time <= $until) {
                $books_read++;
                $total_books++;
            }
        }
        
        foreach ($reading_log as $log) {
            $log_time = isset($log['date']) ? strtotime($log['date']) : 0;
            if ($log_time >= $since && $log_time <= $until) {
                if (!empty($log['review'])) { $reviews++; $total_reviews++; }
                if (!empty($log['video_url'])) { $videos++; $total_videos++; }
            }
        }
        
        foreach ($penalties as $p) {
            $p_time = isset($p['date']) ? strtotime($p['date']) : 0;
            if ($p_time >= $since && $p_time <= $until) { $penalty_count++; $total_penalties++; }
        }
        
        $all_data[] = array(
            'name' => $student->display_name,
            'books_read' => $books_read,
            'reviews' => $reviews,
            'videos' => $videos,
            'xp' => $xp,
            'badges' => count($badges),
            'penalties' => $penalty_count,
        );
    }
    
    usort($all_data, function($a, $b) { return $b['books_read'] - $a['books_read']; });
    
    // Alunos inativos
    $inactive_students = array();
    foreach ($all_data as $s) {
        if ($s['books_read'] === 0) {
            $inactive_students[] = $s['name'];
        }
    }
    
    return array(
        'title' => __('Desempenho de Todos os Alunos', 'book-manager'),
        'total_students' => count($students),
        'total_books' => $total_books,
        'total_reviews' => $total_reviews,
        'total_videos' => $total_videos,
        'total_penalties' => $total_penalties,
        'students' => $all_data,
        'inactive_students' => $inactive_students,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

function bm_report_student_performance($user_id, $since, $until) {
    if (!$user_id) return array('error' => __('Aluno não encontrado.', 'book-manager'));
    
    $student = get_userdata($user_id);
    if (!$student) return array('error' => __('Aluno não encontrado.', 'book-manager'));
    
    $loan_history = get_user_meta($user_id, '_bm_loan_history', true) ?: array();
    $reading_log = get_user_meta($user_id, '_bm_reading_log', true) ?: array();
    $xp = bm_get_xp($user_id);
    $badges = get_user_meta($user_id, '_bm_badges', true) ?: array();
    $penalties = get_user_meta($user_id, '_bm_penalties', true) ?: array();
    
    $books_read = 0;
    $books_read_list = array();
    $active_loans = 0;
    $overdue_loans = 0;
    $total_videos = 0;
    $total_reviews = 0;
    
    foreach ($loan_history as $loan) {
        $loan_time = isset($loan['loan_date']) ? strtotime($loan['loan_date']) : 0;
        if ($loan['status'] === 'returned' && $loan_time >= $since && $loan_time <= $until) {
            $books_read++;
            $book_title = get_the_title($loan['book_id']);
            $book_author = get_post_meta($loan['book_id'], '_bm_author', true);
            $return_date = isset($loan['returned_date']) ? date('d/m/Y', strtotime($loan['returned_date'])) : '';
            $books_read_list[] = array(
                'title' => $book_title ?: __('Livro #', 'book-manager') . $loan['book_id'],
                'author' => $book_author,
                'returned_date' => $return_date,
            );
        }
        if ($loan['status'] === 'active') {
            $active_loans++;
            if (isset($loan['due_date']) && strtotime($loan['due_date']) < current_time('timestamp')) {
                $overdue_loans++;
            }
        }
    }
    
    foreach ($reading_log as $log) {
        $log_time = isset($log['date']) ? strtotime($log['date']) : 0;
        if ($log_time >= $since && $log_time <= $until) {
            if (!empty($log['review'])) $total_reviews++;
            if (!empty($log['video_url'])) $total_videos++;
        }
    }
    
    $penalty_count = 0;
    foreach ($penalties as $p) {
        $p_time = isset($p['date']) ? strtotime($p['date']) : 0;
        if ($p_time >= $since && $p_time <= $until) $penalty_count++;
    }
    
    return array(
        'title' => sprintf(__('Desempenho: %s', 'book-manager'), $student->display_name),
        'student_name' => $student->display_name,
        'books_read' => $books_read,
        'books_read_list' => $books_read_list,
        'active_loans' => $active_loans,
        'overdue_loans' => $overdue_loans,
        'reviews' => $total_reviews,
        'videos' => $total_videos,
        'xp' => $xp,
        'badges' => count($badges),
        'penalties' => $penalty_count,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

function bm_report_class_reading($group, $since, $until) {
    if (empty($group)) return array('error' => __('Informe uma turma.', 'book-manager'));
    
    $students = get_users(array('role' => 'bm_student', 'number' => 200));
    $class_students = array();
    
    foreach ($students as $student) {
        $student_group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true);
        if (mb_strtolower(trim($student_group)) === mb_strtolower(trim($group))) {
            $class_students[] = $student;
        }
    }
    
    if (empty($class_students)) return array('error' => __('Nenhum aluno nesta turma.', 'book-manager'));
    
    $student_data = array();
    $total_books = 0;
    $overdue_count = 0;
    $never_read = array();
    
    foreach ($class_students as $student) {
        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
        $books_read = 0;
        $has_overdue = false;
        
        foreach ($loan_history as $loan) {
            $loan_time = isset($loan['loan_date']) ? strtotime($loan['loan_date']) : 0;
            if ($loan['status'] === 'returned' && $loan_time >= $since && $loan_time <= $until) {
                $books_read++;
                $total_books++;
            }
            if ($loan['status'] === 'active' && isset($loan['due_date']) && strtotime($loan['due_date']) < current_time('timestamp')) {
                $has_overdue = true;
                $overdue_count++;
            }
        }
        
        $student_data[] = array(
            'name' => $student->display_name,
            'books_read' => $books_read,
            'has_overdue' => $has_overdue,
        );
        
        if ($books_read === 0) {
            $never_read[] = $student->display_name;
        }
    }
    
    usort($student_data, function($a, $b) { return $b['books_read'] - $a['books_read']; });
    $average = count($class_students) > 0 ? round($total_books / count($class_students), 1) : 0;
    
    return array(
        'title' => sprintf(__('Leitura: %s', 'book-manager'), $group),
        'group' => $group,
        'total_students' => count($class_students),
        'total_books' => $total_books,
        'average' => $average,
        'overdue_count' => $overdue_count,
        'never_read' => $never_read,
        'students' => $student_data,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

function bm_report_active_penalties() {
    $students = get_users(array('role' => 'bm_student', 'number' => 200));
    $active_penalties = array();
    
    foreach ($students as $student) {
        $penalty_active = get_user_meta($student->ID, '_bm_penalty_active', true);
        if ($penalty_active === '1') {
            $penalties = get_user_meta($student->ID, '_bm_penalties', true) ?: array();
            $last = end($penalties);
            $active_penalties[] = array(
                'student_name' => $student->display_name,
                'student_id' => $student->ID,
                'type' => isset($last['type']) ? $last['type'] : '',
                'value' => isset($last['value']) ? $last['value'] : '',
                'note' => isset($last['note']) ? $last['note'] : '',
                'date' => isset($last['date']) ? $last['date'] : '',
                'until' => get_user_meta($student->ID, '_bm_penalty_until', true),
            );
        }
    }
    
    return array(
        'title' => __('Multas Ativas', 'book-manager'),
        'total' => count($active_penalties),
        'penalties' => $active_penalties,
    );
}

function bm_report_genre_ranking($since, $until) {
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    $genre_counts = array();
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        $book_loans = 0;
        
        foreach ($reservations as $r) {
            $loan_time = isset($r['loan_date']) ? strtotime($r['loan_date']) : 0;
            if ($r['status'] !== 'waiting' && $loan_time >= $since && $loan_time <= $until) {
                $book_loans++;
            }
        }
        
        if ($book_loans > 0) {
            $genres = wp_get_post_terms($book->ID, 'bm_genre', array('fields' => 'names'));
            foreach ($genres as $genre) {
                if (!isset($genre_counts[$genre])) $genre_counts[$genre] = 0;
                $genre_counts[$genre] += $book_loans;
            }
        }
    }
    
    arsort($genre_counts);
    
    return array(
        'title' => __('Ranking por Gênero', 'book-manager'),
        'genres' => $genre_counts,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

function bm_report_top_books($since, $until, $genre = '') {
    $args = array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any');
    if (!empty($genre)) {
        $args['tax_query'] = array(array('taxonomy' => 'bm_genre', 'field' => 'name', 'terms' => $genre));
    }
    
    $all_books = get_posts($args);
    $book_data = array();
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        $loan_count = 0;
        $total_days = 0;
        $days_count = 0;
        
        foreach ($reservations as $r) {
            $loan_time = isset($r['loan_date']) ? strtotime($r['loan_date']) : 0;
            if ($r['status'] !== 'waiting' && $loan_time >= $since && $loan_time <= $until) {
                $loan_count++;
                if (isset($r['loan_date']) && isset($r['returned_date'])) {
                    $days = (strtotime($r['returned_date']) - strtotime($r['loan_date'])) / DAY_IN_SECONDS;
                    if ($days > 0) { $total_days += $days; $days_count++; }
                }
            }
        }
        
        if ($loan_count > 0) {
            $avg_days = $days_count > 0 ? round($total_days / $days_count, 1) : 0;
            $book_data[] = array(
                'book_id' => $book->ID,
                'title' => $book->post_title,
                'author' => get_post_meta($book->ID, '_bm_author', true),
                'loans' => $loan_count,
                'avg_days' => $avg_days,
            );
        }
    }
    
    usort($book_data, function($a, $b) { return $b['loans'] - $a['loans']; });
    $book_data = array_slice($book_data, 0, 20);
    
    return array(
        'title' => __('Livros Mais Emprestados', 'book-manager'),
        'books' => $book_data,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

function bm_report_reading_trend($group, $since, $until) {
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    $months = array();
    
    $current = $since;
    while ($current <= $until) {
        $key = date('Y-m', $current);
        $months[$key] = 0;
        $current = strtotime('+1 month', $current);
    }
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        foreach ($reservations as $r) {
            $loan_time = isset($r['loan_date']) ? strtotime($r['loan_date']) : 0;
            if ($r['status'] !== 'waiting' && $r['status'] !== 'rejected' && $loan_time >= $since && $loan_time <= $until) {
                $key = date('Y-m', $loan_time);
                if (isset($months[$key])) {
                    $months[$key]++;
                }
            }
        }
    }
    
    return array(
        'title' => __('Tendência de Leitura', 'book-manager'),
        'months' => $months,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

function bm_report_custom($args, $since, $until) {
    $columns = isset($args['custom_columns']) ? $args['custom_columns'] : array('name', 'books_read');
    $sort_by = isset($args['custom_sort']) ? $args['custom_sort'] : 'name';
    $filter_group = isset($args['custom_filter_group']) ? $args['custom_filter_group'] : '';
    
    $students = get_users(array('role' => 'bm_student', 'number' => 200));
    $rows = array();
    
    foreach ($students as $student) {
        $group = get_user_meta($student->ID, '_bm_user_' . sanitize_key('Turma'), true);
        
        if (!empty($filter_group) && mb_strtolower(trim($group)) !== mb_strtolower(trim($filter_group))) {
            continue;
        }
        
        $loan_history = get_user_meta($student->ID, '_bm_loan_history', true) ?: array();
        $reading_log = get_user_meta($student->ID, '_bm_reading_log', true) ?: array();
        $xp = bm_get_xp($student->ID);
        $badges = get_user_meta($student->ID, '_bm_badges', true) ?: array();
        $penalties = get_user_meta($student->ID, '_bm_penalties', true) ?: array();
        
        $books_read = 0;
        $reviews = 0;
        $videos = 0;
        
        foreach ($loan_history as $loan) {
            $loan_time = isset($loan['loan_date']) ? strtotime($loan['loan_date']) : 0;
            if ($loan['status'] === 'returned' && $loan_time >= $since && $loan_time <= $until) {
                $books_read++;
            }
        }
        
        foreach ($reading_log as $log) {
            $log_time = isset($log['date']) ? strtotime($log['date']) : 0;
            if ($log_time >= $since && $log_time <= $until) {
                if (!empty($log['review'])) $reviews++;
                if (!empty($log['video_url'])) $videos++;
            }
        }
        
        $penalty_count = 0;
        foreach ($penalties as $p) {
            $p_time = isset($p['date']) ? strtotime($p['date']) : 0;
            if ($p_time >= $since && $p_time <= $until) $penalty_count++;
        }
        
        $row = array();
        if (in_array('name', $columns)) $row['name'] = $student->display_name;
        if (in_array('group', $columns)) $row['group'] = $group ?: '—';
        if (in_array('books_read', $columns)) $row['books_read'] = $books_read;
        if (in_array('reviews', $columns)) $row['reviews'] = $reviews;
        if (in_array('videos', $columns)) $row['videos'] = $videos;
        if (in_array('xp', $columns)) $row['xp'] = $xp;
        if (in_array('badges', $columns)) $row['badges'] = count($badges);
        if (in_array('penalties', $columns)) $row['penalties'] = $penalty_count;
        
        $rows[] = $row;
    }
    
    if ($sort_by === 'xp') {
        usort($rows, function($a, $b) { return ($b['xp'] ?? 0) - ($a['xp'] ?? 0); });
    } elseif ($sort_by === 'books_read') {
        usort($rows, function($a, $b) { return ($b['books_read'] ?? 0) - ($a['books_read'] ?? 0); });
    } else {
        usort($rows, function($a, $b) { return strcmp($a['name'] ?? '', $b['name'] ?? ''); });
    }
    
    return array(
        'title' => __('Relatório Configurável', 'book-manager'),
        'columns' => $columns,
        'rows' => $rows,
        'period_start' => date('d/m/Y', $since),
        'period_end' => date('d/m/Y', $until),
    );
}

// ==========================================
// FASE 31: RENDERIZAÇÃO DE RELATÓRIOS
// ==========================================

function bm_render_report_html($report) {
    if (isset($report['error'])) {
        return '<p style="color:#dc3545;">' . esc_html($report['error']) . '</p>';
    }
    
    $html = '<h2>' . esc_html($report['title']) . '</h2>';
    
    if (isset($report['period_start'])) {
        $html .= '<p style="color:#666;margin-bottom:15px;">' . esc_html($report['period_start']) . ' — ' . esc_html($report['period_end']) . '</p>';
    }
    
    // Visão Geral
    if (isset($report['total_loans'])) {
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;">';
        $html .= bm_render_stat_card(__('Empréstimos', 'book-manager'), $report['total_loans'], '#0073aa');
        $html .= bm_render_stat_card(__('Devoluções', 'book-manager'), $report['total_returns'], '#46b450');
        $html .= bm_render_stat_card(__('Em Atraso', 'book-manager'), $report['total_overdue'], '#dc3545');
        $html .= bm_render_stat_card(__('Reservas Pendentes', 'book-manager'), $report['total_reservations'], '#f0ad4e');
        $html .= '</div>';
    }
    
    // Desempenho de Todos os Alunos
    if (isset($report['total_students']) && !isset($report['student_name']) && !isset($report['group'])) {
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;">';
        $html .= bm_render_stat_card(__('Alunos', 'book-manager'), $report['total_students'], '#0073aa');
        $html .= bm_render_stat_card(__('Livros Lidos', 'book-manager'), $report['total_books'], '#46b450');
        $html .= bm_render_stat_card(__('Resenhas', 'book-manager'), $report['total_reviews'], '#f0ad4e');
        $html .= bm_render_stat_card(__('Vídeos', 'book-manager'), $report['total_videos'], '#e4405f');
        $html .= bm_render_stat_card(__('Multas', 'book-manager'), $report['total_penalties'], '#dc3545');
        $html .= '</div>';
        
        if (!empty($report['students'])) {
            $html .= '<table class="wp-list-table widefat fixed striped">';
            $html .= '<thead><tr><th>' . __('Aluno', 'book-manager') . '</th><th>' . __('Livros Lidos', 'book-manager') . '</th><th>' . __('Resenhas', 'book-manager') . '</th><th>' . __('Vídeos', 'book-manager') . '</th><th>' . __('XP', 'book-manager') . '</th><th>' . __('Medalhas', 'book-manager') . '</th><th>' . __('Multas', 'book-manager') . '</th></tr></thead><tbody>';
            foreach ($report['students'] as $s) {
                $html .= '<tr><td>' . esc_html($s['name']) . '</td><td>' . $s['books_read'] . '</td><td>' . $s['reviews'] . '</td><td>' . $s['videos'] . '</td><td>' . $s['xp'] . '</td><td>' . $s['badges'] . '</td><td>' . $s['penalties'] . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
    }
    
    // Desempenho do Aluno
    if (isset($report['student_name'])) {
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;">';
        $html .= bm_render_stat_card(__('Livros Lidos', 'book-manager'), $report['books_read'], '#46b450');
        $html .= bm_render_stat_card(__('Empréstimos Ativos', 'book-manager'), $report['active_loans'], '#0073aa');
        $html .= bm_render_stat_card(__('Em Atraso', 'book-manager'), $report['overdue_loans'], '#dc3545');
        $html .= bm_render_stat_card(__('Resenhas', 'book-manager'), $report['reviews'], '#f0ad4e');
        $html .= bm_render_stat_card(__('Vídeos', 'book-manager'), $report['videos'], '#e4405f');
        $html .= bm_render_stat_card(__('XP', 'book-manager'), $report['xp'], '#ffc107');
        $html .= bm_render_stat_card(__('Medalhas', 'book-manager'), $report['badges'], '#ffc107');
        $html .= bm_render_stat_card(__('Multas', 'book-manager'), $report['penalties'], '#dc3545');
        $html .= '</div>';
        
        if (!empty($report['books_read_list'])) {
            $html .= '<h3>' . __('Livros Lidos', 'book-manager') . '</h3>';
            $html .= '<table class="wp-list-table widefat fixed striped">';
            $html .= '<thead><tr><th>' . __('Livro', 'book-manager') . '</th><th>' . __('Autor', 'book-manager') . '</th><th>' . __('Devolvido em', 'book-manager') . '</th></tr></thead><tbody>';
            foreach ($report['books_read_list'] as $book) {
                $html .= '<tr><td><strong>' . esc_html($book['title']) . '</strong></td><td>' . esc_html($book['author']) . '</td><td>' . esc_html($book['returned_date']) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
    }
    
    // Leitura por Turma
    if (isset($report['group'])) {
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;">';
        $html .= bm_render_stat_card(__('Alunos', 'book-manager'), $report['total_students'], '#0073aa');
        $html .= bm_render_stat_card(__('Livros Lidos', 'book-manager'), $report['total_books'], '#46b450');
        $html .= bm_render_stat_card(__('Média por Aluno', 'book-manager'), $report['average'], '#f0ad4e');
        $html .= bm_render_stat_card(__('Em Atraso', 'book-manager'), $report['overdue_count'], '#dc3545');
        $html .= '</div>';
        
        if (!empty($report['students'])) {
            $html .= '<table class="wp-list-table widefat fixed striped">';
            $html .= '<thead><tr><th>' . __('Aluno', 'book-manager') . '</th><th>' . __('Livros Lidos', 'book-manager') . '</th><th>' . __('Status', 'book-manager') . '</th></tr></thead><tbody>';
            foreach ($report['students'] as $s) {
                $status = isset($s['has_overdue']) && $s['has_overdue'] ? '🔴 ' . __('Atrasado', 'book-manager') : '✅ ' . __('Em dia', 'book-manager');
                $html .= '<tr><td>' . esc_html($s['name']) . '</td><td>' . $s['books_read'] . '</td><td>' . $status . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        
        if (!empty($report['never_read'])) {
            $html .= '<p style="margin-top:10px;color:#dc3545;"><strong>' . __('Alunos que nunca leram:', 'book-manager') . '</strong> ' . esc_html(implode(', ', $report['never_read'])) . '</p>';
        }
    }
    
    // Multas Ativas
    if (isset($report['total']) && isset($report['penalties']) && !isset($report['total_students'])) {
        $html .= bm_render_stat_card(__('Total de Multas Ativas', 'book-manager'), $report['total'], '#dc3545');
        
        if (!empty($report['penalties'])) {
            $html .= '<table class="wp-list-table widefat fixed striped" style="margin-top:15px;">';
            $html .= '<thead><tr><th>' . __('Aluno', 'book-manager') . '</th><th>' . __('Tipo', 'book-manager') . '</th><th>' . __('Descrição', 'book-manager') . '</th><th>' . __('Data', 'book-manager') . '</th><th>' . __('Até', 'book-manager') . '</th></tr></thead><tbody>';
            foreach ($report['penalties'] as $p) {
                $type_label = $p['type'] === 'warning' ? __('Advertência', 'book-manager') : ($p['type'] === 'suspension' ? __('Suspensão', 'book-manager') : __('Multa', 'book-manager'));
                $until = !empty($p['until']) ? date('d/m/Y', strtotime($p['until'])) : '—';
                $html .= '<tr><td>' . esc_html($p['student_name']) . '</td><td>' . $type_label . '</td><td>' . esc_html($p['note']) . '</td><td>' . date('d/m/Y', strtotime($p['date'])) . '</td><td>' . $until . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
    }
    
    // Ranking por Gênero
    if (isset($report['genres'])) {
        if (!empty($report['genres'])) {
            $html .= bm_render_bar_chart($report['genres'], __('Empréstimos por Gênero', 'book-manager'));
        } else {
            $html .= '<p>' . __('Nenhum empréstimo no período.', 'book-manager') . '</p>';
        }
    }
    
    // Livros Mais Emprestados
    if (isset($report['books'])) {
        if (!empty($report['books'])) {
            $html .= '<table class="wp-list-table widefat fixed striped">';
            $html .= '<thead><tr><th>#</th><th>' . __('Livro', 'book-manager') . '</th><th>' . __('Autor', 'book-manager') . '</th><th>' . __('Empréstimos', 'book-manager') . '</th><th>' . __('Tempo Médio', 'book-manager') . '</th></tr></thead><tbody>';
            foreach ($report['books'] as $i => $book) {
                $html .= '<tr><td>' . ($i + 1) . '</td><td><strong>' . esc_html($book['title']) . '</strong></td><td>' . esc_html($book['author']) . '</td><td>' . $book['loans'] . '</td><td>' . ($book['avg_days'] > 0 ? $book['avg_days'] . ' ' . __('dias', 'book-manager') : '—') . '</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p>' . __('Nenhum empréstimo no período.', 'book-manager') . '</p>';
        }
    }
    
    // Tendência de Leitura
    if (isset($report['months'])) {
        if (!empty($report['months']) && array_sum($report['months']) > 0) {
            $html .= bm_render_bar_chart($report['months'], __('Empréstimos por Mês', 'book-manager'));
        } else {
            $html .= '<p>' . __('Nenhum empréstimo no período.', 'book-manager') . '</p>';
        }
    }
    
    // Configurável
    if (isset($report['columns']) && isset($report['rows'])) {
        if (!empty($report['rows'])) {
            $col_labels = array(
                'name' => __('Nome', 'book-manager'),
                'group' => __('Turma', 'book-manager'),
                'books_read' => __('Livros Lidos', 'book-manager'),
                'reviews' => __('Resenhas', 'book-manager'),
                'videos' => __('Vídeos', 'book-manager'),
                'xp' => __('XP', 'book-manager'),
                'badges' => __('Medalhas', 'book-manager'),
                'penalties' => __('Multas', 'book-manager'),
            );
            $html .= '<table class="wp-list-table widefat fixed striped">';
            $html .= '<thead><tr>';
            foreach ($report['columns'] as $col) {
                $html .= '<th>' . (isset($col_labels[$col]) ? $col_labels[$col] : $col) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($report['rows'] as $row) {
                $html .= '<tr>';
                foreach ($report['columns'] as $col) {
                    $html .= '<td>' . esc_html($row[$col] ?? '—') . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p>' . __('Nenhum aluno encontrado.', 'book-manager') . '</p>';
        }
    }
    
    return $html;
}

function bm_render_stat_card($label, $value, $color) {
    return '<div style="background:#fff;padding:15px;border-radius:6px;text-align:center;border-left:4px solid ' . $color . ';box-shadow:0 1px 4px rgba(0,0,0,0.08);">'
        . '<div style="font-size:28px;font-weight:bold;color:' . $color . ';">' . esc_html($value) . '</div>'
        . '<div style="font-size:13px;color:#666;margin-top:4px;">' . esc_html($label) . '</div>'
        . '</div>';
}

function bm_render_bar_chart($data, $title) {
    $max = max($data);
    if ($max <= 0) return '';
    
    $html = '<h3>' . esc_html($title) . '</h3>';
    $html .= '<div style="max-width:600px;margin-bottom:20px;">';
    
    foreach ($data as $label => $value) {
        $pct = $max > 0 ? round(($value / $max) * 100) : 0;
        $html .= '<div style="display:flex;align-items:center;margin:8px 0;">';
        $html .= '<div style="width:120px;font-size:12px;text-align:right;padding-right:10px;">' . esc_html($label) . '</div>';
        $html .= '<div style="flex:1;background:#eee;border-radius:4px;height:24px;overflow:hidden;">';
        $html .= '<div style="background:#0073aa;height:100%;width:' . $pct . '%;border-radius:4px;display:flex;align-items:center;padding-left:8px;">';
        $html .= '<span style="color:#fff;font-size:11px;font-weight:bold;">' . $value . '</span>';
        $html .= '</div></div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

// ==========================================
// FASE 31: SHORTCODE RELATÓRIO DE TURMA
// ==========================================
function bm_class_report_shortcode($atts) {
    if (!is_user_logged_in()) return '<p>' . __('Faça login para acessar.', 'book-manager') . '</p>';
    
    $atts = shortcode_atts(array(
        'group' => '',
        'period' => 'year',
    ), $atts);
    
    $now = current_time('timestamp');
    switch ($atts['period']) {
        case 'month': $since = strtotime('-30 days', $now); break;
        case 'bimester': $since = strtotime('-60 days', $now); break;
        case 'year': $since = strtotime('-365 days', $now); break;
        default: $since = strtotime('-365 days', $now);
    }
    $until = $now;
    
    $group = $atts['group'];
    if (empty($group)) {
        $user_id = get_current_user_id();
        $group = get_user_meta($user_id, '_bm_user_' . sanitize_key('Turma'), true);
    }
    
    if (empty($group)) return '<p>' . __('Turma não informada.', 'book-manager') . '</p>';
    
    $report = bm_report_class_reading($group, $since, $until);
    return bm_render_report_html($report);
}
add_shortcode('bm_class_report', 'bm_class_report_shortcode');

// ==========================================
// FASE 31: ESTATÍSTICAS DE USO
// ==========================================
function bm_get_library_stats($period = 'month', $filters = array()) {
    $cache_key = 'bm_library_stats_' . $period;
    $cached = bm_get_cached($cache_key);
    if ($cached) {
        return bm_filter_stats_by_display($cached, $filters);
    }
    
    $now = current_time('timestamp');
    switch ($period) {
        case 'week': $since = strtotime('-7 days', $now); $prev_since = strtotime('-14 days', $now); $prev_until = strtotime('-7 days', $now); break;
        case 'bimester': $since = strtotime('-60 days', $now); $prev_since = strtotime('-120 days', $now); $prev_until = strtotime('-60 days', $now); break;
        case 'year': $since = strtotime('-365 days', $now); $prev_since = strtotime('-730 days', $now); $prev_until = strtotime('-365 days', $now); break;
        case 'month':
        default: $since = strtotime('-30 days', $now); $prev_since = strtotime('-60 days', $now); $prev_until = strtotime('-30 days', $now); break;
    }
    $until = $now;
    
    $all_books = get_posts(array('post_type' => 'bm_book', 'posts_per_page' => -1, 'post_status' => 'any'));
    $stats = array();
    
    $total_loans = 0;
    $prev_loans = 0;
    $book_counts = array();
    $student_counts = array();
    $genre_counts = array();
    
    foreach ($all_books as $book) {
        $reservations = get_post_meta($book->ID, '_bm_reservations', true) ?: array();
        foreach ($reservations as $r) {
            $loan_time = isset($r['loan_date']) ? strtotime($r['loan_date']) : 0;
            if ($r['status'] !== 'waiting' && $r['status'] !== 'rejected') {
                if ($loan_time >= $since && $loan_time <= $until) {
                    $total_loans++;
                    if (!isset($book_counts[$book->ID])) $book_counts[$book->ID] = array('title' => $book->post_title, 'count' => 0);
                    $book_counts[$book->ID]['count']++;
                    $user_data = get_userdata($r['user_id']);
                    if ($user_data && in_array('bm_student', (array) $user_data->roles)) {
                        if (!isset($student_counts[$r['user_id']])) $student_counts[$r['user_id']] = 0;
                        $student_counts[$r['user_id']]++;
                    }
                    $genres = wp_get_post_terms($book->ID, 'bm_genre', array('fields' => 'names'));
                    foreach ($genres as $g) {
                        if (!isset($genre_counts[$g])) $genre_counts[$g] = 0;
                        $genre_counts[$g]++;
                    }
                }
                if ($loan_time >= $prev_since && $loan_time <= $prev_until) {
                    $prev_loans++;
                }
            }
        }
    }
    
    $stats['total_loans'] = $total_loans;
    $stats['total_loans_prev'] = $prev_loans;
    
    if (!empty($book_counts)) {
        arsort($book_counts);
        $top_book = reset($book_counts);
        $stats['top_book'] = $top_book;
    }
    
    if (!empty($student_counts)) {
        arsort($student_counts);
        foreach ($student_counts as $sid => $count) {
            $student = get_userdata($sid);
            if ($student && !empty($student->display_name)) {
                $stats['top_student'] = array('name' => $student->display_name, 'count' => $count);
                break;
            }
        }
    }
    
    if (!empty($genre_counts)) {
        arsort($genre_counts);
        $top_genre = key($genre_counts);
        $stats['top_genre'] = array('name' => $top_genre, 'count' => $genre_counts[$top_genre]);
    }
    
    $stats['period'] = $period;
    bm_set_cached($cache_key, $stats, 3600);
    return bm_filter_stats_by_display($stats, $filters);
}

function bm_filter_stats_by_display($stats, $filters) {
    $result = $stats;
    
    if (isset($filters['loans']) && !$filters['loans']) {
        unset($result['total_loans']);
    }
    if (isset($filters['top_book']) && !$filters['top_book']) {
        unset($result['top_book']);
    }
    if (isset($filters['top_student']) && !$filters['top_student']) {
        unset($result['top_student']);
    }
    if (isset($filters['top_genre']) && !$filters['top_genre']) {
        unset($result['top_genre']);
    }
    
    return $result;
}

function bm_library_stats_shortcode($atts) {
    $period = isset($_GET['bm_period']) ? sanitize_text_field($_GET['bm_period']) : 'month';
    $show_loans = isset($_GET['bm_loans']) ? true : false;
    $show_top_book = isset($_GET['bm_top_book']) ? true : false;
    $show_top_student = isset($_GET['bm_top_student']) ? true : false;
    $show_top_genre = isset($_GET['bm_top_genre']) ? true : false;
    
    if (!isset($_GET['bm_period']) && !isset($_GET['bm_loans']) && !isset($_GET['bm_top_book']) && !isset($_GET['bm_top_student']) && !isset($_GET['bm_top_genre'])) {
        $show_loans = true;
        $show_top_book = true;
        $show_top_student = true;
        $show_top_genre = true;
    }
    
    $filters = array(
        'loans' => $show_loans,
        'top_book' => $show_top_book,
        'top_student' => $show_top_student,
        'top_genre' => $show_top_genre,
    );
    
    $stats = bm_get_library_stats($period, $filters);
    
    $period_labels = array('week' => __('esta semana', 'book-manager'), 'month' => __('este mês', 'book-manager'), 'bimester' => __('este bimestre', 'book-manager'), 'year' => __('este ano', 'book-manager'));
    $period_label = isset($period_labels[$period]) ? $period_labels[$period] : $period;
    
    $trend = function($current, $prev) {
        if ($prev == 0) return '';
        $diff = round((($current - $prev) / $prev) * 100);
        if ($diff > 0) return ' 📈+' . $diff . '%';
        if ($diff < 0) return ' 📉' . $diff . '%';
        return ' ➡️0%';
    };
    
    ob_start();
    ?>
    <div style="max-width:700px;margin:20px auto;padding:20px;">
        <h2 style="text-align:center;">📊 <?php printf(__('Estatísticas de %s', 'book-manager'), $period_label); ?></h2>
        
        <form method="get" style="background:#f9f9f9;padding:15px;border-radius:8px;margin:15px 0;display:flex;flex-wrap:wrap;gap:15px;align-items:end;">
            <div>
                <label><strong><?php _e('Período', 'book-manager'); ?></strong></label>
                <select name="bm_period" style="width:140px;">
                    <option value="week" <?php selected($period, 'week'); ?>><?php _e('Última Semana', 'book-manager'); ?></option>
                    <option value="month" <?php selected($period, 'month'); ?>><?php _e('Último Mês', 'book-manager'); ?></option>
                    <option value="bimester" <?php selected($period, 'bimester'); ?>><?php _e('Último Bimestre', 'book-manager'); ?></option>
                    <option value="year" <?php selected($period, 'year'); ?>><?php _e('Último Ano', 'book-manager'); ?></option>
                </select>
            </div>
            <div>
                <strong><?php _e('Mostrar:', 'book-manager'); ?></strong>
                <label style="margin-left:8px;"><input type="checkbox" name="bm_loans" value="1" <?php checked($show_loans); ?>> <?php _e('Empréstimos', 'book-manager'); ?></label>
                <label style="margin-left:8px;"><input type="checkbox" name="bm_top_book" value="1" <?php checked($show_top_book); ?>> <?php _e('Livro Popular', 'book-manager'); ?></label>
                <label style="margin-left:8px;"><input type="checkbox" name="bm_top_student" value="1" <?php checked($show_top_student); ?>> <?php _e('Aluno Ativo', 'book-manager'); ?></label>
                <label style="margin-left:8px;"><input type="checkbox" name="bm_top_genre" value="1" <?php checked($show_top_genre); ?>> <?php _e('Gênero Popular', 'book-manager'); ?></label>
            </div>
            <div>
                <button type="submit" class="bm-btn-filter"><?php _e('Filtrar', 'book-manager'); ?></button>
                <a href="<?php the_permalink(); ?>" class="bm-btn-clear"><?php _e('Limpar', 'book-manager'); ?></a>
            </div>
        </form>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin:20px 0;">
            <?php if (isset($stats['total_loans']) && $show_loans): ?>
                <div style="background:#fff;padding:15px;border-radius:8px;text-align:center;border-left:4px solid #0073aa;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                    <div style="font-size:28px;font-weight:bold;color:#0073aa;"><?php echo $stats['total_loans']; ?></div>
                    <div style="font-size:13px;color:#666;"><?php _e('Empréstimos', 'book-manager'); echo $trend($stats['total_loans'], $stats['total_loans_prev']); ?></div>
                </div>
            <?php endif; ?>
            <?php if (isset($stats['top_book']) && $show_top_book): ?>
                <div style="background:#fff;padding:15px;border-radius:8px;text-align:center;border-left:4px solid #46b450;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                    <div style="font-size:14px;font-weight:bold;color:#111;"><?php echo esc_html($stats['top_book']['title']); ?></div>
                    <div style="font-size:13px;color:#666;"><?php printf(__('Livro mais emprestado (%d)', 'book-manager'), $stats['top_book']['count']); ?></div>
                </div>
            <?php endif; ?>
            <?php if (isset($stats['top_student']) && $show_top_student): ?>
                <div style="background:#fff;padding:15px;border-radius:8px;text-align:center;border-left:4px solid #f0ad4e;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                    <div style="font-size:14px;font-weight:bold;color:#111;"><?php echo esc_html($stats['top_student']['name']); ?></div>
                    <div style="font-size:13px;color:#666;"><?php printf(__('Aluno mais ativo (%d)', 'book-manager'), $stats['top_student']['count']); ?></div>
                </div>
            <?php endif; ?>
            <?php if (isset($stats['top_genre']) && $show_top_genre): ?>
                <div style="background:#fff;padding:15px;border-radius:8px;text-align:center;border-left:4px solid #e4405f;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
                    <div style="font-size:14px;font-weight:bold;color:#111;"><?php echo esc_html($stats['top_genre']['name']); ?></div>
                    <div style="font-size:13px;color:#666;"><?php printf(__('Gênero mais lido (%d)', 'book-manager'), $stats['top_genre']['count']); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('bm_library_stats', 'bm_library_stats_shortcode');


// ==========================================
// FASE 31: EXPORTAÇÃO PDF
// ==========================================
function bm_ajax_export_report_pdf() {
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) wp_die(__('Sem permissão.', 'book-manager'));
    
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'overview';
    $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
    $date_start = isset($_GET['date_start']) ? sanitize_text_field($_GET['date_start']) : '';
    $date_end = isset($_GET['date_end']) ? sanitize_text_field($_GET['date_end']) : '';
    $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
    $group = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : '';
    
    $args = array(
        'type' => $type,
        'period' => $period,
        'date_start' => $date_start,
        'date_end' => $date_end,
        'subject_id' => $subject_id,
        'group' => $group,
    );
    $report = bm_generate_report($args);
    $report_html = bm_render_report_html($report);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($report['title']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .no-print { text-align: center; margin: 20px 0; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 13px; }
            th { background: #f5f5f5; }
            @media print {
                .no-print { display: none; }
                body { margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="padding:20px;background:#f9f9f9;margin-bottom:20px;">
            <h2><?php _e('Exportar Relatório', 'book-manager'); ?></h2>
            <p><?php _e('Pressione Ctrl+P para imprimir ou salvar como PDF.', 'book-manager'); ?></p>
            <button onclick="window.print()" style="padding:10px 20px;background:#111;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:16px;">🖨️ <?php _e('Imprimir / Salvar PDF', 'book-manager'); ?></button>
        </div>
        <?php echo $report_html; ?>
    </body>
    </html>
    <?php
    exit;
}
add_action('wp_ajax_bm_export_report_pdf', 'bm_ajax_export_report_pdf');


// ==========================================
// TAREFA 1: ENDPOINT JSON PARA RELATÓRIOS DINÂMICOS
// ==========================================
function bm_ajax_get_report_data() {
    check_ajax_referer('bm_reports_nonce', 'nonce');
    
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Sem permissão.', 'book-manager')));
    }
    
    $args = array(
        'type'       => isset($_POST['bm_report_type']) ? sanitize_text_field($_POST['bm_report_type']) : 'overview',
        'period'     => isset($_POST['bm_period']) ? sanitize_text_field($_POST['bm_period']) : 'month',
        'date_start' => isset($_POST['bm_date_start']) ? sanitize_text_field($_POST['bm_date_start']) : '',
        'date_end'   => isset($_POST['bm_date_end']) ? sanitize_text_field($_POST['bm_date_end']) : '',
        'subject'    => isset($_POST['bm_subject']) ? sanitize_text_field($_POST['bm_subject']) : 'all',
        'subject_id' => isset($_POST['bm_subject_id']) ? intval($_POST['bm_subject_id']) : 0,
        'group'      => isset($_POST['bm_group']) ? sanitize_text_field($_POST['bm_group']) : '',
        'genre'      => isset($_POST['bm_genre']) ? sanitize_text_field($_POST['bm_genre']) : '',
        'custom_columns' => isset($_POST['bm_custom_columns']) ? array_map('sanitize_text_field', $_POST['bm_custom_columns']) : array('name', 'books_read'),
        'custom_sort' => isset($_POST['bm_custom_sort']) ? sanitize_text_field($_POST['bm_custom_sort']) : 'name',
    );
    
    $report = bm_generate_report($args);
    
    $report['_meta'] = array(
        'type'       => $args['type'],
        'period'     => $args['period'],
        'subject'    => $args['subject'],
        'generated_at' => current_time('mysql'),
    );
    
    wp_send_json_success($report);
}
add_action('wp_ajax_bm_get_report_data', 'bm_ajax_get_report_data');
function bm_ajax_save_dashboard_order() {
    check_ajax_referer('bm_reports_nonce', 'nonce');
    if (!current_user_can('edit_bm_books') && !current_user_can('manage_options')) {
        wp_die(__('Sem permissão.', 'book-manager'));
    }
    
    $order = isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : '';
    $user_id = get_current_user_id();
    
    if (!empty($order)) {
        update_user_meta($user_id, '_bm_dashboard_order', $order);
        wp_send_json_success(array('message' => 'Ordem salva.'));
    } else {
        wp_send_json_error(array('message' => 'Ordem vazia.'));
    }
}
add_action('wp_ajax_bm_save_dashboard_order', 'bm_ajax_save_dashboard_order');