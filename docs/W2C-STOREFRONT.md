# W2C - Storefront Facil Digital+

## Escopo

A W2C implementa a vitrine comercial
sobre WooCommerce.

Inclui:

- catalogo de apostilas;
- busca de produtos;
- ordenacao;
- contagem de resultados;
- paginacao;
- estado vazio;
- pagina individual de apostila;
- preco;
- disponibilidade;
- add-to-cart;
- produtos relacionados;
- pagina de busca;
- pagina 404;
- dados temporarios de desenvolvimento.

## Autoridade comercial

WooCommerce continua sendo a autoridade para:

- produto;
- preco;
- estoque;
- disponibilidade;
- sessao;
- carrinho;
- checkout;
- pedidos.

O tema nao possui tabela comercial paralela.

## Preco

Cada produto possui um unico preco base.

A forma de pagamento nao cria precos
paralelos para Pix, cartao ou boleto.

## Templates WooCommerce

A W2C possui overrides apenas onde existe
necessidade estrutural real:

- woocommerce/archive-product.php;
- woocommerce/content-product.php;
- woocommerce/single-product.php.

Os hooks principais do WooCommerce sao
preservados sempre que aplicavel.

## Produto individual

A pagina inclui:

- imagem ou placeholder;
- nome;
- preco;
- descricao curta;
- disponibilidade;
- add-to-cart WooCommerce;
- beneficios;
- descricao;
- informacoes comerciais;
- estrutura futura de simulados;
- FAQ;
- relacionados.

## PDFs

Produtos seed sao virtuais, mas nao usam
o mecanismo padrao `downloadable` do
WooCommerce.

Isso e deliberado.

O fluxo definitivo sera:

pedido WooCommerce
-> pagamento confirmado
-> entitlement Facil Digital+ Core
-> geracao de PDF protegido
-> download autorizado

Esse fluxo pertence as fases posteriores.

## Seed

`tools/seed-w2c.php`

Cria tres produtos temporarios apenas em
`development`.

Eles sao identificados por:

`_fd_w2c_seed = 1`

e por uma chave:

`_fd_w2c_seed_key`

O script e idempotente.

## Cleanup

`tools/cleanup-w2c.php`

Remove exclusivamente produtos marcados
como seed W2C.

Ele tambem e bloqueado fora do ambiente
`development`.

## Fora da W2C

Nao pertencem a esta etapa:

- CPF;
- Mercado Pago;
- entitlement;
- PDF protegido;
- dashboard completo;
- simulados funcionais;
- ranking;
- checkout visual definitivo.
