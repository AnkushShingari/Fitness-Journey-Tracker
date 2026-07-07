<?php
/**
 * Database Handler - Enhanced with improved schema, indexes, and dynamic field support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FJT_Database
{
    /**
     * Get table name
     */
    public static function get_table_name($type = 'user')
    {
        global $wpdb;

        switch ($type) {
            case 'entries':
                return $wpdb->prefix . FJT_ENTRIES_TABLE;
            case 'sessions':
                return $wpdb->prefix . FJT_SESSIONS_TABLE;
            default:
                return $wpdb->prefix . FJT_USER_TABLE;
        }
    }

    /**
     * Create all required tables
     */
    public static function create_tables()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $user_table = self::get_table_name('user');
        $entries_table = self::get_table_name('entries');
        $sessions_table = self::get_table_name('sessions');
        $charset_collate = $wpdb->get_charset_collate();

        // Users table - stores core user information
        $sql_users = "CREATE TABLE {$user_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            mobile_number VARCHAR(20) NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            full_name VARCHAR(150) NOT NULL DEFAULT '',
            goal VARCHAR(50) DEFAULT NULL,
            target_weight DECIMAL(10,2) DEFAULT NULL,
            user_profile LONGTEXT NULL,
            current_step INT(2) NOT NULL DEFAULT 1,
            form_completed TINYINT(1) NOT NULL DEFAULT 0,
            is_restricted TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            time_created DATETIME NOT NULL,
            time_updated DATETIME NOT NULL,
            last_entry_date DATE DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            total_entries BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY mobile_number (mobile_number),
            KEY email (email),
            KEY status (status),
            KEY is_restricted (is_restricted),
            KEY time_created (time_created),
            KEY last_login (last_login)
        ) {$charset_collate};";

        // Entries table - stores all form submissions and progress
        $sql_entries = "CREATE TABLE {$entries_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            mobile_number VARCHAR(20) NOT NULL,
            weight DECIMAL(10,2) NOT NULL,
            meta LONGTEXT NULL,
            entry_type VARCHAR(20) DEFAULT 'weight_update',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY mobile_number (mobile_number),
            KEY entry_type (entry_type),
            KEY created_at (created_at),
            KEY mobile_date (mobile_number, created_at)
        ) {$charset_collate};";

        // Sessions table - stores secure session data
        $sql_sessions = "CREATE TABLE {$sessions_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_token VARCHAR(64) NOT NULL,
            mobile_number VARCHAR(20) NOT NULL,
            session_data LONGTEXT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            last_activity DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY mobile_number (mobile_number),
            KEY expires_at (expires_at),
            KEY last_activity (last_activity)
        ) {$charset_collate};";

        // Form configs table - stores dynamic form configurations
        $sql_form_configs = "CREATE TABLE {$wpdb->prefix}" . FJT_FORM_CONFIG_TABLE . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_name VARCHAR(50) NOT NULL,
            config_data LONGTEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY form_name (form_name),
            KEY is_active (is_active)
        ) {$charset_collate};";

        dbDelta($sql_users);
        dbDelta($sql_entries);
        dbDelta($sql_sessions);
        dbDelta($sql_form_configs);
    }

    /**
     * Insert or update user - SAFE MERGE (preserves existing data)
     */
    public static function upsert_user($mobile_number, $data = [])
    {
        global $wpdb;
        $table = self::get_table_name('user');

        $existing = self::get_user($mobile_number);

        // SAFE MERGE: Only update provided fields
        if ($existing) {
            // For updates, only set fields that are provided
            $db_data = [
                'time_updated' => current_time('mysql')
            ];

            // Only update fields that are explicitly provided
            if (isset($data['full_name'])) {
                $db_data['full_name'] = sanitize_text_field($data['full_name']);
            }

            if (isset($data['email'])) {
                $db_data['email'] = !empty($data['email']) ? sanitize_email($data['email']) : null;
            }

            if (isset($data['user_profile'])) {
                // SAFE MERGE: Merge with existing profile, don't replace
                $existing_profile = !empty($existing['user_profile']) ? $existing['user_profile'] : [];
                if (is_string($existing_profile)) {
                    $existing_profile = json_decode($existing_profile, true) ?: [];
                }
                
                $new_profile = is_array($data['user_profile']) ? $data['user_profile'] : [];
                $merged_profile = array_merge($existing_profile, $new_profile);
                $db_data['user_profile'] = wp_json_encode($merged_profile);
            }

            if (isset($data['goal'])) {
                $db_data['goal'] = sanitize_text_field($data['goal']);
            }
            
            if (isset($data['target_weight'])) {
                $db_data['target_weight'] = floatval($data['target_weight']);
            }

            if (isset($data['current_step'])) {
                $db_data['current_step'] = intval($data['current_step']);
            }

            if (isset($data['form_completed'])) {
                $db_data['form_completed'] = intval($data['form_completed']);
            }

            if (isset($data['status'])) {
                $db_data['status'] = sanitize_text_field($data['status']);
            }
            
            if (isset($data['last_login'])) {
                $db_data['last_login'] = $data['last_login'];
            }

            $wpdb->update(
                $table,
                $db_data,
                ['mobile_number' => $mobile_number],
                array_fill(0, count($db_data), '%s'),
                ['%s']
            );
            return true;
        }

        // For new users, set defaults for required fields
        $db_data = [
            'mobile_number' => sanitize_text_field($mobile_number),
            'full_name' => sanitize_text_field($data['full_name'] ?? ''),
            'email' => !empty($data['email']) ? sanitize_email($data['email']) : null,
            'user_profile' => is_array($data['user_profile'] ?? []) ? wp_json_encode($data['user_profile']) : ($data['user_profile'] ?? '{}'),
            'goal' => sanitize_text_field($data['goal'] ?? ''),
            'target_weight' => !empty($data['target_weight']) ? floatval($data['target_weight']) : null,
            'current_step' => intval($data['current_step'] ?? 1),
            'form_completed' => intval($data['form_completed'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'time_created' => current_time('mysql'),
            'time_updated' => current_time('mysql'),
            'last_login' => current_time('mysql')
        ];
        
        return $wpdb->insert(
            $table,
            $db_data,
            array_fill(0, count($db_data), '%s')
        );
    }

    /**
     * Get user by mobile number
     */
    public static function get_user($mobile_number)
    {
        global $wpdb;
        $table = self::get_table_name('user');

        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE mobile_number = %s LIMIT 1",
                $mobile_number
            ),
            ARRAY_A
        );

        if ($user && !empty($user['user_profile'])) {
            $user['user_profile'] = json_decode($user['user_profile'], true);
        }

        return $user;
    }

    /**
     * Get all users with pagination and filtering
     */
    public static function get_all_users($args = [])
    {
        global $wpdb;
        $table = self::get_table_name('user');

        $defaults = [
            'limit' => 1000,
            'offset' => 0,
            'orderby' => 'time_created',
            'order' => 'DESC',
            'status' => null,
            'is_restricted' => null,
            'search' => null
        ];

        $args = array_merge($defaults, $args);

        $where = ['1=1'];
        $query_params = [];

        if (!is_null($args['status'])) {
            $where[] = 'status = %s';
            $query_params[] = $args['status'];
        }

        if (!is_null($args['is_restricted'])) {
            $where[] = 'is_restricted = %d';
            $query_params[] = $args['is_restricted'];
        }

        if (!empty($args['search'])) {
            $where[] = '(full_name LIKE %s OR mobile_number LIKE %s OR email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");

        if (empty($query_params)) {
            $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT {$args['limit']} OFFSET {$args['offset']}";
            return $wpdb->get_results($query, ARRAY_A);
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
            array_merge($query_params, [$args['limit'], $args['offset']])
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Count total users with filters (for pagination)
     */
    public static function count_users($args = [])
    {
        global $wpdb;
        $table = self::get_table_name('user');

        $defaults = [
            'status' => null,
            'is_restricted' => null,
            'search' => null
        ];

        $args = array_merge($defaults, $args);

        $where = ['1=1'];
        $query_params = [];

        if (!is_null($args['status'])) {
            $where[] = 'status = %s';
            $query_params[] = $args['status'];
        }

        if (!is_null($args['is_restricted'])) {
            $where[] = 'is_restricted = %d';
            $query_params[] = $args['is_restricted'];
        }

        if (!empty($args['search'])) {
            $where[] = '(full_name LIKE %s OR mobile_number LIKE %s OR email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        if (empty($query_params)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where_clause}");
        }

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
            $query_params
        );

        return (int) $wpdb->get_var($query);
    }

    /**
     * Get latest weight for a user
     */
    public static function get_latest_weight($mobile)
    {
        global $wpdb;
        $table = self::get_table_name('entries');

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT weight FROM {$table} WHERE mobile_number = %s ORDER BY created_at DESC LIMIT 1",
            $mobile
        ), ARRAY_A);

        return $result ? $result['weight'] : null;
    }

    /**
     * Get user statistics
     */
    public static function get_user_stats()
    {
        global $wpdb;
        $table = self::get_table_name('user');

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN form_completed = 1 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN is_restricted = 1 THEN 1 ELSE 0 END) as restricted,
                SUM(CASE WHEN status = 'active' AND is_restricted = 0 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN DATE(last_login) = CURDATE() THEN 1 ELSE 0 END) as today_active
            FROM {$table}",
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Insert entry
     */
    public static function insert_entry($mobile, $weight, $meta = [], $entry_type = 'weight_update')
    {
        global $wpdb;
        $table = self::get_table_name('entries');

        $result = $wpdb->insert(
            $table,
            [
                'mobile_number' => sanitize_text_field($mobile),
                'weight' => floatval($weight),
                'meta' => wp_json_encode($meta),
                'entry_type' => sanitize_text_field($entry_type),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%f', '%s', '%s', '%s']
        );

        if ($result) {
            self::update_entry_stats($mobile);
        }

        return $result;
    }

    /**
     * Get user entries
     */
    public static function get_user_entries($mobile, $limit = null)
    {
        global $wpdb;
        $table = self::get_table_name('entries');

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE mobile_number = %s ORDER BY created_at ASC" . ($limit ? " LIMIT {$limit}" : ""),
            $mobile
        );

        $entries = $wpdb->get_results($query, ARRAY_A);

        foreach ($entries as &$entry) {
            $entry['meta'] = !empty($entry['meta']) ? json_decode($entry['meta'], true) : [];
        }

        return $entries;
    }

    /**
     * Update entry statistics
     */
    public static function update_entry_stats($mobile_number)
    {
        global $wpdb;
        $user_table = self::get_table_name('user');
        $entries_table = self::get_table_name('entries');

        $count = intval(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$entries_table} WHERE mobile_number = %s",
                    $mobile_number
                )
            )
        );

        $last_date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM {$entries_table}
                WHERE mobile_number = %s
                ORDER BY created_at DESC
                LIMIT 1",
                $mobile_number
            )
        );

        return $wpdb->update(
            $user_table,
            [
                'total_entries' => $count,
                'last_entry_date' => $last_date ? gmdate('Y-m-d', strtotime($last_date)) : null,
                'time_updated' => current_time('mysql')
            ],
            ['mobile_number' => $mobile_number],
            ['%d', '%s', '%s'],
            ['%s']
        );
    }

    /**
     * Update user restriction status
     */
    public static function set_restriction($mobile_number, $status = 1)
    {
        global $wpdb;
        $table = self::get_table_name('user');

        return $wpdb->update(
            $table,
            [
                'is_restricted' => intval($status),
                'time_updated' => current_time('mysql')
            ],
            ['mobile_number' => $mobile_number],
            ['%d', '%s'],
            ['%s']
        );
    }

    /**
     * Update user progress step
     */
    public static function update_user_step($mobile_number, $step)
    {
        global $wpdb;
        $table = self::get_table_name('user');

        return $wpdb->update(
            $table,
            [
                'current_step' => intval($step),
                'time_updated' => current_time('mysql')
            ],
            ['mobile_number' => $mobile_number],
            ['%d', '%s'],
            ['%s']
        );
    }

    /**
     * Delete user and all related data
     */
    public static function delete_user($mobile_number)
    {
        global $wpdb;

        $user_table = self::get_table_name('user');
        $entries_table = self::get_table_name('entries');
        $sessions_table = self::get_table_name('sessions');

        // Delete in transaction
        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->delete($sessions_table, ['mobile_number' => $mobile_number], ['%s']);
            $wpdb->delete($entries_table, ['mobile_number' => $mobile_number], ['%s']);
            $wpdb->delete($user_table, ['mobile_number' => $mobile_number], ['%s']);

            $wpdb->query('COMMIT');
            return true;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }

    /**
     * Update entry
     */
    public static function update_entry($entry_id, $data)
    {
        global $wpdb;
        $table = self::get_table_name('entries');

        $update_data = [];

        if (isset($data['weight'])) {
            $update_data['weight'] = floatval($data['weight']);
        }

        if (isset($data['meta'])) {
            $update_data['meta'] = is_array($data['meta']) ? wp_json_encode($data['meta']) : $data['meta'];
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $table,
            $update_data,
            ['id' => intval($entry_id)],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
    }

    /**
     * Delete entry
     */
    public static function delete_entry($entry_id, $mobile_number = null)
    {
        global $wpdb;
        $table = self::get_table_name('entries');

        $where = ['id' => intval($entry_id)];
        $where_format = ['%d'];

        if ($mobile_number) {
            $where['mobile_number'] = $mobile_number;
            $where_format[] = '%s';
        }

        $result = $wpdb->delete($table, $where, $where_format);

        if ($result && $mobile_number) {
            self::update_entry_stats($mobile_number);
        }

        return $result;
    }
}