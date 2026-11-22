<?php

use App\Http\Controllers\Academic;
use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\CoursePreviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Exams;
use App\Http\Controllers\Gateway;
use App\Http\Controllers\Guardian;
use App\Http\Controllers\PublicSite;
use App\Http\Controllers\StaffPreviewFileController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use App\Http\Controllers\Webhook;
use Illuminate\Support\Facades\Route;

/*
| 04 §2: nhóm công khai; nhóm `app` = auth + account.active + password.changed + staff.2fa.
| Tuyến nhân sự thêm `role:` (vai hiệu lực, dẫn tới thiết lập 2FA khi vai mới chưa hiệu lực).
*/

// QT-06 (P2): tuyến công khai, giới hạn tần suất chung (09 P2.2).
Route::middleware('throttle:public')->group(function () {
    Route::get('/', [PublicSite\CatalogController::class, 'home'])->name('home');
    Route::get('/about', [PublicSite\AboutController::class, 'show'])->name('public.about');
    Route::get('/teachers/{teacher}', [PublicSite\TeacherPageController::class, 'show'])
        ->whereNumber('teacher')->name('public.teachers.show');
    Route::get('/courses', [PublicSite\CatalogController::class, 'index'])->name('public.courses.index');
    Route::get('/courses/search/suggestions', [PublicSite\CatalogController::class, 'suggestions'])->name('public.courses.suggestions');
    Route::get('/sitemap.xml', [PublicSite\CatalogController::class, 'sitemap'])->name('public.sitemap');
    Route::get('/courses/{slug}', [PublicSite\CoursePageController::class, 'show'])->name('public.courses.show');
    // P8 — QT-26: combo công khai.
    Route::get('/bundles', [PublicSite\BundleController::class, 'index'])->name('public.bundles.index');
    Route::get('/bundles/{slug}', [PublicSite\BundleController::class, 'show'])->name('public.bundles.show');
    // P8 — QT-27c: tư vấn khóa học bằng AI (bộ giả lập).
    Route::get('/advisor', [PublicSite\AdvisorController::class, 'show'])->name('public.advisor.show');
    Route::get('/advisor/state', [PublicSite\AdvisorController::class, 'state'])->name('public.advisor.state');
    // P10 — M29 (CR-258): chat trực tiếp với nhân viên, Khách cũng nhắn được (khóa theo phiên trình duyệt).
    Route::get('/support/state', [Account\SupportChatController::class, 'state'])->name('account.support.state');
    Route::post('/support/messages', [Account\SupportChatController::class, 'send'])->middleware('throttle:10,1')->name('account.support.send');
    Route::post('/support/read', [Account\SupportChatController::class, 'read'])->name('account.support.read');
    Route::post('/advisor', [PublicSite\AdvisorController::class, 'ask'])->middleware('throttle:20,1')->name('public.advisor.ask');
    Route::get('/courses/{slug}/preview/{lesson}', [PublicSite\PreviewLessonController::class, 'show'])
        ->whereNumber('lesson')->name('public.lessons.preview');
    Route::middleware('signed')->group(function () {
        Route::get('/courses/{slug}/preview/{lesson}/video', [PublicSite\PreviewLessonController::class, 'video'])
            ->whereNumber('lesson')->name('public.lessons.preview.video');
        Route::get('/courses/{slug}/preview/{lesson}/pdf', [PublicSite\PreviewLessonController::class, 'pdf'])
            ->whereNumber('lesson')->name('public.lessons.preview.pdf');
        Route::get('/courses/{slug}/preview/{lesson}/resources/{resource}', [PublicSite\PreviewLessonController::class, 'resource'])
            ->whereNumber(['lesson', 'resource'])->name('public.lessons.preview.resources.show');
    });
});
// QT-11: chỉ yêu cầu tra số chứng nhận tính vào giới hạn theo IP.
Route::get('/certificates/verify', [PublicSite\CertificateVerifyController::class, 'verify'])->name('public.certificates.verify');
Route::get('/certificates/{number}', [PublicSite\CertificateVerifyController::class, 'show'])
    ->middleware('throttle:certificate-lookup')->name('public.certificates.show');
// QT-06 A6: Khách bấm "Mua ngay" → đăng nhập rồi về đúng trang khóa; P3 thay bằng đặt đơn.
Route::post('/webhooks/payment-sim', Webhook\PaymentSimulationController::class)->middleware('throttle:webhook')->name('webhooks.payment-sim');
// P10 (CR-260): MoMo bản thử — IPN và trang quay lại.
Route::post('/webhooks/momo', [Gateway\MoMoController::class, 'ipn'])->middleware('throttle:webhook')->name('webhooks.momo');
Route::get('/payments/momo/return', [Gateway\MoMoController::class, 'back'])->middleware('throttle:webhook')->name('payments.momo.return');
// P8 — QT-30: bộ mô phỏng ngân hàng (nguồn đối soát đã xác thực bằng HMAC).
Route::post('/webhooks/bank-sim/transactions', [Webhook\BankSimulationController::class, 'transaction'])->middleware('throttle:webhook')->name('webhooks.bank-sim.transactions');
Route::post('/webhooks/bank-sim/confirmations', [Webhook\BankSimulationController::class, 'confirmation'])->middleware('throttle:webhook')->name('webhooks.bank-sim.confirmations');

// MH-HV-17: đích của password.changed — chỉ trang này và đăng xuất mở được khi phải đổi mật khẩu.
Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('/account/password/required', [Account\PasswordController::class, 'required'])->name('account.password.required');
    Route::put('/account/password/required', [Account\PasswordController::class, 'updateRequired'])->name('account.password.required.update');
    // MH-HV-18: đích của staff.2fa — mở được khi bị buộc thiết lập 2FA.
    Route::get('/account/security', [Account\SecurityController::class, 'edit'])
        ->middleware('password.changed')->name('account.security.edit');
});

Route::middleware(['auth', 'account.active', 'password.changed', 'staff.2fa'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/course-versions/{version}/preview', CoursePreviewController::class)->name('course-versions.preview');
    Route::get('/course-versions/{version}/preview/lessons/{lesson}/files/{file}', StaffPreviewFileController::class)
        ->middleware('signed')->name('course-versions.preview.files');

    // P5 — MH-HV-14 thông báo, MH-HV-16 thiết bị (mọi vai có tài khoản).
    Route::get('/notifications', [Account\NotificationController::class, 'index'])->name('account.notifications.index');
    Route::get('/notifications/unread', [Account\NotificationController::class, 'unread'])->name('account.notifications.unread');
    Route::post('/notifications/read-all', [Account\NotificationController::class, 'readAll'])->name('account.notifications.read-all');
    Route::post('/notifications/{notification}/read', [Account\NotificationController::class, 'read'])->name('account.notifications.read');
    Route::put('/notifications/preferences', [Account\NotificationController::class, 'preferences'])->name('account.notifications.preferences');
    Route::post('/account/push-subscriptions', [Account\PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('account.push.store');
    Route::post('/account/push-subscriptions/delete', [Account\PushSubscriptionController::class, 'destroy'])->middleware('throttle:20,1')->name('account.push.destroy');
    Route::get('/account/devices', [Account\DeviceController::class, 'index'])->name('account.devices.index');
    Route::delete('/account/devices/{device}', [Account\DeviceController::class, 'destroy'])->whereNumber('device')->name('account.devices.destroy');

    Route::get('/account/profile', [Account\ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/account/profile', [Account\ProfileController::class, 'update'])->name('account.profile.update');
    Route::put('/account/security/password', [Account\PasswordController::class, 'update'])
        ->middleware('throttle:6,1')->name('account.security.password.update');

    // P6 — MH-HV-14/15: phòng thi thử cho mọi tài khoản đúng đối tượng (nhân sự dự thi "Không xếp hạng").
    Route::prefix('exams')->name('exams.')->group(function () {
        Route::get('/', [Exams\RoomController::class, 'index'])->name('index');
        Route::get('/{exam}', [Exams\RoomController::class, 'show'])->whereNumber('exam')->name('show');
        Route::post('/{exam}/register', [Exams\RoomController::class, 'register'])->name('register');
        Route::post('/{exam}/cancel', [Exams\RoomController::class, 'cancel'])->name('cancel');
        Route::post('/{exam}/consent', [Exams\RoomController::class, 'consent'])->name('consent');
        Route::post('/{exam}/start', [Exams\RoomController::class, 'start'])->name('start');
        Route::get('/attempts/{attempt}', [Exams\RoomController::class, 'attempt'])->name('attempts.show');
        Route::get('/attempts/{attempt}/result', [Exams\RoomController::class, 'result'])->name('attempts.result');
        Route::patch('/attempts/{attempt}/answers/{quizQuestion}', [Exams\RoomController::class, 'save'])->name('attempts.answers.update');
        Route::post('/attempts/{attempt}/submit', [Exams\RoomController::class, 'submit'])->name('attempts.submit');
        Route::post('/attempts/{attempt}/tab-leave', [Exams\RoomController::class, 'tabLeave'])->middleware('throttle:120,1')->name('attempts.tab-leave');
    });

    // P8 — QT-25 MH-PH-01/02: Phụ huynh chỉ đọc dữ liệu của con đã liên kết (BR-MR-04/05).
    // P8 — QT-29 E47: ảnh bài cộng đồng theo quyền đọc bài (Học viên) hoặc kiểm duyệt (Giáo vụ).
    Route::get('/community/posts/{post}/image', [Student\FeedController::class, 'image'])->name('community.image');
    Route::middleware(['role:PHU_HUYNH', 'verified'])->prefix('guardian')->name('guardian.')->group(function () {
        Route::get('/', [Guardian\GuardianController::class, 'index'])->name('dashboard');
        Route::post('/links', [Guardian\GuardianController::class, 'link'])->middleware('throttle:10,1')->name('links.store');
        Route::delete('/links/{link}', [Guardian\GuardianController::class, 'unlink'])->name('links.destroy');
        Route::get('/children/{student}', [Guardian\GuardianController::class, 'show'])->whereNumber('student')->name('children.show');
    });

    Route::middleware('role:HOC_VIEN')->group(function () {
        Route::get('/student', Student\DashboardController::class)->name('student.dashboard');
        // P8 — QT-26: đặt combo, trang cộng tác viên.
        Route::post('/student/bundles/{bundle}/orders', [PublicSite\BundleController::class, 'order'])->middleware('verified')->name('student.bundles.orders.store');
        Route::get('/student/affiliate', [Student\AffiliateController::class, 'index'])->name('student.affiliate.index');
        // P8 — QT-29: bảng tin cộng đồng.
        Route::get('/community', [Student\FeedController::class, 'index'])->name('student.feed.index');
        Route::post('/community/posts', [Student\FeedController::class, 'store'])->middleware('throttle:20,1')->name('student.feed.store');
        Route::get('/community/posts/{post}', [Student\FeedController::class, 'show'])->name('student.feed.show');
        Route::post('/community/posts/{post}', [Student\FeedController::class, 'update'])->name('student.feed.update');
        Route::post('/community/posts/{post}/comments', [Student\FeedController::class, 'comment'])->middleware('throttle:30,1')->name('student.feed.comments.store');
        Route::put('/community/posts/{post}/like', [Student\FeedController::class, 'like'])->name('student.feed.like');
        Route::post('/community/posts/{post}/report', [Student\FeedController::class, 'report'])->middleware('throttle:20,1')->name('student.feed.report');
        // P8 — QT-25 MH-HV-21: mã mời và liên kết phụ huynh.
        Route::get('/student/guardians', [Student\GuardianInviteController::class, 'index'])->name('student.guardians.index');
        Route::post('/student/guardians/invites', [Student\GuardianInviteController::class, 'store'])->middleware('throttle:10,1')->name('student.guardians.invites.store');
        Route::delete('/student/guardians/links/{link}', [Student\GuardianInviteController::class, 'unlink'])->name('student.guardians.links.destroy');
        Route::get('/courses/{slug}/buy', [Student\OrderController::class, 'checkout'])->middleware('verified')->name('public.courses.buy');
        Route::post('/student/courses/{slug}/orders', [Student\OrderController::class, 'store'])->middleware('verified')->name('student.orders.store');
        Route::get('/student/courses', [Student\CourseController::class, 'index'])->name('student.courses.index');
        Route::get('/student/orders', [Student\OrderController::class, 'index'])->name('student.orders.index');
        Route::get('/student/orders/{order}', [Student\OrderController::class, 'show'])->name('student.orders.show');
        Route::post('/student/orders/{order}/payments', [Student\OrderController::class, 'pay'])->middleware('verified')->name('student.orders.payments.store');
        Route::post('/student/orders/{order}/cancel', [Student\OrderController::class, 'cancel'])->name('student.orders.cancel');
        // P8 — QT-30: chuyển khoản QR.
        Route::post('/student/orders/{order}/transfer', [Student\BankTransferController::class, 'store'])->middleware('verified')->name('student.orders.transfer.store');
        Route::post('/student/orders/{order}/transfer/simulate', [Student\BankTransferController::class, 'simulate'])->middleware('verified')->name('student.orders.transfer.simulate');

        // P5 — QT-13/14/15/16: giỏ, yêu thích, mã giảm giá, mã kích hoạt, gia hạn, hóa đơn, đánh giá.
        Route::get('/student/cart', [Student\CommerceController::class, 'cart'])->name('student.cart.index');
        Route::post('/student/cart/courses/{course}', [Student\CommerceController::class, 'addToCart'])->name('student.cart.store');
        Route::delete('/student/cart/courses/{course}', [Student\CommerceController::class, 'removeFromCart'])->name('student.cart.destroy');
        Route::post('/student/cart/checkout', [Student\CommerceController::class, 'checkoutCart'])->middleware('verified')->name('student.cart.checkout');
        Route::get('/student/wishlist', [Student\CommerceController::class, 'wishlist'])->name('student.wishlist.index');
        Route::post('/student/wishlist/{course}', [Student\CommerceController::class, 'toggleWishlist'])->name('student.wishlist.toggle');
        Route::get('/student/activate', [Student\CommerceController::class, 'activation'])->name('student.activation.create');
        Route::post('/student/activate', [Student\CommerceController::class, 'redeem'])->middleware(['verified', 'throttle:30,1'])->name('student.activation.store');
        Route::post('/student/enrollments/{enrollment}/renew', [Student\CommerceController::class, 'renew'])->middleware('verified')->name('student.enrollments.renew');
        Route::post('/student/orders/{order}/coupon', [Student\CommerceController::class, 'applyCoupon'])->name('student.orders.coupon');
        Route::get('/student/orders/{order}/invoice', [Student\CommerceController::class, 'invoice'])->name('student.orders.invoice');
        Route::post('/student/courses/{course}/rating', [Student\CommerceController::class, 'rate'])->name('student.ratings.store');

        Route::prefix('student')->name('student.')->middleware('device.bound')->group(function () {
            Route::get('/courses/{course}/lessons/{lesson}', [Student\LessonController::class, 'show'])->name('lessons.show');
            Route::get('/courses/{course}/lessons/{lesson}/files/{file}', [Student\LessonController::class, 'stream'])
                ->middleware('signed')->name('lessons.files.stream');
            Route::post('/courses/{course}/lessons/{lesson}/watch-beats', [Student\LessonController::class, 'watch'])->name('lessons.watch-beats.store');
            Route::post('/courses/{course}/lessons/{lesson}/complete', [Student\LessonController::class, 'complete'])->name('lessons.complete');
            // P8 — QT-27b: hỏi AI trong bài, chuyển câu hỏi cho giáo viên.
            Route::get('/courses/{course}/lessons/{lesson}/ai', [Student\AiLessonController::class, 'show'])->name('lessons.ai.show');
            Route::post('/courses/{course}/lessons/{lesson}/ai', [Student\AiLessonController::class, 'ask'])->middleware('throttle:30,1')->name('lessons.ai.ask');
            Route::post('/ai/conversations/{conversation}/hand-off', [Student\AiLessonController::class, 'handOff'])->name('ai.hand-off');
            // P8 — QT-28: buổi học trực tiếp.
            Route::get('/live', [Student\LiveSessionController::class, 'index'])->name('live.index');
            Route::post('/live-sessions/{session}/join', [Student\LiveSessionController::class, 'join'])->name('live.join');
            Route::get('/live-recordings/{recording}', [Student\LiveSessionController::class, 'recording'])->name('live.recording');
            Route::get('/courses/{course}/quizzes/{quiz}', [Student\QuizController::class, 'show'])->name('quizzes.show');
            Route::get('/courses/{course}/quizzes/{quiz}/history', [Student\QuizController::class, 'history'])->name('quizzes.history');
            Route::post('/courses/{course}/quizzes/{quiz}/attempts', [Student\QuizController::class, 'start'])->name('quizzes.attempts.store');
            Route::get('/attempts/{attempt}', [Student\AttemptController::class, 'show'])->name('attempts.show');
            Route::get('/attempts/{attempt}/result', [Student\AttemptController::class, 'result'])->name('attempts.result');
            Route::patch('/attempts/{attempt}/answers/{quizQuestion}', [Student\AttemptController::class, 'save'])->name('attempts.answers.update');
            Route::post('/attempts/{attempt}/submit', [Student\AttemptController::class, 'submit'])->name('attempts.submit');
            Route::get('/certificates', [Student\CertificateController::class, 'index'])->name('certificates.index');
            // P6 — QT-18 hỏi đáp, ghi chú; QT-21 chuyển phiên bản.
            Route::post('/courses/{course}/lessons/{lesson}/discussions', [Student\CommunityController::class, 'ask'])->name('discussions.store');
            Route::post('/discussions/{discussion}/follow', [Student\CommunityController::class, 'follow'])->name('discussions.follow');
            Route::post('/discussions/{discussion}/replies/{reply}/accept', [Student\CommunityController::class, 'accept'])->scopeBindings()->name('discussions.accept');
            Route::delete('/discussions/{discussion}/accept', [Student\CommunityController::class, 'unaccept'])->name('discussions.unaccept');
            Route::post('/courses/{course}/lessons/{lesson}/notes', [Student\CommunityController::class, 'storeNote'])->name('notes.store');
            Route::patch('/notes/{note}', [Student\CommunityController::class, 'updateNote'])->name('notes.update');
            Route::delete('/notes/{note}', [Student\CommunityController::class, 'destroyNote'])->name('notes.destroy');
            Route::post('/enrollments/{enrollment}/switch-version', [Student\CommunityController::class, 'switchVersion'])->name('enrollments.switch-version');
            // P7 — QT-22 luyện tập, QT-23 thành tích, QT-24 thẻ ghi nhớ và mini game.
            Route::get('/practice', [Student\PracticeController::class, 'index'])->name('practice.index');
            Route::post('/practice/activities/{activity}/sessions', [Student\PracticeController::class, 'start'])->middleware('throttle:30,1')->name('practice.start');
            Route::get('/practice/sessions/{session}', [Student\PracticeController::class, 'show'])->name('practice.sessions.show');
            Route::get('/practice/sessions/{session}/result', [Student\PracticeController::class, 'result'])->name('practice.sessions.result');
            Route::post('/practice/sessions/{session}/answers', [Student\PracticeController::class, 'answer'])->middleware('throttle:120,1')->name('practice.sessions.answer');
            Route::post('/practice/sessions/{session}/hint', [Student\PracticeController::class, 'hint'])->name('practice.sessions.hint');
            Route::get('/achievements', [Student\AchievementController::class, 'index'])->name('achievements.index');
            Route::put('/achievements/profile', [Student\AchievementController::class, 'updateProfile'])->name('achievements.profile');
            Route::get('/flashcards', [Student\FlashcardController::class, 'index'])->name('flashcards.index');
            Route::get('/flashcards/sets/{set}', [Student\FlashcardController::class, 'review'])->name('flashcards.review');
            Route::post('/flashcards/{card}/grade', [Student\FlashcardController::class, 'grade'])->middleware('throttle:240,1')->name('flashcards.grade');
            Route::post('/flashcards/sets/{set}/games', [Student\FlashcardController::class, 'startGame'])->middleware('throttle:30,1')->name('games.start');
            Route::get('/games/{session}', [Student\FlashcardController::class, 'play'])->name('games.play');
            Route::post('/games/{session}/answers', [Student\FlashcardController::class, 'answerGame'])->middleware('throttle:240,1')->name('games.answer');
            Route::get('/games/{session}/result', [Student\FlashcardController::class, 'gameResult'])->name('games.result');
            Route::get('/certificates/{certificate}/pdf', [Student\CertificateController::class, 'download'])
                ->middleware('signed')->name('certificates.download');
        });

        Route::prefix('gateway/simulated')->name('gateway.simulated.')->group(function () {
            Route::get('/{payment}', [Gateway\SimulatedPaymentController::class, 'show'])->name('show');
            Route::post('/{payment}/confirm', [Gateway\SimulatedPaymentController::class, 'confirm'])->name('confirm');
            Route::post('/{payment}/resend', [Gateway\SimulatedPaymentController::class, 'resend'])->name('resend');
        });
    });

    Route::middleware('role:GIAO_VIEN')->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/', Teacher\DashboardController::class)->name('dashboard');
        Route::get('/profile', [Teacher\ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile', [Teacher\ProfileController::class, 'update'])->name('profile.update');

        // P1 — MH-GV-03..08: soạn bản nháp, gửi duyệt, ngân hàng câu hỏi.
        Route::get('/courses', [Teacher\CourseEditorController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}', [Teacher\CourseEditorController::class, 'show'])->name('courses.show');
        Route::get('/courses/{course}/quiz-report', [Teacher\QuizReportController::class, 'show'])->name('courses.quiz-report');
        Route::get('/courses/{course}/quiz-report/{quiz}/export', [Teacher\QuizReportController::class, 'export'])->name('courses.quiz-report.export');
        Route::get('/courses/{course}/attempts/{attempt}', [Teacher\AttemptResultController::class, 'show'])->name('courses.attempts.show');
        Route::post('/courses/{course}/enrollments/{enrollment}/quizzes/{quiz}/extra-attempts', [Teacher\ExtraAttemptController::class, 'store'])
            ->name('courses.enrollments.quizzes.extra-attempts.store');
        Route::post('/courses/{course}/versions', [Teacher\CourseEditorController::class, 'createDraft'])->name('courses.versions.store');
        Route::post('/courses/{course}/cover', [Teacher\CourseEditorController::class, 'cover'])->name('courses.cover');
        Route::post('/course-versions/{version}/sections', [Teacher\CourseEditorController::class, 'addSection'])->name('course-versions.sections.store');
        Route::post('/course-versions/{version}/submit', [Teacher\CourseEditorController::class, 'submit'])->name('course-versions.submit');
        Route::post('/course-versions/{version}/withdraw', [Teacher\CourseEditorController::class, 'withdraw'])->name('course-versions.withdraw');
        Route::post('/course-versions/{version}/reopen', [Teacher\CourseEditorController::class, 'reopen'])->name('course-versions.reopen');
        Route::patch('/sections/{section}', [Teacher\CourseEditorController::class, 'updateSection'])->name('sections.update');
        Route::post('/sections/{section}/lessons', [Teacher\CourseEditorController::class, 'addLesson'])->name('sections.lessons.store');
        Route::patch('/lessons/{lesson}', [Teacher\CourseEditorController::class, 'updateLesson'])->name('lessons.update');
        Route::post('/lessons/{lesson}/video', [Teacher\CourseEditorController::class, 'video'])->name('lessons.video');
        Route::post('/lessons/{lesson}/pdf', [Teacher\CourseEditorController::class, 'pdf'])->name('lessons.pdf');
        Route::post('/lessons/{lesson}/resources', [Teacher\CourseEditorController::class, 'resource'])->name('lessons.resources.store');
        Route::put('/lessons/{lesson}/quiz', [Teacher\CourseEditorController::class, 'quiz'])->name('lessons.quiz.update');
        Route::put('/lessons/{lesson}/quiz/questions', [Teacher\CourseEditorController::class, 'quizQuestions'])->name('lessons.quiz.questions');
        Route::post('/content/{kind}/{id}/move', [Teacher\CourseEditorController::class, 'move'])->whereNumber('id')->name('content.move');
        Route::delete('/content/{kind}/{id}', [Teacher\CourseEditorController::class, 'remove'])->whereNumber('id')->name('content.destroy');

        Route::get('/courses/{course}/question-banks', [Teacher\QuestionBankController::class, 'index'])->name('courses.question-banks.index');
        Route::post('/courses/{course}/question-banks', [Teacher\QuestionBankController::class, 'store'])->name('courses.question-banks.store');
        Route::get('/question-banks/{bank}', [Teacher\QuestionBankController::class, 'show'])->name('question-banks.show');
        Route::get('/reviews', [Teacher\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{rating}/reply', [Teacher\ReviewController::class, 'reply'])->name('reviews.reply');
        Route::patch('/question-banks/{bank}', [Teacher\QuestionBankController::class, 'update'])->name('question-banks.update');
        Route::post('/question-banks/{bank}/questions', [Teacher\QuestionBankController::class, 'storeQuestion'])->name('question-banks.questions.store');
        Route::post('/question-banks/{bank}/import', [Teacher\QuestionBankController::class, 'import'])->name('question-banks.import');
        Route::get('/question-banks-template', [Teacher\QuestionBankController::class, 'template'])->name('question-banks.template');
        Route::patch('/question-banks/{bank}/confidential', [Teacher\QuestionBankController::class, 'confidential'])->name('question-banks.confidential');
        Route::post('/questions/{question}', [Teacher\QuestionBankController::class, 'updateQuestion'])->name('questions.update');
        Route::post('/questions/{question}/deactivate', [Teacher\QuestionBankController::class, 'deactivate'])->name('questions.deactivate');
        Route::post('/questions/{question}/reactivate', [Teacher\QuestionBankController::class, 'reactivate'])->name('questions.reactivate');
        // P8 M26 (QT-28): buổi học trực tiếp.
        Route::get('/live-sessions', [Teacher\LiveSessionController::class, 'index'])->name('live-sessions.index');
        Route::post('/live-sessions', [Teacher\LiveSessionController::class, 'store'])->name('live-sessions.store');
        Route::patch('/live-sessions/{session}', [Teacher\LiveSessionController::class, 'update'])->name('live-sessions.update');
        Route::post('/live-sessions/{session}/cancel', [Teacher\LiveSessionController::class, 'cancel'])->name('live-sessions.cancel');
        Route::post('/live-sessions/{session}/recording', [Teacher\LiveSessionController::class, 'recording'])->name('live-sessions.recording');
        // P8 M25 (QT-27a): AI gợi ý câu nháp, duyệt, bỏ nháp, khôi phục.
        Route::post('/question-banks/{bank}/ai', [Teacher\AiQuestionController::class, 'generate'])->name('question-banks.ai.generate');
        Route::post('/questions/{question}/ai/approve', [Teacher\AiQuestionController::class, 'approve'])->name('questions.ai.approve');
        Route::post('/questions/{question}/ai/discard', [Teacher\AiQuestionController::class, 'discard'])->name('questions.ai.discard');
        Route::post('/questions/{question}/ai/restore', [Teacher\AiQuestionController::class, 'restore'])->name('questions.ai.restore');

        // P6 — MH-GV-12..14: hỏi đáp, đề thi thử, kỳ thi.
        Route::get('/discussions', [Teacher\DiscussionController::class, 'index'])->name('discussions.index');
        Route::post('/discussions/{discussion}/replies', [Teacher\DiscussionController::class, 'reply'])->name('discussions.reply');
        Route::post('/discussions/{discussion}/replies/{reply}/accept', [Teacher\DiscussionController::class, 'accept'])->scopeBindings()->name('discussions.accept');
        Route::delete('/discussions/{discussion}/accept', [Teacher\DiscussionController::class, 'unaccept'])->name('discussions.unaccept');
        Route::get('/exam-papers', [Teacher\ExamPaperController::class, 'index'])->name('exam-papers.index');
        Route::get('/exam-papers/create', [Teacher\ExamPaperController::class, 'create'])->name('exam-papers.create');
        Route::post('/exam-papers', [Teacher\ExamPaperController::class, 'store'])->name('exam-papers.store');
        Route::get('/exam-papers/{quiz}/edit', [Teacher\ExamPaperController::class, 'edit'])->name('exam-papers.edit');
        Route::put('/exam-papers/{quiz}', [Teacher\ExamPaperController::class, 'update'])->name('exam-papers.update');
        Route::get('/exams/{exam}/export', [Exams\ManageController::class, 'export'])->name('exams.export');
        Route::get('/exams', [Exams\ManageController::class, 'index'])->name('exams.index');
        Route::get('/exams/create', [Exams\ManageController::class, 'create'])->name('exams.create');
        Route::post('/exams', [Exams\ManageController::class, 'store'])->name('exams.store');
        Route::get('/exams/{exam}', [Exams\ManageController::class, 'show'])->name('exams.show');
        Route::get('/exams/{exam}/edit', [Exams\ManageController::class, 'edit'])->name('exams.edit');
        Route::put('/exams/{exam}', [Exams\ManageController::class, 'update'])->name('exams.update');
        Route::post('/exams/{exam}/schedule', [Exams\ManageController::class, 'schedule'])->name('exams.schedule');
        Route::post('/exams/{exam}/reschedule', [Exams\ManageController::class, 'reschedule'])->name('exams.reschedule');
        Route::put('/exams/{exam}/publish-at', [Exams\ManageController::class, 'publishAt'])->name('exams.publish-at');
        // P7 — MH-GV-15/16: hoạt động luyện tập, bộ thẻ ghi nhớ.
        Route::get('/activities', [Teacher\PracticeContentController::class, 'activities'])->name('activities.index');
        Route::get('/activities/create', [Teacher\PracticeContentController::class, 'createActivity'])->name('activities.create');
        Route::post('/activities', [Teacher\PracticeContentController::class, 'storeActivity'])->name('activities.store');
        Route::get('/activities/{activity}/edit', [Teacher\PracticeContentController::class, 'editActivity'])->name('activities.edit');
        Route::put('/activities/{activity}', [Teacher\PracticeContentController::class, 'updateActivity'])->name('activities.update');
        Route::post('/activities/{activity}/archive', [Teacher\PracticeContentController::class, 'archiveActivity'])->name('activities.archive');
        Route::get('/flashcard-sets', [Teacher\PracticeContentController::class, 'sets'])->name('flashcard-sets.index');
        Route::post('/flashcard-sets', [Teacher\PracticeContentController::class, 'storeSet'])->name('flashcard-sets.store');
        Route::get('/flashcard-sets/{set}', [Teacher\PracticeContentController::class, 'showSet'])->name('flashcard-sets.show');
        Route::put('/flashcard-sets/{set}', [Teacher\PracticeContentController::class, 'updateSet'])->name('flashcard-sets.update');
        Route::post('/flashcard-sets/{set}/archive', [Teacher\PracticeContentController::class, 'archiveSet'])->name('flashcard-sets.archive');
        Route::post('/flashcard-sets/{set}/cards', [Teacher\PracticeContentController::class, 'storeCard'])->name('flashcard-sets.cards.store');
        Route::post('/flashcards/{card}', [Teacher\PracticeContentController::class, 'updateCard'])->name('flashcards.update');
        Route::delete('/flashcards/{card}', [Teacher\PracticeContentController::class, 'destroyCard'])->name('flashcards.destroy');
    });

    Route::middleware('role:GIAO_VU')->prefix('academic')->name('academic.')->group(function () {
        Route::get('/', Academic\DashboardController::class)->name('dashboard');
        Route::get('/orders', [Academic\OrderController::class, 'index'])->name('orders.index');
        // P8 — QT-30 A4: bản chuyển khoản chờ tra soát.
        Route::get('/bank-reviews', [Academic\BankReviewController::class, 'index'])->name('bank-reviews.index');
        // P10 — M29: hộp thư chat hỗ trợ.
        Route::get('/support', [Academic\SupportInboxController::class, 'index'])->name('support.index');
        Route::post('/support/{thread}/messages', [Academic\SupportInboxController::class, 'reply'])->middleware('throttle:60,1')->name('support.reply');
        Route::post('/support/{thread}/close', [Academic\SupportInboxController::class, 'close'])->name('support.close');
        // P8 — QT-29: kiểm duyệt cộng đồng.
        Route::get('/community', [Academic\CommunityModerationController::class, 'index'])->name('community.index');
        Route::post('/community/posts/{post}/moderate', [Academic\CommunityModerationController::class, 'post'])->name('community.posts.moderate');
        Route::post('/community/comments/{comment}/moderate', [Academic\CommunityModerationController::class, 'comment'])->name('community.comments.moderate');
        Route::get('/orders/{order}', [Academic\OrderController::class, 'show'])->name('orders.show');
        Route::scopeBindings()->group(function () {
            Route::post('/orders/{order}/items/{item}/regrant', [Academic\OrderController::class, 'regrant'])->name('orders.items.regrant');
            Route::post('/orders/{order}/payments/{payment}/accept-late', [Academic\OrderController::class, 'acceptLate'])->name('orders.payments.accept-late');
            Route::post('/orders/{order}/payments/{payment}/mark-refunded', [Academic\OrderController::class, 'markRefunded'])->name('orders.payments.mark-refunded');
        });
        Route::get('/teachers', [Academic\TeacherController::class, 'index'])->name('teachers.index');
        Route::get('/enrollments', [Academic\EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('/students/{user}', [Academic\StudentController::class, 'show'])->name('students.show');
        Route::post('/enrollments/{enrollment}/quizzes/{quiz}/extra-attempts', [Academic\EnrollmentController::class, 'grant'])
            ->name('enrollments.quizzes.extra-attempts.store');
        Route::post('/enrollments/{enrollment}/revoke', [Academic\CommerceController::class, 'revoke'])->name('enrollments.revoke');
        Route::post('/enrollments/{enrollment}/restore', [Academic\CommerceController::class, 'restore'])->name('enrollments.restore');
        Route::post('/orders/{order}/payments/{payment}/stop-grant-refund', [Academic\CommerceController::class, 'stopGrantRefund'])
            ->scopeBindings()->name('orders.payments.stop-grant-refund');
        // P5 — MH-GVU-12..15.
        Route::get('/promotions', [Academic\CommerceController::class, 'promotions'])->name('promotions.index');
        Route::post('/promotions', [Academic\CommerceController::class, 'savePromotion'])->name('promotions.store');
        Route::put('/promotions/{promotion}', [Academic\CommerceController::class, 'savePromotion'])->name('promotions.update');
        // P8 — QT-26: combo, cộng tác viên, hoa hồng.
        Route::get('/bundles', [Academic\BundleAffiliateController::class, 'bundles'])->name('bundles.index');
        Route::post('/bundles', [Academic\BundleAffiliateController::class, 'saveBundle'])->name('bundles.store');
        Route::put('/bundles/{bundle}', [Academic\BundleAffiliateController::class, 'saveBundle'])->name('bundles.update');
        Route::post('/bundles/{bundle}/accepting', [Academic\BundleAffiliateController::class, 'toggleBundle'])->name('bundles.accepting');
        Route::get('/affiliates', [Academic\BundleAffiliateController::class, 'affiliates'])->name('affiliates.index');
        Route::post('/affiliates', [Academic\BundleAffiliateController::class, 'saveAffiliate'])->name('affiliates.store');
        Route::put('/affiliates/{affiliate}', [Academic\BundleAffiliateController::class, 'saveAffiliate'])->name('affiliates.update');
        Route::post('/commissions/{commission}/pay', [Academic\BundleAffiliateController::class, 'payCommission'])->name('commissions.pay');
        Route::get('/coupons', [Academic\CommerceController::class, 'coupons'])->name('coupons.index');
        Route::post('/coupons', [Academic\CommerceController::class, 'saveCoupon'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [Academic\CommerceController::class, 'saveCoupon'])->name('coupons.update');
        Route::get('/activation-batches', [Academic\CommerceController::class, 'batches'])->name('activation-batches.index');
        Route::post('/activation-batches', [Academic\CommerceController::class, 'storeBatch'])->name('activation-batches.store');
        Route::get('/activation-batches/{batch}', [Academic\CommerceController::class, 'showBatch'])->name('activation-batches.show');
        Route::get('/activation-batches/{batch}/export', [Academic\CommerceController::class, 'exportBatch'])->name('activation-batches.export');
        Route::post('/activation-batches/{batch}/void', [Academic\CommerceController::class, 'voidCodes'])->name('activation-batches.void');
        Route::get('/moderation', [Academic\CommerceController::class, 'moderation'])->name('moderation.index');
        Route::post('/moderation/ratings/{rating}/hide', [Academic\CommerceController::class, 'hideRating'])->name('moderation.ratings.hide');
        Route::post('/moderation/ratings/{rating}/unhide', [Academic\CommerceController::class, 'unhideRating'])->name('moderation.ratings.unhide');
        Route::get('/certificates', [Academic\CertificateController::class, 'index'])->name('certificates.index');
        Route::post('/certificates/{certificate}/revoke', [Academic\CertificateController::class, 'revoke'])->name('certificates.revoke');
        Route::post('/certificates/{certificate}/regenerate-pdf', [Academic\CertificateController::class, 'regeneratePdf'])->name('certificates.regenerate-pdf');
        Route::get('/reports', [Academic\ReportController::class, 'index'])->name('reports.index');
        // P6 — QT-20 xuất dashboard; QT-18 ẩn/hiện hỏi đáp; CR-67 tiên quyết; MH-GVU-12 kỳ thi.
        Route::get('/dashboard/export', [Academic\DashboardController::class, 'export'])->name('dashboard.export');
        Route::get('/discussions', [Academic\DiscussionController::class, 'index'])->name('discussions.index');
        Route::post('/discussions/{discussion}/hide', [Academic\DiscussionController::class, 'hide'])->name('discussions.hide');
        Route::post('/discussions/{discussion}/unhide', [Academic\DiscussionController::class, 'unhide'])->name('discussions.unhide');
        Route::put('/courses/{course}/prerequisite', [Academic\CourseController::class, 'prerequisite'])->name('courses.prerequisite');
        Route::get('/exams', [Exams\ManageController::class, 'index'])->name('exams.index');
        Route::get('/exams/create', [Exams\ManageController::class, 'create'])->name('exams.create');
        Route::post('/exams', [Exams\ManageController::class, 'store'])->name('exams.store');
        Route::get('/exams/{exam}', [Exams\ManageController::class, 'show'])->name('exams.show');
        Route::get('/exams/{exam}/edit', [Exams\ManageController::class, 'edit'])->name('exams.edit');
        Route::put('/exams/{exam}', [Exams\ManageController::class, 'update'])->name('exams.update');
        Route::post('/exams/{exam}/schedule', [Exams\ManageController::class, 'schedule'])->name('exams.schedule');
        Route::post('/exams/{exam}/reschedule', [Exams\ManageController::class, 'reschedule'])->name('exams.reschedule');
        Route::put('/exams/{exam}/publish-at', [Exams\ManageController::class, 'publishAt'])->name('exams.publish-at');
        Route::post('/teachers', [Academic\TeacherController::class, 'store'])->name('teachers.store');
        Route::post('/teachers/{user}/profile', [Academic\TeacherController::class, 'storeProfile'])->name('teachers.profile.store');
        Route::put('/teachers/{user}/subjects', [Academic\TeacherController::class, 'subjects'])->name('teachers.subjects.update');

        // P1 — MH-GVU-03..06: khóa học, duyệt, công bố, bán.
        Route::get('/courses', [Academic\CourseController::class, 'index'])->name('courses.index');
        Route::post('/courses', [Academic\CourseController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}', [Academic\CourseController::class, 'show'])->name('courses.show');
        Route::patch('/courses/{course}', [Academic\CourseController::class, 'update'])->name('courses.update');
        Route::put('/courses/{course}/pricing', [Academic\CourseController::class, 'pricing'])->name('courses.pricing');
        Route::put('/courses/{course}/tags', [Academic\CourseController::class, 'tags'])->name('courses.tags');
        Route::put('/courses/{course}/author', [Academic\CourseController::class, 'reassign'])->name('courses.author');
        Route::post('/courses/{course}/sell', [Academic\CourseController::class, 'sell'])->name('courses.sell');
        Route::post('/courses/{course}/stop-selling', [Academic\CourseController::class, 'stopSelling'])->name('courses.stop-selling');
        Route::post('/courses/{course}/resume-selling', [Academic\CourseController::class, 'resumeSelling'])->name('courses.resume-selling');
        Route::get('/course-versions/{version}', [Academic\CourseVersionController::class, 'show'])->name('course-versions.show');
        Route::post('/course-versions/{version}/approve', [Academic\CourseVersionController::class, 'approve'])->name('course-versions.approve');
        Route::post('/course-versions/{version}/return', [Academic\CourseVersionController::class, 'returnVersion'])->name('course-versions.return');
        Route::post('/course-versions/{version}/publish', [Academic\CourseVersionController::class, 'publish'])->name('course-versions.publish');
    });

    Route::middleware('role:QUAN_TRI')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/revenue', [Admin\RevenueController::class, 'index'])->name('revenue.index');

        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/roles', [Admin\UserController::class, 'assignRole'])->name('users.roles.store');
        Route::delete('/users/{user}/roles/{role}', [Admin\UserController::class, 'removeRole'])->name('users.roles.destroy');
        Route::patch('/users/{user}/lock', [Admin\UserController::class, 'lock'])->name('users.lock');
        Route::patch('/users/{user}/unlock', [Admin\UserController::class, 'unlock'])->name('users.unlock');
        Route::delete('/users/{user}/two-factor', [Admin\UserController::class, 'resetTwoFactor'])->name('users.two-factor.destroy');

        Route::get('/taxonomies', [Admin\TaxonomyController::class, 'index'])->name('taxonomies.index');
        Route::post('/taxonomies/{kind}', [Admin\TaxonomyController::class, 'store'])->name('taxonomies.store');
        Route::patch('/taxonomies/{kind}/{taxonomy}', [Admin\TaxonomyController::class, 'update'])->whereNumber('taxonomy')->name('taxonomies.update');
        Route::delete('/taxonomies/{kind}/{taxonomy}', [Admin\TaxonomyController::class, 'destroy'])->whereNumber('taxonomy')->name('taxonomies.destroy');
        Route::patch('/taxonomies/{kind}/{taxonomy}/restore', [Admin\TaxonomyController::class, 'restore'])->whereNumber('taxonomy')->name('taxonomies.restore');
        Route::delete('/taxonomies/{kind}/{taxonomy}/force', [Admin\TaxonomyController::class, 'forceDestroy'])->whereNumber('taxonomy')->name('taxonomies.force-destroy');

        // P7 — QT-23 A1: huy hiệu.
        Route::get('/badges', [Admin\BadgeController::class, 'index'])->name('badges.index');
        Route::post('/badges', [Admin\BadgeController::class, 'store'])->name('badges.store');
        Route::put('/badges/{badge}', [Admin\BadgeController::class, 'update'])->name('badges.update');
        Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings/grant-failure-mode', [Admin\SettingController::class, 'grantFailureMode'])->name('settings.grant-failure-mode.update');
        Route::patch('/settings/ai', [Admin\SettingController::class, 'ai'])->name('settings.ai.update');
    });
});
