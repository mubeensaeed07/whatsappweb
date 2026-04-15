<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whats_app_messages', function (Blueprint $table): void {
            $table->string('chat_id')->nullable()->index()->after('message_id');
            $table->string('contact_name')->nullable()->after('chat_id');
            $table->boolean('from_me')->default(false)->index()->after('body');
            $table->timestamp('read_at')->nullable()->index()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('whats_app_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'chat_id',
                'contact_name',
                'from_me',
                'read_at',
            ]);
        });
    }
};
