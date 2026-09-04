<?php

namespace Database\Seeders;

use App\Models\GameType;
use App\Models\Player;
use App\Models\Result;
use App\Models\ResultScore;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $sue = Player::firstOrCreate(
            ['slug' => 'sue'],
            ['name' => 'Sue', 'colour' => '#db2777', 'position' => 1],
        );

        $lee = Player::firstOrCreate(
            ['slug' => 'lee'],
            ['name' => 'Lee', 'colour' => '#0d9488', 'position' => 2],
        );

        $rummikub = GameType::firstOrCreate(
            ['slug' => 'rummikub'],
            [
                'name' => 'Rummikub',
                'emoji' => '🁢',
                // Points are the tiles left in your hand when the other
                // player goes out. Fewest wins; the winner is usually on 0.
                'win_direction' => 'low',
                'unit_label' => 'points',
                'position' => 1,
            ],
        );

        GameType::firstOrCreate(
            ['slug' => 'cribbage'],
            [
                'name' => 'Cribbage',
                'emoji' => '🂡',
                'win_direction' => 'high',
                'unit_label' => 'points',
                'position' => 2,
            ],
        );

        GameType::firstOrCreate(
            ['slug' => 'backgammon'],
            [
                'name' => 'Backgammon',
                'emoji' => '🎲',
                'win_direction' => 'high',
                'unit_label' => 'points',
                'position' => 3,
            ],
        );

        // The four games carried over from the Google Sheet. Sue's score
        // first, Lee's second, exactly as they were recorded there.
        $carriedOver = [
            ['2026-09-04', 0, 25],
            ['2026-09-04', 3, 0],
            ['2026-09-04', 59, 0],
            ['2026-09-04', 12, 0],
        ];

        if ($rummikub->results()->count() === 0) {
            foreach ($carriedOver as [$date, $suePoints, $leePoints]) {
                $result = Result::create([
                    'game_type_id' => $rummikub->id,
                    'played_on' => $date,
                    'note' => 'Carried over from the spreadsheet',
                ]);

                ResultScore::create([
                    'result_id' => $result->id,
                    'player_id' => $sue->id,
                    'points' => $suePoints,
                ]);

                ResultScore::create([
                    'result_id' => $result->id,
                    'player_id' => $lee->id,
                    'points' => $leePoints,
                ]);
            }
        }
    }
}
