# P1 — Auditoria de pré-produção

## Status

P1 concluída com ressalvas conhecidas e não bloqueantes.

Resultado final do auditor:

- PASS: 44
- WARN: 6
- FAIL: 0
- P1_STATUS=PASS_WITH_WARNINGS

A regressão automatizada completa até o macrolote M4 foi executada
com sucesso.

## Classificação dos warnings

### 1. URLs de ambiente em wp-content

O aviso é causado por:

`Release/ReleaseReadinessService.php`

O serviço contém verificações explícitas para hosts como localhost,
127.0.0.1 e GitHub Codespaces.

Essas referências são controles de segurança destinados a impedir
que ambientes de desenvolvimento sejam classificados como produção.

Não representam URL de produção hardcoded utilizada pela aplicação.

Classificação:

FALSO POSITIVO CONHECIDO / NÃO BLOQUEANTE.

### 2. archive-product.php ausente

A ausência será avaliada durante P2 — Frontend final.

O WooCommerce possui hierarquia própria de templates e a ausência
deste arquivo isoladamente não comprova falha funcional.

Classificação:

PENDÊNCIA P2 / NÃO BLOQUEANTE PARA P1.

### 3. single-product.php ausente

A ausência será avaliada durante P2 — Frontend final.

Será decidido se a página individual utilizará template próprio do
tema ou composição através dos mecanismos/hooks já existentes.

Classificação:

PENDÊNCIA P2 / NÃO BLOQUEANTE PARA P1.

### 4. Produto de teste W20

O produto dedicado aos testes do W20 permanece no banco de
desenvolvimento.

Classificação:

ARTEFATO DE HOMOLOGAÇÃO / NÃO BLOQUEANTE.

### 5. Pedido de teste W20

O pedido utilizado na homologação técnica permanece preservado para
rastreabilidade.

Não deve ser tratado como pedido real de produção.

Classificação:

ARTEFATO DE HOMOLOGAÇÃO / NÃO BLOQUEANTE.

### 6. Usuário de teste W20

A conta utilizada na homologação técnica permanece no ambiente de
desenvolvimento.

Classificação:

ARTEFATO DE HOMOLOGAÇÃO / NÃO BLOQUEANTE.

## Limite da P1

A conclusão desta auditoria não conclui o M5.

Continuam pendentes para o ambiente hospedado na Hostinger:

- homologação E2E final do W20;
- validação de pagamento e notificação/webhook;
- download autenticado pela conta do aluno;
- W21 — migração/domínio;
- W22 — pagamento real controlado;
- W23 — produção.

## Próxima etapa

P2 — Frontend final.

Escopo principal:

Home, catálogo, página da apostila, carrinho, autenticação,
área pública, responsividade, navegação, estados vazios,
acessibilidade e identidade visual.
