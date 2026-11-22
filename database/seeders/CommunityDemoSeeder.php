<?php

namespace Database\Seeders;

use App\Enums\M27\PostTopic;
use App\Models\M27\CommunityComment;
use App\Models\M27\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu mẫu P8 M27 (QT-29): hai bài trên bảng tin, một bình luận, một lượt thích và một báo cáo để Giáo vụ thử kiểm duyệt.
 * Ghi thẳng bản ghi (không qua giới hạn tần suất). Chạy lại không tạo trùng.
 */
final class CommunityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $an = User::query()->where('email', 'an.nguyen@edunest.test')->first();
        $chi = User::query()->where('email', 'chi.le@edunest.test')->first();
        if ($an === null || $chi === null || CommunityPost::query()->exists()) {
            return;
        }
        $p1 = CommunityPost::query()->create(['user_id' => $an->id, 'topic' => PostTopic::OnThi, 'title' => 'Cách mình ôn cân bằng phương trình oxi hóa khử',
            'body' => "Mình viết số oxi hóa lên từng nguyên tố trước, rồi cân bằng electron cho và nhận.\nAi có mẹo nhanh hơn thì chia sẻ nhé!", 'like_count' => 1]);
        CommunityComment::query()->create(['community_post_id' => $p1->id, 'user_id' => $chi->id, 'body' => 'Mình dùng phương pháp thăng bằng electron, nhanh lắm.']);
        DB::table('community_likes')->insert(['community_post_id' => $p1->id, 'user_id' => $chi->id, 'created_at' => now()]);
        $p2 = CommunityPost::query()->create(['user_id' => $chi->id, 'topic' => PostTopic::GiaoLuu, 'title' => 'Tìm bạn học nhóm Hóa 12 buổi tối',
            'body' => 'Nhóm học online 3 buổi mỗi tuần, ai tham gia để lại bình luận nhé.']);
        DB::table('community_reports')->insert(['community_post_id' => $p2->id, 'user_id' => $an->id, 'reason' => 'Nghi quảng cáo nhóm ngoài', 'created_at' => now()]);
    }
}
