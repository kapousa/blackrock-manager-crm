<?php
/**
 * Plugin Name: Black Rock - CRM Manager Override
 * Description: Master CRM pipelines with Dashboard navigation, detailed table views, custom styling, quick agent assignment, and native Houzez dashboard template integration.
 * Author: Black Rock Real Estate
 * Version: 4.9.1
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

// 1. Intercept Dashboard Page Load for Custom Views
add_action('template_redirect', 'br_crm_catch_dashboard_views', 1);
function br_crm_catch_dashboard_views() {
    if (isset($_GET['br_view']) && !empty($_GET['br_view'])) {
        add_filter('template_include', 'br_crm_load_custom_dashboard_template', 99);
    }
}

function br_crm_load_custom_dashboard_template($template) {
    // Force Houzez generic dashboard layout wrapper
    $houzez_template = locate_template('user-dashboard.php');
    if (!empty($houzez_template)) {
        return $houzez_template;
    }
    return $template;
}

// 2. Render Custom Content directly in main area
add_action('houzez_dashboard_content', 'br_render_inside_houzez_dashboard', 5);
add_action('wp_dashboard_content', 'br_render_inside_houzez_dashboard', 5);
function br_render_inside_houzez_dashboard() {
    $br_view = isset($_GET['br_view']) ? sanitize_text_field($_GET['br_view']) : '';

    if ($br_view === 'master-crm') {
        echo render_blackrock_crm_shortcode(array());
    } elseif ($br_view === 'bayut-audit-portal') {
        if (shortcode_exists('bayut_audit_portal')) {
            echo do_shortcode('[bayut_audit_portal]');
        } else {
            echo '<div class="alert alert-warning">Bayut Validator plugin is not active.</div>';
        }
    }
}

// Fallback: If theme template doesn't trigger houzez_dashboard_content, inject via the_content
add_filter('the_content', 'br_crm_override_page_content', 999);
function br_crm_override_page_content($content) {
    if (isset($_GET['br_view']) && !empty($_GET['br_view'])) {
        $br_view = sanitize_text_field($_GET['br_view']);
        if ($br_view === 'master-crm') {
            return render_blackrock_crm_shortcode(array());
        } elseif ($br_view === 'bayut-audit-portal') {
            if (shortcode_exists('bayut_audit_portal')) {
                return do_shortcode('[bayut_audit_portal]');
            }
            return '<div class="alert alert-warning">Bayut Validator plugin is not active.</div>';
        }
    }
    return $content;
}

// 3. Custom CSS for Table Views & Hiding Default Template Elements when View is Active
add_action('wp_head', 'blackrock_crm_custom_styles');
function blackrock_crm_custom_styles() {
    if (isset($_GET['br_view']) && !empty($_GET['br_view'])) {
        echo '<style>
            /* Hide default property/dashboard list items when custom CRM view is active */
            .my-property-search, .property-management-list, .dashboard-property-list,
            .board-header, .dashboard-listing-table, .pagination { display: none !important; }
        </style>';
    }
    echo '<style>
        .table-responsive { width: 100% !important; overflow-x: auto; }
        .table { width: 100% !important; margin-bottom: 1rem; color: #212529; border-collapse: collapse; }
        .table thead th { background-color: #2c3e50 !important; color: #ffffff !important; border-color: #34495e !important; padding: 12px 15px !important; font-weight: 600 !important; font-size: 14px !important; text-transform: uppercase; letter-spacing: 0.5px; }
        .table tbody td { padding: 12px 15px !important; vertical-align: middle !important; border-top: 1px solid #dee2e6 !important; }
        .badge-info { background-color: #17a2b8 !important; color: #ffffff !important; padding: 6px 12px !important; font-size: 12px !important; border-radius: 4px !important; display: inline-block !important; }
        .badge-secondary { background-color: #6c757d !important; color: #ffffff !important; padding: 6px 12px !important; }
        .badge-primary { background-color: #007bff !important; color: #ffffff !important; padding: 6px 12px !important; }
        .badge-warning { background-color: #ffc107 !important; color: #212529 !important; padding: 6px 12px !important; }
        .badge-success { background-color: #28a745 !important; color: #ffffff !important; padding: 6px 12px !important; }
    </style>';
}

// 4. AJAX Handler for Quick Lead Assignment
add_action('wp_ajax_br_assign_lead_agent', 'br_assign_lead_agent_callback');
function br_assign_lead_agent_callback() {
    if (!current_user_can('manage_options') && !current_user_can('houzez_manager')) {
        wp_send_json_error('Unauthorized permissions');
    }

    $lead_id  = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $agent_id = isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0;

    if (!$lead_id) {
        wp_send_json_error('Invalid Lead ID');
    }

    global $wpdb;
    $updated = $wpdb->update(
        "{$wpdb->prefix}houzez_crm_leads",
        array('user_id' => $agent_id),
        array('lead_id' => $lead_id),
        array('%d'),
        array('%d')
    );

    if ($updated !== false) {
        wp_send_json_success('Agent updated successfully');
    } else {
        wp_send_json_error('Failed to update agent assignment');
    }
}

// 5. Unified CSV Export Handler
add_action('template_redirect', 'handle_blackrock_crm_export', 1);
function handle_blackrock_crm_export() {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if (strpos($action, 'export_blackrock_') === 0) {
        if (!current_user_can('manage_options') && !current_user_can('houzez_manager')) wp_die('Unauthorized.');

        global $wpdb;
        $type = str_replace('export_blackrock_', '', $action);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename='.$action.'_'.date('Y-m-d').'.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        if ($type === 'inquiries') {
            fputcsv($output, array('ID', 'Contact Name', 'Email', 'Phone', 'Inquiry Type', 'Property Listing', 'Price/Budget', 'Status', 'Date'));
            $query = "SELECT e.*, l.first_name, l.last_name, l.email as lead_email, l.mobile as lead_mobile, p.post_title
                      FROM {$wpdb->prefix}houzez_crm_enquiries e
                      LEFT JOIN {$wpdb->prefix}houzez_crm_leads l ON e.lead_id = l.lead_id
                      LEFT JOIN {$wpdb->prefix}posts p ON e.listing_id = p.ID
                      ORDER BY e.enquiry_id DESC";
            $results = $wpdb->get_results($query);
            foreach ($results as $r) {
                $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
                if (empty($name)) { $name = $r->display_name ?? 'N/A'; }
                $email = $r->lead_email ?? ($r->email ?? 'N/A');
                $phone = $r->lead_mobile ?? ($r->mobile ?? 'N/A');

                fputcsv($output, array(
                    $r->enquiry_id ?? '-',
                    $name,
                    $email,
                    $phone,
                    $r->enquiry_type ?? ($r->inquiry_type ?? 'General'),
                    $r->post_title ?? 'N/A',
                    $r->price ?? ($r->min_price ? $r->min_price . ' - ' . $r->max_price : 'N/A'),
                    $r->status ?? 'New',
                    $r->time ?? 'N/A'
                ));
            }
        } elseif ($type === 'deals') {
            fputcsv($output, array('ID', 'Deal Title', 'Contact Name', 'Phone', 'Property Listing', 'Deal Value', 'Status', 'Next Action', 'Date'));
            $query = "SELECT d.*, l.first_name, l.last_name, l.mobile as lead_mobile, p.post_title
                      FROM {$wpdb->prefix}houzez_crm_deals d
                      LEFT JOIN {$wpdb->prefix}houzez_crm_leads l ON d.lead_id = l.lead_id
                      LEFT JOIN {$wpdb->posts} p ON d.listing_id = p.ID
                      ORDER BY d.deal_id DESC";
            $results = $wpdb->get_results($query);
            foreach ($results as $r) {
                $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
                if (empty($name)) { $name = $r->display_name ?? 'N/A'; }
                $phone = $r->lead_mobile ?? ($r->mobile ?? 'N/A');

                fputcsv($output, array(
                    $r->deal_id ?? '-',
                    $r->title ?? 'N/A',
                    $name,
                    $phone,
                    $r->post_title ?? 'N/A',
                    $r->deal_value ?? '0',
                    $r->status ?? 'New',
                    $r->next_action ?? 'N/A',
                    $r->time ?? 'N/A'
                ));
            }
        } else { // Leads
            fputcsv($output, array('ID', 'Name', 'Email', 'Phone', 'Type', 'Source', 'Status', 'Agent', 'Date'));
            $query = "SELECT l.*, u.display_name as agent_name
                      FROM {$wpdb->prefix}houzez_crm_leads l
                      LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                      ORDER BY l.lead_id DESC";
            $results = $wpdb->get_results($query);
            foreach ($results as $r) {
                $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
                if (empty($name)) { $name = $r->display_name ?? 'N/A'; }

                fputcsv($output, array(
                    $r->lead_id ?? '-',
                    $name,
                    $r->email ?? 'N/A',
                    $r->mobile ?? 'N/A',
                    $r->type ?? 'N/A',
                    $r->source ?? ($r->source_link ?? 'Direct'),
                    $r->status ?? 'New',
                    $r->agent_name ?? 'Unassigned',
                    $r->time ?? 'N/A'
                ));
            }
        }
        fclose($output); exit;
    }
}

// 6. Master CRM Board Renderer
function render_master_crm_board($type) {
    global $wpdb;
    $export_url = add_query_arg(['action' => 'export_blackrock_'.$type], home_url('/'));

    $agents = get_users(array(
        'role__in' => array('houzez_agent', 'administrator', 'editor', 'author', 'houzez_manager'),
        'orderby'  => 'display_name',
        'order'    => 'ASC'
    ));

    ob_start(); ?>
    <div class="dashboard-content-area" style="padding: 20px; background: #fff; margin-top: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div class="dashboard-area">
            <div class="dashboard-header clearfix" style="margin-bottom: 30px;">
                <div class="float-left">
                    <h2 class="title">Master <?php echo ucfirst($type); ?> Board</h2>
                </div>
                <div class="float-right">
                    <a href="<?php echo esc_url($export_url); ?>" class="btn btn-success">Export CSV</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <?php if ($type === 'leads'):
                        $query = "SELECT l.*, u.display_name as agent_name
                                  FROM {$wpdb->prefix}houzez_crm_leads l
                                  LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                                  ORDER BY l.lead_id DESC";
                        $data = $wpdb->get_results($query);
                    ?>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Info</th>
                                <th>Lead Type</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Assigned Agent</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data)) : foreach ($data as $item):
                                $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
                                if (empty($name)) { $name = $item->display_name ?? 'N/A'; }
                                $assigned_user_id = intval($item->user_id ?? 0);
                                $lead_type = !empty($item->type) ? ucfirst($item->type) : 'General';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($name); ?></strong></td>
                                <td>
                                    <?php echo esc_html($item->email ?? 'N/A'); ?><br>
                                    <small class="text-muted"><?php echo esc_html($item->mobile ?? 'N/A'); ?></small>
                                </td>
                                <td><span class="badge badge-info"><?php echo esc_html($lead_type); ?></span></td>
                                <td><?php echo esc_html($item->source ?? ($item->source_link ?? 'Direct')); ?></td>
                                <td><span class="badge badge-secondary"><?php echo esc_html(ucfirst($item->status ?? 'New')); ?></span></td>
                                <td>
                                    <select class="form-control br-agent-assign-select" data-lead-id="<?php echo esc_attr($item->lead_id); ?>" style="min-width: 150px; font-size: 13px;">
                                        <option value="0" <?php selected($assigned_user_id, 0); ?>>-- Unassigned --</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?php echo esc_attr($agent->ID); ?>" <?php selected($assigned_user_id, $agent->ID); ?>>
                                                <?php echo esc_html($agent->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?php echo esc_html($item->time ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="7">No leads found.</td></tr>
                            <?php endif; ?>
                        </tbody>

                    <?php elseif ($type === 'inquiries'):
                        $query = "SELECT e.*, l.first_name, l.last_name, l.email as lead_email, l.mobile as lead_mobile, p.post_title
                                  FROM {$wpdb->prefix}houzez_crm_enquiries e
                                  LEFT JOIN {$wpdb->prefix}houzez_crm_leads l ON e.lead_id = l.lead_id
                                  LEFT JOIN {$wpdb->posts} p ON e.listing_id = p.ID
                                  ORDER BY e.enquiry_id DESC";
                        $data = $wpdb->get_results($query);
                    ?>
                        <thead>
                            <tr>
                                <th>Contact Name</th>
                                <th>Inquiry Type</th>
                                <th>Property Listing</th>
                                <th>Price / Budget</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data)) : foreach ($data as $item):
                                $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
                                if (empty($name)) { $name = $item->display_name ?? 'N/A'; }
                                $email = $item->lead_email ?? ($item->email ?? 'N/A');
                                $phone = $item->lead_mobile ?? ($item->mobile ?? 'N/A');
                                $price_display = $item->price ?? ($item->min_price ? $item->min_price . ' - ' . $item->max_price : 'N/A');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($name); ?></strong><br>
                                    <small class="text-muted"><?php echo esc_html($email); ?> | <?php echo esc_html($phone); ?></small>
                                </td>
                                <td><span class="badge badge-primary"><?php echo esc_html(ucfirst($item->enquiry_type ?? ($item->inquiry_type ?? 'General'))); ?></span></td>
                                <td><strong><?php echo esc_html($item->post_title ?? 'General Inquiry'); ?></strong></td>
                                <td><?php echo esc_html($price_display); ?></td>
                                <td><span class="badge badge-warning"><?php echo esc_html(ucfirst($item->status ?? 'New')); ?></span></td>
                                <td><?php echo esc_html($item->time ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="6">No inquiries found.</td></tr>
                            <?php endif; ?>
                        </tbody>

                    <?php elseif ($type === 'deals'):
                        $query = "SELECT d.*, l.first_name, l.last_name, l.mobile as lead_mobile, p.post_title
                                  FROM {$wpdb->prefix}houzez_crm_deals d
                                  LEFT JOIN {$wpdb->prefix}houzez_crm_leads l ON d.lead_id = l.lead_id
                                  LEFT JOIN {$wpdb->posts} p ON d.listing_id = p.ID
                                  ORDER BY d.deal_id DESC";
                        $data = $wpdb->get_results($query);
                    ?>
                        <thead>
                            <tr>
                                <th>Deal Title</th>
                                <th>Contact Name</th>
                                <th>Property Listing</th>
                                <th>Deal Value</th>
                                <th>Status</th>
                                <th>Next Action</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($data)) : foreach ($data as $item):
                                $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
                                if (empty($name)) { $name = $item->display_name ?? 'N/A'; }
                                $phone = $item->lead_mobile ?? ($item->mobile ?? 'N/A');
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($item->title ?? 'N/A'); ?></strong></td>
                                <td>
                                    <?php echo esc_html($name); ?><br>
                                    <small class="text-muted"><?php echo esc_html($phone); ?></small>
                                </td>
                                <td><?php echo esc_html($item->post_title ?? 'N/A'); ?></td>
                                <td><strong><?php echo esc_html($item->deal_value ?? '0'); ?></strong></td>
                                <td><span class="badge badge-success"><?php echo esc_html(ucfirst($item->status ?? 'New')); ?></span></td>
                                <td><?php echo esc_html($item->next_action ?? '-'); ?></td>
                                <td><?php echo esc_html($item->time ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="7">No deals found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var selects = document.querySelectorAll('.br-agent-assign-select');

        selects.forEach(function(select) {
            select.addEventListener('change', function() {
                var leadId  = this.getAttribute('data-lead-id');
                var agentId = this.value;
                var selectElem = this;

                selectElem.style.borderColor = '#ffc107';

                var formData = new FormData();
                formData.append('action', 'br_assign_lead_agent');
                formData.append('lead_id', leadId);
                formData.append('agent_id', agentId);

                fetch(ajaxurl, { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        selectElem.style.borderColor = '#28a745';
                    } else {
                        selectElem.style.borderColor = '#dc3545';
                        alert('Error updating agent assignment');
                    }
                })
                .catch(function() { selectElem.style.borderColor = '#dc3545'; });
            });
        });
    });
    </script>
    <?php return ob_get_clean();
}

// 7. Register Shortcode
add_shortcode('blackrock_crm_board', 'render_blackrock_crm_shortcode');
function render_blackrock_crm_shortcode($atts) {
    $type = isset($_GET['crm_type']) ? sanitize_text_field($_GET['crm_type']) : 'leads';
    if (!current_user_can('manage_options') && !current_user_can('houzez_manager')) return 'Unauthorized.';
    return render_master_crm_board($type);
}

// 8. Inject Correct Links into Dashboard Sidebar
add_action('wp_footer', 'inject_blackrock_crm_links', 9999);
function inject_blackrock_crm_links() {
    if (!current_user_can('manage_options') && !current_user_can('houzez_manager')) return;
    ?>
    <script>
    (function() {
        function addLinks() {
            var headers = document.querySelectorAll('h5, .sidebar-heading');
            var crmList = null;
            for (var i = 0; i < headers.length; i++) {
                if (headers[i].textContent.trim() === 'CRM') {
                    crmList = headers[i].nextElementSibling;
                    break;
                }
            }
            if (crmList && crmList.tagName === 'UL' && !document.getElementById('br-master-leads')) {
                var baseUrl = '/my-properties/';
                var crmItems = [
                    { id: 'br-master-leads', url: baseUrl + '?br_view=master-crm&crm_type=leads', label: 'All Leads', icon: 'houzez-icon icon-single-neutral' },
                    { id: 'br-master-deals', url: baseUrl + '?br_view=master-crm&crm_type=deals', label: 'All Deals', icon: 'houzez-icon icon-briefcase' },
                    { id: 'br-master-inquiries', url: baseUrl + '?br_view=master-crm&crm_type=inquiries', label: 'All Inquiries', icon: 'houzez-icon icon-messages-bubble' },
                    { id: 'br-master-validator', url: baseUrl + '?br_view=bayut-audit-portal', label: 'Feed Validator', icon: 'houzez-icon icon-check-circle-1' }
                ];

                crmItems.forEach(function(item) {
                    var li = document.createElement('li');
                    li.id = item.id;
                    li.className = 'nav-item';
                    li.innerHTML = '<a class="nav-link" href="' + item.url + '"><i class="' + item.icon + ' mr-2"></i> <span>' + item.label + '</span></a>';
                    crmList.appendChild(li);
                });
            }
        }
        setInterval(addLinks, 1000);
    })();
    </script>
    <?php
}