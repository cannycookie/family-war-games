<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Result extends Model
{
    protected $fillable = ['game_type_id', 'played_on', 'note'];

    protected $casts = [
        'played_on' => 'date',
    ];

    public function gameType(): BelongsTo
    {
        return $this->belongsTo(GameType::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ResultScore::class);
    }

    public function pointsByPlayer(): array
    {
        return $this->scores->pluck('points', 'player_id')->all();
    }

    public function winningPlayerIds(): array
    {
        return $this->gameType->winningPlayerIds($this->pointsByPlayer());
    }

    public function isDraw(): bool
    {
        return count($this->winningPlayerIds()) > 1;
    }

    public function margin(): int
    {
        $points = array_values($this->pointsByPlayer());

        return empty($points) ? 0 : max($points) - min($points);
    }
}
