<?php

namespace App\Models\M02;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * L06 levels.
 *
 * @property int $id
 * @property string $name
 * @property int $position
 * @property Carbon|null $deleted_at
 */
class Level extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'position'];
}
