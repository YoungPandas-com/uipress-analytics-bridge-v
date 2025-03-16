/**
 * Admin JavaScript for UIPress Analytics Bridge
 * Simplified version with direct functionality
 */
jQuery(document).ready(function($) {
    // Direct event binding for connect button
    $('#uipress-analytics-connect').on('click', function(e) {
        e.preventDefault();
        console.log('Connect button clicked');
        
        // Show loading message
        showMessage('info', 'Connecting to Google Analytics...');
        
        // Get the API credentials from the settings page
        var clientID = $('#client_id').val();
        
        // If we're on the connection tab and don't have access to the client ID field
        if (!clientID) {
            // Open the authentication window directly
            openAuthWindow();
            return;
        }
        
        // Make sure we have API credentials
        if (!clientID) {
            showMessage('error', 'Please enter your Google API Client ID first.');
            return;
        }
        
        // Open the authentication window
        openAuthWindow();
    });
    
    /**
     * Open the Google authentication window
     */
    function openAuthWindow() {
        // Get the redirect URI for our callback
        var redirectUri = encodeURIComponent(ajaxurl + '?action=uipress_analytics_bridge_oauth_callback');
        
        // Create a state parameter for security
        var state = Math.random().toString(36).substring(2, 15);
        localStorage.setItem('uip_analytics_bridge_state', state);
        
        // Build the auth URL directly
        var authUrl = 'https://accounts.google.com/o/oauth2/auth' + 
            '?client_id=' + encodeURIComponent(clientID) + 
            '&redirect_uri=' + redirectUri +
            '&scope=' + encodeURIComponent('https://www.googleapis.com/auth/analytics.readonly') +
            '&response_type=code' +
            '&access_type=offline' +
            '&state=' + state +
            '&prompt=consent';
        
        // Open the auth window
        var width = 600;
        var height = 700;
        var left = (screen.width / 2) - (width / 2);
        var top = (screen.height / 2) - (height / 2);
        
        window.open(authUrl, 'uipress_analytics_auth', 
            'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left);
            
        // Show a message to the user
        showMessage('info', 'Please complete the authentication in the opened window.');
    }
    
    /**
     * Show a message to the user
     */
    function showMessage(type, message) {
        // Remove any existing notices
        $('.notice').remove();
        
        // Create a new notice
        var noticeClass = 'notice ';
        if (type === 'error') {
            noticeClass += 'notice-error';
        } else if (type === 'success') {
            noticeClass += 'notice-success';
        } else {
            noticeClass += 'notice-info';
        }
        
        var notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Add the notice to the page
        $('.wrap h1').after(notice);
    }
    
    // Test button to make sure jQuery is working
    $('<button type="button" class="button button-secondary" id="uipress-test-button">Test jQuery</button>')
        .insertAfter('#uipress-analytics-connect')
        .on('click', function(e) {
            e.preventDefault();
            alert('jQuery is working!');
        });
});