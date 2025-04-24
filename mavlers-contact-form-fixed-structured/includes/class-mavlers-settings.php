<?php
/**
 * Settings management for Mavlers Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mavlers_Settings {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'mavlers-forms',
            __('Email Settings', 'mavlers-contact-form'),
            __('Email Settings', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-email-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'mavlers-forms',
            __('Email Logs', 'mavlers-contact-form'),
            __('Email Logs', 'mavlers-contact-form'),
            'manage_options',
            'mavlers-email-logs',
            array($this, 'render_logs_page')
        );
    }

    public function register_settings() {
        register_setting('mavlers_email_settings', 'mavlers_email_header');
        register_setting('mavlers_email_settings', 'mavlers_email_footer');
        register_setting('mavlers_email_settings', 'mavlers_admin_email');
        register_setting('mavlers_email_settings', 'mavlers_auto_responder_subject');
        register_setting('mavlers_email_settings', 'mavlers_auto_responder_content');
        register_setting('mavlers_email_settings', 'mavlers_log_retention_days');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Email Settings', 'mavlers-contact-form'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('mavlers_email_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Email Header', 'mavlers-contact-form'); ?></th>
                        <td>
                            <?php
                            wp_editor(
                                get_option('mavlers_email_header', ''),
                                'mavlers_email_header',
                                array(
                                    'textarea_name' => 'mavlers_email_header',
                                    'media_buttons' => true,
                                    'textarea_rows' => 5
                                )
                            );
                            ?>
                            <p class="description"><?php _e('This will appear at the top of all emails. You can include your logo and other header content.', 'mavlers-contact-form'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Email Footer', 'mavlers-contact-form'); ?></th>
                        <td>
                            <?php
                            wp_editor(
                                get_option('mavlers_email_footer', ''),
                                'mavlers_email_footer',
                                array(
                                    'textarea_name' => 'mavlers_email_footer',
                                    'media_buttons' => true,
                                    'textarea_rows' => 5
                                )
                            );
                            ?>
                            <p class="description"><?php _e('This will appear at the bottom of all emails. You can include social media links, address, and contact information.', 'mavlers-contact-form'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Admin Email', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="email" name="mavlers_admin_email" value="<?php echo esc_attr(get_option('mavlers_admin_email', get_option('admin_email'))); ?>" class="regular-text">
                            <p class="description"><?php _e('Email address where form submissions will be sent.', 'mavlers-contact-form'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto-responder Subject', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="text" name="mavlers_auto_responder_subject" value="<?php echo esc_attr(get_option('mavlers_auto_responder_subject', 'Thank you for contacting us')); ?>" class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto-responder Content', 'mavlers-contact-form'); ?></th>
                        <td>
                            <?php
                            wp_editor(
                                get_option('mavlers_auto_responder_content', ''),
                                'mavlers_auto_responder_content',
                                array(
                                    'textarea_name' => 'mavlers_auto_responder_content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10
                                )
                            );
                            ?>
                            <p class="description"><?php _e('This will be sent to users who submit the form. You can use {name} to include the user\'s name.', 'mavlers-contact-form'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Log Retention Period', 'mavlers-contact-form'); ?></th>
                        <td>
                            <input type="number" name="mavlers_log_retention_days" value="<?php echo esc_attr(get_option('mavlers_log_retention_days', 90)); ?>" class="small-text" min="1">
                            <p class="description"><?php _e('Number of days to keep email logs. Logs older than this will be automatically deleted.', 'mavlers-contact-form'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mavlers_email_logs';
        
        // Handle log deletion
        if (isset($_POST['delete_logs']) && check_admin_referer('mavlers_delete_logs')) {
            $days = intval($_POST['delete_days']);
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
            echo '<div class="notice notice-success"><p>' . __('Logs deleted successfully.', 'mavlers-contact-form') . '</p></div>';
        }

        // Get logs
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1><?php _e('Email Logs', 'mavlers-contact-form'); ?></h1>

            <div class="tablenav top">
                <form method="post" action="">
                    <?php wp_nonce_field('mavlers_delete_logs'); ?>
                    <select name="delete_days">
                        <option value="30"><?php _e('Last 30 days', 'mavlers-contact-form'); ?></option>
                        <option value="60"><?php _e('Last 60 days', 'mavlers-contact-form'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'mavlers-contact-form'); ?></option>
                    </select>
                    <input type="submit" name="delete_logs" class="button" value="<?php _e('Delete Logs', 'mavlers-contact-form'); ?>">
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'mavlers-contact-form'); ?></th>
                        <th><?php _e('Recipient', 'mavlers-contact-form'); ?></th>
                        <th><?php _e('Subject', 'mavlers-contact-form'); ?></th>
                        <th><?php _e('Status', 'mavlers-contact-form'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)) : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                                <td><?php echo esc_html($log->recipient); ?></td>
                                <td><?php echo esc_html($log->subject); ?></td>
                                <td><?php echo esc_html($log->status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php _e('No logs found.', 'mavlers-contact-form'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Initialize the settings class
Mavlers_Settings::get_instance(); 