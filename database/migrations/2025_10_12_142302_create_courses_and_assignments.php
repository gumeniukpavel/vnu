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
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable();
            $t->string('title');
            $t->timestamps();
        });
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->timestamp('due_at')->nullable();
            $t->string('status')->default('pending'); // pending|done
            $t->timestamps();
            $t->index(['course_id','due_at']);
        });
        Schema::create('course_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role')->nullable(); // student|teacher
            $t->timestamps();
            $t->unique(['course_id','user_id']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_user');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('courses');
    }
};
