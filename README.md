# WooCommerce SGTM Webhook

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/wc-sgtm-webhook.svg)](https://wordpress.org/plugins/wc-sgtm-webhook/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/wc-sgtm-webhook.svg)](https://wordpress.org/plugins/wc-sgtm-webhook/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/r/wc-sgtm-webhook.svg)](https://wordpress.org/plugins/wc-sgtm-webhook/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)](https://github.com/seu-usuario/wc-sgtm-webhook/blob/main/LICENSE)

Integração profissional do WooCommerce com Server-Side Google Tag Manager via webhooks para rastreamento avançado de e-commerce.

## 📋 Descrição

O **WooCommerce SGTM Webhook** fornece uma integração robusta entre o WooCommerce e o Server-Side Google Tag Manager (SGTM), permitindo o rastreamento preciso de eventos de e-commerce através de webhooks em tempo real.

### 🚀 Principais Recursos

- ✅ **Envio em tempo real** de eventos para SGTM
- 📊 **Rastreamento completo** de pedidos e status
- 🛒 **Monitoramento do carrinho** (adicionar/remover produtos)
- 💳 **Acompanhamento do checkout** e pagamentos
- 🔧 **Configuração simples** via painel administrativo
- 🔒 **Suporte a autenticação** para endpoints seguros
- 🐛 **Modo de depuração** para solução de problemas
- 📈 **Dashboard com estatísticas** detalhadas
- 🔄 **Sistema de retry** para falhas temporárias

### 📊 Eventos Rastreados

- **Novo Pedido** - Quando um pedido é criado
- **Status do Pedido** - Mudanças de status (pendente → processando → concluído)
- **Pagamento Concluído** - Confirmação de pagamentos
- **Adicionar ao Carrinho** - Produtos adicionados
- **Remover do Carrinho** - Produtos removidos
- **Checkout Iniciado** - Início do processo de checkout

### 🎯 Benefícios do Server-Side Tracking

- 📈 **Maior precisão** nos dados coletados
- 🚫 **Não afetado** por bloqueadores de anúncios
- ⚡ **Melhor performance** para o usuário
- 🔐 **Conformidade** com regulamentos de privacidade
- 📊 **Dados consistentes** para análise

## 🛠️ Instalação

### Via WordPress Admin

1. Acesse **Plugins → Adicionar Novo**
2. Pesquise por "WooCommerce SGTM Webhook"
3. Clique em **Instalar Agora**
4. **Ative** o plugin

### Via Upload Manual

1. Baixe o arquivo `.zip` do plugin
2. Acesse **Plugins → Adicionar Novo → Enviar Plugin**
3. Selecione o arquivo e clique em **Instalar Agora**
4. **Ative** o plugin

### Via FTP

1. Extraia o arquivo `.zip`
2. Envie a pasta `wc-sgtm-webhook` para `/wp-content/plugins/`
3. Ative o plugin no painel administrativo

## ⚙️ Configuração

1. Acesse **WooCommerce → SGTM Webhook**
2. Configure a **URL do Webhook** do seu servidor SGTM
3. Ative o **Envio de Webhooks**
4. Configure as opções avançadas conforme necessário
5. Teste a conexão usando o botão **Testar Webhook**

### Configurações Principais

- **URL do Webhook**: Endpoint do seu servidor SGTM
- **Timeout**: Tempo limite para requisições (padrão: 30s)
- **Tentativas**: Número de tentativas em caso de falha (padrão: 3)
- **Validar SSL**: Verificação de certificados SSL
- **Modo Debug**: Logs detalhados para depuração

## 📖 Uso

Após a configuração, o plugin funcionará automaticamente:

1. **Eventos são capturados** em tempo real
2. **Dados são enviados** via webhook para o SGTM
3. **Logs são registrados** para monitoramento
4. **Estatísticas são atualizadas** no dashboard

### Dashboard de Estatísticas

Acesse **WooCommerce → SGTM Webhook → Dashboard** para ver:

- Total de webhooks enviados
- Taxa de sucesso
- Erros recentes
- Performance por período
- Logs detalhados

## 🔧 Requisitos

- **WordPress**: 5.6 ou superior
- **WooCommerce**: 5.0 ou superior
- **PHP**: 7.4 ou superior
- **Servidor SGTM**: Configurado e funcionando

## 🤝 Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. **Fork** o repositório
2. Crie uma **branch** para sua feature (`git checkout -b feature/nova-feature`)
3. **Commit** suas mudanças (`git commit -am 'Adiciona nova feature'`)
4. **Push** para a branch (`git push origin feature/nova-feature`)
5. Abra um **Pull Request**

### Desenvolvimento Local

```bash
# Clone o repositório
git clone https://github.com/seu-usuario/wc-sgtm-webhook.git

# Entre na pasta
cd wc-sgtm-webhook

# Instale em um ambiente WordPress local
# (XAMPP, Local by Flywheel, etc.)
```

## 📝 Changelog

### 1.0.0 (2024-01-XX)
- 🎉 Lançamento inicial
- ✅ Integração básica com SGTM
- 📊 Dashboard de estatísticas
- 🔧 Configurações avançadas
- 🐛 Sistema de logs e debug

## 🆘 Suporte

- **Documentação**: [Wiki do GitHub](https://github.com/seu-usuario/wc-sgtm-webhook/wiki)
- **Issues**: [GitHub Issues](https://github.com/seu-usuario/wc-sgtm-webhook/issues)
- **Fórum WordPress**: [Plugin Support](https://wordpress.org/support/plugin/wc-sgtm-webhook/)

## 📄 Licença

Este plugin é licenciado sob a [GPL v2 ou posterior](https://www.gnu.org/licenses/gpl-2.0.html).

## 🙏 Créditos

Desenvolvido com ❤️ para a comunidade WordPress e WooCommerce.

---

**⭐ Se este plugin foi útil, considere deixar uma avaliação no [WordPress.org](https://wordpress.org/plugins/wc-sgtm-webhook/)!**