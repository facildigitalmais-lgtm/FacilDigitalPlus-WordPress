<?php

declare(strict_types=1);

use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Entitlements\EntitlementRepository;
use FacilDigital\Core\PDFs\DownloadService;

if (!defined('ABSPATH')) {
    exit(1);
}

$seed = get_option('fd_m2_seed', []);
if (!is_array($seed) || $seed === []) {
    throw new RuntimeException('M2 seed ausente.');
}

$userId = (int) ($seed['user_id'] ?? 0);
$otherUserId = (int) ($seed['other_user_id'] ?? 0);
$pdfId = (int) ($seed['pdf_id'] ?? 0);
$entitlementId = (int) ($seed['entitlement_id'] ?? 0);
$orderId = (int) ($seed['order_id'] ?? 0);

$service = new DownloadService();
$authorization = $service->authorize($userId, $pdfId);

try {
    $service->authorize($otherUserId, $pdfId);
    throw new RuntimeException('Outro usuario recebeu acesso ao PDF.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Outro usuario recebeu acesso ao PDF.') {
        throw $exception;
    }
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
$_SERVER['HTTP_USER_AGENT'] = 'FD-M2-Test-Agent';

$service->record($authorization);
$authorization = $service->authorize($userId, $pdfId);
$service->record($authorization);

$limitBlocked = false;
try {
    $service->authorize($userId, $pdfId);
} catch (RuntimeException $exception) {
    $limitBlocked = $exception->getMessage() === 'download_limit_reached';
}

if (!$limitBlocked) {
    throw new RuntimeException('Limite de downloads nao aplicado.');
}

global $wpdb;
$downloadTable = Database::table('downloads');
$rows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT ip_hash, user_agent_hash FROM {$downloadTable} WHERE entitlement_id = %d",
        $entitlementId
    ),
    ARRAY_A
);

if (!is_array($rows) || count($rows) !== 2) {
    throw new RuntimeException('Registros de download incorretos.');
}

foreach ($rows as $row) {
    foreach (['ip_hash', 'user_agent_hash'] as $key) {
        $hash = (string) ($row[$key] ?? '');
        if (strlen($hash) !== 64) {
            throw new RuntimeException('Hash operacional invalido.');
        }
        if (
            str_contains($hash, '203.0.113.50')
            || str_contains($hash, 'FD-M2-Test-Agent')
        ) {
            throw new RuntimeException('PII operacional armazenada em claro.');
        }
    }
}

$repository = new EntitlementRepository();
$repository->revokeByOrder($orderId, 'm2_test');

$revokedBlocked = false;
try {
    $service->authorize($userId, $pdfId);
} catch (RuntimeException $exception) {
    $revokedBlocked = in_array(
        $exception->getMessage(),
        ['download_entitlement_inactive', 'download_unauthorized'],
        true
    );
}

if (!$revokedBlocked) {
    throw new RuntimeException('Entitlement revogado ainda baixou PDF.');
}

$seedRow = $repository->findById($entitlementId);
if (!is_array($seedRow)) {
    throw new RuntimeException('Entitlement seed ausente.');
}

$repository->grant(
    (int) ($seedRow['user_id'] ?? 0),
    (int) ($seedRow['product_id'] ?? 0),
    (int) ($seedRow['order_id'] ?? 0),
    isset($seedRow['order_item_id'])
        ? (int) $seedRow['order_item_id']
        : null,
    'm2_test_restore'
);

echo wp_json_encode([
    'authorized_owner' => true,
    'blocked_other_user' => true,
    'download_limit_enforced' => true,
    'revocation_enforced' => true,
    'hashes_only' => true,
], JSON_PRETTY_PRINT);
