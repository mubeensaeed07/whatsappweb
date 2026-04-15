<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function incoming(Request $request): JsonResponse
    {
        $providedSecret = (string) $request->header('X-Webhook-Secret', '');
        $expectedSecret = (string) config('services.whatsapp_gateway.webhook_secret');

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
            return response()->json([
                'ok' => false,
                'error' => 'Unauthorized webhook request.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($request->boolean('is_ack_update') || $request->input('event_type') === 'message_ack') {
            $data = $request->validate([
                'message_id' => ['required', 'string', 'max:255'],
                'ack' => ['required', 'integer', 'min:0', 'max:5'],
                'timestamp' => ['nullable', 'integer'],
                'chat_id' => ['nullable', 'string', 'max:255'],
                'contact_name' => ['nullable', 'string', 'max:255'],
                'from' => ['nullable', 'string', 'max:255'],
                'to' => ['nullable', 'string', 'max:255'],
                'body' => ['nullable', 'string'],
                'from_me' => ['nullable', 'boolean'],
                'raw' => ['nullable', 'array'],
            ]);

            $existing = WhatsAppMessage::query()
                ->where('message_id', $data['message_id'])
                ->first();

            $payload = is_array($existing?->payload) ? $existing->payload : [];
            $payload['ack'] = (int) $data['ack'];
            $payload['event_type'] = 'message_ack';
            $payload['raw'] = $data['raw'] ?? ($payload['raw'] ?? null);

            $attributes = [
                'chat_id' => $data['chat_id'] ?? $existing?->chat_id,
                'contact_name' => $data['contact_name'] ?? $existing?->contact_name,
                'from_number' => $data['from'] ?? $existing?->from_number,
                'to_number' => $data['to'] ?? $existing?->to_number,
                'body' => array_key_exists('body', $data) ? ($data['body'] ?? '') : ($existing?->body ?? ''),
                'from_me' => (bool) ($data['from_me'] ?? $existing?->from_me ?? true),
                'received_at' => isset($data['timestamp'])
                    ? now()->setTimestamp((int) $data['timestamp'])
                    : ($existing?->received_at ?? now()),
                'read_at' => ((int) $data['ack'] >= 3) ? now() : ($existing?->read_at),
                'payload' => $payload,
            ];

            $message = WhatsAppMessage::query()->updateOrCreate(
                ['message_id' => $data['message_id']],
                $attributes
            );

            return response()->json([
                'ok' => true,
                'id' => $message->id,
            ]);
        }

        $data = $request->validate([
            'message_id' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'from_me' => ['nullable', 'boolean'],
            'ack' => ['nullable', 'integer'],
            'timestamp' => ['nullable', 'integer'],
            'raw' => ['nullable', 'array'],
        ]);

        $fromMe = (bool) ($data['from_me'] ?? false);
        $chatId = $data['chat_id'] ?? ($fromMe ? ($data['to'] ?? $data['from']) : $data['from']);

        $messageId = $data['message_id'] ?? null;
        $attributes = [
            'chat_id' => $chatId,
            'contact_name' => $data['contact_name'] ?? $chatId,
            'from_number' => $data['from'],
            'to_number' => $data['to'] ?? null,
            'body' => $data['body'] ?? '',
            'from_me' => $fromMe,
            'received_at' => isset($data['timestamp']) ? now()->setTimestamp($data['timestamp']) : now(),
            'read_at' => $fromMe ? now() : null,
            'payload' => $data,
        ];

        if (! empty($messageId)) {
            $message = WhatsAppMessage::query()->updateOrCreate(
                ['message_id' => $messageId],
                $attributes
            );
        } else {
            $message = WhatsAppMessage::query()->create($attributes);
        }

        return response()->json([
            'ok' => true,
            'id' => $message->id,
        ]);
    }
}
