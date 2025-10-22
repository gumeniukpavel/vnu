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
        Schema::create('notification_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type'); // schedule.change | assignment.due | ...
            $t->json('payload');
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id','created_at']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_items');
    }
};
