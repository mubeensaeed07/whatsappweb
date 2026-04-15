const express = require("express");
const axios = require("axios");
const QRCode = require("qrcode");
const fs = require("fs");
const path = require("path");
const { Client, LocalAuth } = require("whatsapp-web.js");
const dotenv = require("dotenv");

dotenv.config();

const app = express();
app.use(express.json());

const PORT = Number(process.env.PORT || 3001);
const API_TOKEN = process.env.API_TOKEN || "";
const SESSION_ID = process.env.SESSION_ID || "laravel-main";
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || "";
const LARAVEL_WEBHOOK_SECRET = process.env.LARAVEL_WEBHOOK_SECRET || "";

const state = {
  status: "initializing",
  qr: null,
  info: null,
  lastError: null,
  lastEventAt: new Date().toISOString(),
};

let client = null;
let initializing = false;

function touchState(status, extra = {}) {
  state.status = status;
  state.lastEventAt = new Date().toISOString();
  Object.assign(state, extra);
  console.log(`[state] ${state.status}`);
}

function normalizeChatId(to) {
  if (to.includes("@")) {
    return to;
  }

  const normalized = to.replace(/[^\d]/g, "");
  return `${normalized}@c.us`;
}

async function postIncomingMessage(payload) {
  if (!LARAVEL_WEBHOOK_URL) {
    return;
  }

  try {
    await axios.post(LARAVEL_WEBHOOK_URL, payload, {
      timeout: 10000,
      headers: {
        "X-Webhook-Secret": LARAVEL_WEBHOOK_SECRET,
      },
    });
  } catch (error) {
    console.error("[webhook] failed:", error.message);
  }
}

async function initClient() {
  if (initializing) {
    return;
  }

  initializing = true;
  touchState("initializing", { qr: null, lastError: null });

  if (client) {
    try {
      await client.destroy();
    } catch (error) {
      console.error("[client] destroy failed:", error.message);
    }
  }

  client = new Client({
    authStrategy: new LocalAuth({ clientId: SESSION_ID }),
    authTimeoutMs: 120000,
    takeoverOnConflict: true,
    takeoverTimeoutMs: 0,
    puppeteer: {
      headless: true,
      args: ["--no-sandbox", "--disable-setuid-sandbox"],
    },
  });

  client.on("qr", async (qrText) => {
    try {
      const qrDataUrl = await QRCode.toDataURL(qrText);
      touchState("qr_ready", { qr: qrDataUrl });
      console.log("[wa] qr received");
    } catch (error) {
      touchState("qr_ready", { qr: null, lastError: error.message });
    }
  });

  client.on("loading_screen", (percent, message) => {
    touchState("loading", {
      lastError: null,
      loading: { percent, message },
    });
    console.log(`[wa] loading ${percent}% ${message}`);
  });

  client.on("change_state", (waState) => {
    console.log(`[wa] state changed: ${waState}`);
  });

  client.on("ready", async () => {
    const info = client.info
      ? {
          wid: client.info.wid?._serialized || null,
          pushname: client.info.pushname || null,
          platform: client.info.platform || null,
        }
      : null;

    touchState("connected", { qr: null, info, lastError: null });
    console.log("[wa] client ready");
  });

  client.on("authenticated", () => {
    touchState("authenticated", { lastError: null });
    console.log("[wa] authenticated");
  });

  client.on("auth_failure", (message) => {
    touchState("auth_failure", { lastError: message || "Authentication failed." });
    console.error("[wa] auth failure:", message);
  });

  client.on("disconnected", (reason) => {
    touchState("disconnected", {
      qr: null,
      info: null,
      lastError: typeof reason === "string" ? reason : "Disconnected",
    });
    console.error("[wa] disconnected:", reason);
  });

  client.on("message", async (message) => {
    const contactName =
      message._data?.notifyName ||
      message._data?.pushname ||
      message._data?.from ||
      null;

    await postIncomingMessage({
      message_id: message.id?._serialized || null,
      chat_id: message.fromMe ? message.to || message.from : message.from,
      contact_name: contactName,
      from: message.from,
      to: message.to || null,
      body: message.body || "",
      from_me: Boolean(message.fromMe),
      ack: typeof message.ack === "number" ? message.ack : null,
      timestamp: message.timestamp || null,
      raw: {
        type: message.type || null,
        hasMedia: Boolean(message.hasMedia),
        fromMe: Boolean(message.fromMe),
        ack: typeof message.ack === "number" ? message.ack : null,
      },
    });
  });

  client.on("message_ack", async (message, ack) => {
    if (!message?.fromMe) {
      return;
    }

    const contactName =
      message._data?.notifyName ||
      message._data?.pushname ||
      message._data?.from ||
      null;

    await postIncomingMessage({
      message_id: message.id?._serialized || null,
      chat_id: message.to || message.from || null,
      contact_name: contactName,
      from: message.from || "me",
      to: message.to || null,
      body: message.body || "",
      from_me: true,
      ack: typeof ack === "number" ? ack : null,
      timestamp: message.timestamp || null,
      event_type: "message_ack",
      is_ack_update: true,
      raw: {
        type: message.type || null,
        hasMedia: Boolean(message.hasMedia),
        fromMe: true,
        ack: typeof ack === "number" ? ack : null,
      },
    });
  });

  try {
    await client.initialize();
  } catch (error) {
    touchState("error", { lastError: error.message });
  } finally {
    initializing = false;
  }
}

function authorize(req, res, next) {
  if (!API_TOKEN) {
    return res.status(500).json({
      ok: false,
      error: "API_TOKEN is missing in gateway env.",
    });
  }

  const header = req.header("authorization") || "";
  const token = header.startsWith("Bearer ") ? header.slice(7) : "";

  if (!token || token !== API_TOKEN) {
    return res.status(401).json({
      ok: false,
      error: "Unauthorized",
    });
  }

  next();
}

app.get("/health", (_req, res) => {
  res.json({ ok: true, status: state.status });
});

app.use(authorize);

app.get("/status", (_req, res) => {
  res.json({
    ok: true,
    data: {
      status: state.status,
      info: state.info,
      lastError: state.lastError,
      lastEventAt: state.lastEventAt,
    },
  });
});

app.get("/qr", (_req, res) => {
  res.json({
    ok: true,
    data: {
      status: state.status,
      qr: state.qr,
      lastEventAt: state.lastEventAt,
    },
  });
});

app.post("/send", async (req, res) => {
  const to = req.body?.to;
  const message = req.body?.message;

  if (!to || !message) {
    return res.status(422).json({
      ok: false,
      error: "Both 'to' and 'message' are required.",
    });
  }

  if (!client || state.status !== "connected") {
    return res.status(409).json({
      ok: false,
      error: "WhatsApp is not connected yet.",
      status: state.status,
    });
  }

  try {
    const chatId = normalizeChatId(String(to));
    const sent = await client.sendMessage(chatId, String(message));

    return res.json({
      ok: true,
      data: {
        id: sent.id?._serialized || null,
        to: chatId,
        body: sent.body || "",
        ack: typeof sent.ack === "number" ? sent.ack : null,
        timestamp: sent.timestamp || null,
      },
    });
  } catch (error) {
    return res.status(500).json({
      ok: false,
      error: error.message || "Send message failed.",
    });
  }
});

app.get("/chats", async (req, res) => {
  const limit = Math.max(1, Math.min(200, Number(req.query?.limit || 50)));

  if (!client || state.status !== "connected") {
    return res.status(409).json({
      ok: false,
      error: "WhatsApp is not connected yet.",
      status: state.status,
    });
  }

  try {
    const chats = await client.getChats();
    const sorted = chats
      .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0))
      .slice(0, limit);

    const items = await Promise.all(
      sorted.map(async (chat) => {
        const chatId = chat.id?._serialized || null;
        let profilePicUrl = null;

        try {
          if (chatId) {
            profilePicUrl = await client.getProfilePicUrl(chatId);
          }
        } catch (_error) {
          profilePicUrl = null;
        }

        return {
          chat_id: chatId,
          name: chat.name || chat.formattedTitle || chatId,
          is_group: Boolean(chat.isGroup),
          profile_pic_url: profilePicUrl,
          unread_count: chat.unreadCount || 0,
          last_message:
            chat.lastMessage?.body ||
            chat.lastMessage?._data?.caption ||
            "",
          last_message_from_me: Boolean(chat.lastMessage?.fromMe),
          last_at: chat.timestamp ? new Date(chat.timestamp * 1000).toISOString() : null,
        };
      })
    );

    return res.json({
      ok: true,
      data: items,
    });
  } catch (error) {
    return res.status(500).json({
      ok: false,
      error: error.message || "Failed to load chats.",
    });
  }
});

app.get("/chats/:chatId/messages", async (req, res) => {
  const chatId = req.params.chatId;
  const limit = Math.max(1, Math.min(300, Number(req.query?.limit || 80)));

  if (!client || state.status !== "connected") {
    return res.status(409).json({
      ok: false,
      error: "WhatsApp is not connected yet.",
      status: state.status,
    });
  }

  try {
    const chat = await client.getChatById(chatId);
    const messages = await chat.fetchMessages({ limit });

    return res.json({
      ok: true,
      data: messages.map((message) => ({
        message_id: message.id?._serialized || null,
        chat_id: chatId,
        body: message.body || "",
        from_me: Boolean(message.fromMe),
        ack: typeof message.ack === "number" ? message.ack : null,
        from_number: message.from || null,
        to_number: message.to || null,
        author: message.author || null,
        type: message.type || null,
        timestamp: message.timestamp || null,
        received_at: message.timestamp
          ? new Date(message.timestamp * 1000).toISOString()
          : null,
      })),
    });
  } catch (error) {
    return res.status(500).json({
      ok: false,
      error: error.message || "Failed to load messages.",
    });
  }
});

app.post("/chats/:chatId/read", async (req, res) => {
  const chatId = req.params.chatId;

  if (!client || state.status !== "connected") {
    return res.status(409).json({
      ok: false,
      error: "WhatsApp is not connected yet.",
      status: state.status,
    });
  }

  try {
    const chat = await client.getChatById(chatId);
    await chat.sendSeen();

    return res.json({
      ok: true,
      data: { chat_id: chatId, marked: true },
    });
  } catch (error) {
    return res.status(500).json({
      ok: false,
      error: error.message || "Failed to mark chat as read.",
    });
  }
});

app.post("/chats/:chatId/sync-history", async (req, res) => {
  const chatId = req.params.chatId;
  const limit = Math.max(1, Math.min(500, Number(req.body?.limit || 150)));

  if (!client || state.status !== "connected") {
    return res.status(409).json({
      ok: false,
      error: "WhatsApp is not connected yet.",
      status: state.status,
    });
  }

  try {
    const chat = await client.getChatById(chatId);
    const messages = await chat.fetchMessages({ limit });

    return res.json({
      ok: true,
      data: {
        chat_id: chatId,
        fetched: messages.length,
        messages: messages.map((message) => ({
          message_id: message.id?._serialized || null,
          chat_id: chatId,
          body: message.body || "",
          from_me: Boolean(message.fromMe),
          ack: typeof message.ack === "number" ? message.ack : null,
          from_number: message.from || null,
          to_number: message.to || null,
          author: message.author || null,
          type: message.type || null,
          timestamp: message.timestamp || null,
          received_at: message.timestamp
            ? new Date(message.timestamp * 1000).toISOString()
            : null,
        })),
      },
    });
  } catch (error) {
    return res.status(500).json({
      ok: false,
      error: error.message || "Failed to sync chat history.",
    });
  }
});

app.post("/restart", async (_req, res) => {
  await initClient();

  return res.json({
    ok: true,
    data: {
      status: state.status,
    },
  });
});

app.post("/logout", async (_req, res) => {
  if (!client) {
    return res.json({ ok: true, data: { status: state.status } });
  }

  try {
    await client.logout();
    await client.destroy();
    touchState("logged_out", { qr: null, info: null });
    await initClient();

    return res.json({
      ok: true,
      data: {
        status: state.status,
      },
    });
  } catch (error) {
    return res.status(500).json({
      ok: false,
      error: error.message || "Logout failed.",
    });
  }
});

app.post("/reset-session", async (_req, res) => {
  try {
    if (client) {
      try {
        await client.destroy();
      } catch (error) {
        console.error("[wa] destroy before reset failed:", error.message);
      }
    }

    const sessionPath = path.resolve(process.cwd(), ".wwebjs_auth", `session-${SESSION_ID}`);
    fs.rmSync(sessionPath, { recursive: true, force: true });
    touchState("session_reset", { qr: null, info: null, lastError: null });

    await initClient();

    return res.json({
      ok: true,
      data: { status: state.status },
    });
  } catch (error) {
    return res.status(500).json({
      ok: false,
      error: error.message || "Session reset failed.",
    });
  }
});

app.listen(PORT, async () => {
  console.log(`[gateway] running on http://127.0.0.1:${PORT}`);
  await initClient();
});
