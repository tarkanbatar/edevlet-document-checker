<?php

require '../vendor/autoload.php';

// Get object
$eDevlet = new \EDevlet\Dogrula();
// $eDevlet->verbose = true;

// Output version
echo sprintf('E-Devlet Belge Doğrulama %s' . "\r\n", $eDevlet->version);
echo 'Usage: php check.php [filename]' . "\r\n";

// Get arguments
$args = array_values(array_diff_key($argv, array(basename(__FILE__))));

// Check arguments
if (count($args) < 1) die('Wrong arguments passed!' . "\r\n");

// Get file
$file = $args[0];
if (!is_file($file) || !file_exists($file)) die('file not found!' . "\r\n");
echo sprintf('Belge: %s' . "\r\n", $file);

// Validate
echo sprintf('Doğrulanıyor... ');
$result = $eDevlet->dogrula($file);
var_dump($result);
echo sprintf('%s' . "\r\n", $result === true ? 'Geçerli' : ($result === false ? 'Geçersiz' : 'Bağlantı problemi lütfen tekrar deneyin'));

$adSoyad = $eDevlet->get_adSoyad();
$tcNo = $eDevlet->get_kimlikNo();

echo $adSoyad." ".$tcNo;
