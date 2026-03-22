/**
 * Ecwid WooCommerce Sync - Admin Scripts
 */

(function($) {
    'use strict';

    var EcwidSyncAdmin = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#ecwid-test-connection').on('click', this.testConnection);
            $('#ecwid-manual-sync').on('click', this.manualSync);
            $('#ecwid-full-sync').on('click', this.fullSync);
            $('#ecwid-register-webhook').on('click', this.registerWebhook);
            $('#ecwid-clear-logs').on('click', this.clearLogs);
        },

        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#ecwid-connection-result');
            
            $button.prop('disabled', true).addClass('ecwid-loading');
            $result.removeClass('success error').text(ecwidSync.strings.testing);

            $.ajax({
                url: ecwidSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_test_connection',
                    nonce: ecwidSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(response.data.message);
                    } else {
                        $result.addClass('error').text(ecwidSync.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text(ecwidSync.strings.error + ' Connection failed');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ecwid-loading');
                }
            });
        },

        manualSync: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#ecwid-sync-result');
            
            $button.prop('disabled', true).addClass('ecwid-loading');
            $result.removeClass('success error').text(ecwidSync.strings.syncing);

            $.ajax({
                url: ecwidSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_manual_sync',
                    nonce: ecwidSync.nonce,
                    full_sync: 'false'
                },
                success: function(response) {
                    if (response.success) {
                        var message = response.data.message;
                        if (response.data.result && response.data.result.imported !== undefined) {
                            message += ' Imported: ' + response.data.result.imported + 
                                      ', Updated: ' + response.data.result.updated;
                        }
                        $result.addClass('success').text(message);
                    } else {
                        $result.addClass('error').text(ecwidSync.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text(ecwidSync.strings.error + ' Sync failed');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ecwid-loading');
                }
            });
        },

        fullSync: function(e) {
            e.preventDefault();
            
            if (!confirm(ecwidSync.strings.confirm + ' This may take a while for large order histories.')) {
                return;
            }
            
            var $button = $(this);
            var $result = $('#ecwid-sync-result');
            var fromDate = $('#ecwid-full-sync-date').val();
            
            if (!fromDate) {
                alert('Please select a date');
                return;
            }
            
            $button.prop('disabled', true).addClass('ecwid-loading');
            $result.removeClass('success error').text(ecwidSync.strings.syncing);

            $.ajax({
                url: ecwidSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_manual_sync',
                    nonce: ecwidSync.nonce,
                    full_sync: 'true',
                    from_date: fromDate
                },
                timeout: 300000, // 5 minutes timeout for full sync
                success: function(response) {
                    if (response.success) {
                        var message = response.data.message;
                        if (response.data.result) {
                            message += ' Imported: ' + (response.data.result.imported || 0) + 
                                      ', Updated: ' + (response.data.result.updated || 0) +
                                      ', Errors: ' + (response.data.result.errors || 0);
                        }
                        $result.addClass('success').text(message);
                    } else {
                        $result.addClass('error').text(ecwidSync.strings.error + ' ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = error;
                    if (status === 'timeout') {
                        errorMsg = 'Request timed out. The sync may still be running in the background.';
                    }
                    $result.addClass('error').text(ecwidSync.strings.error + ' ' + errorMsg);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ecwid-loading');
                }
            });
        },

        registerWebhook: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#ecwid-webhook-result');
            
            $button.prop('disabled', true).addClass('ecwid-loading');
            $result.removeClass('success error').text('Registering webhook...');

            $.ajax({
                url: ecwidSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_register_webhook',
                    nonce: ecwidSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('success').text(response.data.message);
                    } else {
                        $result.addClass('error').text(ecwidSync.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('error').text(ecwidSync.strings.error + ' Registration failed');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ecwid-loading');
                }
            });
        },

        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm(ecwidSync.strings.confirm)) {
                return;
            }
            
            var $button = $(this);
            
            $button.prop('disabled', true).addClass('ecwid-loading');

            $.ajax({
                url: ecwidSync.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_clear_logs',
                    nonce: ecwidSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(ecwidSync.strings.error + ' ' + response.data.message);
                    }
                },
                error: function() {
                    alert(ecwidSync.strings.error + ' Operation failed');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ecwid-loading');
                }
            });
        }
    };

    $(document).ready(function() {
        EcwidSyncAdmin.init();
    });

})(jQuery);
