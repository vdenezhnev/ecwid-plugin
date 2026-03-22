/**
 * Ecwid WooCommerce Admin JavaScript
 *
 * @package Ecwid_WooCommerce
 * @since   1.0.0
 */

(function($) {
    'use strict';

    var EcwidWCAdmin = {
        /**
         * Initialize admin functionality.
         */
        init: function() {
            this.bindEvents();
            this.loadSyncStatus();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            // Test connection button.
            $('#ecwid-wc-test-connection').on('click', this.testConnection.bind(this));

            // Start sync button.
            $('#ecwid-wc-start-sync').on('click', this.startSync.bind(this));

            // Clear logs button.
            $('#ecwid-wc-clear-logs').on('click', this.clearLogs.bind(this));

            // Toggle log context.
            $(document).on('click', '.ecwid-wc-toggle-context', this.toggleLogContext);
        },

        /**
         * Test API connection.
         */
        testConnection: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var originalText = $button.text();

            $button.prop('disabled', true).text(ecwidWcAdmin.strings.testing);

            $.ajax({
                url: ecwidWcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_wc_test_connection',
                    nonce: ecwidWcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EcwidWCAdmin.showNotice('success', ecwidWcAdmin.strings.connected);
                    } else {
                        EcwidWCAdmin.showNotice('error', response.data.message || ecwidWcAdmin.strings.connectError);
                    }
                },
                error: function() {
                    EcwidWCAdmin.showNotice('error', ecwidWcAdmin.strings.connectError);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Start synchronization.
         */
        startSync: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);

            if ($button.hasClass('ecwid-wc-syncing')) {
                return;
            }

            $button.addClass('ecwid-wc-syncing').prop('disabled', true);
            $button.find('.dashicons').after(' ' + ecwidWcAdmin.strings.syncing);

            // Show progress.
            $('#ecwid-wc-sync-progress').slideDown();
            this.updateProgress(0, ecwidWcAdmin.strings.syncing);

            $.ajax({
                url: ecwidWcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_wc_start_sync',
                    nonce: ecwidWcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EcwidWCAdmin.showNotice('success', ecwidWcAdmin.strings.syncComplete);
                        EcwidWCAdmin.loadSyncStatus();
                    } else {
                        EcwidWCAdmin.showNotice('error', response.data.message || ecwidWcAdmin.strings.syncError);
                    }
                },
                error: function() {
                    EcwidWCAdmin.showNotice('error', ecwidWcAdmin.strings.syncError);
                },
                complete: function() {
                    $button.removeClass('ecwid-wc-syncing').prop('disabled', false);
                    $button.contents().filter(function() {
                        return this.nodeType === 3;
                    }).remove();
                    $('#ecwid-wc-sync-progress').slideUp();
                }
            });
        },

        /**
         * Clear logs.
         */
        clearLogs: function(e) {
            e.preventDefault();

            if (!confirm(ecwidWcAdmin.strings.confirm)) {
                return;
            }

            var $button = $(e.currentTarget);
            $button.prop('disabled', true);

            $.ajax({
                url: ecwidWcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_wc_clear_logs',
                    nonce: ecwidWcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EcwidWCAdmin.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        EcwidWCAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    EcwidWCAdmin.showNotice('error', 'An error occurred.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Toggle log context visibility.
         */
        toggleLogContext: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $context = $button.siblings('.ecwid-wc-log-context');

            $context.slideToggle(200);
            $button.text($context.is(':visible') ? 'Hide details' : 'Show details');
        },

        /**
         * Load sync status.
         */
        loadSyncStatus: function() {
            if ($('#ecwid-wc-start-sync').length === 0) {
                return;
            }

            $.ajax({
                url: ecwidWcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_wc_get_sync_status',
                    nonce: ecwidWcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EcwidWCAdmin.updateSyncStatus(response.data);
                    }
                }
            });
        },

        /**
         * Update sync status display.
         */
        updateSyncStatus: function(data) {
            // Products.
            $('#products-synced').text(data.products.synced);
            $('#products-pending').text(data.products.pending);
            $('#products-failed').text(data.products.failed);

            // Orders.
            $('#orders-synced').text(data.orders.synced);
            $('#orders-pending').text(data.orders.pending);
            $('#orders-failed').text(data.orders.failed);

            // Customers.
            $('#customers-synced').text(data.customers.synced);
            $('#customers-pending').text(data.customers.pending);
            $('#customers-failed').text(data.customers.failed);

            // Last sync.
            if (data.last_sync) {
                $('.ecwid-wc-last-sync').text('Last sync: ' + data.last_sync);
            }
        },

        /**
         * Update progress bar.
         */
        updateProgress: function(percent, text) {
            $('#ecwid-wc-progress-bar').css('width', percent + '%');
            $('#ecwid-wc-progress-text').text(text);
        },

        /**
         * Show admin notice.
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').first().after($notice);
            
            // Auto-dismiss after 5 seconds.
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Make dismissible work.
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize on document ready.
    $(document).ready(function() {
        EcwidWCAdmin.init();
    });

})(jQuery);
