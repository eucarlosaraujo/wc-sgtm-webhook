# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [1.0.0] - 2024-01-XX

### Adicionado
- Integração inicial com Server-Side Google Tag Manager
- Dashboard administrativo completo
- Sistema de configurações avançadas
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