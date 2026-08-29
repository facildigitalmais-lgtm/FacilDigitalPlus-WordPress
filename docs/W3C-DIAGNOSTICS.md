# W3C — Registry, diagnósticos e operação

A W3C encerra a fundação operacional do Fácil Digital+ Core
antes dos módulos de domínio da W4.

## Entregas

Core 0.4.0.

ModuleRegistry como ponto único de registro dos módulos do
plugin.

Diagnostics como leitura central do estado operacional.

Endpoint administrativo:

GET /wp-json/facil-digital/v1/status

Comando:

wp facil-digital status

Preservação do health check público existente.

Gate:

tools/validate-w3c.sh

## Segurança

O endpoint `/status` não é público.

Ele exige:

facil_digital_access_admin

O endpoint `/health` continua mínimo e público para
monitoramento.

Dados internos detalhados ficam apenas no diagnóstico protegido
e no WP-CLI.

## Registry

Novos módulos do Core devem ser registrados em:

Core\ModuleRegistry::defaults()

e implementar:

Contracts\ModuleInterface

O tema não deve instanciar módulos do Core diretamente.

## Diagnóstico

O snapshot operacional inclui:

- versão do Core;
- versão instalada e alvo do schema;
- tabelas ausentes;
- versão instalada e alvo das capabilities;
- estado do WooCommerce;
- versões de WordPress e PHP;
- ambiente WordPress;
- erros de requisitos;
- estado agregado ready.

Nenhum dado pessoal, CPF, token ou credencial deve ser incluído
no diagnóstico.

## Uninstall

A política continua não destrutiva.

Desinstalar o plugin não remove tabelas, entitlements,
tentativas, PDFs, downloads ou dados de clientes.

## Gate

Executar:

./tools/validate-w3c.sh

A W3 somente é encerrada quando terminar com:

PASS - W3C VALIDADA
