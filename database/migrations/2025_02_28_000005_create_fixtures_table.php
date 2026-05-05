<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('external_fixture_id')->unique();
            $table->unsignedSmallInteger('season')->nullable();
            $table->timestamp('date');
            $table->unsignedInteger('home_team_id')->nullable();
            $table->string('home_team_name');
            $table->unsignedInteger('away_team_id')->nullable();
            $table->string('away_team_name');
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('venue', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->index(['league_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
