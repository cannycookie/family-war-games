<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('colour')->default('#6366f1');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('game_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('emoji', 16)->default('game');

            // The whole point of this column: some games are won by the
            // lowest score (Rummikub, where points are penalties for the
            // tiles left in your hand) and some by the highest (crib,
            // backgammon). Storing it per game means nobody - human or
            // otherwise - has to remember which way round a game runs.
            $table->enum('win_direction', ['low', 'high']);

            $table->string('unit_label')->default('points');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_type_id')->constrained()->cascadeOnDelete();
            $table->date('played_on');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['game_type_id', 'played_on']);
        });

        Schema::create('result_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->timestamps();

            $table->unique(['result_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_scores');
        Schema::dropIfExists('results');
        Schema::dropIfExists('game_types');
        Schema::dropIfExists('players');
    }
};
