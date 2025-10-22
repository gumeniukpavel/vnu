<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('azure_id')->nullable()->index();
            $t->string('azure_tenant_id')->nullable()->index();
            $t->text('azure_access_token')->nullable();
            $t->text('azure_refresh_token')->nullable();
            $t->timestamp('azure_token_expires_at')->nullable();
            $t->string('azure_provider')->nullable()->default('azure');
            $t->string('given_name')->nullable();
            $t->string('surname')->nullable();
            $t->string('job_title')->nullable();
            $t->string('department')->nullable();
            $t->string('phone')->nullable();
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'azure_id','azure_tenant_id','azure_access_token','azure_refresh_token',
                'azure_token_expires_at','azure_provider','given_name','surname','job_title','department', 'phone'
            ]);
        });
    }
};
