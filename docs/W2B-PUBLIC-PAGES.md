# W2B - Paginas Publicas e Autenticacao

## Escopo

A W2B adiciona:

- Home comercial;
- produtos recentes na Home;
- estado vazio de catalogo;
- Sobre;
- Contato;
- FAQ;
- Politica de Privacidade;
- Termos de Uso;
- Login;
- Cadastro;
- Recuperacao de senha.

## Autenticacao

A aplicacao nao possui sistema paralelo
de usuarios ou senhas.

Login:

WordPress `wp_signon()`.

Cadastro:

WooCommerce `wc_create_new_customer()`.

Recuperacao:

WordPress `retrieve_password()`.

## Seguranca

As acoes proprias utilizam:

- POST;
- WordPress nonce;
- sanitizacao;
- escaping;
- redirects locais validados;
- cookies oficiais WordPress.

Senhas nao sao:

- armazenadas pelo tema;
- adicionadas a URLs;
- adicionadas a logs;
- armazenadas em JavaScript;
- armazenadas em localStorage.

## Responsabilidades

WordPress:

- usuario;
- autenticacao;
- senha;
- recuperacao.

WooCommerce:

- cliente;
- carrinho;
- checkout;
- pedido;
- produtos.

Facil Digital+ Core:

- regras de negocio proprias da plataforma.

Tema:

- apresentacao;
- experiencia visual;
- formularios publicos.

## Fora da W2B

Ainda nao pertencem a esta fase:

- CPF;
- Mercado Pago;
- entitlement;
- PDFs protegidos;
- simulados funcionais;
- rankings;
- dashboard completo;
- filtros avancados do catalogo.