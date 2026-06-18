<?php
/**
 * Conversor sencillo de Markdown -> Word (.docx) usando PhpWord.
 * Pensado para REQUISITOS.md (encabezados, tablas, listas, código, citas, negrita).
 *
 * Uso:  php tools/md_to_docx.php REQUISITOS.md REQUISITOS.docx
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

$input  = $argv[1] ?? (__DIR__ . '/../REQUISITOS.md');
$output = $argv[2] ?? (__DIR__ . '/../REQUISITOS.docx');

if (!is_file($input)) {
    fwrite(STDERR, "No existe el fichero de entrada: $input\n");
    exit(1);
}

$md    = file_get_contents($input);
$md    = str_replace(["\r\n", "\r"], "\n", $md);
$lines = explode("\n", $md);

$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language(\PhpOffice\PhpWord\Style\Language::ES_ES));

// ---- Estilos ----
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(11);

$phpWord->addTitleStyle(1, ['name' => 'Calibri Light', 'size' => 22, 'color' => '1F3864', 'bold' => true], ['spaceAfter' => 200]);
$phpWord->addTitleStyle(2, ['name' => 'Calibri Light', 'size' => 16, 'color' => '2E5496', 'bold' => true], ['spaceBefore' => 240, 'spaceAfter' => 120]);
$phpWord->addTitleStyle(3, ['name' => 'Calibri Light', 'size' => 13, 'color' => '2E5496', 'bold' => true], ['spaceBefore' => 180, 'spaceAfter' => 100]);

$codeFont  = ['name' => 'Consolas', 'size' => 9, 'color' => '333333'];
$codePara  = ['spaceAfter' => 120, 'spaceBefore' => 60, 'shading' => ['fill' => 'F2F2F2']];
$quoteFont = ['italic' => true, 'color' => '555555'];
$quotePara = ['spaceAfter' => 120, 'indentation' => ['left' => Converter::cmToTwip(0.6)]];

$tableStyle = [
    'borderColor' => 'BFBFBF',
    'borderSize'  => 6,
    'cellMargin'  => 60,
];
$phpWord->addTableStyle('TablaReq', $tableStyle);
$headerCellShading = ['bgColor' => '1F3864'];
$headerFont        = ['bold' => true, 'color' => 'FFFFFF'];

$section = $phpWord->addSection([
    'marginTop'    => Converter::cmToTwip(2),
    'marginBottom' => Converter::cmToTwip(2),
    'marginLeft'   => Converter::cmToTwip(2.2),
    'marginRight'  => Converter::cmToTwip(2.2),
]);

/** Añade texto con soporte de **negrita** e `inline code` a un textrun. */
function addInline($container, string $text): void
{
    // Divide por **negrita** y `código`
    $tokens = preg_split('/(\*\*.+?\*\*|`[^`]+`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    foreach ($tokens as $tok) {
        if (preg_match('/^\*\*(.+)\*\*$/s', $tok, $m)) {
            $container->addText(htmlspecialchars_decode($m[1]), ['bold' => true]);
        } elseif (preg_match('/^`(.+)`$/s', $tok, $m)) {
            $container->addText(htmlspecialchars_decode($m[1]), ['name' => 'Consolas', 'size' => 10, 'color' => 'C7254E']);
        } else {
            $container->addText(htmlspecialchars_decode($tok));
        }
    }
}

$i = 0;
$n = count($lines);
while ($i < $n) {
    $line = $lines[$i];
    $trim = trim($line);

    // --- Bloque de código ```
    if (preg_match('/^```/', $trim)) {
        $i++;
        $code = [];
        while ($i < $n && !preg_match('/^```/', trim($lines[$i]))) {
            $code[] = $lines[$i];
            $i++;
        }
        $i++; // saltar cierre
        foreach ($code as $cl) {
            $section->addText(htmlspecialchars_decode($cl) ?: ' ', $codeFont, $codePara + ['lineHeight' => 1.0]);
        }
        continue;
    }

    // --- Tabla
    if (preg_match('/^\|(.+)\|\s*$/', $line) && isset($lines[$i + 1]) && preg_match('/^\|[\s:|-]+\|\s*$/', trim($lines[$i + 1]))) {
        $headerCells = array_map('trim', explode('|', trim($line, " |")));
        $i += 2; // saltar cabecera y separador
        $rows = [];
        while ($i < $n && preg_match('/^\|(.+)\|\s*$/', $lines[$i])) {
            $rows[] = array_map('trim', explode('|', trim($lines[$i], " |")));
            $i++;
        }
        $table = $section->addTable('TablaReq');
        // cabecera
        $table->addRow();
        foreach ($headerCells as $h) {
            $cell = $table->addCell(Converter::cmToTwip(4), $headerCellShading);
            $run  = $cell->addTextRun();
            $run->addText(htmlspecialchars_decode(str_replace('**', '', $h)), $headerFont);
        }
        // filas
        foreach ($rows as $r) {
            $table->addRow();
            foreach ($headerCells as $ci => $_) {
                $cell = $table->addCell(Converter::cmToTwip(4));
                $run  = $cell->addTextRun();
                addInline($run, $r[$ci] ?? '');
            }
        }
        $section->addTextBreak(1);
        continue;
    }

    // --- Encabezados
    if (preg_match('/^(#{1,6})\s+(.*)$/', $trim, $m)) {
        $level = strlen($m[1]);
        $text  = htmlspecialchars_decode(trim($m[2]));
        $section->addTitle($text, min($level, 3));
        $i++;
        continue;
    }

    // --- Regla horizontal
    if (preg_match('/^---+$/', $trim)) {
        $section->addText('', [], ['borderBottomSize' => 6, 'borderBottomColor' => 'BFBFBF', 'spaceAfter' => 120]);
        $i++;
        continue;
    }

    // --- Cita
    if (preg_match('/^>\s?(.*)$/', $line, $m)) {
        $run = $section->addTextRun($quotePara);
        addInline($run, $m[1]);
        // estilo cursiva para todo el run: aplicamos por defecto añadiendo de nuevo si vacío
        $i++;
        continue;
    }

    // --- Lista con viñetas
    if (preg_match('/^[-*]\s+(.*)$/', $trim, $m)) {
        $run = $section->addListItemRun(0, \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED);
        addInline($run, $m[1]);
        $i++;
        continue;
    }

    // --- Lista numerada
    if (preg_match('/^\d+\.\s+(.*)$/', $trim, $m)) {
        $run = $section->addListItemRun(0, \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER);
        addInline($run, $m[1]);
        $i++;
        continue;
    }

    // --- Línea en blanco
    if ($trim === '') {
        $section->addTextBreak(1);
        $i++;
        continue;
    }

    // --- Párrafo normal
    $run = $section->addTextRun(['spaceAfter' => 120]);
    addInline($run, $trim);
    $i++;
}

$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($output);

echo "OK -> $output\n";
