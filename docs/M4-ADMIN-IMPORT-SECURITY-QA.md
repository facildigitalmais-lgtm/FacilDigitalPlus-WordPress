# M4 — Admin, Importação, Hardening e QA

O macrolote M4 reúne W16, W17, W18 e W19.

## W16 — Operação administrativa

O dashboard Fácil Digital+ passa a exibir métricas de vendas, pedidos, alunos, apostilas, questões, simulados, tentativas, PDFs e downloads. Páginas operacionais adicionais permitem consultar resultados, rankings, PDFs, downloads, alunos e relatórios sem expor CPF, storage keys, hashes de IP/User-Agent ou credenciais.

Pedidos e pagamentos continuam sob autoridade do WooCommerce.

## W17 — Importação e exportação

O banco de questões recebe importador CSV com:

- dry-run antes de persistir;
- limite de 10 MiB e 10 mil linhas por arquivo;
- delimitador vírgula ou ponto e vírgula;
- validação de tipo, enunciado, alternativas, resposta correta, ano e concurso;
- importação pelo admin e WP-CLI;
- exportação CSV em lotes.

Cabeçalhos aceitos incluem `tipo`, `enunciado`, `alternativa_a` até `alternativa_e`, `correta`, `comentario`, `banca`, `concurso`, `cargo`, `disciplina`, `assunto`, `dificuldade`, `ano` e `status`.

## W18 — Hardening

`SecurityAudit` verifica a prontidão do banco, capabilities, WooCommerce, Mercado Pago oficial, storage privado, chaves WordPress e controles adicionais para produção. Em desenvolvimento, controles estritamente produtivos aparecem como avisos; em produção tornam-se bloqueios quando inseguros.

O painel e o WP-CLI não mostram CPF, e-mail, telefone, storage key, senha de PDF, token do Mercado Pago ou hashes de telemetria.

## W19 — QA e carga

`QaService` verifica integridade relacional das tabelas próprias, simulados publicados sem questões, percentuais inválidos, prontidão de segurança e tempos de consultas críticas.

Comandos:

```bash
wp facil-digital qa
wp facil-digital security-audit
wp facil-digital questions import arquivo.csv --dry-run
wp facil-digital questions import arquivo.csv
wp facil-digital questions export arquivo.csv
```

Gate:

```bash
./tools/validate-m4.sh
```

O lote só está concluído com `PASS - M4 VALIDADO`.
