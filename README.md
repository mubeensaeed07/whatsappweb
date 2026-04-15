# Laravel WhatsApp Gateway Test Project

This project provides a Laravel-first API layer with a separate Node.js WhatsApp gateway service based on `whatsapp-web.js`.

The implementation supports:

- QR code login flow
- automatic session persistence (`LocalAuth`)
- auto reconnect by reusing saved session
- send message API
- receive message webhook into Laravel and database storage

The baseline idea for QR route handling comes from this repository:
[gomeslucasm/whatsappjs-qr-code-view](https://github.com/gomeslucasm/whatsappjs-qr-code-view)

## Architecture

- `whatsapp-gateway` (Node.js): owns WhatsApp Web client, QR generation, session files, and send/receive events.
- Laravel app: exposes project APIs, proxies gateway actions, stores incoming messages.

## 1) Install and Configure Laravel

1. Install PHP dependencies:
   - `composer install`
2. Copy env:
   - `copy .env.example .env`
3. Set WhatsApp variables in `.env`:
   - `WHATSAPP_GATEWAY_URL=http://127.0.0.1:3001`
   - `WHATSAPP_GATEWAY_TOKEN=change-me`
   - `WHATSAPP_WEBHOOK_SECRET=change-this-secret`
4. Run migrations:
   - `php artisan migrate`
5. Start Laravel API:
   - `php artisan serve`

## 2) Install and Configure Node WhatsApp Gateway

1. Open `whatsapp-gateway`.
2. Install dependencies:
   - `npm install`
3. Copy env:
   - `copy .env.example .env`
4. Keep values aligned with Laravel:
   - `API_TOKEN` must match `WHATSAPP_GATEWAY_TOKEN`
   - `LARAVEL_WEBHOOK_SECRET` must match `WHATSAPP_WEBHOOK_SECRET`
   - `LARAVEL_WEBHOOK_URL=http://127.0.0.1:8000/api/whatsapp/webhook/incoming`
5. Start the gateway:
   - `npm start`

## 3) API Endpoints (Laravel)

- `GET /api/whatsapp/status`
- `GET /api/whatsapp/qr`
- `POST /api/whatsapp/send`
  - payload: `{ "to": "923001234567", "message": "Hello" }`
- `GET /api/whatsapp/messages?limit=50`
- `POST /api/whatsapp/restart`
- `POST /api/whatsapp/logout`

## Testing Flow

1. Start Laravel and gateway.
2. Call `GET /api/whatsapp/qr` and render the returned `data.qr` data-url in your UI.
3. Scan QR with WhatsApp app.
4. Confirm connected status using `GET /api/whatsapp/status`.
5. Send test message with `POST /api/whatsapp/send`.
6. Send message to your connected number and verify it is stored in `whats_app_messages`.

## Notes

- Session persistence is handled by `whatsapp-web.js` `LocalAuth` inside `whatsapp-gateway`.
- This test setup is intentionally modular so it can be moved later into your existing main module without major changes.
