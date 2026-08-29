<?php

declare(strict_types=1);

namespace FacilDigital\Core\Admin;

use FacilDigital\Core\Core\Database;

final class DashboardService
{
    /** @return array<string,int|float> */
    public function snapshot(): array
    {
        global $wpdb;

        $questions = Database::table('questions');
        $simulations = Database::table('simulations');
        $attempts = Database::table('attempts');
        $pdfFiles = Database::table('pdf_files');
        $downloads = Database::table('downloads');

        $salesToday = 0.0;
        $ordersToday = 0;

        if (function_exists('wc_get_orders')) {
            $start = wp_date('Y-m-d');
            $orders = wc_get_orders([
                'limit' => -1,
                'status' => function_exists('wc_get_is_paid_statuses')
                    ? wc_get_is_paid_statuses()
                    : ['processing', 'completed'],
                'date_paid' => '>=' . $start,
                'return' => 'objects',
            ]);

            if (is_array($orders)) {
                foreach ($orders as $order) {
                    if (!$order instanceof \WC_Order) {
                        continue;
                    }
                    $ordersToday++;
                    $salesToday += (float) $order->get_total();
                }
            }
        }

        $studentCounts = count_users();
        $roles = is_array($studentCounts['avail_roles'] ?? null)
            ? $studentCounts['avail_roles']
            : [];
        $students = (int) ($roles['customer'] ?? 0)
            + (int) ($roles['subscriber'] ?? 0);

        $apostilas = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = 'product'
                   AND p.post_status IN ('publish','private','draft')
                   AND pm.meta_key = %s
                   AND pm.meta_value = 'yes'",
                '_fd_is_apostila'
            )
        );

        return [
            'sales_today' => round($salesToday, 2),
            'orders_today' => $ordersToday,
            'students' => $students,
            'apostilas' => $apostilas,
            'questions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$questions}"),
            'simulations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$simulations}"),
            'attempts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$attempts}"),
            'completed_attempts' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$attempts} WHERE status = 'completed'"
            ),
            'average_percentage' => round((float) $wpdb->get_var(
                "SELECT COALESCE(AVG(percentage),0) FROM {$attempts} WHERE status = 'completed'"
            ), 2),
            'ready_pdfs' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$pdfFiles} WHERE status = 'ready'"
            ),
            'downloads' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$downloads}"),
        ];
    }
}
