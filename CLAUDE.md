# WC SGTM Webhook Pro - Plugin Context

## 📋 Visão Geral

Plugin WordPress/WooCommerce que envia dados de pedidos pagos para Server-Side Google Tag Manager (SGTM) via Data Client, otimizado para Meta Conversions API.

**Versão Atual:** 3.0.0  
**Linguagem:** PHP 7.4+  
**Framework:** WordPress 6.0+ / WooCommerce 7.0+  
**Autor:** Carlos Araújo - Alta Cúpula 

---

## 🏗️ Arquitetura

### Estrutura de Diretórios
```
wc-sgtm-webhook/
├── wc-sgtm-webhook.php          # Plugin principal (autoloader)
├── includes/
│   ├── class-helpers.php         # Funções auxiliares
│   ├── class-core.php            # Lógica de envio webhook
│   ├── class-admin.php           # Interface administrativa
│   └── class-ajax.php            # Handlers AJAX
├── assets/
│   ├── css/admin.css             # Estilos admin
│   └── js/admin.js               # Scripts admin
└── docs/
    ├── README.md
    └── CHANGELOG.md
```

### Classes Principais

**WC_SGTM_Helpers** (`includes/class-helpers.php`)
- Funções auxiliares compartilhadas
- Sistema de logs (ERROR, WARNING, INFO, DEBUG)
- Construção de endpoint: `build_endpoint()`
- Hash de PII (SHA-256)
- Estatísticas do banco de dados

**WC_SGTM_Core** (`includes/class-core.php`)
- Lógica de envio de webhooks
- Preparação de payload (user_data + custom_data)
- Hooks WooCommerce: `completed`, `processing`, `payment_complete`
- Deduplicação por `event_id`

**WC_SGTM_Admin** (`includes/class-admin.php`)
- Interface administrativa (4 abas)
- Formulários de configuração
- Dashboard com estatísticas
- Lista de pedidos recentes

**WC_SGTM_Ajax** (`includes/class-ajax.php`)
- Handler de reenvio de webhook
- Validação de nonce e permissões

---

## 🎯 Fluxo de Dados

1. **Pedido pago no WooCommerce**
   - Hook: `woocommerce_order_status_completed`
   - Verifica: webhook ativo + não duplicado + pedido pago

2. **Preparação do payload**
   - User data: email, phone, name (hasheados + plain)
   - Custom data: valor, produtos, categorias, cupons
   - Metadata: source, versão, payment method

3. **Envio para SGTM**
   - POST para: `{url}/data?id={container_id}`
   - Headers: Content-Type, User-Agent, Authorization (opcional)
   - Timeout: 30s, SSL verify: true

4. **Processamento da resposta**
   - HTTP 2xx: sucesso → meta `_sgtm_webhook_sent`
   - HTTP erro: falha → meta `_sgtm_webhook_error`
   - WP_Error: erro de conexão → log + meta

---

## ⚙️ Configurações

Armazenadas em `wp_options`:

| Chave | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `wc_sgtm_webhook_url` | string | '' | URL base SGTM |
| `wc_sgtm_container_id` | string | '' | GTM-XXXXXXX |
| `wc_sgtm_webhook_token` | string | '' | Bearer token (opcional) |
| `wc_sgtm_webhook_enabled` | yes/no | 'no' | Ativar webhook |
| `wc_sgtm_debug_mode` | yes/no | 'no' | Logs detalhados |

---

## 🔒 Segurança

- **Sanitização:** `esc_url_raw()`, `sanitize_text_field()`
- **Escape:** `esc_html()`, `esc_attr()`, `esc_url()`
- **Nonce:** Todos os formulários verificados
- **Permissions:** `current_user_can('manage_woocommerce')`
- **SSL:** `sslverify: true`
- **Hash PII:** SHA-256 em todos os dados pessoais

---

## 🧪 Testes

### Testar localmente
```bash
# 1. Ativar modo debug
# WooCommerce > SGTM Webhook > Configurações
# [✓] Modo Debug → Salvar

# 2. Fazer pedido de teste
# Use gateway que confirma imediatamente (ex: PIX manual)

# 3. Ver logs
# WooCommerce > SGTM Webhook > Ferramentas
# Seção: Logs Recentes
```

### Comandos úteis
```bash
# Ver logs do WooCommerce
tail -f wp-content/uploads/wc-logs/wc-sgtm-webhook-*.log

# Verificar opções no banco
wp option get wc_sgtm_webhook_url
wp option get wc_sgtm_webhook_enabled

# Limpar metas de pedidos (forçar reenvio)
wp post meta delete 12345 _sgtm_webhook_sent
```

---

## 📝 Padrões de Código

### Naming Conventions
- Classes: `WC_SGTM_ClassName`
- Funções: `wc_sgtm_function_name()`
- Hooks: `wc_sgtm_hook_name`
- CSS: `.wc-sgtm-class-name`
- JS: `wcSgtmCamelCase`

### WordPress Coding Standards
- Indentação: tabs
- Espaços: ao redor de operadores
- Chaves: mesmo estilo K&R
- Strings: aspas simples (exceto interpolação)
- Arrays: formato longo `array()` em PHP < 5.4

### Documentação
- PHPDoc em todas as funções públicas
- `@param`, `@return`, `@throws` quando aplicável
- Comentários inline para lógica complexa

---

## 🐛 Debug Comum

### "Webhook não dispara"
1. Verificar: `wc_sgtm_webhook_enabled` = 'yes'
2. Verificar: pedido tem status 'completed' ou 'processing'
3. Verificar: `$order->is_paid()` retorna true
4. Ver logs: modo debug ativado

### "Erro 404 no endpoint"
- URL incorreta ou path `/data` inexistente
- Container ID ausente na URL
- SGTM server offline

### "Headers vazios"
- Token configurado mas vazio → corrigido em v3.0.0
- Validação: `if (!empty($token))` antes de adicionar header

---

## 🛣️ Roadmap

### v3.1.0 (Q1 2025)
- [ ] Suporte HPOS (High-Performance Order Storage)
- [ ] Eventos adicionais (ViewContent, AddToCart)
- [ ] ActionScheduler para filas
- [ ] Webhook por gateway

### v3.2.0 (Q2 2025)
- [ ] Múltiplos endpoints
- [ ] Campos customizados
- [ ] Exportação de relatórios PDF
- [ ] GA4 integration

---

## 📞 Contato

**Suporte:** suporte@trafegonaveia.com.br
**Desenvolvedor:** Carlos Araújo  
**Empresa:** Alta Cúpula
**Site:** https://ads.trafegonaveia.com.br

---

## 🎯 KPIs de Sucesso

| Métrica | Meta |
|---------|------|
| EMQ | ≥ 8.0/10 |
| ROAS | ≥ 7.0 |
| CPA | ≤ R$ 60 |
| Cobertura Match Keys | ≥ 95% |
| Uptime | 99.9% |

---

**Última Atualização:** 30/10/2024  
**Versão deste documento:** 1.0
