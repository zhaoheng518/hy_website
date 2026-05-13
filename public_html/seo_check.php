<?php
declare(strict_types=1);

/**
 * Temporary SEO sampling checker for pre-launch validation.
 * Usage: open /seo_check.php in browser.
 */

header('Content-Type: text/html; charset=UTF-8');

$root = __DIR__;
$dataPath = $root . '/app/data';
$siteConfigPath = $dataPath . '/site.json';

if (!is_file($siteConfigPath)) {
    http_response_code(500);
    echo '<h1>SEO Check Failed</h1><p>Missing app/data/site.json</p>';
    exit;
}

$site = json_decode((string) file_get_contents($siteConfigPath), true);
if (!is_array($site)) {
    http_response_code(500);
    echo '<h1>SEO Check Failed</h1><p>Invalid site.json</p>';
    exit;
}

$siteUrl = rtrim((string) ($site['site_url'] ?? ''), '/');
$supportedLangs = $site['supported_langs'] ?? ['en', 'cn', 'es', 'ru', 'ar'];
if (!is_array($supportedLangs) || empty($supportedLangs)) {
    $supportedLangs = ['en', 'cn', 'es', 'ru', 'ar'];
}

function readJsonFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function normalizeUrl(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }
    $scheme = $parts['scheme'] ?? 'http';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    return $scheme . '://' . $host . $port . $path . $query;
}

function httpFetch(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "User-Agent: SEOCheckBot/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    if (!empty($headers[0]) && preg_match('#\s(\d{3})\s#', $headers[0], $m)) {
        $status = (int) $m[1];
    }
    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'headers' => $headers,
    ];
}

function extractPageMeta(string $html): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = $dom->loadHTML($html);
    libxml_clear_errors();
    if (!$ok) {
        return [
            'title' => '',
            'description' => '',
            'canonical' => [],
            'alternates' => [],
            'html_dir' => '',
        ];
    }

    $xpath = new DOMXPath($dom);

    $title = trim((string) $xpath->evaluate('string(//title)'));
    $description = trim((string) $xpath->evaluate("string(//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='description']/@content)"));

    $canonical = [];
    foreach ($xpath->query("//link[translate(@rel,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='canonical']") as $node) {
        $canonical[] = trim((string) $node->getAttribute('href'));
    }

    $alternates = [];
    foreach ($xpath->query("//link[translate(@rel,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='alternate']") as $node) {
        $hreflang = trim((string) $node->getAttribute('hreflang'));
        $href = trim((string) $node->getAttribute('href'));
        if ($hreflang !== '') {
            $alternates[$hreflang] = $href;
        }
    }

    $htmlDir = '';
    if ($dom->documentElement instanceof DOMElement) {
        $htmlDir = strtolower(trim((string) $dom->documentElement->getAttribute('dir')));
    }

    return [
        'title' => $title,
        'description' => $description,
        'canonical' => $canonical,
        'alternates' => $alternates,
        'html_dir' => $htmlDir,
    ];
}

function buildExpectedHreflangMap(string $siteUrl, array $supportedLangs, array $langConfig, string $pathWithoutLang): array
{
    $expected = [];
    foreach ($supportedLangs as $lang) {
        $lang = trim((string) $lang);
        if ($lang === '') {
            continue;
        }
        $hreflang = (string) ($langConfig[$lang]['hreflang'] ?? $lang);
        $expected[$hreflang] = $siteUrl . '/' . $lang . $pathWithoutLang;
    }
    return $expected;
}

function pickRandomSlug(array $rows, bool $publishedOnly = false): ?string
{
    $candidates = [];
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['slug'])) {
            continue;
        }
        if ($publishedOnly && (($row['status'] ?? 'published') !== 'published')) {
            continue;
        }
        $candidates[] = (string) $row['slug'];
    }
    if (empty($candidates)) {
        return null;
    }
    $idx = random_int(0, count($candidates) - 1);
    return $candidates[$idx];
}

function findBySlug(array $rows, string $slug): ?array
{
    foreach ($rows as $row) {
        if (is_array($row) && (($row['slug'] ?? '') === $slug)) {
            return $row;
        }
    }
    return null;
}

function checkPage(
    string $siteUrl,
    array $supportedLangs,
    array $langConfig,
    string $lang,
    string $type,
    string $path,
    array $localRecord,
    array $enRecord
): array {
    $url = $siteUrl . $path;
    $fetch = httpFetch($url);
    $errors = [];
    $notes = [];

    if ($fetch['status'] < 200 || $fetch['status'] >= 400) {
        $errors[] = 'HTTP status abnormal: ' . $fetch['status'];
        return [
            'path' => $path,
            'status' => 'FAIL',
            'errors' => $errors,
            'notes' => $notes,
        ];
    }

    $meta = extractPageMeta($fetch['body']);

    // TDK completeness
    if ($meta['title'] === '') {
        $errors[] = 'Missing or empty <title>.';
    }
    if ($meta['description'] === '') {
        $errors[] = 'Missing or empty meta description.';
    }

    // Fallback detection note for non-en
    if ($lang !== 'en') {
        $localTitle = trim((string) ($localRecord['seo_title'] ?? ''));
        $localDesc = trim((string) ($localRecord['seo_desc'] ?? ''));
        $enTitle = trim((string) ($enRecord['seo_title'] ?? ''));
        $enDesc = trim((string) ($enRecord['seo_desc'] ?? ''));
        if (($localTitle === '' && $enTitle !== '') || ($localDesc === '' && $enDesc !== '')) {
            if ($meta['title'] !== '' && $meta['description'] !== '') {
                $notes[] = 'Fallback check: local SEO field empty, rendered TDK not empty (fallback appears active).';
            } else {
                $errors[] = 'Fallback check failed: local SEO empty but rendered TDK is empty.';
            }
        }
    }

    // hreflang loop
    $parts = explode('/', trim($path, '/'));
    $pathWithoutLang = '';
    if (!empty($parts)) {
        array_shift($parts);
        $pathWithoutLang = empty($parts) ? '' : '/' . implode('/', $parts);
    }
    $expectedHreflang = buildExpectedHreflangMap($siteUrl, $supportedLangs, $langConfig, $pathWithoutLang);
    foreach ($expectedHreflang as $hreflang => $expectedUrl) {
        if (!isset($meta['alternates'][$hreflang])) {
            $errors[] = "Missing hreflang '{$hreflang}'.";
            continue;
        }
        $actual = $meta['alternates'][$hreflang];
        $p = parse_url($actual);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            $errors[] = "hreflang '{$hreflang}' is not absolute URL: {$actual}";
            continue;
        }
        if (normalizeUrl($actual) !== normalizeUrl($expectedUrl)) {
            $errors[] = "hreflang '{$hreflang}' mismatch. expected={$expectedUrl}, actual={$actual}";
        }
    }

    // Canonical uniqueness + self
    if (count($meta['canonical']) !== 1) {
        $errors[] = 'Canonical tag count is not 1.';
    } else {
        $canonical = $meta['canonical'][0];
        $expectedCanonical = $siteUrl . $path;
        if (normalizeUrl($canonical) !== normalizeUrl($expectedCanonical)) {
            $errors[] = "Canonical mismatch. expected={$expectedCanonical}, actual={$canonical}";
        }
    }

    // RTL for Arabic
    if ($lang === 'ar' && $meta['html_dir'] !== 'rtl') {
        $errors[] = 'Arabic page missing dir="rtl" on <html>.';
    }

    return [
        'path' => $path,
        'status' => empty($errors) ? 'PASS' : 'FAIL',
        'errors' => $errors,
        'notes' => $notes,
    ];
}

$langConfig = is_array($site['lang_config'] ?? null) ? $site['lang_config'] : [];
$results = [];

foreach ($supportedLangs as $lang) {
    $lang = trim((string) $lang);
    if ($lang === '') {
        continue;
    }

    $products = readJsonFile($dataPath . '/' . $lang . '/products.json');
    $blogs = readJsonFile($dataPath . '/' . $lang . '/blog.json');
    $enProducts = readJsonFile($dataPath . '/en/products.json');
    $enBlogs = readJsonFile($dataPath . '/en/blog.json');

    $productSlug = pickRandomSlug($products, false);
    $blogSlug = pickRandomSlug($blogs, true);

    if ($productSlug !== null) {
        $path = '/' . $lang . '/product/' . rawurlencode($productSlug);
        $localRecord = findBySlug($products, $productSlug) ?? [];
        $enRecord = findBySlug($enProducts, $productSlug) ?? [];
        $results[] = checkPage($siteUrl, $supportedLangs, $langConfig, $lang, 'product', $path, $localRecord, $enRecord);
    } else {
        $results[] = [
            'path' => '/' . $lang . '/product/{sample-missing}',
            'status' => 'FAIL',
            'errors' => ['No product sample found for this language.'],
            'notes' => [],
        ];
    }

    if ($blogSlug !== null) {
        $path = '/' . $lang . '/blog/' . rawurlencode($blogSlug);
        $localRecord = findBySlug($blogs, $blogSlug) ?? [];
        $enRecord = findBySlug($enBlogs, $blogSlug) ?? [];
        $results[] = checkPage($siteUrl, $supportedLangs, $langConfig, $lang, 'blog', $path, $localRecord, $enRecord);
    } else {
        $results[] = [
            'path' => '/' . $lang . '/blog/{sample-missing}',
            'status' => 'FAIL',
            'errors' => ['No published blog sample found for this language.'],
            'notes' => [],
        ];
    }
}

$passCount = 0;
foreach ($results as $row) {
    if (($row['status'] ?? 'FAIL') === 'PASS') {
        $passCount++;
    }
}
$total = count($results);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Sampling Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        h1 { margin: 0 0 8px; }
        .meta { margin-bottom: 18px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px; vertical-align: top; text-align: left; }
        th { background: #f9fafb; }
        .pass { color: #065f46; font-weight: 700; }
        .fail { color: #991b1b; font-weight: 700; }
        .mono { font-family: Menlo, Monaco, Consolas, monospace; font-size: 12px; }
        ul { margin: 0; padding-left: 18px; }
    </style>
</head>
<body>
    <h1>SEO Sampling Check</h1>
    <div class="meta">
        Target Site: <span class="mono"><?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?></span><br>
        Languages: <span class="mono"><?php echo htmlspecialchars(implode(', ', $supportedLangs), ENT_QUOTES, 'UTF-8'); ?></span><br>
        Result: <strong><?php echo $passCount; ?>/<?php echo $total; ?></strong> PASS
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30%;">Page Path</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 60%;">Details</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $row): ?>
            <tr>
                <td class="mono"><?php echo htmlspecialchars((string) $row['path'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="<?php echo ($row['status'] === 'PASS') ? 'pass' : 'fail'; ?>">
                    <?php echo htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td>
                    <?php if (!empty($row['errors'])): ?>
                        <ul>
                            <?php foreach ($row['errors'] as $err): ?>
                                <li><?php echo htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <span>All validations passed.</span>
                    <?php endif; ?>
                    <?php if (!empty($row['notes'])): ?>
                        <ul>
                            <?php foreach ($row['notes'] as $note): ?>
                                <li><?php echo htmlspecialchars((string) $note, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
