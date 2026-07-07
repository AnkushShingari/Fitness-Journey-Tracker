<?php
/**
 * Shortcode Handler - Fully Dynamic Form Rendering
 * All forms are rendered dynamically from database configuration
 */

if (!defined('ABSPATH')) exit;

class FJT_Shortcode
{
    /**
     * Render main shortcode
     */
    public static function render($atts)
    {
        // Check for active session
        $current_user = FJT_Session_Manager::get_current_user();
        
        ob_start();
        ?>
        <div class="min-h-screen bg-gradient-to-br from-purple-50 via-white to-blue-50 p-4 md:p-8 font-sans">
            <div class="max-w-4xl mx-auto">
                
                <?php if ($current_user): ?>
                    <!-- User is logged in - Show dashboard -->
                    <?php echo self::get_dashboard_html($current_user); ?>
                <?php else: ?>
                    <!-- No active session - Show forms -->
                    <div id="fjtContainer">
                        <!-- Step 1: Mobile verification -->
                        <div id="step1" class="fjt-step active">
                            <?php echo self::get_mobile_check_html(); ?>
                        </div>

                        <!-- Step 2: Health form (hidden initially) -->
                        <div id="step2" class="fjt-step" style="display: none;">
                            <?php echo self::get_health_form_html(); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Mobile number verification form
     */
    private static function get_mobile_check_html()
    {
        // Check for query parameter prefill
        $mobile_prefill = isset($_GET['mobile_number']) ? sanitize_text_field($_GET['mobile_number']) : '';
        $is_prefilled = !empty($mobile_prefill);
        
        ob_start();
        ?>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden max-w-md mx-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-[var(--fjt-primary-first)] to-[var(--fjt-primary-second)] p-6 text-center">
                <h1 class="text-2xl font-bold text-white mb-2">🧘‍♀️ Start Your Journey</h1>
                <p class="text-purple-100 text-sm">Track your fitness progress</p>
            </div>

            <div class="p-6">
                <form id="mobileCheckForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                        <input 
                            type="tel" 
                            id="mobileInput" 
                            name="mobile_number" 
                            placeholder="e.g. 9876543210"
                            maxlength="15"
                            required
                            value="<?php echo esc_attr($mobile_prefill); ?>"
                            <?php echo $is_prefilled ? 'readonly' : ''; ?>
                            class="fjt-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all <?php echo $is_prefilled ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                        >
                        <p class="text-xs text-gray-500 mt-2">Enter your 10-digit mobile number</p>
                    </div>

                    <button 
                        type="submit"
                        class="w-full bg-gradient-to-r from-[var(--fjt-primary-first)] to-[var(--fjt-primary-second)] text-white font-bold py-3 rounded-xl hover:shadow-lg transition-all"
                    >
                        Continue
                    </button>

                    <p class="text-center text-xs text-gray-400">
                        Your data is <span class="font-bold">सुरक्षित</span> and secure
                    </p>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * DYNAMIC Health information form - Renders based on database config
     */
    private static function get_health_form_html()
    {
        $form_configs = FJT_Form_Config::get_all_form_configs();
        
        ob_start();
        ?>
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden max-w-2xl mx-auto">
            <!-- Header -->
            <div class="bg-gradient-to-r from-[var(--fjt-primary-first)] to-[var(--fjt-primary-second)] p-6 border-b border-purple-100">
                <h1 class="text-2xl font-bold text-white text-center mb-2">📋 Health Profile</h1>
                <p class="text-purple-100 text-sm text-center">Let's personalize your journey</p>
            </div>

            <div class="p-6 md:p-8">
                <form id="healthForm" class="space-y-6">
                    
                    <!-- Hidden fields for autofill data -->
                    <input type="hidden" name="mobile_number" id="hidden_mobile">

                    <?php
                    // Render each form section dynamically
                    $sections = [
                        'personal_info' => '👤 Personal Information',
                        'body_details' => '⚖️ Body Details',
                        'goals_lifestyle' => '🎯 Goals & Lifestyle'
                    ];
                    
                    foreach ($sections as $section_key => $section_title):
                        if (!isset($form_configs[$section_key])) continue;
                        
                        $fields = $form_configs[$section_key];
                        
                        // Sort by order
                        uasort($fields, function($a, $b) {
                            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
                        });
                    ?>
                    
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-gray-800 border-b pb-2"><?php echo esc_html($section_title); ?></h3>
                        
                        <?php foreach ($fields as $field_name => $field_config): 
                            // Runtime protection: target_weight is always required
                            if ($field_name === 'target_weight') {
                                $field_config['required'] = true;
                                $field_config['protected'] = true;
                            }
                        ?>
                            <?php echo self::render_field($field_name, $field_config, $section_key); ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php endforeach; ?>

                    <button 
                        type="submit"
                        class="w-full bg-gradient-to-r from-[var(--fjt-primary-first)] to-[var(--fjt-primary-second)] text-white font-bold py-4 rounded-xl hover:shadow-lg transition-all"
                    >
                        Complete Profile
                    </button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * DYNAMIC Field Renderer - Renders any field type based on config
     * Now supports query parameter prefilling with hidden input backup
     */
    private static function render_field($field_name, $config, $section = '')
    {
        $type = $config['type'] ?? 'text';
        $label = $config['label'] ?? ucwords(str_replace('_', ' ', $field_name));
        $required = !empty($config['required']);
        $placeholder = $config['placeholder'] ?? '';
        
        // Check for query parameter prefill
        $query_value = isset($_GET[$field_name]) ? sanitize_text_field($_GET[$field_name]) : '';
        $is_prefilled = !empty($query_value);
        
        ob_start();
        
        // Special handling for mobile field (show as disabled)
        if ($field_name === 'mobile_number') {
            ?>
            <div class="fjt-field-wrap">
                <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                <input 
                    type="tel" 
                    name="mobile_number_display" 
                    id="mobile_display"
                    placeholder="<?php echo esc_attr($placeholder); ?>"
                    disabled
                    class="fjt-input fjt-input-disabled"
                >
            </div>
            <?php
            return ob_get_clean();
        }
        
        // Render based on type
        switch ($type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
            case 'date':
                ?>
                <div class="fjt-field-wrap">
                    <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                    <input 
                        type="<?php echo esc_attr($type); ?>" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        id="<?php echo esc_attr($field_name); ?>_input"
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        <?php if ($is_prefilled): ?>
                        value="<?php echo esc_attr($query_value); ?>"
                        readonly
                        <?php endif; ?>
                        <?php if ($required): ?>required<?php endif; ?>
                        <?php if (!empty($config['max_length'])): ?>maxlength="<?php echo esc_attr($config['max_length']); ?>"<?php endif; ?>
                        class="fjt-input <?php echo $is_prefilled ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                    >
                </div>
                <?php
                break;
                
            case 'textarea':
                ?>
                <div class="fjt-field-wrap">
                    <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                    <textarea 
                        name="<?php echo esc_attr($field_name); ?>" 
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        <?php if ($is_prefilled): ?>readonly<?php endif; ?>
                        <?php if ($required): ?>required<?php endif; ?>
                        <?php if (!empty($config['max_length'])): ?>maxlength="<?php echo esc_attr($config['max_length']); ?>"<?php endif; ?>
                        rows="4"
                        class="fjt-input fjt-textarea <?php echo $is_prefilled ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                    ><?php echo $is_prefilled ? esc_textarea($query_value) : ''; ?></textarea>
                </div>
                <?php
                break;
                
            case 'number':
                ?>
                <div class="fjt-field-wrap">
                    <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                    <input 
                        type="number" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        <?php if ($is_prefilled): ?>
                        value="<?php echo esc_attr($query_value); ?>"
                        readonly
                        <?php endif; ?>
                        <?php if ($required): ?>required<?php endif; ?>
                        <?php if (isset($config['min'])): ?>min="<?php echo esc_attr($config['min']); ?>"<?php endif; ?>
                        <?php if (isset($config['max'])): ?>max="<?php echo esc_attr($config['max']); ?>"<?php endif; ?>
                        <?php if (isset($config['step'])): ?>step="<?php echo esc_attr($config['step']); ?>"<?php endif; ?>
                        class="fjt-input <?php echo $is_prefilled ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                    >
                </div>
                <?php
                break;
                
            case 'select':
                $field_id = esc_attr($field_name) . '_dd';
                ?>
                <div class="fjt-field-wrap">
                    <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                    <div class="fjt-dropdown-wrap">
                        <button type="button" class="fjt-dropdown-trigger <?php echo $is_prefilled ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" onclick="<?php echo $is_prefilled ? '' : "fjtToggleDropdown('" . esc_js($field_id) . "')"; ?>" <?php echo $is_prefilled ? 'disabled' : ''; ?>>
                            <span id="<?php echo esc_attr($field_name); ?>_dd_text" class="<?php echo $is_prefilled ? 'selected' : 'fjt-dd-placeholder'; ?>"><?php echo $is_prefilled ? esc_html($query_value) : esc_html($placeholder ?: 'Select ' . $label); ?></span>
                            <?php if (!$is_prefilled): ?>
                            <svg class="fjt-dd-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            <?php endif; ?>
                        </button>
                        <?php if (!$is_prefilled): ?>
                        <div id="<?php echo esc_attr($field_id); ?>" class="fjt-dropdown-menu">
                            <?php if (!empty($config['options'])): ?>
                                <?php foreach ($config['options'] as $option): ?>
                                    <button type="button" class="fjt-dd-item" onclick="fjtSelectDropdown('<?php echo esc_js($field_name); ?>', '<?php echo esc_js($option); ?>')"><?php echo esc_html($option); ?></button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <input type="hidden" name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_name); ?>_input" value="<?php echo $is_prefilled ? esc_attr($query_value) : ''; ?>" <?php if ($required): ?>required<?php endif; ?>>
                    </div>
                </div>
                <?php
                break;
                
            case 'radio':
                ?>
                <div class="fjt-field-wrap">
                    <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                    <div class="fjt-radio-grid">
                        <?php if (!empty($config['options'])): ?>
                            <?php foreach ($config['options'] as $index => $option): ?>
                                <?php 
                                $is_checked = $is_prefilled && $query_value === $option;
                                ?>
                                <label class="fjt-radio-card <?php echo $is_prefilled ? 'pointer-events-none opacity-75' : ''; ?>">
                                    <input 
                                        type="radio" 
                                        name="<?php echo esc_attr($field_name); ?>" 
                                        value="<?php echo esc_attr($option); ?>"
                                        <?php if ($is_checked): ?>checked<?php endif; ?>
                                        <?php if ($is_prefilled): ?>disabled<?php endif; ?>
                                        <?php if ($required && $index === 0): ?>required<?php endif; ?>
                                        class="hidden"
                                    >
                                    <div class="fjt-radio-ui">
                                        <div class="fjt-radio-outer"><div class="fjt-radio-inner"></div></div>
                                        <span class="fjt-radio-label"><?php echo esc_html($option); ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_prefilled): ?>
                        <!-- Hidden input to ensure disabled field value is submitted -->
                        <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($query_value); ?>">
                    <?php endif; ?>
                </div>
                <?php
                break;
                
            case 'checkbox':
                // Parse query value for checkboxes (comma-separated or array)
                $checked_values = [];
                if ($is_prefilled) {
                    if (is_array($query_value)) {
                        $checked_values = array_map('sanitize_text_field', $query_value);
                    } else {
                        $checked_values = array_map('trim', explode(',', $query_value));
                    }
                }
                ?>
                <div class="fjt-field-wrap">
                    <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                    <div class="fjt-checkbox-grid">
                        <?php if (!empty($config['options'])): ?>
                            <?php foreach ($config['options'] as $option): ?>
                                <?php 
                                $is_checked = $is_prefilled && in_array($option, $checked_values, true);
                                ?>
                                <label class="fjt-checkbox-card <?php echo $is_prefilled ? 'pointer-events-none opacity-75' : ''; ?>">
                                    <input 
                                        type="checkbox" 
                                        name="<?php echo esc_attr($field_name); ?>[]" 
                                        value="<?php echo esc_attr($option); ?>"
                                        <?php if ($is_checked): ?>checked<?php endif; ?>
                                        <?php if ($is_prefilled): ?>disabled<?php endif; ?>
                                        class="fjt-checkbox-input"
                                    >
                                    <span class="fjt-checkbox-label"><?php echo esc_html($option); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_prefilled && !empty($checked_values)): ?>
                        <!-- Hidden inputs to ensure disabled field values are submitted -->
                        <?php foreach ($checked_values as $checked_val): ?>
                            <input type="hidden" name="<?php echo esc_attr($field_name); ?>[]" value="<?php echo esc_attr($checked_val); ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php
                break;
                
            case 'range':
                $min = $config['min'] ?? 1;
                $max = $config['max'] ?? 10;
                $default = $config['default'] ?? (($min + $max) / 2);
                $prefill_value = $is_prefilled ? $query_value : $default;
                ?>
                <div class="fjt-field-wrap">
                    <div class="fjt-range-header">
                        <label class="fjt-label" style="margin-bottom:0"><?php echo esc_html($label); ?></label>
                        <span id="<?php echo esc_attr($field_name); ?>_value" class="fjt-range-value"><?php echo esc_html($prefill_value); ?>/<?php echo esc_html($max); ?></span>
                    </div>
                    <input 
                        type="range" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        id="<?php echo esc_attr($field_name); ?>_slider"
                        min="<?php echo esc_attr($min); ?>" 
                        max="<?php echo esc_attr($max); ?>" 
                        value="<?php echo esc_attr($prefill_value); ?>"
                        <?php if ($is_prefilled): ?>disabled<?php endif; ?>
                        class="fjt-range-slider <?php echo $is_prefilled ? 'opacity-50 pointer-events-none' : ''; ?>"
                        oninput="document.getElementById('<?php echo esc_js($field_name); ?>_value').textContent = this.value + '/<?php echo esc_js($max); ?>'"
                    >
                    <?php if ($is_prefilled): ?>
                        <!-- Hidden input to ensure disabled field value is submitted -->
                        <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($prefill_value); ?>">
                    <?php endif; ?>
                    <div class="fjt-range-labels">
                        <span>Low (<?php echo esc_html($min); ?>)</span>
                        <span>Moderate (<?php echo esc_html(round(($min + $max) / 2)); ?>)</span>
                        <span>High (<?php echo esc_html($max); ?>)</span>
                    </div>
                </div>
                <?php
                break;
                
            case 'hidden':
                ?>
                <input 
                    type="hidden" 
                    name="<?php echo esc_attr($field_name); ?>" 
                    value="<?php echo esc_attr($config['default'] ?? ''); ?>"
                >
                <?php
                break;
                
            default:
                ?>
                <div class="fjt-field-wrap">
                    <label class="fjt-label"><?php echo esc_html($label); ?><?php if ($required): ?> <span class="fjt-required">*</span><?php endif; ?></label>
                    <input 
                        type="text" 
                        name="<?php echo esc_attr($field_name); ?>" 
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        class="fjt-input"
                    >
                </div>
                <?php
        }
        
        return ob_get_clean();
    }

    /**
     * User dashboard with progress - TAB-BASED LAYOUT
     */
    private static function get_dashboard_html($user)
    {
        $entries = FJT_Database::get_user_entries($user['mobile_number']);
        $latest_entry = !empty($entries) ? end($entries) : null;
        $latest_weight = $latest_entry ? $latest_entry['weight'] : '--';
        $initial_entry = !empty($entries) ? reset($entries) : null;
        $weight_change = ($latest_entry && $initial_entry && $initial_entry !== $latest_entry)
            ? round($latest_entry['weight'] - $initial_entry['weight'], 1) : null;
        
        $profile = !empty($user['user_profile']) ? $user['user_profile'] : [];
        if (is_string($profile)) {
            $profile = json_decode($profile, true) ?: [];
        }
        
        ob_start();
        ?>
        <div class="fjt-dashboard">

            <!-- Header -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-5 mb-4">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Welcome back, <?php echo esc_html($user['full_name']); ?>! 👋</h1>
                        <p class="text-gray-400 text-sm"><?php echo esc_html($user['mobile_number']); ?></p>
                    </div>
                    <button id="logoutBtn" class="px-5 py-2 bg-red-500 hover:bg-red-600 text-white font-medium rounded-xl transition-all text-sm">Logout</button>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Current</p>
                    <p class="text-2xl font-bold text-[var(--fjt-primary-first)]"><?php echo esc_html($latest_weight); ?><?php echo $latest_weight !== '--' ? ' kg' : ''; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Target</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $user['target_weight'] ? esc_html($user['target_weight']) . ' kg' : '--'; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Entries</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo esc_html($user['total_entries']); ?></p>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
                <div class="flex border-b border-gray-100 overflow-x-auto">
                    <button class="fjt-tab-btn flex-1 min-w-max py-3 px-4 text-sm font-semibold text-gray-500 hover:text-[var(--fjt-primary-first)] transition-all border-b-2 border-transparent whitespace-nowrap" data-tab="add-entry">➕ Add Entry</button>
                    <button class="fjt-tab-btn flex-1 min-w-max py-3 px-4 text-sm font-semibold text-gray-500 hover:text-[var(--fjt-primary-first)] transition-all border-b-2 border-transparent whitespace-nowrap" data-tab="progress">📊 Progress</button>
                    <button class="fjt-tab-btn flex-1 min-w-max py-3 px-4 text-sm font-semibold text-gray-500 hover:text-[var(--fjt-primary-first)] transition-all border-b-2 border-transparent whitespace-nowrap" data-tab="history">📚 History</button>
                    <button class="fjt-tab-btn flex-1 min-w-max py-3 px-4 text-sm font-semibold text-gray-500 hover:text-[var(--fjt-primary-first)] transition-all border-b-2 border-transparent whitespace-nowrap" data-tab="profile">👤 Profile</button>
                </div>

                <!-- Tab: Add Entry -->
                <div id="fjt-tab-add-entry" class="fjt-tab-panel p-5" style="display:none;">
                    <?php echo self::get_dynamic_entry_form($user, $entries); ?>
                </div>

                <!-- Tab: Progress Chart -->
                <div id="fjt-tab-progress" class="fjt-tab-panel p-5" style="display:none;">
                    <?php echo self::get_progress_summary($user, $entries); ?>
                    <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
                        <h2 class="text-lg font-bold text-gray-800">Weight Progress</h2>
                        <select id="chartFilter" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-400">
                            <option value="all">All Time</option>
                            <option value="30">Last 30 Days</option>
                            <option value="7">Last 7 Days</option>
                        </select>
                    </div>
                    <?php if (!empty($entries)): ?>
                        <div style="position:relative; min-height:200px;">
                            <canvas id="progressChart"></canvas>
                        </div>
                        <?php if ($weight_change !== null): ?>
                        <p class="text-center text-sm mt-3 <?php echo $weight_change < 0 ? 'text-green-600' : 'text-orange-500'; ?> font-semibold">
                            <?php echo $weight_change < 0 ? '↓' : '↑'; ?> <?php echo abs($weight_change); ?> kg overall
                        </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-gray-400 text-center py-10">No entries yet. Add your first entry!</p>
                    <?php endif; ?>
                </div>

                <!-- Tab: History -->
                <div id="fjt-tab-history" class="fjt-tab-panel p-5" style="display:none;">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Entry History</h2>
                    <?php if (!empty($entries)): ?>
                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        <?php foreach (array_reverse($entries) as $entry): 
                            $meta = $entry['meta'] ?? [];
                            if (is_string($meta)) $meta = json_decode($meta, true);
                            if (!is_array($meta)) $meta = [];
                        ?>
                        <div class="p-4 border border-gray-100 rounded-xl bg-gray-50 hover:bg-white hover:shadow-sm transition-all">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-bold text-gray-800 text-lg"><?php echo esc_html($entry['weight']); ?> kg</p>
                                    <p class="text-xs text-gray-400 mt-0.5"><?php echo esc_html(date('M d, Y', strtotime($entry['created_at']))); ?></p>
                                </div>
                                <div class="text-right text-sm text-gray-500">
                                    <?php if (!empty($meta['feeling'])): ?>
                                        <p class="italic">"<?php echo esc_html($meta['feeling']); ?>"</p>
                                    <?php endif; ?>
                                    <?php if (!empty($meta['energy'])): ?>
                                        <p>Energy: <?php echo esc_html($meta['energy']); ?>/10</p>
                                    <?php endif; ?>
                                    <?php if (!empty($meta['sleep'])): ?>
                                        <p>Sleep: <?php echo esc_html($meta['sleep']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-400 text-center py-10">No entries yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Tab: Profile -->
                <div id="fjt-tab-profile" class="fjt-tab-panel p-5" style="display:none;">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Your Profile</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between py-2 border-b border-gray-50">
                            <span class="text-sm text-gray-500 font-medium">Name</span>
                            <span class="text-sm text-gray-800 font-semibold"><?php echo esc_html($user['full_name']); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-gray-50">
                            <span class="text-sm text-gray-500 font-medium">Mobile</span>
                            <span class="text-sm text-gray-800"><?php echo esc_html($user['mobile_number']); ?></span>
                        </div>
                        <?php if (!empty($user['email'])): ?>
                        <div class="flex justify-between py-2 border-b border-gray-50">
                            <span class="text-sm text-gray-500 font-medium">Email</span>
                            <span class="text-sm text-gray-800"><?php echo esc_html($user['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user['goal'])): ?>
                        <div class="flex justify-between py-2 border-b border-gray-50">
                            <span class="text-sm text-gray-500 font-medium">Goal</span>
                            <span class="text-sm text-gray-800"><?php echo esc_html($user['goal']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php foreach ($profile as $key => $val): 
                            if (empty($val)) continue;
                            $label = ucwords(str_replace('_', ' ', $key));
                            $display = is_array($val) ? implode(', ', $val) : $val;
                        ?>
                        <div class="flex justify-between py-2 border-b border-gray-50">
                            <span class="text-sm text-gray-500 font-medium"><?php echo esc_html($label); ?></span>
                            <span class="text-sm text-gray-800"><?php echo esc_html($display); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

        <script>
            window.fjtEntries = <?php echo json_encode($entries); ?>;
            window.fjtUser = <?php echo json_encode($user); ?>;
        </script>

        <style>
            .fjt-tab-btn.active {
                color: var(--fjt-primary-second);
                border-bottom-color: var(--fjt-primary-second) !important;
                background: var(--fjt-primary-lite);
            }
            .fjt-tab-btn.focus {
                border-color: var(--fjt-primary-second) !important;
                background: #faf5ff;
            }
            .fjt-dashboard .fjt-add-entry-inner {
                padding: 0;
            }
            .fjt-dashboard #entryForm {
                padding: 0;
            }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get progress summary message above chart
     */
    private static function get_progress_summary($user, $entries = [])
    {
        if (empty($entries) || count($entries) < 1) {
            return '';
        }
        
        $starting_weight = floatval($entries[0]['weight']);
        $current_weight = floatval($entries[count($entries) - 1]['weight']);
        $target_weight = !empty($user['target_weight']) ? floatval($user['target_weight']) : null;
        
        // Get previous weight (second latest entry if exists)
        $previous_weight = null;
        if (count($entries) > 1) {
            $previous_weight = floatval($entries[count($entries) - 2]['weight']);
        }
        
        // Determine goal type
        $is_weight_loss_goal = $target_weight && $target_weight < $starting_weight;
        $is_weight_gain_goal = $target_weight && $target_weight > $starting_weight;
        
        // Calculate overall progress
        $total_change = $current_weight - $starting_weight;
        $abs_total_change = abs($total_change);
        
        // Calculate recent progress (if previous weight exists)
        $recent_progress = null;
        if ($previous_weight !== null) {
            $recent_change = $current_weight - $previous_weight;
            
            // Determine if recent change is positive or negative based on goal
            if ($is_weight_loss_goal) {
                // For weight loss: lower is better
                if ($recent_change < 0) {
                    $recent_progress = 'positive';
                } elseif ($recent_change > 0) {
                    $recent_progress = 'negative';
                } else {
                    $recent_progress = 'no_change';
                }
            } elseif ($is_weight_gain_goal) {
                // For weight gain: higher is better
                if ($recent_change > 0) {
                    $recent_progress = 'positive';
                } elseif ($recent_change < 0) {
                    $recent_progress = 'negative';
                } else {
                    $recent_progress = 'no_change';
                }
            }
        }
        
        // Check if target reached
        $target_reached = false;
        if ($target_weight) {
            if ($is_weight_loss_goal && $current_weight <= $target_weight) {
                $target_reached = true;
            } elseif ($is_weight_gain_goal && $current_weight >= $target_weight) {
                $target_reached = true;
            }
        }
        
        ob_start();
        ?>
        <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-xl p-4 mb-4">
            <p class="text-sm text-gray-800 leading-relaxed">
                <?php if ($target_reached): ?>
                    <!-- TARGET REACHED -->
                    <span class="font-semibold text-green-700">🎉 Congratulations!</span><br>
                    You've successfully reached your target weight.<br>
                    Amazing dedication and consistency 🔥
                    
                <?php elseif ($recent_progress === 'positive'): ?>
                    <!-- POSITIVE PROGRESS -->
                    <span class="font-semibold text-green-700">Yea... you are doing good.</span><br>
                    You started from <strong><?php echo esc_html($starting_weight); ?> kg</strong> and now <strong><?php echo esc_html($current_weight); ?> kg</strong>.<br>
                    <?php if ($is_weight_loss_goal): ?>
                        You have <span class="text-green-600 font-bold">lost <?php echo esc_html($abs_total_change); ?> kg</span>.
                        <?php if ($target_weight): 
                            $remaining = $current_weight - $target_weight;
                        ?>
                            <br>Keep going. Only <strong><?php echo esc_html($remaining); ?> kg</strong> left to reach your target weight.
                        <?php endif; ?>
                    <?php else: ?>
                        You have <span class="text-blue-600 font-bold">gained <?php echo esc_html($abs_total_change); ?> kg</span>.
                        <?php if ($target_weight): 
                            $remaining = $target_weight - $current_weight;
                        ?>
                            <br>Only <strong><?php echo esc_html($remaining); ?> kg</strong> more to reach your target weight.
                        <?php endif; ?>
                    <?php endif; ?>
                    
                <?php elseif ($recent_progress === 'no_change'): ?>
                    <!-- NO CHANGE -->
                    <span class="font-semibold text-blue-700">You're maintaining your progress.</span><br>
                    Consistency is the key 💪<br>
                    Keep following your routine.
                    
                <?php elseif ($recent_progress === 'negative'): ?>
                    <!-- NEGATIVE PROGRESS -->
                    <span class="font-semibold text-orange-700">Don't worry — progress takes time 💙</span><br>
                    <?php if ($is_weight_loss_goal): ?>
                        Your current weight increased slightly compared to before.<br>
                    <?php else: ?>
                        Your current weight decreased slightly compared to before.<br>
                    <?php endif; ?>
                    Stay consistent and work a little harder toward your goal.<br>
                    You can do this 🔥
                    
                <?php else: ?>
                    <!-- FIRST ENTRY OR NO COMPARISON -->
                    <span class="font-semibold text-blue-700">Great start!</span><br>
                    You started from <strong><?php echo esc_html($starting_weight); ?> kg</strong>.<br>
                    <?php if ($target_weight): ?>
                        Your target is <strong><?php echo esc_html($target_weight); ?> kg</strong>.<br>
                        Keep tracking your progress regularly 💪
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * DYNAMIC Entry Form - Renders based on entry_fields config
     */
    private static function get_dynamic_entry_form($user, $entries = [])
    {
        $entry_config = FJT_Form_Config::get_form_config('entry_fields');
        
        // Sort by order
        uasort($entry_config, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        // Get previous weight if exists
        $previous_weight = null;
        if (!empty($entries)) {
            $previous_weight = floatval($entries[count($entries) - 1]['weight']);
        }
        
        ob_start();
        ?>
        
        <?php if ($previous_weight): ?>
        <!-- Previous Weight Message -->
        <div class="bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-xl p-4 mb-4">
            <p class="text-sm text-gray-700 leading-relaxed">
                <span class="font-semibold text-purple-700">Your previous weight is <?php echo esc_html($previous_weight); ?> kg.</span><br>
                Add your new weight below to check your progress result.
            </p>
        </div>
        <?php endif; ?>
        
        <form id="entryForm" class="space-y-4">
            <input type="hidden" name="mobile_number" value="<?php echo esc_attr($user['mobile_number']); ?>">
            
            <!-- Weight (always required) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Current Weight (kg) <span class="text-red-500">*</span>
                </label>
                <input 
                    type="number" 
                    name="weight" 
                    placeholder="e.g. 65"
                    min="20"
                    max="300"
                    step="0.1"
                    required
                    class="fjt-input w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
            </div>

            <!-- Dynamic Fields from entry_fields config -->
            <?php foreach ($entry_config as $field_name => $field_config): 
                // target_weight is a profile field, not an entry field
                if ($field_name === 'target_weight') continue;
            ?>
                <?php echo self::render_field($field_name, $field_config, 'entry_fields'); ?>
            <?php endforeach; ?>

            <button 
                type="submit"
                class="w-full bg-gradient-to-r from-[var(--fjt-primary-first)] to-[var(--fjt-primary-second)] text-white font-bold py-4 rounded-xl hover:shadow-lg transition-all"
            >
                Save Entry
            </button>
        </form>
        <?php
        return ob_get_clean();
    }
}