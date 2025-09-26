<?php
/**
 * Security utilities for SlimWP Simple Points Plugin
 * Centralizes security validations and safe file operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class SlimWP_Security_Utils {
    
    /**
     * Validate file path is secure and within allowed boundaries
     * 
     * @param string $path The file path to validate
     * @param string $base_dir The base directory that paths must be within
     * @param array $allowed_extensions Array of allowed file extensions
     * @return bool True if path is safe, false otherwise
     */
    public static function validate_file_path($path, $base_dir = null, $allowed_extensions = array()) {
        if (empty($path)) {
            error_log('SlimWP Security: Empty file path provided');
            return false;
        }
        
        // Set default base directory to plugin directory
        if ($base_dir === null) {
            $base_dir = SLIMWP_PLUGIN_DIR;
        }
        
        // Resolve real paths to prevent directory traversal
        $real_path = realpath(dirname($path));
        $real_base = realpath($base_dir);
        
        if (!$real_base) {
            error_log('SlimWP Security: Invalid base directory: ' . $base_dir);
            return false;
        }
        
        // For new files, check if parent directory is within bounds
        if (!$real_path) {
            $parent_dir = dirname($path);
            $real_path = realpath($parent_dir);
            
            if (!$real_path) {
                // Check if we can create the directory within bounds
                $temp_path = $parent_dir;
                while ($temp_path !== '.' && $temp_path !== '/' && $temp_path !== dirname($temp_path)) {
                    $real_path = realpath($temp_path);
                    if ($real_path) {
                        break;
                    }
                    $temp_path = dirname($temp_path);
                }
            }
        }
        
        if (!$real_path) {
            error_log('SlimWP Security: Cannot resolve file path: ' . $path);
            return false;
        }
        
        // Check that the file is within the allowed directory
        if (strpos($real_path, $real_base) !== 0) {
            error_log('SlimWP Security: Path traversal attempt detected: ' . $path);
            return false;
        }
        
        // Check file extension if restrictions are provided
        if (!empty($allowed_extensions)) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed_extensions)) {
                error_log('SlimWP Security: Invalid file extension: ' . $extension);
                return false;
            }
        }
        
        // Check filename doesn't contain dangerous characters
        $filename = basename($path);
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
            error_log('SlimWP Security: Invalid filename characters: ' . $filename);
            return false;
        }
        
        // Additional checks for common attack patterns
        $dangerous_patterns = array(
            '../',
            '..\\',
            './',
            '.\\',
            'php://',
            'data://',
            'file://',
            'ftp://',
            'http://',
            'https://'
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (stripos($path, $pattern) !== false) {
                error_log('SlimWP Security: Dangerous path pattern detected: ' . $pattern);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Safely write content to a file with security validations
     * 
     * @param string $path The file path to write to
     * @param string $content The content to write
     * @param array $options Options array with validation parameters
     * @return bool|int False on failure, number of bytes written on success
     */
    public static function safe_file_write($path, $content, $options = array()) {
        $defaults = array(
            'base_dir' => SLIMWP_PLUGIN_DIR,
            'allowed_extensions' => array(),
            'max_size' => 1048576, // 1MB default
            'validate_content' => true,
            'file_permissions' => 0644
        );
        
        $options = array_merge($defaults, $options);
        
        // Validate file path
        if (!self::validate_file_path($path, $options['base_dir'], $options['allowed_extensions'])) {
            return false;
        }
        
        // Validate content if requested
        if ($options['validate_content'] && !self::validate_file_content($content, $options['max_size'])) {
            return false;
        }
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                error_log('SlimWP Security: Failed to create directory: ' . $dir);
                return false;
            }
        }
        
        // Check directory permissions
        if (!is_writable($dir)) {
            error_log('SlimWP Security: Directory not writable: ' . $dir);
            return false;
        }
        
        // Write file with exclusive lock
        $result = file_put_contents($path, $content, LOCK_EX);
        
        if ($result === false) {
            error_log('SlimWP Security: Failed to write file: ' . $path);
            return false;
        }
        
        // Set file permissions
        if (function_exists('chmod')) {
            chmod($path, $options['file_permissions']);
        }
        
        return $result;
    }
    
    /**
     * Validate file content for security
     * 
     * @param string $content The content to validate
     * @param int $max_size Maximum allowed file size
     * @return bool True if content is safe, false otherwise
     */
    public static function validate_file_content($content, $max_size = 1048576) {
        if (empty($content)) {
            error_log('SlimWP Security: Empty content provided');
            return false;
        }
        
        // Check content size
        $content_length = strlen($content);
        if ($content_length > $max_size) {
            error_log('SlimWP Security: Content too large: ' . $content_length . ' bytes');
            return false;
        }
        
        // Check for potentially dangerous content
        $dangerous_patterns = array(
            '<?php',
            '<%',
            '<script',
            'eval(',
            'system(',
            'exec(',
            'shell_exec(',
            'passthru(',
            'file_get_contents(',
            'file_put_contents(',
            'fopen(',
            'fwrite(',
            'include(',
            'require(',
            'include_once(',
            'require_once('
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                error_log('SlimWP Security: Dangerous content pattern detected: ' . $pattern);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Safely read file with security validations
     * 
     * @param string $path The file path to read from
     * @param array $options Options array with validation parameters
     * @return string|false File contents on success, false on failure
     */
    public static function safe_file_read($path, $options = array()) {
        $defaults = array(
            'base_dir' => SLIMWP_PLUGIN_DIR,
            'allowed_extensions' => array(),
            'max_size' => 1048576, // 1MB default
            'validate_content' => true
        );
        
        $options = array_merge($defaults, $options);
        
        // Validate file path
        if (!self::validate_file_path($path, $options['base_dir'], $options['allowed_extensions'])) {
            return false;
        }
        
        // Check if file exists and is readable
        if (!file_exists($path) || !is_readable($path)) {
            error_log('SlimWP Security: File not readable: ' . $path);
            return false;
        }
        
        // Check file size
        $file_size = filesize($path);
        if ($file_size > $options['max_size']) {
            error_log('SlimWP Security: File too large: ' . $file_size . ' bytes');
            return false;
        }
        
        // Read file content
        $content = file_get_contents($path);
        
        if ($content === false) {
            error_log('SlimWP Security: Failed to read file: ' . $path);
            return false;
        }
        
        // Validate content if requested
        if ($options['validate_content'] && !self::validate_file_content($content, $options['max_size'])) {
            return false;
        }
        
        return $content;
    }
    
    /**
     * Sanitize filename for safe storage
     * 
     * @param string $filename The filename to sanitize
     * @return string Sanitized filename
     */
    public static function sanitize_filename($filename) {
        // Remove directory traversal attempts
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
        
        // Remove multiple consecutive underscores/dots
        $filename = preg_replace('/[_.]{2,}/', '_', $filename);
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        
        // Limit filename length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * Check if a path is within allowed system locations for reading
     * 
     * @param string $path The path to check
     * @param array $safe_paths Array of allowed system paths
     * @return bool True if path is safe, false otherwise
     */
    public static function is_safe_system_path($path, $safe_paths = array()) {
        if (empty($safe_paths)) {
            // Default safe system paths for CA certificates
            $safe_paths = array(
                '/etc/ssl/certs/ca-certificates.crt',
                '/etc/pki/tls/certs/ca-bundle.crt',
                '/etc/ssl/ca-bundle.pem',
                '/etc/ssl/cert.pem',
                '/usr/local/share/certs/ca-root-nss.crt',
                '/System/Library/OpenSSL/certs/cert.pem' // macOS
            );
        }
        
        $real_path = realpath($path);
        if (!$real_path) {
            return false;
        }
        
        return in_array($real_path, $safe_paths);
    }
}