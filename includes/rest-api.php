<?php
/**
 * REST API Handler - Fully Dynamic Field Processing
 */

if (!defined('ABSPATH')) exit;

class FJT_REST_API
{
    public static function register_endpoints()
    {
        // FRONTEND ENDPOINTS
        add_action('wp_ajax_fjt_check_user', [__CLASS__, 'check_user']);
        add_action('wp_ajax_nopriv_fjt_check_user', [__CLASS__, 'check_user']);

        add_action('wp_ajax_fjt_save_user', [__CLASS__, 'save_user']);
        add_action('wp_ajax_nopriv_fjt_save_user', [__CLASS__, 'save_user']);

        add_action('wp_ajax_fjt_add_entry', [__CLASS__, 'add_entry']);
        add_action('wp_ajax_nopriv_fjt_add_entry', [__CLASS__, 'add_entry']);

        add_action('wp_ajax_fjt_update_user', [__CLASS__, 'update_user']);
        add_action('wp_ajax_nopriv_fjt_update_user', [__CLASS__, 'update_user']);

        add_action('wp_ajax_fjt_logout', [__CLASS__, 'logout']);
        add_action('wp_ajax_nopriv_fjt_logout', [__CLASS__, 'logout']);

        add_action('wp_ajax_fjt_resume_session', [__CLASS__, 'resume_session']);
        add_action('wp_ajax_nopriv_fjt_resume_session', [__CLASS__, 'resume_session']);

        // ADMIN ENDPOINTS
        add_action('wp_ajax_fjt_admin_get_users', [__CLASS__, 'admin_get_users']);
        add_action('wp_ajax_fjt_admin_get_user', [__CLASS__, 'admin_get_user']);
        add_action('wp_ajax_fjt_admin_delete_user', [__CLASS__, 'admin_delete_user']);
        add_action('wp_ajax_fjt_admin_restrict_user', [__CLASS__, 'admin_restrict_user']);
        add_action('wp_ajax_fjt_admin_update_user', [__CLASS__, 'admin_update_user']);
        add_action('wp_ajax_fjt_admin_update_entry', [__CLASS__, 'admin_update_entry']);
        add_action('wp_ajax_fjt_admin_update_entry_full', [__CLASS__, 'admin_update_entry_full']);
        add_action('wp_ajax_fjt_admin_delete_entry', [__CLASS__, 'admin_delete_entry']);
        add_action('wp_ajax_fjt_admin_get_stats', [__CLASS__, 'admin_get_stats']);
        add_action('wp_ajax_fjt_admin_update_user_field', [__CLASS__, 'admin_update_user_field']);
        
        // FORM BUILDER ENDPOINTS
        add_action('wp_ajax_fjt_fb_save_field', [__CLASS__, 'fb_save_field']);
        add_action('wp_ajax_fjt_fb_delete_field', [__CLASS__, 'fb_delete_field']);
        add_action('wp_ajax_fjt_fb_reorder_fields', [__CLASS__, 'fb_reorder_fields']);
        add_action('wp_ajax_fjt_fb_add_field', [__CLASS__, 'fb_add_field']);
    }

    /**
     * Check if user exists and resume session
     */
    public static function check_user()
    {
        FJT_Validation::validate_ajax_request('fjt_check_user');

        $mobile = isset($_POST['mobile_number']) ? $_POST['mobile_number'] : '';
        
        $validation = FJT_Validation::validate_mobile($mobile);
        
        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['message']]);
        }

        $mobile = $validation['value'];
        $user = FJT_Database::get_user($mobile);

        if (!$user) {
            wp_send_json_success(['exists' => false]);
        }

        if (!empty($user['is_restricted'])) {
            wp_send_json_error([
                'message' => 'Your account has been restricted. Please contact support.',
                'restricted' => true
            ]);
        }

        $session_token = FJT_Session_Manager::start_session($mobile);
        $entries = FJT_Database::get_user_entries($mobile);
        FJT_Database::upsert_user($mobile, ['last_login' => current_time('mysql')]);

        wp_send_json_success([
            'exists' => true,
            'user' => $user,
            'entries' => $entries,
            'session_token' => $session_token
        ]);
    }

    /**
     * Save new user - DYNAMIC field processing
     * Now supports query parameter fallback for missing POST fields
     */
    public static function save_user()
    {
        FJT_Validation::validate_ajax_request('fjt_save_user');

        parse_str($_POST['data'] ?? '', $form);

        // Merge with query parameters as fallback (sanitize first)
        $query_params = [];
        foreach ($_GET as $key => $value) {
            $key = sanitize_key($key);
            if (!empty($key) && !empty($value)) {
                $query_params[$key] = sanitize_text_field($value);
            }
        }
        
        // POST data takes precedence, query params are fallback
        $form = array_merge($query_params, $form);

        $required_fields = ['mobile_number', 'full_name', 'weight', 'target_weight'];
        $validation = FJT_Validation::validate_form($form, $required_fields);

        if (!$validation['valid']) {
            wp_send_json_error([
                'message' => 'Please fix the following errors',
                'errors' => $validation['errors']
            ]);
        }

        $data = $validation['data'];
        // Merge: start from raw form, overlay validated values (validated may have cleaned values)
        // This ensures dynamic fields not in config are still preserved
        $full_data = array_merge($form, $data);
        $mobile = $full_data['mobile_number'] ?? $data['mobile_number'];

        // Validate target_weight value
        $target_weight = floatval($data['target_weight'] ?? 0);
        if ($target_weight < 20 || $target_weight > 300) {
            wp_send_json_error(['message' => 'Target weight must be between 20 and 300 kg']);
        }

        $existing = FJT_Database::get_user($mobile);
        
        if ($existing && !empty($existing['is_restricted'])) {
            wp_send_json_error(['message' => 'Account restricted']);
        }

        // DYNAMIC: Build user profile from all non-core fields using full_data
        $profile = [];
        $core_fields = ['mobile_number', 'full_name', 'email', 'weight', 'goal', 'target_weight'];
        
        foreach ($full_data as $key => $value) {
            $key = sanitize_key($key);
            if (!in_array($key, $core_fields) && $value !== '' && $value !== null) {
                if (is_array($value)) {
                    $profile[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $profile[$key] = sanitize_text_field($value);
                }
            }
        }

        $user_data = [
            'full_name' => sanitize_text_field($full_data['full_name']),
            'email' => !empty($full_data['email']) ? sanitize_email($full_data['email']) : null,
            'user_profile' => $profile,
            'goal' => sanitize_text_field($full_data['goal'] ?? ''),
            'target_weight' => $target_weight,
            'current_step' => 2,
            'form_completed' => 1,
            'status' => 'active'
        ];

        FJT_Database::upsert_user($mobile, $user_data);

        // Save initial weight entry with ALL dynamic fields from full form data
        $weight = floatval($full_data['weight']);
        if ($weight > 0) {
            // Build entry meta from all non-system fields
            $system_keys = ['mobile_number', 'full_name', 'email', 'weight', 'goal', 'target_weight'];
            $entry_meta = [];
            foreach ($full_data as $key => $value) {
                $key = sanitize_key($key);
                if (in_array($key, $system_keys)) continue;
                if (is_array($value)) {
                    $entry_meta[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $entry_meta[$key] = sanitize_text_field($value);
                }
            }
            $entry_meta['submitted_at'] = current_time('mysql');
            FJT_Database::insert_entry($mobile, $weight, $entry_meta, 'initial');
        }

        $session_token = FJT_Session_Manager::start_session($mobile);
        $user = FJT_Database::get_user($mobile);
        $entries = FJT_Database::get_user_entries($mobile);

        wp_send_json_success([
            'message' => 'Profile saved successfully',
            'user' => $user,
            'entries' => $entries,
            'session_token' => $session_token
        ]);
    }

    /**
     * Add new weight entry - DYNAMIC field processing
     */
    public static function add_entry()
    {
        FJT_Validation::validate_ajax_request('fjt_add_entry');

        $mobile = sanitize_text_field($_POST['mobile_number'] ?? '');
        $weight = floatval($_POST['weight'] ?? 0);

        $validation = FJT_Validation::validate_mobile($mobile);
        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['message']]);
        }

        $mobile = $validation['value'];

        if ($weight <= 0 || $weight < 20 || $weight > 300) {
            wp_send_json_error(['message' => 'Invalid weight value']);
        }

        $user = FJT_Database::get_user($mobile);
        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
        }

        if (!empty($user['is_restricted'])) {
            wp_send_json_error(['message' => 'Account restricted']);
        }

        $session_token = FJT_Session_Manager::get_session_from_cookie();
        if (!$session_token || !FJT_Session_Manager::validate_session($session_token)) {
            wp_send_json_error(['message' => 'Session expired. Please login again.']);
        }

        // DYNAMIC: Capture ALL POST fields except system fields
        $system_keys = ['action', 'nonce', 'mobile_number', 'weight'];
        $meta = [];
        
        foreach ($_POST as $key => $value) {
            $key = sanitize_key($key);
            if (in_array($key, $system_keys)) continue;
            if (is_array($value)) {
                $meta[$key] = array_map('sanitize_text_field', $value);
            } else {
                $meta[$key] = sanitize_text_field($value);
            }
        }
        
        $meta['submitted_at'] = current_time('mysql');

        FJT_Database::insert_entry($mobile, $weight, $meta, 'weight_update');

        wp_send_json_success([
            'message' => 'Entry saved successfully',
            'entry' => [
                'weight' => $weight,
                'created_at' => current_time('mysql')
            ]
        ]);
    }

    /**
     * Update user profile - DYNAMIC
     */
    public static function update_user()
    {
        FJT_Validation::validate_ajax_request('fjt_update_user');

        parse_str($_POST['data'] ?? '', $form);

        $mobile = $form['mobile_number'] ?? '';
        $full_name = $form['full_name'] ?? '';

        $validation = FJT_Validation::validate_mobile($mobile);
        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['message']]);
        }

        if (empty($full_name)) {
            wp_send_json_error(['message' => 'Full name is required']);
        }

        $mobile = $validation['value'];

        // DYNAMIC: Build profile from all non-core fields
        $profile = [];
        $core_fields = ['mobile_number', 'full_name', 'email', 'goal', 'target_weight'];
        
        foreach ($form as $key => $value) {
            $key = sanitize_key($key);
            if (in_array($key, $core_fields)) continue;
            if ($value === '' || $value === null) continue;
            if (is_array($value)) {
                $profile[$key] = array_map('sanitize_text_field', $value);
            } else {
                $profile[$key] = sanitize_text_field($value);
            }
        }

        $user_data = [
            'full_name' => sanitize_text_field($full_name),
            'email' => !empty($form['email']) ? sanitize_email($form['email']) : null,
            'user_profile' => $profile,
            'goal' => sanitize_text_field($form['goal'] ?? ''),
        ];

        // Protect target_weight: only update if a valid value is provided
        if (!empty($form['target_weight'])) {
            $new_tw = floatval($form['target_weight']);
            if ($new_tw >= 20 && $new_tw <= 300) {
                $user_data['target_weight'] = $new_tw;
            }
        }
        // If target_weight is empty/absent, the existing DB value is preserved (upsert only sets provided fields)

        FJT_Database::upsert_user($mobile, $user_data);

        wp_send_json_success(['message' => 'Profile updated successfully']);
    }

    /**
     * Logout user
     */
    public static function logout()
    {
        FJT_Validation::validate_ajax_request('fjt_logout');

        $session_token = FJT_Session_Manager::get_session_from_cookie();
        if ($session_token) {
            FJT_Session_Manager::destroy_session($session_token);
        }

        wp_send_json_success(['message' => 'Logged out successfully']);
    }

    /**
     * Resume session
     */
    public static function resume_session()
    {
        FJT_Validation::validate_ajax_request('fjt_resume_session');

        $session_token = FJT_Session_Manager::get_session_from_cookie();
        
        if (!$session_token || !FJT_Session_Manager::validate_session($session_token)) {
            wp_send_json_error(['message' => 'No valid session found']);
        }

        $session_data = FJT_Session_Manager::get_session_data($session_token);
        $mobile = $session_data['mobile_number'] ?? '';

        if (empty($mobile)) {
            wp_send_json_error(['message' => 'Invalid session data']);
        }

        $user = FJT_Database::get_user($mobile);
        $entries = FJT_Database::get_user_entries($mobile);

        wp_send_json_success([
            'user' => $user,
            'entries' => $entries
        ]);
    }

    // ===== ADMIN ENDPOINTS =====

    public static function admin_get_users()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $search = sanitize_text_field($_POST['search'] ?? '');
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 20;

        $args = [
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        ];

        if (!empty($search)) {
            $args['search'] = $search;
        }

        if ($filter === 'active') {
            $args['status'] = 'active';
            $args['is_restricted'] = 0;
        } elseif ($filter === 'restricted') {
            $args['is_restricted'] = 1;
        } elseif ($filter === 'completed') {
            $args['status'] = 'active';
        }

        $users = FJT_Database::get_all_users($args);
        
        // Get total count for pagination
        $count_args = $args;
        unset($count_args['limit'], $count_args['offset']);
        $total_users = FJT_Database::count_users($count_args);
        $total_pages = ceil($total_users / $per_page);

        // Get current weight efficiently
        foreach ($users as &$user) {
            $current_weight = FJT_Database::get_latest_weight($user['mobile_number']);
            $user['current_weight'] = $current_weight ?? '--';
        }

        wp_send_json_success([
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_users' => $total_users,
                'per_page' => $per_page
            ]
        ]);
    }

    public static function admin_get_user()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $mobile = sanitize_text_field($_POST['mobile'] ?? '');
        $user = FJT_Database::get_user($mobile);

        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
        }

        $entries = FJT_Database::get_user_entries($mobile);

        wp_send_json_success([
            'user' => $user,
            'entries' => $entries
        ]);
    }

    public static function admin_delete_user()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $mobile = sanitize_text_field($_POST['mobile'] ?? '');
        $result = FJT_Database::delete_user($mobile);

        if ($result) {
            wp_send_json_success(['message' => 'User deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete user']);
        }
    }

    public static function admin_restrict_user()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $mobile = sanitize_text_field($_POST['mobile'] ?? '');
        $status = intval($_POST['status'] ?? 1);

        FJT_Database::set_restriction($mobile, $status);

        wp_send_json_success(['message' => 'User restriction updated']);
    }

    public static function admin_update_user()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $mobile = sanitize_text_field($_POST['mobile'] ?? '');
        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = $_POST['value'] ?? '';

        if (empty($mobile) || empty($field)) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        $user = FJT_Database::get_user($mobile);
        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
        }

        // DYNAMIC: Handle profile fields
        $core_fields = ['full_name', 'email', 'goal', 'target_weight'];
        
        if (in_array($field, $core_fields)) {
            $update_data = [$field => $value];
            FJT_Database::upsert_user($mobile, $update_data);
        } else {
            // Update in user_profile JSON
            $profile = $user['user_profile'] ?? [];
            if (is_string($profile)) {
                $profile = json_decode($profile, true);
            }
            $profile[$field] = $value;
            FJT_Database::upsert_user($mobile, ['user_profile' => $profile]);
        }

        wp_send_json_success(['message' => 'Field updated successfully']);
    }

    public static function admin_update_user_field()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $mobile = sanitize_text_field($_POST['mobile'] ?? '');
        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = $_POST['value'] ?? '';

        $user = FJT_Database::get_user($mobile);
        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
        }

        $core_fields = ['full_name', 'email', 'goal', 'target_weight'];
        
        if (in_array($field, $core_fields)) {
            FJT_Database::upsert_user($mobile, [$field => $value]);
        } else {
            $profile = $user['user_profile'] ?? [];
            if (is_string($profile)) {
                $profile = json_decode($profile, true);
            }
            $profile[$field] = $value;
            FJT_Database::upsert_user($mobile, ['user_profile' => $profile]);
        }

        wp_send_json_success(['message' => 'Updated successfully']);
    }

    public static function admin_update_entry()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $entry_id = intval($_POST['entry_id'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);

        if ($entry_id <= 0 || $weight <= 0) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        FJT_Database::update_entry($entry_id, ['weight' => $weight]);

        wp_send_json_success(['message' => 'Entry updated successfully']);
    }

    public static function admin_update_entry_full()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $entry_id = intval($_POST['entry_id'] ?? 0);

        if ($entry_id <= 0) {
            wp_send_json_error(['message' => 'Invalid entry ID']);
        }

        global $wpdb;
        $table = FJT_Database::get_table_name('entries');
        
        $entry = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $entry_id),
            ARRAY_A
        );

        if (!$entry) {
            wp_send_json_error(['message' => 'Entry not found']);
        }

        // Mode 1: single field/value pair (from inline entry field editor)
        if (isset($_POST['field']) && $_POST['field'] !== '') {
            $field = sanitize_text_field($_POST['field']);
            $value = sanitize_text_field($_POST['value'] ?? '');

            if ($field === 'weight') {
                FJT_Database::update_entry($entry_id, ['weight' => floatval($value)]);
            } else {
                $meta = !empty($entry['meta']) ? json_decode($entry['meta'], true) : [];
                if (!is_array($meta)) $meta = [];
                $meta[$field] = $value;
                FJT_Database::update_entry($entry_id, ['meta' => $meta]);
            }
        } else {
            // Mode 2: bulk update from table row editor (weight + meta fields sent directly)
            if (isset($_POST['weight'])) {
                $weight = floatval($_POST['weight']);
                if ($weight >= 20 && $weight <= 300) {
                    FJT_Database::update_entry($entry_id, ['weight' => $weight]);
                }
            }

            // Update meta fields - DYNAMIC: capture all POST keys that aren't system fields
            $system_keys = ['action', 'nonce', 'entry_id', 'weight', 'field', 'value'];
            $meta = !empty($entry['meta']) ? json_decode($entry['meta'], true) : [];
            if (!is_array($meta)) $meta = [];
            $changed = false;

            foreach ($_POST as $key => $raw_value) {
                $key = sanitize_key($key);
                if (in_array($key, $system_keys)) continue;
                $meta[$key] = sanitize_text_field($raw_value);
                $changed = true;
            }

            if ($changed) {
                FJT_Database::update_entry($entry_id, ['meta' => $meta]);
            }
        }

        wp_send_json_success(['message' => 'Entry updated successfully']);
    }

    public static function admin_delete_entry()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $entry_id = intval($_POST['entry_id'] ?? 0);
        $mobile = sanitize_text_field($_POST['mobile'] ?? '');

        $result = FJT_Database::delete_entry($entry_id, $mobile);

        if ($result) {
            wp_send_json_success(['message' => 'Entry deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete entry']);
        }
    }

    public static function admin_get_stats()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $stats = FJT_Database::get_user_stats();

        wp_send_json_success(['stats' => $stats]);
    }

    // ===== FORM BUILDER ENDPOINTS =====

    public static function fb_save_field()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $form_name = sanitize_text_field($_POST['form_name'] ?? '');
        $field_name = sanitize_text_field($_POST['field_name'] ?? '');
        $field_config = json_decode(stripslashes($_POST['field_config'] ?? '{}'), true);

        if (empty($form_name) || empty($field_name)) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        $current_config = FJT_Form_Config::get_form_config($form_name);
        $current_config[$field_name] = $field_config;

        FJT_Form_Config::update_form_config($form_name, $current_config);

        wp_send_json_success(['message' => 'Field saved successfully']);
    }

    public static function fb_delete_field()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $form_name = sanitize_text_field($_POST['form_name'] ?? '');
        $field_name = sanitize_text_field($_POST['field_name'] ?? '');

        if (FJT_Form_Config::is_protected_field($field_name)) {
            wp_send_json_error(['message' => 'Cannot delete protected field']);
        }

        $current_config = FJT_Form_Config::get_form_config($form_name);
        unset($current_config[$field_name]);

        FJT_Form_Config::update_form_config($form_name, $current_config);

        wp_send_json_success(['message' => 'Field deleted successfully']);
    }

    public static function fb_reorder_fields()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $form_name = sanitize_text_field($_POST['form_name'] ?? '');
        $field_order = json_decode(stripslashes($_POST['field_order'] ?? '[]'), true);

        $current_config = FJT_Form_Config::get_form_config($form_name);

        foreach ($field_order as $index => $field_name) {
            if (isset($current_config[$field_name])) {
                $current_config[$field_name]['order'] = $index + 1;
            }
        }

        FJT_Form_Config::update_form_config($form_name, $current_config);

        wp_send_json_success(['message' => 'Fields reordered successfully']);
    }

    public static function fb_add_field()
    {
        check_ajax_referer('fjt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $form_name = sanitize_text_field($_POST['form_name'] ?? '');
        $field_name = sanitize_text_field($_POST['field_name'] ?? '');
        $field_label = sanitize_text_field($_POST['field_label'] ?? '');
        $field_type = sanitize_text_field($_POST['field_type'] ?? 'text');

        if (empty($form_name) || empty($field_name) || empty($field_label)) {
            wp_send_json_error(['message' => 'All fields are required']);
        }

        // Validate field name format
        if (!preg_match('/^[a-z_]+$/', $field_name)) {
            wp_send_json_error(['message' => 'Field name must be lowercase with underscores only']);
        }

        $current_config = FJT_Form_Config::get_form_config($form_name);

        if (isset($current_config[$field_name])) {
            wp_send_json_error(['message' => 'Field already exists']);
        }

        $validation_map = [
            'text' => 'text',
            'textarea' => 'text',
            'email' => 'email',
            'tel' => 'mobile',
            'number' => 'number',
            'range' => 'number',
            'select' => 'select',
            'radio' => 'radio',
            'checkbox' => 'checkbox'
        ];

        $new_field = [
            'label' => $field_label,
            'type' => $field_type,
            'required' => false,
            'validation' => $validation_map[$field_type] ?? 'text',
            'order' => count($current_config) + 1
        ];

        if (in_array($field_type, ['text', 'textarea'])) {
            $new_field['placeholder'] = '';
            $new_field['max_length'] = 255;
        }

        if (in_array($field_type, ['number', 'range'])) {
            $new_field['min'] = 0;
            $new_field['max'] = 100;
        }

        if ($field_type === 'number') {
            $new_field['step'] = 1;
        }

        if ($field_type === 'range') {
            $new_field['default'] = 50;
        }

        if (in_array($field_type, ['select', 'radio', 'checkbox'])) {
            $new_field['options'] = ['Option 1', 'Option 2', 'Option 3'];
        }

        $current_config[$field_name] = $new_field;

        FJT_Form_Config::update_form_config($form_name, $current_config);

        wp_send_json_success([
            'message' => 'Field added successfully',
            'field' => $new_field
        ]);
    }
}