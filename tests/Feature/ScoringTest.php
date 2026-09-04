<?php

namespace Tests\Feature;

use App\Livewire\Scoreboard;
use App\Models\GameType;
use App\Models\Player;
use App\Models\Result;
use App\Models\ResultScore;
use App\Support\Standings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    use RefreshDatabase;

    protected Player $sue;
    protected Player $lee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sue = Player::create(['name' => 'Sue', 'slug' => 'sue', 'position' => 1]);
        $this->lee = Player::create(['name' => 'Lee', 'slug' => 'lee', 'position' => 2]);
    }

    protected function game(string $direction): GameType
    {
        return GameType::create([
            'name' => 'Test game',
            'slug' => 'test-'.$direction,
            'win_direction' => $direction,
        ]);
    }

    protected function record(GameType $game, int $suePoints, int $leePoints): Result
    {
        $result = Result::create([
            'game_type_id' => $game->id,
            'played_on' => '2026-09-04',
        ]);

        ResultScore::create(['result_id' => $result->id, 'player_id' => $this->sue->id, 'points' => $suePoints]);
        ResultScore::create(['result_id' => $result->id, 'player_id' => $this->lee->id, 'points' => $leePoints]);

        return $result->fresh('scores');
    }

    /** In Rummikub the points are penalties, so the lowest score takes it. */
    public function test_lowest_score_wins_a_low_direction_game(): void
    {
        $game = $this->game('low');

        $result = $this->record($game, 12, 0);

        $this->assertSame([$this->lee->id], $result->winningPlayerIds());
        $this->assertFalse($result->isDraw());
        $this->assertSame(12, $result->margin());
    }

    public function test_highest_score_wins_a_high_direction_game(): void
    {
        $game = $this->game('high');

        $result = $this->record($game, 121, 96);

        $this->assertSame([$this->sue->id], $result->winningPlayerIds());
        $this->assertSame(25, $result->margin());
    }

    public function test_a_zero_beats_a_positive_score_when_low_wins(): void
    {
        $game = $this->game('low');

        $result = $this->record($game, 0, 25);

        $this->assertSame([$this->sue->id], $result->winningPlayerIds());
    }

    public function test_negative_scores_are_taken_as_given(): void
    {
        $game = $this->game('low');

        $result = $this->record($game, -5, 0);

        $this->assertSame([$this->sue->id], $result->winningPlayerIds());
        $this->assertSame(5, $result->margin());
    }

    public function test_equal_scores_are_a_draw_in_both_directions(): void
    {
        foreach (['low', 'high'] as $direction) {
            $result = $this->record($this->game($direction), 40, 40);

            $this->assertTrue($result->isDraw(), $direction.' should draw');
            $this->assertCount(2, $result->winningPlayerIds());
        }
    }

    /** The four games carried over from the spreadsheet: Lee 3, Sue 1. */
    public function test_standings_match_the_carried_over_rummikub_games(): void
    {
        $game = GameType::create([
            'name' => 'Rummikub', 'slug' => 'rummikub', 'win_direction' => 'low',
        ]);

        $this->record($game, 0, 25);
        $this->record($game, 3, 0);
        $this->record($game, 59, 0);
        $this->record($game, 12, 0);

        $standing = Standings::make()->forGame($game);

        $byName = collect($standing['rows'])->keyBy(fn ($row) => $row['player']->name);

        $this->assertSame(3, $byName['Lee']['won']);
        $this->assertSame(1, $byName['Sue']['won']);
        $this->assertSame(25, $byName['Lee']['points']);
        $this->assertSame(74, $byName['Sue']['points']);
        $this->assertSame(0, $standing['drawn']);
        $this->assertStringContainsString('lower is better', $standing['points_label']);
    }

    public function test_draws_are_counted_and_not_awarded_to_anyone(): void
    {
        $game = $this->game('high');

        $this->record($game, 10, 10);
        $this->record($game, 20, 5);

        $standing = Standings::make()->forGame($game);
        $byName = collect($standing['rows'])->keyBy(fn ($row) => $row['player']->name);

        $this->assertSame(1, $standing['drawn']);
        $this->assertSame(1, $byName['Sue']['won']);
        $this->assertSame(0, $byName['Lee']['won']);
    }

    public function test_the_board_is_not_reachable_without_the_key(): void
    {
        config(['scoreboard.key' => 'a-long-random-key']);

        $this->get('/guessing')->assertNotFound();
        $this->get('/a-long-random-key')->assertOk();
    }

    public function test_a_game_can_be_saved_through_the_ui_and_lands_the_right_way_round(): void
    {
        $game = GameType::create([
            'name' => 'Rummikub', 'slug' => 'rummikub', 'win_direction' => 'low',
        ]);

        Livewire::test(Scoreboard::class)
            ->call('startAdding')
            ->set('points.'.$this->sue->id, '30')
            ->set('points.'.$this->lee->id, '0')
            ->call('save')
            ->assertHasNoErrors();

        $result = Result::with('scores')->first();

        $this->assertNotNull($result);
        $this->assertSame($game->id, $result->game_type_id);
        $this->assertSame([$this->lee->id], $result->winningPlayerIds());
    }

    public function test_the_score_entry_form_rejects_junk(): void
    {
        GameType::create(['name' => 'Crib', 'slug' => 'crib', 'win_direction' => 'high']);

        Livewire::test(Scoreboard::class)
            ->call('startAdding')
            ->set('points.'.$this->sue->id, 'not a number')
            ->set('points.'.$this->lee->id, '')
            ->call('save')
            ->assertHasErrors(['points.'.$this->sue->id, 'points.'.$this->lee->id]);

        $this->assertSame(0, Result::count());
    }

    public function test_a_new_game_records_its_own_win_direction(): void
    {
        GameType::create(['name' => 'Crib', 'slug' => 'crib', 'win_direction' => 'high']);

        Livewire::test(Scoreboard::class)
            ->set('newGameName', 'Golf')
            ->set('newGameDirection', 'low')
            ->set('newGameUnit', 'strokes')
            ->call('saveGame')
            ->assertHasNoErrors();

        $golf = GameType::where('slug', 'golf')->first();

        $this->assertNotNull($golf);
        $this->assertTrue($golf->lowWins());
        $this->assertSame('Lowest score wins', $golf->directionLabel());
    }
}
