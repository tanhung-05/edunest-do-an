<?php

namespace App\Actions\M04;

use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionType;
use App\Models\M04\QuestionBank;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\Tx;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * M04 (N), CR-68: nhập câu hỏi từ Excel (.xlsx) hoặc CSV theo mẫu; kiểm từng dòng trước, có lỗi thì không nhập dòng nào.
 * Cột: loai, do_kho, noi_dung, diem, goi_y, loi_giai, lua_chon_1..lua_chon_8, dap_an.
 * `dap_an`: số thứ tự lựa chọn đúng, cách nhau bằng dấu phẩy (một/nhiều đáp án); "Đúng"/"Sai" cho đúng/sai;
 * để trống với điền từ (mọi lựa chọn là đáp án chấp nhận) và sắp xếp (thứ tự lựa chọn là thứ tự đúng).
 */
final class ImportQuestions
{
    public const MAX_ROWS = 500;

    public const HEADERS = ['loai', 'do_kho', 'noi_dung', 'diem', 'goi_y', 'loi_giai',
        'lua_chon_1', 'lua_chon_2', 'lua_chon_3', 'lua_chon_4', 'lua_chon_5', 'lua_chon_6', 'lua_chon_7', 'lua_chon_8', 'dap_an'];

    public function __construct(private QuestionBankActions $questions) {}

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(User $actor, QuestionBank $bank, UploadedFile $file): array
    {
        $rows = self::read($file);
        $parsed = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            try {
                $parsed[] = self::parse($row);
            } catch (DomainRejection $e) {
                $errors[] = ['row' => $line, 'message' => $e->getMessage()];
            }
        }
        if ($rows === []) {
            $errors[] = ['row' => 1, 'message' => 'Tệp không có dòng câu hỏi nào.'];
        }
        if (count($rows) > self::MAX_ROWS) {
            $errors[] = ['row' => self::MAX_ROWS + 2, 'message' => 'Tối đa '.self::MAX_ROWS.' câu mỗi lần nhập.'];
        }
        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $errors];
        }

        Tx::run(function () use ($actor, $bank, $parsed) {
            AuthoringScope::ensure($actor, CourseLocks::course($bank->course_id));
            foreach ($parsed as $data) {
                $this->questions->createQuestion($actor, $bank, $data);
            }
        });

        return ['imported' => count($parsed), 'errors' => []];
    }

    /** @return list<array<string, string>> */
    public static function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            throw new DomainRejection('Chỉ nhận tệp .xlsx hoặc .csv.', 'file');
        }
        try {
            $reader = IOFactory::createReader($ext === 'csv' ? 'Csv' : 'Xlsx');
            if ($ext === 'csv') {
                /** @var Csv $reader */
                $reader->setInputEncoding('UTF-8');
            }
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable) {
            throw new DomainRejection('Không đọc được tệp. Hãy dùng đúng mẫu.', 'file');
        }
        $header = array_map(fn ($h) => self::cell($h), array_shift($sheet) ?? []);
        if (isset($header[0])) {
            $header[0] = ltrim($header[0], "\u{FEFF}");
        }
        if (array_slice($header, 0, count(self::HEADERS)) !== self::HEADERS) {
            throw new DomainRejection('Dòng tiêu đề không đúng mẫu.', 'file');
        }
        $rows = [];
        foreach ($sheet as $cells) {
            $values = array_map(fn ($c) => self::cell($c), array_slice($cells, 0, count(self::HEADERS)));
            if (implode('', $values) === '') {
                continue;
            }
            $rows[] = array_combine(self::HEADERS, array_pad($values, count(self::HEADERS), ''));
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{type: QuestionType, difficulty: QuestionDifficulty, stem: string, points: string|null, hint: string|null, explanation: string|null, options: list<array{content: string, is_correct: bool}>}
     */
    public static function parse(array $row): array
    {
        $type = QuestionType::tryFrom(strtoupper($row['loai']));
        if ($type === null || in_array($type, [QuestionType::NoiCap, QuestionType::DienNhieuCho], true)) {
            throw new DomainRejection('Loại câu "'.$row['loai'].'" không hợp lệ (MOT_DAP_AN, NHIEU_DAP_AN, DUNG_SAI, DIEN_TU, SAP_XEP).');
        }
        $difficulty = $row['do_kho'] === '' ? QuestionDifficulty::TrungBinh : QuestionDifficulty::tryFrom(strtoupper($row['do_kho']));
        if ($difficulty === null) {
            throw new DomainRejection('Độ khó phải là DE, TRUNG_BINH hoặc KHO.');
        }
        if ($row['noi_dung'] === '') {
            throw new DomainRejection('Thiếu nội dung câu hỏi.');
        }
        $choices = [];
        for ($i = 1; $i <= 8; $i++) {
            if ($row['lua_chon_'.$i] !== '') {
                $choices[$i] = $row['lua_chon_'.$i];
            }
        }
        if ($type === QuestionType::DungSai) {
            $truth = mb_strtolower($row['dap_an']);
            if (! in_array($truth, ['đúng', 'sai'], true)) {
                throw new DomainRejection('Câu đúng/sai cần đáp án "Đúng" hoặc "Sai".');
            }
            $options = [['content' => 'Đúng', 'is_correct' => $truth === 'đúng'], ['content' => 'Sai', 'is_correct' => $truth === 'sai']];
        } elseif (in_array($type, [QuestionType::DienTu, QuestionType::SapXep], true)) {
            $options = array_values(array_map(fn ($c) => ['content' => $c, 'is_correct' => true], $choices));
        } else {
            $correct = array_filter(array_map('trim', explode(',', $row['dap_an'])), fn ($v) => $v !== '');
            foreach ($correct as $n) {
                if (! ctype_digit($n) || ! isset($choices[(int) $n])) {
                    throw new DomainRejection('Đáp án "'.$row['dap_an'].'" không trỏ tới lựa chọn có nội dung.');
                }
            }
            $correct = array_map('intval', $correct);
            $options = [];
            foreach ($choices as $i => $c) {
                $options[] = ['content' => $c, 'is_correct' => in_array($i, $correct, true)];
            }
        }
        $options = QuestionBankActions::validateOptions($type, $options);
        $points = $row['diem'] === '' ? null : $row['diem'];
        if ($points !== null && (! is_numeric($points) || (float) $points <= 0 || (float) $points > 9999.99)) {
            throw new DomainRejection('Điểm phải là số lớn hơn 0.');
        }

        return [
            'type' => $type, 'difficulty' => $difficulty, 'stem' => $row['noi_dung'], 'points' => $points,
            'hint' => $row['goi_y'] === '' ? null : $row['goi_y'], 'explanation' => $row['loi_giai'] === '' ? null : $row['loi_giai'],
            'options' => $options,
        ];
    }

    /** Tệp mẫu .xlsx có dòng tiêu đề và ba dòng ví dụ. */
    public static function template(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('cau_hoi');
        $sheet->fromArray([
            self::HEADERS,
            ['MOT_DAP_AN', 'DE', 'Nguyên tố có ký hiệu Na là gì?', '1', '', 'Na là natri.', 'Natri', 'Nitơ', 'Neon', '', '', '', '', '', '1'],
            ['DIEN_TU', 'TRUNG_BINH', 'Công thức hóa học của nước là ___', '1', '', '', 'H2O', 'H₂O', '', '', '', '', '', '', ''],
            ['SAP_XEP', 'KHO', 'Sắp xếp theo thứ tự tăng dần số proton', '2', '', '', 'H', 'He', 'Li', '', '', '', '', '', ''],
        ]);

        return $book;
    }

    private static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(is_scalar($value) ? (string) $value : '');
    }
}
