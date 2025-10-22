<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('schedule_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('location')->nullable();
            $t->timestamp('start_at');
            $t->timestamp('end_at');
            $t->string('group')->nullable();
            $t->string('source')->default('stub'); // moodle|ics|...
            $t->timestamps();
            $t->index(['user_id','start_at']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_events');
    }
};
