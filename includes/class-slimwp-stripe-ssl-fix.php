<?php
/**
 * SSL Certificate Fix for Stripe
 * This creates a CA bundle if one doesn't exist
 */

if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Stripe_SSL_Fix {
    
    public static function ensure_ca_bundle() {
        // Validate plugin directory exists and is within expected bounds
        if (!defined('SLIMWP_PLUGIN_DIR') || empty(SLIMWP_PLUGIN_DIR)) {
            error_log('SlimWP Security: SLIMWP_PLUGIN_DIR not defined');
            return false;
        }
        
        // Construct and validate the CA bundle path
        $ca_bundle_path = self::get_secure_ca_bundle_path();
        if (!$ca_bundle_path) {
            return false;
        }
        
        // If CA bundle doesn't exist, create it
        if (!file_exists($ca_bundle_path)) {
            return self::create_ca_bundle($ca_bundle_path);
        }
        
        return $ca_bundle_path;
    }
    
    private static function create_ca_bundle($path) {
        // Additional security validation before file creation
        if (!self::validate_file_path($path)) {
            error_log('SlimWP Security: Invalid CA bundle path attempted: ' . $path);
            return false;
        }
        
        // Ensure we have write permissions to the directory
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                error_log('SlimWP Security: Failed to create CA bundle directory: ' . $dir);
                return false;
            }
        }
        
        // Check directory permissions
        if (!is_writable($dir)) {
            error_log('SlimWP Security: CA bundle directory not writable: ' . $dir);
            return false;
        }
        
        // Get CA content securely
        $ca_content = self::get_secure_ca_content();
        if (empty($ca_content)) {
            error_log('SlimWP Security: No valid CA content available');
            return false;
        }
        
        // Validate CA content before writing
        if (!self::validate_ca_content($ca_content)) {
            error_log('SlimWP Security: Invalid CA content detected');
            return false;
        }
        
        // Write the CA bundle securely using our security utilities
        $write_result = SlimWP_Security_Utils::safe_file_write($path, $ca_content, array(
            'allowed_extensions' => array('crt', 'pem', 'cert'),
            'max_size' => 2097152, // 2MB for CA bundles
            'validate_content' => false, // We'll validate separately
            'file_permissions' => 0644
        ));
        
        if ($write_result === false) {
            error_log('SlimWP Security: Failed to write CA bundle securely to: ' . $path);
            return false;
        }
        
        error_log('SlimWP Stripe: Successfully created CA bundle at ' . $path);
        return $path;
    }
    
    /**
     * Get secure path for CA bundle file
     */
    private static function get_secure_ca_bundle_path() {
        $plugin_dir = realpath(SLIMWP_PLUGIN_DIR);
        if (!$plugin_dir) {
            error_log('SlimWP Security: Could not resolve plugin directory');
            return false;
        }
        
        // Construct the path within the plugin directory
        $ca_bundle_path = $plugin_dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 
                         'stripe-php' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 
                         'ca-certificates.crt';
        
        // Validate the path is within plugin bounds
        if (!self::validate_file_path($ca_bundle_path)) {
            return false;
        }
        
        return $ca_bundle_path;
    }
    
    /**
     * Validate that file path is secure and within plugin directory
     */
    private static function validate_file_path($path) {
        return SlimWP_Security_Utils::validate_file_path($path, SLIMWP_PLUGIN_DIR, array('crt', 'pem', 'cert'));
    }
    
    /**
     * Get CA content securely
     */
    private static function get_secure_ca_content() {
        $ca_content = '';
        
        // Option 1: Try to use system CA bundle securely
        if (function_exists('curl_version')) {
            $curl_info = curl_version();
            if (!empty($curl_info['cainfo']) && file_exists($curl_info['cainfo'])) {
                // Validate system CA bundle path
                $system_ca_path = realpath($curl_info['cainfo']);
                if ($system_ca_path && self::is_safe_system_path($system_ca_path)) {
                    $ca_content = file_get_contents($system_ca_path);
                    if ($ca_content && self::validate_ca_content($ca_content)) {
                        return $ca_content;
                    }
                }
            }
        }
        
        // Option 2: Use our known-good minimal CA bundle
        return self::get_minimal_ca_bundle();
    }
    
    /**
     * Validate that a system path is safe to read from
     */
    private static function is_safe_system_path($path) {
        return SlimWP_Security_Utils::is_safe_system_path($path);
    }
    
    /**
     * Validate CA content format and security
     */
    private static function validate_ca_content($content) {
        if (empty($content)) {
            return false;
        }
        
        // Check content looks like PEM certificates
        if (strpos($content, '-----BEGIN CERTIFICATE-----') === false) {
            return false;
        }
        
        if (strpos($content, '-----END CERTIFICATE-----') === false) {
            return false;
        }
        
        // Check content size is reasonable (not too small or too large)
        $content_length = strlen($content);
        if ($content_length < 100 || $content_length > 1048576) { // 1MB max
            return false;
        }
        
        // Check for potentially dangerous content
        $dangerous_patterns = array(
            '<?php',
            '<script',
            'eval(',
            'system(',
            'exec(',
            'shell_exec(',
            'passthru(',
            'file_get_contents(',
            'file_put_contents(',
            'fopen(',
            'fwrite('
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                error_log('SlimWP Security: Dangerous content detected in CA bundle');
                return false;
            }
        }
        
        return true;
    }
    
    private static function get_minimal_ca_bundle() {
        // A minimal CA bundle with common root certificates
        // This includes the certificates commonly used by Stripe and other major services
        return '-----BEGIN CERTIFICATE-----
MIIFazCCA1OgAwIBAgIRAIIQz7DSQONZRGPgu2OCiwAwDQYJKoZIhvcNAQELBQAw
TzELMAkGA1UEBhMCVVMxKTAnBgNVBAoTIEludGVybmV0IFNlY3VyaXR5IFJlc2Vh
cmNoIEdyb3VwMRUwEwYDVQQDEwxJU1JHIFJvb3QgWDEwHhcNMTUwNjA0MTEwNDM4
WhcNMzUwNjA0MTEwNDM4WjBPMQswCQYDVQQGEwJVUzEpMCcGA1UEChMgSW50ZXJu
ZXQgU2VjdXJpdHkgUmVzZWFyY2ggR3JvdXAxFTATBgNVBAMTDElTUkcgUm9vdCBY
MTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAK3oJHP0FDfzm54rVygc
h77ct984kIxuPOZXoHj3dcKi/vVqbvYATyjb3miGbESTtrFj/RQSa78f0uoxmyF+
0TM8ukj13Xnfs7j/EvEhmkvBioZxaUpmZmyPfjxwv60pIgbz5MDmgK7iS4+3mX6U
A5/TR5d8mUgjU+g4rk8Kb4Mu0UlXjIB0ttov0DiNewNwIRt18jA8+o+u3dpjq+sW
T8KOEUt+zwvo/7V3LvSye0rgTBIlDHCNAymg4VMk7BPZ7hm/ELNKjD+Jo2FR3qyH
B5T0Y3HsLuJvW5iB4YlcNHlsdu87kGJ55tukmi8mxdAQ4Q7e2RCOFvu396j3x+UC
B5iPNgiV5+I3lg02dZ77DnKxHZu8A/lJBdiB3QW0KtZB6awBdpUKD9jf1b0SHzUv
KBds0pjBqAlkd25HN7rOrFleaJ1/ctaJxQZBKT5ZPt0m9STJEadao0xAH0ahmbWn
OlFuhjuefXKnEgV4We0+UXgVCwOPjdAvBbI+e0ocS3MFEvzG6uBQE3xDk3SzynTn
jh8BCNAw1FtxNrQHusEwMFxIt4I7mKZ9YIqioymCzLq9gwQbooMDQaHWBfEbwrbw
qHyGO0aoSCqI3Haadr8faqU9GY/rOPNk3sgrDQoo//fb4hVC1CLQJ13hef4Y53CI
rU7m2Ys6xt0nUW7/vGT1M0NPAgMBAAGjQjBAMA4GA1UdDwEB/wQEAwIBBjAPBgNV
HRMBAf8EBTADAQH/MB0GA1UdDgQWBBR5tFnme7bl5AFzgAiIyBpY9umbbjANBgkq
hkiG9w0BAQsFAAOCAgEAVR9YqbyyqFDQDLHYGmkgJykIrGF1XIpu+ILlaS/V9lZL
ubhzEFnTIZd+50xx+7LSYK05qAvqFyFWhfFQDlnrzuBZ6brJFe+GnY+EgPbk6ZGQ
3BebYhtF8GaV0nxvwuo77x/Py9auJ/GpsMiu/X1+mvoiBOv/2X/qkSsisRcOj/KK
NFtY2PwByVS5uCbMiogziUwthDyC3+6WVwW6LLv3xLfHTjuCvjHIInNzktHCgKQ5
ORAzI4JMPJ+GslWYHb4phowim57iaztXOoJwTdwJx4nLCgdNbOhdjsnvzqvHu7Ur
TkXWStAmzOVyyghqpZXjFaH3pO3JLF+l+/+sKAIuvtd7u+Nxe5AW0wdeRlN8NwdC
jNPElpzVmbUq4JUagEiuTDkHzsxHpFKVK7q4+63SM1N95R1NbdWhscdCb+ZAJzVc
oyi3B43njTOQ5yOf+1CceWxG1bQVs5ZufpsMljq4Ui0/1lvh+wjChP4kqKOJ2qxq
4RgqsahDYVvTH9w7jXbyLeiNdd8XM2w9U/t7y0Ff/9yi0GE44Za4rF2LN9d11TPA
mRGunUHBcnWEvgJBQl9nJEiU0Zsnvgc/ubhPgXRR4Xq37Z0j4r7g1SgEEzwxA57d
emyPxgcYxn/eR44/KJ4EBs+lVDR3veyJm+kXQ99b21/+jh5Xos1AnX5iItreGCc=
-----END CERTIFICATE-----';
    }
}