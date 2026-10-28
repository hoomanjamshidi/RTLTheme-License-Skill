<?php
/**
 * ساخت فایل bootstrap گیت لایسنس راست‌چین برای یک افزونهٔ دلخواه.
 *
 *   php scaffold.php --plugin-dir=/path/to/plugin --prefix=myplug \
 *       --product="نام فارسی محصول" --textdomain=myplug \
 *       --menu-parent=myplug-settings --shortcodes=myplug,myplug_list \
 *       [--out=myplug-bootstrap.php] [--force]
 *
 * --prefix      پیشوند توابع (lowercase)؛ ثابت‌ها با UPPERCASE همین ساخته می‌شوند
 * --menu-parent اسلاگ منوی والدی که زیرمنوی «مدیریت لایسنس» زیرش می‌نشیند
 * --shortcodes  همهٔ تگ‌های شورت‌کد افزونه (شامل تگ‌های قدیمی) — جداشده با ویرگول
 *
 * فایل RTL_License_*.php باید از قبل در ریشهٔ افزونه باشد؛ نام کلاس و sha1 از
 * روی خودش خوانده می‌شود.
 */

$o = getopt('', ['plugin-dir:', 'prefix:', 'product:', 'textdomain:', 'menu-parent:', 'shortcodes::', 'out::', 'force']);

foreach (['plugin-dir', 'prefix', 'product', 'textdomain', 'menu-parent'] as $req) {
    if (empty($o[$req])) {
        fwrite(STDERR, "missing --$req\n");
        exit(1);
    }
}

$dir    = rtrim($o['plugin-dir'], '/');
$prefix = strtolower($o['prefix']);
$const  = strtoupper($prefix);

if (!preg_match('/^[a-z][a-z0-9_]*$/', $prefix)) {
    fwrite(STDERR, "prefix must be [a-z][a-z0-9_]*\n");
    exit(1);
}

$lic = glob("$dir/RTL_License_*.php");
if (count($lic) !== 1) {
    fwrite(STDERR, 'expected exactly one RTL_License_*.php in plugin root, found ' . count($lic) . "\n");
    exit(1);
}
$class = basename($lic[0], '.php');
$hash  = sha1_file($lic[0]);

$tags = array_values(array_filter(array_map('trim', explode(',', $o['shortcodes'] ?? ''))));
$tags_php = '[' . implode(', ', array_map(fn($t) => var_export($t, true), $tags)) . ']';

$tpl = file_get_contents(__DIR__ . '/../templates/license-bootstrap.php');
$out = strtr($tpl, [
    '__LICENSE_CLASS__' => $class,
    '__LICENSE_HASH__'  => $hash,
    '__CONST__'         => $const,
    '__PREFIX__'        => $prefix,
    '__PRODUCT__'       => addcslashes($o['product'], "'\\"),
    '__TEXTDOMAIN__'    => addcslashes($o['textdomain'], "'\\"),
    '__MENU_PARENT__'   => addcslashes($o['menu-parent'], "'\\"),
    '__SHORTCODES__'    => $tags_php,
    '__CORE_BOOT__'     => "// TODO: core init of the plugin goes here (e.g. My_Plugin::get_instance();)",
]);

$target = $dir . '/' . ($o['out'] ?? "$prefix-bootstrap.php");
if (file_exists($target) && !isset($o['force'])) {
    fwrite(STDERR, "$target exists — pass --force to overwrite\n");
    exit(1);
}
file_put_contents($target, $out);

$product_link = '';
if (preg_match('~Product Link\s*:\s*(\S+)~', (string) file_get_contents($lic[0], false, null, 0, 2048), $m)) {
    $product_link = $m[1];
}

echo "wrote   $target\n";
echo "class   $class\n";
echo "sha1    $hash\n";
if ($product_link) echo "product $product_link\n";
echo "\nmain file must define, before requiring the bootstrap:\n";
echo "  define('{$const}_MAIN_FILE', __FILE__);\n";
echo "  define('{$const}_PLUGIN_PATH', plugin_dir_path(__FILE__));\n";
echo "then replace the TODO in {$prefix}_boot() with the plugin's core init.\n";
