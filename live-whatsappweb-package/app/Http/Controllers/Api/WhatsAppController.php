<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppGatewayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppGatewayService $gateway) {}

    public function status(): JsonResponse
    {
        return $this->proxy(fn (): array => $this->gateway->status());
    }

    public function qr(): JsonResponse
    {
        return $this->proxy(fn (): array => $this->gateway->qr());
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:5000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
        ]);

        $chatId = $this->normalizeChatId($data['to']);

        try {
            $result = $this->gateway->sendMessage($chatId, $data['message']);

            if (($result['ok'] ?? false) === true) {
                $payload = $result['data'] ?? [];

                $messageId = $payload['id'] ?? null;
                $attributes = [
                    'chat_id' => $chatId,
                    'contact_name' => $data['contact_name'] ?? $chatId,
                    'from_number' => 'me',
                    'to_number' => $chatId,
                    'body' => $payload['body'] ?? $data['message'],
                    'from_me' => true,
                    'received_at' => isset($payload['timestamp']) ? now()->setTimestamp((int) $payload['timestamp']) : now(),
                    'read_at' => now(),
                    'payload' => $payload,
                ];

                if (! empty($messageId)) {
                    WhatsAppMessage::query()->updateOrCreate(
                        ['message_id' => $messageId],
                        $attributes
                    );
                } else {
                    WhatsAppMessage::query()->create($attributes);
                }
            }

            return response()->json($result);
        } catch (ConnectionException) {
            return response()->json([
                'ok' => false,
                'error' => 'WhatsApp gateway is not reachable.',
            ], 503);
        } catch (RequestException $exception) {
            $status = $exception->response?->status() ?? 500;
            $body = $exception->response?->json();

            return response()->json([
                'ok' => false,
                'error' => $body['error'] ?? 'Gateway request failed.',
                'details' => $body,
            ], $status);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'error' => 'Unexpected server error.',
                'details' => $exception->getMessage(),
            ], 500);
        }
    }

    public function messages(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 50);
        $limit = max(1, min(200, $limit));

        $messages = WhatsAppMessage::query()
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $messages,
        ]);
    }

    public function restart(): JsonResponse
    {
        return $this->proxy(fn (): array => $this->gateway->restart());
    }

    public function logout(): JsonResponse
    {
        return $this->proxy(fn (): array => $this->gateway->logout());
    }

    public function resetSession(): JsonResponse
    {
        return $this->proxy(fn (): array => $this->gateway->resetSession());
    }

    public function chats(): JsonResponse
    {
        $limit = max(1, min(200, request()->integer('limit', 60)));
        try {
            $response = $this->gateway->chats($limit);

            if (($response['ok'] ?? false) === true) {
                foreach (($response['data'] ?? []) as $chat) {
                    $chatId = (string) ($chat['chat_id'] ?? '');
                    $lastMessage = (string) ($chat['last_message'] ?? '');
                    $lastAt = (string) ($chat['last_at'] ?? '');

                    if ($chatId === '' || $lastMessage === '' || $lastAt === '') {
                        continue;
                    }

                    $snapshotId = 'chat-snapshot-'.md5($chatId.'|'.$lastAt.'|'.$lastMessage);

                    WhatsAppMessage::query()->updateOrCreate(
                        ['message_id' => $snapshotId],
                        [
                            'chat_id' => $chatId,
                            'contact_name' => $chat['name'] ?? $chatId,
                            'from_number' => ! empty($chat['last_message_from_me']) ? 'me' : $chatId,
                            'to_number' => ! empty($chat['last_message_from_me']) ? $chatId : null,
                            'body' => $lastMessage,
                            'from_me' => (bool) ($chat['last_message_from_me'] ?? false),
                            'received_at' => $lastAt,
                            'read_at' => ! empty($chat['last_message_from_me']) ? now() : null,
                            'payload' => [
                                'source' => 'chat_snapshot',
                                'is_group' => (bool) ($chat['is_group'] ?? false),
                                'from_me' => (bool) ($chat['last_message_from_me'] ?? false),
                            ],
                        ]
                    );
                }
            }

            return response()->json($response);
        } catch (ConnectionException) {
            return response()->json([
                'ok' => false,
                'error' => 'WhatsApp gateway is not reachable.',
            ], 503);
        } catch (RequestException $exception) {
            $status = $exception->response?->status() ?? 500;
            $body = $exception->response?->json();

            return response()->json([
                'ok' => false,
                'error' => $body['error'] ?? 'Gateway request failed.',
                'details' => $body,
            ], $status);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'error' => 'Unexpected server error.',
                'details' => $exception->getMessage(),
            ], 500);
        }
    }

    public function chatMessages(Request $request, string $chatId): JsonResponse
    {
        $limit = max(1, min(300, (int) $request->integer('limit', 80)));
        $decodedChatId = urldecode($chatId);

        try {
            $response = $this->gateway->chatMessages($decodedChatId, $limit);

            if (($response['ok'] ?? false) === true) {
                $rows = collect($response['data'] ?? []);
                $messageIds = $rows
                    ->pluck('message_id')
                    ->filter(fn ($id): bool => is_string($id) && $id !== '')
                    ->values();

                if ($messageIds->isNotEmpty()) {
                    $ackByMessageId = WhatsAppMessage::query()
                        ->whereIn('message_id', $messageIds)
                        ->get(['message_id', 'payload'])
                        ->mapWithKeys(function (WhatsAppMessage $message): array {
                            $localAck = data_get($message->payload, 'ack', data_get($message->payload, 'raw.ack'));

                            return [$message->message_id => is_numeric($localAck) ? (int) $localAck : null];
                        });

                    $response['data'] = $rows->map(function (array $item) use ($ackByMessageId): array {
                        $messageId = $item['message_id'] ?? null;
                        $liveAck = isset($item['ack']) && is_numeric($item['ack']) ? (int) $item['ack'] : null;
                        $storedAck = $messageId ? $ackByMessageId->get($messageId) : null;

                        if ($liveAck === null && $storedAck !== null) {
                            $item['ack'] = $storedAck;
                        } elseif ($liveAck !== null && $storedAck !== null) {
                            $item['ack'] = max($liveAck, $storedAck);
                        }

                        return $item;
                    })->values()->all();
                }

                return response()->json($response);
            }
        } catch (Throwable) {
            // Fall back to locally captured messages when live history fetch fails.
        }

        $messages = WhatsAppMessage::query()
            ->where(function ($query) use ($decodedChatId): void {
                $query->where('chat_id', $decodedChatId)
                    ->orWhere('from_number', $decodedChatId)
                    ->orWhere(function ($subQuery) use ($decodedChatId): void {
                        $subQuery->where('from_me', true)
                            ->where('to_number', $decodedChatId);
                    });
            })
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $deduped = $messages
            ->groupBy(function (WhatsAppMessage $message) use ($decodedChatId): string {
                $ts = optional($message->received_at)->timestamp ?? 0;
                $body = (string) $message->body;
                $chat = $message->chat_id ?: $decodedChatId;

                return $chat.'|'.$ts.'|'.$body;
            })
            ->map(function ($group) {
                return $group
                    ->sortByDesc(fn (WhatsAppMessage $m): int => (int) $m->from_me)
                    ->first();
            })
            ->filter()
            ->reverse()
            ->values()
            ->map(function (WhatsAppMessage $message) use ($decodedChatId): array {
                return [
                    'message_id' => $message->message_id,
                    'chat_id' => $message->chat_id ?: $decodedChatId,
                    'body' => $message->body,
                    'from_me' => (bool) $message->from_me,
                    'ack' => data_get($message->payload, 'ack', data_get($message->payload, 'raw.ack')),
                    'from_number' => $message->from_number,
                    'to_number' => $message->to_number,
                    'author' => null,
                    'type' => 'chat',
                    'timestamp' => optional($message->received_at)->timestamp,
                    'received_at' => optional($message->received_at)->toISOString(),
                ];
            });

        return response()->json([
            'ok' => true,
            'source' => 'local_fallback',
            'data' => $deduped,
        ]);
    }

    public function markChatRead(string $chatId): JsonResponse
    {
        $decodedChatId = urldecode($chatId);
        $response = $this->proxy(fn (): array => $this->gateway->markChatRead($decodedChatId));

        WhatsAppMessage::query()
            ->where('chat_id', $decodedChatId)
            ->where('from_me', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $response;
    }

    public function syncChatHistory(Request $request, string $chatId): JsonResponse
    {
        $decodedChatId = urldecode($chatId);
        $limit = max(1, min(500, (int) $request->integer('limit', 150)));

        try {
            $response = $this->gateway->syncChatHistory($decodedChatId, $limit);
            $messages = $response['data']['messages'] ?? [];

            $imported = 0;
            foreach ($messages as $item) {
                $messageId = $item['message_id'] ?? null;
                if (empty($messageId)) {
                    continue;
                }

                WhatsAppMessage::query()->updateOrCreate(
                    ['message_id' => $messageId],
                    [
                        'chat_id' => $item['chat_id'] ?? $decodedChatId,
                        'contact_name' => $decodedChatId,
                        'from_number' => $item['from_number'] ?? null,
                        'to_number' => $item['to_number'] ?? null,
                        'body' => $item['body'] ?? '',
                        'from_me' => (bool) ($item['from_me'] ?? false),
                        'received_at' => isset($item['timestamp']) ? now()->setTimestamp((int) $item['timestamp']) : now(),
                        'read_at' => ! empty($item['from_me']) ? now() : null,
                        'payload' => $item,
                    ]
                );
                $imported++;
            }

            return response()->json([
                'ok' => true,
                'chat_id' => $decodedChatId,
                'imported' => $imported,
                'fetched' => $response['data']['fetched'] ?? count($messages),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => true,
                'chat_id' => $decodedChatId,
                'imported' => 0,
                'fetched' => 0,
                'warning' => 'Live sync is unavailable for this chat right now.',
                'details' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeChatId(string $to): string
    {
        if (str_contains($to, '@')) {
            return $to;
        }

        return preg_replace('/\D+/', '', $to).'@c.us';
    }

    private function proxy(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (ConnectionException) {
            return response()->json([
                'ok' => false,
                'error' => 'WhatsApp gateway is not reachable.',
            ], 503);
        } catch (RequestException $exception) {
            $status = $exception->response?->status() ?? 500;
            $body = $exception->response?->json();

            return response()->json([
                'ok' => false,
                'error' => $body['error'] ?? 'Gateway request failed.',
                'details' => $body,
            ], $status);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'error' => 'Unexpected server error.',
                'details' => $exception->getMessage(),
            ], 500);
        }
    }
}
