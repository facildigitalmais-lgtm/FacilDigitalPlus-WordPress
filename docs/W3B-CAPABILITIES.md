# W3B — Roles, capabilities e modularizacao

## Objetivo

Criar a fundacao de permissionamento do Facil Digital+ Core sem conceder
capabilities amplas do WordPress ou WooCommerce aos papeis customizados.

## Versoes

- Core: `0.3.0`
- Schema de banco: `1.0.0` (inalterado)
- Schema de capabilities: `1.0.0`

## Roles

### Administrador

O role nativo `administrator` recebe todas as capabilities do Core.

### Gerente Facil Digital+

Slug: `facil_digital_manager`

Pode operar os modulos de negocio do Core, mas nao recebe:

- `facil_digital_manage_settings`
- `manage_options`
- `manage_woocommerce`
- `install_plugins`
- `edit_users`

A integracao futura com produtos/pedidos WooCommerce recebera somente as
capabilities WooCommerce estritamente necessarias, em fase propria.

### Editor de Questoes

Slug: `facil_digital_question_editor`

Recebe somente:

- `read`
- `facil_digital_access_admin`
- `facil_digital_manage_questions`

## Capabilities do Core

- `facil_digital_access_admin`
- `facil_digital_manage_contests`
- `facil_digital_manage_apostilas`
- `facil_digital_manage_entitlements`
- `facil_digital_manage_pdfs`
- `facil_digital_manage_downloads`
- `facil_digital_manage_questions`
- `facil_digital_manage_simulations`
- `facil_digital_view_results`
- `facil_digital_view_rankings`
- `facil_digital_manage_students`
- `facil_digital_view_reports`
- `facil_digital_manage_settings`

## Upgrade idempotente

As capabilities possuem versao propria em:

`facil_digital_core_capabilities_version`

`Capabilities::maybeRun()` sincroniza os papeis quando a versao muda ou
quando a instalacao perde alguma capability esperada.

## Modularizacao

`Plugin` deixa de concentrar menu e REST. Os primeiros modulos sao:

- `Admin\\Menu`
- `API\\HealthController`

Ambos implementam `Contracts\\ModuleInterface`.

Novos modulos de concursos, PDFs, entitlement, questoes e simulados poderao
ser adicionados sem transformar `Core\\Plugin` em uma classe gigante.

## Seguranca

- o menu administrativo usa capabilities proprias;
- configuracoes sao exclusivas de administrador nesta fase;
- roles customizadas nao recebem `manage_options`;
- roles customizadas nao recebem `manage_woocommerce` prematuramente;
- usuario comum nao recebe acesso ao Core;
- a instalacao e repetivel e idempotente.

## Validacao

Executar:

```bash
./tools/validate-w3b.sh
```

A W3B somente deve ser considerada concluida com:

```text
PASS - W3B VALIDADA
```
