<?php

declare(strict_types=1);

use FacilDigital\Core\Release\ReleaseReadinessService;

$service = new ReleaseReadinessService();
$sandbox = $service->report('sandbox');
$production = $service->report('production');
$missing = $service->paymentProof(999999999, true);

if (!isset($sandbox['ready_automated'], $sandbox['checks'], $sandbox['manual_gates'])) {
    throw new RuntimeException('sandbox_report_invalid');
}
if (($sandbox['stage'] ?? '') !== 'sandbox') {
    throw new RuntimeException('sandbox_stage_invalid');
}
if (($production['stage'] ?? '') !== 'production') {
    throw new RuntimeException('production_stage_invalid');
}
if (($missing['ready'] ?? true) !== false || ($missing['reason'] ?? '') !== 'order_not_found') {
    throw new RuntimeException('payment_fail_closed_invalid');
}

$manualSandbox = $sandbox['manual_gates'];
foreach (['compra_teste_aprovada', 'webhook_refletiu_pedido_pago', 'entitlement_criado', 'pdf_personalizado_pronto', 'download_do_aluno_validado'] as $gate) {
    if (!in_array($gate, $manualSandbox, true)) {
        throw new RuntimeException('sandbox_manual_gate_missing:' . $gate);
    }
}

$manualProduction = $production['manual_gates'];
foreach (['compra_real_controlada_aprovada', 'rollback_documentado'] as $gate) {
    if (!in_array($gate, $manualProduction, true)) {
        throw new RuntimeException('production_manual_gate_missing:' . $gate);
    }
}

echo wp_json_encode([
    'status' => 'ok',
    'sandbox_ready_automated' => (bool) $sandbox['ready_automated'],
    'production_ready_automated' => (bool) $production['ready_automated'],
    'sandbox_manual_gates' => count($manualSandbox),
    'production_manual_gates' => count($manualProduction),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
