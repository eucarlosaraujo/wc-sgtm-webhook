=== WC SGTM Webhook Pro ===
Contributors: carlosaraujo
Tags: woocommerce, google tag manager, server-side, meta ads, conversion api
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Envia dados de pedidos pagos para Server-Side Google Tag Manager (Stape.io) via Data Client com Event Match Quality otimizado para Meta Ads.

== Description ==

**WC SGTM Webhook Pro** é um plugin profissional que integra WooCommerce com Server-Side Google Tag Manager (SGTM) via Data Client, otimizado para **Meta Conversions API** com alta **Event Match Quality (EMQ)**.

### 🚀 Principais Funcionalidades

* ✅ Envio automático de eventos `purchase` para SGTM
* ✅ Suporte completo a **Stape.io** e **Self-Hosted SGTM**
* ✅ **Event Match Quality otimizado** (EMQ ≥ 8/10)
* ✅ Hash SHA-256 automático de dados pessoais (LGPD compliant)
* ✅ Retry automático com deduplicação por `event_id`
* ✅ Dashboard administrativo com estatísticas em tempo real
* ✅ Sistema de logs com rotação automática
* ✅ Reenvio manual de webhooks
* ✅ Teste de conexão integrado

### 📊 Dados Enviados (Match Keys)

O plugin envia **todos os match keys** recomendados pelo Meta:

**Alta Prioridade:**
* `em` (email) - hasheado + plain
* `ph` (phone) - hasheado + plain
* `fn` (first name) - hasheado + plain
* `ln` (last name) - hasheado + plain

**Média/Baixa Prioridade:**
* `ct` (city)
* `st` (state)
* `zp` (zip code)
* `country` (country code)
* `external_id` (user ID)
* `fbp` / `fbc` (se disponíveis via cookies)

### 🔒 Segurança & Privacidade

* Hash SHA-256 de todos os dados pessoais (PII)
* Transmissão via HTTPS com SSL verify
* Suporte a Bearer Token para autenticação
* LGPD compliant
* Logs com redação automática de dados sensíveis

### 📦 Payload Completo

```json
{
  "client_name": "Data Client",
  "event_name": "purchase",
  "event_time": 1234567890,
  "event_id": "wc_12345_1234567890",
  "action_source": "website",
  "user_data": {
    "em": ["hash_sha256"],
    "ph": ["hash_sha256"],
    "fn": ["hash_sha256"],
    "ln": ["hash_sha256"],
    "ct": ["hash_sha256"],
    "st": ["hash_sha256"],
    "zp": ["hash_sha256"],
    "country": ["hash_sha256"],
    "external_id": ["hash_sha256"]
  },
  "custom_data": {
    "currency": "BRL",
    "value": 199.90,
    "order_id": "12345",
    "contents": [...]
  }
}
```

== Installation ==

### Instalação Automática

1. Vá para **Plugins > Adicionar Novo** no WordPress
2. Pesquise por "WC SGTM Webhook Pro"
3. Clique em **Instalar Agora** e depois **Ativar**

### Instalação Manual

1. Baixe o arquivo `wc-sgtm-webhook.zip`
2. Vá para **Plugins > Adicionar Novo > Enviar Plugin**
3. Selecione o arquivo ZIP e clique em **Instalar Agora**
4. Ative o plugin

### Configuração

1. Vá para **WooCommerce > SGTM Webhook**
2. Na aba **Configurações**, preencha:
   * **URL do Webhook**: URL base do SGTM (ex: `https://sgtm.seudominio.com`)
   * **Container ID**: ID do container GTM (ex: `GTM-XXXXXXX`)
   * **Token** (opcional): Bearer token para autenticação
3. Marque **Ativar Webhook**
4. Clique em **Salvar Configurações**
5. Vá para a aba **Ferramentas** e clique em **Testar Conexão**

== Frequently Asked Questions ==

= O plugin funciona com Stape.io? =

Sim! O plugin foi desenvolvido especificamente para Stape.io, mas também funciona com qualquer servidor SGTM self-hosted.

= Como obtenho o Container ID? =

No Google Tag Manager, acesse seu container Server-Side e copie o ID no formato `GTM-XXXXXXX` que aparece no topo da página.

= O plugin envia eventos para outros gateways além de Meta Ads? =

O plugin envia dados para o SGTM via Data Client. Dentro do SGTM, você pode configurar tags para enviar para Meta, Google Ads, TikTok, Pinterest, etc.

= Os dados pessoais são protegidos? =

Sim! Todos os dados pessoais (PII) são hasheados com SHA-256 antes do envio, conforme recomendado pelo Meta e exigido pela LGPD.

= Como posso testar se está funcionando? =

1. Vá para **WooCommerce > SGTM Webhook > Ferramentas**
2. Clique em **Testar Conexão**
3. Faça um pedido de teste no site
4. Verifique na aba **Pedidos** se o webhook foi enviado
5. Confirme no SGTM Debug Mode se os eventos estão chegando

= Como reenviar um webhook que falhou? =

1. Vá para **WooCommerce > SGTM Webhook > Pedidos**
2. Encontre o pedido com erro
3. Clique no botão **🔄 Reenviar**

= O plugin suporta HPOS (High-Performance Order Storage)? =

A versão atual usa `post_meta` tradicional. Suporte a HPOS será adicionado em versão futura.

== Screenshots ==

1. Dashboard com estatísticas em tempo real
2. Configurações do webhook
3. Lista de pedidos com status de envio
4. Ferramentas e logs detalhados

== Changelog ==

= 3.0.0 - 2024-10-30 =
* 🎉 Primeira versão pública
* ✅ Envio automático de webhooks para pedidos pagos
* ✅ Suporte completo a Stape.io e SGTM self-hosted
* ✅ Event Match Quality otimizado (EMQ ≥ 8/10)
* ✅ Dashboard administrativo com estatísticas
* ✅ Sistema de logs com rotação automática
* ✅ Reenvio manual de webhooks
* ✅ Teste de conexão integrado
* ✅ Hash SHA-256 de dados pessoais (LGPD)
* ✅ Suporte a Bearer Token

== Upgrade Notice ==

= 3.0.0 =
Primeira versão estável do plugin. Recomendado para todos os usuários.

== Requisitos ==

* WordPress 6.0 ou superior
* WooCommerce 7.0 ou superior
* PHP 7.4 ou superior
* SSL/HTTPS habilitado
* Servidor SGTM configurado (Stape.io ou self-hosted)

== Suporte ==

== Roadmap ==

### Versão 3.1 (Q1 2025)
* [ ] Suporte a HPOS (High-Performance Order Storage)
* [ ] Eventos adicionais (AddToCart, InitiateCheckout)
* [ ] Integração com ActionScheduler
* [ ] Webhook personalizado por gateway de pagamento

### Versão 3.2 (Q2 2025)
* [ ] Suporte a múltiplos endpoints
* [ ] Campos customizados configuráveis
* [ ] Exportação de relatórios em PDF
* [ ] Integração com Google Analytics 4

== Créditos ==

Desenvolvido por **Carlos Araújo** para **Alta Cúpula / Elevelife**

Agradecimentos especiais:
* Equipe Stape.io pela infraestrutura SGTM
* Comunidade WooCommerce
* Meta Developer Documentation

== Licença ==

Este plugin é licenciado sob a GPL v3 ou posterior.
