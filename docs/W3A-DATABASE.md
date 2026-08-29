# W3A — Database, Installer e Migrations

Primeiro checkpoint da W3 do Facil Digital+ Core.

Implementado:

- plugin Core `0.2.0`;
- schema `1.0.0`;
- `Database` com prefixo dinamico via `$wpdb->prefix`;
- `Installer` com `dbDelta()`;
- `Migrations` com lock e verificacao de tabelas;
- `Activator` para instalacao inicial;
- nove tabelas-base: questoes, alternativas, simulados, relacoes, tentativas, respostas, entitlements, PDFs e downloads;
- uninstall nao destrutivo;
- validador `tools/validate-w3a.sh`.

Decisoes:

- sem foreign keys nesta fase;
- sem CPF nas tabelas W3A;
- downloads preveem apenas hashes de IP/User-Agent;
- nenhuma tabela usa prefixo `wp_` fixo;
- schema so e marcado como instalado se todas as tabelas existirem.

Validacao:

```bash
./tools/validate-w3a.sh
```

Proximo checkpoint: W3B — capabilities, roles, admin modular e estrutura dos modulos.
