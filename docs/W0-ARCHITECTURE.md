# W0 - Arquitetura Facil Digital+

## Plataforma

WordPress + WooCommerce + Facil Digital+ Core.

## Responsabilidades

### WordPress

- usuarios
- autenticacao
- CMS
- paginas
- sessoes
- permissoes basicas

### WooCommerce

- produtos
- precos
- carrinho
- checkout
- pedidos
- clientes
- cupons
- pagamentos

### Mercado Pago

- Pix
- cartao
- boleto
- confirmacao financeira

### Facil Digital+ Core

- concursos
- cargos
- apostilas
- entitlement
- PDFs privados
- PDF personalizado
- senha CPF
- marca d'agua
- banco de questoes
- simulados
- tentativas
- resultados
- ranking
- dashboards
- regras de acesso

### Tema Facil Digital+

- HTML
- PHP de apresentacao
- CSS
- JavaScript
- layout publico
- dashboard do aluno
- interface dos simulados

O tema nao e autoridade sobre regras de negocio.

## Regra financeira

Mercado Pago -> WooCommerce -> Facil Digital+ Core.

O Core nao aprova pagamentos por conta propria.

## PDF

O PDF master permanece privado.

O cliente recebe somente uma copia personalizada contendo:

- marca d'agua;
- identificacao do comprador;
- CPF mascarado;
- codigo de rastreamento;
- senha baseada no CPF normalizado.

O PDF master nunca sera usado como fallback publico.

## Simulados

Tempo, entitlement, respostas e resultado possuem autoridade
no servidor.

JavaScript e somente camada de interface.

## Banco

Tabelas proprias utilizarao `$wpdb->prefix`.

Pedidos nao serao consultados diretamente nas tabelas internas
do WooCommerce. O Core utilizara APIs WooCommerce e sera
compativel com HPOS.
