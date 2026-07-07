<?php
/**
 * Form Configuration - Fully Dynamic Database-Driven System
 * All form fields are stored in database and can be modified via admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class FJT_Form_Config
{
    /**
     * Protected core fields that cannot be removed
     */
    const PROTECTED_FIELDS = ['full_name', 'email', 'mobile_number', 'weight', 'target_weight'];
    
    /**
     * Initialize default form configurations on plugin activation
     */
    public static function initialize_default_configs()
    {
        global $wpdb;
        $table = $wpdb->prefix . FJT_FORM_CONFIG_TABLE;
        
        // Check if configs already exist
        $exists = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        if ($exists > 0) {
            return; // Already initialized
        }
        
        // Default configurations
        $default_configs = self::get_default_form_configs();
        
        foreach ($default_configs as $form_name => $config) {
            $wpdb->insert(
                $table,
                [
                    'form_name' => $form_name,
                    'config_data' => wp_json_encode($config),
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
        }
    }
    
    /**
     * Get default form configurations (used only for initial setup)
     */
    private static function get_default_form_configs()
    {
        return [
            'personal_info' => [
                'full_name' => [
                    'label' => 'Full Name',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'e.g. Priya Sharma',
                    'validation' => 'text',
                    'max_length' => 150,
                    'protected' => true,
                    'order' => 1
                ],
                'mobile_number' => [
                    'label' => 'Mobile Number',
                    'type' => 'tel',
                    'required' => true,
                    'placeholder' => 'e.g. 9876543210',
                    'validation' => 'mobile',
                    'max_length' => 15,
                    'protected' => true,
                    'order' => 2
                ],
                'email' => [
                    'label' => 'Email Address',
                    'type' => 'email',
                    'required' => false,
                    'placeholder' => 'e.g. priya@example.com',
                    'validation' => 'email',
                    'max_length' => 100,
                    'protected' => true,
                    'order' => 3
                ],
                'age' => [
                    'label' => 'Age',
                    'type' => 'number',
                    'required' => false,
                    'placeholder' => 'e.g. 32',
                    'validation' => 'number',
                    'min' => 10,
                    'max' => 120,
                    'order' => 4
                ],
                'gender' => [
                    'label' => 'Gender',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['Male', 'Female', 'Other'],
                    'validation' => 'select',
                    'order' => 5
                ]
            ],
            'body_details' => [
                'weight' => [
                    'label' => 'Current Weight (kg)',
                    'type' => 'number',
                    'required' => true,
                    'placeholder' => 'e.g. 68',
                    'validation' => 'number',
                    'min' => 20,
                    'max' => 300,
                    'step' => 0.1,
                    'protected' => true,
                    'order' => 1
                ],
                'height' => [
                    'label' => 'Height',
                    'type' => 'text',
                    'required' => false,
                    'placeholder' => 'e.g. 165cm',
                    'validation' => 'text',
                    'max_length' => 20,
                    'order' => 2
                ]
            ],
            'goals_lifestyle' => [
                'goal' => [
                    'label' => 'Your Goal',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['Weight Loss', 'Flexibility', 'General Fitness', 'Pain Relief'],
                    'validation' => 'select',
                    'order' => 1
                ],
                'target_weight' => [
                    'label' => 'Target Weight (kg)',
                    'type' => 'number',
                    'required' => false,
                    'placeholder' => 'e.g. 60',
                    'validation' => 'number',
                    'min' => 20,
                    'max' => 300,
                    'step' => 0.1,
                    'order' => 2
                ],
                'problems' => [
                    'label' => 'Main Problem',
                    'type' => 'checkbox',
                    'required' => false,
                    'options' => [
                        'Belly Fat',
                        'Back Pain',
                        'Low Energy',
                        'Stress & Anxiety',
                        'Poor Sleep'
                    ],
                    'validation' => 'checkbox',
                    'order' => 3
                ],
                'daily_routine' => [
                    'label' => 'Daily Routine',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['Sitting Job', 'Active', 'Mixed'],
                    'validation' => 'select',
                    'order' => 4
                ],
                'diet' => [
                    'label' => 'Can you follow a diet plan?',
                    'type' => 'radio',
                    'required' => false,
                    'options' => ['Yes', 'No'],
                    'validation' => 'radio',
                    'order' => 5
                ]
            ],
            'entry_fields' => [
                'feeling' => [
                    'label' => 'How do you feel compare to last month?',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['😊 Better', '😐 Same', '😔 Worse'],
                    'validation' => 'select',
                    'max_length' => 100,
                    'order' => 1
                ],
                'energy' => [
                    'label' => 'Energy Level',
                    'type' => 'range',
                    'required' => false,
                    'min' => 1,
                    'max' => 10,
                    'default' => 5,
                    'validation' => 'number',
                    'order' => 2
                ],
                'sleep' => [
                    'label' => 'Sleep Quality',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['🌙 Good', '😴 Average', '😩 Poor'],
                    'validation' => 'select',
                    'order' => 3
                ],
                'consistency' => [
                    'label' => 'Class Consistency',
                    'type' => 'select',
                    'required' => false,
                    'options' => ['🔥 Regular (5+ days/week)', '✅ Moderate (3-4 days/week)', '📅 Rarely'],
                    'validation' => 'select',
                    'order' => 4
                ],
                'testimonial' => [
                    'label' => 'Would you like to share your result as a testimonial?',
                    'type' => 'radio',
                    'required' => false,
                    'options' => ['Yes', 'No'],
                    'validation' => 'radio',
                    'order' => 5
                ]
            ]
        ];
    }
    
    /**
     * Get all form configs from database
     */
    public static function get_all_form_configs()
    {
        global $wpdb;
        $table = $wpdb->prefix . FJT_FORM_CONFIG_TABLE;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY form_name",
            ARRAY_A
        );
        
        $configs = [];
        foreach ($results as $row) {
            $configs[$row['form_name']] = json_decode($row['config_data'], true);
        }
        
        // Fallback to defaults if empty (shouldn't happen after initialization)
        if (empty($configs)) {
            $configs = self::get_default_form_configs();
        }
        
        return $configs;
    }
    
    /**
     * Get single form config
     */
    public static function get_form_config($form_name)
    {
        $all_configs = self::get_all_form_configs();
        return isset($all_configs[$form_name]) ? $all_configs[$form_name] : [];
    }
    
    /**
     * Update form configuration
     */
    public static function update_form_config($form_name, $config)
    {
        global $wpdb;
        $table = $wpdb->prefix . FJT_FORM_CONFIG_TABLE;
        
        // Check if exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE form_name = %s", $form_name)
        );
        
        if ($exists) {
            return $wpdb->update(
                $table,
                [
                    'config_data' => wp_json_encode($config),
                    'updated_at' => current_time('mysql')
                ],
                ['form_name' => $form_name],
                ['%s', '%s'],
                ['%s']
            );
        } else {
            return $wpdb->insert(
                $table,
                [
                    'form_name' => $form_name,
                    'config_data' => wp_json_encode($config),
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
        }
    }
    
    /**
     * Get all field names (flattened)
     */
    public static function get_all_field_names()
    {
        $all_fields = self::get_all_form_configs();
        $field_names = [];

        foreach ($all_fields as $section => $fields) {
            $field_names = array_merge($field_names, array_keys($fields));
        }

        return $field_names;
    }

    /**
     * Get field config by name (searches across all forms)
     */
    public static function get_field_config($field_name)
    {
        $all_fields = self::get_all_form_configs();

        foreach ($all_fields as $section => $fields) {
            if (isset($fields[$field_name])) {
                return $fields[$field_name];
            }
        }

        return null;
    }

    /**
     * Get required fields
     */
    public static function get_required_fields()
    {
        $all_fields = self::get_all_form_configs();
        $required = [];

        foreach ($all_fields as $section => $fields) {
            foreach ($fields as $name => $config) {
                if (!empty($config['required'])) {
                    $required[] = $name;
                }
            }
        }

        return $required;
    }

    /**
     * Validate field value against config
     */
    public static function validate_field($field_name, $value, $config = null)
    {
        if (!$config) {
            $config = self::get_field_config($field_name);
        }

        if (!$config) {
            // If field not in config, it might be old data - allow it
            return ['valid' => true];
        }

        // Check required
        if (!empty($config['required']) && empty($value)) {
            return ['valid' => false, 'message' => $config['label'] . ' is required'];
        }

        // Skip validation if empty and not required
        if (empty($value) && empty($config['required'])) {
            return ['valid' => true];
        }

        // Type-specific validation
        switch ($config['validation']) {
            case 'mobile':
                return FJT_Validation::validate_mobile($value);
            
            case 'email':
                return FJT_Validation::validate_email($value);
            
            case 'number':
                return FJT_Validation::validate_number($value, $config);
            
            case 'text':
                return FJT_Validation::validate_text($value, $config);
            
            case 'select':
            case 'radio':
                return FJT_Validation::validate_option($value, $config['options'] ?? []);
            
            case 'checkbox':
                return FJT_Validation::validate_checkbox($value, $config['options'] ?? []);
            
            default:
                return ['valid' => true];
        }
    }

    /**
     * Sanitize form data based on field config
     */
    public static function sanitize_form_data($data)
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $config = self::get_field_config($key);

            if (!$config) {
                // Unknown field - might be legacy data, sanitize as text
                $sanitized[$key] = sanitize_text_field($value);
                continue;
            }

            switch ($config['type']) {
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                
                case 'number':
                case 'range':
                    $sanitized[$key] = floatval($value);
                    break;
                
                case 'checkbox':
                    $sanitized[$key] = is_array($value) 
                        ? array_map('sanitize_text_field', $value) 
                        : [];
                    break;
                
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Get admin table columns configuration
     */
    public static function get_admin_columns()
    {
        return [
            'mobile_number' => 'Mobile Number',
            'full_name' => 'Full Name',
            'email' => 'Email',
            'goal' => 'Goal',
            'current_weight' => 'Current Weight',
            'target_weight' => 'Target Weight',
            'total_entries' => 'Total Entries',
            'last_entry_date' => 'Last Entry',
            'status' => 'Status',
            'time_created' => 'Registered',
        ];
    }
    
    /**
     * Check if field is protected (cannot be deleted)
     */
    public static function is_protected_field($field_name)
    {
        return in_array($field_name, self::PROTECTED_FIELDS);
    }
}
