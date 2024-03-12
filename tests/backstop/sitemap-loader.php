<?php

/**
 * Pass the following arguments:
 * 1. the backstop sites identifier - e.g. example
 * 2. the url to the sitemap - e.g. https://www.example.com/sitemap.xml
 * 3. (optional) the mode - e.g. robots
 */
$id = $argv[1] ?? null;
$host = $argv[2] ?? null;
$mode = $argv[3] ?? null;

if (empty($id) || empty($host)) {
    echo "\033[0;31mError: Please provide the backstop sites identifier and the url to the sitemap as arguments.\033[0m" . "\n";
    exit;
}

$hostname = parse_url($host, PHP_URL_HOST);

$sitemaps[] = $host;

if ($host !== '' && $mode === 'robots') {
    $host = rtrim(str_replace('robots.txt', '', $host), '/');
    $robotsUrl = $host . '/robots.txt';

    echo "\033[0;36mLoad and parse robots.txt from: $robotsUrl\033[0m" . "\n";

    // load robots.txt
    $robots_file = file_get_contents(
        $robotsUrl,
        false,
        strpos($robotsUrl, 'ddev.site') !== false ?
            stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]) :
            null
    );

    $pattern = '/Sitemap: ([^\s]+)/';
    preg_match_all($pattern, $robots_file, $match);
    $sitemaps = $match[1];
}

$scenarios = [];
$urls = [];

$urlSegments = parse_url($host);
unset($urlSegments['path'], $urlSegments['query'], $urlSegments['fragment']);
$baseUri = build_url($urlSegments);

echo "\n";
foreach ($sitemaps as $sitemap) {
    if (strpos($sitemap, '://') === false) {

        $sitemap = $baseUri . $sitemap;
    }
    $childSitemaps = simplexml_load_string(file_get_contents($sitemap));
    if (isset($childSitemaps->sitemap)) {
        foreach ($childSitemaps->sitemap as $childSitemap) {
            if (strpos($childSitemap->loc, '://') === false) {
                $childSitemap->loc = $baseUri . $childSitemap->loc;
            }
            echo "\033[0;36mLoad and parse sitemap from:\033[0m $childSitemap->loc" . "\n";
            $sitemapData = simplexml_load_string(file_get_contents((string)$childSitemap->loc));

            foreach ($sitemapData as $entry) {
                $urls[] = (string)$entry->loc;
            }
        }
    }
}

$urls = array_unique($urls);
//sort($scenarios);

foreach ($urls as $url) {
    $label = ltrim(
        substr(
            str_replace(
                [$baseUri, 'https://', 'http://', '/', '.'],
                ['', '', '', '_', '_'],
                $url
            ),
            0,
            100
        ) . '_' . md5($url),
        '_'
    );
    $scenarios[] = [
        'label' => $label,
        'url' => $url
    ];
}

$sitesDirectory = __DIR__ . '/sites/' . $id;
if (!is_dir($sitesDirectory) && !mkdir($sitesDirectory, 0755, true) && !is_dir($sitesDirectory)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $sitesDirectory));
}

echo "\033[0;32mWrite scenarios file:\033[0m ./sites/$id/scenarios.js" . "\n";
$content = 'module.exports = ' . json_encode($scenarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ';';
file_put_contents(__DIR__ . '/sites/' . $id . '/scenarios.js', $content);


function build_url(array $parts)
{
    return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
        ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
        (isset($parts['user']) ? "{$parts['user']}" : '') .
        (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
        (isset($parts['user']) ? '@' : '') .
        (isset($parts['host']) ? "{$parts['host']}" : '') .
        (isset($parts['port']) ? ":{$parts['port']}" : '') .
        (isset($parts['path']) ? "{$parts['path']}" : '') .
        (isset($parts['query']) ? "?{$parts['query']}" : '') .
        (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
}
