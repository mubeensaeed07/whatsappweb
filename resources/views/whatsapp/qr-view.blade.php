<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Web Module</title>
    <style>
        html, body { height: 100%; overflow: hidden; }
        body { margin: 0; font-family: Arial, sans-serif; background: #111b21; color: #e9edef; }
        .app { display: grid; grid-template-columns: 360px 1fr; position: fixed; inset: 0; overflow: hidden; }
        .sidebar { background: #202c33; border-right: 1px solid #2a3942; display: grid; grid-template-rows: auto auto auto minmax(0, 1fr); min-height: 0; }
        .topbar { padding: 12px; border-bottom: 1px solidrgb(113, 121, 126); }
        .status { font-size: 13px; margin-bottom: 8px; color: #aebac1; }
        .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        button { border: 0; border-radius: 6px; padding: 7px 10px; cursor: pointer; background: #00a884; color: #fff; font-size: 12px; }
        button.alt { background: #2a3942; }
        .qr-wrap { padding: 12px; border-bottom: 1px solid #2a3942; display: none; }
        .qr-wrap img { width: 220px; border-radius: 8px; background: #fff; padding: 8px; }
        .search { padding: 10px 12px; border-bottom: 1px solid #2a3942; }
        .search input { width: 100%; border: 0; border-radius: 6px; padding: 9px; background: #2a3942; color: #e9edef; }
        .chat-list { overflow-y: auto; min-height: 0; height: 100%; scrollbar-gutter: stable both-edges; outline: none; padding-right: 0; }
        .chat-item { padding: 12px; border-bottom: 1px solid #2a3942; cursor: pointer; display: grid; grid-template-columns: 42px 1fr auto; gap: 10px; }
        .chat-item.active, .chat-item:hover { background: #2a3942; }
        .avatar { width: 42px; height: 42px; border-radius: 50%; background: #6a7175; display: flex; align-items: center; justify-content: center; font-weight: 700; overflow: hidden; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .chat-name { font-size: 14px; font-weight: 600; color: #e9edef; }
        .chat-last { font-size: 12px; color: #aebac1; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
        .badge { background: #00a884; color: #111b21; min-width: 20px; text-align: center; border-radius: 10px; padding: 2px 6px; font-size: 11px; font-weight: 700; }
        .main { display: grid; grid-template-rows: 62px minmax(0, 1fr) auto; background: #0b141a; min-height: 0; overflow: hidden; position: relative; }
        .chat-header { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #2a3942; background: #202c33; justify-content: space-between; }
        .chat-header-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .chat-header-right { display: flex; align-items: center; gap: 8px; }
        .chat-title { font-size: 15px; font-weight: 600; }
        .chat-sub { font-size: 12px; color: #aebac1; }
        .messages { overflow-y: auto; overflow-x: hidden; padding: 18px 18px 18px; background: #0b141a; min-height: 0; scrollbar-gutter: stable both-edges; outline: none; height: 100%; padding-right: 4px; }
        .empty { color: #8696a0; font-size: 14px; padding: 10px 0; }
        .row { margin-bottom: 8px; display: flex; }
        .row.me { justify-content: flex-end; }
        .bubble { max-width: 70%; padding: 8px 10px; border-radius: 8px; font-size: 14px; line-height: 1.4; word-break: break-word; }
        .row.me .bubble { background: #005c4b; }
        .row.other .bubble { background: #202c33; }
        .meta {
            font-size: 11px;
            color: #b7c5cd;
            margin-top: 4px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
        }
        .ticks {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .ticks.sent,
        .ticks.delivered { color: #d1d7db; }
        .ticks.read { color: #53bdeb; }
        .ticks svg {
            width: 16px;
            height: 11px;
            display: block;
        }
        .composer { display: grid; grid-template-columns: 1fr auto; gap: 10px; padding: 12px; border-top: 1px solid #2a3942; background: #202c33; position: relative; left: auto; right: auto; bottom: auto; z-index: 3; box-shadow: 0 -2px 10px rgba(0,0,0,0.35); }
        .composer input { border: 0; border-radius: 8px; padding: 12px; background: #2a3942; color: #e9edef; }
        .composer button { padding: 0 16px; font-size: 14px; }
        .small-note { font-size: 11px; color: #8696a0; }
        .scroll-latest-btn {
            position: absolute;
            right: 16px;
            bottom: 84px;
            z-index: 12;
            border-radius: 18px;
            padding: 8px 12px;
            font-size: 12px;
            background: #00a884;
            color: #0b141a;
            font-weight: 700;
            display: none;
        }
        .chat-list,
        .messages {
            scrollbar-width: auto;
            -ms-overflow-style: auto;
        }
        .chat-list::-webkit-scrollbar,
        .messages::-webkit-scrollbar {
            width: 14px;
            height: 14px;
        }
        .chat-list::-webkit-scrollbar-track,
        .messages::-webkit-scrollbar-track {
            background: #1b2a33;
        }
        .chat-list::-webkit-scrollbar-thumb,
        .messages::-webkit-scrollbar-thumb {
            background: #6d8a9c;
            border-radius: 10px;
            border: 2px solid #1b2a33;
        }
        .chat-list::-webkit-scrollbar-thumb:hover,
        .messages::-webkit-scrollbar-thumb:hover {
            background: #8da9b9;
        }
        @media (max-width: 900px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="topbar">
            <div id="statusText" class="status">Status: loading...</div>
            <div class="actions">
                <button id="refreshBtn" type="button">Refresh</button>
                <button id="restartBtn" type="button" class="alt">Restart</button>
                <button id="logoutBtn" type="button" class="alt">Logout</button>
                <button id="resetBtn" type="button" class="alt">Reset Session</button>
            </div>
        </div>
        <div id="qrWrap" class="qr-wrap">
            <img id="qrImg" alt="QR">
            <div class="empty">Scan QR to connect</div>
        </div>
        <div class="search"><input id="searchInput" placeholder="Search chats"></div>
        <div id="chatList" class="chat-list" tabindex="0"></div>
    </aside>
    <main class="main">
        <div class="chat-header">
            <div class="chat-header-left">
                <div id="headerAvatar" class="avatar">WA</div>
                <div>
                    <div id="chatTitle" class="chat-title">Select a chat</div>
                    <div id="chatSub" class="chat-sub">Waiting for connection</div>
                </div>
            </div>
            <div class="chat-header-right">
                <span id="syncStatus" class="small-note"></span>
            </div>
        </div>
        <section id="messages" class="messages" tabindex="0"><div class="empty">No chat selected.</div></section>
        <button id="scrollLatestBtn" type="button" class="scroll-latest-btn">New msgs ↓</button>
        <div class="composer">
            <input id="messageInput" placeholder="Type a message" disabled>
            <button id="sendBtn" disabled>Send</button>
        </div>
    </main>
</div>
<script>
    const state = {
        status: "loading",
        chats: [],
        selectedChatId: null,
        selectedChatName: "",
        lastAutoSyncAt: 0,
        chatListScrollTop: 0,
        userScrollingUntil: 0,
        chatsSignature: "",
        messagesSignatureByChat: {},
        activePane: "messages",
    };
    const el = {
        statusText: document.getElementById("statusText"),
        qrWrap: document.getElementById("qrWrap"),
        qrImg: document.getElementById("qrImg"),
        chatList: document.getElementById("chatList"),
        searchInput: document.getElementById("searchInput"),
        chatTitle: document.getElementById("chatTitle"),
        chatSub: document.getElementById("chatSub"),
        headerAvatar: document.getElementById("headerAvatar"),
        messages: document.getElementById("messages"),
        messageInput: document.getElementById("messageInput"),
        sendBtn: document.getElementById("sendBtn"),
        syncStatus: document.getElementById("syncStatus"),
        scrollLatestBtn: document.getElementById("scrollLatestBtn"),
    };
    async function getJson(url) { const r = await fetch(url, { headers: { Accept: "application/json" } }); if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); }
    async function postJson(url, payload = {}) { const r = await fetch(url, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify(payload) }); const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.error || d.message || ("HTTP " + r.status)); return d; }
    function initials(name) { const txt = (name || "WA").trim(); return txt.slice(0, 2).toUpperCase(); }
    function escapeHtml(value) { return String(value || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;"); }
    function formatPhoneDigits(digits) {
        if (!digits) return "";
        if (digits.length >= 11) {
            const country = digits.slice(0, digits.length - 10);
            const p1 = digits.slice(-10, -7);
            const p2 = digits.slice(-7, -4);
            const p3 = digits.slice(-4);
            return `+${country} ${p1} ${p2} ${p3}`;
        }
        return digits;
    }
    function prettyName(name, chatId) {
        const candidate = (name || "").trim();
        if (candidate && candidate !== chatId && !candidate.includes("@")) {
            return candidate;
        }
        const raw = String(chatId || "").split("@")[0].replace(/\D/g, "");
        return formatPhoneDigits(raw) || (chatId || "Unknown");
    }
    function avatarColor(seed) {
        let hash = 0;
        const text = String(seed || "wa");
        for (let i = 0; i < text.length; i += 1) hash = text.charCodeAt(i) + ((hash << 5) - hash);
        const hue = Math.abs(hash) % 360;
        return `hsl(${hue}, 58%, 38%)`;
    }
    function avatarMarkup(name, picUrl) {
        if (picUrl) {
            const safe = String(picUrl).replace(/"/g, "&quot;");
            return `<div class="avatar"><img src="${safe}" alt="${name || "avatar"}"></div>`;
        }
        const bg = avatarColor(name);
        return `<div class="avatar" style="background:${bg};">${initials(name)}</div>`;
    }
    function renderStatus(status, error, qr) {
        state.status = status;
        el.statusText.textContent = error ? `Status: ${status} (${error})` : `Status: ${status}`;
        const showQr = status !== "connected";
        el.qrWrap.style.display = showQr ? "block" : "none";
        if (showQr && qr) el.qrImg.src = qr;
        if (status !== "connected") {
            clearChatUiForPrivacy();
        }
    }
    function clearChatUiForPrivacy() {
        state.chats = [];
        state.chatsSignature = "";
        state.messagesSignatureByChat = {};
        state.selectedChatId = null;
        state.selectedChatName = "";
        state.chatListScrollTop = 0;

        el.chatList.innerHTML = '<div class="empty" style="padding:12px;">Chats are hidden until WhatsApp is connected.</div>';
        el.messages.innerHTML = '<div class="empty">No chat selected.</div>';
        el.chatTitle.textContent = "Select a chat";
        el.chatSub.textContent = "Waiting for connection";
        el.headerAvatar.innerHTML = "WA";
        el.headerAvatar.style.background = "";
        el.messageInput.value = "";
        el.messageInput.disabled = true;
        el.sendBtn.disabled = true;
        el.syncStatus.textContent = "";
        el.scrollLatestBtn.style.display = "none";
    }
    function renderChats() {
        state.chatListScrollTop = el.chatList.scrollTop;
        const q = el.searchInput.value.toLowerCase().trim();
        const filtered = state.chats.filter(c => (c.name || "").toLowerCase().includes(q) || (c.chat_id || "").toLowerCase().includes(q));
        if (!filtered.length) {
            el.chatList.innerHTML = '<div class="empty" style="padding:12px;">No chats yet.</div>';
            return;
        }
        el.chatList.innerHTML = filtered.map(chat => `
            <div class="chat-item ${chat.chat_id === state.selectedChatId ? "active" : ""}" data-chat-id="${escapeHtml(chat.chat_id)}" data-name="${escapeHtml(prettyName(chat.name, chat.chat_id))}" data-pic="${escapeHtml(chat.profile_pic_url || "")}">
                ${avatarMarkup(prettyName(chat.name, chat.chat_id), chat.profile_pic_url)}
                <div>
                    <div class="chat-name">${escapeHtml(prettyName(chat.name, chat.chat_id))}</div>
                    <div class="chat-last">${escapeHtml(chat.last_message || "")}</div>
                </div>
                <div>${chat.unread_count > 0 ? `<span class="badge">${chat.unread_count}</span>` : ""}</div>
            </div>`).join("");
        el.chatList.querySelectorAll(".chat-item").forEach(item => item.addEventListener("click", () => openChat(item.dataset.chatId, item.dataset.name, item.dataset.pic)));
        requestAnimationFrame(() => {
            el.chatList.scrollTop = state.chatListScrollTop;
        });
    }
    function isNearBottom(container, threshold = 40) {
        const distance = container.scrollHeight - container.scrollTop - container.clientHeight;
        return distance <= threshold;
    }
    function markUserScrolling() {
        state.userScrollingUntil = Date.now() + 10000;
    }
    function setActivePane(pane) {
        state.activePane = pane;
    }
    function keyboardScrollCurrentPane(direction) {
        const container = state.activePane === "chats" ? el.chatList : el.messages;
        if (!container) return;
        const step = 80;
        container.scrollTop += (direction > 0 ? step : -step);
        markUserScrolling();
        if (container === el.messages) toggleScrollLatestButton();
    }
    function formatMessageTime(isoOrNull) {
        if (!isoOrNull) return "";
        return new Date(isoOrNull).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    }
    function messageTickMarkup(message) {
        if (!message.from_me) return "";
        const ack = Number.isFinite(Number(message.ack)) ? Number(message.ack) : 1;
        const singleTickSvg = '<svg viewBox="0 0 16 11" aria-hidden="true"><path d="M1.2 6.2l2.2 2.3L8.2 2.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const doubleTickSvg = '<svg viewBox="0 0 16 11" aria-hidden="true"><path d="M0.8 6.3l2 2.2L7.1 4.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.4 6.3l2 2.2L11.7 2.2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        if (ack >= 3) return `<span class="ticks read">${doubleTickSvg}</span>`;
        if (ack >= 2) return `<span class="ticks delivered">${doubleTickSvg}</span>`;
        return `<span class="ticks sent">${singleTickSvg}</span>`;
    }
    function bindForcedWheelScroll(container, onAfterScroll) {
        container.addEventListener("wheel", (e) => {
            if (!container) return;
            if (container.scrollHeight <= container.clientHeight) return;
            e.preventDefault();
            container.scrollTop += e.deltaY;
            markUserScrolling();
            if (onAfterScroll) onAfterScroll();
        }, { passive: false });
    }
    function chatsSignature(list) {
        return (list || []).map(c => `${c.chat_id}|${c.last_at}|${c.unread_count}|${c.last_message}`).join("||");
    }
    function messagesSignature(chatId, list) {
        return `${chatId}::` + (list || []).map(m => `${m.message_id}|${m.timestamp}|${m.body}|${m.from_me ? 1 : 0}|${m.ack ?? ""}`).join("||");
    }
    function toggleScrollLatestButton() {
        if (isNearBottom(el.messages, 50)) {
            el.scrollLatestBtn.style.display = "none";
        } else {
            el.scrollLatestBtn.style.display = "inline-flex";
        }
    }
    function renderMessages(messages, forceBottom = false) {
        if (!messages.length) {
            el.messages.innerHTML = '<div class="empty">No messages in this chat.</div>';
            return;
        }
        const previousScrollTop = el.messages.scrollTop;
        const shouldStickToBottom = forceBottom || isNearBottom(el.messages);
        el.messages.innerHTML = messages.map(m => `
            <div class="row ${m.from_me ? "me" : "other"}">
                <div class="bubble">
                    ${escapeHtml(m.body || "")}
                    <div class="meta">
                        <span>${formatMessageTime(m.received_at)}</span>
                        ${messageTickMarkup(m)}
                    </div>
                </div>
            </div>`).join("");
        requestAnimationFrame(() => {
            if (shouldStickToBottom) {
                el.messages.scrollTop = el.messages.scrollHeight;
                toggleScrollLatestButton();
                return;
            }
            el.messages.scrollTop = previousScrollTop;
            toggleScrollLatestButton();
        });
    }
    async function openChat(chatId, name, picUrl = "") {
        state.selectedChatId = chatId; state.selectedChatName = name || chatId;
        el.chatTitle.textContent = state.selectedChatName;
        el.chatSub.textContent = chatId;
        if (picUrl) {
            el.headerAvatar.innerHTML = `<img src="${String(picUrl).replace(/"/g, "&quot;")}" alt="${escapeHtml(state.selectedChatName)}">`;
        } else {
            el.headerAvatar.textContent = initials(state.selectedChatName);
            el.headerAvatar.style.background = avatarColor(state.selectedChatName);
        }
        el.messageInput.disabled = false; el.sendBtn.disabled = false;
        el.syncStatus.textContent = "";
        await loadCurrentChatMessages(true);
        try { await postJson(`/api/whatsapp/chats/${encodeURIComponent(chatId)}/read`); } catch (_e) {}
        autoSyncHistory(true);
        await loadChats();
    }
    async function loadStatus() {
        const [statusRes, qrRes] = await Promise.all([getJson("/api/whatsapp/status"), getJson("/api/whatsapp/qr")]);
        renderStatus(statusRes?.data?.status || "unknown", statusRes?.data?.lastError || null, qrRes?.data?.qr || null);
    }
    async function loadChats() {
        if (state.status !== "connected") {
            clearChatUiForPrivacy();
            return;
        }
        try {
            const res = await getJson("/api/whatsapp/chats");
            const incoming = res.data || [];
            const nextSignature = chatsSignature(incoming);
            if (nextSignature === state.chatsSignature) return;
            state.chatsSignature = nextSignature;
            state.chats = incoming;
            renderChats();
        } catch (e) {
            if (String(e.message || "").includes("HTTP 409")) {
                clearChatUiForPrivacy();
                return;
            }
            throw e;
        }
    }
    async function loadCurrentChatMessages(forceBottom = false) {
        if (!state.selectedChatId) return;
        try {
            const limit = forceBottom ? 120 : 60;
            const res = await getJson(`/api/whatsapp/chats/${encodeURIComponent(state.selectedChatId)}/messages?limit=${limit}`);
            const rows = res.data || [];
            const nextSignature = messagesSignature(state.selectedChatId, rows);
            if (!forceBottom && state.messagesSignatureByChat[state.selectedChatId] === nextSignature) {
                return;
            }
            state.messagesSignatureByChat[state.selectedChatId] = nextSignature;
            renderMessages(rows, forceBottom);
        } catch (e) {
            el.messages.innerHTML = `<div class="empty">Unable to load messages (${e.message}). New incoming messages will appear once received.</div>`;
        }
    }
    async function sendMessage() {
        const msg = el.messageInput.value.trim();
        if (!msg || !state.selectedChatId) return;
        el.sendBtn.disabled = true;
        try {
            await postJson("/api/whatsapp/send", { to: state.selectedChatId, message: msg, contact_name: state.selectedChatName });
            el.messageInput.value = "";
            await loadCurrentChatMessages(true);
            await loadChats();
        } catch (e) { alert("Send failed: " + e.message); }
        finally { el.sendBtn.disabled = false; }
    }
    async function autoSyncHistory(isInitial = false) {
        if (!state.selectedChatId) return;
        const now = Date.now();
        if (state.lastAutoSyncAt && (now - state.lastAutoSyncAt) < 30000) {
            return;
        }
        state.lastAutoSyncAt = now;
        el.syncStatus.textContent = isInitial ? "Syncing history..." : "Auto-syncing...";
        try {
            const res = await postJson(`/api/whatsapp/chats/${encodeURIComponent(state.selectedChatId)}/sync-history`, { limit: 200 });
            el.syncStatus.textContent = res.warning ? res.warning : `Imported ${res.imported || 0}`;
            await loadCurrentChatMessages();
        } catch (e) {
            el.syncStatus.textContent = "Auto-sync unavailable";
        }
        setTimeout(() => { el.syncStatus.textContent = ""; }, 2500);
    }
    async function action(url, ask = false) {
        if (ask && !confirm("Are you sure?")) return;
        await postJson(url);
        await boot();
    }

    async function resetSession() {
        const resetBtn = document.getElementById("resetBtn");
        resetBtn.disabled = true;
        el.statusText.textContent = "Status: resetting session...";
        try {
            await postJson("/api/whatsapp/reset-session");
            el.statusText.textContent = "Status: session reset requested";
            await boot();
        } catch (e) {
            el.statusText.textContent = "Status: reset failed (" + e.message + ")";
            alert("Reset Session failed: " + e.message);
        } finally {
            resetBtn.disabled = false;
        }
    }
    async function boot() {
        try {
            await loadStatus();
            if (state.status !== "connected") {
                return;
            }
            await loadChats();
            if (state.selectedChatId) await loadCurrentChatMessages();
        }
        catch (e) { el.statusText.textContent = "Status: error (" + e.message + ")"; }
    }
    document.getElementById("refreshBtn").addEventListener("click", boot);
    document.getElementById("restartBtn").addEventListener("click", () => action("/api/whatsapp/restart"));
    document.getElementById("logoutBtn").addEventListener("click", () => action("/api/whatsapp/logout", true));
    document.getElementById("resetBtn").addEventListener("click", resetSession);
    el.searchInput.addEventListener("input", renderChats);
    el.sendBtn.addEventListener("click", sendMessage);
    el.messageInput.addEventListener("keydown", (e) => { if (e.key === "Enter") sendMessage(); });
    el.chatList.addEventListener("click", () => { setActivePane("chats"); el.chatList.focus(); });
    el.messages.addEventListener("click", () => { setActivePane("messages"); el.messages.focus(); });
    el.chatList.addEventListener("focus", () => setActivePane("chats"));
    el.messages.addEventListener("focus", () => setActivePane("messages"));
    el.messages.addEventListener("scroll", () => { markUserScrolling(); toggleScrollLatestButton(); });
    bindForcedWheelScroll(el.messages, toggleScrollLatestButton);
    el.messages.addEventListener("mousedown", markUserScrolling);
    el.chatList.addEventListener("scroll", markUserScrolling);
    bindForcedWheelScroll(el.chatList);
    el.chatList.addEventListener("mousedown", markUserScrolling);
    el.scrollLatestBtn.addEventListener("click", () => {
        el.messages.scrollTop = el.messages.scrollHeight;
        toggleScrollLatestButton();
    });
    document.addEventListener("keydown", (e) => {
        if (document.activeElement === el.messageInput) return;
        if (e.key === "ArrowDown") {
            e.preventDefault();
            keyboardScrollCurrentPane(1);
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            keyboardScrollCurrentPane(-1);
        }
    });
    boot();
    setInterval(async () => {
        if (Date.now() < state.userScrollingUntil) return;
        await loadStatus();
        if (state.status !== "connected") return;
        await loadChats();
        await loadCurrentChatMessages(false);
        // Keep heavy history sync infrequent to avoid UI lag.
        if (state.selectedChatId && (!state.lastAutoSyncAt || (Date.now() - state.lastAutoSyncAt) > 120000)) {
            await autoSyncHistory(false);
        }
    }, 5000);
</script>
</body>
</html>
