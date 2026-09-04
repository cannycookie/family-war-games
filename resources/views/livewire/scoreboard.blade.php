<div class="wrap" wire:poll.10s>

    {{-- One-time "who's on this phone" --}}
    @if ($this->choosingPlayer)
        <div class="sheet-backdrop">
            <div class="sheet">
                <h2>Who's on this phone?</h2>
                <p class="muted">So the app knows which score is yours. You can change it later.</p>
                <div class="choice-row">
                    @foreach ($this->players as $player)
                        <button type="button" class="choice" style="--accent: {{ $player->colour }}"
                                wire:click="iAm('{{ $player->slug }}')">
                            {{ $player->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <header class="topbar">
        <div>
            <h1>{{ config('scoreboard.name') }}</h1>
            @if ($this->me)
                <button type="button" class="linkish" wire:click="$set('choosingPlayer', true)">
                    You're {{ $this->me->name }} on this phone
                </button>
            @endif
        </div>
    </header>

    {{-- Game tabs --}}
    <nav class="tabs" aria-label="Games">
        @foreach ($this->games as $g)
            <button type="button"
                    class="tab @if ($this->game && $g->id === $this->game->id) is-active @endif"
                    wire:click="selectGame('{{ $g->slug }}')">
                <span class="tab-emoji" aria-hidden="true">{{ $g->emoji }}</span>
                {{ $g->name }}
            </button>
        @endforeach
        <button type="button" class="tab tab-add" wire:click="$toggle('addingGame')" aria-label="Add a game">+</button>
    </nav>

    @if ($addingGame)
        <form class="card form" wire:submit="saveGame">
            <h2>New game</h2>
            <label>
                Name
                <input type="text" wire:model="newGameName" placeholder="Dominoes" autocomplete="off">
            </label>
            @error('newGameName') <p class="error">{{ $message }}</p> @enderror

            <fieldset class="direction">
                <legend>How is it won?</legend>
                <label class="radio">
                    <input type="radio" wire:model="newGameDirection" value="high">
                    <span><strong>Highest score wins</strong><small>Crib, backgammon, most games</small></span>
                </label>
                <label class="radio">
                    <input type="radio" wire:model="newGameDirection" value="low">
                    <span><strong>Lowest score wins</strong><small>Rummikub, golf - points are penalties</small></span>
                </label>
            </fieldset>

            <label>
                What are the units called?
                <input type="text" wire:model="newGameUnit" placeholder="points">
            </label>

            <div class="actions">
                <button type="button" class="btn ghost" wire:click="$set('addingGame', false)">Cancel</button>
                <button type="submit" class="btn">Add game</button>
            </div>
        </form>
    @endif

    @if ($this->game)
        @php($standing = $this->standing)

        {{-- The headline scoreboard --}}
        <section class="card score-card">
            <div class="score-head">
                <span class="direction-pill @if ($this->game->lowWins()) is-low @endif">
                    {{ $this->game->directionLabel() }}
                </span>
                <span class="muted">{{ $standing['played'] }} played</span>
            </div>

            <div class="score-grid">
                @foreach ($standing['rows'] as $row)
                    <div class="score @if (in_array($row['player']->id, $standing['leaders'], true) && $standing['played']) is-leading @endif"
                         style="--accent: {{ $row['player']->colour }}">
                        <div class="score-name">
                            {{ $row['player']->name }}
                            @if ($this->me && $this->me->id === $row['player']->id)
                                <span class="you">you</span>
                            @endif
                        </div>
                        <div class="score-value">{{ $row['won'] }}</div>
                        <div class="score-sub">{{ Str::plural('win', $row['won']) }}</div>
                        <div class="score-points">{{ number_format($row['points']) }}</div>
                    </div>
                @endforeach
            </div>

            <p class="points-label">{{ $standing['points_label'] }}@if ($standing['drawn']) &middot; {{ $standing['drawn'] }} drawn @endif</p>
        </section>

        {{-- Add a game --}}
        @if (! $adding)
            <button type="button" class="btn big" wire:click="startAdding">
                Add a {{ $this->game->name }} game
            </button>
        @else
            <form class="card form" wire:submit="save">
                <h2>New {{ $this->game->name }} game</h2>
                <p class="muted">{{ $this->game->directionLabel() }}.</p>

                <div class="entry-grid">
                    @foreach ($this->players as $player)
                        <label class="entry" style="--accent: {{ $player->colour }}">
                            <span class="entry-name">
                                {{ $player->name }}
                                @if ($this->me && $this->me->id === $player->id)<span class="you">you</span>@endif
                            </span>
                            <input type="number" inputmode="numeric" pattern="-?[0-9]*"
                                   wire:model="points.{{ $player->id }}"
                                   placeholder="0" enterkeyhint="done">
                            @error('points.'.$player->id) <span class="error">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>

                <label class="date-label">
                    Played on
                    <input type="date" wire:model="playedOn">
                </label>
                @error('playedOn') <p class="error">{{ $message }}</p> @enderror

                <div class="actions">
                    <button type="button" class="btn ghost" wire:click="cancelAdding">Cancel</button>
                    <button type="submit" class="btn">
                        <span wire:loading.remove wire:target="save">Save game</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </button>
                </div>
            </form>
        @endif

        {{-- History --}}
        <section class="history">
            <h2>History</h2>

            @forelse ($this->history as $result)
                @php($winners = $result->winningPlayerIds())
                <div class="row">
                    <div class="row-date">{{ $result->played_on->format('j M') }}</div>
                    <div class="row-scores">
                        @foreach ($this->players as $player)
                            @php($score = $result->scores->firstWhere('player_id', $player->id))
                            <span class="chip @if (in_array($player->id, $winners, true)) is-win @endif"
                                  style="--accent: {{ $player->colour }}">
                                <span class="chip-name">{{ $player->name }}</span>
                                <span class="chip-points">{{ $score?->points ?? '-' }}</span>
                            </span>
                        @endforeach
                    </div>
                    <div class="row-verdict">
                        @if (count($winners) > 1)
                            Draw
                        @elseif (count($winners) === 1)
                            @php($winner = $this->players->firstWhere('id', $winners[0]))
                            {{ $this->me && $this->me->id === $winner?->id ? 'You' : $winner?->name }}
                            <small>by {{ $result->margin() }}</small>
                        @endif
                    </div>
                    <button type="button" class="row-delete" title="Delete this game"
                            wire:click="deleteResult({{ $result->id }})"
                            wire:confirm="Delete this game? It won't come back.">&times;</button>
                </div>
            @empty
                <p class="muted empty">No {{ $this->game->name }} games yet. Add the first one above.</p>
            @endforelse
        </section>
    @endif

    {{-- Toast --}}
    <div x-data="{ show: false, message: '' }"
         x-on:saved.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 2600)"
         x-show="show" x-cloak class="toast" x-text="message"></div>
</div>
