<?php

namespace App\Support;

use App\Models\GameType;
use App\Models\Player;
use App\Models\Result;
use Illuminate\Support\Collection;

/**
 * Works out the running standings for a game type, or across all of them.
 *
 * Everything here routes through GameType::winningPlayerIds(), so a game's
 * win direction is applied in exactly one place. Totals are reported as raw
 * sums plus a label describing what a big number means, because in a
 * low-wins game a high total is a bad thing and calling it "points" invites
 * exactly the wrong reading.
 */
class Standings
{
    public function __construct(
        protected Collection $players,
    ) {}

    public static function make(): self
    {
        return new self(Player::orderBy('position')->orderBy('id')->get());
    }

    public function players(): Collection
    {
        return $this->players;
    }

    /**
     * @return array{
     *     rows: array<int, array{player: Player, won: int, points: int}>,
     *     drawn: int,
     *     played: int,
     *     leaders: array<int, int>,
     *     points_label: string,
     * }
     */
    public function forGame(GameType $game): array
    {
        $results = Result::with('scores')
            ->where('game_type_id', $game->id)
            ->get();

        $won = [];
        $points = [];
        $drawn = 0;

        foreach ($this->players as $player) {
            $won[$player->id] = 0;
            $points[$player->id] = 0;
        }

        foreach ($results as $result) {
            $byPlayer = $result->pointsByPlayer();

            foreach ($byPlayer as $playerId => $value) {
                if (array_key_exists($playerId, $points)) {
                    $points[$playerId] += $value;
                }
            }

            $winners = $game->winningPlayerIds($byPlayer);

            if (count($winners) > 1) {
                $drawn++;
                continue;
            }

            foreach ($winners as $playerId) {
                if (array_key_exists($playerId, $won)) {
                    $won[$playerId]++;
                }
            }
        }

        $rows = $this->players->map(fn (Player $player) => [
            'player' => $player,
            'won' => $won[$player->id],
            'points' => $points[$player->id],
        ])->sortByDesc('won')->values()->all();

        return [
            'rows' => $rows,
            'drawn' => $drawn,
            'played' => $results->count(),
            'leaders' => $this->leaders($won),
            'points_label' => $game->lowWins()
                ? 'Penalty '.$game->unit_label.' (lower is better)'
                : ucfirst($game->unit_label).' scored',
        ];
    }

    /**
     * Games won across every game type, so the front page can show one
     * overall figure alongside the per-game breakdown.
     */
    public function overall(): array
    {
        $won = [];
        $drawn = 0;
        $played = 0;

        foreach ($this->players as $player) {
            $won[$player->id] = 0;
        }

        foreach (GameType::all() as $game) {
            $standing = $this->forGame($game);
            $drawn += $standing['drawn'];
            $played += $standing['played'];

            foreach ($standing['rows'] as $row) {
                $won[$row['player']->id] += $row['won'];
            }
        }

        $rows = $this->players->map(fn (Player $player) => [
            'player' => $player,
            'won' => $won[$player->id],
        ])->sortByDesc('won')->values()->all();

        return [
            'rows' => $rows,
            'drawn' => $drawn,
            'played' => $played,
            'leaders' => $this->leaders($won),
        ];
    }

    /** @return array<int, int> player ids tied at the top */
    protected function leaders(array $won): array
    {
        if (empty($won) || max($won) === 0) {
            return [];
        }

        return array_keys($won, max($won), true);
    }
}
