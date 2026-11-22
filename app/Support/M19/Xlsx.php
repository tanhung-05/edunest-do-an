<?php

namespace App\Support\M19;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Xuất bảng tính nhiều trang tính (M19). Mỗi trang là danh sách dòng; dòng đầu là tiêu đề. */
final class Xlsx
{
    /** @param array<string, list<list<string|int|float|null>>> $sheets */
    public static function build(array $sheets): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $title => $rows) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr($title, 0, 31));
            $sheet->fromArray($rows === [] ? [['(trống)']] : $rows, null, 'A1', true);
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
            foreach (range(1, max(1, count($rows[0] ?? []))) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** @param array<string, list<list<string|int|float|null>>> $sheets */
    public static function download(string $filename, array $sheets): StreamedResponse
    {
        $book = self::build($sheets);

        return response()->streamDownload(function () use ($book) {
            (new XlsxWriter($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
