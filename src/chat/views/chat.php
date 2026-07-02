<?php
/**
 * View: interface web do chat em tempo real.
 *
 * Variáveis recebidas:
 *   $title      — título da página
 *   $wsUrl      — URL do WebSocket (ws://... ou wss://...)
 *   $statusUrl  — URL da rota de status HTTP
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Chat') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .chat-wrapper {
            width: 100%;
            max-width: 680px;
            height: 90dvh;
            display: flex;
            flex-direction: column;
            border: 1px solid #1e293b;
            border-radius: 12px;
            overflow: hidden;
            background: #1e293b;
        }

        .chat-header {
            padding: 14px 18px;
            background: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #334155;
        }

        .chat-header h1 { font-size: 1rem; font-weight: 600; }

        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #64748b;
            flex-shrink: 0;
            transition: background .3s;
        }
        .status-dot.connected    { background: #22c55e; }
        .status-dot.disconnected { background: #ef4444; }

        .online-count {
            margin-left: auto;
            font-size: .75rem;
            color: #94a3b8;
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .msg {
            max-width: 80%;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: .875rem;
            line-height: 1.4;
            word-break: break-word;
        }
        .msg.mine      { background: #3b82f6; align-self: flex-end; border-radius: 10px 10px 2px 10px; }
        .msg.others    { background: #334155; align-self: flex-start; border-radius: 10px 10px 10px 2px; }
        .msg.system    { background: transparent; align-self: center; color: #64748b; font-size: .75rem; font-style: italic; }
        .msg .from     { font-size: .7rem; opacity: .7; margin-bottom: 2px; }

        .chat-input {
            display: flex;
            gap: 8px;
            padding: 12px 16px;
            border-top: 1px solid #334155;
            background: #0f172a;
        }

        .chat-input input {
            flex: 1;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #1e293b;
            color: #e2e8f0;
            font-size: .875rem;
            outline: none;
        }
        .chat-input input:focus { border-color: #3b82f6; }

        .chat-input button {
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            background: #3b82f6;
            color: #fff;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .chat-input button:hover    { background: #2563eb; }
        .chat-input button:disabled { background: #334155; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="chat-wrapper">
    <div class="chat-header">
        <span class="status-dot" id="dot"></span>
        <h1>Chat ao vivo</h1>
        <span class="online-count" id="onlineCount">conectando…</span>
    </div>

    <div class="messages" id="messages"></div>

    <div class="chat-input">
        <input
            type="text"
            id="msgInput"
            placeholder="Digite uma mensagem…"
            disabled
            maxlength="500"
            autocomplete="off"
        >
        <button id="sendBtn" disabled>Enviar</button>
    </div>
</div>

<script>
(function () {
    const WS_URL  = <?= json_encode($wsUrl) ?>;
    const myColor = '#3b82f6';

    const dot          = document.getElementById('dot');
    const onlineCount  = document.getElementById('onlineCount');
    const messages     = document.getElementById('messages');
    const msgInput     = document.getElementById('msgInput');
    const sendBtn      = document.getElementById('sendBtn');

    let myFd = null;
    let ws   = null;

    // ── Helpers ──────────────────────────────────────────────────────────────

    function addMessage(text, type = 'system', from = null) {
        const div = document.createElement('div');
        div.className = 'msg ' + type;

        if (from !== null && type === 'others') {
            const label = document.createElement('div');
            label.className = 'from';
            label.textContent = 'usuário #' + from;
            div.appendChild(label);
        }

        div.appendChild(document.createTextNode(text));
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function setConnected(connected) {
        dot.className       = 'status-dot ' + (connected ? 'connected' : 'disconnected');
        msgInput.disabled   = !connected;
        sendBtn.disabled    = !connected;
        if (connected) msgInput.focus();
    }

    function send() {
        const text = msgInput.value.trim();
        if (!text || !ws || ws.readyState !== WebSocket.OPEN) return;

        ws.send(JSON.stringify({ event: 'message', text }));

        // Exibe imediatamente do lado do remetente
        addMessage(text, 'mine');
        msgInput.value = '';
    }

    // ── WebSocket ─────────────────────────────────────────────────────────────

    function connect() {
        ws = new WebSocket(WS_URL);

        ws.onopen = () => {
            console.log('[WS] conectado');
        };

        ws.onmessage = ({ data }) => {
            let payload;
            try { payload = JSON.parse(data); } catch { return; }

            switch (payload.event) {
                case 'connected':
                    myFd = payload.user;
                    setConnected(true);
                    onlineCount.textContent = payload.online + ' online';
                    addMessage('Você entrou como usuário #' + payload.user + '. (' + payload.online + ' online)', 'system');
                    break;

                case 'user_joined':
                    onlineCount.textContent = payload.online + ' online';
                    addMessage('Usuário #' + payload.user + ' entrou. (' + payload.online + ' online)', 'system');
                    break;

                case 'user_left':
                    onlineCount.textContent = payload.online + ' online';
                    addMessage('Usuário #' + payload.user + ' saiu. (' + payload.online + ' online)', 'system');
                    break;

                case 'message':
                    // Ignora mensagens próprias (já adicionadas localmente em send())
                    if (payload.from !== myFd) {
                        addMessage(payload.text, 'others', payload.from);
                    }
                    break;

                case 'pong':
                    console.log('[WS] pong');
                    break;

                case 'error':
                    addMessage('Erro: ' + payload.message, 'system');
                    break;
            }
        };

        ws.onclose = () => {
            setConnected(false);
            onlineCount.textContent = 'desconectado';
            addMessage('Conexão encerrada. Reconectando em 3s…', 'system');
            setTimeout(connect, 3000);
        };

        ws.onerror = (err) => {
            console.error('[WS] erro', err);
        };
    }

    // ── Eventos ───────────────────────────────────────────────────────────────

    sendBtn.addEventListener('click', send);

    msgInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });

    // Heartbeat a cada 25s para manter a conexão viva
    setInterval(() => {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ event: 'ping' }));
        }
    }, 25_000);

    connect();
})();
</script>

</body>
</html>
