/**
 * WC SGTM Webhook Pro - Admin Scripts
 */

(function($) {
    'use strict';
    
    const WcSgtmAdmin = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Resend webhook button
            $(document).on('click', '.wc-sgtm-resend', this.resendWebhook);
            
            // Show/hide token field
            $('#webhook_token').on('focus', function() {
                $(this).attr('type', 'text');
            }).on('blur', function() {
                $(this).attr('type', 'password');
            });
        },
        
        resendWebhook: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const orderId = $button.data('order-id');
            
            if (!orderId) {
                alert('ID do pedido não encontrado.');
                return;
            }
            
            if (!confirm('Reenviar webhook para o pedido #' + orderId + '?')) {
                return;
            }
            
            // Set loading state
            $button.addClass('loading').prop('disabled', true);
            const originalText = $button.html();
            $button.html('⏳ Enviando...');
            
            // Send AJAX request
            $.ajax({
                url: wcSgtmAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_sgtm_resend_webhook',
                    nonce: wcSgtmAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        // Reload page to show updated status
                        location.reload();
                    } else {
                        alert('❌ ' + response.data.message);
                        $button.removeClass('loading').prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Erro de comunicação ao reenviar webhook.';
                    
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    
                    alert('❌ ' + errorMsg);
                    $button.removeClass('loading').prop('disabled', false).html(originalText);
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        WcSgtmAdmin.init();
    });
    
})(jQuery);
