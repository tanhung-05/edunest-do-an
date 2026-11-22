<?php

namespace App\Models\M02;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * L07 tags.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $deleted_at
 */
class Tag extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug'];
}
