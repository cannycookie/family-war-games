<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameType extends Model
{
    protected $fillable = [
        'name', 'slug', 'emoji', 'win_direction', 'unit_label', 'position', 'archived',
    ];

    protected $casts = [
        'archived' => 'boolean',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function lowWins(): bool
    {
        return $this->win_direction === 'low';
    }

    /**
     * Human-readable reminder of which way this game runs, shown next to
     * the score entry fields so the direction is never a guess.
     */
    public function directionLabel(): string
    {
        return $this->lowWins()
            ? 'Lowest score wins'
            : 'Highest score wins';
    }

    /**
     * Given a map of player_id => points, return the winning player ids.
     * More than one id means a draw. Returns an empty array if there is
     * nothing to compare.
     */
    public function winningPlayerIds(array $pointsByPlayer): array
    {
        if (empty($pointsByPlayer)) {
            return [];
        }

        $best = $this->lowWins()
            ? min($pointsByPlayer)
            : max($pointsByPlayer);

        return array_keys($pointsByPlayer, $best, true);
    }
}
