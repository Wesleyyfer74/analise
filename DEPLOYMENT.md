# Deploy na Hostinger

Dominio de producao: `https://gerador.spacered.com.br`

## 1. Antes do upload

1. Revogue a chave da OpenAI que apareceu anteriormente em `.env.example`.
2. Gere uma nova chave no painel da OpenAI.
3. Substitua somente o valor de `OPENAI_API_KEY` no arquivo `.env`.
4. Confirme que a conta da API possui faturamento e acesso aos modelos configurados.

O arquivo `.env` deve permanecer privado e nunca deve ser enviado para repositorios.

## 2. Pasta correta do subdominio

No hPanel da Hostinger, abra:

`Dominios > Subdominios > gerador.spacered.com.br`

Confira qual e a pasta raiz definida para esse subdominio. Envie o conteudo desta
pasta `gabriel` diretamente para essa raiz. O arquivo `index.php` deve ficar na
raiz acessada pelo subdominio, e nao dentro de uma segunda pasta `gabriel`.

Estrutura esperada:

```text
raiz-do-subdominio/
|-- .env
|-- .htaccess
|-- .user.ini
|-- index.php
|-- producao-check.php
|-- api/
|-- data/
|-- includes/
|-- logs/
`-- static/
```

Ative o SSL do subdominio antes dos testes.

## Banco de dados

Para producao na Hostinger, prefira MySQL. Configure no `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nome_do_banco
DB_USER=usuario_do_banco
DB_PASS=senha_do_banco
```

Em ambiente local, `DB_CONNECTION=sqlite` cria `data/app.sqlite` automaticamente.
No primeiro acesso, o sistema cria as tabelas e o admin inicial definido por
`ADMIN_EMAIL` e `ADMIN_PASSWORD`.

Troque `ADMIN_PASSWORD` antes de subir para producao.

## Asaas

Configure:

```env
ASAAS_ENV=production
ASAAS_API_KEY=sua-chave-asaas
ASAAS_WEBHOOK_TOKEN=um-token-secreto-diferente-da-api-key
ASAAS_PROJECT_WALLET_ID=wallet-da-subconta-do-dono
ASAAS_PROJECT_SPLIT_PERCENT=100
```

Webhook no Asaas:

```text
https://gerador.spacered.com.br/api/asaas/webhook.php
```

Configure o token do webhook no Asaas com o mesmo valor de
`ASAAS_WEBHOOK_TOKEN`. O sistema valida o header `asaas-access-token`.

## 3. Versao e extensoes PHP

Use PHP 8.1 ou superior. No hPanel, habilite:

- `curl`
- `gd`
- `fileinfo`
- `mbstring`
- `openssl`
- `exif`
- `imagick`, quando disponivel

`imagick` e necessario para converter HEIC/HEIF no servidor. Sem ele, JPEG, PNG,
WebP, BMP, GIF e AVIF continuam funcionando, e o sistema orienta o usuario a
enviar HEIC como JPG.

Os limites recomendados estao em `.user.ini`:

```ini
upload_max_filesize=32M
post_max_size=70M
memory_limit=512M
max_execution_time=360
max_input_time=180
```

A Hostinger pode levar alguns minutos para aplicar alteracoes em `.user.ini`.

## 4. Permissoes

Use permissoes `755` nas pastas e `644` nos arquivos. O usuario do PHP precisa
conseguir gravar em:

```text
data/reports/
static/cache/
```

Nao use permissao `777`.

## 5. Verificacao

Abra o valor de `DEPLOY_CHECK_TOKEN` no `.env` e acesse:

```text
https://gerador.spacered.com.br/producao-check.php?token=VALOR_DO_TOKEN
```

Corrija qualquer item obrigatorio marcado como erro. Depois:

1. Exclua `producao-check.php` do servidor.
2. Abra `https://gerador.spacered.com.br`.
3. Acesse `/admin/login.php` com o admin do `.env`.
4. Crie ou ative um cliente por 30 dias.
5. Acesse `/cliente/login.php` com esse cliente.
6. Teste duas fotos JPG.
7. Teste uma foto PNG ou WebP.
8. Teste uma foto HEIC de iPhone se `imagick` estiver ativo.
9. Gere a analise e depois a previa.
10. Confirme que a previa mantem a mesma pessoa e altera principalmente o cabelo.
11. Confirme que o botao "Baixar imagem da previa" funciona no celular.

## 6. Protecao e retencao

- As fotos ficam em uma pasta bloqueada pelo Apache.
- Somente a sessao que criou o relatorio consegue visualizar as imagens.
- Relatorios e fotos expiram em 24 horas por padrao.
- O sistema limita analises e geracoes repetidas por sessao.
- Nao remova os arquivos `.htaccess` de `data/` e `static/`.
- Para diagnostico temporario, altere `VISAGISMO_LOG_FILE=1`. Depois do teste,
  volte para `0`. O arquivo de log fica protegido dentro de `logs/`.

## 7. Erros comuns

### OPENAI_API_KEY nao configurada

Confirme que `.env` esta na mesma pasta de `index.php` e que a linha nao possui
aspas incorretas ou espacos antes do nome.

### Modelo sem acesso ou saldo

Confirme faturamento, limites e acesso a `gpt-5-mini` e `gpt-image-2` no projeto
da API associado a chave.

### Upload excede o limite

Confira no verificador se `upload_max_filesize` e `post_max_size` receberam os
valores de `.user.ini`.

### HEIC nao abre

Ative `imagick` no hPanel ou solicite a extensao ao suporte da Hostinger. Alguns
servidores possuem Imagick sem o codec HEIC; nesse caso, use JPG/PNG no celular.

### Erro 500

Consulte os logs PHP no hPanel. Se o erro comecou depois do upload, confirme se
o plano permite as diretivas usadas em `.user.ini` e se `mod_rewrite` esta ativo.
