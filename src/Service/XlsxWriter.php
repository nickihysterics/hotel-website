<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use ZipArchive;

/** Формирует минимальный совместимый XLSX без внешней табличной библиотеки. */
final class XlsxWriter
{
    /**
     * Отправляет книгу пользователю и гарантированно удаляет временный файл.
     *
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    public function download(string $filename, array $headers, array $rows): never
    {
        $path = tempnam(sys_get_temp_dir(), 'hotel-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Не удалось подготовить файл.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать Excel-файл.');
        }
        // XLSX — ZIP-контейнер с обязательными OOXML-частями книги, связей, стилей и листа.
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Hotel" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF285A4C"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0" xfId="0"/><xf fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($headers, $rows));
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        unlink($path);
        exit;
    }

    /**
     * Собирает XML листа, оставляя числа числовыми и экранируя текст для XML.
     *
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    private function sheet(array $headers, array $rows): string
    {
        $allRows = array_merge([$headers], $rows);
        // Первая строка закреплена, чтобы заголовки оставались видны в длинном реестре.
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
        foreach ($allRows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $xml .= '<row r="' . $number . '">';
            foreach ($row as $columnIndex => $value) {
                $reference = $this->column($columnIndex + 1) . $number;
                if ($rowIndex > 0 && (is_int($value) || is_float($value))) {
                    $xml .= '<c r="' . $reference . '"><v>' . $value . '</v></c>';
                } else {
                    $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $style = $rowIndex === 0 ? ' s="1"' : '';
                    $xml .= '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData><autoFilter ref="A1:' . $this->column(count($headers)) . count($allRows) . '"/></worksheet>';
    }

    private function column(int $number): string
    {
        $result = '';
        while ($number > 0) {
            --$number;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intdiv($number, 26);
        }
        return $result;
    }
}
