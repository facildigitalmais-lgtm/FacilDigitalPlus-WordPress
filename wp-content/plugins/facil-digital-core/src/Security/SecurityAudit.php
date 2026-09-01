<?php

declare(strict_types=1);

namespace FacilDigital\Core\Security;

use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Core\Database;
use FacilDigital\Core\PDFs\PrivateStorage;
use FacilDigital\Core\WooCommerce\MercadoPagoModule;

final class SecurityAudit
{
    /** @return array{ready:bool,checks:list<array{id:string,status:string,message:string}>,errors:list<string>,warnings:list<string>} */
    public function run(): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];
        $environment = wp_get_environment_type();

        $this->check($checks, $errors, 'database', Database::isReady(), 'Banco próprio íntegro.');
        $this->check($checks, $errors, 'capabilities', Capabilities::isReady(), 'Capabilities e papéis íntegros.');
        $this->check($checks, $errors, 'woocommerce', class_exists('WooCommerce'), 'WooCommerce ativo.');
        $this->check($checks, $errors, 'mercado_pago_official', MercadoPagoModule::isOfficialPluginActive(), 'Plugin oficial Mercado Pago ativo.');

        $storageReady = false;
        $storageMessage = 'Storage privado indisponível.';
        try {
            $storage = new PrivateStorage();
            $root = $storage->root();
            $storageReady = $storage->isReady();
            $public = rtrim(wp_normalize_path(ABSPATH), '/');
            $private = rtrim(wp_normalize_path($root), '/');
            $storageReady = $storageReady
                && $private !== $public
                && !str_starts_with($private, $public . '/');
            $storageMessage = $storageReady
                ? 'Storage privado fora da webroot e gravável pelo processo PHP.'
                : 'Storage privado não atende aos requisitos.';
        } catch (\Throwable $exception) {
            $storageMessage = sanitize_key($exception->getMessage());
        }
        $this->check($checks, $errors, 'private_storage', $storageReady, $storageMessage);

        $manager = get_role(Capabilities::ROLE_MANAGER);
        $leastPrivilege = $manager instanceof \WP_Role
            && !$manager->has_cap('manage_options')
            && !$manager->has_cap('manage_woocommerce')
            && !$manager->has_cap('edit_users')
            && !$manager->has_cap('activate_plugins');
        $this->check($checks, $errors, 'least_privilege', $leastPrivilege, 'Gerente sem privilégios globais sensíveis.');

        $saltsReady = true;
        foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY'] as $constant) {
            if (!defined($constant)) {
                $saltsReady = false;
                break;
            }
            $value = (string) constant($constant);
            if ($value === '' || str_contains($value, 'put your unique phrase here')) {
                $saltsReady = false;
                break;
            }
        }
        $this->check($checks, $errors, 'wordpress_keys', $saltsReady, 'Chaves de autenticação WordPress configuradas.');

        $debug = defined('WP_DEBUG') && WP_DEBUG;
        $fileEditDisabled = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        if ($environment === 'production') {
            $this->check($checks, $errors, 'production_debug', !$debug, 'WP_DEBUG deve estar desabilitado em produção.');
            $this->check($checks, $errors, 'production_file_editor', $fileEditDisabled, 'Editor de arquivos deve estar desabilitado em produção.');
        } else {
            $checks[] = ['id' => 'production_debug', 'status' => 'info', 'message' => 'Controle de WP_DEBUG será bloqueante somente em produção.'];
            $checks[] = ['id' => 'production_file_editor', 'status' => 'info', 'message' => 'DISALLOW_FILE_EDIT será bloqueante somente em produção.'];
            if ($debug) {
                $warnings[] = 'WP_DEBUG ativo no ambiente de desenvolvimento.';
            }
        }

        return [
            'ready' => $errors === [],
            'checks' => $checks,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @param list<array{id:string,status:string,message:string}> $checks @param list<string> $errors */
    private function check(array &$checks, array &$errors, string $id, bool $ok, string $message): void
    {
        $checks[] = ['id' => $id, 'status' => $ok ? 'pass' : 'fail', 'message' => $message];
        if (!$ok) {
            $errors[] = $id;
        }
    }
}
