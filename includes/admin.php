<?php
/**
 * Admin Dashboard Handler - Complete user and analytics management
 */

if (!defined('ABSPATH')) exit;

class FJT_Admin
{
    /**
     * Render main dashboard (users list)
     */
    public static function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $stats = FJT_Database::get_user_stats();
        ?>
        <div class="wrap fjt-admin-wrap">
            <h1 class="wp-heading-inline">Fitness Tracker Dashboard</h1>
            
            <div class="fjt-admin-container">
                
                <!-- Statistics Cards -->
                <div class="fjt-stats-grid">
                    <div class="fjt-stat-card fjt-stat-primary">
                        <div class="fjt-stat-icon">👥</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>

                    <div class="fjt-stat-card fjt-stat-success">
                        <div class="fjt-stat-icon">✅</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo number_format($stats['completed']); ?></h3>
                            <p>Completed Profiles</p>
                        </div>
                    </div>

                    <div class="fjt-stat-card fjt-stat-warning">
                        <div class="fjt-stat-icon">🚫</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo number_format($stats['restricted']); ?></h3>
                            <p>Restricted Users</p>
                        </div>
                    </div>

                    <div class="fjt-stat-card fjt-stat-info">
                        <div class="fjt-stat-icon">🔥</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo number_format($stats['today_active']); ?></h3>
                            <p>Active Today</p>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="fjt-filters-section">
                    <div class="fjt-search-box">
                        <input 
                            type="text" 
                            id="userSearch" 
                            placeholder="Search by name, mobile, or email..."
                            class="fjt-search-input"
                        >
                        <button id="searchBtn" class="fjt-btn fjt-btn-primary">Search</button>
                    </div>

                    <div class="fjt-filter-buttons">
                        <button class="fjt-filter-btn active" data-filter="all">All Users</button>
                        <button class="fjt-filter-btn" data-filter="active">Active</button>
                        <button class="fjt-filter-btn" data-filter="restricted">Restricted</button>
                        <button class="fjt-filter-btn" data-filter="completed">Completed</button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="fjt-table-container">
                    <table class="fjt-table">
                        <thead>
                            <tr>
                                <th>Mobile</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Goal</th>
                                <th>Current Weight</th>
                                <th>Target Weight</th>
                                <th>Entries</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="9" class="fjt-loading"></td>
                                <td colspan="9" class="fjt-table-message">Loading users...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Render user detail page
     */
    public static function render_user_detail()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $mobile = isset($_GET['mobile']) ? sanitize_text_field($_GET['mobile']) : '';

        if (empty($mobile)) {
            echo '<div class="wrap"><h1>Invalid User</h1><a href="?page=fitness-tracker">Back to Dashboard</a></div>';
            return;
        }

        $user = FJT_Database::get_user($mobile);

        if (!$user) {
            echo '<div class="wrap"><h1>User Not Found</h1><a href="?page=fitness-tracker">Back to Dashboard</a></div>';
            return;
        }

        $entries = FJT_Database::get_user_entries($mobile);
        $profile = !empty($user['user_profile']) ? $user['user_profile'] : [];

        // Calculate current weight from latest entry
        $current_weight = null;
        if (!empty($entries)) {
            $latest_entry = end($entries);
            $current_weight = $latest_entry['weight'] ?? null;
        }
        ?>
        <div class="wrap fjt-admin-wrap">
            <div class="fjt-user-header">
                <div>
                    <h1 class="wp-heading-inline"><?php echo esc_html($user['full_name']); ?></h1>
                    <p class="fjt-user-mobile"><?php echo esc_html($user['mobile_number']); ?></p>
                </div>
                <a href="?page=fitness-tracker" class="fjt-btn fjt-btn-secondary">← Back to Dashboard</a>
            </div>

            <div class="fjt-user-container">
                
                <!-- User Info Card -->
                <div class="fjt-user-card">
                    <h2>User Information</h2>
                    
                    <div class="fjt-info-grid">
                        <div class="fjt-info-item">
                            <label>Full Name:</label>
                            <div class="fjt-editable-field" data-field="full_name">
                                <p class="fjt-display-mode"><?php echo esc_html($user['full_name']); ?></p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <input type="text" class="fjt-edit-input" value="<?php echo esc_attr($user['full_name']); ?>" />
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>

                        <div class="fjt-info-item">
                            <label>Mobile:</label>
                            <p><?php echo esc_html($user['mobile_number']); ?></p>
                        </div>

                        <div class="fjt-info-item">
                            <label>Current Weight:</label>
                            <p>
                                <?php if ($current_weight): ?>
                                    <strong style="color:#7c3aed;"><?php echo esc_html(number_format((float)$current_weight, 1)); ?> kg</strong>
                                    <span style="font-size:11px;color:#9ca3af;margin-left:6px;">(latest entry)</span>
                                <?php else: ?>
                                    <span style="color:#9ca3af;">No entries yet</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="fjt-info-item">
                            <label>Email:</label>
                            <div class="fjt-editable-field" data-field="email">
                                <p class="fjt-display-mode"><?php echo esc_html($user['email'] ?: 'Not provided'); ?></p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <input type="email" class="fjt-edit-input" value="<?php echo esc_attr($user['email']); ?>" />
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>

                        <?php 
                        $age = $profile['age'] ?? '';
                        $gender = $profile['gender'] ?? '';
                        $height = $profile['height'] ?? '';
                        ?>

                        <div class="fjt-info-item">
                            <label>Age:</label>
                            <div class="fjt-editable-field" data-field="age">
                                <p class="fjt-display-mode"><?php echo esc_html($age ?: 'Not provided'); ?></p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <input type="number" class="fjt-edit-input" value="<?php echo esc_attr($age); ?>" min="10" max="120" />
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>

                        <div class="fjt-info-item">
                            <label>Gender:</label>
                            <div class="fjt-editable-field" data-field="gender">
                                <p class="fjt-display-mode"><?php echo esc_html($gender ?: 'Not provided'); ?></p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <select class="fjt-edit-input">
                                        <option value="">Select</option>
                                        <option value="Male" <?php selected($gender, 'Male'); ?>>Male</option>
                                        <option value="Female" <?php selected($gender, 'Female'); ?>>Female</option>
                                        <option value="Other" <?php selected($gender, 'Other'); ?>>Other</option>
                                    </select>
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>

                        <div class="fjt-info-item">
                            <label>Height:</label>
                            <div class="fjt-editable-field" data-field="height">
                                <p class="fjt-display-mode"><?php echo esc_html($height ?: 'Not provided'); ?></p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <input type="text" class="fjt-edit-input" value="<?php echo esc_attr($height); ?>" placeholder="e.g. 165cm" />
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>

                        <div class="fjt-info-item">
                            <label>Goal:</label>
                            <div class="fjt-editable-field" data-field="goal">
                                <p class="fjt-display-mode"><?php echo esc_html($user['goal'] ?: 'Not set'); ?></p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <select class="fjt-edit-input">
                                        <option value="">Select</option>
                                        <option value="Weight Loss" <?php selected($user['goal'], 'Weight Loss'); ?>>Weight Loss</option>
                                        <option value="Flexibility" <?php selected($user['goal'], 'Flexibility'); ?>>Flexibility</option>
                                        <option value="General Fitness" <?php selected($user['goal'], 'General Fitness'); ?>>General Fitness</option>
                                        <option value="Pain Relief" <?php selected($user['goal'], 'Pain Relief'); ?>>Pain Relief</option>
                                    </select>
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>

                        <div class="fjt-info-item">
                            <label>Target Weight:</label>
                            <div class="fjt-editable-field" data-field="target_weight">
                                <p class="fjt-display-mode"><?php echo esc_html($user['target_weight'] ? $user['target_weight'] . ' kg' : 'Not set'); ?></p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <input type="number" class="fjt-edit-input" value="<?php echo esc_attr($user['target_weight']); ?>" min="20" max="300" step="0.1" placeholder="kg" />
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>

                        <?php 
                        // DYNAMIC: Render additional profile fields that are not core fields
                        $core_display_fields = ['age', 'gender', 'height'];
                        foreach ($profile as $field_name => $field_value): 
                            if (in_array($field_name, $core_display_fields) || empty($field_value)) continue;
                            
                            // Get field config if exists
                            $field_config = FJT_Form_Config::get_field_config($field_name);
                            $field_label = $field_config['label'] ?? ucwords(str_replace('_', ' ', $field_name));
                            $field_type = $field_config['type'] ?? 'text';
                            
                            $display_value = is_array($field_value) ? implode(', ', $field_value) : $field_value;
                        ?>
                        <div class="fjt-info-item">
                            <label><?php echo esc_html($field_label); ?>:</label>
                            <div class="fjt-editable-field" data-field="<?php echo esc_attr($field_name); ?>">
                                <p class="fjt-display-mode">
                                    <?php echo esc_html($display_value); ?>
                                    <?php if (!$field_config): ?>
                                        <span class="fjt-badge fjt-badge-warning" style="font-size: 10px; margin-left: 5px;" title="Legacy field - no longer in form config">Legacy</span>
                                    <?php endif; ?>
                                </p>
                                <div class="fjt-edit-mode" style="display:none;">
                                    <?php if ($field_type === 'select' && !empty($field_config['options'])): ?>
                                        <select class="fjt-edit-input">
                                            <option value="">Select</option>
                                            <?php foreach ($field_config['options'] as $option): ?>
                                                <option value="<?php echo esc_attr($option); ?>" <?php selected($field_value, $option); ?>>
                                                    <?php echo esc_html($option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($field_type === 'number'): ?>
                                        <input 
                                            type="number" 
                                            class="fjt-edit-input" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            min="<?php echo esc_attr($field_config['min'] ?? 0); ?>"
                                            max="<?php echo esc_attr($field_config['max'] ?? 100); ?>"
                                        />
                                    <?php else: ?>
                                        <input 
                                            type="text" 
                                            class="fjt-edit-input" 
                                            value="<?php echo esc_attr($display_value); ?>"
                                        />
                                    <?php endif; ?>
                                    <button class="fjt-btn-icon fjt-save-edit" title="Save">💾</button>
                                    <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="fjt-info-item">
                            <label>Total Entries:</label>
                            <p><?php echo esc_html($user['total_entries']); ?></p>
                        </div>

                        <div class="fjt-info-item">
                            <label>Status:</label>
                            <p><span class="fjt-badge fjt-badge-<?php echo $user['is_restricted'] ? 'danger' : 'success'; ?>">
                                <?php echo $user['is_restricted'] ? 'Restricted' : 'Active'; ?>
                            </span></p>
                        </div>

                        <div class="fjt-info-item">
                            <label>Registered:</label>
                            <p><?php echo esc_html(date('M d, Y', strtotime($user['time_created']))); ?></p>
                        </div>

                        <div class="fjt-info-item">
                            <label>Last Login:</label>
                            <p><?php echo esc_html($user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'); ?></p>
                        </div>
                    </div>

                    <!-- Edit Toggle Button -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #f3f4f6;">
                        <button id="fjtToggleEdit" class="fjt-btn fjt-btn-primary">
                            <span class="fjt-edit-toggle-text">Enable Editing</span>
                        </button>
                    </div>

                    <input type="hidden" id="fjtUserMobile" value="<?php echo esc_attr($mobile); ?>" />

                    <!-- Action Buttons -->
                    <div class="fjt-user-actions">
                        <?php if ($user['is_restricted']): ?>
                            <button class="fjt-btn fjt-btn-success" onclick="fjtRestrict('<?php echo esc_js($mobile); ?>', 0)">
                                Unrestrict User
                            </button>
                        <?php else: ?>
                            <button class="fjt-btn fjt-btn-warning" onclick="fjtRestrict('<?php echo esc_js($mobile); ?>', 1)">
                                Restrict User
                            </button>
                        <?php endif; ?>

                        <button class="fjt-btn fjt-btn-danger" onclick="fjtDeleteUser('<?php echo esc_js($mobile); ?>')">
                            Delete User
                        </button>
                    </div>
                </div>

                <!-- Progress Chart -->
                <?php if (!empty($entries)): ?>
                <div class="fjt-user-card">
                    <h2>Weight Progress</h2>
                    <canvas id="userProgressChart" height="80"></canvas>
                </div>
                <?php endif; ?>

                <!-- Entries Accordion - DYNAMIC RENDERING -->
                <div class="fjt-user-card">
                    <h2>Weight Entries (<?php echo count($entries); ?>)</h2>
                    
                    <?php if (empty($entries)): ?>
                        <p class="fjt-no-data">No entries found</p>
                    <?php else: ?>
                        <div class="fjt-entries-accordion">
                            <?php foreach (array_reverse($entries) as $index => $entry): 
                                // Safe meta handling
                                $meta = [];
                                if (!empty($entry['meta'])) {
                                    if (is_string($entry['meta'])) {
                                        $meta = json_decode($entry['meta'], true);
                                    } elseif (is_array($entry['meta'])) {
                                        $meta = $entry['meta'];
                                    }
                                }
                                if (!is_array($meta)) {
                                    $meta = [];
                                }
                            ?>
                                <div class="fjt-entry-card" data-entry-id="<?php echo $entry['id']; ?>">
                                    
                                    <!-- Accordion Header -->
                                    <div class="fjt-entry-header" onclick="fjtToggleEntryAccordion(<?php echo $entry['id']; ?>)">
                                        <div class="fjt-entry-summary">
                                            <h3>
                                                Entry #<?php echo count($entries) - $index; ?>
                                                <span class="fjt-entry-weight"><?php echo esc_html($entry['weight']); ?> kg</span>
                                            </h3>
                                            <p class="fjt-entry-date">
                                                <?php echo esc_html(date('M d, Y H:i', strtotime($entry['created_at']))); ?>
                                                <span class="fjt-badge fjt-badge-info">
                                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $entry['entry_type']))); ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="fjt-accordion-control">
                                            <button 
                                                class="fjt-btn-icon fjt-entry-edit-btn" 
                                                onclick="event.stopPropagation(); fjtToggleEntryEdit(<?php echo $entry['id']; ?>)"
                                                title="Edit Entry"
                                                data-entry-id="<?php echo $entry['id']; ?>"
                                            >
                                                ✏️
                                            </button>
                                            <span class="fjt-accordion-icon">▼</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Accordion Body (Hidden by default) -->
                                    <div class="fjt-entry-body" style="display: none;">
                                        <div class="fjt-entry-fields">
                                            
                                            <!-- Core Field: Weight (Always shown) -->
                                            <div class="fjt-info-item">
                                                <label>Weight (kg):</label>
                                                <div class="fjt-editable-field" data-field="weight" data-entry-id="<?php echo $entry['id']; ?>">
                                                    <p class="fjt-display-mode">
                                                        <strong><?php echo esc_html($entry['weight']); ?> kg</strong>
                                                    </p>
                                                    <div class="fjt-edit-mode" style="display:none;">
                                                        <input 
                                                            type="number" 
                                                            class="fjt-edit-input" 
                                                            value="<?php echo esc_attr($entry['weight']); ?>" 
                                                            min="20" 
                                                            max="300" 
                                                            step="0.1"
                                                        />
                                                        <button class="fjt-btn-icon fjt-save-entry-field" title="Save">💾</button>
                                                        <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php 
                                            // DYNAMIC: Render all meta fields (current and historical)
                                            foreach ($meta as $field_name => $field_value): 
                                                if (in_array($field_name, ['submitted_at'])) continue;
                                                
                                                // Get field config if exists
                                                $field_config = FJT_Form_Config::get_field_config($field_name);
                                                $field_label = $field_config['label'] ?? ucwords(str_replace('_', ' ', $field_name));
                                                $field_type = $field_config['type'] ?? 'text';
                                                
                                                // Format value for display
                                                $display_value = is_array($field_value) ? implode(', ', $field_value) : $field_value;
                                                
                                                if (empty($display_value) && $display_value !== '0') continue;
                                            ?>
                                            <div class="fjt-info-item">
                                                <label>
                                                    <?php echo esc_html($field_label); ?>:
                                                    <?php if (!$field_config): ?>
                                                        <span class="fjt-badge fjt-badge-warning" style="font-size: 9px; margin-left: 4px;" title="This field no longer exists in form config">Legacy</span>
                                                    <?php endif; ?>
                                                </label>
                                                <div class="fjt-editable-field" data-field="<?php echo esc_attr($field_name); ?>" data-entry-id="<?php echo $entry['id']; ?>">
                                                    <p class="fjt-display-mode">
                                                        <?php echo esc_html($display_value); ?>
                                                    </p>
                                                    <div class="fjt-edit-mode" style="display:none;">
                                                        <?php if ($field_config && $field_type === 'select' && !empty($field_config['options'])): ?>
                                                            <select class="fjt-edit-input">
                                                                <option value="">Select</option>
                                                                <?php foreach ($field_config['options'] as $option): ?>
                                                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($field_value, $option); ?>>
                                                                        <?php echo esc_html($option); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php elseif ($field_config && ($field_type === 'number' || $field_type === 'range')): ?>
                                                            <input 
                                                                type="number" 
                                                                class="fjt-edit-input" 
                                                                value="<?php echo esc_attr($field_value); ?>"
                                                                min="<?php echo esc_attr($field_config['min'] ?? 0); ?>"
                                                                max="<?php echo esc_attr($field_config['max'] ?? 100); ?>"
                                                            />
                                                        <?php else: ?>
                                                            <input 
                                                                type="text" 
                                                                class="fjt-edit-input" 
                                                                value="<?php echo esc_attr($display_value); ?>"
                                                            />
                                                        <?php endif; ?>
                                                        <button class="fjt-btn-icon fjt-save-entry-field" title="Save">💾</button>
                                                        <button class="fjt-btn-icon fjt-cancel-edit" title="Cancel">❌</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                        </div>
                                        
                                        <!-- Entry Actions -->
                                        <div class="fjt-entry-footer">
                                            <button 
                                                class="fjt-btn fjt-btn-sm fjt-btn-danger" 
                                                onclick="fjtDeleteEntry(<?php echo $entry['id']; ?>, '<?php echo esc_js($mobile); ?>')"
                                            >
                                                🗑️ Delete Entry
                                            </button>
                                        </div>
                                    </div>
                                    
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <script>
            // Store entry data for chart
            window.fjtUserEntries = <?php echo json_encode($entries); ?>;
            window.fjtTargetWeight = <?php echo json_encode($user['target_weight'] ? floatval($user['target_weight']) : null); ?>;
        </script>
        <?php
    }

    /**
     * Render analytics page
     */
    public static function render_analytics()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $stats = FJT_Database::get_user_stats();
        $users = FJT_Database::get_all_users(['limit' => 100, 'orderby' => 'time_created', 'order' => 'DESC']);
        
        // Calculate growth data
        $growth_data = self::calculate_growth_data($users);
        ?>
        <div class="wrap fjt-admin-wrap">
            <h1 class="wp-heading-inline">Analytics Dashboard</h1>
            
            <div class="fjt-admin-container">
                
                <!-- Overview Stats -->
                <div class="fjt-stats-grid">
                    <div class="fjt-stat-card fjt-stat-primary">
                        <div class="fjt-stat-icon">👥</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>

                    <div class="fjt-stat-card fjt-stat-success">
                        <div class="fjt-stat-icon">📈</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo number_format($stats['active']); ?></h3>
                            <p>Active Users</p>
                        </div>
                    </div>

                    <div class="fjt-stat-card fjt-stat-info">
                        <div class="fjt-stat-icon">🔥</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo number_format($stats['today_active']); ?></h3>
                            <p>Today's Activity</p>
                        </div>
                    </div>

                    <div class="fjt-stat-card fjt-stat-success">
                        <div class="fjt-stat-icon">✅</div>
                        <div class="fjt-stat-content">
                            <h3><?php echo $stats['total_users'] > 0 ? round(($stats['completed'] / $stats['total_users']) * 100) : 0; ?>%</h3>
                            <p>Completion Rate</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="fjt-charts-row">
                    <div class="fjt-chart-card">
                        <h2>User Growth</h2>
                        <canvas id="growthChart" height="80"></canvas>
                    </div>

                    <div class="fjt-chart-card">
                        <h2>User Status Distribution</h2>
                        <canvas id="statusChart" height="80"></canvas>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="fjt-user-card">
                    <h2>Recent Registrations</h2>
                    <div class="fjt-table-container">
                        <table class="fjt-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Goal</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($users, 0, 10) as $user): ?>
                                    <tr>
                                        <td><?php echo esc_html($user['full_name']); ?></td>
                                        <td><?php echo esc_html($user['mobile_number']); ?></td>
                                        <td><?php echo esc_html($user['goal'] ?: 'Not set'); ?></td>
                                        <td><?php echo esc_html(date('M d, Y', strtotime($user['time_created']))); ?></td>
                                        <td>
                                            <span class="fjt-badge fjt-badge-<?php echo $user['is_restricted'] ? 'danger' : 'success'; ?>">
                                                <?php echo $user['is_restricted'] ? 'Restricted' : 'Active'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?page=fitness-tracker-user&mobile=<?php echo urlencode($user['mobile_number']); ?>" class="fjt-btn fjt-btn-sm fjt-btn-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <script>
            window.fjtGrowthData = <?php echo json_encode($growth_data); ?>;
            window.fjtStatusData = {
                active: <?php echo $stats['active']; ?>,
                restricted: <?php echo $stats['restricted']; ?>,
                completed: <?php echo $stats['completed']; ?>
            };
        </script>
        <?php
    }

    /**
     * Calculate growth data for charts
     */
    private static function calculate_growth_data($users)
    {
        $data = [];
        $months = [];

        // Get last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $months[$month] = 0;
        }

        // Count users per month
        foreach ($users as $user) {
            $month = date('Y-m', strtotime($user['time_created']));
            if (isset($months[$month])) {
                $months[$month]++;
            }
        }

        // Format for chart
        foreach ($months as $month => $count) {
            $data['labels'][] = date('M Y', strtotime($month . '-01'));
            $data['values'][] = $count;
        }

        return $data;
    }
}