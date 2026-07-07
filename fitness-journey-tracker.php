<?php
/**
 * Plugin Name: Fitness Journey Tracker
 * Description: Interactive fitness journey tracker with fully dynamic form system, advanced session management, and comprehensive admin dashboard
 * Version: 4.0.11
 * Author: AnkushShingari
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fitness-journey-tracker
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FJT_PLUGIN_FILE', __FILE__);
define('FJT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FJT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FJT_PLUGIN_VERSION', '4.0.11');
define('FJT_USER_TABLE', 'fjt_users');
define('FJT_ENTRIES_TABLE', 'fjt_entries');
define('FJT_SESSIONS_TABLE', 'fjt_sessions');
define('FJT_FORM_CONFIG_TABLE', 'fjt_form_configs');

// Load plugin files
require_once FJT_PLUGIN_DIR . 'includes/database.php';
require_once FJT_PLUGIN_DIR . 'includes/session-manager.php';
require_once FJT_PLUGIN_DIR . 'includes/form-config.php';
require_once FJT_PLUGIN_DIR . 'includes/validation.php';
require_once FJT_PLUGIN_DIR . 'includes/rest-api.php';
require_once FJT_PLUGIN_DIR . 'includes/shortcode.php';
require_once FJT_PLUGIN_DIR . 'includes/admin.php';
require_once FJT_PLUGIN_DIR . 'includes/form-builder.php';

class Fitness_Journey_Tracker
{
    private static $instance = null;

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Activation/Deactivation
        register_activation_hook(FJT_PLUGIN_FILE, ['Fitness_Journey_Tracker', 'activate_plugin']);
        register_deactivation_hook(FJT_PLUGIN_FILE, ['Fitness_Journey_Tracker', 'deactivate_plugin']);

        // Initialize plugin
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Admin hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        
        // Session cleanup cron
        add_action('fjt_session_cleanup', [FJT_Session_Manager::class, 'cleanup_expired_sessions']);
        if (!wp_next_scheduled('fjt_session_cleanup')) {
            wp_schedule_event(time(), 'daily', 'fjt_session_cleanup');
        }
    }

    public static function activate_plugin()
    {
        require_once FJT_PLUGIN_DIR . 'includes/database.php';
        FJT_Database::create_tables();
        
        // Initialize default form configs if not exists
        require_once FJT_PLUGIN_DIR . 'includes/form-config.php';
        FJT_Form_Config::initialize_default_configs();
    }

    public static function deactivate_plugin()
    {
        wp_clear_scheduled_hook('fjt_session_cleanup');
    }

    public function init()
    {
        // Register shortcode
        add_shortcode('fitness_tracker', [FJT_Shortcode::class, 'render']);

        // Register AJAX endpoints
        FJT_REST_API::register_endpoints();
    }

    public function enqueue_frontend_assets()
    {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'fitness_tracker')) {
            return;
        }

        // Tailwind CSS
        wp_enqueue_script(
            'fjt-tailwind',
            'https://cdn.tailwindcss.com',
            [],
            null,
            false
        );

        // Chart.js
        wp_enqueue_script(
            'fjt-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );

        // Plugin CSS
        wp_enqueue_style(
            'fjt-styles',
            FJT_PLUGIN_URL . 'assets/css/style.css',
            [],
            FJT_PLUGIN_VERSION
        );

        // Plugin JS
        wp_enqueue_script(
            'fjt-app',
            FJT_PLUGIN_URL . 'assets/js/app.js',
            ['jquery', 'fjt-chartjs'],
            FJT_PLUGIN_VERSION,
            true
        );

        // Localize script
        wp_localize_script('fjt-app', 'fjtData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fjt_nonce'),
            'pluginUrl' => FJT_PLUGIN_URL,
            'formConfig' => FJT_Form_Config::get_all_form_configs(),
        ]);
    }

    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'fitness-tracker') === false) {
            return;
        }

        // Tailwind CSS
        wp_enqueue_script(
            'fjt-admin-tailwind',
            'https://cdn.tailwindcss.com',
            [],
            null,
            false
        );

        // Chart.js
        wp_enqueue_script(
            'fjt-admin-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Sortable.js for drag and drop
        wp_enqueue_script(
            'fjt-sortable',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
            [],
            '1.15.0',
            true
        );

        // Admin CSS
        wp_enqueue_style(
            'fjt-admin-styles',
            FJT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            FJT_PLUGIN_VERSION
        );

        // Admin JS
        wp_enqueue_script(
            'fjt-admin-app',
            FJT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'fjt-admin-chartjs', 'fjt-sortable'],
            FJT_PLUGIN_VERSION,
            true
        );

        wp_localize_script('fjt-admin-app', 'fjtAdminData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fjt_admin_nonce'),
            'formConfig' => FJT_Form_Config::get_all_form_configs(),
        ]);
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'Fitness Tracker',
            'Fitness Tracker',
            'manage_options',
            'fitness-tracker',
            [FJT_Admin::class, 'render_dashboard'],
            'dashicons-heart',
            26
        );

        add_submenu_page(
            'fitness-tracker',
            'All Users',
            'All Users',
            'manage_options',
            'fitness-tracker',
            [FJT_Admin::class, 'render_dashboard']
        );

        add_submenu_page(
            'fitness-tracker',
            'View User',
            null, // Hide from menu
            'manage_options',
            'fitness-tracker-user',
            [FJT_Admin::class, 'render_user_detail']
        );
        
        add_submenu_page(
            'fitness-tracker',
            'Analytics',
            'Analytics',
            'manage_options',
            'fitness-tracker-analytics',
            [FJT_Admin::class, 'render_analytics']
        );
        
        add_submenu_page(
            'fitness-tracker',
            'Form Builder',
            'Form Builder',
            'manage_options',
            'fitness-tracker-form-builder',
            [FJT_Form_Builder::class, 'render_page']
        );
    }
}

// Initialize plugin
Fitness_Journey_Tracker::get_instance();
