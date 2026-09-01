<?php

declare(strict_types=1);

namespace FacilDigital\Core\Core;

final class Installer
{
    public static function installSchema(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $questions = Database::table('questions');
        $questionOptions = Database::table('question_options');
        $simulations = Database::table('simulations');
        $simulationQuestions = Database::table('simulation_questions');
        $attempts = Database::table('attempts');
        $attemptAnswers = Database::table('attempt_answers');
        $entitlements = Database::table('entitlements');
        $pdfFiles = Database::table('pdf_files');
        $downloads = Database::table('downloads');

        $queries = [
            "CREATE TABLE {$questions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                contest_term_id bigint(20) unsigned DEFAULT NULL,
                question_type varchar(32) NOT NULL DEFAULT 'multiple_choice',
                statement longtext NOT NULL,
                explanation longtext DEFAULT NULL,
                board varchar(191) DEFAULT NULL,
                position_name varchar(191) DEFAULT NULL,
                subject varchar(191) DEFAULT NULL,
                topic varchar(191) DEFAULT NULL,
                difficulty varchar(32) NOT NULL DEFAULT 'medium',
                exam_year smallint(5) unsigned DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                image_attachment_id bigint(20) unsigned DEFAULT NULL,
                created_by bigint(20) unsigned DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY contest_term_id (contest_term_id),
                KEY status (status),
                KEY subject (subject),
                KEY board (board)
            ) {$charsetCollate};",
            "CREATE TABLE {$questionOptions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                question_id bigint(20) unsigned NOT NULL,
                option_key varchar(16) NOT NULL,
                option_text longtext NOT NULL,
                is_correct tinyint(1) NOT NULL DEFAULT 0,
                sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY question_option (question_id,option_key),
                KEY question_id (question_id),
                KEY is_correct (is_correct)
            ) {$charsetCollate};",
            "CREATE TABLE {$simulations} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                slug varchar(191) NOT NULL,
                description longtext DEFAULT NULL,
                contest_term_id bigint(20) unsigned DEFAULT NULL,
                position_name varchar(191) DEFAULT NULL,
                duration_seconds int(10) unsigned NOT NULL DEFAULT 0,
                question_count int(10) unsigned NOT NULL DEFAULT 0,
                attempt_limit smallint(5) unsigned DEFAULT NULL,
                minimum_score decimal(7,2) NOT NULL DEFAULT 0.00,
                show_answer_key tinyint(1) NOT NULL DEFAULT 1,
                comment_policy varchar(32) NOT NULL DEFAULT 'after_finish',
                ranking_enabled tinyint(1) NOT NULL DEFAULT 0,
                selection_mode varchar(32) NOT NULL DEFAULT 'manual',
                status varchar(20) NOT NULL DEFAULT 'draft',
                created_by bigint(20) unsigned DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY slug (slug),
                KEY contest_term_id (contest_term_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$simulationQuestions} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                simulation_id bigint(20) unsigned NOT NULL,
                question_id bigint(20) unsigned NOT NULL,
                sort_order int(10) unsigned NOT NULL DEFAULT 0,
                points decimal(8,2) NOT NULL DEFAULT 1.00,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY simulation_question (simulation_id,question_id),
                KEY simulation_id (simulation_id),
                KEY question_id (question_id),
                KEY simulation_order (simulation_id,sort_order)
            ) {$charsetCollate};",
            "CREATE TABLE {$attempts} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                simulation_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'in_progress',
                started_at datetime NOT NULL,
                expires_at datetime DEFAULT NULL,
                submitted_at datetime DEFAULT NULL,
                score decimal(10,2) NOT NULL DEFAULT 0.00,
                percentage decimal(7,2) NOT NULL DEFAULT 0.00,
                correct_count int(10) unsigned NOT NULL DEFAULT 0,
                incorrect_count int(10) unsigned NOT NULL DEFAULT 0,
                unanswered_count int(10) unsigned NOT NULL DEFAULT 0,
                elapsed_seconds int(10) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY simulation_id (simulation_id),
                KEY user_id (user_id),
                KEY user_simulation (user_id,simulation_id),
                KEY simulation_status (simulation_id,status),
                KEY started_at (started_at)
            ) {$charsetCollate};",
            "CREATE TABLE {$attemptAnswers} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                attempt_id bigint(20) unsigned NOT NULL,
                question_id bigint(20) unsigned NOT NULL,
                selected_option_id bigint(20) unsigned DEFAULT NULL,
                answer_value longtext DEFAULT NULL,
                is_correct tinyint(1) DEFAULT NULL,
                answered_at datetime DEFAULT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY attempt_question (attempt_id,question_id),
                KEY attempt_id (attempt_id),
                KEY question_id (question_id),
                KEY selected_option_id (selected_option_id)
            ) {$charsetCollate};",
            "CREATE TABLE {$entitlements} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned NOT NULL,
                order_id bigint(20) unsigned NOT NULL,
                order_item_id bigint(20) unsigned DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                source varchar(32) NOT NULL DEFAULT 'woocommerce',
                granted_at datetime NOT NULL,
                revoked_at datetime DEFAULT NULL,
                expires_at datetime DEFAULT NULL,
                revocation_reason varchar(191) DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY user_product_order (user_id,product_id,order_id),
                KEY order_item_id (order_item_id),
                KEY user_status (user_id,status),
                KEY product_status (product_id,status),
                KEY order_id (order_id)
            ) {$charsetCollate};",
            "CREATE TABLE {$pdfFiles} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                entitlement_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned NOT NULL,
                order_id bigint(20) unsigned NOT NULL,
                product_version varchar(64) NOT NULL DEFAULT '1',
                storage_key varchar(512) NOT NULL,
                file_size bigint(20) unsigned DEFAULT NULL,
                sha256 char(64) DEFAULT NULL,
                tracking_code varchar(64) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                watermark_enabled tinyint(1) NOT NULL DEFAULT 1,
                password_enabled tinyint(1) NOT NULL DEFAULT 1,
                generation_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
                generated_at datetime DEFAULT NULL,
                error_code varchar(64) DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY entitlement_version (entitlement_id,product_version),
                UNIQUE KEY tracking_code (tracking_code),
                KEY user_id (user_id),
                KEY product_id (product_id),
                KEY order_id (order_id),
                KEY status (status)
            ) {$charsetCollate};",
            "CREATE TABLE {$downloads} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                entitlement_id bigint(20) unsigned NOT NULL,
                pdf_file_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned NOT NULL,
                order_id bigint(20) unsigned NOT NULL,
                ip_hash char(64) DEFAULT NULL,
                user_agent_hash char(64) DEFAULT NULL,
                downloaded_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY entitlement_id (entitlement_id),
                KEY pdf_file_id (pdf_file_id),
                KEY product_id (product_id),
                KEY order_id (order_id),
                KEY downloaded_at (downloaded_at)
            ) {$charsetCollate};",
        ];

        foreach ($queries as $query) {
            dbDelta($query);
        }
    }
}
