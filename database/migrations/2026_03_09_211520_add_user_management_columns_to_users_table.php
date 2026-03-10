<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->after('id');
            $table->string('password')->nullable()->change();
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->timestamp('invited_at')->nullable()->after('is_active');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');
            $table->timestamp('last_login_at')->nullable()->after('invitation_accepted_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('deactivated_at')->nullable()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'member_id',
                'is_active',
                'invited_at',
                'invitation_accepted_at',
                'last_login_at',
                'last_login_ip',
                'deactivated_at',
            ]);
        });
    }
};
