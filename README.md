# Facil Digital+ WordPress

Nova plataforma comercial da Facil Digital+.

## Arquitetura

- WordPress
- PHP
- MariaDB/MySQL
- WooCommerce
- Mercado Pago
- tema proprio `facil-digital`
- plugin proprio `facil-digital-core`

Nao utiliza Elementor.

As paginas publicas, area do aluno, simulados e dashboards
serao desenvolvidos com HTML/PHP, CSS e JavaScript proprios.

## Desenvolvimento

Gerar ambiente:

    ./tools/init-env.sh

Subir e instalar:

    ./tools/bootstrap.sh

Validar:

    ./tools/validate-w1.sh

Testar persistencia:

    ./tools/test-persistence.sh

## Codigo proprio

Tema:

    wp-content/themes/facil-digital/

Plugin:

    wp-content/plugins/facil-digital-core/

## Dados privados

Nunca versionar:

- `.env`
- `wp-config.php`
- uploads
- PDFs master
- PDFs personalizados
- backups
- banco
- credenciais de pagamento
