<?php
/*
Plugin Name: Iranian License Plate Widget for Gravity Forms
Description: افزودن یک ویجت برای ورودی شماره پلاک خودروهای ایرانی به فرم‌های گراویتی.
Version: 1.5
Author: Ali Karimi | nedayeweb
Author URI: https://nedayeweb.ir
WC requires at least: 6.4
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) {
    exit; // خروج در صورت دسترسی مستقیم
}

// بارگذاری CSS و JS
function ilp_enqueue_assets() {
    wp_enqueue_style('ilp-style', plugin_dir_url(__FILE__) . 'css/style.css');
    wp_enqueue_script('ilp-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'ilp_enqueue_assets');

// بارگذاری CSS و JS برای تنظیمات ادمین
function ilp_enqueue_admin_assets() {
    wp_enqueue_style('ilp-admin-style', plugin_dir_url(__FILE__) . 'admin/style.css');
    wp_enqueue_script('ilp-admin-script', plugin_dir_url(__FILE__) . 'admin/script.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'ilp_enqueue_admin_assets');

// افزودن تنظیمات افزونه به پنل مدیریت وردپرس
add_action('admin_menu', 'ilp_add_admin_menu');
function ilp_add_admin_menu() {
    add_options_page('تنظیمات پلاک ایرانی', 'پلاک ایرانی', 'manage_options', 'ilp-settings', 'ilp_settings_page');
}

function ilp_settings_page() {
    include plugin_dir_path(__FILE__) . 'admin/settings.php';
}

// ثبت تنظیمات افزونه
add_action('admin_init', 'ilp_register_settings');
function ilp_register_settings() {
    register_setting('ilp_settings_group', 'ilp_style');
    register_setting('ilp_settings_group', 'ilp_background_color');
    register_setting('ilp_settings_group', 'ilp_border_color');
    register_setting('ilp_settings_group', 'ilp_enable_validation');
    register_setting('ilp_settings_group', 'ilp_enable_woocommerce');
    register_setting('ilp_settings_group', 'ilp_show_on_woocommerce_order');
}

// افزودن فیلد سفارشی به گراویتی فرم
add_filter('gform_add_field_buttons', 'ilp_add_license_plate_field');
function ilp_add_license_plate_field($field_groups) {
    $field_groups[] = array(
        'name'      => 'ilp_fields',
        'label'     => __('پلاک ایرانی', 'gravityforms'),
        'fields'    => array(
            array(
                'class' => 'button',
                'value' => __('پلاک خودرو', 'gravityforms'),
                'onclick' => "StartAddField('ilp');"
            )
        )
    );
    return $field_groups;
}

add_filter('gform_field_type_title', 'ilp_field_type_title');
function ilp_field_type_title($type) {
    if ($type == 'ilp') {
        return __('پلاک ایرانی', 'gravityforms');
    }
    return $type;
}

// رندر کردن فیلد
add_action('gform_field_input', 'ilp_render_license_plate_field', 10, 5);
function ilp_render_license_plate_field($input, $field, $value, $lead_id, $form_id) {
    if ($field['type'] == 'ilp') {
        $style = get_option('ilp_style', 'car');
        $class = $style == 'car' ? 'ilp-car' : ($style == 'motorcycle' ? 'ilp-motorcycle' : 'ilp-car');
        $background_color = get_option('ilp_background_color', 'white');
        $border_color = get_option('ilp_border_color', 'black');
        
        $input = '<div class="ilp-license-plate ' . esc_attr($class) . '" style="background-color:' . esc_attr($background_color) . '; border-color:' . esc_attr($border_color) . ';">
                    <input type="text" name="input_' . $field['id'] . '_1" maxlength="2" class="ilp-part" />
                    <input type="text" name="input_' . $field['id'] . '_2" maxlength="1" class="ilp-part ilp-letter" />
                    <input type="text" name="input_' . $field['id'] . '_3" maxlength="3" class="ilp-part" />
                    <input type="text" name="input_' . $field['id'] . '_4" maxlength="2" class="ilp-part" />
                    <div class="ilp-preview"></div>
                  </div>';
    }
    return $input;
}

// ذخیره داده‌ها به صورت ترکیبی
add_filter('gform_save_field_value', 'ilp_save_combined_value', 10, 4);
function ilp_save_combined_value($value, $entry, $field, $form) {
    if ($field->type == 'ilp') {
        $value = rgpost('input_' . $field['id'] . '_1') . ' ' .
                 rgpost('input_' . $field['id'] . '_2') . ' ' .
                 rgpost('input_' . $field['id'] . '_3') . ' ' .
                 rgpost('input_' . $field['id'] . '_4');
    }
    return $value;
}

// تایید صحت پلاک
add_filter('gform_field_validation', 'ilp_validate_license_plate', 10, 4);
function ilp_validate_license_plate($result, $value, $form, $field) {
    if ($field->type == 'ilp' && get_option('ilp_enable_validation', '1')) {
        if (empty($value) || !preg_match('/^[0-9]{2} [A-Za-zآ-ی] [0-9]{3} [0-9]{2}$/', $value)) {
            $result['is_valid'] = false;
            $result['message'] = __('لطفاً شماره پلاک معتبر وارد کنید.', 'gravityforms');
        }
    }
    return $result;
}

// استفاده از پلاک خودرو در فرم‌های سفارش ووکامرس
if (get_option('ilp_enable_woocommerce', '1')) {
    add_action('woocommerce_checkout_fields', 'ilp_add_license_plate_to_checkout');
    function ilp_add_license_plate_to_checkout($fields) {
        $fields['billing']['billing_license_plate'] = array(
            'type'        => 'text',
            'label'       => __('پلاک خودرو', 'woocommerce'),
            'required'    => true,
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 22,
        );
        return $fields;
    }

    // نمایش پلاک خودرو در صفحه‌ی سفارش و فاکتورهای ووکامرس
    if (get_option('ilp_show_on_woocommerce_order', '1')) {
        add_action('woocommerce_admin_order_data_after_billing_address', 'ilp_display_license_plate_in_order', 10, 1);
        add_action('woocommerce_email_customer_details', 'ilp_display_license_plate_in_email', 10, 3);
    }

    function ilp_display_license_plate_in_order($order) {
        echo '<p><strong>' . __('پلاک خودرو') . ':</strong> ' . get_post_meta($order->get_id(), '_billing_license_plate', true) . '</p>';
    }

    function ilp_display_license_plate_in_email($order, $sent_to_admin, $plain_text) {
        echo '<p><strong>' . __('پلاک خودرو') . ':</strong> ' . get_post_meta($order->get_id(), '_billing_license_plate', true) . '</p>';
    }
}

// نمایش گزارش‌ها و آمارها
add_action('admin_menu', 'ilp_add_report_menu');
function ilp_add_report_menu() {
    add_submenu_page('ilp-settings', 'گزارش‌ها و آمارها', 'گزارش‌ها و آمارها', 'manage_options', 'ilp-reports', 'ilp_reports_page');
}

function ilp_reports_page() {
    include plugin_dir_path(__FILE__) . 'admin/reports.php';
}

// فیلترهای گزارش‌ها و آمارها
function ilp_filter_reports($reports) {
    $filtered_reports = array();
    foreach ($reports as $report) {
        if (/* شرط‌های فیلتر */) {
            $filtered_reports[] = $report;
        }
    }
    return $filtered_reports;
}

// امنیت افزونه
function ilp_sanitize_inputs($input) {
    return sanitize_text_field($input);
}

add_filter('gform_pre_submission', 'ilp_sanitize_inputs');

// بهبود مستندات
function ilp_add_documentation_link($links) {
    $documentation_link = '<a href="https://example.com/documentation" target="_blank">' . __('مستندات') . '</a>';
    array_push($links, $documentation_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ilp_add_documentation_link');
