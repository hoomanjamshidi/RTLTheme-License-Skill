<?php
/**
 * ممیزی گیت لایسنس راست‌چین در یک افزونه.
 *
 *   php check.php --plugin-dir=/path/to/plugin --prefix=myplug [--bootstrap=myplug-bootstrap.php]
 *       [--pkg=dist/myplug] [--exclude=demo,wordpress-theme]
 *
 * ۱) ساختار: هدر فقط در فایل اصلی، فایل لایسنس با هش ثابت، ترتیب بررسی‌ها،
 *    نبودِ کش ماندگار، بوت روی plugins_loaded.
 * ۲) هوک‌های سطح فایل: هر add_action/add_filter/add_shortcode/register_* که
 *    خارج از تابع/کلاس صدا زده شده، یا باید در فهرست مجاز باشد یا callback‌اش
 *    با «if (!prefix_license_active()) return;» شروع شود.
 * ۳) رفتار: فقط bootstrap با چند stub بار می‌شود و در حالت قفل بوت می‌شود؛
 *    هوک‌های ثبت‌شده باید دقیقاً همان مجموعهٔ مجاز حالت قفل باشند.
 * ۴) (اختیاری با --pkg) همان بررسی‌های ۱ روی بستهٔ ساخته‌شده.
 */

$o = getopt('', ['plugin-dir:', 'prefix:', 'bootstrap::', 'pkg::', 'exclude::']);
if (empty($o['plugin-dir']) || empty($o['prefix'])) {
    fwrite(STDERR, "usage: php check.php --plugin-dir=DIR --prefix=PREFIX [--bootstrap=FILE] [--pkg=DIR]\n");
    exit(2);
}

$DIR    = rtrim($o['plugin-dir'], '/');
$P      = strtolower($o['prefix']);
$C      = strtoupper($P);
$BOOT   = $DIR . '/' . ($o['bootstrap'] ?? "$P-bootstrap.php");

$R = ['pass' => 0, 'fail' => 0, 'warn' => 0];
function ok($name, $cond, $detail = '') {
    global $R;
    if ($cond) { $R['pass']++; printf("  \033[32mPASS\033[0m  %s\n", $name); }
    else { $R['fail']++; printf("  \033[31mFAIL\033[0m  %s%s\n", $name, $detail !== '' ? "\n         → $detail" : ''); }
}
function warn($name, $detail = '') {
    global $R;
    $R['warn']++;
    printf("  \033[33mWARN\033[0m  %s%s\n", $name, $detail !== '' ? "\n         → $detail" : '');
}
function group($t) { printf("\n\033[1m%s\033[0m\n", $t); }

function header_files($dir) {
    $out = [];
    foreach (glob("$dir/*.php") as $f) {
        if (str_starts_with(basename($f), 'RTL_License_')) continue;
        if (preg_match('/^[ \t\/*#@]*Plugin Name:/mi', (string) file_get_contents($f, false, null, 0, 8192))) {
            $out[] = basename($f);
        }
    }
    return $out;
}

function check_tree($dir, $boot_src, $C, $label) {
    $headers = header_files($dir);
    ok("$label: دقیقاً یک فایل سطح ریشه هدر افزونه دارد", count($headers) === 1, implode(', ', $headers) ?: 'هیچ');

    $lic = glob("$dir/RTL_License_*.php");
    ok("$label: دقیقاً یک فایل RTL_License_*.php در ریشه است", count($lic) === 1, (string) count($lic));
    if (count($lic) !== 1) return $headers[0] ?? null;

    preg_match("/define\('{$C}_LICENSE_CLASS',\s*'([^']+)'\)/", $boot_src, $cls);
    preg_match("/define\('{$C}_LICENSE_HASH',\s*'([^']+)'\)/", $boot_src, $hsh);
    ok("$label: نام کلاس لایسنس با نام فایل می‌خواند", ($cls[1] ?? '') === basename($lic[0], '.php'), ($cls[1] ?? '?') . ' vs ' . basename($lic[0]));
    ok("$label: sha1 فایل لایسنس با {$C}_LICENSE_HASH می‌خواند", ($hsh[1] ?? '') === sha1_file($lic[0]), sha1_file($lic[0]));

    $head = (string) file_get_contents($lic[0], false, null, 0, 512);
    ok("$label: فایل لایسنس انکدشدهٔ راست‌چین است (ionCube)", str_contains($head, 'Rtl-Theme') || str_contains($head, 'ionCube') || str_contains($head, 'ICB0'));

    return $headers[0] ?? null;
}

// ================================================================ ۱
group('۱) ساختار');

if (!file_exists($BOOT)) {
    ok('فایل bootstrap وجود دارد', false, $BOOT);
    exit(1);
}
$boot = file_get_contents($BOOT);
$main_name = check_tree($DIR, $boot, $C, 'سورس');
$main = $main_name ? file_get_contents("$DIR/$main_name") : '';

ok('bootstrap روی plugins_loaded می‌نشیند', (bool) preg_match("/add_action\(\s*'plugins_loaded'\s*,\s*'{$P}_boot'/", $boot));
ok('بررسی مدیریت هوشمند پیش از isActive است',
    strpos($boot, "{$P}_smart_manager_active()") !== false && strpos($boot, "{$P}_smart_manager_active()") < strpos($boot, 'isActive'));
ok('ionCube Loader پیش از require فایل لایسنس بررسی می‌شود',
    strpos($boot, "extension_loaded('ionCube Loader')") !== false
    && strpos($boot, "extension_loaded('ionCube Loader')") < strpos($boot, 'require_once $file'));
ok('هش فایل پیش از require بررسی می‌شود', strpos($boot, 'sha1_file') !== false && strpos($boot, 'sha1_file') < strpos($boot, 'require_once $file'));
ok('وضعیت لایسنس در transient/option ذخیره نمی‌شود',
    !preg_match('/(set_transient|update_option|set_site_transient)\s*\([^;]*licen/i', $boot . $main));
ok('اعلان پیشخوان پشت smart_manager_active() است، نه رشتهٔ وضعیت',
    (bool) preg_match("/if \(!{$P}_smart_manager_active\(\)\) \{\s*add_action\('admin_notices'/", $boot));
ok('زیرمنوی لایسنس فقط داخل بوت هوک می‌شود',
    !preg_match("/^add_action\(\s*'admin_menu'\s*,\s*'{$P}_rtl_license_menu'/m", $boot . "\n" . $main));
ok('bootstrap هدر افزونه ندارد', !preg_match('/Plugin Name:/i', $boot));
ok('فایل اصلی bootstrap را require می‌کند', $main_name && str_contains($main, basename($BOOT)), (string) $main_name);
ok("{$C}_MAIN_FILE در فایل اصلی تعریف شده", str_contains($main, "'{$C}_MAIN_FILE'"));
if (str_contains($boot, '__CORE_BOOT__') || str_contains($boot, 'TODO: core init')) {
    ok('جای هستهٔ افزونه در بوت پر شده', false, 'TODO هنوز در bootstrap است');
}

// ================================================================ ۲
group('۲) هوک‌های سطح فایل (خارج از تابع و کلاس)');

$allowed_top = ['plugins_loaded', 'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook'];
$hook_fns    = ['add_action', 'add_filter', 'add_shortcode', 'register_post_type', 'register_taxonomy',
                'register_block_type', 'register_rest_route', 'register_activation_hook',
                'register_deactivation_hook', 'register_uninstall_hook', 'wp_enqueue_script', 'wp_enqueue_style'];

$excluded = array_filter(array_map(fn($e) => trim($e, " /"), explode(',', $o['exclude'] ?? '')));
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($DIR, FilesystemIterator::SKIP_DOTS));
$top_issues = 0;
foreach ($it as $f) {
    $path = $f->getPathname();
    if (substr($path, -4) !== '.php') continue;
    $rel = substr($path, strlen($DIR) + 1);
    if (preg_match('~^(dist|tests?|tools|vendor|node_modules|graphify-out)/~', $rel) || str_starts_with(basename($rel), 'RTL_License_')) continue;
    if (realpath($path) === realpath($BOOT)) continue;
    foreach ($excluded as $ex) if (str_starts_with($rel, $ex . '/')) continue 2;
    // فایلی که خودِ bootstrap آن را (داخل شاخهٔ فعال) require می‌کند، پشت گیت است
    if (str_contains($boot, "'$rel'") || str_contains($boot, "/$rel'")) continue;
    // پوشهٔ یک پوستهٔ همراه (style.css با Theme Name) جزو افزونه نیست
    $top = strtok($rel, '/');
    if ($top !== $rel && glob("$DIR/$top/{,*/}style.css", GLOB_BRACE)
        && preg_grep('/Theme Name:/i', array_map(fn($f) => (string) file_get_contents($f, false, null, 0, 2048), glob("$DIR/$top/{,*/}style.css", GLOB_BRACE)))) continue;

    $tokens = token_get_all(file_get_contents($path));
    $depth  = 0;
    $stack  = [];        // نوع هر آکولاد: 'scope' (function/class) یا 'block'
    $pending = false;    // دیدن function/class تا آکولاد بعدی
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t)) {
            if (in_array($t[0], [T_FUNCTION, T_CLASS, T_TRAIT, T_INTERFACE, T_FN], true)) $pending = true;
            if (defined('T_ENUM') && $t[0] === T_ENUM) $pending = true;
            if ($t[0] === T_CURLY_OPEN || $t[0] === T_DOLLAR_OPEN_CURLY_BRACES) { $stack[] = 'block'; }
            $scopes = count(array_filter($stack, fn($s) => $s === 'scope'));
            if ($t[0] === T_STRING && $scopes === 0 && in_array(strtolower($t[1]), $hook_fns, true)) {
                // آرگومان اول را بخوان
                $j = $i + 1;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
                if (($tokens[$j] ?? null) !== '(') continue;
                // نادیده‌گرفتن تعریف متد/تابع هم‌نام یا فراخوانی متد
                $k = $i - 1;
                while ($k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k--;
                if (is_array($tokens[$k] ?? null) && in_array($tokens[$k][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) continue;

                $fn   = strtolower($t[1]);
                $line = $t[2];
                $arg1 = '';
                for ($m = $j + 1; $m < $n; $m++) {
                    if (is_array($tokens[$m]) && $tokens[$m][0] === T_WHITESPACE) continue;
                    $arg1 = is_array($tokens[$m]) ? trim($tokens[$m][1], "'\"") : $tokens[$m];
                    break;
                }
                if (in_array($fn, $allowed_top, true) || in_array($arg1, $allowed_top, true)) continue;

                // callback بسته‌ای که با گیت لایسنس شروع شود پذیرفته است
                // فقط پرانتزهای خودِ همین فراخوانی، نه فراخوانی بعدی
                $rest = '';
                $paren = 0;
                for ($m = $j; $m < $n; $m++) {
                    $tok = $tokens[$m];
                    $rest .= is_array($tok) ? $tok[1] : $tok;
                    if ($tok === '(') $paren++;
                    elseif ($tok === ')' && --$paren === 0) break;
                }
                if (preg_match("/function\s*\([^)]*\)\s*(use\s*\([^)]*\)\s*)?\{\s*if\s*\(\s*!\s*{$P}_license_active\(\)\s*\)\s*return/", $rest)) continue;

                $top_issues++;
                warn("$rel:$line  $fn('$arg1') در سطح فایل و بیرون از گیت",
                    'یا به داخل بوتِ شاخهٔ فعال منتقل شود، یا callback با if (!' . $P . '_license_active()) return; شروع شود');
            }
        } else {
            if ($t === '{') { $stack[] = $pending ? 'scope' : 'block'; $pending = false; }
            elseif ($t === '}') { array_pop($stack); }
            elseif ($t === ';') { $pending = false; }
        }
    }
}
if ($top_issues === 0) ok('هیچ هوکی بیرون از گیت ثبت نمی‌شود', true);

// ================================================================ ۳
group('۳) بوت در حالت قفل (bootstrap با stub)');

$probe = <<<'PHP'
<?php
define('ABSPATH', '/');
$GLOBALS['__f'] = []; $GLOBALS['__sc'] = []; $GLOBALS['__opt'] = json_decode($argv[3], true);
function add_action($h, $cb, $p = 10, $a = 1) { $GLOBALS['__f'][] = $h; return true; }
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['__f'][] = $h; return true; }
function add_shortcode($t, $cb) { $GLOBALS['__sc'][] = $t; }
function get_option($k, $d = false) { return $GLOBALS['__opt'][$k] ?? $d; }
function get_site_option($k, $d = false) { return $d; }
function is_multisite() { return false; }
function plugin_basename($f) { return basename(dirname($f)) . '/' . basename($f); }
function plugin_dir_path($f) { return rtrim(dirname($f), '/') . '/'; }
function admin_url($p = '') { return 'http://x/wp-admin/' . $p; }
function __($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return $s; }
function esc_url($s) { return $s; }
function current_user_can($c) { return true; }
function is_user_logged_in() { return true; }
$C = $argv[2];
define("{$C}_MAIN_FILE", $argv[4]);
define("{$C}_PLUGIN_PATH", dirname($argv[4]) . '/');
require $argv[1];
$P = strtolower($C);
foreach ($GLOBALS['__f'] as $i => $h) if ($h === 'plugins_loaded') unset($GLOBALS['__f'][$i]);
call_user_func("{$P}_boot");
echo json_encode(['status' => call_user_func("{$P}_license_status"), 'hooks' => array_values($GLOBALS['__f']), 'sc' => $GLOBALS['__sc']]);
PHP;

$tmp = sys_get_temp_dir() . '/rtl-license-probe-' . getmypid() . '.php';
file_put_contents($tmp, $probe);
$main_abs = $DIR . '/' . ($main_name ?? 'plugin.php');

foreach ([
    'بدون مدیریت هوشمند' => ['active_plugins' => []],
    'با مدیریت هوشمند، بدون لایسنس' => ['active_plugins' => ['RTL-CareUnit/RTL-CareUnit.php']],
] as $label => $opts) {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($BOOT) . ' '
        . escapeshellarg($C) . ' ' . escapeshellarg(json_encode($opts)) . ' ' . escapeshellarg($main_abs) . ' 2>&1');
    $res = json_decode((string) $out, true);
    if (!is_array($res)) { ok("$label: bootstrap بدون خطا بار می‌شود", false, trim((string) $out)); continue; }

    $manager = !empty($opts['active_plugins']);
    ok("$label: وضعیت « {$res['status']} » فعال نیست", $res['status'] !== 'active');

    $expected = ['plugin_action_links_' . plugin_basename_local($main_abs)];
    if (!$manager) { $expected[] = 'admin_notices'; $expected[] = 'network_admin_notices'; }
    $got = $res['hooks'];
    sort($expected); sort($got);
    ok("$label: فقط هوک‌های مجاز حالت قفل ثبت شده‌اند", $got === $expected,
        'got: ' . implode(', ', $got) . ' | expected: ' . implode(', ', $expected));
    if ($res['sc'] === []) warn("$label: هیچ شورت‌کد ساکتی ثبت نشده", 'اگر افزونه شورت‌کد دارد، همهٔ تگ‌ها (شامل قدیمی‌ها) باید در boot_locked ثبت شوند');
}
@unlink($tmp);

function plugin_basename_local($f) { return basename(dirname($f)) . '/' . basename($f); }

// ================================================================ ۴
if (!empty($o['pkg'])) {
    group('۴) بستهٔ ساخته‌شده');
    $pkg = rtrim($o['pkg'], '/');
    $pboot = $pkg . '/' . basename($BOOT);
    check_tree($pkg, file_exists($pboot) ? file_get_contents($pboot) : $boot, $C, 'بسته');
    foreach (['tests', 'tools', 'CLAUDE.md', '.git', 'node_modules'] as $junk) {
        ok("بسته «{$junk}» ندارد", !file_exists("$pkg/$junk"));
    }
}

printf("\n\033[1m%d passed, %d failed, %d warnings\033[0m\n", $R['pass'], $R['fail'], $R['warn']);
exit($R['fail'] === 0 ? 0 : 1);
