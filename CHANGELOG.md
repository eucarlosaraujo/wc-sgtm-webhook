# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [1.2.0] - 2025-10-26
- Corrige Erro Fatal na ativação: garante `logger` antes do `Installer`.
- Installer agora é resiliente: cria `WC_SGTM_Logger` se `plugin->get_logger()` for nulo.
- Protege chamadas `->info()` com checagem de `logger` para evitar `null`.
- Refatora `includes/helpers.php`:
  - Usa `wc_sgtm_get_setting()` para ler configurações do plugin com fallback às constantes.
  - Centraliza `webhook_url`, `webhook_enabled`, `timeout`, `validate_ssl` e `debug_mode`.
- Ajusta envio HTTP para respeitar configurações do painel (timeout/SSL/UA).
- Atualiza `.gitignore` para ignorar `assets/**/*.map`, `*.min.*`, `languages/*.mo`, cobertura e cache de testes.
- Documentação reforçada: README, CONTRIBUTING, SECURITY.

## [1.1.0] - 2025-10-25
- Corrige paths de assets admin para `assets/admin.js` e `assets/admin.css`.
- Unifica estatísticas do dashboard via `Statistics_Manager`.
- Remove método duplicado `get_enhanced_statistics()` no Admin.
- Elimina blocos residuais de SQL no arquivo principal (migração para Installer).
- Remove arquivo duplicado `includes/class-statistics-manager.php`.
- Ajusta construtores:
  - `WC_SGTM_Browser_Capture`, `WC_SGTM_Webhook_Sender`, `WC_SGTM_Admin_Panel` recebem `settings` e `logger`.
  - Admin passa instância do plugin no construtor.

## [1.0.0] - 2025-10-24
- Lançamento inicial do plugin.
- Integração com SGTM server-side via webhooks.
- Painel administrativo com estatísticas e logs.
- Configurações avançadas de timeout, SSL e debug.
- Rastreamento de eventos de e-commerce em tempo real
- Sistema de logs e depuração
- Estatísticas detalhadas de performance
- Sistema de retry para falhas temporárias
- Suporte a autenticação de webhooks
- Validação SSL configurável
- Widget do dashboard do WordPress
- Modo de fallback para maior confiabilidade

### Eventos Suportados
- Novo pedido criado
- Mudanças de status do pedido
- Pagamento concluído
- Adicionar produto ao carrinho
- Remover produto do carrinho
- Início do processo de checkout

### Recursos Técnicos
- Arquitetura modular e extensível
- Sistema de hooks e filtros do WordPress
- Compatibilidade com WooCommerce 5.0+
- Suporte a PHP 7.4+
- Logs estruturados para debugging
- Interface administrativa responsiva
- Internacionalização (i18n) preparada

### Segurança
- Validação de nonce em todas as ações AJAX
- Sanitização de dados de entrada
- Verificação de capacidades de usuário
- Escape de saída para prevenir XSS
- Validação de SSL opcional

[1.0.0]: https://github.com/seu-usuario/wc-sgtm-webhook/releases/tag/v1.0.0