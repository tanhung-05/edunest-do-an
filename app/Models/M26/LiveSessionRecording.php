<?php

namespace App\Models\M26;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * E27 live_session_recordings — bản ghi do Giáo viên tải; tệp hiện hành có `current_for_session_id` (CR-174).
 *
 * @property int $id
 * @property int $live_session_id
 * @property ?int $current_for_session_id
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $uploaded_by_id
 * @property ?CarbonImmutable $replaced_at
 * @property ?int $replaced_by_id
 * @property ?CarbonImmutable $created_at
 */
class LiveSessionRecording extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['replaced_at' => 'immutable_datetime'];
    }
}
