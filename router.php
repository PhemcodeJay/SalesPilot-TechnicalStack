<?php
// router.php

$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$documentRoot = __DIR__;
$staticDirs = ['/assets', '/home_assets'];
$pageRoot = $documentRoot . '/pages';

// ✅ Serve static files from /assets and /home_assets
foreach ($staticDirs as $dir) {
    if (str_starts_with($request, $dir)) {
        $staticFile = $documentRoot . $request;
        if (is_file($staticFile)) {
            return false; // Let PHP serve it directly
        } else {
            http_response_code(404);
            echo "Static file not found.";
            exit;
        }
    }
}

// ✅ Normalize request (e.g., /dashboard → /pages/user/dashboard.php or .html)
$pathVariants = [
    $pageRoot . $request,                          // direct path
    $pageRoot . $request . '.php',                 // try adding .php
    $pageRoot . $request . '.html',                // try adding .html
    $pageRoot . $request . '/index.php',           // folder/index.php
    $pageRoot . $request . '/index.html'           // folder/index.html
];

// ✅ Try all possible page locations
foreach ($pathVariants as $file) {
    if (is_file($file)) {
        require $file;
        exit;
    }
}

// ❌ Not found
http_response_code(404);
echo "404 - Page Not Found";