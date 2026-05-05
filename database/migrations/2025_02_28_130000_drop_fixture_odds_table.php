<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fixture_odds');
    }

    public function down(): void
    {
        Schema::create('fixture_odds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_fixture_id')->unique();
            $table->string('bookmaker_name', 100)->nullable();
            $table->decimal('odd_1', 6, 2)->nullable();
            $table->decimal('odd_x', 6, 2)->nullable();
            $table->decimal('odd_2', 6, 2)->nullable();
            $table->timestamp('updated_at');
            $table->index('external_fixture_id');
        });
    }
};
