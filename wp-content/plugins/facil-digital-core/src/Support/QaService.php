<?php

declare(strict_types=1);

namespace FacilDigital\Core\Support;

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Questions\QuestionRepository;
use FacilDigital\Core\Ranking\RankingService;
use FacilDigital\Core\Security\SecurityAudit;

final class QaService
{
    /** @return array<string,mixed> */
    public function run(): array
    {
        global $wpdb;
        $q = Database::table('questions');
        $qo = Database::table('question_options');
        $s = Database::table('simulations');
        $sq = Database::table('simulation_questions');
        $a = Database::table('attempts');
        $aa = Database::table('attempt_answers');
        $e = Database::table('entitlements');
        $p = Database::table('pdf_files');
        $d = Database::table('downloads');

        $integrity = [
            'orphan_question_options' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$qo} x LEFT JOIN {$q} q ON q.id=x.question_id WHERE q.id IS NULL"),
            'orphan_simulation_questions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$sq} x LEFT JOIN {$s} s ON s.id=x.simulation_id LEFT JOIN {$q} q ON q.id=x.question_id WHERE s.id IS NULL OR q.id IS NULL"),
            'orphan_attempt_answers' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$aa} x LEFT JOIN {$a} a ON a.id=x.attempt_id LEFT JOIN {$q} q ON q.id=x.question_id WHERE a.id IS NULL OR q.id IS NULL"),
            'orphan_pdfs' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p} x LEFT JOIN {$e} e ON e.id=x.entitlement_id WHERE e.id IS NULL"),
            'orphan_downloads' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$d} x LEFT JOIN {$p} p ON p.id=x.pdf_file_id LEFT JOIN {$e} e ON e.id=x.entitlement_id WHERE p.id IS NULL OR e.id IS NULL"),
            'published_simulations_without_questions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$s} s LEFT JOIN {$sq} x ON x.simulation_id=s.id WHERE s.status='published' GROUP BY s.id HAVING COUNT(x.id)=0 LIMIT 1"),
            'invalid_completed_percentages' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$a} WHERE status='completed' AND (percentage < 0 OR percentage > 100)"),
        ];

        $start = microtime(true);
        (new QuestionRepository())->list([], 200, 0);
        $questionMs = round((microtime(true) - $start) * 1000, 2);

        $start = microtime(true);
        (new RankingService())->general(100);
        $rankingMs = round((microtime(true) - $start) * 1000, 2);

        $security = (new SecurityAudit())->run();
        $errors = [];
        foreach ($integrity as $key => $value) {
            if ($value !== 0) {
                $errors[] = $key;
            }
        }
        if (!$security['ready']) {
            $errors[] = 'security';
        }

        $warnings = $security['warnings'];
        if ($questionMs > 5000) {
            $warnings[] = 'Consulta de questões acima de 5s.';
        }
        if ($rankingMs > 5000) {
            $warnings[] = 'Ranking acima de 5s.';
        }

        return [
            'ready' => $errors === [],
            'schema' => Database::installedVersion(),
            'integrity' => $integrity,
            'benchmarks_ms' => [
                'questions_200' => $questionMs,
                'ranking_100' => $rankingMs,
            ],
            'security_ready' => $security['ready'],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
