<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw settings row. Reads and writes go through SettingsRepository, which
 * handles encryption and caching — not through this model directly.
 *
 * @property string $key
 * @property string|null $value
 * @property bool $is_encrypted
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted', 'updated_by_user_id'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
