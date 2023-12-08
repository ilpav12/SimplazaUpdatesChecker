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
        Schema::create('local_addons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('version');
            $table->string('path')->unique();
            $table->foreignId('remote_addon_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->boolean('is_excluded')->default(false);
            $table->string('is_in_community_folder');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_addons');
    }
};
