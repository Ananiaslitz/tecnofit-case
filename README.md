# 💸 Saque PIX — Tecnofit (Teste Técnico)

Este projeto implementa uma **API completa de Saque PIX** para uma conta digital fictícia, construída com **Hyperf 3 (PHP 8.3)**, **MySQL 8**, **Redis**, e **Mailhog**, em um ambiente totalmente conteinerizado via **Docker Compose**.

A aplicação cobre **validação de regras de negócio**, **idempotência HTTP**, **agendamentos futuros**, **execução assíncrona via CLI**, e **observabilidade distribuída com OpenTelemetry**.

---

## 🧩 Funcionalidades

| Categoria | Descrição |
|------------|------------|
| 💰 **Saque PIX imediato** | Permite solicitar um saque instantâneo do saldo da conta via PIX (tipo `email`). |
| ⏰ **Agendamento de saque** | Permite agendar um saque para execução futura (até 7 dias). |
| 🧾 **Listagem de saques** | Lista todos os saques (realizados, agendados ou falhos), com paginação. |
| 🧠 **Idempotência HTTP** | Garante que requisições repetidas (mesmo corpo e chave `Idempotency-Key`) retornem a mesma resposta. |
| 🧮 **Validações automáticas** | Valida método, tipo PIX, formato da chave, valor, casas decimais, campos obrigatórios e datas inválidas. |
| 🧱 **Execução programada (CLI)** | Roda saques agendados pendentes via comando `php bin/hyperf.php withdrawals:process`. |
| 🔭 **Observabilidade OpenTelemetry** | Todos os eventos e execuções são rastreados com spans (`withdraw.requested`, `withdraw.processed`, etc). |
| 📨 **Simulação de e-mail** | Saques processados disparam e-mails simulados via Mailhog. |

---

## 🧱 Stack Técnica

| Componente | Função |
|-------------|--------|
| **PHP Hyperf 3 + Swoole** | Framework assíncrono e reativo da API |
| **MySQL 8** | Banco relacional principal |
| **Redis** | Cache e idempotência |
| **Mailhog** | Captura e-mails simulados (http://localhost:8025) |
| **OpenTelemetry Collector + Jaeger** | Observabilidade e tracing distribuído (http://localhost:16686) |
| **Docker Compose** | Orquestração de todo o ambiente |

---

## 🚀 Subindo o ambiente

```bash
docker compose up -d
```

Após a subida:

| Serviço | URL |
|----------|-----|
| **API** | http://localhost:9501 |
| **Mailhog UI** | http://localhost:8025 |
| **Jaeger UI** | http://localhost:16686 |
| **Redis** | localhost:6379 |

---

## ⚙️ Configuração

Arquivo `.env` padrão:

```env
APP_ENV=dev
APP_TIMEZONE=America/Sao_Paulo

DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=tecnofit
DB_USERNAME=root
DB_PASSWORD=root

REDIS_HOST=redis
REDIS_PORT=6379
IDEMPOTENCY_TTL_SECONDS=3600

OTEL_PHP_TRACES_ENABLED=true
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=otel-collector:4317
OTEL_SERVICE_NAME=tecnofit-saque
```

---

## 🧪 Teste Manual de Contrato (`manual.sh`)

O script `manual.sh` automatiza **testes end-to-end** da API, garantindo que todas as regras de negócio funcionem corretamente.

### Execução:
```bash
chmod +x manual.sh
./manual.sh
```

### Ele valida:

| Etapa | Cenário | Esperado |
|-------|----------|-----------|
| **V1** | Método diferente de `PIX` | ❌ 400 — `"Only PIX withdrawals are supported."` |
| **V2** | Tipo PIX ≠ `email` | ❌ 400 — `"Only PIX type 'email' is supported."` |
| **V3** | Chave PIX inválida (`not-an-email`) | ❌ 400 — `"Invalid PIX e-mail."` |
| **V4** | Valor `amount == 0` | ❌ 400 — `"Amount must be greater than zero."` |
| **V5** | Valor negativo | ❌ 400 — `"Amount must be greater than zero."` |
| **V6** | Valor com >2 casas decimais | ❌ 400 — `"Amount cannot have more than 2 decimal places."` |
| **V7** | Falta `pix.key` | ❌ 400 |
| **V8** | Falta `amount` | ❌ 400 |
| **V9** | `schedule` com formato inválido | ❌ 400 — `"Invalid schedule format, expected Y-m-d H:i"` |
| **1** | Primeira requisição válida | ✅ 200 — Saque criado, `withdraw_id` retornado |
| **2** | Repetição com mesma `Idempotency-Key` | ✅ 200 — Mesma resposta com `Idempotency-Replayed: true` |
| **3** | Listagem paginada | ✅ 200 — Itens, PIX mascarado e paginação |
| **4** | Agendamento no passado | ❌ 400 — `"Schedule cannot be in the past."` |
| **5** | Agendamento > 7 dias | ❌ 400 — `"Schedule cannot be more than 7 days in the future."` |
| **6** | Agendamento válido (+1 min) | ✅ 200 — `withdraw_id` retornado |
| **6b** | Execução via CLI | ✅ Log: `processed=N`, `withdraw.processed` emitido |
| **7** | Duas requisições simultâneas (mesma key) | ✅ Uma responde `409` ou ambas `200` com replay verdadeiro |

---

## 🧮 Execução de Agendamentos

Após criar saques com `schedule` futuro:
```bash
php bin/hyperf.php withdrawals:process
```

Exemplo de log:
```
[INFO] Withdraw processed (scheduled) account.id=1111... outcome=success
processed=2
```

---

## 🧰 Extras

### Criar conta default (para evitar ACCOUNT_NOT_FOUND)
```sql
INSERT INTO account (id, name, balance_cents, created_at, updated_at)
VALUES ('11111111-1111-1111-1111-111111111111', 'Conta Teste', 100000, NOW(), NOW());
```

### Ver saques
```bash
curl -s "http://localhost:9501/account/11111111-1111-1111-1111-111111111111/withdraws?page=1&per_page=10" | jq .
```

---

## 🧭 Estrutura de Pastas

```
src/
 ├─ Adapter/
 │   ├─ In/Http/WithdrawController.php
 │   ├─ In/Cli/ProcessScheduledWithdrawalsCommand.php
 │   └─ Out/Persistence, Out/Observability, Out/Mail
 ├─ Application/
 │   ├─ Command/RequestPixWithdrawHandler.php
 │   ├─ Command/ProcessScheduledWithdrawalsHandler.php
 │   └─ Query/ListWithdrawsHandler.php
 ├─ Domain/
 │   ├─ Entity/Account.php, Withdraw.php
 │   ├─ Service/WithdrawalDomainService.php
 │   └─ ValueObject/Money.php, PixKey.php, Schedule.php
 └─ Shared/
     ├─ Exception/BusinessException.php
     ├─ Http/IdempotencyService.php
     └─ AOP/TracingAspect.php
```

---

## ✅ Testes Automatizados

Testes unitários e de cobertura:
```bash
docker build -t app:test -f Dockerfile .
docker run --rm -it -v "$(pwd):/opt/www" -w /opt/www app:test
```

Gera relatório de cobertura em:
```
build/coverage/index.html
```

---

## 🏁 Resultado Esperado

Ao executar todo o fluxo (`manual.sh` + `withdrawals:process`):

- Todas as validações respondem 400 conforme esperado;
- Saques válidos retornam 200 e aparecem na listagem;
- CLI processa agendados e marca `done=true`;
- Logs e traces aparecem no **Jaeger** e **Mailhog**;
- Nenhum cenário gera erro 500 inesperado.
