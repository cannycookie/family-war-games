<?php

namespace App\Livewire;

use App\Models\GameType;
use App\Models\Player;
use App\Models\Result;
use App\Models\ResultScore;
use App\Support\Standings;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Scoreboard extends Component
{
    #[Url(as: 'game', keep: true)]
    public ?string $gameSlug = null;

    public bool $adding = false;
    public bool $addingGame = false;
    public bool $choosingPlayer = false;

    /**
     * Which player this phone belongs to. Mirrored from the cookie into a
     * property so the greeting updates the moment it's chosen, rather than
     * waiting for the queued cookie to come back on the next request.
     */
    public ?string $meSlug = null;

    /** @var array<int, string> player id => points as typed */
    public array $points = [];

    public string $playedOn = '';

    // New game form
    public string $newGameName = '';
    public string $newGameDirection = 'high';
    public string $newGameUnit = 'points';

    public function mount(): void
    {
        $this->playedOn = now()->toDateString();

        if (! $this->gameSlug) {
            $this->gameSlug = GameType::where('archived', false)
                ->orderBy('position')->value('slug');
        }

        $this->meSlug = request()->cookie('player_slug');
        $this->choosingPlayer = blank($this->meSlug);
    }

    #[Computed]
    public function games()
    {
        return GameType::where('archived', false)
            ->orderBy('position')->orderBy('id')->get();
    }

    #[Computed]
    public function game(): ?GameType
    {
        return $this->games->firstWhere('slug', $this->gameSlug) ?? $this->games->first();
    }

    #[Computed]
    public function players()
    {
        return Player::orderBy('position')->orderBy('id')->get();
    }

    #[Computed]
    public function me(): ?Player
    {
        return $this->meSlug
            ? $this->players->firstWhere('slug', $this->meSlug)
            : null;
    }

    #[Computed]
    public function standing(): array
    {
        return Standings::make()->forGame($this->game);
    }

    #[Computed]
    public function overall(): array
    {
        return Standings::make()->overall();
    }

    #[Computed]
    public function history()
    {
        return Result::with(['scores.player', 'gameType'])
            ->where('game_type_id', $this->game?->id)
            ->orderByDesc('played_on')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function selectGame(string $slug): void
    {
        $this->gameSlug = $slug;
        $this->adding = false;
        unset($this->game, $this->standing, $this->history);
    }

    public function iAm(string $slug): void
    {
        Cookie::queue('player_slug', $slug, 60 * 24 * 365);
        $this->meSlug = $slug;
        $this->choosingPlayer = false;
        unset($this->me);
    }

    public function startAdding(): void
    {
        $this->points = [];

        foreach ($this->players as $player) {
            $this->points[$player->id] = '';
        }

        $this->playedOn = now()->toDateString();
        $this->adding = true;
        $this->resetValidation();
    }

    public function cancelAdding(): void
    {
        $this->adding = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $rules = ['playedOn' => 'required|date'];

        foreach ($this->players as $player) {
            $rules['points.'.$player->id] = 'required|integer|between:-9999,9999';
        }

        $this->validate($rules, [], $this->pointsAttributes());

        $result = Result::create([
            'game_type_id' => $this->game->id,
            'played_on' => $this->playedOn,
        ]);

        foreach ($this->points as $playerId => $value) {
            ResultScore::create([
                'result_id' => $result->id,
                'player_id' => $playerId,
                'points' => (int) $value,
            ]);
        }

        $this->adding = false;
        $this->points = [];

        unset($this->standing, $this->overall, $this->history);

        $result->load('scores', 'gameType');
        $this->dispatch('saved', message: $this->describe($result));
    }

    public function deleteResult(int $id): void
    {
        Result::whereKey($id)->delete();

        unset($this->standing, $this->overall, $this->history);

        $this->dispatch('saved', message: 'Game deleted.');
    }

    public function saveGame(): void
    {
        $this->validate([
            'newGameName' => 'required|string|max:40',
            'newGameDirection' => 'required|in:low,high',
            'newGameUnit' => 'required|string|max:20',
        ]);

        $slug = str($this->newGameName)->slug()->toString();

        $game = GameType::create([
            'name' => $this->newGameName,
            'slug' => GameType::where('slug', $slug)->exists() ? $slug.'-'.uniqid() : $slug,
            'win_direction' => $this->newGameDirection,
            'unit_label' => $this->newGameUnit,
            'position' => (int) GameType::max('position') + 1,
        ]);

        $this->newGameName = '';
        $this->newGameUnit = 'points';
        $this->newGameDirection = 'high';
        $this->addingGame = false;

        unset($this->games);
        $this->selectGame($game->slug);

        $this->dispatch('saved', message: $game->name.' added.');
    }

    protected function pointsAttributes(): array
    {
        $attributes = [];

        foreach ($this->players as $player) {
            $attributes['points.'.$player->id] = $player->name."'s score";
        }

        return $attributes;
    }

    protected function describe(Result $result): string
    {
        $winners = $result->winningPlayerIds();

        if (count($winners) !== 1) {
            return 'Saved - a draw.';
        }

        $winner = $this->players->firstWhere('id', $winners[0]);
        $me = $this->me;

        $who = ($me && $me->id === $winner->id) ? 'You win' : $winner->name.' wins';

        return $who.' by '.$result->margin().'.';
    }

    public function render()
    {
        return view('livewire.scoreboard');
    }
}
