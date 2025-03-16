/**
 * Admin JavaScript for UIPress Analytics Bridge
 *
 * Handles all admin interactions including authentication,
 * cache clearing, and other admin functionality.
 */
(function($) {
    'use strict';

    /**
     * DOM Ready handler
     */
    $(function() {
        var oauthWindow = null;
        
        /**
         * Handle Global Authentication Button
         */
        $('#uipress-global-auth-button').on('click', function(e) {
            e.preventDefault();
            initiateOAuth(false);
        });
        
        /**
         * Handle User Authentication Button
         */
        $('#uipress-user-auth-button').on('click', function(e) {
            e.preventDefault();
            initiateOAuth(true);
        });
        
        /**
         * Handle Clear Cache Button
         */
        $('#uip-analytics-clear-cache').on('click', function(e) {
            e.preventDefault();
            clearCache();
        });
        
        /**
         * Handle Clear All Caches Button
         */
        $('#uip-analytics-clear-cache-all').on('click', function(e) {
            e.preventDefault();
            clearAllCaches();
        });
        
        /**
         * Handle Test Connection Button
         */
        $('#uip-analytics-test-connection').on('click', function(e) {
            e.preventDefault();
            testApiConnection();
        });
        
        /**
         * Handle Refresh Status Button
         */
        $('#uip-analytics-refresh-status').on('click', function(e) {
            e.preventDefault();
            refreshStatus();
        });
        
        /**
         * Initiate OAuth authentication flow
         * 
         * @param {boolean} saveToUser - Whether to save account to user preferences
         */
        function initiateOAuth(saveToUser) {
            // Get OAuth URL from server
            $.ajax({
                url: uipress_analytics_bridge_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_oauth_url',
                    security: uipress_analytics_bridge_admin.security,
                    saveAccountToUser: saveToUser
                },
                beforeSend: function() {
                    showMessage('loading', uipress_analytics_bridge_admin.loading_text);
                },
                success: function(response) {
                    if (response.success && response.url) {
                        // Open OAuth popup window
                        var width = 600;
                        var height = 700;
                        var left = (screen.width / 2) - (width / 2);
                        var top = (screen.height / 2) - (height / 2);
                        
                        oauthWindow = window.open(
                            response.url,
                            'uipress_analytics_auth',
                            'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left
                        );
                        
                        // Create listener for OAuth result
                        window.addEventListener('message', function(event) {
                            if (event.data.type === 'uipress_analytics_bridge_auth') {
                                handleAuthResult(event.data, saveToUser);
                            }
                        });
                        
                        hideMessage();
                    } else {
                        showMessage('error', response.message || 'Failed to get OAuth URL');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('error', 'AJAX error: ' + error);
                }
            });
        }
        
        /**
         * Handle OAuth authentication result
         * 
         * @param {object} data - The authentication data from the OAuth window
         * @param {boolean} saveToUser - Whether to save account to user preferences
         */
        function handleAuthResult(data, saveToUser) {
            if (data.success && data.code && data.property_id) {
                // Save the auth data to server
                $.ajax({
                    url: uipress_analytics_bridge_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'uipress_analytics_bridge_auth',
                        security: uipress_analytics_bridge_admin.security,
                        code: data.code,
                        property_id: data.property_id,
                        saveAccountToUser: saveToUser
                    },
                    beforeSend: function() {
                        showMessage('loading', 'Saving authentication data...');
                    },
                    success: function(response) {
                        if (response.success) {
                            showMessage('success', response.message || 'Authentication successful');
                            refreshStatus();
                        } else {
                            showMessage('error', response.message || 'Failed to save authentication data');
                        }
                    },
                    error: function(xhr, status, error) {
                        showMessage('error', 'AJAX error: ' + error);
                    }
                });
            } else {
                showMessage('error', data.message || 'Authentication failed');
            }
            
            // Close the OAuth window if still open
            if (oauthWindow && !oauthWindow.closed) {
                oauthWindow.close();
            }
        }
        
        /**
         * Clear cache for analytics data
         */
        function clearCache() {
            $.ajax({
                url: uipress_analytics_bridge_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_clear_cache',
                    security: uipress_analytics_bridge_admin.security
                },
                beforeSend: function() {
                    showMessage('loading', 'Clearing cache...');
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.message || 'Cache cleared successfully');
                    } else {
                        showMessage('error', response.message || 'Failed to clear cache');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('error', 'AJAX error: ' + error);
                }
            });
        }
        
        /**
         * Clear all caches (analytics data, authentication, etc.)
         */
        function clearAllCaches() {
            $.ajax({
                url: uipress_analytics_bridge_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_clear_all_caches',
                    security: uipress_analytics_bridge_admin.security
                },
                beforeSend: function() {
                    showMessage('loading', 'Clearing all caches...');
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.message || 'All caches cleared successfully');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage('error', response.message || 'Failed to clear all caches');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('error', 'AJAX error: ' + error);
                }
            });
        }
        
        /**
         * Test API connection to Google Analytics
         */
        function testApiConnection() {
            $.ajax({
                url: uipress_analytics_bridge_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_test_connection',
                    security: uipress_analytics_bridge_admin.security
                },
                beforeSend: function() {
                    $('#uip-analytics-test-result')
                        .removeClass('success error')
                        .html('<p>' + uipress_analytics_bridge_admin.loading_text + '</p>')
                        .show();
                },
                success: function(response) {
                    var resultDiv = $('#uip-analytics-test-result');
                    
                    if (response.success) {
                        resultDiv
                            .addClass('success')
                            .removeClass('error')
                            .html('<p><strong>Success!</strong> ' + (response.message || 'API connection successful') + '</p>');
                        
                        if (response.data) {
                            var html = '<ul>';
                            for (var key in response.data) {
                                if (response.data.hasOwnProperty(key)) {
                                    html += '<li><strong>' + key + ':</strong> ' + response.data[key] + '</li>';
                                }
                            }
                            html += '</ul>';
                            resultDiv.append(html);
                        }
                    } else {
                        resultDiv
                            .addClass('error')
                            .removeClass('success')
                            .html('<p><strong>Error:</strong> ' + (response.message || 'API connection failed') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#uip-analytics-test-result')
                        .addClass('error')
                        .removeClass('success')
                        .html('<p><strong>AJAX Error:</strong> ' + error + '</p>')
                        .show();
                }
            });
        }
        
        /**
         * Refresh status display
         */
        function refreshStatus() {
            $.ajax({
                url: uipress_analytics_bridge_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'uipress_analytics_bridge_refresh_status',
                    security: uipress_analytics_bridge_admin.security
                },
                beforeSend: function() {
                    $('#uipress-global-auth-status, #uipress-user-auth-status').html(uipress_analytics_bridge_admin.loading_text);
                },
                success: function(response) {
                    if (response.success) {
                        if (response.global_status) {
                            $('#uipress-global-auth-status').html(response.global_status);
                        }
                        
                        if (response.user_status) {
                            $('#uipress-user-auth-status').html(response.user_status);
                        }
                    } else {
                        showMessage('error', response.message || 'Failed to refresh status');
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('error', 'AJAX error: ' + error);
                }
            });
        }
        
        /**
         * Show message to user
         * 
         * @param {string} type - Message type (loading, success, error)
         * @param {string} message - Message content
         */
        function showMessage(type, message) {
            var notice = $('<div class="notice is-dismissible"></div>');
            
            if (type === 'loading') {
                notice.addClass('notice-info');
            } else if (type === 'success') {
                notice.addClass('notice-success');
            } else if (type === 'error') {
                notice.addClass('notice-error');
            }
            
            notice.append('<p>' + message + '</p>');
            
            // Remove existing notices
            $('.uipress-analytics-bridge-admin > .notice').remove();
            
            // Insert new notice
            $('.uipress-analytics-bridge-admin > h1').after(notice);
            
            // Add dismiss button functionality
            if (type !== 'loading') {
                var button = $('<button type="button" class="notice-dismiss"></button>');
                button.append('<span class="screen-reader-text">Dismiss this notice.</span>');
                button.on('click', function() {
                    notice.remove();
                });
                notice.append(button);
                
                // Auto dismiss after 5 seconds for success messages
                if (type === 'success') {
                    setTimeout(function() {
                        notice.fadeOut(function() {
                            notice.remove();
                        });
                    }, 5000);
                }
            }
        }
        
        /**
         * Hide any displayed messages
         */
        function hideMessage() {
            $('.uipress-analytics-bridge-admin > .notice').remove();
        }
    });

})(jQuery);