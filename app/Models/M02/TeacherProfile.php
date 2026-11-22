<?php

namespace App\Models\M02;

use App\Models\User;
use Database\Factories\M02\TeacherProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L09 teacher_profiles.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $photo_path
 * @property string|null $bio
 * @property string|null $achievements
 * @property int $created_by_id
 */
class TeacherProfile extends Model
{
    /** @use HasFactory<TeacherProfileFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'photo_path', 'bio', 'achievements', 'created_by_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
