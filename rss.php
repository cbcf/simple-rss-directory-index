<?php
/*
 * Simple RSS feed directory index
 * Published as public domain at https://github.com/cbcf/simple-rss-directory-index
 *
 * URL: https://your-server.com/non-obvious-path/rss.php?channel=SUBDIRNAME&token=SECRET
 * Where SUBDIRNAME is a folder next to this file
 * and SECRET must match the token below.
 *
 * Compatibility: Tested with PHP 8.2.1, expected to be compatible from PHP 5.6+
 */

// SET YOUR AUTH HERE
$ref_token = 'PutYourSuperSecretTokenHere';

if (!key_exists('channel', $_GET) || !key_exists('token', $_GET)) {
    http_response_code(400);
    die();
}

if ($ref_token !== $_GET['token']) {
    http_response_code(401);
    die();
}

$channels_root = realpath(dirname(__FILE__));
$channel_folders = scandir($channels_root, SCANDIR_SORT_NONE);
$channel = $_GET['channel'];

if (false === $channel_folders || !in_array($channel, $channel_folders)) {
    http_response_code(404);
    die();
}

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    $host = "https://";
else
    $host = "http://";

$host .= $_SERVER['HTTP_HOST'];

$base_url = $host . preg_replace("/\/?(rss(\.php)?)?\/?(\?.*)?$/i", "", $_SERVER['REQUEST_URI']).'/';

$self_link = $host . preg_replace("/\?.*$/i", "", $_SERVER['REQUEST_URI']).'?channel='.$channel;

$channel_root = $channels_root . DIRECTORY_SEPARATOR . $channel;
$channel_items = scandir($channel_root, SCANDIR_SORT_NONE);

$items = [];

foreach ($channel_items as $channel_item) {
    if (strlen($channel_item) < 1 || $channel_item[0] == '.') continue;
    $item_path = $channel_root.DIRECTORY_SEPARATOR.$channel_item;
    if (is_dir($item_path) || !file_exists($item_path)) continue;
    $stat = stat($item_path);
    $mtime = $stat['mtime'];
    $description = 'Size: '.$stat['size'].' Bytes';
    $link = $base_url.rawurlencode($channel).'/'.rawurlencode($channel_item);
    $items[] = [
        'title' => $channel_item,
        'link' => $link,
        'guid' => $link.'?mtime='.$mtime,
        'description' => $description,
        'pubDate' => date('D, d M Y H:i:s O', $mtime), // RFC822 wit 4digit year
        '_sort' => $mtime,
    ];
}

/* BEGIN OF RRSS FEED TEMPLATE */
// Note: The schema has been validated with https://validator.w3.org/feed/check.cgi
header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="utf-8"?>'."\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><?php echo @htmlspecialchars($channel); ?></title>
        <link><?php echo @htmlspecialchars($host); ?></link>
        <description>Simple directory index feed</description>
        <atom:link href="<?php echo htmlspecialchars($self_link); ?>" rel="self" type="application/rss+xml" />
<?php foreach ($items as $item) {
    echo "        <item>\n";
    foreach (array_keys($item) as $key) {
        if ($key[0] == '_') continue;
        echo "            <${key}>" . @htmlspecialchars($item[$key]) . "</${key}>\n";
    }
    echo "        </item>\n";
} ?>
    </channel>
</rss>
