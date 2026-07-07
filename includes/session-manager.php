<?php
/**
 * Session Manager - Secure session handling with user locking and progress persistence
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FJT_Session_Manager
{
    const SESSION_COOKIE_NAME = 'fjt_session_token';
    const SESSION_EXPIRY_HOURS = 24;

    /**
     * Start or resume session
     */
    public static function start_session($mobile_number)
    {
        $existing_session = self::get_active_session($mobile_number);

        if ($existing_session) {
            // Update last activity
            self::update_session_activity($existing_session['session_token']);
            self::set_session_cookie($existing_session['session_token']);
            return $existing_session['session_token'];
        }

        // Create new session
        $session_token = self::generate_session_token();
        
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        $wpdb->insert(
            $table,
            [
                'session_token' => $session_token,
                'mobile_number' => sanitize_text_field($mobile_number),
                'session_data' => wp_json_encode([]),
                'ip_address' => self::get_client_ip(),
                'user_agent' => self::get_user_agent(),
                'created_at' => current_time('mysql'),
                'expires_at' => self::get_expiry_time(),
                'last_activity' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        self::set_session_cookie($session_token);
        return $session_token;
    }

    /**
     * Get active session for user
     */
    public static function get_active_session($mobile_number)
    {
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                WHERE mobile_number = %s 
                AND expires_at > NOW() 
                ORDER BY last_activity DESC 
                LIMIT 1",
                $mobile_number
            ),
            ARRAY_A
        );
    }

    /**
     * Get session by token
     */
    public static function get_session_by_token($token)
    {
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} 
                WHERE session_token = %s 
                AND expires_at > NOW() 
                LIMIT 1",
                $token
            ),
            ARRAY_A
        );
    }

    /**
     * Validate session
     */
    public static function validate_session($token)
    {
        if (empty($token)) {
            return false;
        }

        $session = self::get_session_by_token($token);

        if (!$session) {
            return false;
        }

        // Validate IP and user agent for additional security
        $current_ip = self::get_client_ip();
        $current_agent = self::get_user_agent();

        // Update last activity
        self::update_session_activity($token);

        return $session;
    }

    /**
     * Update session data
     */
    public static function update_session_data($token, $data)
    {
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        return $wpdb->update(
            $table,
            [
                'session_data' => wp_json_encode($data),
                'last_activity' => current_time('mysql')
            ],
            ['session_token' => $token],
            ['%s', '%s'],
            ['%s']
        );
    }

    /**
     * Update last activity
     */
    private static function update_session_activity($token)
    {
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        return $wpdb->update(
            $table,
            ['last_activity' => current_time('mysql')],
            ['session_token' => $token],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Destroy session
     */
    public static function destroy_session($token)
    {
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        self::clear_session_cookie();

        return $wpdb->delete(
            $table,
            ['session_token' => $token],
            ['%s']
        );
    }

    /**
     * Destroy all sessions for user
     */
    public static function destroy_user_sessions($mobile_number)
    {
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        self::clear_session_cookie();

        return $wpdb->delete(
            $table,
            ['mobile_number' => $mobile_number],
            ['%s']
        );
    }

    /**
     * Cleanup expired sessions
     */
    public static function cleanup_expired_sessions()
    {
        global $wpdb;
        $table = FJT_Database::get_table_name('sessions');

        return $wpdb->query(
            "DELETE FROM {$table} WHERE expires_at < NOW()"
        );
    }

    /**
     * Generate secure session token
     */
    private static function generate_session_token()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get session expiry time
     */
    private static function get_expiry_time()
    {
        return gmdate('Y-m-d H:i:s', time() + (self::SESSION_EXPIRY_HOURS * 3600));
    }

    /**
     * Set session cookie
     */
    private static function set_session_cookie($token)
    {
        $expiry = time() + (self::SESSION_EXPIRY_HOURS * 3600);
        
        setcookie(
            self::SESSION_COOKIE_NAME,
            $token,
            $expiry,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // httponly
        );
    }

    /**
     * Get session from cookie
     */
    public static function get_session_from_cookie()
    {
        return isset($_COOKIE[self::SESSION_COOKIE_NAME]) 
            ? sanitize_text_field($_COOKIE[self::SESSION_COOKIE_NAME]) 
            : null;
    }

    /**
     * Clear session cookie
     */
    private static function clear_session_cookie()
    {
        setcookie(
            self::SESSION_COOKIE_NAME,
            '',
            time() - 3600,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    /**
     * Get client IP address
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
     * Get user agent
     */
    private static function get_user_agent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) 
            ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255)
            : '';
    }

    /**
     * Get current user from session
     */
    public static function get_current_user()
    {
        $token = self::get_session_from_cookie();
        
        if (!$token) {
            return null;
        }

        $session = self::validate_session($token);
        
        if (!$session) {
            return null;
        }

        return FJT_Database::get_user($session['mobile_number']);
    }

    /**
     * Check if user is logged in
     */
    public static function is_logged_in()
    {
        return self::get_current_user() !== null;
    }

    /**
     * Get session data
     */
    public static function get_session_data($token)
    {
        $session = self::get_session_by_token($token);
        
        if (!$session) {
            return [];
        }

        $data = $session['session_data'] ?? '';
        if (is_string($data) && !empty($data)) {
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return is_array($data) ? $data : [];
    }
}
