# Deploy no Railway sem expor `.env`

Este projeto esta pronto para Railway via `Dockerfile`. O arquivo `.env` local
nao deve ser enviado, commitado nem colocado em ZIP. Use **Variables** no painel
do Railway.

## 1. Arquivos seguros

- `.dockerignore` bloqueia `.env`, `.env.*`, `data/`, `logs/`, `att/` e ZIPs.
- `includes/config.php` primeiro usa variaveis reais do ambiente; `.env` e
  somente fallback local.
- Fotos, relatorios e logs podem usar o volume do Railway por
  `RAILWAY_VOLUME_MOUNT_PATH`.

## 2. Variaveis obrigatorias no Railway

Configure em `Variables`:

```env
OPENAI_API_KEY=...
ANALYSIS_MODEL=gpt-5-mini
IMAGE_MODEL=gpt-image-2
IMAGE_QUALITY=medium

ADMIN_EMAIL=admin@seudominio.com
ADMIN_PASSWORD=troque-por-senha-forte

SUBSCRIPTION_PRICE=97.00
SUBSCRIPTION_DAYS=30
MONTHLY_TOKEN_QUOTA=30

ASAAS_ENV=production
ASAAS_API_KEY=...
ASAAS_WEBHOOK_TOKEN=...
ASAAS_PROJECT_WALLET_ID=...
ASAAS_PROJECT_SPLIT_PERCENT=100

BASE_URL=https://seu-dominio
SESSION_TIMEOUT=3600
REPORT_TTL=86400
```

Banco:

```env
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_NAME=...
DB_USER=...
DB_PASS=...
```

Para teste simples com volume persistente, pode usar:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/data/app.sqlite
```

## 3. Volume

Crie um volume no Railway e monte em `/data`. O Railway disponibiliza
`RAILWAY_VOLUME_MOUNT_PATH` automaticamente. O sistema salva ali:

- `/data/reports`
- `/data/cache`
- `/data/logs`

Sem volume, arquivos locais podem sumir em redeploy/restart.

## 4. Webhook Asaas

Configure no Asaas:

```text
https://SEU_DOMINIO/api/asaas/webhook.php
```

O token do webhook deve ser o mesmo de `ASAAS_WEBHOOK_TOKEN`. Nao use a API key
como token de webhook.

## 5. Observacao importante

Nao suba os ZIPs antigos, porque versoes anteriores continham `.env`. Use apenas
o pacote `gabriel-railway-backend-2026-07-06.zip` ou conecte o repositorio com
`.dockerignore` aplicado.
