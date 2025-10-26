# Contribuindo para WC-SGTM-Webhook

Obrigado por contribuir! Siga estas diretrizes para manter qualidade, segurança e compatibilidade.

## Requisitos e ambiente
- PHP: mínimo `7.4` (testar também `8.0+`).
- WooCommerce: mínimo `5.8`.
- WordPress: versões LTS suportadas.
- Habilite `WP_DEBUG_LOG` em ambientes de desenvolvimento.

## Fluxo de git e branches
- Use `main` para releases estáveis.
- Crie branches por feature: `feat/nome-feature`, `fix/descricao-bug`, `chore/tarefa`.
- Commits seguindo [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `docs:`, `perf:`, `refactor:`, `test:`, `chore:`.
- Mensagens curtas e descritivas; inclua contexto no corpo quando necessário.

## Testes e regressões
- Rode testes manuais após cada mudança em:
  - Envio e processamento do webhook.
  - Painel de administração (salvar configurações, estatísticas, exportar logs).
  - Compatibilidade com PHP e WooCommerce em versões suportadas.
- Faça smoke tests após atualizações de WooCommerce/PHP. Verifique hooks alterados e APIs removidas.

## Segurança
- Sempre sanitize e escape dados (`sanitize_text_field`, `esc_url_raw`, `esc_html`, `esc_attr`).
- Use nonces (`wp_nonce_field`, `wp_verify_nonce`) e checagem de permissões (`current_user_can`).
- Não commitar tokens/URLs sensíveis. Use variáveis de ambiente ou opções.

## Performance
- Utilize cache (transients) para consultas custosas.
- Use `$wpdb->prepare` e `esc_like` em consultas dinâmicas.

## Hooks e extensibilidade
- Exponha filtros e ações em pontos-chave:
  - `wc_sgtm_webhook_headers`, `wc_sgtm_webhook_payload`, `wc_sgtm_webhook_request_args`, `wc_sgtm_webhook_response_received`, `wc_sgtm_statistics_cache_expiration`.

## Pull Requests
- Inclua descrição clara, passos de teste e checklist de segurança.
- Referencie issues relacionados.

Obrigado!