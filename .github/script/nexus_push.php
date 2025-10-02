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

$base = getcwd();

// Directory iterator with dots skipped
$dirIter = new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS);

// Filter that avoids descending into vendor/ and .git/
$filterIter = new RecursiveCallbackFilterIterator(
    $dirIter,
    function (SplFileInfo $current, $key, RecursiveDirectoryIterator $iterator) use ($base) {

        $skip = [
            '.env', 
            'vendor', 
            'test', 
            '.git', 
            '.github', 
            '.gitattributes', 
            '.gitignore', 
            'composer.lock', 
        ];

        // Normalize relative path to forward slashes
        $rel = ltrim(str_replace('\\', '/', substr($current->getPathname(), strlen($base) + 1)), '/');

        // If it's a directory named vendor or .git at this level, skip its subtree
        if ($current->isDir()) {
            $name = $current->getFilename();
            if (in_array($name, $skip)) {
                return false; // do not recurse into this directory
            }
            return true;
        }

        // Skip specific root-level files
        if (in_array($rel, $skip)) {
            return false;
        }

        return true;
    }
);

$iter = new RecursiveIteratorIterator($filterIter, RecursiveIteratorIterator::LEAVES_ONLY);

foreach ($iter as $file) {
    /** @var SplFileInfo $file */
    $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1)), '/');
    $zip->addFile($file->getPathname(), $rel);
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
