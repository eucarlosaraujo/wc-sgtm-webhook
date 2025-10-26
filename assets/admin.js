/**
 * JavaScript para o painel administrativo do WooCommerce SGTM Webhook
 */

(function($) {
    'use strict';

    // Inicializar quando o DOM estiver pronto
    $(document).ready(function() {
        // Manipular a exibição de campos dependentes
        var debugMode = $('#wc_sgtm_webhook_debug_mode');
        var endpointField = $('#wc_sgtm_webhook_endpoint');

        // Verificar conexão com o endpoint
        $('#wc-sgtm-webhook-test-connection').on('click', function(e) {
            e.preventDefault();
            
            var endpoint = endpointField.val();
            if (!endpoint) {
                alert('Por favor, insira uma URL de endpoint válida.');
                return;
            }

            // Mostrar indicador de carregamento
            $(this).addClass('loading').prop('disabled', true);
            
            // Enviar requisição de teste
            $.ajax({
                url: wc_sgtm_webhook_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_sgtm_webhook_test_connection',
                    endpoint: endpoint,
                    nonce: wc_sgtm_webhook_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Conexão bem-sucedida! O endpoint está respondendo corretamente.');
                    } else {
                        alert('Falha na conexão: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Erro ao testar a conexão. Verifique o console para mais detalhes.');
                },
                complete: function() {
                    // Remover indicador de carregamento
                    $('#wc-sgtm-webhook-test-connection').removeClass('loading').prop('disabled', false);
                }
            });
        });

        // Atualizar status de eventos selecionados
        $('input[name="wc_sgtm_webhook_events[]"]').on('change', function() {
            updateEventsStatus();
        });

        function updateEventsStatus() {
            var selectedCount = $('input[name="wc_sgtm_webhook_events[]"]:checked').length;
            var totalCount = $('input[name="wc_sgtm_webhook_events[]"]').length;
            
            if (selectedCount === 0) {
                $('#wc-sgtm-webhook-events-status').html(
                    '<div class="notice notice-warning inline"><p>Nenhum evento selecionado. O plugin não enviará dados.</p></div>'
                );
            } else {
                $('#wc-sgtm-webhook-events-status').html(
                    '<div class="notice notice-success inline"><p>' + selectedCount + ' de ' + totalCount + ' eventos selecionados.</p></div>'
                );
            }
        }

        // Inicializar status
        updateEventsStatus();
    });

})(jQuery);