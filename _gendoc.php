<?php
/**
 * Conversor sencillo Markdown -> Word (.docx) usando PhpWord.
 * Cubre el subconjunto de Markdown usado en INSTALACION-CLIENTE.md:
 * encabezados, listas, listas numeradas, checklists, tablas, bloques de
 * código, reglas horizontales, negrita e inline code.
 */
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

$src = __DIR__ . '/INSTALACION-CLIENTE.md';
$out = __DIR__ . '/INSTALACION-CLIENTE.docx';

$lines = file($src, FILE_IGNORE_NEW_LINES);

$pw = new PhpWord();
$pw->setDefaultFontName('Calibri');
$pw->setDefaultFontSize(11);

// Estilos
$pw->addTitleStyle(1, ['size' => 20, 'bold' => true, 'color' => '1F3864'], ['spaceAfter' => 200, 'spaceBefore' => 100]);
$pw->addTitleStyle(2, ['size' => 15, 'bold' => true, 'color' => '2E5496'], ['spaceAfter' => 120, 'spaceBefore' => 240]);
$pw->addTitleStyle(3, ['size' => 12.5, 'bold' => true, 'color' => '2E5496'], ['spaceAfter' => 80, 'spaceBefore' => 160]);
$codeFont = ['name' => 'Consolas', 'size' => 9.5, 'color' => '333333'];
$codeShade = ['shading' => ['fill' => 'F2F2F2'], 'spaceAfter' => 40, 'spaceBefore' => 40];

$section = $pw->addSection([
    'marginTop' => Converter::cmToTwip(2),
    'marginBottom' => Converter::cmToTwip(2),
    'marginLeft' => Converter::cmToTwip(2.2),
    'marginRight' => Converter::cmToTwip(2.2),
]);

/** Añade texto con **negrita** e `inline code` a un TextRun. */
function addRich($parent, string $text): void
{
    // Trocea por marcadores ** y `
    $tokens = preg_split('/(\*\*.*?\*\*|`[^`]*`)/', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    foreach ($tokens as $t) {
        if (strlen($t) >= 4 && substr($t, 0, 2) === '**' && substr($t, -2) === '**') {
            $parent->addText(htmlspecialchars_decode(substr($t, 2, -2)), ['bold' => true]);
        } elseif (strlen($t) >= 2 && $t[0] === '`' && substr($t, -1) === '`') {
            $parent->addText(htmlspecialchars_decode(substr($t, 1, -1)), ['name' => 'Consolas', 'size' => 9.5, 'color' => 'C7254E']);
        } else {
            $parent->addText(htmlspecialchars_decode($t));
        }
    }
}

$i = 0;
$n = count($lines);
while ($i < $n) {
    $line = $lines[$i];
    $trim = trim($line);

    // Bloque de código
    if (strpos($trim, '```') === 0) {
        $i++;
        $code = [];
        while ($i < $n && strpos(trim($lines[$i]), '```') !== 0) {
            $code[] = $lines[$i];
            $i++;
        }
        $i++; // saltar cierre
        foreach ($code as $cl) {
            $p = $section->addText($cl === '' ? ' ' : $cl, $codeFont, $codeShade);
        }
        $section->addTextBreak(1, ['size' => 4]);
        continue;
    }

    // Regla horizontal
    if ($trim === '---') {
        $section->addText('', [], ['borderBottomSize' => 6, 'borderBottomColor' => 'CCCCCC', 'spaceAfter' => 120]);
        $i++;
        continue;
    }

    // Encabezados
    if (preg_match('/^(#{1,3})\s+(.*)$/', $trim, $m)) {
        $section->addTitle(htmlspecialchars_decode(trim($m[2])), strlen($m[1]));
        $i++;
        continue;
    }

    // Tabla
    if ($trim !== '' && $trim[0] === '|') {
        $tblLines = [];
        while ($i < $n && trim($lines[$i]) !== '' && trim($lines[$i])[0] === '|') {
            $tblLines[] = trim($lines[$i]);
            $i++;
        }
        // Quitar la fila separadora (|---|---|)
        $rows = [];
        foreach ($tblLines as $idx => $tl) {
            if (preg_match('/^\|[\s:\-\|]+\|$/', $tl)) continue;
            $cells = array_map('trim', explode('|', trim($tl, '|')));
            $rows[] = $cells;
        }
        $table = $section->addTable([
            'borderSize' => 4, 'borderColor' => 'BFBFBF', 'cellMargin' => 70, 'width' => 100 * 50, 'unit' => 'pct',
        ]);
        foreach ($rows as $r => $cells) {
            $table->addRow();
            foreach ($cells as $c) {
                $cellStyle = $r === 0 ? ['bgColor' => '2E5496'] : [];
                $cell = $table->addCell(null, $cellStyle);
                $run = $cell->addTextRun();
                if ($r === 0) {
                    $run->addText(htmlspecialchars_decode($c), ['bold' => true, 'color' => 'FFFFFF']);
                } else {
                    addRich($run, $c);
                }
            }
        }
        $section->addTextBreak(1, ['size' => 6]);
        continue;
    }

    // Listas (bullets, checklists, numeradas) — render manual con sangría
    if (preg_match('/^(\s*)([-*]|\d+\.)\s+(.*)$/', $line, $m)) {
        while ($i < $n && preg_match('/^(\s*)([-*]|\d+\.)\s+(.*)$/', $lines[$i], $mm)) {
            $indent = strlen($mm[1]);
            $marker = $mm[2];
            $content = $mm[3];
            $depth = intdiv($indent, 2);
            $numbered = (bool) preg_match('/^\d+\.$/', $marker);
            $run = $section->addTextRun([
                'spaceAfter'   => 40,
                'indentation'  => ['left' => Converter::cmToTwip(0.7 * ($depth + 1)), 'hanging' => Converter::cmToTwip(0.5)],
            ]);
            if (preg_match('/^\[( |x|X)\]\s+(.*)$/', $content, $cm)) {
                // Checklist
                $run->addText($cm[1] === ' ' ? '☐  ' : '☑  ');
                addRich($run, $cm[2]);
            } else {
                $run->addText($numbered ? ($marker . '  ') : "•  ");
                addRich($run, $content);
            }
            $i++;
        }
        $section->addTextBreak(1, ['size' => 4]);
        continue;
    }

    // Cita / aviso (>)
    if (strpos($trim, '>') === 0) {
        $content = ltrim(substr($trim, 1));
        $run = $section->addTextRun(['spaceAfter' => 120]);
        $run->addText('  ');
        addRich($run, $content);
        $i++;
        continue;
    }

    // Línea en blanco
    if ($trim === '') {
        $i++;
        continue;
    }

    // Párrafo normal
    $run = $section->addTextRun(['spaceAfter' => 120]);
    addRich($run, $trim);
    $i++;
}

$pw->save($out, 'Word2007');
echo "Generado: $out (" . filesize($out) . " bytes)\n";
