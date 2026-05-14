<?php

require __DIR__ . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\LegacyUrlRedirect;
use App\Http\Application;

if (!file_exists(DATA_PATH . '/site.json')) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup Required</title></head><body>';
    echo '<h1>Setup Required</h1>';
    echo '<p>The site configuration file is missing. Please create <code>app/data/site.json</code> before running the application.</p>';
    echo '</body></html>';
    exit;
}

Config::load(DATA_PATH . '/site.json');

function ensureLanguageDataDirectories(): void
{
    $supported = Config::get('supported_langs', ['en', 'cn', 'es']);
    if (!is_array($supported) || empty($supported)) {
        return;
    }
    $defaultLangDir = DATA_PATH . '/' . trim((string) Config::get('default_lang', 'en'));
    $templateDir = is_dir($defaultLangDir) ? $defaultLangDir : (DATA_PATH . '/en');
    if (!is_dir($templateDir)) {
        return;
    }

    $templateJsonFiles = glob($templateDir . '/*.json') ?: [];
    foreach ($supported as $lang) {
        $lang = trim((string) $lang);
        if ($lang === '') {
            continue;
        }
        $dir = DATA_PATH . '/' . $lang;
        $wasCreated = false;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $wasCreated = true;
        }

        if ($wasCreated) {
            foreach ($templateJsonFiles as $srcFile) {
                $name = basename($srcFile);
                $dst = $dir . '/' . $name;
                if (!is_file($dst)) {
                    @copy($srcFile, $dst);
                }
            }
        }
    }
}

ensureLanguageDataDirectories();

LegacyUrlRedirect::maybeRedirect();

Application::serveHttp();
