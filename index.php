<?php
// index.php - Main Live Stream Display Screen (Crafted Live Stage View)
require_once __DIR__ . '/api/db.php';
$settings = get_settings($pdo);
$streamTitle  = htmlspecialchars($settings['stream_title'] ?? 'UNSA Interactive Stream');
$screenLayout = $settings['screen_layout'] ?? 'landscape';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $streamTitle; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root {
            --bg-deep: #0b0e14;
            --surface-card: #141923;
            --surface-border: #232b3b;
            --brand-primary: #e63946;
            --brand-accent: #00b4d8;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-deep);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-main);
            overflow: hidden;
        }

        /* 1. LAYOUT CONTROLLER (Landscape vs Portrait) */
        .stage-wrapper.layout-landscape {
            position: relative;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at 15% 20%, #161f30 0%, #0b0e14 70%);
            overflow: hidden;
            display: flex;
        }

        .stage-wrapper.layout-portrait {
            position: relative;
            width: 420px;
            height: 840px;
            max-height: 96vh;
            border-radius: 28px;
            background: radial-gradient(circle at 50% 15%, #182338 0%, #0b0e14 75%);
            overflow: hidden;
            border: 1px solid var(--surface-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
            display: flex;
        }

        /* 2. TOP BRANDING */
        .top-bar {
            position: absolute;
            top: 28px;
            left: 32px;
            display: flex;
            align-items: center;
            gap: 14px;
            z-index: 20;
        }

        .live-badge {
            background: var(--brand-primary);
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 11px;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .live-dot {
            width: 7px;
            height: 7px;
            background: #ffffff;
            border-radius: 50%;
        }

        .channel-title {
            font-family: 'Outfit', sans-serif;
            font-size: 14.5px;
            font-weight: 700;
            color: var(--text-main);
            background: rgba(20, 25, 35, 0.85);
            padding: 7px 18px;
            border-radius: 10px;
            border: 1px solid var(--surface-border);
        }

        .status-pill {
            position: absolute;
            top: 28px;
            right: 32px;
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12px;
            color: var(--text-muted);
            background: rgba(20, 25, 35, 0.85);
            padding: 7px 16px;
            border-radius: 10px;
            border: 1px solid var(--surface-border);
            z-index: 20;
        }

        .status-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }

        /* 3. CHAT OVERLAY CONTAINER */
        .layout-landscape .chat-overlay {
            position: absolute;
            bottom: 36px;
            left: 32px;
            width: 520px;
            max-height: 600px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            gap: 12px;
            overflow: visible;
            z-index: 15;
            -webkit-mask-image: linear-gradient(to top, black 82%, transparent 100%);
            mask-image: linear-gradient(to top, black 82%, transparent 100%);
            pointer-events: none;
        }

        .layout-portrait .chat-overlay {
            position: absolute;
            bottom: 24px;
            left: 16px;
            width: 340px;
            max-height: 480px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            gap: 10px;
            overflow: visible;
            z-index: 15;
            -webkit-mask-image: linear-gradient(to top, black 80%, transparent 100%);
            mask-image: linear-gradient(to top, black 80%, transparent 100%);
            pointer-events: none;
        }

        /* 4. SOLID CRAFTED BUBBLE CHAT */
        .chat-bubble {
            position: relative;
            background: var(--surface-card);
            color: var(--text-main);
            padding: 12px 18px 12px 14px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.45;
            width: fit-content;
            max-width: 96%;
            word-wrap: break-word;
            border: 1px solid var(--surface-border);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transform-origin: left bottom;
            will-change: transform, opacity;
            animation: bubblePopEffect 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            transition: transform 0.4s ease, opacity 0.4s ease;
        }

        @keyframes bubblePopEffect {
            0% {
                opacity: 0;
                transform: translate3d(0, 35px, 0) scale(0.2) scaleY(0.7);
            }
            50% {
                opacity: 1;
                transform: translate3d(0, -6px, 0) scale(1.08) scaleX(1.04) scaleY(0.96);
            }
            75% {
                transform: translate3d(0, 2px, 0) scale(0.98);
            }
            100% {
                opacity: 1;
                transform: translate3d(0, 0, 0) scale(1);
            }
        }

        /* Efek Terdesak di Bagian Atas */
        .chat-bubble.bubble-squeezed {
            opacity: 0.6;
            transform: translate3d(0, -4px, 0) scale(0.94) scaleY(0.88);
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.3, 1);
        }

        .chat-bubble.bubble-exit {
            opacity: 0 !important;
            transform: translate3d(0, -32px, 0) scale(0.7) scaleY(0.55) !important;
            margin-bottom: -40px !important;
            pointer-events: none !important;
            transition: transform 0.45s ease, opacity 0.4s ease, margin-bottom 0.45s ease !important;
        }

        .bubble-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            color: #0b0e14;
            flex-shrink: 0;
            text-transform: uppercase;
            font-family: 'Outfit', sans-serif;
        }

        .bubble-content { flex: 1; }

        .bubble-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 3px;
            flex-wrap: wrap;
        }

        .user-name-label {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 13.5px;
        }

        .recipient-badge {
            background: rgba(0, 180, 216, 0.12);
            color: var(--brand-accent);
            border: 1px solid rgba(0, 180, 216, 0.3);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .message-body {
            color: #e2e8f0;
            font-size: 13.5px;
            line-height: 1.45;
        }

        /* 5. QR CODE CARD */
        .layout-landscape .qr-card-container {
            position: absolute;
            bottom: 36px;
            right: 32px;
            z-index: 25;
        }

        .layout-portrait .qr-card-container {
            position: absolute;
            top: 75px;
            right: 16px;
            z-index: 25;
        }

        .qr-card {
            background: var(--surface-card);
            border: 1px solid var(--surface-border);
            padding: 16px;
            border-radius: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5);
            text-align: center;
            width: 180px;
        }

        .layout-portrait .qr-card {
            width: 135px;
            padding: 10px;
            border-radius: 14px;
        }

        .qr-badge {
            font-family: 'Outfit', sans-serif;
            font-size: 10.5px;
            font-weight: 700;
            color: var(--brand-accent);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .qr-frame {
            background: #ffffff;
            padding: 6px;
            border-radius: 10px;
        }

        .qr-info { font-size: 11px; color: var(--text-muted); line-height: 1.35; }
        .layout-portrait .qr-info { display: none; }
        .qr-link { font-size: 10.5px; color: var(--brand-primary); font-weight: 600; text-decoration: none; word-break: break-all; }
    </style>
</head>

<body>

    <div class="stage-wrapper layout-<?php echo $screenLayout; ?>">
        <!-- Top Navigation -->
        <div class="top-bar">
            <div class="live-badge">
                <div class="live-dot"></div> LIVE
            </div>
            <div class="channel-title"><?php echo $streamTitle; ?></div>
        </div>

        <div class="status-pill">
            <div class="status-pulse" id="statusPulse"></div>
            <span id="statusText">Terhubung</span>
        </div>

        <!-- Chat Bubble Overlay -->
        <div class="chat-overlay" id="chatContainer">
            <div class="chat-bubble">
                <div class="bubble-avatar" style="background: var(--brand-accent);">SYS</div>
                <div class="bubble-content">
                    <div class="bubble-header">
                        <span class="user-name-label" style="color: var(--brand-accent);">Panitia</span>
                        <span class="recipient-badge">Semua</span>
                    </div>
                    <div class="message-body">Scan QR Code untuk login dan mengirimkan komentar interaktif.</div>
                </div>
            </div>
        </div>

        <!-- QR Card -->
        <div class="qr-card-container">
            <div class="qr-card">
                <div class="qr-badge">Scan & Chat</div>
                <div class="qr-frame">
                    <div id="qrcode"></div>
                </div>
                <div class="qr-info">Scan kamera HP untuk login dan bergabung</div>
                <a class="qr-link" id="joinUrlText" href="#" target="_blank"></a>
            </div>
        </div>
    </div>

    <script>
        // 1. Dynamic QR Code
        const qrcodeEl = document.getElementById('qrcode');
        const joinUrlText = document.getElementById('joinUrlText');
        const currentOrigin = window.location.origin;
        const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        const joinUrl = `${currentOrigin}${currentPath}/join.php`;

        const isPortrait = "<?php echo $screenLayout; ?>" === 'portrait';
        const qrSize = isPortrait ? 95 : 120;

        new QRCode(qrcodeEl, {
            text: joinUrl,
            width: qrSize,
            height: qrSize,
            colorDark: "#0b0e14",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });

        joinUrlText.textContent = joinUrl.replace(/^https?:\/\//, '');
        joinUrlText.href = joinUrl;

        // 2. Render Bubble dengan Antrean Halus & Efek Terdesak
        const chatContainer = document.getElementById('chatContainer');
        const statusPulse = document.getElementById('statusPulse');
        const statusText = document.getElementById('statusText');
        const MAX_BUBBLES = isPortrait ? 6 : 8;

        const messageQueue = [];
        let isProcessingQueue = false;

        function enqueueMessage(msg) {
            messageQueue.push(msg);
            if (!isProcessingQueue) {
                processMessageQueue();
            }
        }

        async function processMessageQueue() {
            if (messageQueue.length === 0) {
                isProcessingQueue = false;
                return;
            }
            isProcessingQueue = true;
            const item = messageQueue.shift();
            addBubbleChatDom(item.username, item.recipient, item.message, item.color);

            setTimeout(processMessageQueue, 240);
        }

        function addBubbleChatDom(username, recipient, message, color = '#e63946') {
            const bubble = document.createElement('div');
            bubble.classList.add('chat-bubble');

            const initial = username.trim().charAt(0).toUpperCase() || '?';
            const avatar = document.createElement('div');
            avatar.classList.add('bubble-avatar');
            avatar.style.background = color;
            avatar.textContent = initial;

            const content = document.createElement('div');
            content.classList.add('bubble-content');

            const header = document.createElement('div');
            header.classList.add('bubble-header');

            const userSpan = document.createElement('span');
            userSpan.classList.add('user-name-label');
            userSpan.style.color = color;
            userSpan.textContent = username;
            header.appendChild(userSpan);

            if (recipient && recipient !== 'Semua' && recipient !== 'Umum') {
                const recBadge = document.createElement('span');
                recBadge.classList.add('recipient-badge');
                recBadge.textContent = '➔ ' + recipient;
                header.appendChild(recBadge);
            }

            const messageBody = document.createElement('div');
            messageBody.classList.add('message-body');
            messageBody.textContent = message;

            content.appendChild(header);
            content.appendChild(messageBody);

            bubble.appendChild(avatar);
            bubble.appendChild(content);

            chatContainer.appendChild(bubble);

            // Efek terdesak pada 2 bubble teratas saat mendekati batas
            const currentCount = chatContainer.children.length;
            if (currentCount >= MAX_BUBBLES) {
                const firstChild = chatContainer.children[0];
                const secondChild = chatContainer.children[1];
                if (firstChild) firstChild.classList.add('bubble-squeezed');
                if (secondChild) secondChild.classList.add('bubble-squeezed');
            }

            if (currentCount > MAX_BUBBLES) {
                const oldest = chatContainer.firstElementChild;
                oldest.classList.remove('bubble-squeezed');
                oldest.classList.add('bubble-exit');
                setTimeout(() => {
                    if (oldest.parentNode === chatContainer) {
                        chatContainer.removeChild(oldest);
                    }
                }, 480);
            }
        }

        // 3. Smart Delta Polling
        let lastMessageId = 0;
        let isFetching = false;

        async function fetchMessages() {
            if (isFetching) return;
            isFetching = true;

            try {
                const response = await fetch(`api/get_messages.php?last_id=${lastMessageId}&limit=12`);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const data = await response.json();
                if (data.status === 'success' && data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        const idNum = parseInt(msg.id, 10);
                        if (idNum > lastMessageId) {
                            lastMessageId = idNum;
                        }
                        enqueueMessage(msg);
                    });
                }
                statusPulse.style.background = '#10b981';
                statusText.textContent = 'Terhubung';
            } catch (err) {
                statusPulse.style.background = '#e63946';
                statusText.textContent = 'Menghubungkan';
            } finally {
                isFetching = false;
            }
        }

        fetchMessages();
        setInterval(fetchMessages, 1500);
    </script>
</body>

</html>
