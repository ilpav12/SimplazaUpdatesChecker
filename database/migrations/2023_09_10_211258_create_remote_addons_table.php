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
        Schema::create('remote_addons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('version');
            $table->string('description')->nullable();
            $table->string('warning')->nullable();
            $table->boolean('is_recommended')->nullable();
            $table->string('page')->unique();
            $table->string('torrent')->unique();
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remote_addons');
    }
};
