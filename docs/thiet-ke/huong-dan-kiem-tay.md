# Hướng dẫn kiểm tay EduNest (P0–P4)

| Mục | Giá trị |
|---|---|
| Phạm vi | Mốc P0–P4 theo [09](09-ke-hoach-xay-dung.md) |
| Mã nguồn | Thư mục gốc của repo |
| Mật khẩu | Mọi tài khoản demo dùng mật khẩu seed của dự án `matkhau123` (hằng `DemoSeeder::PASSWORD`). Không dùng mật khẩu thật. |

## 1. Chạy ứng dụng

Yêu cầu: PHP 8.3 (Laragon `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64`), MySQL 8.4 chạy ở `127.0.0.1:3306`, Node 22+.

Mỗi lệnh chạy trong thư mục `edunest`, mỗi tiến trình dài một cửa sổ riêng.

```powershell
composer install
```

```powershell
npm install
```

```powershell
php artisan migrate:fresh --seed
```

```powershell
npm run build
```

```powershell
php artisan serve --port=8000
```

```powershell
php artisan queue:work database --queue=grants,default,media
```

```powershell
php artisan schedule:work
```

- Mở `http://localhost:8000`. Cổng phải khớp `APP_URL` trong `.env` (mặc định `http://localhost:8000`) vì cổng thanh toán mô phỏng gửi xác nhận tới webhook theo `APP_URL`.
- Hàng đợi (`queue:work`) cần cho: gửi xác nhận thanh toán (TV-04), cấp quyền (TV-01), đọc thời lượng video (TV-07), tạo PDF chứng nhận (TV-06). Lịch (`schedule:work`) cần cho: hết hạn đơn, phục hồi cấp quyền, chốt lượt quiz quá giờ, phục hồi chứng nhận, báo cáo đêm 02:00.
- Bật tùy chọn demo "chế độ lỗi cấp quyền" (MH-QT-04): đặt `APP_DEMO=true` trong `.env` rồi chạy lại `php artisan serve`.
- Lệnh xem tình trạng lịch/hàng đợi: `php artisan edunest:ops-status` (xem `php artisan list edunest` và `php artisan list learning`).
- PDF chứng nhận: nếu chưa cài Dompdf, hệ thống dùng bản PDF dự phòng (chữ không dấu). Cài Dompdf để có phông tiếng Việt: `composer require barryvdh/laravel-dompdf`.

## 2. Tài khoản seed

| Vai | Email | Ghi chú |
|---|---|---|
| Quản trị | `quang@edunest.test`, `dung@edunest.test` | Hai Quản trị (thử chặn khóa Quản trị cuối). Lần đầu đăng nhập nhân sự phải thiết lập 2FA. |
| Giáo vụ | `lan.pham@edunest.test`, `minh@edunest.test` | Minh dùng làm người duyệt độc lập. |
| Giáo viên | `hoa.tran@edunest.test`, `binh.gv@edunest.test` | Có hồ sơ công khai. |
| Học viên | `an.nguyen@edunest.test`, `chi.le@edunest.test` | An có sẵn các đơn demo P3. Chi là cộng tác viên (P8). |
| Phụ huynh (P8) | `phu.huynh@edunest.test` | Đã liên kết với An. |
| Học viên (P4) | `dang-hoc.p4@`, `het-han.p4@`, `da-tao.p4@`, `cho-tao.p4@`, `pdf-loi.p4@`, `thu-hoi.p4@` + `edunest.test` | Ghi danh khóa "Hóa 12 nền tảng": đang học, hết hạn, chứng nhận đã tạo/đang tạo/lỗi PDF/đã thu hồi. |

Mật khẩu tất cả: `matkhau123`.

Dữ liệu demo đáng chú ý:
- Khóa: "Hóa 12 nền tảng" (đang bán, có bài xem thử, có bản thay thế ở P4), "Vật lý 12" (chờ duyệt), "Hóa 11 hữu cơ" (quá hạn duyệt), 14 khóa danh mục (một khóa ngừng bán "Hóa học vui cho học sinh").
- Đơn của An: `EDN-DMXXXX2345` hoàn tất, `EDN-DMHHHH2345` hết hạn, `EDN-DMSSSS2345` sai tiền, `EDN-DMCCCC2345` cấp lỗi, `EDN-DMRRRR2345` cần hoàn (khoản muộn).

## 3. Danh mục kiểm tay

Đánh dấu từng ô khi đạt. Ghi lỗi kèm tài khoản, URL và bước.

### P0 — Tài khoản, điều hướng, danh mục nền
- [ ] Đăng ký học viên mới, nhận thư xác minh (log mail), đăng nhập; chưa xác minh thì bị chặn trang cần xác minh.
- [ ] Đăng nhập từng vai, thấy đúng menu theo vai; vai khác URL trả 403.
- [ ] Giáo vụ `lan.pham` tạo tài khoản Giáo viên; Giáo viên đăng nhập bằng mật khẩu tạm, bị buộc đổi mật khẩu.
- [ ] Nhân sự đăng nhập lần đầu bị buộc thiết lập 2FA; Quản trị reset 2FA của một nhân sự (có nhật ký).
- [ ] Quản trị khóa tài khoản: phiên đang mở bị đăng xuất; không khóa được Quản trị cuối cùng.
- [ ] Quản lý danh mục/cấp độ/thẻ: tạo, đổi tên, xóa mềm, khôi phục; không xóa được nhãn đang dùng.
- [ ] Hồ sơ giáo viên công khai `/teachers/{id}` hiển thị đúng ở 390px.

### P1 — Soạn, duyệt, công bố, bật bán
- [ ] Giáo vụ giao soạn khóa mới cho `hoa.tran`.
- [ ] Giáo viên soạn chương/bài (video, bài đọc, PDF, quiz), tự lưu 30 giây, rời trang có cảnh báo; tải MP4 → thời lượng tự đọc (cần `queue:work`).
- [ ] Ngân hàng câu hỏi: tạo câu, gắn vào quiz; gửi duyệt chụp câu tại thời điểm gửi.
- [ ] Gửi duyệt bị chặn khi video chưa có thời lượng hoặc thiếu nội dung bắt buộc.
- [ ] `lan.pham` (đã sửa khóa) không duyệt được (E6); `minh` duyệt độc lập: trả lại có bình luận, hoặc duyệt.
- [ ] Giáo vụ công bố rồi bật bán; ngừng bán, bán lại; nhật ký ghi đủ.
- [ ] Thay MP4 ở bản nháp: thời lượng cũ bị xóa và đọc lại.

### P2 — Danh mục công khai và học thử
- [ ] Trang chủ: khóa nổi bật, mới, giáo viên, số liệu.
- [ ] `/courses`: tìm theo tên/giáo viên, gợi ý khi gõ ≥ 2 ký tự (mũi tên/Enter/Esc), lọc danh mục/cấp độ/giáo viên/giá, sắp xếp, phân trang 12.
- [ ] Trang khóa: đề cương, bài trả phí chỉ có tên và số phút, bài xem thử có "Xem thử"; khóa ngừng bán không có "Mua ngay".
- [ ] Xem thử video/PDF: URL ký 15 phút; mở URL tệp sau khi hết hạn → 403.
- [ ] Truy cập thẳng khóa chưa bán, bài không xem thử, tệp riêng → 404/403.
- [ ] 390px không cuộn ngang; bật "giảm chuyển động" của hệ điều hành → không có hiệu ứng.
- [ ] `/sitemap.xml` liệt kê khóa đang bán.

### P3 — Mua, thanh toán mô phỏng, cấp quyền
- [ ] Khách bấm "Mua ngay" → đăng nhập → quay về trang xác nhận đặt mua.
- [ ] `an.nguyen` (hoặc `chi.le`) đặt mua khóa chưa có → trang đơn có đếm ngược 30 phút.
- [ ] Mở hai tab cùng đặt một khóa → chỉ có một đơn mở.
- [ ] Tick điều khoản → Thanh toán → cổng mô phỏng → chọn "thành công" → (cần `queue:work`) đơn chuyển Đang cấp quyền → Hoàn tất; "Khóa của tôi" có khóa, trang khóa đổi CTA "Vào học".
- [ ] Chọn "thất bại" → đơn vẫn chờ thanh toán, thanh toán lại được bằng khoản mới.
- [ ] "Gửi lại xác nhận" không tạo khoản/ghi danh thứ hai.
- [ ] Hủy đơn chờ thanh toán; đơn quá 30 phút tự hết hạn (cần `schedule:work`), không thanh toán được nữa.
- [ ] Quản trị bật chế độ lỗi cấp quyền (cần `APP_DEMO=true`), mua một khóa → sau 4 lần thử (60/300/900 giây) dòng cấp lỗi, đơn Cần xử lý, hiện ở hàng chờ Giáo vụ; tắt lỗi, Giáo vụ "Cấp lại" → Hoàn tất.
- [ ] Giáo vụ xem đơn `EDN-DMSSSS2345` (sai tiền): khoản Cần hoàn; "Đã hoàn" bắt buộc lý do, hoàn đúng số thực thu.
- [ ] Đơn `EDN-DMRRRR2345` (khoản muộn): Giáo vụ "Chấp nhận khoản muộn" → cấp quyền; hoặc "Đã hoàn" → đơn về Hết hạn.
- [ ] Lịch sử thao tác của đơn chỉ hiện bản ghi của đơn đó; Giáo viên mở → 403.

### P4 — Học, quiz, chứng nhận, báo cáo
- [ ] `dang-hoc.p4` mở bảng học tập: tiếp tục học, tiến độ; vào bài video, xem rồi đóng, mở lại tiếp tục đúng vị trí; hoàn thành video khi xem đủ.
- [ ] Học theo trình tự: bài sau bị khóa khi bài trước bắt buộc chưa xong (khóa học tuần tự).
- [ ] Phiên gắn thiết bị lúc đăng nhập: chép URL video sang trình duyệt khác (cookie khác) → 403; hai tab cùng trình duyệt dùng chung được. (Giới hạn số thiết bị là P5.)
- [ ] Làm quiz: lưu câu, tải lại trang giữ câu trả lời; để hết giờ → lượt tự chốt (cần `schedule:work`); nộp quá giờ bị từ chối; hết lượt thì không làm thêm.
- [ ] Giáo viên (MH-GV-11) hoặc Giáo vụ cấp thêm một lượt cho học viên → học viên làm thêm được đúng một lượt.
- [ ] Hoàn thành mọi bài bắt buộc + quiz cuối đạt → ghi danh Hoàn thành, chứng nhận được tạo, PDF xuất hiện (cần `queue:work`).
- [ ] Mở `/certificates/verify` nhập mã hoặc quét QR → trang xác minh hợp lệ.
- [ ] `het-han.p4`: khóa hiện "Đã hết hạn truy cập", không mở được bài; lịch sử điểm vẫn xem được.
- [ ] Giáo vụ thu hồi chứng nhận (có lý do) → trang xác minh báo đã thu hồi, URL PDF cũ bị chặn (`thu-hoi.p4` là mẫu sẵn).
- [ ] `pdf-loi.p4`: chứng nhận lỗi PDF → Giáo vụ "Tạo lại PDF" → có tệp.
- [ ] Giáo vụ: danh sách học viên/ghi danh (hiệu lực tính theo giờ hiện tại), báo cáo học tập (số liệu đêm có giờ tính), chứng nhận.
- [ ] Giáo viên: báo cáo quiz (điểm trung bình, câu sai nhiều).
- [ ] Quản trị: doanh thu, nhật ký hệ thống (chỉ đọc); Giáo vụ mở nhật ký hệ thống → 403.
- [ ] Chạy tay tổng hợp báo cáo: `php artisan learning:aggregate-daily-report` (cần `queue:work`), hoặc chờ 02:00.

### P5 — Giỏ, mã giảm giá, mã kích hoạt, gia hạn, đánh giá, thông báo, thiết bị
Dữ liệu mẫu P5: khóa "Tiếng Anh giao tiếp cơ bản" đang khuyến mãi 10 ngày; mã giảm giá `CHAOMUNG20` (20%, tối đa 150.000đ) và `GIAM50K` (đơn từ 300.000đ); một lô 20 mã kích hoạt (Giáo vụ xem ở "Mã kích hoạt"). Chạy lại `php artisan db:seed --class=CommerceDemoSeeder` nếu thiếu.
- [ ] Trang khóa đang khuyến mãi: giá mới, giá cũ gạch ngang, đếm ngược tới 00:00 sau ngày cuối; danh mục cũng hiện giá khuyến mãi.
- [ ] `chi.le` thêm hai khóa vào giỏ, bỏ một khóa, lưu yêu thích; giỏ hiện tạm tính; "Thanh toán" tạo một đơn nhiều dòng.
- [ ] Ở trang đơn: nhập `chaomung20` (chữ thường vẫn nhận) → tạm tính, giảm giá, tổng mới, từng dòng có "Giảm từ mã"; nhập lần hai bị từ chối (một mã mỗi đơn); mã sai báo lỗi dưới ô.
- [ ] Thanh toán mô phỏng thành công (cần `queue:work`) → đơn Hoàn tất, dòng "Hóa đơn" tải được PDF `HD-2026-…`; chuông có số chưa đọc, trang Thông báo có "Thanh toán thành công".
- [ ] Để đơn có mã hết hạn (hoặc hủy) → lượt mã được trả: Giáo vụ xem cột "Đã dùng / giữ chỗ / tổng" ở "Mã giảm giá".
- [ ] Nhập mã kích hoạt ở "Nhập mã kích hoạt" → ghi danh mới, hạn = hôm nay + số tháng; nhập sai 5 lần → bị khóa, thông báo giờ thử lại.
- [ ] Giáo vụ tạo lô mã, xuất CSV, vô hiệu một mã và cả lô (bắt buộc lý do).
- [ ] Khóa của tôi: ghi danh có hạn và khóa có giá gia hạn hiện "Gia hạn thêm"/"Gia hạn để học tiếp" → tạo đơn gia hạn một dòng, không áp được mã; thanh toán xong hạn cộng đúng số tháng.
- [ ] Giáo vụ ở hồ sơ học viên: "Thu hồi" (lý do) → học viên mất quyền vào bài; "Khôi phục" → hạn giữ nguyên.
- [ ] Học viên đã học ≥ 30% bài bắt buộc: tab Tổng quan của bài hoặc trang khóa có form đánh giá; gửi lần hai là sửa. Dưới 30% hiện số bài còn thiếu.
- [ ] Giáo viên ở "Đánh giá khóa học" trả lời; Giáo vụ ở "Kiểm duyệt" ẩn (lý do) → điểm trung bình tính lại, người viết thấy lý do; hiện lại.
- [ ] Đăng nhập cùng học viên trên 3 trình duyệt (hoặc cửa sổ ẩn danh): lần thứ ba sang trang chọn thiết bị để đăng xuất; "Thiết bị của tôi" đăng xuất thiết bị khác → thiết bị đó mất phiên và URL video.
- [ ] Video bài học hiện email học viên mờ, đổi chỗ mỗi 30 giây.
- [ ] Lệnh nền: `php artisan edunest:commerce reminders|promotions|codes` (nhắc hết hạn 7/1 ngày, báo khóa yêu thích giảm giá, hết hạn mã kích hoạt); chạy lại không gửi trùng.

### P6 — Hỏi đáp, ghi chú, thi thử, phân tích, chuyển phiên bản
Dữ liệu mẫu P6 (`ExamDemoSeeder`, chạy lại `php artisan db:seed --class=ExamDemoSeeder` trên CSDL chưa có ngân hàng bảo mật): ngân hàng "Đề thi (bảo mật)" 6 câu đủ loại ở "Hóa 12 nền tảng"; kỳ "tuần 1" đã công bố (An và Chi đồng ý công khai tên), kỳ "tuần 2" đang mở, kỳ "cuối kỳ" sắp mở bắt buộc đăng ký; hai câu hỏi và một ghi chú ở bài video đầu. Cần `php artisan schedule:work` (hoặc `php artisan edunest:exams` chạy tay) để kỳ tự mở, đóng, công bố.
- [ ] `dang-hoc.p4` vào bài video đầu của "Hóa 12 nền tảng" → tab "Hỏi đáp": thấy câu đã chấp nhận (viền xanh) và câu chưa trả lời; gửi câu hỏi có tích "Gắn mốc video" → mốc giây hiện cạnh câu hỏi, bấm vào thì video tua tới đó.
- [ ] Học viên khác của khóa bấm "Cũng thắc mắc" (bấm lại là bỏ); người hỏi chấp nhận / bỏ chấp nhận một trả lời.
- [ ] Tab "Ghi chú": thêm, sửa, xóa ghi chú; ghi chú có mốc giây bấm để tua. Ghi danh hết hạn (`het-han.p4`) đọc được nhưng không có ô viết.
- [ ] `hoa.tran` ở "Hỏi đáp": mục "Chờ trả lời" xếp câu chờ lâu nhất trước; trả lời → người hỏi và người theo dõi nhận thông báo "Có trả lời hỏi đáp".
- [ ] `lan.pham` ở "Kiểm duyệt hỏi đáp": ẩn câu hỏi (bắt buộc lý do) → học viên không còn thấy; "Đã ẩn" → "Hiện lại" trả về trạng thái trước.
- [ ] `hoa.tran` ở ngân hàng câu hỏi: tải tệp mẫu, nhập tệp có một dòng sai → báo lỗi theo dòng và không nhập câu nào; sửa xong nhập lại → đủ câu (điền từ, sắp xếp có trong mẫu). Ngân hàng đang dùng cho quiz bài học không bật được "Bảo mật".
- [ ] "Đề thi thử" → tạo đề từ ngân hàng bảo mật, chọn câu → "Kỳ thi" → tạo kỳ (giờ VN) → "Chuyển sang Sắp mở" (đề bị khóa, không sửa câu được nữa); "Dời lịch" chỉ hiện trước giờ mở.
- [ ] `chi.le` mở "Kỳ thi thử" → kỳ "tuần 2" → "Bắt đầu làm bài": đồng hồ theo máy chủ; trả lời câu chọn, câu điền từ (gõ `h2o` vẫn đúng), câu sắp xếp (mũi tên lên/xuống); "Lưu câu"; chuyển tab rồi quay lại → ghi nhận rời tab; "Nộp bài" (tích đồng ý công khai tên) → kết quả "Chờ công bố", chưa có đáp án.
- [ ] Vào kỳ "tuần 1" (đã công bố): bảng top 10 hiện tên người đồng ý, người khác "Ẩn danh", dòng của mình tô màu; "Xem kết quả" có điểm, hạng và đáp án từng câu.
- [ ] Trang chủ (khách) có khối "Vinh danh thi thử" của kỳ công bố gần nhất.
- [ ] Giáo viên/nhân sự dự thi được nhưng ghi "Không xếp hạng". GV người tổ chức thấy cột "Rời tab"; Giáo vụ không thấy cột này.
- [ ] Đặt giờ đóng kỳ đang mở sớm (sửa CSDL hoặc tạo kỳ ngắn) rồi `php artisan edunest:exams`: lượt đang làm chốt "Hết giờ · Đóng cửa sổ"; công bố gửi thông báo "Có kết quả kỳ thi".
- [ ] `hoa.tran` ở "Báo cáo quiz" của khóa: khối "Phân tích" có tiến độ, trung bình/trung vị, biểu đồ 10 nhóm, bảng từng câu; "Xuất Excel" tải `.xlsx`.
- [ ] `lan.pham` ở "Tổng quan": chọn khoảng ngày → doanh thu (mô phỏng), phễu xem → mua → bắt đầu → hoàn thành, khóa bán chạy, mã đã dùng, tỷ lệ gia hạn; "Xuất Excel".
- [ ] `lan.pham` ở trang khóa: đặt "Khóa tiên quyết" → học viên ghi danh mới chưa học bị chặn vào bài (báo tên khóa cần hoàn thành), người đang học dở vẫn học được; đặt vòng bị từ chối.
- [ ] Giáo viên tạo bản mới, gửi duyệt → Giáo vụ mở bản: khối "So với bản đang công bố" liệt kê bài thêm/bỏ/đổi nội dung/đổi tên.
- [ ] Sau khi công bố bản mới, học viên ở bài học thấy "Khóa học có phiên bản mới" + "Chuyển sang bản mới" → về "Khóa của tôi"; bài không đổi nội dung vẫn tính hoàn thành, quiz phải làm lại.

### P7 — Luyện tập, XP, chuỗi ngày, huy hiệu, thẻ ghi nhớ, mini game
Dữ liệu mẫu P7 (`PracticeDemoSeeder`, chạy lại `php artisan db:seed --class=PracticeDemoSeeder` trên CSDL chưa có hoạt động ở "Hóa 12 nền tảng"): hoạt động "Ôn nhanh chương 1" (ngẫu nhiên) và "Luyện điểm yếu chương 1"; bộ thẻ "Thuật ngữ Hóa 12" 8 thẻ; `dang-hoc.p4` có chuỗi 3 ngày, 4 thẻ đã chấm; `an.nguyen` có một phiên tuần này. Cần `php artisan schedule:work` (hoặc `php artisan edunest:practice` chạy tay) để chốt phiên im lặng và hạn câu Try Hard.
- [ ] `dang-hoc.p4` mở "Luyện tập" → "Ôn nhanh chương 1" → "Learning": sai lần 1 được thử lại, "Gợi ý" chỉ một lần; làm hết câu → trang kết quả có đáp án, lời giải và XP từng câu.
- [ ] Chọn "Try Hard": có đồng hồ câu và 3 mạng, không có gợi ý; để hết giờ một câu → câu "Hết giờ" và không mất mạng; sai 3 câu → phiên "Hết mạng", chỉ có XP câu.
- [ ] Đang làm phiên, bắt đầu lại cùng hoạt động → phiên cũ thành "Bỏ dở", 0 XP.
- [ ] "Thành tích": tổng XP, cấp và thanh tiến tới cấp sau, chuỗi 3 ngày (có thưởng 10 XP), lịch 30 ngày, huy hiệu, sổ XP; bảng xếp hạng tuần/tháng hiện biệt danh hoặc "Ẩn danh"; đặt biệt danh, bật "Hiện tên" → bảng đổi theo.
- [ ] "Thẻ ghi nhớ" → "Thuật ngữ Hóa 12": khối "Cần ôn hôm nay"; lật thẻ, bấm loa (thẻ tiếng Anh đọc giọng Anh), chấm Quên/Khó/Nhớ/Dễ → thẻ rời hàng, ngày ôn kế tiếp hiện đúng (lần đầu 1/1/3/4 ngày).
- [ ] "Bắn bong bóng": chọn nghĩa đúng trong tối đa 4 bong bóng; chơi hết → màn kết thúc "đã nhớ tốt / cần ôn lại" và XP thưởng phiên. "Mưa chữ": gõ mặt sau (không phân biệt hoa thường, phải đúng dấu); để chữ chạm đáy → hiện đáp án, không phạt. Chơi game không đổi lịch ôn.
- [ ] `hoa.tran` ở "Hoạt động luyện tập": tạo hoạt động (số câu 1–50, giây Try Hard 10–300), không chọn được ngân hàng bảo mật; lưu trữ hoạt động → học viên không còn thấy.
- [ ] `hoa.tran` ở "Bộ thẻ ghi nhớ": thêm thẻ có ảnh và ngôn ngữ phát âm; sửa mặt sau → học viên đang mở thẻ đó chấm thì bị báo "mở lại thẻ", lịch ôn của thẻ quay về mới.
- [ ] `quang` ở "Huy hiệu": sửa tên/ngưỡng, tắt một huy hiệu → người đã nhận vẫn giữ; tạo huy hiệu mới.
- [ ] Ghi danh hết hạn (`het-han.p4`): không bắt đầu phiên mới, không ôn thẻ, không chơi game. Thu hồi ghi danh hoặc khóa tài khoản khi đang làm phiên → phiên "Bỏ dở".

### P8 — Phụ huynh, combo, AI, buổi trực tiếp, cộng đồng, chuyển khoản, API, PWA
Dữ liệu mẫu P8: `phu.huynh@edunest.test` đã liên kết với `an.nguyen`; `dang-hoc.p4` có một mã mời còn hạn; "Combo Hóa học trọn bộ"; `chi.le` là cộng tác viên mã `chi-le` (10%); hai câu AI ở trạng thái Nháp trong ngân hàng của `hoa.tran`; một buổi trực tiếp ngày mai 19:00 và một buổi đã qua; hai bài cộng đồng, một báo cáo. Nhắc buổi trực tiếp và báo cáo tuần cần `php artisan schedule:work`. Cấp quyền sau thanh toán cần `php artisan queue:work --queue=grants,default,media`.
- [ ] `phu.huynh` đăng nhập → "Con của tôi": thấy An, mở xem tiến độ bài bắt buộc, điểm quiz, thi thử đã công bố, chuỗi ngày, hạn ghi danh; không có đáp án, đơn, ghi chú. Gỡ liên kết → không còn xem được.
- [ ] `dang-hoc.p4` ở "Phụ huynh" tạo mã mới; Khách mở `/register/guardian`, nhập mã sai → không tạo tài khoản; nhập mã đúng → tài khoản Phụ huynh liên kết ngay; dùng lại mã → bị từ chối.
- [ ] Khách mở `/bundles` → "Combo Hóa học trọn bộ": giá combo, khóa đã sở hữu (khi đăng nhập), số dự kiến trả. `chi.le` mở trang khóa với `?ref=chi-le` → không tự giới thiệu được; `an.nguyen` mua qua `?ref=chi-le`, thanh toán xong → `chi.le` ở "Tiếp thị liên kết" thấy hoa hồng chờ duyệt; Giáo vụ duyệt chi ở "Cộng tác viên".
- [ ] `an.nguyen` mở một bài đọc → "Hỏi AI về bài này": hỏi được, câu chứa `#loi-ai` mô phỏng nhà cung cấp lỗi và hiện thông báo lỗi; "Chuyển sang hỏi đáp" tạo câu hỏi ở tab Hỏi đáp. Khách mở "Tư vấn AI" (`/advisor`) hỏi về khóa; hết lượt trong ngày thì bị chặn. `quang` tắt AI ở Cấu hình → cả hai nơi báo AI đang tắt.
- [ ] `hoa.tran` ở ngân hàng câu hỏi: "AI gợi ý câu hỏi từ bài" chọn bài đọc → câu Nháp; Duyệt, Bỏ nháp, Khôi phục. Câu Nháp không vào quiz.
- [ ] `an.nguyen` ở "Buổi trực tiếp": buổi ngày mai chưa có nút vào; buổi đã qua có bản ghi (nếu GV đã tải). `hoa.tran` ở "Buổi trực tiếp": lên lịch, dời giờ, hủy có lý do → học viên thấy "Đã hủy"; tải bản ghi mp4.
- [ ] `an.nguyen` ở "Cộng đồng": đăng bài có ảnh và thẻ khóa; bài thứ 6 trong 60 phút bị chặn; bình luận, thích, báo cáo bài của `chi.le`. Sửa bài cần email đã xác minh.
- [ ] Giáo vụ ở "Kiểm duyệt cộng đồng": lọc "Có báo cáo", ẩn bài kèm lý do → tác giả thấy lý do, người khác mở link nhận 404; hiện lại.
- [ ] `an.nguyen` đặt mua một khóa → trang đơn có khối "Chuyển khoản qua QR": chưa tích điều khoản thì nút tắt; tích → QR, ngân hàng, số tài khoản, số tiền, nội dung = mã đơn, hạn bằng hạn đơn. Bấm "Mô phỏng: ngân hàng báo đã nhận tiền" → "Đang cấp quyền học" → (chạy hàng đợi) "Hoàn tất". 390px không cuộn ngang.
- [ ] Giáo vụ ở "Chuyển khoản chờ tra soát": giao dịch nội dung lệch hiện số tiền, nội dung đã che số điện thoại và email. Dữ liệu này chỉ sinh khi ngân hàng gửi nội dung lệch, đã có test `QT30`.
- [ ] API: theo `api-v1.md`, `POST /api/v1/tokens` bằng tài khoản Học viên mẫu → token; `GET /api/v1/me`, `/courses`; ở web "Thiết bị của tôi" có "Ứng dụng EduNest"; đăng xuất thiết bị đó → API trả 401.
- [ ] PWA: bản dựng (`npm run build`, không chạy Vite dev) → Chrome đề xuất cài; tắt mạng rồi tải lại trang → "Bạn đang ngoại tuyến".

## 4. Lệnh kiểm tự động

```powershell
php vendor/bin/pest
```

```powershell
php vendor/bin/phpstan analyse --memory-limit=1G
```

```powershell
npm run types:check
```

- Theo mốc: `php vendor/bin/pest --group=P3` (P0…P4). Test đồng thời: `--group=concurrency`.
- Test trình duyệt (Playwright) chỉ chạy khi đặt `EDUNEST_BROWSER=1`.
- Chạy song song nhiều bộ test: đặt `DB_DATABASE=edunest_test_<tên>` (CSDL phải tồn tại, tên bắt đầu `edunest_test`).
