<?php

namespace App\Console\Commands;

use App\Actions\M20\PracticeSessions;
use App\Actions\M21\XpLedger;
use App\Actions\M22\GameSessions;
use App\Enums\M21\XpSourceType;
use App\Listeners\RecordLearningXp;
use App\Models\M09\LessonProgress;
use App\Models\M21\XpSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * TV-14 (02d §4): chốt hạn câu Try Hard và phiên luyện tập/game quá 60 phút không hoạt động; xử lý lại nguồn XP chưa xong
 * và bổ sung nguồn hoàn thành bài bị lỡ (CR-159). Chạy mỗi phút, idempotent.
 */
final class PracticeSchedules extends Command
{
    protected $signature = 'edunest:practice';

    protected $description = 'Tác vụ nền luyện tập, mini game và sổ XP';

    public function handle(PracticeSessions $practice, GameSessions $games, XpLedger $ledger, RecordLearningXp $learning): int
    {
        $result = ['practice' => $practice->sweep(), 'games' => $games->sweep()];

        $missed = LessonProgress::query()->whereNotNull('completed_at')->where('completed_at', '>=', now()->subDays(2))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('xp_sources')->where('type', XpSourceType::HoanThanhBai->value)
                ->whereColumn('xp_sources.source_key', DB::raw('CAST(lesson_progress.id AS CHAR)')))
            ->limit(500)->get();
        foreach ($missed as $progress) {
            $learning->recordLesson($progress);
        }
        $result['lessons'] = $missed->count();

        $pending = XpSource::query()->whereNull('processed_at')->where('created_at', '<=', now()->subSeconds(30))->orderBy('id')->limit(500)->pluck('id');
        foreach ($pending as $id) {
            $ledger->processSafely($id);
        }
        $result['sources'] = $pending->count();
        $this->info(json_encode($result) ?: '');

        return self::SUCCESS;
    }
}
