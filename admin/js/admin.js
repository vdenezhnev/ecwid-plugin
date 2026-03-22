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
            $(document).on('click', '#ecwid-wc-test-connection', this.testConnection.bind(this));

            // Start sync button.
            $(document).on('click', '#ecwid-wc-start-sync', this.startSync.bind(this));

            // Clear logs button.
            $(document).on('click', '#ecwid-wc-clear-logs', this.clearLogs.bind(this));

            // Toggle log context.
            $(document).on('click', '.ecwid-wc-toggle-context', this.toggleLogContext);

            // Toggle password visibility.
            $(document).on('click', '.ecwid-wc-toggle-password', this.togglePassword);

            // Form validation.
            $('#ecwid-wc-settings-form').on('submit', this.validateForm.bind(this));

            // Store ID validation on blur.
            $('#ecwid_wc_ecwid_store_id').on('blur', this.validateStoreId);
        },

        /**
         * Validate Store ID format.
         */
        validateStoreId: function() {
            var $input = $(this);
            var value = $input.val().trim();
            
            if (value && !/^\d+$/.test(value)) {
                EcwidWCAdmin.showFieldError($input, ecwidWcAdmin.strings.invalidStoreId);
                return false;
            } else {
                EcwidWCAdmin.clearFieldError($input);
                return true;
            }
        },

        /**
         * Validate form before submit.
         */
        validateForm: function(e) {
            var isValid = true;
            
            // Validate Store ID.
            var $storeId = $('#ecwid_wc_ecwid_store_id');
            var storeIdValue = $storeId.val().trim();
            
            if (storeIdValue && !/^\d+$/.test(storeIdValue)) {
                this.showFieldError($storeId, ecwidWcAdmin.strings.invalidStoreId);
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
            
            return isValid;
        },

        /**
         * Show field error.
         */
        showFieldError: function($field, message) {
            this.clearFieldError($field);
            $field.addClass('ecwid-wc-field-error');
            $field.after('<span class="ecwid-wc-field-error-message" style="color:#d63638;display:block;margin-top:5px;">' + message + '</span>');
        },

        /**
         * Clear field error.
         */
        clearFieldError: function($field) {
            $field.removeClass('ecwid-wc-field-error');
            $field.siblings('.ecwid-wc-field-error-message').remove();
        },

        /**
         * Toggle password visibility.
         */
        togglePassword: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $input = $button.siblings('input');
            var $icon = $button.find('.dashicons');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        },

        /**
         * Test API connection.
         */
        testConnection: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            
            if ($button.hasClass('ecwid-wc-testing')) {
                return;
            }

            var $container = $('#ecwid-wc-connection-status-container');
            var originalContent = $container.find('.ecwid-wc-status').clone();

            // Get current form values.
            var storeId = $('#ecwid_wc_ecwid_store_id').val().trim();
            var accessToken = $('#ecwid_wc_ecwid_access_token').val().trim();

            // Update UI.
            $button.addClass('ecwid-wc-testing').prop('disabled', true);
            $container.find('.ecwid-wc-status').html(
                '<span class="dashicons dashicons-update"></span> ' + ecwidWcAdmin.strings.testing
            ).removeClass('ecwid-wc-status-connected ecwid-wc-status-error ecwid-wc-status-unknown ecwid-wc-status-not-configured');

            $.ajax({
                url: ecwidWcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ecwid_wc_test_connection',
                    nonce: ecwidWcAdmin.nonce,
                    store_id: storeId,
                    access_token: accessToken
                },
                success: function(response) {
                    var $status = $container.find('.ecwid-wc-status');
                    
                    if (response.success) {
                        $status
                            .html('<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message)
                            .addClass('ecwid-wc-status-connected');
                        EcwidWCAdmin.showNotice('success', response.data.message);
                    } else {
                        $status
                            .html('<span class="dashicons dashicons-warning"></span> ' + (response.data.message || ecwidWcAdmin.strings.connectError))
                            .addClass('ecwid-wc-status-error');
                        EcwidWCAdmin.showNotice('error', response.data.message || ecwidWcAdmin.strings.connectError);
                    }
                },
                error: function() {
                    var $status = $container.find('.ecwid-wc-status');
                    $status
                        .html('<span class="dashicons dashicons-warning"></span> ' + ecwidWcAdmin.strings.connectError)
                        .addClass('ecwid-wc-status-error');
                    EcwidWCAdmin.showNotice('error', ecwidWcAdmin.strings.connectError);
                },
                complete: function() {
                    $button.removeClass('ecwid-wc-testing').prop('disabled', false);
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
            var originalHtml = $button.html();
            $button.html('<span class="dashicons dashicons-update"></span> ' + ecwidWcAdmin.strings.syncing);

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
                    $button.removeClass('ecwid-wc-syncing').prop('disabled', false).html(originalHtml);
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
            // Remove existing notices of same type.
            $('.ecwid-wc-admin-notice.' + type).remove();
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible ecwid-wc-admin-notice ' + type + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
            
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
