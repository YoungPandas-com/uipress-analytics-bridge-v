# UIPress Analytics Bridge

UIPress Analytics Bridge is a WordPress plugin that enhances the Google Analytics integration for UIPress Pro by providing an improved authentication and data retrieval system.

## Features

- **Enhanced Authentication:** Replaces UIPress Pro's native Google Analytics authentication with a more reliable custom implementation
- **Complete Compatibility:** Maintains full compatibility with UIPress Pro's expected data structures and UI
- **Improved Error Handling:** Provides better diagnostics and error handling
- **Dual Analytics Support:** Supports both Universal Analytics and Google Analytics 4 (GA4) properties
- **WordPress Best Practices:** Implements proper WordPress loading patterns to avoid critical errors
- **Performance Optimization:** Includes robust caching system to improve dashboard performance
- **User-Friendly Interface:** Simple configuration and clear error messages

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- UIPress Lite 3.0.0 or higher
- UIPress Pro 3.0.0 or higher
- Google API credentials (Client ID and Client Secret)

## Installation

1. Upload the `uipress-analytics-bridge` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > UIPress Analytics to configure the plugin
4. Enter your Google API credentials (Client ID and Client Secret)
5. Authenticate with Google Analytics

## Configuration

### Google API Credentials

To use this plugin, you need to create Google API credentials:

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select an existing one)
3. Navigate to "APIs & Services" > "Library"
4. Enable the "Google Analytics API"
5. Go to "Credentials" and create an OAuth 2.0 Client ID
6. Set the authorized redirect URI to `[your-site-url]/wp-admin/admin-ajax.php?action=uipress_analytics_bridge_auth_callback`
7. Copy the Client ID and Client Secret to the plugin settings

### Authentication

After setting up your API credentials, you can authenticate with Google Analytics:

1. Click "Authenticate Globally" to set up analytics for all users
2. Click "Authenticate User" to set up analytics for your user account only
3. Follow the OAuth flow to grant access to your Google Analytics account
4. Select the property you want to use for analytics

### Advanced Settings

- **Cache Duration:** Set how long analytics data should be cached (default: 60 minutes)
- **Debug Mode:** Enable detailed logging for troubleshooting
- **Compatibility Mode:** Enable for better compatibility with older UIPress versions
- **Auto-Clear Cache:** Enable automatic daily cache clearing

## Frequently Asked Questions

### How does this plugin work with UIPress Pro?

UIPress Analytics Bridge intercepts the AJAX requests that UIPress Pro makes to Google Analytics and handles them with an improved implementation. It uses the same data structures and formatting as UIPress Pro, so it's completely compatible with UIPress's UI components.

### Does this plugin replace UIPress Pro?

No, this plugin requires UIPress Pro to be installed and activated. It enhances UIPress Pro's Google Analytics integration but doesn't replace any other functionality.

### Will this plugin affect my existing UIPress dashboards?

No, your existing dashboards will continue to work as before, but with improved analytics data retrieval. You don't need to rebuild or reconfigure your dashboards.

### Does this plugin work with GA4?

Yes, UIPress Analytics Bridge fully supports both Universal Analytics and Google Analytics 4 (GA4) properties.

### Can I use both global and user-specific authentication?

Yes, you can set up global authentication for all users and also allow individual users to authenticate with their own Google Analytics accounts.

## Troubleshooting

If you encounter any issues with the plugin, try these troubleshooting steps:

1. Enable Debug Mode in the Advanced Settings
2. Check the WordPress debug.log file for error messages
3. Try clearing the cache using the "Clear Cache Now" button
4. Verify your Google API credentials are correct
5. Ensure your Google API project has the Google Analytics API enabled
6. Check that your redirect URI is correctly configured in the Google Cloud Console

## Support

For support, please create an issue on the plugin's GitHub repository or contact us through our website.

## Credits

UIPress Analytics Bridge is developed and maintained by [Your Name/Company].

## License

This plugin is licensed under the GPL v2 or later.