<?php
/**
 * Plugin Name: Black Rock - CRM Manager Override
 * Description: Master CRM pipelines with Dashboard navigation and detailed table views.
 * Author:  Black Rock Real Estate
 * Version: 4.4.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Initialize Plugin Update Checker from GitHub
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/kapousa/blackrock-manager-crm/',
    __FILE__,
    'blackrock-manager-crm'
);

$myUpdateChecker->setBranch('master');

// 1. Unified CSV Export Handler
add_action('template_redirect', 'handle_blackrock_crm_export', 1);

function inject_houzez_sidebar_css() {
    if (is_page('master-crm')) {
        echo '<style>
            .user-dashboard-right { width: 75% !important; float: right; }
        </style>';
    }
}

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
                      LEFT JOIN {$wpdb->prefix}posts p ON d.listing_id = p.ID
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

// 2. Render Master CRM Board (With Detailed Information)
function render_master_crm_board($type) {
    global $wpdb;
    $export_url = add_query_arg(['action' => 'export_blackrock_'.$type], home_url('/'));

    ob_start(); ?>
    <div class="user-dashboard-right">
        <div class="dashboard-content-area">
            <div class="dashboard-area">
                <div class="dashboard-header clearfix" style="margin-bottom: 30px;">
                    <div class="float-left">
                        <h2 class="title">Master <?php echo ucfirst($type); ?> Board</h2>
                    </div>
                    <div class="float-right">
                        <!-- Navigation Buttons -->
                        <a href="/user-dashboard-2/" class="btn btn-primary" style="margin-right: 10px;">Back To Dashboard</a>
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
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($name); ?></strong></td>
                                    <td>
                                        <?php echo esc_html($item->email ?? 'N/A'); ?><br>
                                        <small class="text-muted"><?php echo esc_html($item->mobile ?? 'N/A'); ?></small>
                                    </td>
                                    <td><span class="badge badge-info"><?php echo esc_html(ucfirst($item->type ?? 'General')); ?></span></td>
                                    <td><?php echo esc_html($item->source ?? ($item->source_link ?? 'Direct')); ?></td>
                                    <td><span class="badge badge-secondary"><?php echo esc_html(ucfirst($item->status ?? 'New')); ?></span></td>
                                    <td><?php echo esc_html($item->agent_name ?? 'Unassigned'); ?></td>
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
                                      LEFT JOIN {$wpdb->prefix}posts p ON e.listing_id = p.ID
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
                                      LEFT JOIN {$wpdb->prefix}posts p ON d.listing_id = p.ID
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
    </div>
    <?php return ob_get_clean();
}

// 3. Register Shortcode
add_shortcode('blackrock_crm_board', 'render_blackrock_crm_shortcode');
function render_blackrock_crm_shortcode($atts) {
    $type = isset($_GET['crm_type']) ? sanitize_text_field($_GET['crm_type']) : 'leads';
    if (!current_user_can('manage_options') && !current_user_can('houzez_manager')) return 'Unauthorized.';
    return render_master_crm_board($type);
}

// 4. Inject Sidebar Links (Leads, Deals, Inquiries, Validator) with native Font Awesome Icons
add_action('wp_footer', 'inject_blackrock_crm_links', 9999);
function inject_blackrock_crm_links() {
    if (!current_user_can('manage_options') && !current_user_can('houzez_manager')) return;
    ?>
    <script>
    (function() {
        function addLinks() {
            var headers = document.querySelectorAll('h5');
            var crmList = null;
            for (var i = 0; i < headers.length; i++) {
                if (headers[i].textContent.trim() === 'CRM') {
                    crmList = headers[i].nextElementSibling;
                    break;
                }
            }
            if (crmList && crmList.tagName === 'UL' && !document.getElementById('br-master-leads')) {
                var crmItems = [
                    { type: 'leads', label: 'All Leads', icon: 'fa fa-user' },
                    { type: 'deals', label: 'All Deals', icon: 'fa fa-briefcase' },
                    { type: 'inquiries', label: 'All Inquiries', icon: 'fa fa-envelope' }
                ];
                crmItems.forEach(function(item) {
                    var li = document.createElement('li');
                    li.id = 'br-master-' + item.type;
                    li.innerHTML = '<a href="/master-crm/?crm_type=' + item.type + '"><i class="' + item.icon + ' mr-2"></i> ' + item.label + '</a>';
                    crmList.appendChild(li);
                });

                // Add Validator page link with Font Awesome check icon
                var valLi = document.createElement('li');
                valLi.id = 'br-master-validator';
                valLi.innerHTML = '<a href="/bayut-audit-portal/"><i class="fa fa-check-circle mr-2"></i> Feed Validator</a>';
                crmList.appendChild(valLi);
            }
        }
        setInterval(addLinks, 1000);
    })();
    </script>
    <?php
}