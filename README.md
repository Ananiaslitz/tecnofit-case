# 💸 Saque PIX — Tecnofit (Teste Técnico)

Este projeto implementa uma **API de Saque PIX** para uma conta digital fictícia, utilizando **Hyperf 3 (PHP 8.3)**, **MySQL 8**, **Redis**, e **Mailhog**, dentro de um ambiente totalmente conteinerizado via **Docker**.

A API permite:
- Solicitar **saques PIX imediatos**;
- **Agendar** saques para execução futura (até 7 dias);
- **Listar** saques realizados/agendados;
- Executar **agendamentos pendentes** via comando CLI;
- Garantir **idempotência HTTP**;
- Emitir **eventos observáveis** e rastreáveis (OpenTelemetry).

---

## 🧱 Stack

| Componente | Função |
|-------------|--------|
| **PHP Hyperf 3** | Framework assíncrono principal da aplicação |
| **MySQL 8** | Banco de dados relacional principal |
| **Redis** | Cache e mecanismo de idempotência |
| **Mailhog** | Captura de e-mails (para simular notificações) |
| **OpenTelemetry Collector + Jaeger** | Observabilidade e tracing distribuído |
| **Docker Compose** | Orquestração do ambiente completo |

---

## 🚀 Subindo o ambiente

```bash
docker compose up -d
```

Após a subida:
- **API** → http://localhost:9501
- **Redis** → localhost:6379
- **Jaeger UI** → http://localhost:16686
- **Mailhog UI** → http://localhost:8025

---

## ⚙️ Configurações

Edite o arquivo `.env` na raiz (ou use variáveis de ambiente):

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

# Observabilidade
OTEL_PHP_TRACES_ENABLED=true
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=otel-collector:4317
OTEL_SERVICE_NAME=tecnofit-saque
```
