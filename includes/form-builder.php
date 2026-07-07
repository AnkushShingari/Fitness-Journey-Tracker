<?php
/**
 * Form Builder - Admin interface for managing dynamic forms
 */

if (!defined('ABSPATH')) exit;

class FJT_Form_Builder
{
    /**
     * Render form builder page
     */
    public static function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        $form_configs = FJT_Form_Config::get_all_form_configs();
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'personal_info';
        
        ?>
        <div class="wrap fjt-form-builder-wrap">
            <h1 class="wp-heading-inline">📝 Form Builder</h1>
            <p class="description font-medium">Manage form fields dynamically. Add, edit, reorder, or remove fields without coding.</p>
            <p class="description">Use ShortCode <code>[fitness_tracker]</code> to render front-end user interface.</p>
            <p class="description"></p>Users can be directed to the registration page with pre-filled data by matching the field name with the query attribute: <br>Example URL:&nbsp;<code>https://yoursite.com/fitness-tracker/?mobile_number=9876543210&full_name=John%20Doe&email=john@example.com</code></p>
            
            <div class="fjt-fb-container">
                
                <!-- Form Tabs -->
                <div class="fjt-fb-tabs">
                    <button class="fjt-fb-tab <?php echo $active_tab === 'personal_info' ? 'active' : ''; ?>" data-form="personal_info">
                        👤 Personal Information
                    </button>
                    <button class="fjt-fb-tab <?php echo $active_tab === 'body_details' ? 'active' : ''; ?>" data-form="body_details">
                        ⚖️ Body Details
                    </button>
                    <button class="fjt-fb-tab <?php echo $active_tab === 'goals_lifestyle' ? 'active' : ''; ?>" data-form="goals_lifestyle">
                        🎯 Goals & Lifestyle
                    </button>
                    <button class="fjt-fb-tab <?php echo $active_tab === 'entry_fields' ? 'active' : ''; ?>" data-form="entry_fields">
                        📊 Entry Fields
                    </button>
                </div>
                
                <!-- Form Content -->
                <?php foreach ($form_configs as $form_name => $fields): ?>
                <div class="fjt-fb-form-content" id="form-<?php echo esc_attr($form_name); ?>" style="display: <?php echo $active_tab === $form_name ? 'block' : 'none'; ?>;">
                    
                    <div class="fjt-fb-header">
                        <h2><?php echo esc_html(ucwords(str_replace('_', ' ', $form_name))); ?></h2>
                        <button class="fjt-btn fjt-btn-primary add-field-btn" data-form="<?php echo esc_attr($form_name); ?>">
                            ➕ Add New Field
                        </button>
                    </div>
                    
                    <div class="fjt-fb-fields-list" id="fields-<?php echo esc_attr($form_name); ?>">
                        <?php 
                        // Sort fields by order
                        uasort($fields, function($a, $b) {
                            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
                        });
                        
                        foreach ($fields as $field_name => $field_config): 
                            $is_protected = FJT_Form_Config::is_protected_field($field_name);
                        ?>
                        <div class="fjt-fb-field-item" data-field="<?php echo esc_attr($field_name); ?>" data-form="<?php echo esc_attr($form_name); ?>">
                            <div class="fjt-fb-field-header">
                                <span class="fjt-fb-drag-handle">⋮⋮</span>
                                <div class="fjt-fb-field-info">
                                    <strong><?php echo esc_html($field_config['label']); ?></strong>
                                    <span class="fjt-fb-field-meta">
                                        <?php echo esc_html($field_config['type']); ?>
                                        <?php if (!empty($field_config['required'])): ?>
                                            <span class="fjt-fb-badge fjt-required">Required</span>
                                        <?php endif; ?>
                                        <?php if ($is_protected): ?>
                                            <span class="fjt-fb-badge fjt-protected">Protected</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="fjt-fb-field-actions">
                                    <button class="fjt-btn-icon edit-field-btn" title="Edit Field">✏️</button>
                                    <?php if (!$is_protected): ?>
                                        <button class="fjt-btn-icon delete-field-btn" title="Delete Field">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Edit Form (Hidden by default) -->
                            <div class="fjt-fb-field-edit" style="display: none;">
                                <div class="fjt-fb-field-edit-grid">
                                    
                                    <div class="fjt-fb-form-group">
                                        <label>Field Label:</label>
                                        <input type="text" class="field-label" value="<?php echo esc_attr($field_config['label']); ?>" />
                                    </div>
                                    
                                    <div class="fjt-fb-form-group">
                                        <label>Field Type:</label>
                                        <select class="field-type">
                                            <?php
                                            $field_types = [
                                                'text' => 'Text',
                                                'textarea' => 'Textarea',
                                                'number' => 'Number',
                                                'email' => 'Email',
                                                'tel' => 'Telephone',
                                                'select' => 'Dropdown',
                                                'radio' => 'Radio Buttons',
                                                'checkbox' => 'Checkboxes',
                                                'range' => 'Range Slider',
                                                'date' => 'Date',
                                                'url' => 'URL',
                                                'hidden' => 'Hidden'
                                            ];
                                            foreach ($field_types as $value => $label):
                                            ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($field_config['type'], $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="fjt-fb-form-group">
                                        <label>Placeholder:</label>
                                        <input type="text" class="field-placeholder" value="<?php echo esc_attr($field_config['placeholder'] ?? ''); ?>" />
                                    </div>
                                    
                                    <div class="fjt-fb-form-group">
                                        <label>Validation:</label>
                                        <select class="field-validation">
                                            <?php
                                            $validations = [
                                                'text' => 'Text',
                                                'email' => 'Email',
                                                'mobile' => 'Mobile',
                                                'number' => 'Number',
                                                'select' => 'Dropdown',
                                                'radio' => 'Radio',
                                                'checkbox' => 'Checkbox'
                                            ];
                                            foreach ($validations as $value => $label):
                                            ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($field_config['validation'] ?? 'text', $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="fjt-fb-form-group">
                                        <label>
                                            <input type="checkbox" class="field-required" <?php checked(!empty($field_config['required'])); ?> />
                                            Required Field
                                        </label>
                                    </div>
                                    
                                    <div class="fjt-fb-form-group number-fields" style="display: <?php echo in_array($field_config['type'], ['number', 'range']) ? 'block' : 'none'; ?>;">
                                        <label>Min Value:</label>
                                        <input type="number" class="field-min" value="<?php echo esc_attr($field_config['min'] ?? ''); ?>" />
                                    </div>
                                    
                                    <div class="fjt-fb-form-group number-fields" style="display: <?php echo in_array($field_config['type'], ['number', 'range']) ? 'block' : 'none'; ?>;">
                                        <label>Max Value:</label>
                                        <input type="number" class="field-max" value="<?php echo esc_attr($field_config['max'] ?? ''); ?>" />
                                    </div>
                                    
                                    <div class="fjt-fb-form-group number-fields" style="display: <?php echo $field_config['type'] === 'number' ? 'block' : 'none'; ?>;">
                                        <label>Step:</label>
                                        <input type="number" class="field-step" value="<?php echo esc_attr($field_config['step'] ?? ''); ?>" step="0.1" />
                                    </div>
                                    
                                    <div class="fjt-fb-form-group text-fields" style="display: <?php echo in_array($field_config['type'], ['text', 'textarea']) ? 'block' : 'none'; ?>;">
                                        <label>Max Length:</label>
                                        <input type="number" class="field-maxlength" value="<?php echo esc_attr($field_config['max_length'] ?? ''); ?>" />
                                    </div>
                                    
                                    <div class="fjt-fb-form-group range-fields" style="display: <?php echo $field_config['type'] === 'range' ? 'block' : 'none'; ?>;">
                                        <label>Default Value:</label>
                                        <input type="number" class="field-default" value="<?php echo esc_attr($field_config['default'] ?? ''); ?>" />
                                    </div>
                                    
                                    <div class="fjt-fb-form-group option-fields" style="display: <?php echo in_array($field_config['type'], ['select', 'radio', 'checkbox']) ? 'block' : 'none'; ?>;">
                                        <label>Options (one per line):</label>
                                        <textarea class="field-options" rows="4"><?php 
                                            if (!empty($field_config['options'])) {
                                                echo esc_textarea(implode("\n", $field_config['options']));
                                            }
                                        ?></textarea>
                                    </div>
                                    
                                </div>
                                
                                <div class="fjt-fb-field-edit-actions">
                                    <button class="fjt-btn fjt-btn-primary save-field-btn">💾 Save Field</button>
                                    <button class="fjt-btn fjt-btn-secondary cancel-edit-btn">❌ Cancel</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                </div>
                <?php endforeach; ?>
                
            </div>
        </div>
        
        <!-- Add Field Modal -->
        <div id="addFieldModal" class="fjt-modal" style="display: none;">
            <div class="fjt-modal-content">
                <div class="fjt-modal-header">
                    <h2>Add New Field</h2>
                    <button class="fjt-modal-close">&times;</button>
                </div>
                <div class="fjt-modal-body">
                    <div class="fjt-fb-form-group">
                        <label>Field Name (Internal):</label>
                        <input type="text" id="newFieldName" placeholder="e.g. phone_number" />
                        <small>Use lowercase, underscores only. No spaces.</small>
                    </div>
                    
                    <div class="fjt-fb-form-group">
                        <label>Field Label (Display):</label>
                        <input type="text" id="newFieldLabel" placeholder="e.g. Phone Number" />
                    </div>
                    
                    <div class="fjt-fb-form-group">
                        <label>Field Type:</label>
                        <select id="newFieldType">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="email">Email</option>
                            <option value="tel">Telephone</option>
                            <option value="select">Dropdown</option>
                            <option value="radio">Radio Buttons</option>
                            <option value="checkbox">Checkboxes</option>
                            <option value="range">Range Slider</option>
                            <option value="date">Date</option>
                            <option value="url">URL</option>
                        </select>
                    </div>
                </div>
                <div class="fjt-modal-footer">
                    <button class="fjt-btn fjt-btn-primary" id="createFieldBtn">➕ Create Field</button>
                    <button class="fjt-btn fjt-btn-secondary fjt-modal-close">Cancel</button>
                </div>
            </div>
        </div>
        
        <style>
        .fjt-form-builder-wrap { padding: 20px; }
        .fjt-fb-container { background: #fff; margin-top: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .fjt-fb-tabs { display: flex; border-bottom: 2px solid #e5e7eb; padding: 0 20px; }
        .fjt-fb-tab { padding: 15px 20px; border: none; background: none; cursor: pointer; font-weight: 500; color: #6b7280; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .fjt-fb-tab:hover { color: #8b5cf6; }
        .fjt-fb-tab.active { color: #8b5cf6; border-bottom-color: #8b5cf6; }
        .fjt-fb-form-content { padding: 20px; }
        .fjt-fb-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; }
        .fjt-fb-fields-list { display: flex; flex-direction: column; gap: 10px; }
        .fjt-fb-field-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; transition: all 0.2s; }
        .fjt-fb-field-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .fjt-fb-field-header { display: flex; align-items: center; gap: 15px; }
        .fjt-fb-drag-handle { cursor: move; color: #9ca3af; font-size: 18px; }
        .fjt-fb-field-info { flex: 1; }
        .fjt-fb-field-info strong { display: block; margin-bottom: 4px; }
        .fjt-fb-field-meta { font-size: 13px; color: #6b7280; display: flex; align-items: center; gap: 8px; }
        .fjt-fb-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .fjt-fb-badge.fjt-required { background: #fef3c7; color: #92400e; }
        .fjt-fb-badge.fjt-protected { background: #dbeafe; color: #1e40af; }
        .fjt-fb-field-actions { display: flex; gap: 8px; }
        .fjt-btn-icon { background: none; border: none; cursor: pointer; font-size: 18px; padding: 4px 8px; border-radius: 4px; transition: all 0.2s; }
        .fjt-btn-icon:hover { background: #e5e7eb; }
        .fjt-fb-field-edit { margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; }
        .fjt-fb-field-edit-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .fjt-fb-form-group label { display: block; font-weight: 500; margin-bottom: 5px; color: #374151; }
        .fjt-fb-form-group input[type="text"],
        .fjt-fb-form-group input[type="number"],
        .fjt-fb-form-group select,
        .fjt-fb-form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; }
        .fjt-fb-form-group small { color: #6b7280; font-size: 12px; }
        .fjt-fb-field-edit-actions { display: flex; gap: 10px; }
        .fjt-btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .fjt-btn-primary { background: #8b5cf6; color: white; }
        .fjt-btn-primary:hover { background: #7c3aed; }
        .fjt-btn-secondary { background: #e5e7eb; color: #374151; }
        .fjt-btn-secondary:hover { background: #d1d5db; }
        .fjt-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        .fjt-modal-content { background: white; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow: auto; }
        .fjt-modal-header { padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .fjt-modal-header h2 { margin: 0; }
        .fjt-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; }
        .fjt-modal-body { padding: 20px; }
        .fjt-modal-footer { padding: 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end; }
        </style>
        <?php
    }
}
