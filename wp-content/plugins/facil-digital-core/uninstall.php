<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Politica de dados do Facil Digital+ Core:
 *
 * - desinstalar o plugin NAO apaga tabelas;
 * - desinstalar o plugin NAO apaga entitlements;
 * - desinstalar o plugin NAO apaga historico de simulados;
 * - desinstalar o plugin NAO apaga registros de PDFs/downloads;
 * - desinstalar o plugin NAO remove automaticamente dados de clientes.
 *
 * Uma rotina destrutiva futura devera ser explicita, administrativa,
 * auditavel e protegida contra execucao acidental.
 */
