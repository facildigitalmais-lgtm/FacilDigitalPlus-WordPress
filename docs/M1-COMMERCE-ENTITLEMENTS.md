# M1 — W4 + W5 + W6 + W7

O macrolote M1 conecta a fundação do Fácil Digital+ Core ao
fluxo comercial real do WooCommerce.

## Entregas

### Concursos

Taxonomia:

`fd_concurso`

URL pública:

`/concurso/{slug}/`

O gerenciamento utiliza as capabilities próprias do Core.

### Apostilas

O produto comercial continua sendo um produto WooCommerce.

O Core acrescenta:

- concurso;
- cargo;
- banca;
- ano;
- número de páginas;
- versão do material;
- possui simulados;
- limite de downloads;
- geração de PDF personalizado;
- marca-d'água;
- senha de PDF.

Produtos marcados como apostila são virtuais e não utilizam o
download público nativo do WooCommerce.

O PDF master permanece fora deste macrolote e será implementado
no M2 com storage privado.

### Checkout

O WooCommerce continua sendo a autoridade do checkout.

Para apostilas protegidas:

- conta é obrigatória;
- criação de conta no checkout é permitida;
- CPF é obrigatório;
- CPF é validado por dígitos verificadores;
- CPF completo fica somente no metadata protegido do pedido;
- interfaces administrativas mostram CPF mascarado.

### Mercado Pago

O gateway não é reimplementado pelo Fácil Digital+ Core.

O runtime DEV utiliza o plugin oficial:

`woocommerce-mercadopago`

O M1 valida apenas presença e integração estrutural.

Credenciais, sandbox de pagamento e pagamento real permanecem no
macrolote M5.

### Entitlements

O Core observa estados autoritativos do WooCommerce.

Grant:

- `woocommerce_payment_complete`;
- pedido `processing`;
- pedido `completed`.

Revogação:

- `refunded`;
- `cancelled`;
- `failed`.

Grant exige:

- usuário associado ao pedido;
- `WC_Order::is_paid()`;
- `date_paid`;
- produto marcado como apostila Fácil Digital+.

A unique key do banco e o repository garantem idempotência por:

`user_id + product_id + order_id`

Comprar o mesmo produto em um novo pedido cria um entitlement
independente. Revogar uma compra antiga não remove uma compra
nova válida.

### REST

Endpoint autenticado:

`GET /wp-json/facil-digital/v1/me/entitlements`

Não contém CPF, e-mail ou telefone.

## Gate

Preparar dependência de runtime:

`./tools/setup-m1-runtime.sh`

Validar:

`./tools/validate-m1.sh`

Somente encerrar o macrolote quando a saída terminar em:

`PASS - M1 VALIDADO`
