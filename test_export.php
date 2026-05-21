<?php
require_once __DIR__ . '/src/core/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/core/DatabaseExport.php';
require_once __DIR__ . '/src/models/Factura.php';

try {
    $db   = new DatabaseExport();
    $m    = new Factura($db);
    $rows = $m->getAll([]);
    $db->close();
    echo 'Filas: ' . count($rows) . "\n";

    if (count($rows) > 0) {
        $excel = \avadim\FastExcelWriter\Excel::create();
        $sheet = $excel->getSheet();
        $sheet->writeHeader(['Col1' => null, 'Col2' => null]);
        $sheet->writeRow(['a', 'b']);
        $tmp = sys_get_temp_dir() . '/test_export.xlsx';
        $excel->save($tmp);
        echo 'Excel OK: ' . filesize($tmp) . " bytes\n";
        unlink($tmp);
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . "\n";
}
