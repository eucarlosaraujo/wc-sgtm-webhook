=== WooCommerce SGTM Webhook ===
Contributors: seuusuario
Donate link: https://seusite.com/
Tags: woocommerce, google tag manager, sgtm, webhook, analytics, ecommerce
Requires at least: 5.6
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
WC requires at least: 5.0
WC tested up to: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integração do WooCommerce com o Server-Side Google Tag Manager via webhooks para rastreamento avançado de e-commerce.

== Description ==

O plugin WooCommerce SGTM Webhook fornece uma integração robusta entre o WooCommerce e o Server-Side Google Tag Manager (SGTM), permitindo o rastreamento avançado de eventos de e-commerce através de webhooks.

= Principais Recursos =

* Envio de eventos em tempo real para o SGTM
* Rastreamento de pedidos novos e atualizações de status
* Monitoramento de ações do carrinho (adicionar/remover produtos)
* Acompanhamento do processo de checkout
* Configuração simples através do painel administrativo do WordPress
* Suporte a autenticação para endpoints SGTM seguros
* Modo de depuração para solução de problemas

= Eventos Rastreados =

* Novo Pedido
* Mudança de Status do Pedido
* Pagamento Concluído
* Adicionar ao Carrinho
* Remover do Carrinho
* Atualização do Checkout

= Benefícios do Server-Side Tracking =

O rastreamento server-side oferece várias vantagens em relação ao rastreamento tradicional baseado em navegador:

* Maior precisão nos dados coletados
* Não é afetado por bloqueadores de anúncios
* Melhor desempenho para o usuário final
* Conformidade aprimorada com regulamentos de privacidade
* Dados mais consistentes para análise

= Compatibilidade =

Este plugin requer:

* WordPress 5.6 ou superior
* WooCommerce 5.0 ou superior
* PHP 7.2 ou superior

== Installation ==

1. Faça upload dos arquivos do plugin para o diretório `/wp-content/plugins/wc-sgtm-webhook`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse WooCommerce > SGTM Webhook para configurar o plugin
4. Insira a URL do endpoint do seu Server-Side GTM
5. Selecione os eventos que deseja rastrear
6. Salve as configurações

== Frequently Asked Questions ==

= O que é o Server-Side Google Tag Manager? =

O Server-Side Google Tag Manager (SGTM) é uma extensão do Google Tag Manager que permite processar tags e eventos no servidor em vez de no navegador do usuário. Isso oferece maior controle, segurança e precisão no rastreamento de dados.

= Preciso ter conhecimento técnico para usar este plugin? =

O plugin foi projetado para ser fácil de configurar, mas você precisará ter acesso a um ambiente de Server-Side GTM configurado e saber a URL do endpoint para onde os dados serão enviados.

= Este plugin funciona com outros plugins de e-commerce? =

Não, este plugin foi desenvolvido especificamente para o WooCommerce.

= Os dados são enviados em tempo real? =

Sim, os eventos são enviados para o SGTM assim que ocorrem no site.

= Como posso verificar se os dados estão sendo enviados corretamente? =

Você pode ativar o modo de depuração nas configurações do plugin para registrar informações detalhadas no log do WordPress.

== Screenshots ==

1. Tela de configurações do plugin
2. Exemplo de dados enviados para o SGTM
3. Integração com o painel do WooCommerce

== Changelog ==

= 1.0.0 =
* Lançamento inicial

== Upgrade Notice ==

= 1.0.0 =
Versão inicial do plugin WooCommerce SGTM Webhook.