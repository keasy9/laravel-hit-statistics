<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hits', function (Blueprint $table) {
            $table->id();
            $table->text('visited_from');
            $table->text('visited_to');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('useragent')->nullable();
            $table->timestamp('visited_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hits');
    }
};
