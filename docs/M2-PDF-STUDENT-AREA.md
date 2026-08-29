# M2 — PDF privado, proteção e área do aluno

O M2 agrupa W8, W9 e W10.

## Entregas

- storage privado fora da webroot;
- PDF master privado por apostila;
- upload administrativo sem Media Library pública;
- FPDI + TCPDF via Composer;
- geração idempotente por entitlement + versão do material;
- geração assíncrona via Action Scheduler do WooCommerce;
- watermark em todas as páginas;
- nome do aluno, CPF mascarado, pedido e tracking code;
- senha de abertura pelo CPF normalizado;
- CPF não é persistido na tabela de PDFs;
- download autenticado e autorizado por entitlement;
- limite de downloads por compra;
- IP e User-Agent somente em hash HMAC;
- revogação de entitlement bloqueia download;
- endpoint REST `/facil-digital/v1/me/pdfs` sem storage key;
- dashboard do aluno e endpoint `Minhas apostilas` no My Account.

## Storage

Em desenvolvimento:

`/workspace/.runtime/facil-digital-private`

Em produção deve ser configurado com `FACIL_DIGITAL_PRIVATE_DIR` para um diretório persistente, gravável pelo PHP e fora da raiz pública do domínio.

Estrutura:

```text
masters/
generated/
temp/
```

Nenhum master ou PDF de aluno é registrado na Media Library.

## PDF

A implementação usa dependências diretas:

- `setasign/fpdi`;
- `tecnickcom/tcpdf`.

O metapacote abandonado `setasign/fpdi-tcpdf` não é utilizado.

A geração reconstrói cada página do master e adiciona os identificadores do licenciamento. A senha completa do CPF é usada apenas em memória para definir a proteção do PDF.

## Download

A URL nunca contém o caminho real do arquivo. O Core valida:

1. sessão;
2. nonce;
3. dono do PDF;
4. entitlement específico ativo;
5. status `ready`;
6. arquivo privado existente;
7. limite de downloads.

Somente então o arquivo é transmitido pelo PHP.

## Gate

Executar:

```bash
./tools/setup-m2-runtime.sh
./tools/validate-m2.sh
```

O lote só está concluído com:

`PASS - M2 VALIDADO`
