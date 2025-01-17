<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->text('author');
            $table->integer('level_number');
            $table->text('level_label');
            $table->longText('content_html')->charset('binary');
            $table->longText('content_markdown')->charset('binary');
            $table->text('srcUrl')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutorials');
    }
};
