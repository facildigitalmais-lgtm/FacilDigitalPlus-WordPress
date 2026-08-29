# M3 — Banco de Questões, Simulados, Tentativas, Resultados e Ranking

O macrolote M3 reúne W11, W12, W13, W14 e W15.

## Banco de questões

As questões continuam nas tabelas próprias preparadas na W3. O Core oferece CRUD administrativo protegido por `facil_digital_manage_questions`, filtros e busca, duplicação, ativação/desativação e exclusão segura. Questões já referenciadas por simulados ou tentativas são desativadas em vez de destruídas.

Tipos iniciais:

- múltipla escolha A/B/C/D/E;
- Certo/Errado.

Cada questão mantém concurso, cargo, banca, disciplina, assunto, dificuldade, ano, comentário e imagem opcional.

## Simulados

Os simulados são registros da tabela `fd_simulations` e usam `fd_simulation_questions` para a composição. O painel permite seleção manual, por disciplina, por assunto, por banca ou aleatória. A composição final é persistida para que a prova não dependa de uma consulta aleatória a cada tentativa.

O acesso do aluno exige entitlement ativo em apostila marcada com simulados e vinculada ao mesmo concurso; quando cargo estiver preenchido nos dois lados, ele também deve coincidir.

## Motor de tentativa

O servidor é autoridade para:

- início e retomada;
- limite de tentativas;
- expiração;
- pertencimento da questão e alternativa;
- autosave;
- finalização;
- correção e percentual.

O estado de uma tentativa em andamento nunca contém `is_correct`, `correct_key` ou comentário da resposta.

## REST

Rotas autenticadas:

- `GET /facil-digital/v1/simulations`;
- `POST /facil-digital/v1/simulations/{id}/attempts`;
- `GET /facil-digital/v1/attempts/{id}`;
- `POST /facil-digital/v1/attempts/{id}/answers`;
- `POST /facil-digital/v1/attempts/{id}/finish`;
- `GET /facil-digital/v1/attempts/{id}/result`;
- `GET /facil-digital/v1/me/results`;
- `GET /facil-digital/v1/simulations/{id}/ranking`.

Escritas críticas possuem rate limit básico por usuário. O M4 fará hardening e testes de carga adicionais.

## Frontend

A URL pública de apresentação usa `/simulado/{slug}/`. Iniciar a prova exige login e autorização. A execução é responsiva, possui navegação entre questões, autosave e cronômetro derivado do prazo informado pelo servidor.

A área Minha Conta passa a mostrar `Simulados` e `Resultados`, além de estatísticas de quantidade, média e posição geral.

## Ranking

O ranking usa o melhor resultado de cada aluno por simulado e agrega a média dos melhores resultados quando o escopo contém mais de um simulado. Desempate: menor tempo acumulado.

A saída pública utiliza somente nome anonimizado, como `Maria O.`. CPF, e-mail e telefone nunca fazem parte da resposta.

## Gate

Executar:

```bash
./tools/validate-m3.sh
```

O lote só está concluído com:

`PASS - M3 VALIDADO`
