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
        Schema::create('identities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('provider');     // 'azure'|'moodle'|...
            $t->string('subject_id');   // sub/oid з провайдера
            $t->text('refresh_token')->nullable(); // зашифрувати через cast/encrypt
            $t->timestamp('expires_at')->nullable();
            $t->json('scopes')->nullable();
            $t->timestamps();
            $t->unique(['provider','subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identities');
    }
};
