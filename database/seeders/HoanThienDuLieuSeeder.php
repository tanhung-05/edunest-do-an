<?php

namespace Database\Seeders;

use App\Actions\M03\AssignCourseAuthoring;
use App\Actions\M05\EditDraftContent;
use App\Actions\M05\VersionTransitions;
use App\Actions\M07\CancelOrder;
use App\Actions\M07\MarkPaymentRefunded;
use App\Actions\M07\PlaceOrder;
use App\Actions\M07\ReconcilePayment;
use App\Actions\M07\StartPayment;
use App\Actions\M08\GrantOrderItem;
use App\Actions\M12\ManageCart;
use App\Actions\M13\ActivationCodes;
use App\Actions\M13\RedeemActivationCode;
use App\Actions\M14\CourseRatings;
use App\Actions\M17\Discussions;
use App\Actions\M22\FlashcardReviews;
use App\Actions\M22\GameSessions;
use App\Actions\M28\BankTransfers;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M03\SaleStatus;
use App\Enums\M05\LessonType;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Enums\M07\PaymentEventOutcome;
use App\Enums\M07\PaymentEventResult;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Enums\M09\Activity;
use App\Enums\M15\NotificationKind;
use App\Enums\M18\ExamStatus;
use App\Enums\M22\GameKind;
use App\Enums\M27\PostTopic;
use App\Models\M01\Role;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use App\Models\M02\TeacherProfile;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M08\Enrollment;
use App\Models\M13\ActivationCode;
use App\Models\M13\ActivationCodeBatch;
use App\Models\M14\CourseRating;
use App\Models\M17\Discussion;
use App\Models\M18\ExamEvent;
use App\Models\M22\FlashcardSet;
use App\Models\M22\GameCard;
use App\Models\User;
use App\Notifications\EduNotification;
use App\Support\M07\PaymentConfirmation;
use App\Support\M09\LearningAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hoàn thiện dữ liệu cho buổi báo cáo (22/11): tên người thật, email nhận được thư (mailinator, hộp thư công khai),
 * thêm học viên mua khóa, đánh giá, và phủ các trạng thái còn thiếu của từng phân hệ. Chạy sau các seeder demo,
 * trên cơ sở dữ liệu mới (`php artisan migrate:fresh --seed`). Mỗi phần chạy độc lập; phần lỗi chỉ cảnh báo.
 */
class HoanThienDuLieuSeeder extends Seeder
{
    /** Email cũ => [họ tên, email mới]. */
    private const NGUOI_DUNG = [
        'quang@edunest.test' => ['Lê Minh Quang', 'quang.le.edunest@mailinator.com'],
        'dung@edunest.test' => ['Phạm Tiến Dũng', 'dung.pham.edunest@mailinator.com'],
        'lan.pham@edunest.test' => ['Phạm Thị Lan', 'lan.pham.edunest@mailinator.com'],
        'minh@edunest.test' => ['Ngô Đức Minh', 'minh.ngo.edunest@mailinator.com'],
        'hoa.tran@edunest.test' => ['Trần Minh Hóa', 'hoa.tran.edunest@mailinator.com'],
        'binh.gv@edunest.test' => ['Đỗ Văn Bình', 'binh.do.edunest@mailinator.com'],
        'thu.ha@edunest.test' => ['Nguyễn Thu Hà', 'ha.nguyen.edunest@mailinator.com'],
        'hoang.nam@edunest.test' => ['Lê Hoàng Nam', 'nam.le.edunest@mailinator.com'],
        'ngoc.mai@edunest.test' => ['Phạm Ngọc Mai', 'mai.pham.edunest@mailinator.com'],
        'duc.thanh@edunest.test' => ['Võ Đức Thành', 'thanh.vo.edunest@mailinator.com'],
        'an.nguyen@edunest.test' => ['Nguyễn Văn An', 'an.nguyen.2007@mailinator.com'],
        'chi.le@edunest.test' => ['Lê Khánh Chi', 'chi.le.2007@mailinator.com'],
        'phu.huynh@edunest.test' => ['Nguyễn Văn Phúc', 'phuc.nguyen.ph@mailinator.com'],
        'dang-hoc.p4@edunest.test' => ['Nguyễn Hoàng Long', 'long.nguyen.2007@mailinator.com'],
        'het-han.p4@edunest.test' => ['Lê Thu Trang', 'trang.le.2006@mailinator.com'],
        'da-tao.p4@edunest.test' => ['Phạm Minh Khoa', 'khoa.pham.2006@mailinator.com'],
        'cho-tao.p4@edunest.test' => ['Vũ Ngọc Ánh', 'anh.vu.2007@mailinator.com'],
        'pdf-loi.p4@edunest.test' => ['Trần Gia Bảo', 'bao.tran.2007@mailinator.com'],
        'thu-hoi.p4@edunest.test' => ['Đỗ Quang Huy', 'huy.do.2006@mailinator.com'],
    ];

    /** Tên thật thay cho 29 học viên "Học viên demo NN" của kỳ thi thử. */
    private const HOC_VIEN = [
        'Trần Bảo Ngọc', 'Hoàng Minh Tuấn', 'Phan Thị Hồng Nhung', 'Đặng Quốc Việt', 'Bùi Thanh Hương', 'Ngô Gia Hân',
        'Lý Đức Anh', 'Vũ Thị Mai Anh', 'Đinh Công Thành', 'Trương Mỹ Linh', 'Hồ Tấn Phát', 'Dương Khánh Vy',
        'Mai Xuân Trường', 'Tạ Thu Thảo', 'Lâm Nhật Minh', 'Châu Ngọc Hân', 'Kiều Văn Hậu', 'Quách Thùy Dung',
        'Tô Minh Nhật', 'Lưu Bảo Châu', 'Cao Thanh Tùng', 'Nguyễn Ngọc Diệp', 'Trịnh Hải Đăng', 'Phùng Thị Yến',
        'La Quốc Bảo', 'Huỳnh Thanh Tâm', 'Giang Minh Khôi', 'Đoàn Phương Thảo', 'Võ Hoài Nam',
    ];

    private const NHAN_XET = [
        5 => ['Thầy giảng rất dễ hiểu, bài đọc ngắn gọn mà đủ ý. Em lấy lại gốc sau 3 tuần.', 'Khóa học bám sát chương trình, bài kiểm tra cuối chương giúp em biết mình hổng chỗ nào.', 'Nội dung chất lượng, có ví dụ thực tế nên nhớ lâu. Rất đáng tiền.',
            'Em học buổi tối sau giờ học thêm, mỗi bài chỉ 10–15 phút nên không bị ngợp.', 'Phần tóm tắt cuối bài rất hay, em chụp lại để ôn trước giờ kiểm tra.', 'Lần đầu em làm bài kiểm tra Hóa được 9 điểm, cảm ơn thầy nhiều!',
            'Giải thích kỹ từng bước, có cả mẹo nhớ nên em không phải học thuộc lòng.', 'Học trên điện thoại mượt, tiến độ lưu lại nên đi xe buýt cũng học được.'],
        4 => ['Nội dung tốt, mong thầy bổ sung thêm video cho các bài khó.', 'Bài tập vừa sức, giải thích rõ. Giao diện học trên điện thoại hơi nhỏ chữ.',
            'Kiến thức chắc, chỉ mong có thêm đề luyện tập có bấm giờ.', 'Khóa ổn, phần chương 2 hơi dồn, em phải xem lại hai lần mới hiểu.'],
        3 => ['Kiến thức ổn nhưng hơi ít bài tập vận dụng cao.', 'Hợp với bạn mất gốc, bạn khá rồi thì nên học khóa luyện đề.'],
    ];

    private User $giaoVu;

    private User $duyet;

    public function run(): void
    {
        $this->giaoVu = User::query()->where('email', 'lan.pham@edunest.test')->firstOrFail();
        $this->duyet = User::query()->where('email', 'minh@edunest.test')->firstOrFail();

        $this->phan('Học viên', fn () => $this->hocVienThiThu());
        $this->phan('Hồ sơ giáo viên', fn () => $this->hoSoGiaoVien());
        $this->phan('Bản bị trả lại, bản đã duyệt', fn () => $this->trangThaiPhienBan());
        $this->phan('Học viên mua khóa, tiến độ, đánh giá', fn () => $this->hocVienMuaKhoa());
        $this->phan('Trạng thái đơn và thanh toán', fn () => $this->trangThaiDon());
        $this->phan('Mã kích hoạt', fn () => $this->maKichHoat());
        $this->phan('Hỏi đáp trong bài', fn () => $this->hoiDap());
        $this->phan('Hỏi đáp bị ẩn', fn () => $this->hoiDapBiAn());
        $this->phan('Kỳ thi nháp, đã đóng, đăng ký', fn () => $this->kyThi());
        $this->phan('Hoa hồng giới thiệu', fn () => $this->hoaHong());
        $this->phan('Cộng đồng', fn () => $this->congDong());
        $this->phan('Chat hỗ trợ', fn () => $this->chatHoTro());
        $this->phan('Buổi học trực tiếp', fn () => $this->hocTrucTiep());
        $this->phan('Mã giảm giá', fn () => $this->maGiamGia());
        $this->phan('Tài nguyên, ghi chú, yêu thích, thẻ', fn () => $this->linhTinh());
        $this->phan('Mini game, chuyển khoản, giỏ hàng', fn () => $this->troChoiChuyenKhoanGioHang());
        $this->phan('Thông báo', fn () => $this->thongBao());
        // Đổi tên và email sau cùng vì các seeder trên tra theo email cũ.
        $this->phan('Tên và email người dùng', fn () => $this->nguoiDung());
    }

    private function phan(string $ten, \Closure $viec): void
    {
        try {
            $viec();
            $this->command?->info('  ✓ '.$ten);
        } catch (\Throwable $e) {
            $this->command?->warn('  ! '.$ten.': '.$e->getMessage());
            report($e);
        }
    }

    private function u(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    /** @var list<User> */
    private array $hocVien = [];

    /** @return list<User> */
    private function hocVienThiThu(): array
    {
        if ($this->hocVien !== []) {
            return $this->hocVien;
        }
        foreach (self::HOC_VIEN as $i => $name) {
            $email = Str::of(Str::ascii($name, 'vi'))->lower()->replace(' ', '.')->toString().'.'.(2006 + $i % 3).'@mailinator.com';
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $user = new User(['name' => $name, 'email' => $email, 'password' => Hash::make(DemoSeeder::PASSWORD)]);
                $user->forceFill(['email_verified_at' => now()->subDays(40 - $i), 'nickname' => Str::of($name)->afterLast(' ')->append((string) (10 + $i))->toString(),
                    'show_name_on_leaderboard' => $i % 3 !== 0, 'created_at' => now()->subDays(45 - $i)])->save();
                $user->roles()->syncWithoutDetaching([Role::idFor(RoleCode::HocVien) => ['assigned_at' => now()->subDays(45 - $i)]]);
            }
            $this->hocVien[] = $user;
        }

        return $this->hocVien;
    }

    /** Hành động của Giáo vụ cần 2FA đã bật: bật tạm khi seed, trả lại trạng thái chưa thiết lập để người dùng tự thiết lập khi đăng nhập. */
    private function voiGiaoVu(\Closure $viec): mixed
    {
        $this->giaoVu->forceFill(['two_factor_secret' => encrypt('SEEDTEMPORARYKEY'), 'two_factor_confirmed_at' => now()])->save();
        $this->giaoVu->forgetRoleCache();
        try {
            return $viec();
        } finally {
            $this->giaoVu->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
            $this->giaoVu->forgetRoleCache();
        }
    }

    private function hoSoGiaoVien(): void
    {
        $mon = fn (string $ten) => (int) Category::query()->where('name', $ten)->whereNotNull('parent_id')->value('id');
        $hoSo = [
            'hoa.tran@edunest.test' => ['Thạc sĩ Hóa học, Đại học Sư phạm TP.HCM. 12 năm dạy Hóa THPT và luyện thi tốt nghiệp tại TP.HCM.', "Giáo viên dạy giỏi cấp thành phố năm học 2022–2023.\nHướng dẫn 15 học sinh đạt giải học sinh giỏi Hóa cấp thành phố.", ['Hóa học']],
            'binh.gv@edunest.test' => ['Cử nhân Sư phạm Vật lí, Đại học Sư phạm Hà Nội. 8 năm dạy Vật lí THPT, chuyên đề dao động và điện xoay chiều.', 'Tác giả bộ đề luyện thi Vật lí được hơn 3.000 học sinh sử dụng.', ['Vật lý', 'Lịch sử', 'Địa lý', 'Tiếng Anh', 'Sinh học']],
            'thu.ha@edunest.test' => [null, null, ['Sinh học']],
            'hoang.nam@edunest.test' => [null, null, ['Lịch sử', 'Địa lý']],
            'ngoc.mai@edunest.test' => [null, null, ['Tiếng Anh']],
            'duc.thanh@edunest.test' => [null, null, ['Vật lý']],
        ];
        foreach ($hoSo as $email => [$bio, $thanhTich, $cacMon]) {
            $gv = $this->u($email);
            $p = TeacherProfile::query()->firstOrNew(['user_id' => $gv->id], ['created_by_id' => $this->giaoVu->id]);
            if ($bio !== null) {
                $p->bio = $bio;
                $p->achievements = $thanhTich;
            }
            $p->subject_category_ids = array_values(array_filter(array_map($mon, $cacMon)));
            $p->save();
        }
    }

    private function trangThaiPhienBan(): void
    {
        $edit = app(EditDraftContent::class);
        $flow = app(VersionTransitions::class);
        $hoa = $this->u('hoa.tran@edunest.test');
        $mai = $this->u('ngoc.mai@edunest.test');

        $tao = function (User $gv, string $ten, string $mon, string $capDo, int $gia, string $chuong, array $bai) use ($edit): array {
            $course = app(AssignCourseAuthoring::class)->handle($this->giaoVu, [
                'title' => $ten, 'category_id' => (int) Category::query()->where('name', $mon)->whereNotNull('parent_id')->value('id'),
                'level_id' => (int) Level::query()->where('name', $capDo)->value('id'), 'learning_mode' => LearningMode::TuanTu,
                'price' => $gia, 'access_months' => 12, 'author_id' => $gv->id, 'due_date' => now()->addDays(20)->toDateString(),
            ]);
            $v = CourseVersion::query()->where('course_id', $course->id)->whereNotNull('open_draft_key')->firstOrFail();
            $v->forceFill(['summary' => $bai[0][2], 'objectives' => "Nắm kiến thức trọng tâm của chuyên đề.\nLàm được bài tập vận dụng.", 'description' => $bai[0][2]])->save();
            $s = $edit->addSection($gv, $v->id, $chuong);
            $ids = [];
            foreach ($bai as [$tenBai, $html]) {
                $l = $edit->addLesson($gv, $s->id, $tenBai, LessonType::BaiDoc);
                $edit->saveLesson($gv, $l->id, ['content' => $html, 'is_required' => true]);
                $ids[] = $l->id;
            }

            return [$v, $ids];
        };

        [$v1, $ids1] = $tao($hoa, 'Hóa 12 – Polymer và vật liệu', 'Hóa học', 'Nâng cao', 449000, 'Chương 1. Đại cương về polymer', [
            ['Khái niệm polymer', '<p>Polymer là những hợp chất có phân tử khối rất lớn do nhiều mắt xích liên kết với nhau, ví dụ polyethylene (−CH<sub>2</sub>−CH<sub>2</sub>−)<sub>n</sub>.</p>', 'Polymer và vật liệu polymer: chất dẻo, tơ, cao su, keo dán – chuyên đề Hóa 12.'],
            ['Phản ứng trùng hợp và trùng ngưng', '<p>Trùng hợp: nhiều monomer có liên kết đôi kết hợp thành polymer. Trùng ngưng: monomer có ít nhất hai nhóm chức kết hợp và giải phóng phân tử nhỏ như H<sub>2</sub>O.</p>', ''],
        ]);
        $flow->submit($hoa, $v1);
        $flow->returnVersion($this->duyet, $v1->fresh() ?? $v1, 'Bài "Khái niệm polymer" còn ngắn, cần bổ sung phân loại polymer theo nguồn gốc và ví dụ ứng dụng trong đời sống.', [
            $ids1[0] => 'Thêm bảng phân loại: polymer thiên nhiên, bán tổng hợp, tổng hợp.',
            0 => 'Nên bổ sung bài kiểm tra cuối chương trước khi gửi duyệt lại.',
        ]);

        [$v2] = $tao($mai, 'Tiếng Anh IELTS nền tảng 5.0', 'Tiếng Anh', 'Nâng cao', 899000, 'Unit 1. Làm quen với IELTS', [
            ['Cấu trúc bài thi IELTS', '<p>Bài thi IELTS gồm bốn kĩ năng: Listening (30 phút), Reading (60 phút), Writing (60 phút) và Speaking (11–14 phút). Điểm từng kĩ năng từ 0 đến 9.</p>', 'Lộ trình IELTS 5.0 cho người mới: làm quen cấu trúc đề, từ vựng chủ đề và chiến thuật cho từng kĩ năng.'],
            ['Chiến thuật Listening Part 1', '<p>Đọc trước câu hỏi, đoán loại thông tin cần điền (số, tên, ngày tháng). Chú ý bẫy thông tin bị sửa lại trong hội thoại.</p>', ''],
        ]);
        $flow->submit($mai, $v2);
        $flow->approve($this->duyet, $v2->fresh() ?? $v2);
    }

    /** Thêm ghi danh qua đơn hoàn tất cho học viên thi thử, có tiến độ và đánh giá để trang khóa có số liệu thật. */
    private function hocVienMuaKhoa(): void
    {
        $students = $this->hocVienThiThu();
        $courses = Course::query()->where('sale_status', SaleStatus::DangBan)->whereNotNull('current_version_id')
            ->whereNotIn('title', ['Hóa 12 nền tảng'])->orderBy('id')->get()->values();
        $ratings = app(CourseRatings::class);
        $nguon = [OrderSource::MuaNgay, OrderSource::GioHang, OrderSource::MuaNgay, OrderSource::Combo];
        foreach ($courses->take(8) as $ci => $course) {
            $buyers = array_slice($students, ($ci * 3) % count($students), 6 - ($ci % 3));
            foreach ($buyers as $bi => $student) {
                if (Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->exists()) {
                    continue;
                }
                $enrollment = $this->donHoanTat($student, $course, $nguon[($ci + $bi) % 4], 2 + $ci * 3 + $bi);
                $lessons = Lesson::query()->where('course_version_id', $course->current_version_id)->whereNull('removed_at')
                    ->where('is_required', true)->orderBy('course_section_id')->orderBy('position')->get();
                $done = (int) ceil($lessons->count() * [1.0, 0.6, 0.35, 0.8, 0.1][($ci + $bi) % 5]);
                foreach ($lessons->take($done) as $li => $lesson) {
                    DB::table('lesson_progress')->insertOrIgnore([
                        'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id,
                        'started_at' => now()->subDays(10 - min($li, 9)), 'completed_at' => now()->subDays(9 - min($li, 8)), 'last_viewed_at' => now()->subDays(9 - min($li, 8)),
                    ]);
                }
                if ($done * 100 >= 30 * max(1, $lessons->count()) && $bi < 4) {
                    $stars = [5, 5, 4, 5, 3, 4][($ci + $bi) % 6];
                    $list = self::NHAN_XET[$stars];
                    $r = $ratings->submit($student, $course, $stars, $list[($ci * 3 + $bi * 5) % count($list)]);
                    $when = now()->subDays(max(1, 12 - $ci - $bi))->setTime(8 + ($ci * 5 + $bi * 3) % 14, ($ci * 17 + $bi * 11) % 60);
                    $r->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
                }
            }
        }
        // Giáo viên trả lời một đánh giá, Giáo vụ ẩn một đánh giá vi phạm.
        $first = CourseRating::query()->orderBy('id')->first();
        $author = $first === null ? null : User::query()->whereKey(DB::table('course_authors')->where('course_id', $first->course_id)->whereNotNull('current_key')->value('user_id'))->first();
        if ($first !== null && $author !== null) {
            $ratings->reply($author, $first, 'Cảm ơn em đã góp ý. Thầy sẽ bổ sung thêm bài tập vận dụng trong bản cập nhật tháng tới.');
        }
        $last = CourseRating::query()->orderByDesc('id')->first();
        if ($last !== null && $last->id !== $first?->id) {
            $last->forceFill(['comment' => 'Khóa này tệ, ai cần tài liệu giá rẻ thì inbox Zalo 09xx xxx xxx nhé.'])->save();
            $this->voiGiaoVu(fn () => $ratings->hide($this->giaoVu, $last->fresh() ?? $last, 'Nội dung quảng cáo, chứa thông tin liên hệ ngoài nền tảng.'));
        }
    }

    private function donHoanTat(User $student, Course $course, OrderSource $source, int $daysAgo): Enrollment
    {
        $at = now()->subDays($daysAgo)->setTime(19 + $daysAgo % 4, (7 * $daysAgo) % 60);
        $order = new Order;
        $order->forceFill([
            'public_code' => PlaceOrder::publicCode(), 'user_id' => $student->id, 'source' => $source, 'status' => OrderStatus::HoanTat,
            'subtotal' => $course->price, 'total' => $course->price, 'created_at' => $at, 'updated_at' => $at,
            'expires_at' => $at->copy()->addMinutes(30), 'completed_at' => $at->copy()->addMinutes(3), 'first_completed_at' => $at->copy()->addMinutes(3),
        ])->save();
        $item = $order->items()->create([
            'course_id' => $course->id, 'course_title' => $course->title, 'kind' => OrderItemKind::MuaKhoa, 'status' => OrderItemStatus::ChuaCap,
            'list_price' => $course->price, 'effective_price' => $course->price, 'final_amount' => $course->price, 'access_months' => $course->access_months,
        ]);
        $payment = $order->payments()->create([
            'transaction_code' => 'GD-'.Str::upper((string) Str::ulid()), 'channel' => $daysAgo % 3 === 0 ? PaymentChannel::ChuyenKhoan : PaymentChannel::MoPhong,
            'txn_status' => PaymentTxnStatus::ThanhCong, 'requested_amount' => $course->price, 'received_amount' => $course->price,
            'reconcile_status' => PaymentReconcileStatus::HopLe, 'terms_accepted_at' => $at, 'succeeded_at' => $at->copy()->addMinutes(2), 'reconciled_at' => $at->copy()->addMinutes(2),
        ]);
        $payload = json_encode(['transaction_code' => $payment->transaction_code, 'result' => 'THANH_CONG', 'received_amount' => $course->price], JSON_THROW_ON_ERROR);
        $payment->events()->create([
            'transaction_code' => $payment->transaction_code, 'result' => PaymentEventResult::ThanhCong, 'signature_valid' => true,
            'payload' => $payload, 'signature' => PaymentConfirmation::sign($payload), 'received_at' => $at->copy()->addMinutes(2), 'outcome' => PaymentEventOutcome::DaGhi,
        ]);
        app(GrantOrderItem::class)->locked($order, $item);
        $enrollment = Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();
        $enrollment->forceFill(['granted_at' => $at->copy()->addMinutes(3), 'created_at' => $at->copy()->addMinutes(3)])->save();

        return $enrollment;
    }

    private function trangThaiDon(): void
    {
        $students = $this->hocVienThiThu();
        $courses = Course::query()->where('sale_status', SaleStatus::DangBan)->whereNotNull('current_version_id')->orderByDesc('id')->get()->values();
        $pick = function (User $u) use ($courses): Course {
            return $courses->first(fn (Course $c) => ! Enrollment::query()->where('user_id', $u->id)->where('course_id', $c->id)->exists()
                && ! Order::query()->where('user_id', $u->id)->whereHas('items', fn ($q) => $q->where('course_id', $c->id))->whereIn('status', ['CHO_THANH_TOAN', 'DANG_CAP_QUYEN'])->exists())
                ?? throw new \RuntimeException('Không còn khóa để đặt.');
        };
        $place = app(PlaceOrder::class);
        $start = app(StartPayment::class);
        $reconcile = app(ReconcilePayment::class);

        // Học viên tự hủy đơn.
        $u1 = $students[20];
        $o1 = $place->handle($u1, $pick($u1));
        if ($o1 instanceof Order) {
            app(CancelOrder::class)->handle($u1, $o1);
        }
        // Thanh toán thất bại (cổng báo thất bại), đơn vẫn chờ thanh toán lại.
        $u2 = $students[21];
        $o2 = $place->handle($u2, $pick($u2));
        if ($o2 instanceof Order) {
            $p2 = $start->handle($u2, $o2, true);
            $body = PaymentConfirmation::simulated($p2, PaymentEventResult::ThatBai);
            $reconcile->handle($body, PaymentConfirmation::sign($body));
            // Xác nhận giả mạo (sai chữ ký) bị từ chối, được ghi lại để Quản trị tra cứu.
            $fake = json_encode(['transaction_code' => $p2->transaction_code, 'result' => 'THANH_CONG', 'received_amount' => $p2->requested_amount], JSON_THROW_ON_ERROR);
            $reconcile->handle($fake, str_repeat('a', 64));
        }
        // Đã bấm thanh toán nhưng chưa có kết quả (giao dịch khởi tạo).
        $u3 = $students[22];
        $o3 = $place->handle($u3, $pick($u3));
        if ($o3 instanceof Order) {
            $start->handle($u3, $o3, true);
        }
        // Giáo vụ xác nhận đã hoàn tiền một khoản cần hoàn (từ OrderDemoSeeder).
        $refund = Payment::query()->where('reconcile_status', PaymentReconcileStatus::CanHoan)->orderBy('id')->first();
        if ($refund !== null) {
            $this->voiGiaoVu(fn () => app(MarkPaymentRefunded::class)->handle($this->giaoVu, $refund->order, $refund,
                'Đã chuyển khoản hoàn 100% qua Vietcombank, mã giao dịch FT24326118893.', true));
        }
    }

    private function maKichHoat(): void
    {
        $codes = app(ActivationCodes::class);
        $batch = ActivationCodeBatch::query()->orderBy('id')->first();
        if ($batch === null) {
            return;
        }
        $batch->forceFill(['note' => 'Phát cho học sinh lớp 12A1 THPT Nguyễn Thượng Hiền theo hợp đồng liên kết tháng 11/2026'])->save();
        $free = ActivationCode::query()->where('batch_id', $batch->id)->where('status', 'CHUA_DUNG')->orderBy('id')->get();
        foreach ([$this->u('chi.le@edunest.test'), $this->hocVienThiThu()[23]] as $i => $student) {
            if (Enrollment::query()->where('user_id', $student->id)->where('course_id', $batch->course_id)->exists() || ! isset($free[$i])) {
                continue;
            }
            app(RedeemActivationCode::class)->handle($student, $free[$i]->code);
        }
        if (isset($free[2])) {
            $this->voiGiaoVu(fn () => $codes->void($this->giaoVu, $batch, $free[2], 'Mã bị lộ khi gửi nhầm vào nhóm lớp, đã cấp mã thay thế.', true));
        }
        // Lô cũ đã quá hạn nhập mã.
        $course = Course::query()->where('title', 'Vật lý 10 cơ học')->first();
        if ($course !== null) {
            $old = $this->voiGiaoVu(fn () => $codes->createBatch($this->giaoVu, $course, 5, CarbonImmutable::now()->addDay(), 'Chương trình học thử hè 2026'));
            $old->forceFill(['redeem_until' => now()->subDays(3)])->save();
            $codes->expireOverdue();
        }
    }

    private function hoiDap(): void
    {
        $k1 = Course::query()->where('title', 'Hóa 12 nền tảng')->firstOrFail();
        $teacher = $this->u('hoa.tran@edunest.test');
        $lessons = Lesson::query()->where('course_version_id', $k1->current_version_id)->where('type', LessonType::BaiDoc)->whereNull('removed_at')->orderBy('id')->get()->values();
        $enrollments = Enrollment::query()->where('course_id', $k1->id)->where('course_version_id', $k1->current_version_id)
            ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('het_han_luc')->orWhere('het_han_luc', '>', now()))->orderBy('id')->get()->values();
        $qa = [
            ['Thầy ơi, vì sao este lại ít tan trong nước hơn carboxylic acid có cùng số C ạ?', 'Vì este không tạo được liên kết hydrogen giữa các phân tử với nhau và tạo liên kết hydrogen với nước rất yếu, còn acid có nhóm −OH tạo liên kết hydrogen mạnh với nước em nhé.'],
            ['Phản ứng thủy phân este trong môi trường acid có cần đun nóng không ạ?', null],
            ['Em chưa hiểu vì sao xà phòng hóa là phản ứng một chiều?', null],
        ];
        $d = app(Discussions::class);
        foreach ($qa as $i => [$q, $a]) {
            if (! isset($enrollments[$i], $lessons[$i % max(1, $lessons->count())])) {
                break;
            }
            $e = $enrollments[$i];
            $student = User::query()->findOrFail($e->user_id);
            $open = LearningAccess::lessons($e)->first(fn (Lesson $l) => $l->type !== LessonType::Quiz
                && LearningAccess::allows($student, $e, Activity::ViewLesson, $l));
            if ($open === null) {
                continue;
            }
            $discussion = $d->ask($student, $e, $open, $q, null);
            if ($a !== null) {
                $d->reply($teacher, $discussion, $a);
            }
        }
    }

    private function hoiDapBiAn(): void
    {
        $d = Discussion::query()->where('status', 'CHUA_TRA_LOI')->orderByDesc('id')->first();
        if ($d === null) {
            return;
        }
        $d->forceFill(['body' => 'Ai cần đáp án đề thi thử tuần sau thì nhắn mình, bán rẻ.'])->save();
        $this->voiGiaoVu(fn () => app(Discussions::class)->hide($this->giaoVu, $d->fresh() ?? $d, 'Mua bán đề thi, vi phạm quy chế cộng đồng.'));
    }

    private function kyThi(): void
    {
        $base = ExamEvent::query()->orderBy('id')->first();
        if ($base === null) {
            return;
        }
        $now = now();
        $draft = $base->replicate(['status', 'opened_at', 'closed_at', 'published_at', 'scheduled_at', 'reminded_at', 'publish_at']);
        $draft->forceFill(['title' => 'Thi thử Hóa 12 – tháng 12 (đang soạn)', 'status' => ExamStatus::Nhap,
            'opens_at' => $now->copy()->addDays(14)->setTime(19, 30), 'closes_at' => $now->copy()->addDays(14)->setTime(21, 30)])->save();
        $closed = $base->replicate(['published_at', 'publish_at', 'reminded_at']);
        $closed->forceFill(['title' => 'Thi thử Hóa 12 – giữa kỳ', 'status' => ExamStatus::DaDong,
            'opens_at' => $now->copy()->subDays(2)->setTime(19, 0), 'closes_at' => $now->copy()->subDays(2)->setTime(21, 0),
            'scheduled_at' => $now->copy()->subDays(9), 'opened_at' => $now->copy()->subDays(2)->setTime(19, 0), 'closed_at' => $now->copy()->subDays(2)->setTime(21, 0),
            'publish_at' => $now->copy()->addDay()->setTime(8, 0)])->save();
        foreach (DB::table('exam_event_courses')->where('exam_event_id', $base->id)->pluck('course_id') as $cid) {
            DB::table('exam_event_courses')->insertOrIgnore([['exam_event_id' => $draft->id, 'course_id' => $cid], ['exam_event_id' => $closed->id, 'course_id' => $cid]]);
        }
        $upcoming = ExamEvent::query()->where('status', ExamStatus::SapMo)->orderBy('id')->first();
        if ($upcoming !== null) {
            $upcoming->forceFill(['requires_registration' => true])->save();
            foreach (array_slice($this->hocVienThiThu(), 0, 6) as $i => $u) {
                DB::table('exam_registrations')->insertOrIgnore(['exam_event_id' => $upcoming->id, 'user_id' => $u->id,
                    'status' => $i === 5 ? 'DA_HUY' : 'DA_DANG_KY', 'created_at' => $now->copy()->subHours(30 - $i), 'updated_at' => $now->copy()->subHours(20 - $i)]);
            }
        }
    }

    private function hoaHong(): void
    {
        $nam = $this->u('hoang.nam@edunest.test');
        $aff = DB::table('affiliates')->orderBy('id')->first();
        $aff2 = DB::table('affiliates')->insertGetId(['user_id' => $nam->id, 'code' => 'THAYNAM10', 'rate_bp' => 1000, 'is_active' => true,
            'created_by_id' => $this->giaoVu->id, 'updated_by_id' => $this->giaoVu->id, 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
        $orders = Order::query()->where('status', OrderStatus::HoanTat)->whereNull('affiliate_id')->orderBy('id')->take(6)->get();
        foreach ($orders as $i => $o) {
            $affId = $i % 2 === 0 ? $aff2 : ($aff->id ?? $aff2);
            $rate = $i % 2 === 0 ? 1000 : (int) ($aff->rate_bp ?? 1000);
            $o->forceFill(['affiliate_id' => $affId, 'affiliate_rate_bp_snapshot' => $rate, 'affiliate_commission_eligible' => true])->save();
            $status = ['DA_CHI', 'DA_CHI', 'CHO_DUYET', 'CHO_DUYET', 'HUY', 'CHO_DUYET'][$i];
            DB::table('affiliate_commissions')->insertOrIgnore([
                'order_id' => $o->id, 'affiliate_id' => $affId, 'base_amount' => $o->total, 'rate_bp' => $rate,
                'amount' => intdiv($o->total * $rate, 10000), 'status' => $status, 'recorded_at' => $o->completed_at ?? now(),
                'paid_at' => $status === 'DA_CHI' ? now()->subDays(1) : null, 'paid_by_id' => $status === 'DA_CHI' ? $this->giaoVu->id : null,
                'cancelled_at' => $status === 'HUY' ? now()->subDays(2) : null, 'cancel_reason' => $status === 'HUY' ? 'Đơn hoàn tiền theo yêu cầu học viên.' : null,
            ]);
        }
    }

    private function congDong(): void
    {
        $s = $this->hocVienThiThu();
        $course = Course::query()->where('title', 'Hóa 12 nền tảng')->first();
        $posts = [
            [$s[0], PostTopic::OnThi, 'Lịch ôn 30 ngày trước thi tốt nghiệp mình đang theo', "Tuần 1–2: tổng ôn lý thuyết theo chương, mỗi ngày 1 chương.\nTuần 3: luyện đề, mỗi ngày 1 đề 50 phút.\nTuần 4: chữa lỗi sai, ôn công thức.\nCác bạn góp ý giúp mình nhé!"],
            [$s[1], PostTopic::HoiDap, 'Phân biệt phản ứng trùng hợp và trùng ngưng thế nào cho nhanh?', 'Mình hay nhầm hai loại này khi làm trắc nghiệm, có mẹo nào nhận biết nhanh không mọi người?'],
            [$s[2], PostTopic::HocTap, 'Chia sẻ sơ đồ tư duy chương Este – Lipit', 'Mình tổng hợp lại toàn bộ chương thành một sơ đồ, bạn nào cần thì xem trong khóa Hóa 12 nền tảng phần tài liệu nhé.'],
            [$s[3], PostTopic::GiaoLuu, 'Bán tài khoản học giá rẻ, liên hệ Zalo', 'Bán lại tài khoản đã mua đủ khóa, giá chỉ 100k, inbox mình.'],
        ];
        foreach ($posts as $i => [$u, $topic, $title, $body]) {
            $id = DB::table('community_posts')->insertGetId(['user_id' => $u->id, 'topic' => $topic->value, 'course_id' => $i === 2 ? $course?->id : null,
                'title' => $title, 'body' => $body, 'status' => $i === 3 ? 'DA_AN' : 'HIEN', 'like_count' => [12, 4, 9, 0][$i],
                'hidden_reason' => $i === 3 ? 'Mua bán tài khoản, vi phạm điều khoản sử dụng.' : null, 'hidden_at' => $i === 3 ? now()->subHours(5) : null,
                'hidden_by_id' => $i === 3 ? $this->giaoVu->id : null, 'created_at' => now()->subDays(6 - $i), 'updated_at' => now()->subDays(6 - $i)]);
            if ($i === 3) {
                DB::table('community_reports')->insert(['community_post_id' => $id, 'user_id' => $s[4]->id, 'reason' => 'Bài viết mua bán tài khoản.', 'created_at' => now()->subHours(6)]);
            } else {
                DB::table('community_comments')->insert(['community_post_id' => $id, 'user_id' => $s[5 + $i]->id, 'body' => ['Lịch hợp lý đó, mình thêm 1 buổi ôn lý thuyết vào cuối tuần.', 'Trùng ngưng thì luôn tách ra phân tử nhỏ như nước nhé.', 'Cảm ơn bạn, sơ đồ rất dễ nhìn!'][$i],
                    'status' => 'HIEN', 'created_at' => now()->subDays(5 - $i), 'updated_at' => now()->subDays(5 - $i)]);
            }
        }
    }

    private function chatHoTro(): void
    {
        $s = $this->hocVienThiThu();
        $staff = $this->giaoVu;
        $threads = [
            [$s[6], 'DA_TRA_LOI', [['u', 'Em chuyển khoản rồi nhưng đơn vẫn báo chờ thanh toán ạ.'], ['s', 'Chào em, hệ thống đối soát tự động trong 5 phút. Em kiểm tra lại nội dung chuyển khoản có đúng mã đơn không nhé.']]],
            [$s[7], 'DONG', [['u', 'Cho em hỏi khóa Luyện đề Hóa có học được trên điện thoại không ạ?'], ['s', 'Được em nhé, EduNest chạy trên trình duyệt điện thoại và có thể cài như ứng dụng.'], ['u', 'Dạ em cảm ơn ạ.']]],
        ];
        // Khách chưa đăng nhập để lại câu hỏi, chờ nhân viên trả lời.
        $g = now()->subMinutes(25);
        $gid = DB::table('support_threads')->insertGetId(['user_id' => null, 'guest_key' => hash('sha256', 'khach-seed-1'), 'guest_name' => 'Chị Hồng (phụ huynh)',
            'guest_phone' => '0903456781', 'status' => 'CHO', 'staff_unread' => 1, 'user_unread' => 0, 'last_message_at' => $g, 'created_at' => $g, 'updated_at' => $g]);
        DB::table('support_messages')->insert(['support_thread_id' => $gid, 'sender_id' => null, 'from_staff' => false,
            'body' => 'Con tôi học lớp 11, muốn ôn Hóa vô cơ thì nên học khóa nào? Có học thử trước được không?', 'created_at' => $g]);
        foreach ($threads as $i => [$u, $status, $msgs]) {
            $t0 = now()->subDays(3 - $i)->setTime(20, 10);
            $tid = DB::table('support_threads')->insertGetId(['user_id' => $u->id, 'status' => $status, 'assigned_to_id' => $staff->id,
                'staff_unread' => 0, 'user_unread' => $status === 'DA_TRA_LOI' ? 1 : 0, 'last_message_at' => $t0->copy()->addMinutes(count($msgs) * 4), 'created_at' => $t0, 'updated_at' => $t0]);
            foreach ($msgs as $k => [$who, $body]) {
                DB::table('support_messages')->insert(['support_thread_id' => $tid, 'sender_id' => $who === 's' ? $staff->id : $u->id,
                    'from_staff' => $who === 's', 'body' => $body, 'created_at' => $t0->copy()->addMinutes($k * 4)]);
            }
        }
    }

    private function hocTrucTiep(): void
    {
        $sessions = DB::table('live_sessions')->orderBy('id')->get();
        $past = $sessions->first();
        if ($past === null) {
            return;
        }
        $enrolled = Enrollment::query()->where('course_id', $past->course_id)->limit(5)->pluck('user_id');
        foreach ($enrolled as $i => $uid) {
            DB::table('live_session_joins')->insertOrIgnore(['live_session_id' => $past->id, 'user_id' => $uid, 'first_clicked_at' => now()->subDays(1)->setTime(20, $i)]);
        }
        // Buổi đã học, có bản ghi để học viên xem lại.
        $id = DB::table('live_sessions')->insertGetId(['course_id' => $past->course_id, 'title' => 'Chữa đề thi thử tuần 1 – trực tiếp',
            'description' => 'Chữa chi tiết 10 câu vận dụng cao của đề thi thử tuần 1.', 'starts_at' => now()->subDays(4)->setTime(20, 0),
            'duration_minutes' => 90, 'meeting_url' => 'https://meet.google.com/edu-nest-hoa', 'schedule_rev' => 1,
            'created_by_id' => $past->created_by_id, 'updated_by_id' => $past->created_by_id, 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)]);
        $src = 'lessons/video/demo-p4.mp4';
        $dst = 'live-recordings/chua-de-tuan-1.mp4';
        if (Storage::disk('local')->exists($src)) {
            Storage::disk('local')->copy($src, $dst);
            DB::table('live_session_recordings')->insert(['live_session_id' => $id, 'current_for_session_id' => $id, 'file_path' => $dst,
                'original_name' => 'chua-de-tuan-1.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => Storage::disk('local')->size($dst),
                'uploaded_by_id' => $past->created_by_id, 'created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)]);
        }
        // Buổi bị hủy.
        DB::table('live_sessions')->insert(['course_id' => $past->course_id, 'title' => 'Giải đáp thắc mắc chương 2', 'description' => 'Buổi hỏi đáp tự do.',
            'starts_at' => now()->addDays(5)->setTime(20, 0), 'duration_minutes' => 60, 'meeting_url' => 'https://meet.google.com/edu-nest-hoa', 'schedule_rev' => 2,
            'cancelled_at' => now()->subHours(3), 'cancelled_by_id' => $past->created_by_id,
            'created_by_id' => $past->created_by_id, 'updated_by_id' => $past->created_by_id, 'created_at' => now()->subDays(3), 'updated_at' => now()->subHours(3)]);
    }

    private function maGiamGia(): void
    {
        $hoa = (int) Category::query()->where('name', 'Hóa học')->whereNotNull('parent_id')->value('id');
        $course = Course::query()->where('title', 'Luyện đề Hóa THPT')->first();
        $rows = [
            ['CHAOTHANG12', 'PHAN_TRAM', 15, 150000, 0, 'TOAN_BO', null, null, now()->addDays(1), now()->addDays(31), 200, 'Ưu đãi tháng 12 cho mọi khóa'],
            ['HOAHOC50K', 'SO_TIEN', 50000, null, 300000, 'DANH_MUC', $hoa, null, now()->subDays(5), now()->addDays(25), 100, 'Giảm 50.000đ cho khóa Hóa học từ 300.000đ'],
            ['LUYENDEHOA', 'PHAN_TRAM', 20, 150000, 0, 'KHOA', null, $course?->id, now()->subDays(2), now()->addDays(10), 50, 'Giảm 20% khóa Luyện đề Hóa THPT'],
            ['HE2026', 'PHAN_TRAM', 30, 200000, 0, 'TOAN_BO', null, null, now()->subDays(120), now()->subDays(60), 300, 'Khuyến mãi hè 2026 (đã kết thúc)'],
        ];
        foreach ($rows as [$code, $type, $value, $max, $min, $scope, $cat, $cid, $from, $to, $limit, $note]) {
            if (DB::table('coupons')->where('code', $code)->exists() || ($scope === 'KHOA' && $course === null)) {
                continue;
            }
            DB::table('coupons')->insert(['code' => $code, 'discount_type' => $type, 'value' => $value, 'max_discount' => $max, 'min_order' => $min,
                'starts_on' => $from->toDateString(), 'ends_on' => $to->toDateString(), 'starts_at' => $from->startOfDay(), 'ends_at' => $to->endOfDay(),
                'total_limit' => $limit, 'per_user_limit' => 1, 'scope_type' => $scope, 'scope_category_id' => $cat, 'scope_course_id' => $cid,
                'note' => $note, 'created_by_id' => $this->giaoVu->id, 'updated_by_id' => $this->giaoVu->id, 'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(7)]);
        }
    }

    private function linhTinh(): void
    {
        $s = $this->hocVienThiThu();
        $courses = Course::query()->where('sale_status', SaleStatus::DangBan)->orderBy('id')->get();
        foreach (array_slice($s, 10, 6) as $i => $u) {
            foreach ($courses->slice($i, 2) as $c) {
                DB::table('wishlists')->insertOrIgnore(['user_id' => $u->id, 'course_id' => $c->id, 'created_at' => now()->subDays($i + 1)]);
            }
        }
        // Thẻ cho khóa để lọc theo chủ đề.
        $tags = Tag::query()->pluck('id', 'name');
        foreach ($courses as $c) {
            $t = str_contains($c->title, 'Luyện đề') ? 'THPT quốc gia' : (str_contains($c->title, 'Tiếng Anh') ? 'Ngữ pháp' : (str_contains($c->title, 'Hóa 12') ? 'Este' : (str_contains($c->title, 'Vật lý') ? 'Dao động cơ' : null)));
            if ($t !== null && isset($tags[$t])) {
                DB::table('course_tag')->insertOrIgnore(['course_id' => $c->id, 'tag_id' => $tags[$t]]);
            }
        }
        // Tài liệu kèm bài và ghi chú của học viên.
        $k1 = Course::query()->where('title', 'Hóa 12 nền tảng')->first();
        $lesson = $k1 === null ? null : Lesson::query()->where('course_version_id', $k1->current_version_id)->where('type', LessonType::BaiDoc)->orderBy('id')->first();
        $pdf = 'lessons/pdf/demo-de-cuong.pdf';
        if ($lesson !== null && Storage::disk('local')->exists($pdf)) {
            Storage::disk('local')->copy($pdf, 'lesson-resources/tom-tat-este-lipit.pdf');
            DB::table('lesson_resources')->insert(['lesson_id' => $lesson->id, 'title' => 'Tóm tắt công thức Este – Lipit', 'file_path' => 'lesson-resources/tom-tat-este-lipit.pdf',
                'original_name' => 'tom-tat-este-lipit.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => Storage::disk('local')->size($pdf), 'position' => 1,
                'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)]);
            $en = Enrollment::query()->where('course_id', $k1->id)->with('user')->orderBy('id')->take(2)->get();
            foreach ($en as $i => $e) {
                DB::table('lesson_notes')->insert(['user_id' => $e->user_id, 'course_id' => $k1->id, 'lesson_key' => $lesson->lesson_key, 'lesson_id' => $lesson->id,
                    'body' => ['Este no đơn chức: CnH2nO2, n ≥ 2. Nhớ cách gọi tên: gốc R′ + gốc acid + "at".', 'Isoamyl acetate mùi chuối, ethyl butyrate mùi dứa – hay hỏi trong đề.'][$i],
                    'created_at' => now()->subDays(3 + $i), 'updated_at' => now()->subDays(3 + $i)]);
            }
        }
        // Cảnh báo đăng nhập bất thường và báo cáo tuần phụ huynh đã gửi.
        DB::table('security_alerts')->insert(['user_id' => $s[8]->id, 'ip_list' => json_encode(['113.161.72.15', '27.72.98.140', '14.232.166.201']), 'detected_at' => now()->subDays(1)->setTime(22, 41)]);
        $guardian = User::query()->where('email', 'phu.huynh@edunest.test')->first();
        $child = User::query()->where('email', 'an.nguyen@edunest.test')->first();
        if ($guardian !== null && $child !== null) {
            foreach ([14, 7] as $d) {
                DB::table('guardian_weekly_reports')->insertOrIgnore(['guardian_id' => $guardian->id, 'student_id' => $child->id,
                    'week_start' => now()->subDays($d)->startOfWeek()->toDateString(), 'sent_at' => now()->subDays($d - 7)->startOfWeek()->setTime(7, 0)]);
            }
        }
        // Một tài khoản bị khóa do vi phạm.
        $locked = $s[28];
        $locked->forceFill(['status' => AccountStatus::BiKhoa, 'locked_at' => now()->subDays(2), 'locked_reason' => 'Chia sẻ tài khoản cho nhiều người dùng, vi phạm điều khoản sử dụng.'])->save();
    }

    /** Phiên Bắn bong bóng / Mưa chữ (xong và đang chơi), đơn trả bằng chuyển khoản, giỏ hàng còn khóa chờ mua. */
    private function troChoiChuyenKhoanGioHang(): void
    {
        $set = FlashcardSet::query()->firstOrFail();
        $players = [];
        foreach (Enrollment::query()->where('course_id', $set->course_id)->orderBy('id')->pluck('user_id') as $id) {
            $u = User::query()->findOrFail((int) $id);
            try {
                FlashcardReviews::activeEnrollment($u, $set->course_id, CarbonImmutable::now());
                $players[] = $u;
            } catch (\Throwable) {
                continue;
            }
            if (count($players) === 3) {
                break;
            }
        }
        $games = app(GameSessions::class);
        $plan = [[0, GameKind::BanBong, 6], [0, GameKind::MuaChu, 4], [1, GameKind::BanBong, 3], [1, GameKind::MuaChu, 2], [2, GameKind::BanBong, 1]];
        foreach ($plan as $k => [$who, $kind, $ago]) {
            $at = CarbonImmutable::now()->subDays($ago)->setTime(20 + $k % 3, 5 * $k);
            $session = $games->start($players[$who], $set, $kind, $at);
            for ($pos = 1; $pos <= $session->card_count; $pos++) {
                $card = GameCard::query()->where('game_session_id', $session->id)->where('position', $pos)->firstOrFail();
                $at = $at->addSeconds(7 + ($pos * 3) % 9);
                $miss = ($pos + $k) % 4 === 0;
                $bottom = $kind === GameKind::MuaChu && ($pos + $k) % 5 === 0;
                $value = $bottom ? null : ($miss ? 'không nhớ' : (string) $card->snapshot['back']);
                $session = $games->answer($players[$who], $session, $pos, $value, $bottom, 'seed-'.$session->id.'-'.$pos, $at);
            }
        }
        // Một phiên đang chơi dở (để demo tiếp tục ván).
        $games->start($players[2], $set, GameKind::MuaChu);

        // Chuyển khoản: một đơn đã khớp tiền (hoàn tất), một đơn đã đồng ý điều khoản, đang chờ chuyển.
        $bank = app(BankTransfers::class);
        $pending = Order::query()->where('status', OrderStatus::ChoThanhToan->value)->orderByDesc('id')->limit(2)->get();
        foreach ($pending as $i => $order) {
            $bank->open(User::query()->findOrFail($order->user_id), $order, true);
            if ($i === 0) {
                $bank->simulate($order->refresh(), (int) $order->total);
                // Cấp quyền chạy ở hàng đợi grants: xử lý ngay để đơn về HOAN_TAT.
                Artisan::call('queue:work', ['--queue' => 'grants', '--stop-when-empty' => true, '--tries' => 1]);
            }
        }

        // Giỏ hàng: học viên đã chọn khóa nhưng chưa đặt.
        $cart = app(ManageCart::class);
        foreach (array_slice($players, 0, 2) as $n => $student) {
            $owned = Enrollment::query()->where('user_id', $student->id)->pluck('course_id');
            Course::query()->where('sale_status', SaleStatus::DangBan->value)->whereNotIn('id', $owned)->orderBy('id')->skip($n)->limit(2)->get()
                ->each(fn (Course $c) => $cart->add($student, $c));
        }
    }

    private function thongBao(): void
    {
        $an = $this->u('an.nguyen@edunest.test');
        $hoa = $this->u('hoa.tran@edunest.test');
        $an->notify(new EduNotification(NotificationKind::KhuyenMai, 'Khóa yêu thích đang giảm giá', 'Luyện đề Hóa THPT giảm 20% đến hết tuần này với mã LUYENDEHOA.', '/courses', 'seed-km-an'));
        $an->notify(new EduNotification(NotificationKind::PhienBanMoi, 'Khóa học có phiên bản mới', 'Hóa 12 nền tảng vừa cập nhật video bài giảng mới. Bạn có thể chuyển sang bản mới.', '/student/courses', 'seed-pb-an'));
        $an->notify(new EduNotification(NotificationKind::BuoiTrucTiep, 'Sắp đến buổi học trực tiếp', 'Buổi "Chữa đề thi thử" bắt đầu lúc 20:00 tối mai.', '/student/courses', 'seed-live-an'));
        $an->notify(new EduNotification(NotificationKind::CanhBaoBatThuong, 'Đăng nhập từ thiết bị mới', 'Tài khoản vừa đăng nhập trên Chrome, Windows tại TP. Hồ Chí Minh. Nếu không phải bạn, hãy đổi mật khẩu.', '/account/devices', 'seed-cb-an'));
        $an->notify(new EduNotification(NotificationKind::MuaThanhCong, 'Thanh toán thành công', 'Đơn mua khóa đã hoàn tất. Khóa học đã có trong mục Khóa của tôi.', '/student/courses', 'seed-mua-an'));
        $an->notify(new EduNotification(NotificationKind::TraLoiHoiDap, 'Thầy đã trả lời câu hỏi của bạn', 'Thầy Trần Minh Hóa vừa trả lời câu hỏi trong bài "Khái niệm este".', '/student/courses', 'seed-hd-an'));
        $hoa->notify(new EduNotification(NotificationKind::GiaoSoan, 'Bạn được giao soạn khóa mới', 'Giáo vụ giao soạn "Hóa 12 – Polymer và vật liệu", hạn soạn trong 20 ngày.', '/teacher/courses', 'seed-gs-hoa'));
        $hoa->notify(new EduNotification(NotificationKind::KetQuaDuyet, 'Phiên bản bị trả lại', '"Hóa 12 – Polymer và vật liệu" cần bổ sung nội dung theo góp ý của người duyệt.', '/teacher/courses', 'seed-kq-hoa'));
    }

    private function nguoiDung(): void
    {
        foreach (self::NGUOI_DUNG as $old => [$name, $email]) {
            User::query()->where('email', $old)->update(['name' => $name, 'email' => $email]);
        }
        User::query()->where('email', 'chup-anh.p9@edunest.test')->update(['name' => 'Hoàng Thị Thu', 'email' => 'thu.hoang.edunest@mailinator.com']);
        User::query()->where('email', 'test.uat2026@edunest.test')->update(['name' => 'Trịnh Văn Tùng', 'email' => 'tung.trinh.2007@mailinator.com']);
    }
}
