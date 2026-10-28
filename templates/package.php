<?php
/**
 * ساخت بستهٔ توزیع برای ارسال به راست‌چین:  php tools/package.php [--out=dir] [--no-zip]
 *
 * خروجی:  dist/<slug>/            پوشهٔ آمادهٔ کپی در wp-content/plugins
 *         dist/<slug>-<نسخه>.zip
 *
 * قواعدی که هر کدام یک بار بسته را خراب کرده‌اند:
 *  - سفیدفهرست، نه سیاه‌فهرست: tests/، tools/، CLAUDE.md و zipهای قدیمی هرگز
 *    به‌طور اتفاقی وارد بسته نمی‌شوند.
 *  - RTL_License_*.php بایت‌به‌بایت کپی می‌شود؛ sha1 آن در bootstrap چک می‌شود.
 *  - هدر افزونه یک کامنت است؛ فشرده‌سازی PHP کامنت‌ها را برمی‌دارد، پس هدر
 *    دوباره برگردانده می‌شود، وگرنه افزونه در فهرست افزونه‌ها دیده نمی‌شود.
 */

/* ------------------------------------------------------------ پیکربندی */

$SLUG       = '__SLUG__';                 // نام پوشهٔ افزونه
$MAIN       = '__SLUG__.php';             // تنها فایل دارای هدر افزونه
$BOOTSTRAP  = '__PREFIX__-bootstrap.php';
$CONST      = '__CONST__';
$MINIFY_PHP = true;                       // php_strip_whitespace روی PHPها

$patterns = [
    $MAIN,
    $BOOTSTRAP,
    'index.php',
    'includes/*.php',
    'languages/*.po',
    'languages/*.mo',
    'assets/**',
    'RTL_License_*.php',
];

/* ------------------------------------------------------------------ اجرا */

$root     = dirname(__DIR__);
$opts     = getopt('', ['out::', 'no-zip']);
$out_dir  = isset($opts['out']) && $opts['out'] !== false ? rtrim($opts['out'], '/') : "$root/dist";
$make_zip = !array_key_exists('no-zip', $opts);

preg_match('/^[ \t\/*#@]*Version:\s*(\S+)/mi', file_get_contents("$root/$MAIN"), $v);
$version = $v[1] ?? '0';

if (file_exists("$root/tools/build.php")) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tools/build.php"), $rc);
    if ($rc !== 0) { fwrite(STDERR, "  ! build failed\n"); exit(1); }
}

$files = [];
foreach ($patterns as $pattern) {
    if (str_ends_with($pattern, '/**')) {
        $base = "$root/" . substr($pattern, 0, -3);
        if (!is_dir($base)) continue;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
            $files[] = substr($f->getPathname(), strlen($root) + 1);
        }
        continue;
    }
    foreach (glob("$root/$pattern") as $abs) {
        if (is_file($abs)) $files[] = substr($abs, strlen($root) + 1);
    }
}
$files = array_values(array_unique(array_filter($files, fn($f) => basename($f) !== '.DS_Store')));
sort($files);

$pkg = "$out_dir/$SLUG";
if (is_dir($pkg)) rmtree($pkg);
mkdir($pkg, 0755, true);

foreach ($files as $rel) {
    $dest = "$pkg/$rel";
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    pack_file("$root/$rel", $dest, $rel, $MINIFY_PHP);
    echo "  $rel\n";
}

/* ----------------------------------------------------------- بررسی بسته */

$problems = [];

$headers = [];
foreach (glob("$pkg/*.php") as $f) {
    if (str_starts_with(basename($f), 'RTL_License_')) continue;
    if (preg_match('/^[ \t\/*#@]*Plugin Name:/mi', (string) file_get_contents($f, false, null, 0, 8192))) $headers[] = basename($f);
}
if ($headers !== [$MAIN]) $problems[] = "فقط $MAIN باید هدر افزونه داشته باشد: " . implode(', ', $headers);

foreach ($files as $rel) {
    if (substr($rel, -4) !== '.php' || str_starts_with(basename($rel), 'RTL_License_')) continue;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$pkg/$rel") . ' 2>&1', $lint, $lrc);
    if ($lrc !== 0) $problems[] = "syntax error in $rel";
}

$boot_src = file_get_contents("$root/$BOOTSTRAP");
preg_match("/define\('{$CONST}_LICENSE_CLASS',\s*'([^']+)'\)/", $boot_src, $lc);
preg_match("/define\('{$CONST}_LICENSE_HASH',\s*'([^']+)'\)/", $boot_src, $lh);
$lic = "$pkg/" . ($lc[1] ?? '__missing__') . '.php';
if (!file_exists($lic))                         $problems[] = 'فایل لایسنس در بسته نیست';
elseif (sha1_file($lic) !== ($lh[1] ?? ''))     $problems[] = "هش فایل لایسنس با {$CONST}_LICENSE_HASH نمی‌خواند";
if (count(glob("$pkg/RTL_License_*.php")) !== 1) $problems[] = 'باید دقیقاً یک RTL_License_*.php در بسته باشد';

if ($problems) {
    foreach ($problems as $p) fwrite(STDERR, "  ✘ $p\n");
    exit(1);
}

if ($make_zip) {
    $zip = "$out_dir/$SLUG-$version.zip";
    @unlink($zip);
    exec('cd ' . escapeshellarg($out_dir) . ' && zip -rq ' . escapeshellarg(basename($zip)) . ' ' . escapeshellarg($SLUG) . ' -x "*.DS_Store"', $zo, $zrc);
    if ($zrc !== 0) { fwrite(STDERR, "  ! zip failed\n"); exit(1); }
    echo "\n  ▸ $zip\n";
}
printf("\n  ✔ v%s — %d files\n", $version, count($files));

/* ---------------------------------------------------------------- ابزار */

function pack_file($src, $dest, $rel, $minify) {
    if (!$minify || substr($rel, -4) !== '.php' || str_starts_with(basename($rel), 'RTL_License_')) {
        copy($src, $dest);
        return;
    }
    $code = php_strip_whitespace($src);
    $raw  = file_get_contents($src);
    if (preg_match('~^<\?php\s*(/\*\*.*?\*/)~s', $raw, $m) && str_contains($m[1], 'Plugin Name:')) {
        $code = preg_replace('~^<\?php~', "<?php\n" . $m[1], $code, 1);
    }
    file_put_contents($dest, $code);
}

function rmtree($dir) {
    foreach (scandir($dir) as $e) {
        if ($e === '.' || $e === '..') continue;
        is_dir("$dir/$e") ? rmtree("$dir/$e") : unlink("$dir/$e");
    }
    rmdir($dir);
}
