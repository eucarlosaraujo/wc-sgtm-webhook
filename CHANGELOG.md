# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [3.1.0] - 2025-11-04
### ✅ Adicionado

- Captura automática de User Agent no checkout
- Função guess_gender() para inferir gênero pelo primeiro nome
- Busca de categoria de produtos via WooCommerce API

### ✅ Alterado

- **user_data**: Todos os campos agora em formato Meta Ads (lowercase, sem hash)
- user_data.ph: Adiciona prefixo "55" automaticamente se ausente
- user_data.fn: Retorna apenas primeiro nome
- user_data.ln: Retorna apenas último sobrenome
- user_data.ct: Remove acentos e espaços da cidade
- user_data.zp: Retorna apenas 5 primeiros dígitos
- user_data.client_ip_address: Busca do pedido WooCommerce
- user_data.client_user_agent: Busca do meta _customer_user_agent
- **custom_data.content_type**: Fixo como "product_group"
- custom_data.contents: Agora inclui campo category para cada produto
- custom_data.transaction_id: Convertido para string
- custom_data.num_items: Soma total de quantidades dos itens

### 🔄 Melhorado

Função normalize_city() otimizada para UTF-8
Compatibilidade total com Meta Conversions API
Event Match Quality aprimorado

## [3.0.0] - 2024-10-30

### 🎉 Lançamento Inicial

Primeira versão pública do WC SGTM Webhook Pro.

### ✅ Adicionado

#### Core
- Envio automático de eventos `purchase` para SGTM quando pedido é pago
- Suporte a hooks: `woocommerce_order_status_completed`, `woocommerce_order_status_processing`, `woocommerce_payment_complete`
- Preparação de payload com todos os match keys do Meta Ads
- Hash SHA-256 automático de dados pessoais (PII)
- Deduplicação por `event_id` único
- Sistema de retry com meta tracking

#### Configuração
- URL do webhook configurável
- Container ID (GTM-XXXXX) configurável
- Bearer Token opcional para autenticação
- Toggle para ativar/desativar webhook
- Modo debug com logs detalhados

#### Interface Admin
- Dashboard com estatísticas em tempo real:
  - Status do webhook (ativo/inativo)
  - Envios hoje
  - Total enviado
  - Modo debug
  - Último envio
- Aba de configurações com validação de campos
- Aba de pedidos com lista dos últimos 20 pedidos:
  - Status de envio do webhook
  - Código de resposta HTTP
  - Botão de reenvio manual
- Aba de ferramentas com:
  - Teste de conexão
  - Limpeza de logs
  - Visualização de logs recentes
  - Informações do sistema

#### Sistema de Logs
- Logs categorizados por nível (ERROR, WARNING, INFO, DEBUG)
- Rotação automática (mantém últimos 7 dias)
- Integração com WooCommerce Logger
- Visualização de logs na interface admin

#### Segurança
- Verificação de nonce em todas as ações
- Sanitização de inputs
- Escape de outputs
- Verificação de permissões
- SSL verify habilitado

#### Dados Enviados
Match keys de alta prioridade:
- `em` (email) - hasheado + plain
- `ph` (phone) - hasheado + plain
- `fn` (first name) - hasheado + plain
- `ln` (last name) - hasheado + plain

Match keys de média/baixa prioridade:
- `ct` (city)
- `st` (state)
- `zp` (zip code)
- `country` (country code)
- `external_id` (user ID)

Dados do pedido:
- `currency`, `value`, `order_id`
- `num_items`, `content_ids`, `content_names`
- `content_category`, `contents` (detalhado)
- `subtotal`, `tax`, `shipping`, `discount`
- `coupon` (se aplicável)

Metadados:
- `source`: woocommerce
- `plugin_version`
- `site_url`
- `order_status`
- `payment_method`
- `order_date`

### 🔧 Tecnologias

- PHP 7.4+
- WordPress 6.0+
- WooCommerce 7.0+
- JavaScript (jQuery)
- CSS3

### 📦 Estrutura

```
wc-sgtm-webhook/
├── wc-sgtm-webhook.php      # Arquivo principal
├── includes/
│   ├── class-helpers.php     # Funções auxiliares
│   ├── class-core.php        # Lógica de envio
│   ├── class-admin.php       # Interface admin
│   └── class-ajax.php        # Handlers AJAX
├── assets/
│   ├── css/admin.css         # Estilos admin
│   └── js/admin.js           # Scripts admin
├── languages/                # Traduções (futuro)
├── readme.txt                # README WordPress.org
└── CHANGELOG.md              # Este arquivo
```

### 🔒 Conformidade

- **LGPD**: Hash de todos os dados pessoais
- **SSL/TLS**: Transmissão criptografada
- **WordPress Coding Standards**: Seguido
- **WooCommerce Guidelines**: Seguido

### ⚠️ Limitações Conhecidas

- Não suporta HPOS (High-Performance Order Storage) - planejado para v3.1
- Apenas evento `purchase` - eventos adicionais planejados para v3.1
- Interface apenas em inglês/português - i18n completo planejado para v3.2

---

## [Próximas Versões]

### [3.1.0] - Planejado para Q1 2025

#### Planejado
- [ ] Suporte a HPOS (High-Performance Order Storage)
- [ ] Eventos adicionais: `ViewContent`, `AddToCart`, `InitiateCheckout`, `AddPaymentInfo`
- [ ] Integração com ActionScheduler para filas
- [ ] Webhook personalizado por gateway de pagamento
- [ ] Campos FBP/FBC via cookies (JavaScript)
- [ ] Dashboard melhorado com gráficos

### [3.2.0] - Planejado para Q2 2025

#### Planejado
- [ ] Suporte a múltiplos endpoints
- [ ] Campos customizados configuráveis
- [ ] Exportação de relatórios em PDF
- [ ] Integração com Google Analytics 4
- [ ] Internacionalização completa (i18n)
- [ ] Suporte a webhooks condicionais (regras)

---

## Tipos de Mudanças

- `✅ Adicionado` para novas funcionalidades
- `🔧 Modificado` para mudanças em funcionalidades existentes
- `❌ Depreciado` para funcionalidades que serão removidas
- `🗑️ Removido` para funcionalidades removidas
- `🐛 Corrigido` para correção de bugs
- `🔒 Segurança` para correções de vulnerabilidades

---

## Contribuindo

Para contribuir com o projeto, consulte [CONTRIBUTING.md](CONTRIBUTING.md).

## Licença

GPL v3 ou posterior. Consulte [LICENSE](LICENSE) para mais detalhes.
