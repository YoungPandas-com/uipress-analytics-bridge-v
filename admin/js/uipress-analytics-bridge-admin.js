/**
 * Admin JavaScript for UIPress Analytics Bridge.
 */
(function($) {
    'use strict';

    /**
     * Initialize the admin interface.
     */
    function initAdmin() {
        // Handle tab navigation
        initTabs();
        
        // Handle authentication
        initAuthentication();
        
        // Handle clear cache
        initClearCache();
    }

    /**
     * Initialize tab navigation.
     */
    function initTabs() {
        $('.uipress-analytics-bridge-tab-nav a').on('click', function(e) {
            e.preventDefault();
            
            // Get the target tab
            var target = $(this).attr('href');
            
            // Remove active class from all tabs
            $('.uipress-analytics-bridge-tab-nav a').removeClass('active');
            $('.uipress-analytics-bridge-tab-pane').removeClass('active');
            
            // Add active class to the clicked tab
            $(this).addClass('active');
            $(target).addClass('active');
            
            // Update the URL hash
            window.location.hash = target;
        });
        
        // Check for hash in URL
        if (window.location.hash) {
            var hash = window.location.hash;
            if ($(hash).length) {
                $('.uipress-analytics-bridge-tab-nav a[href="' + hash + '"]').trigger('click');
            }
        }
    }

    /**
     * Initialize authentication functionality.
     */
    function initAuthentication() {
        // Authentication button
        $('#authenticate-button').on('click', function(e) {
            e.preventDefault();
            openAuthWindow();
        });
        
        // Revoke button
        $('#revoke-button').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to revoke Google Analytics authentication?')) {
                revokeAuthentication();
            }
        });
        
        // Test connection button
        $('#test-connection-button').on('click', function(e) {
            e.preventDefault();
            testConnection();
        });
    }

    /**
     * Initialize clear cache functionality.
     */
    function initClearCache() {
        $('#clear-cache-button').on('click', function(e) {
            e.preventDefault();
            clearCache();
        });
    }

    /**
     * Open the Google Authentication window.
     */
    function openAuthWindow() {
        // Get the auth URL from localized script
        var authUrl = uipAnalyticsBridge.authUrl;
        
        // Open the auth window
        var authWindow = window.open(authUrl, 'uipress_analytics_auth', 'width=600,height=700');
        
        // Check for auth completion
        var authCheckInterval = setInterval(function() {
            try {
                // Check if the window has been closed
                if (authWindow.closed) {
                    clearInterval(authCheckInterval);
                    
                    // Reload the page to refresh the auth status
                    window.location.reload();
                }
                
                // Check if the window URL has the auth code
                if (authWindow.location.href.indexOf('code=') !== -1) {
                    // Extract the auth code
                    var code = authWindow.location.href.split('code=')[1].split('&')[0];
                    
                    // Close the auth window
                    authWindow.close();
                    clearInterval(authCheckInterval);
                    
                    // Handle the auth code
                    handleAuthCode(code);
                }
            } catch (e) {
                // Ignore cross-origin errors
            }
        }, 500);
    }

    /**
     * Handle the authentication code.
     * 
     * @param {string} code The authentication code from Google.
     */
    function handleAuthCode(code) {
        // Show loading message
        showMessage('Processing authentication...', 'info');
        
        // Send the auth code to the server
        $.ajax({
            url: uipAnalyticsBridge.ajaxurl,
            type: 'POST',
            data: {
                action: 'uipress_analytics_bridge_auth',
                security: uipAnalyticsBridge.nonce,
                code: code,
                saveAccountToUser: 'false'
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Successfully authenticated with Google Analytics.', 'success');
                    
                    // Update the auth status
                    updateAuthStatus(true);
                    
                    // Reload the page to refresh the auth status
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showMessage('Authentication failed: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Authentication failed due to a server error.', 'error');
            }
        });
    }

    /**
     * Revoke the Google Analytics authentication.
     */
    function revokeAuthentication() {
        // Show loading message
        showMessage('Revoking authentication...', 'info');
        
        // Send the revoke request to the server
        $.ajax({
            url: uipAnalyticsBridge.ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_ajax_uip_remove_analytics_account',
                security: uipAnalyticsBridge.nonce,
                saveAccountToUser: 'false'
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Successfully revoked Google Analytics authentication.', 'success');
                    
                    // Update the auth status
                    updateAuthStatus(false);
                    
                    // Reload the page to refresh the auth status
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showMessage('Revocation failed: ' + response.message, 'error');
                }
            },
            error: function() {
                showMessage('Revocation failed due to a server error.', 'error');
            }
        });
    }

    /**
     * Test the Google Analytics connection.
     */
    function testConnection() {
        // Show loading message
        showMessage('Testing connection...', 'info');
        
        // Send the test request to the server
        $.ajax({
            url: uipAnalyticsBridge.ajaxurl,
            type: 'POST',
            data: {
                action: 'uipress_analytics_bridge_test_connection',
                security: uipAnalyticsBridge.nonce,
                saveAccountToUser: 'false'
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Connection successful! Property Type: ' + response.data.property_type + ', Property ID: ' + response.data.property_id, 'success');
                } else {
                    showMessage('Connection failed: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Connection test failed due to a server error.', 'error');
            }
        });
    }

    /**
     * Clear the analytics data cache.
     */
    function clearCache() {
        // Show loading message
        showMessage('Clearing cache...', 'info');
        
        // Send the clear cache request to the server
        $.ajax({
            url: uipAnalyticsBridge.ajaxurl,
            type: 'POST',
            data: {
                action: 'uipress_analytics_bridge_clear_cache',
                security: uipAnalyticsBridge.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Cache cleared successfully.', 'success');
                } else {
                    showMessage('Failed to clear cache: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('Failed to clear cache due to a server error.', 'error');
            }
        });
    }

    /**
     * Update the authentication status in the UI.
     * 
     * @param {boolean} isAuthenticated Whether the user is authenticated.
     */
    function updateAuthStatus(isAuthenticated) {
        var statusHtml = '';
        
        if (isAuthenticated) {
            statusHtml = '<div class="uipress-analytics-bridge-status-connected">' +
                '<span class="dashicons dashicons-yes"></span> ' +
                'Connected to Google Analytics' +
                '</div>';
                
            // Enable the revoke and test buttons
            $('#revoke-button').prop('disabled', false);
            $('#test-connection-button').prop('disabled', false);
        } else {
            statusHtml = '<div class="uipress-analytics-bridge-status-disconnected">' +
                '<span class="dashicons dashicons-no"></span> ' +
                'Not connected to Google Analytics' +
                '</div>';
                
            // Disable the revoke and test buttons
            $('#revoke-button').prop('disabled', true);
            $('#test-connection-button').prop('disabled', true);
        }
        
        // Update the status indicator
        $('#auth-status-indicator').html(statusHtml);
    }

    /**
     * Show a message to the user.
     * 
     * @param {string} message The message to show.
     * @param {string} type The message type (info, success, error).
     */
    function showMessage(message, type) {
        // Remove any existing messages
        $('.uipress-analytics-bridge-message').remove();
        
        // Create the message element
        var $message = $('<div class="uipress-analytics-bridge-message uipress-analytics-bridge-message-' + type + '">' +
            '<p>' + message + '</p>' +
            '</div>');
        
        // Add the message to the page
        $('.wrap > h1').after($message);
        
        // Automatically remove the message after 5 seconds (if not error)
        if (type !== 'error') {
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }

    // Initialize when the document is ready
    $(document).ready(initAdmin);

})(jQuery);