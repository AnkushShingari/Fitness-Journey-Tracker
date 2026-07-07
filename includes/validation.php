<?php
/**
 * Validation Handler - Comprehensive frontend and backend validation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FJT_Validation
{
    /**
     * Validate mobile number - EXTREMELY STRICT
     */
    public static function validate_mobile($mobile)
    {
        // Remove all whitespace and special characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);

        // Check if empty
        if (empty($mobile)) {
            return ['valid' => false, 'message' => 'Mobile number is required'];
        }

        // Check length (Indian mobile: 10 digits)
        if (strlen($mobile) < 10 || strlen($mobile) > 15) {
            return ['valid' => false, 'message' => 'Mobile number must be 10-15 digits'];
        }

        // Indian mobile number validation (starts with 6-9)
        if (strlen($mobile) === 10 && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            return ['valid' => false, 'message' => 'Invalid Indian mobile number format'];
        }

        // Prevent sequential numbers (1234567890, 0000000000, etc.)
        if (preg_match('/^(0+|1+|2+|3+|4+|5+|6+|7+|8+|9+)$/', $mobile)) {
            return ['valid' => false, 'message' => 'Invalid mobile number pattern'];
        }

        // Prevent obviously fake patterns
        if (preg_match('/^(1234567890|0123456789|9876543210)$/', $mobile)) {
            return ['valid' => false, 'message' => 'Invalid mobile number'];
        }

        return ['valid' => true, 'value' => $mobile];
    }

    /**
     * Validate email
     */
    public static function validate_email($email)
    {
        if (empty($email)) {
            return ['valid' => true]; // Email is optional
        }

        $email = sanitize_email($email);

        if (!is_email($email)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }

        // Additional checks for disposable email domains
        $disposable_domains = ['tempmail.com', 'throwaway.email', '10minutemail.com', 'guerrillamail.com'];
        $domain = substr(strrchr($email, '@'), 1);

        if (in_array($domain, $disposable_domains)) {
            return ['valid' => false, 'message' => 'Temporary email addresses are not allowed'];
        }

        return ['valid' => true, 'value' => $email];
    }

    /**
     * Validate number
     */
    public static function validate_number($value, $config = [])
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return ['valid' => true]; // Allow empty if not required
        }

        if (!is_numeric($value)) {
            return ['valid' => false, 'message' => 'Must be a valid number'];
        }

        $value = floatval($value);

        // Check min
        if (isset($config['min']) && $value < $config['min']) {
            return ['valid' => false, 'message' => 'Minimum value is ' . $config['min']];
        }

        // Check max
        if (isset($config['max']) && $value > $config['max']) {
            return ['valid' => false, 'message' => 'Maximum value is ' . $config['max']];
        }

        return ['valid' => true, 'value' => $value];
    }

    /**
     * Validate text
     */
    public static function validate_text($value, $config = [])
    {
        if (empty($value)) {
            return ['valid' => true];
        }

        $value = sanitize_text_field($value);

        // Check max length
        if (isset($config['max_length']) && strlen($value) > $config['max_length']) {
            return ['valid' => false, 'message' => 'Maximum length is ' . $config['max_length'] . ' characters'];
        }

        // Check for SQL injection patterns
        $dangerous_patterns = [
            '/(\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bCREATE\b)/i',
            '/(<script|javascript:|onerror=|onload=)/i'
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return ['valid' => false, 'message' => 'Invalid input detected'];
            }
        }

        return ['valid' => true, 'value' => $value];
    }

    /**
     * Validate dropdown/radio option
     */
    public static function validate_option($value, $allowed_options)
    {
        if (empty($value)) {
            return ['valid' => true];
        }

        if (!in_array($value, $allowed_options, true)) {
            return ['valid' => false, 'message' => 'Invalid option selected'];
        }

        return ['valid' => true, 'value' => sanitize_text_field($value)];
    }

    /**
     * Validate checkbox values
     */
    public static function validate_checkbox($values, $allowed_options)
    {
        if (empty($values) || !is_array($values)) {
            return ['valid' => true, 'value' => []];
        }

        $validated = [];

        foreach ($values as $value) {
            if (in_array($value, $allowed_options, true)) {
                $validated[] = sanitize_text_field($value);
            }
        }

        return ['valid' => true, 'value' => $validated];
    }

    /**
     * Validate entire form submission
     */
    public static function validate_form($data, $required_fields = [])
    {
        $errors = [];
        $validated_data = [];

        // Check required fields
        foreach ($required_fields as $field) {
            if (empty($data[$field]) && $data[$field] !== 0 && $data[$field] !== '0') {
                $config = FJT_Form_Config::get_field_config($field);
                $label = $config ? $config['label'] : ucfirst(str_replace('_', ' ', $field));
                $errors[$field] = $label . ' is required';
            }
        }

        // Validate each field
        foreach ($data as $field_name => $value) {
            $config = FJT_Form_Config::get_field_config($field_name);

            if (!$config) {
                // Dynamic/unknown field — sanitize and keep (do NOT drop)
                if (is_array($value)) {
                    $validated_data[$field_name] = array_map('sanitize_text_field', $value);
                } else {
                    $validated_data[$field_name] = sanitize_text_field($value);
                }
                continue;
            }

            $result = FJT_Form_Config::validate_field($field_name, $value, $config);

            if (!$result['valid']) {
                $errors[$field_name] = $result['message'];
            } else if (isset($result['value'])) {
                $validated_data[$field_name] = $result['value'];
            } else {
                $validated_data[$field_name] = $value;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated_data
        ];
    }

    /**
     * Sanitize and validate AJAX request
     */
    public static function validate_ajax_request($action, $nonce_action = 'fjt_nonce')
    {
        // Verify nonce
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error([
                'message' => 'Security check failed',
                'code' => 'invalid_nonce'
            ], 403);
        }

        // Check for rate limiting (prevent abuse)
        $ip = self::get_client_ip();
        $rate_limit_key = 'fjt_rate_limit_' . md5($ip . $action);
        $attempts = get_transient($rate_limit_key);

        if ($attempts && $attempts > 100) {
            wp_send_json_error([
                'message' => 'Too many requests. Please try again later.',
                'code' => 'rate_limit_exceeded'
            ], 429);
        }

        // Increment attempt counter
        set_transient($rate_limit_key, ($attempts ? $attempts + 1 : 1), 3600);

        return true;
    }

    /**
     * Get client IP
     */
    private static function get_client_ip()
    {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return sanitize_text_field($ip);
    }

    /**
     * Prevent XSS in output
     */
    public static function safe_output($value)
    {
        return esc_html($value);
    }

    /**
     * Sanitize array recursively
     */
    public static function sanitize_array($array)
    {
        $sanitized = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_array($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
}
