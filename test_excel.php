<?php
require_once __DIR__ . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test.xlsx';
echo 'Temp dir: ' . sys_get_temp_dir() . "\n";
echo 'Writable: ' . (is_writable(sys_get_temp_dir()) ? 'SI' : 'NO') . "\n";

$excel = \avadim\FastExcelWriter\Excel::create();
$sheet = $excel->getSheet();
$sheet->writeHeader(['A' => null, 'B' => null]);
$sheet->writeRow(['hola', 'mundo']);
$excel->save($tmp);
echo 'Guardado: ' . (file_exists($tmp) ? filesize($tmp) . ' bytes' : 'NO EXISTE') . "\n";
if (file_exists($tmp)) unlink($tmp);
