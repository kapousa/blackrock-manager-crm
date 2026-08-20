<?php
/**
 * Plugin Name: Black Rock - CRM Manager Override
 * Description: Baseline master CRM plugin (Reverted to stable shortcode mode).
 * Author: Black Rock Real Estate
 * Version: 4.9.0
 */

if (!defined('ABSPATH')) exit;

// Initialize Plugin Update Checker from GitHub
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/kapousa/blackrock-manager-crm/',
    __FILE__,
    'blackrock-manager-crm'
);
$myUpdateChecker->setBranch('master');

// Register Master CRM Shortcode
add_shortcode('blackrock_crm_board', 'render_blackrock_crm_shortcode');
function render_blackrock_crm_shortcode($atts) {
    if (!current_user_can('manage_options') && !current_user_can('houzez_manager')) {
        return 'Unauthorized access.';
    }

    $type = isset($_GET['crm_type']) ? sanitize_text_field($_GET['crm_type']) : 'leads';
    global $wpdb;

    ob_start(); ?>
    <div class="dashboard-content-area" style="padding: 20px; background: #fff; margin-top: 20px;">
        <h3>Master <?php echo ucfirst($type); ?> Board</h3>
        <p>Shortcode rendered successfully.</p>
    </div>
    <?php
    return ob_get_clean();
}