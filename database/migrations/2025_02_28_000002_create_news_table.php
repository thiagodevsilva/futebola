<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->timestamp('published_at');
            $table->string('author')->nullable();
            $table->string('link', 1024);
            $table->char('link_hash', 64)->unique(); // hash do link: evita índice único > 3072 bytes no MySQL utf8mb4
            $table->string('image_url', 1024)->nullable();
            $table->string('guid', 1024)->nullable();
            $table->timestamps();
        });

        Schema::table('news', function (Blueprint $table) {
            $table->index('published_at');
            $table->index('feed_id');
            $table->index(['feed_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
