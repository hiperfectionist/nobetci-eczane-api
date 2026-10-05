<?php

/**
 * CLI Test Runner for Nöbetçi Eczane API
 */

$options = getopt('', ['sehir:', 'ilce:', 'refresh::', 'list::']);

$sehir = $options['sehir'] ?? null;
$ilce = $options['ilce'] ?? null;
$refresh = isset($options['refresh']);
$list = isset($options['list']);

// If not using named flags, check positional arguments
if (!$sehir && isset($argv[1]) && strpos($argv[1], '--') !== 0) {
    $sehir = $argv[1];
}
if (!$ilce && isset($argv[2]) && strpos($argv[2], '--') !== 0) {
    $ilce = $argv[2];
}

$_GET = [];
if (!empty($sehir)) $_GET['sehir'] = $sehir;
if (!empty($ilce)) $_GET['ilce'] = $ilce;
if ($refresh) $_GET['refresh'] = '1';
if ($list) $_GET['list'] = '1';

$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require __DIR__ . '/index.php';
$output = ob_get_clean();

echo $output . PHP_EOL;
