<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('season');
            $table->unsignedTinyInteger('rank');
            $table->unsignedInteger('team_id')->nullable();
            $table->string('team_name');
            $table->string('team_logo', 1024)->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('win')->default(0);
            $table->unsignedSmallInteger('draw')->default(0);
            $table->unsignedSmallInteger('loss')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->integer('goal_diff')->default(0);
            $table->string('form', 20)->nullable();
            $table->timestamps();
        });

        Schema::table('standings', function (Blueprint $table) {
            $table->unique(['league_id', 'season', 'rank']);
            $table->index(['league_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
