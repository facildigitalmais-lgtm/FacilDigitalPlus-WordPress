# M5 — Sandbox, domínio, pagamento real e produção

O macrolote M5 reúne W20, W21, W22 e W23. Diferentemente dos macrolotes anteriores, ele possui gates manuais obrigatórios. Nenhum script deste lote conecta contas Mercado Pago, grava credenciais, troca o domínio real ou habilita pagamentos produtivos automaticamente.

## W20 — Mercado Pago sandbox

1. Criar duas contas de teste distintas no Mercado Pago: vendedor e comprador.
2. Vincular a loja à conta de teste do vendedor usando o plugin oficial.
3. Realizar uma compra aprovada com a conta de teste do comprador.
4. Confirmar no WooCommerce que o pedido ficou pago e possui transaction id.
5. Confirmar entitlement ativo, PDF personalizado pronto e download autenticado.
6. Executar `./tools/m5-sandbox-gate.sh <ORDER_ID>`.

O gate técnico só aceita um pedido cujo gateway seja Mercado Pago, esteja pago, tenha transaction id, entitlement ativo e PDF pronto.

## W21 — Migração de domínio

Antes da troca:

- backup completo e teste de restauração;
- reduzir TTL do DNS com antecedência;
- confirmar HTTPS no domínio final;
- confirmar e-mail transacional;
- confirmar storage privado persistente;
- confirmar cron/Action Scheduler;
- planejar janela e rollback.

Use apenas o dry-run:

```bash
./tools/m5-domain-plan.sh https://origem.example https://facildigitalmais.com
```

O script usa `wp search-replace --dry-run` e não persiste a mudança. A troca real só deve ocorrer na janela aprovada.

## W22 — Pagamento real controlado

Depois de desvincular a conta de teste e vincular a conta Mercado Pago real:

1. ativar/confirmar credenciais produtivas fora do Git;
2. confirmar SSL;
3. deixar apenas um produto de baixo valor para o teste controlado, se necessário;
4. realizar uma compra real com autorização explícita;
5. confirmar pedido pago, transaction id, entitlement, geração do PDF e download;
6. validar estorno/cancelamento de forma controlada quando aplicável.

A prova técnica de um pedido pode ser feita com:

```bash
wp facil-digital release payment --order=<ID> --require-pdf=1
```

## W23 — Produção

No ambiente final execute:

```bash
./tools/m5-production-readiness.sh
```

Os checks automáticos exigem, entre outros:

- `WP_ENVIRONMENT_TYPE=production`;
- URLs HTTPS;
- domínio não local/Codespaces;
- WooCommerce ativo;
- plugin oficial Mercado Pago ativo;
- Action Scheduler disponível;
- storage privado fora da webroot e gravável;
- `WP_DEBUG` desativado;
- `DISALLOW_FILE_EDIT=true`;
- `FORCE_SSL_ADMIN=true`.

Mesmo com todos os checks automáticos verdes, ainda são obrigatórios: backup restaurável, confirmação de DNS/SSL/e-mail, conta Mercado Pago real, compra real controlada, webhook refletido no pedido, entitlement, PDF, download e rollback documentado.

## Comandos

```bash
wp facil-digital release check --stage=sandbox
wp facil-digital release check --stage=production
wp facil-digital release payment --order=<ID> --require-pdf=1
./tools/m5-domain-plan.sh <OLD_URL> <NEW_URL>
./tools/m5-sandbox-gate.sh <ORDER_ID>
./tools/m5-production-readiness.sh
./tools/validate-m5.sh
```

## Critério de fechamento

`PASS - M5 AUTOMACAO VALIDADA` fecha apenas a parte automatizada do lote.

O projeto só poderá ser declarado em produção depois da conclusão explícita dos gates humanos W20, W21, W22 e W23.
