# EduNest

Nền tảng học trực tuyến cho học sinh THPT. Học viên mua khóa học, học theo bài, làm bài kiểm tra, thi thử, luyện tập và ôn bằng thẻ ghi nhớ. Giáo viên biên soạn khóa học. Giáo vụ duyệt nội dung và vận hành đơn hàng. Phụ huynh theo dõi tiến độ của con.

Đồ án tốt nghiệp, phát triển trên Laravel và React.

## Mục lục

- [Tính năng](#tính-năng)
- [Vai trò người dùng](#vai-trò-người-dùng)
- [Công nghệ](#công-nghệ)
- [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
- [Cài đặt](#cài-đặt)
- [Chạy ứng dụng](#chạy-ứng-dụng)
- [Dữ liệu mẫu và tài khoản demo](#dữ-liệu-mẫu-và-tài-khoản-demo)
- [Cấu hình dịch vụ ngoài](#cấu-hình-dịch-vụ-ngoài)
- [Tác vụ định kỳ và lệnh Artisan](#tác-vụ-định-kỳ-và-lệnh-artisan)
- [Kiểm thử và chất lượng mã](#kiểm-thử-và-chất-lượng-mã)
- [Cấu trúc thư mục](#cấu-trúc-thư-mục)
- [Xử lý sự cố](#xử-lý-sự-cố)

## Tính năng

Hệ thống chia thành 29 phân hệ (M01–M29):

| Nhóm | Phân hệ |
|---|---|
| Tài khoản | M01 Đăng ký, đăng nhập, xác minh email, xác thực 2 lớp (bắt buộc với Giáo vụ, Quản trị), phân quyền theo vai · M16 Chống chia sẻ tài khoản, watermark |
| Nội dung | M02 Danh mục, cấp độ, thẻ, hồ sơ và chuyên môn giáo viên · M03 Biên soạn khóa học · M04 Ngân hàng câu hỏi (một đáp án, nhiều đáp án, đúng/sai, điền từ, sắp xếp) · M05 Duyệt, công bố, quản lý phiên bản |
| Bán hàng | M06 Danh mục công khai, tìm kiếm, trang khóa · M07 Đơn hàng, thanh toán, cấp quyền · M08 Thời hạn truy cập, gia hạn · M12 Mã giảm giá, giỏ hàng, yêu thích · M13 Mã kích hoạt · M24 Gói combo, tiếp thị liên kết · M28 Chuyển khoản VietQR qua webhook |
| Học tập | M09 Trình học, tiến độ (bài đọc, video, PDF) · M10 Bài kiểm tra, hoàn thành khóa, chứng nhận có mã tra cứu · M17 Hỏi đáp theo bài, ghi chú · M20 Luyện tập · M22 Thẻ ghi nhớ, mini game Bắn bong bóng và Mưa chữ |
| Thi và động lực | M18 Thi thử trực tuyến, xếp hạng, vinh danh · M21 XP, cấp độ, chuỗi ngày, huy hiệu, bảng xếp hạng |
| Tương tác | M14 Đánh giá khóa học · M15 Thông báo thời gian thực, email, thông báo đẩy trình duyệt · M26 Buổi học trực tiếp, bản ghi · M27 Cộng đồng · M29 Chat hỗ trợ |
| Khác | M11 Báo cáo, nhật ký kiểm toán, tác vụ nền · M19 Phân tích kết quả, dashboard · M23 Phụ huynh, báo cáo tuần · M25 Trợ lý AI (Gemini) |

Thanh toán hỗ trợ bộ mô phỏng (luôn có), cổng MoMo môi trường thử và chuyển khoản ngân hàng bằng mã VietQR.

## Vai trò người dùng

| Vai | Khu vực | Việc chính |
|---|---|---|
| Khách | `/`, `/courses`, `/teachers`, `/verify` | Xem khóa học, tra cứu chứng nhận, chat hỗ trợ |
| Học viên | `/student` | Mua khóa, học, thi thử, luyện tập, thẻ ghi nhớ, cộng đồng |
| Giáo viên | `/teacher` | Soạn khóa, ngân hàng câu hỏi, trả lời hỏi đáp, buổi trực tiếp |
| Giáo vụ | `/academic` | Duyệt khóa, phân công giáo viên, đơn hàng, mã kích hoạt, kỳ thi, hoa hồng |
| Quản trị | `/admin` | Người dùng, vai trò, cấu hình hệ thống, nhật ký |
| Phụ huynh | `/guardian` | Theo dõi tiến độ, báo cáo tuần của con |

## Công nghệ

- **Máy chủ:** PHP 8.3, Laravel 13, Fortify (xác thực, 2FA), Sanctum, Reverb (WebSocket), hàng đợi trên CSDL
- **Giao diện:** Inertia 3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui, Wayfinder (sinh route TypeScript)
- **CSDL:** MySQL 8.4 (dùng khóa dòng, ràng buộc CHECK, mức cô lập READ COMMITTED)
- **Tích hợp:** Google Gemini, MoMo, VietQR (EMVCo), Web Push (VAPID), SMTP
- **Chất lượng:** Pest, PHPStan (Larastan), Pint, ESLint/Prettier qua Vite+, GitHub Actions

## Yêu cầu hệ thống

- PHP 8.3 với các extension `pdo_mysql`, `mbstring`, `openssl`, `gd`, `intl`, `zip`, `bcmath`
- Composer 2
- Node.js 22 và npm
- MySQL 8.4 (8.0 trở lên vẫn chạy, nhưng CI dùng 8.4)

Trên Windows có thể dùng [Laragon](https://laragon.org), bộ này có sẵn PHP, MySQL và Composer.

## Cài đặt

```bash
git clone https://github.com/tanhung-05/edunest-do-an.git
cd edunest-do-an
```

Tạo CSDL rỗng tên `edunest` (utf8mb4), rồi chạy:

```bash
composer setup
```

Lệnh này cài thư viện PHP và Node, tạo `.env` từ `.env.example`, sinh `APP_KEY`, chạy migration và build giao diện.

Mở `.env` và kiểm tra phần CSDL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=edunest
DB_USERNAME=root
DB_PASSWORD=
```

Nạp dữ liệu mẫu và tạo liên kết thư mục lưu trữ:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

## Chạy ứng dụng

Chạy mỗi lệnh trong một cửa sổ terminal riêng:

```bash
php artisan serve                                          # máy chủ web, http://127.0.0.1:8000
php artisan queue:listen --queue=default,grants,media --tries=1   # hàng đợi: cấp quyền học, email, thông báo
php artisan reverb:start                                   # thông báo thời gian thực, chat
npm run dev                                                # Vite, chỉ cần khi sửa giao diện
php artisan schedule:work                                  # tác vụ định kỳ (tùy chọn khi demo)
```

Hàng đợi phải nghe đủ ba kênh `default`, `grants`, `media`. Nếu thiếu kênh `grants`, đơn đã thanh toán sẽ không được cấp quyền học.

Nếu không sửa giao diện, chạy `npm run build` một lần là đủ, không cần `npm run dev`.

## Dữ liệu mẫu và tài khoản demo

`php artisan migrate:fresh --seed` tạo bộ dữ liệu giống thật:

- 14 khóa học có nội dung thật, chia theo môn.
- Khoảng 40 học viên đã mua khóa, đang học dở hoặc đã học xong, có đánh giá.
- Dữ liệu ở đủ các trạng thái: đơn hàng, phiên bản khóa, kỳ thi, mã kích hoạt, mã giảm giá, hỏi đáp, cộng đồng, chat hỗ trợ, buổi học trực tiếp, mini game.

Mọi tài khoản dùng mật khẩu `matkhau123`:

| Vai | Email |
|---|---|
| Quản trị | `quang.le.edunest@mailinator.com` |
| Giáo vụ | `lan.pham.edunest@mailinator.com` |
| Giáo viên | `hoa.tran.edunest@mailinator.com` |
| Học viên | `an.nguyen.2007@mailinator.com` |
| Phụ huynh | `phuc.nguyen.ph@mailinator.com` |

Ghi chú:

- Giáo vụ và Quản trị bắt buộc bật xác thực 2 lớp ở lần đăng nhập đầu. Dùng Google Authenticator hoặc ứng dụng tương tự.
- Email có đuôi `@mailinator.com`. Khi đã cấu hình SMTP, thư gửi tới các địa chỉ này đọc được công khai trên mailinator.com.
- Một số dữ liệu tính theo thời điểm nạp, như đơn chờ thanh toán (giữ 2 giờ), kỳ thi và buổi học trực tiếp. Nên nạp lại ngay trước khi demo.

Tạo tài khoản quản trị đầu tiên trên CSDL trống:

```bash
php artisan edunest:admin:create admin@example.com --name="Quản trị"
```

## Cấu hình dịch vụ ngoài

Mọi dịch vụ ngoài đều tùy chọn. Khi chưa cấu hình, ứng dụng vẫn chạy bằng bộ mô phỏng.

Cách nhanh nhất trên Windows là chạy script sau. Script hỏi từng giá trị, ẩn ký tự khi nhập và ghi vào `.env`:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/cau-hinh-khoa.ps1
```

| Dịch vụ | Biến môi trường | Ghi chú |
|---|---|---|
| Email (Gmail SMTP) | `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Dùng mật khẩu ứng dụng 16 ký tự của Google, không dùng mật khẩu đăng nhập |
| Trợ lý AI | `GEMINI_API_KEY`, `GEMINI_MODEL` | Lấy khóa ở Google AI Studio. Kiểm tra bằng `php artisan edunest:ai-check` |
| MoMo (môi trường thử) | `MOMO_ENDPOINT`, `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY` | Đăng ký ở developers.momo.vn. Khi có khóa, trang đơn hàng hiện thêm lựa chọn MoMo |
| VietQR | `BANK_BIN`, `BANK_SIM_BANK`, `BANK_SIM_ACCOUNT`, `BANK_SIM_ACCOUNT_NAME` | `BANK_BIN` là mã 6 số của ngân hàng. Điền đủ 4 dòng thì mã QR quét được bằng ứng dụng ngân hàng |
| Thông báo đẩy | `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | Sinh khóa bằng `php artisan edunest:vapid` |
| Thời gian thực | `REVERB_*`, `VITE_REVERB_*` | Đổi xong phải chạy lại `npm run build` |

Sau khi sửa `.env`, chạy `php artisan config:clear` rồi khởi động lại hàng đợi.

## Tác vụ định kỳ và lệnh Artisan

`php artisan schedule:work` tự chạy các tác vụ định kỳ. Có thể chạy tay từng lệnh:

| Lệnh | Việc |
|---|---|
| `edunest:expire-overdue-orders` | Hủy đơn quá hạn thanh toán |
| `edunest:recover-granting-orders` | Cấp lại quyền cho đơn bị kẹt |
| `edunest:commerce {reminders\|promotions\|codes}` | Nhắc giỏ hàng, khuyến mãi, hết hạn mã |
| `edunest:exams` | Mở, đóng kỳ thi và công bố kết quả |
| `edunest:practice` | Lịch luyện tập |
| `edunest:guardian-reports` | Gửi báo cáo tuần cho phụ huynh |
| `learning:finalize-overdue-attempts` | Nộp các bài làm quá giờ |
| `learning:aggregate-daily-report` | Tổng hợp số liệu học tập theo ngày |
| `edunest:prune-operational-data` | Dọn dữ liệu vận hành cũ |
| `edunest:ops-status` | Xem tình trạng hàng đợi và tác vụ nền |
| `edunest:demo-exam --close-in=2` | Tạo kỳ thi demo đóng sau vài phút |
| `edunest:admin:reset-2fa {email}` | Đặt lại xác thực 2 lớp cho một tài khoản |

## Kiểm thử và chất lượng mã

Bộ test dùng CSDL riêng `edunest_test` (khai báo trong `phpunit.xml`). Tạo CSDL này trước khi chạy:

```bash
php artisan test                 # toàn bộ test Pest (hơn 900 test)
php artisan test --filter=MoMo   # chạy một nhóm test
composer lint                    # định dạng PHP bằng Pint
vendor/bin/phpstan analyse       # phân tích tĩnh
npm run check                    # định dạng và lint TypeScript
npm run types:check              # kiểm tra kiểu TypeScript
composer ci:check                # chạy đủ các bước như GitHub Actions
```

GitHub Actions (`.github/workflows/tests.yml`) chạy `composer ci:check` trên MySQL 8.4 mỗi lần đẩy lên `main`.

## Cấu trúc thư mục

```
app/
  Actions/M01..M29/     Nghiệp vụ theo phân hệ (mọi thay đổi dữ liệu đi qua đây)
  Http/Controllers/     Controller theo vai: Student, Teacher, Academic, Admin, Guardian, PublicSite; Gateway, Webhook cho thanh toán
  Models/M01..M29/      Model Eloquent theo phân hệ
  Enums/                Trạng thái, loại (mã tiếng Việt viết hoa, ví dụ HOAN_TAT)
  Jobs/  Notifications/ Tác vụ nền, thông báo
  Support/              Tiện ích: cổng thanh toán, VietQR, quyền truy cập bài học…
database/
  migrations/           Lược đồ theo từng mốc P0–P10
  seeders/              Dữ liệu mẫu; nội dung khóa học ở seeders/du-lieu/
resources/js/
  pages/                Trang Inertia theo vai
  components/           Thành phần giao diện dùng chung
routes/                 web.php, console.php (lịch tác vụ), channels.php
scripts/                cau-hinh-khoa.ps1 (nhập khóa dịch vụ vào .env)
tests/                  Feature và Unit (Pest), nhóm theo quy trình QT-xx và mốc Pxx
```

## Xử lý sự cố

| Hiện tượng | Cách xử lý |
|---|---|
| Đơn đã thanh toán nhưng không vào học được | Hàng đợi chưa chạy hoặc thiếu kênh `grants`. Chạy lại `queue:listen` với đủ ba kênh |
| Giao diện không đổi sau khi sửa | Chạy `npm run build`, hoặc dùng `npm run dev` |
| Không có thông báo thời gian thực | Chạy `php artisan reverb:start` và kiểm tra biến `REVERB_*` |
| Lỗi tạo khóa EC khi bật thông báo đẩy trên Windows | Thêm `OPENSSL_CONF=<đường dẫn PHP>/extras/ssl/openssl.cnf` vào `.env`, rồi khởi động lại hàng đợi |
| Mất mã 2FA của tài khoản quản trị | `php artisan edunest:admin:reset-2fa email@...` |
| Đổi `.env` nhưng không có tác dụng | `php artisan config:clear` |
