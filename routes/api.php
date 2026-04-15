<?php

use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('whatsapp')->group(function (): void {
    Route::get('status', [WhatsAppController::class, 'status']);
    Route::get('qr', [WhatsAppController::class, 'qr']);
    Route::post('send', [WhatsAppController::class, 'send']);
    Route::get('messages', [WhatsAppController::class, 'messages']);
    Route::get('chats', [WhatsAppController::class, 'chats']);
    Route::get('chats/{chatId}/messages', [WhatsAppController::class, 'chatMessages']);
    Route::post('chats/{chatId}/read', [WhatsAppController::class, 'markChatRead']);
    Route::post('chats/{chatId}/sync-history', [WhatsAppController::class, 'syncChatHistory']);
    Route::post('restart', [WhatsAppController::class, 'restart']);
    Route::post('logout', [WhatsAppController::class, 'logout']);
    Route::post('reset-session', [WhatsAppController::class, 'resetSession']);

    // Consumed by the Node WhatsApp gateway.
    Route::post('webhook/incoming', [WhatsAppWebhookController::class, 'incoming']);
});
