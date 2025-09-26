<?php
// save as bin/nexus_push.php and run: php bin/nexus_push.php <repo-url> <user> <pass> <version>
[$script, $repoUrl, $user, $pass, $version] = $argv + [null, null, null, null, null];
if (!$repoUrl || !$user || !$pass || !$version) {
    fwrite(STDERR, "Usage: php nexus_push.php <repoUrl> <user> <pass> <version>\n");
    exit(2);
}

// Read composer.json
$meta = json_decode(file_get_contents('composer.json'), true);
if (!isset($meta['name'])) { fwrite(STDERR, "composer.json 'name' is missing\n"); exit(2); }
$pkg = $meta['name'];

// Zip current project (skip vendor/.git/lock)
$zip = new ZipArchive();
$zipPath = sys_get_temp_dir() . '/' . str_replace('/', '-', $pkg) . '-' . $version . '.zip';
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$root = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getcwd(), FilesystemIterator::SKIP_DOTS));
foreach ($root as $file) {
    $rel = substr($file->getPathname(), strlen(getcwd()) + 1);
    if (preg_match('#^(vendor/|\.git/|composer\.lock)$#', $rel)) continue;
    if ($file->isFile()) $zip->addFile($file->getPathname(), $rel);
}
$zip->close();

// Upload via PUT to composer endpoint
$ch = curl_init();
$url = rtrim($repoUrl, '/') . '/packages/upload/' . $pkg . '/' . $version;
$fh = fopen($zipPath, 'rb');

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_USERPWD        => $user . ':' . $pass,
    CURLOPT_PUT            => true,
    CURLOPT_INFILE         => $fh,
    CURLOPT_INFILESIZE     => filesize($zipPath),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1, // avoid some HTTP/2 quirks
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$body = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
fclose($fh);

fwrite(STDOUT, "HTTP: $code\n");
if ($code >= 200 && $code < 300) {
    echo "Upload OK\n";
    exit(0);
}
if ($code === 409) {
    fwrite(STDERR, "Conflict: version already exists.\n");
} else {
    fwrite(STDERR, "Upload failed ($code): $err\nResponse: $body\n");
}
exit(1);
