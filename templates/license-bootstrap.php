<?php
/**
 * Plugin bootstrap + گیت لایسنس راست‌چین.
 *
 * قاعدهٔ فروشگاه: بدون «افزونه مدیریت هوشمند راست‌چین» (RTL-CareUnit) و بدون
 * لایسنس فعال، محصول نباید هیچ قابلیتی ارائه کند — نه منوی پیشخوان، نه
 * متاباکس، نه شورت‌کد، نه بلوک گوتنبرگ، نه ویجت المنتور، نه REST API و نه
 * دارایی فرانت‌اند. پس کل هستهٔ افزونه پشت همین گیت بوت می‌شود.
 *
 * این فایل باید پیش از بسته‌بندی نهایی با RTL-Theme انکد شود.
 */

if (!defined('ABSPATH')) exit;

define('__CONST___LICENSE_CLASS', '__LICENSE_CLASS__');
define('__CONST___LICENSE_HASH', '__LICENSE_HASH__');
define('__CONST___SMART_MANAGER_URL', 'https://www.rtl-theme.com/rtl-smart-management/');
define('__CONST___INSTALL_MANUAL_URL', 'https://www.rtl-theme.com/rcu-install-manual/');

/**
 * آیا «افزونه مدیریت هوشمند راست‌چین» نصب و فعال است؟
 *
 * این گیت روی plugins_loaded اجرا می‌شود، پس فایل اصلی هر افزونهٔ فعالی
 * قبلاً بارگذاری شده و class_exists قابل اتکاست (و به نام پوشه هم وابسته
 * نیست). فهرست active_plugins فقط تکیه‌گاه دوم است.
 */
function __PREFIX___smart_manager_active() {
    if (class_exists('RtlRequired') || class_exists('RTL\RSM\Core\ServiceContainer')) return true;

    $active = (array) get_option('active_plugins', []);
    if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
        $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', [])));
    }

    foreach ($active as $plugin) {
        $plugin = (string) $plugin;
        if (strcasecmp(basename($plugin), 'RTL-CareUnit.php') === 0) return true;
        if (stripos($plugin, 'RTL-CareUnit/') === 0) return true;
    }

    return false;
}

/**
 * وضعیت لایسنس: active | no-manager | no-loader | no-license-file | inactive
 *
 * نتیجه فقط در همین درخواست کش می‌شود؛ هیچ transient یا option‌ای ذخیره
 * نمی‌شود تا غیرفعال‌شدن مدیریت هوشمند بلافاصله اثر کند.
 */
function __PREFIX___license_status() {
    static $status = null;
    if ($status !== null) return $status;

    if (!__PREFIX___smart_manager_active()) return $status = 'no-manager';

    // فایل لایسنس با ionCube انکد شده است. بدون لودر، همین require کل درخواست
    // را با exit می‌کُشد و وردپرس آن را «فتال ارور هنگام فعال‌سازی» گزارش می‌کند.
    if (!extension_loaded('ionCube Loader')) return $status = 'no-loader';

    $file = __CONST___PLUGIN_PATH . __CONST___LICENSE_CLASS . '.php';
    if (!file_exists($file) || @sha1_file($file) !== __CONST___LICENSE_HASH) return $status = 'no-license-file';

    require_once $file;

    $class = __CONST___LICENSE_CLASS;
    if (!class_exists($class) || !method_exists($class, 'isActive')) return $status = 'no-license-file';

    $license = new $class();

    return $status = ($license->{'isActive'}() === true) ? 'active' : 'inactive';
}

/**
 * تنها پرسشی که بقیهٔ کد باید بپرسد.
 */
function __PREFIX___license_active() {
    return __PREFIX___license_status() === 'active';
}

/**
 * مقصد لینک فعال‌سازی: بدون مدیریت هوشمند صفحهٔ دانلود راست‌چین، با آن صفحهٔ
 * خودِ آن افزونه در پیشخوان (اسلاگش اعلام نشده، پس از منوی ثبت‌شده پیدا
 * می‌شود) و در نبودِ آن صفحهٔ افزونه‌ها.
 */
function __PREFIX___license_page_url() {
    if (!__PREFIX___smart_manager_active()) return __CONST___SMART_MANAGER_URL;

    if (!empty($GLOBALS['menu']) && is_array($GLOBALS['menu'])) {
        foreach ($GLOBALS['menu'] as $item) {
            $slug = isset($item[2]) ? (string) $item[2] : '';
            if ($slug !== '' && preg_match('/^(rtl|rcu|rsm)([-_]|$)|smart[-_]?manage|care[-_]?unit/i', $slug)) {
                return admin_url('admin.php?page=' . $slug);
            }
        }
    }

    return admin_url('plugins.php');
}

/**
 * لینک بیرونی باید در زبانهٔ تازه باز شود و پیشخوان را ترک نکند.
 */
function __PREFIX___license_link_target($url) {
    return in_array($url, [__CONST___SMART_MANAGER_URL, __CONST___INSTALL_MANUAL_URL], true)
        ? ' target="_blank" rel="noopener noreferrer"' : '';
}

/**
 * متن حالت قفل بر اساس دلیل واقعی قفل‌بودن.
 *
 * @return array{0:string,1:string,2:string} عنوان، توضیح، برچسب دکمه
 */
function __PREFIX___license_message() {
    $title = sprintf(__('%s غیرفعال است', '__TEXTDOMAIN__'), __('__PRODUCT__', '__TEXTDOMAIN__'));

    switch (__PREFIX___license_status()) {
        case 'no-manager':
            return [$title,
                __('برای استفاده از این افزونه باید «افزونه مدیریت هوشمند راست‌چین» نصب و فعال باشد.', '__TEXTDOMAIN__'),
                __('دانلود افزونه مدیریت هوشمند راست‌چین', '__TEXTDOMAIN__')];
        case 'no-loader':
            return [$title,
                __('افزونهٔ ionCube Loader روی سرور شما فعال نیست و امکان بررسی لایسنس وجود ندارد. با پشتیبانی هاست خود تماس بگیرید.', '__TEXTDOMAIN__'),
                __('راهنمای مدیریت هوشمند', '__TEXTDOMAIN__')];
        case 'no-license-file':
            return [$title,
                __('فایل لایسنس افزونه ناقص یا دست‌کاری شده است. نسخهٔ سالم افزونه را دوباره نصب کنید.', '__TEXTDOMAIN__'),
                __('مدیریت لایسنس', '__TEXTDOMAIN__')];
        default:
            return [$title,
                __('لایسنس افزونه فعال نیست. لایسنس خود را در «افزونه مدیریت هوشمند راست‌چین» فعال کنید.', '__TEXTDOMAIN__'),
                __('فعال‌سازی لایسنس', '__TEXTDOMAIN__')];
    }
}

/**
 * اعلان پیشخوان در حالت قفل — فقط وقتی مدیریت هوشمند روشن نیست. با روشن‌بودنش
 * خودِ آن «محصول … غیرفعال می‌باشد» را بالای پیشخوان چاپ می‌کند.
 */
function __PREFIX___locked_admin_notice() {
    if (!current_user_can('activate_plugins')) return;

    $link = '<a href="' . esc_url(__CONST___INSTALL_MANUAL_URL) . '"'
          . __PREFIX___license_link_target(__CONST___INSTALL_MANUAL_URL) . '>'
          . esc_html__('این صفحه', '__TEXTDOMAIN__') . '</a>';
    ?>
    <div class="notice notice-warning">
        <p><?php
            /* translators: 1: نام محصول (پررنگ) 2: نام افزونهٔ مدیریت لایسنس (پررنگ) 3: لینک «این صفحه» به راهنمای نصب */
            printf(
                esc_html__('محصول %1$s برای فعال‌سازی نیاز به افزونه %2$s دارد که در حال حاضر بر روی سایت شما نصب/فعال نشده است. جهت راهنمایی بیشتر به %3$s مراجعه نمایید.', '__TEXTDOMAIN__'),
                '<strong>' . esc_html__('__PRODUCT__', '__TEXTDOMAIN__') . '</strong>',
                '<strong>"' . esc_html__('مدیریت لایسنس راست‌چین', '__TEXTDOMAIN__') . '"</strong>',
                $link
            );
        ?></p>
    </div>
    <?php
}

/**
 * در حالت قفل شورت‌کدها ثبت می‌مانند اما محتوایی تولید نمی‌کنند؛ فقط جلوی
 * چاپ متن خام «[tag …]» گرفته می‌شود. مدیر سایت دلیل را می‌بیند.
 */
function __PREFIX___locked_shortcode($atts = [], $content = '', $tag = '') {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) return '';

    list(, $text) = __PREFIX___license_message();

    return '<p class="__PREFIX__-license-notice">' . esc_html($text) . '</p>';
}

/**
 * لینک اقدام در صفحهٔ افزونه‌ها: در حالت قفل به‌جای «تنظیمات» مسیر فعال‌سازی.
 */
function __PREFIX___locked_action_links($links) {
    list(, , $action) = __PREFIX___license_message();

    $url = __PREFIX___license_page_url();

    array_unshift($links, '<a href="' . esc_url($url) . '"' . __PREFIX___license_link_target($url) . '>' . esc_html($action) . '</a>');

    return $links;
}

/**
 * حالت قفل: هیچ قابلیتی ثبت نمی‌شود.
 */
function __PREFIX___boot_locked() {
    foreach (__SHORTCODES__ as $tag) {
        add_shortcode($tag, '__PREFIX___locked_shortcode');
    }

    // بررسی روی smart_manager_active() است نه رشتهٔ وضعیت، چون وضعیت برای همهٔ
    // درخواست کش می‌شود ولی این تابع هر بار active_plugins را می‌خواند.
    if (!__PREFIX___smart_manager_active()) {
        add_action('admin_notices', '__PREFIX___locked_admin_notice');
        add_action('network_admin_notices', '__PREFIX___locked_admin_notice');
    }

    // plugin_action_links_* فقط برای فایلِ دارای هدر افزونه شلیک می‌شود.
    add_filter('plugin_action_links_' . plugin_basename(__CONST___MAIN_FILE), '__PREFIX___locked_action_links');
}

/**
 * بوت افزونه — روی plugins_loaded تا تشخیص مدیریت هوشمند به ترتیب بارگذاری
 * افزونه‌ها وابسته نباشد.
 */
function __PREFIX___boot() {
    if (!__PREFIX___license_active()) {
        __PREFIX___boot_locked();
        return;
    }

    // ---- هستهٔ افزونه — همهٔ add_action/add_filter/add_shortcode/register_*
    // ---- که قبلاً در سطح فایل بودند باید از این نقطه به بعد ثبت شوند.
    __CORE_BOOT__

    // زیرمنوی «مدیریت لایسنس» راست‌چین — اولویت ۹۹ تا والد قبلاً ساخته شده باشد.
    add_action('admin_menu', '__PREFIX___rtl_license_menu', 99);
}

// --------------------------------------------------------------------------------------------------- Start RTL License
/**
 * زیرمنوی «مدیریت لایسنس» راست‌چین زیر منوی خودِ افزونه.
 *
 * فقط از شاخهٔ «لایسنس فعال» هوک می‌شود: در حالت قفل نه هوکی باید بماند و نه
 * والدِ منو وجود دارد. فایل لایسنس اینجا دوباره require نمی‌شود؛ رسیدن به این
 * تابع یعنی license_status() آن را بار کرده است.
 */
function __PREFIX___rtl_license_menu() {
    $class = __CONST___LICENSE_CLASS;

    if (!class_exists($class) || !method_exists($class, 'admin_menu')) return;

    $license = new $class();
    $license->{'admin_menu'}('__MENU_PARENT__');
}
// ----------------------------------------------------------------------------------------------------- End RTL License

add_action('plugins_loaded', '__PREFIX___boot', 5);
