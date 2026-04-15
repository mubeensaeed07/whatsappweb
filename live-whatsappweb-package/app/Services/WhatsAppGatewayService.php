<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WhatsAppGatewayService
{
    public function status(): array
    {
        return $this->request('get', '/status');
    }

    public function qr(): array
    {
        return $this->request('get', '/qr');
    }

    public function sendMessage(string $to, string $message): array
    {
        return $this->request('post', '/send', [
            'to' => $to,
            'message' => $message,
        ]);
    }

    public function chats(int $limit = 50): array
    {
        return $this->request('get', '/chats?limit='.$limit);
    }

    public function chatMessages(string $chatId, int $limit = 80): array
    {
        return $this->request('get', '/chats/'.urlencode($chatId).'/messages?limit='.$limit);
    }

    public function markChatRead(string $chatId): array
    {
        return $this->request('post', '/chats/'.urlencode($chatId).'/read');
    }

    public function syncChatHistory(string $chatId, int $limit = 150): array
    {
        return $this->request('post', '/chats/'.urlencode($chatId).'/sync-history', [
            'limit' => $limit,
        ]);
    }

    public function restart(): array
    {
        return $this->request('post', '/restart');
    }

    public function logout(): array
    {
        return $this->request('post', '/logout');
    }

    public function resetSession(): array
    {
        return $this->request('post', '/reset-session');
    }

    /**
     * @throws ConnectionException
     * @throws RequestException
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $baseUrl = rtrim(config('services.whatsapp_gateway.url'), '/');
        $token = config('services.whatsapp_gateway.token');

        $request = Http::acceptJson()->timeout(20);

        if (! empty($token)) {
            $request = $request->withToken($token);
        }

        $response = $request->{$method}($baseUrl.$path, $payload)->throw();

        return $response->json() ?? [];
    }
}
