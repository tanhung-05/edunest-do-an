# Hướng dẫn cài đặt và triển khai EduNest

Chạy các lệnh cài đặt tại thư mục gốc của repo. Hướng dẫn này dùng cho máy phát triển (Windows + Laragon hoặc tương đương) và cho máy chủ Linux.

## 1. Yêu cầu

| Thành phần | Phiên bản | Ghi chú |
|---|---|---|
| PHP | 8.3 | Bật `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `fileinfo`, `openssl` |
| Composer | 2.x | |
| MySQL | 8.4 | Bắt buộc. Ứng dụng dùng khóa dòng, ràng buộc CHECK và mức cô lập READ COMMITTED; SQLite không chạy được |
| Node.js | 22 | Để dựng giao diện (Vite+) |

## 2. Cài trên máy phát triển

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

Sửa `.env`: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` cho MySQL. Tạo CSDL `edunest` (utf8mb4) rồi:

```powershell
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

Trước `npm run build`, đặt `REVERB_APP_KEY` và `REVERB_APP_SECRET` trong `.env` thành hai chuỗi ngẫu nhiên của riêng máy bạn (tạo bằng lệnh dưới). Giao diện đọc khóa này lúc dựng, nên đổi khóa thì phải dựng lại.

```powershell
php -r "echo bin2hex(random_bytes(10));"
```

Chạy ứng dụng cần bốn tiến trình (mỗi lệnh một cửa sổ):

```powershell
php artisan serve --port=8000
```

```powershell
php artisan queue:work --queue=grants,default,media
```

```powershell
php artisan schedule:work
```

```powershell
php artisan reverb:start
```

- `serve` phải ở cổng khớp `APP_URL` (mặc định `http://localhost:8000`) vì webhook thanh toán mô phỏng gọi lại đúng địa chỉ này.
- Hàng đợi `grants` cấp quyền học sau thanh toán; `media` dựng PDF chứng nhận; `default` gửi email và thông báo.
- `schedule:work` chạy các tác vụ nền: hết hạn đơn, chốt lượt quiz quá giờ, mở/đóng/công bố kỳ thi, nhắc buổi trực tiếp, báo cáo tuần phụ huynh, luyện tập.
- Thời gian thực: `BROADCAST_CONNECTION=reverb` (mặc định từ P10). Chuông thông báo nghe kênh riêng của người dùng qua `reverb:start` (cổng 8080), số chưa đọc đổi ngay và hiện dòng báo nhanh. Reverb chưa chạy thì chuông vẫn tự hỏi lại mỗi 30 giây; muốn tắt hẳn thì đặt `BROADCAST_CONNECTION=log` và để trống `REVERB_APP_KEY` rồi dựng lại giao diện.
- Email: Laragon có sẵn Mailpit. Đặt `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, bật Mailpit trong Laragon rồi xem mọi email (xác minh, quên mật khẩu, mua thành công, báo cáo tuần) ở http://localhost:8025. Không có Mailpit thì giữ `MAIL_MAILER=log`, email ghi vào `storage/logs/laravel.log`.
- Trợ lý AI: dán khóa Gemini vào `GEMINI_API_KEY` trong `.env` (để trống thì dùng bộ giả lập), rồi chạy `php artisan edunest:ai-check` để kiểm khóa và tên mô hình. Đổi mô hình bằng `GEMINI_MODEL`.
- Chat với nhân viên: học viên mở hộp chat nổi góc phải, thẻ "Nhân viên"; Giáo vụ trả lời ở mục "Chat hỗ trợ" trên thanh bên. Cần `reverb:start` và `queue:work` để tin đến tức thời.
- Thời lượng video MP4 đọc bằng PHP, không cần cài ffmpeg. Chỉ tệp lạ mới thử `ffprobe` nếu máy có.

Tài khoản mẫu và dữ liệu demo: xem `docs/thiet-ke/huong-dan-kiem-tay.md` §2. Mật khẩu chung `matkhau123`. Tài khoản nhân sự phải thiết lập 2FA ở lần đăng nhập đầu.

## 3. Kịch bản demo

- Dữ liệu đủ cho đặc tả §14 sau `php artisan migrate:fresh --seed` (đã kiểm chạy sạch trên CSDL trống ngày 21/11/2026).
- Ngay trước đoạn demo phòng thi: `php artisan edunest:demo-exam --close-in=2` tạo kỳ thi có 30 bài nộp (một bài của giáo viên gắn "Không xếp hạng"), tự đóng và công bố sau 2 phút khi `schedule:work` đang chạy.
- Lượt quiz quá giờ: `an.nguyen` có một lượt "Kiểm tra chương 1" đã quá giờ còn mở; mở lại sẽ thấy tự chốt.

## 4. Kiểm tra tự động

```powershell
php vendor/bin/pest
```

Test cần CSDL MySQL `edunest_test` (xem `phpunit.xml`). Chạy song song nhiều bộ: đặt `DB_DATABASE=edunest_test_<tên>`. GitHub Actions (`.github/workflows/tests.yml`) dựng MySQL 8.4 làm dịch vụ và chạy lint, kiểu, toàn bộ test.

## 5. Triển khai lên máy chủ

1. Máy chủ Linux có Nginx + PHP-FPM 8.3, MySQL 8.4, Supervisor. Thư mục gốc web trỏ vào `edunest/public`.
2. `.env` sản phẩm:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<tên miền>`.
   - `SESSION_SECURE_COOKIE=true` (bắt buộc khi chạy HTTPS).
   - `APP_DEMO=false` để tắt nút mô phỏng thanh toán và chuyển khoản.
   - `PAYMENT_SIM_SECRET`, `BANK_SIM_SECRET`: chuỗi ngẫu nhiên riêng, không dùng giá trị mẫu.
   - `MAIL_*` theo dịch vụ email thật.
3. Lệnh triển khai:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

4. Supervisor giữ `php artisan queue:work --queue=grants,default,media --tries=3` (và `reverb:start` nếu dùng). Cron chạy `* * * * * php /đường-dẫn/edunest/artisan schedule:run`.
5. Tạo Quản trị đầu tiên: `php artisan edunest:admin:create <email>` (không truyền `--password` để lệnh hỏi mật khẩu tại chỗ, tránh lưu vào lịch sử lệnh).
6. Kiểm tra sau triển khai: `php artisan edunest:ops-status` (hàng đợi, lịch, nhịp worker), mở `/up`.

## 6. Những phần cần khóa thật (chưa cấu hình)

| Phần | Hiện tại | Cần để chạy thật |
|---|---|---|
| Trợ lý AI | Bộ giả lập nội bộ | Khóa nhà cung cấp, cài lớp `AiProvider` mới (CR-239) |
| Chuyển khoản | Ngân hàng mô phỏng, QR mô phỏng | Tài khoản ngân hàng, cổng đối soát, chuẩn VietQR (CR-247) |
| Thông báo đẩy | Service worker có sẵn trình nhận | Khóa VAPID, thư viện web-push (CR-251) |
| PDF chứng nhận, hóa đơn | Bản dự phòng | `composer require barryvdh/laravel-dompdf` (CR-209) |
