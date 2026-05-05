<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 1024);
            $table->string('category', 100)->default('futebol');
            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('priority')->default(0);
            $table->string('language', 10)->default('pt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeds');
    }
};
