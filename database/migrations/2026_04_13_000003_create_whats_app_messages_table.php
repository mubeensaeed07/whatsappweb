<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whats_app_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id')->nullable()->index();
            $table->string('from_number')->index();
            $table->string('to_number')->nullable()->index();
            $table->longText('body')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whats_app_messages');
    }
};
