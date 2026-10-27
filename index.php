<?php
// index.php - Main Live Stream Display Screen (Awwwards-Grade Interactive Three.js + GSAP Stage)
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
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- QRCode CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- Three.js (WebGL 3D Engine) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <!-- GSAP (GreenSock Animation Platform) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>

    <style>
        :root {
            --bg-deep: #080b10;
            --surface-card: #131822;
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
            background: #080b10;
            overflow: hidden;
            display: flex;
        }

        .stage-wrapper.layout-portrait {
            position: relative;
            width: 420px;
            height: 840px;
            max-height: 96vh;
            border-radius: 28px;
            background: #080b10;
            overflow: hidden;
            border: 1px solid var(--surface-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
            display: flex;
        }

        /* 3D WebGL Canvas Layer */
        #webglCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
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
            background: rgba(19, 24, 34, 0.85);
            backdrop-filter: blur(12px);
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
            background: rgba(19, 24, 34, 0.85);
            backdrop-filter: blur(12px);
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
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transform-origin: left bottom;
            will-change: transform, opacity;
            pointer-events: auto;
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

        /* 5. QR CODE CARD (Interactive Tilt Ready) */
        .layout-landscape .qr-card-container {
            position: absolute;
            bottom: 36px;
            right: 32px;
            z-index: 25;
            perspective: 800px;
        }

        .layout-portrait .qr-card-container {
            position: absolute;
            top: 75px;
            right: 16px;
            z-index: 25;
            perspective: 800px;
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
            transform-style: preserve-3d;
            transition: border-color 0.3s ease;
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

    <div class="stage-wrapper layout-<?php echo $screenLayout; ?>" id="stageWrapper">
        <!-- Three.js Interactive 3D Canvas -->
        <canvas id="webglCanvas"></canvas>

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

        <!-- QR Card with Interactive Physics -->
        <div class="qr-card-container">
            <div class="qr-card" id="qrCard">
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
        // ==============================================================
        // 1. THREE.JS INTERACTIVE 3D WEBGL AMBIENT MESH (Awwwards Style)
        // ==============================================================
        const stageWrapper = document.getElementById('stageWrapper');
        const canvas3D = document.getElementById('webglCanvas');
        let renderer, scene, camera, pointsGeometry, particlesMesh, torusWire;
        let mouseX = 0, mouseY = 0;
        let targetX = 0, targetY = 0;

        function initThreeJS() {
            const width = stageWrapper.clientWidth;
            const height = stageWrapper.clientHeight;

            scene = new THREE.Scene();
            camera = new THREE.PerspectiveCamera(60, width / height, 0.1, 1000);
            camera.position.z = 8;

            renderer = new THREE.WebGLRenderer({ canvas: canvas3D, alpha: true, antialias: true });
            renderer.setSize(width, height);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

            // A. Partikel 3D Luar Angkasa Halus
            const particleCount = 220;
            const posArray = new Float32Array(particleCount * 3);
            for (let i = 0; i < particleCount * 3; i++) {
                posArray[i] = (Math.random() - 0.5) * 22;
            }

            pointsGeometry = new THREE.BufferGeometry();
            pointsGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));

            const pointsMaterial = new THREE.PointsMaterial({
                size: 0.045,
                color: 0x00b4d8,
                transparent: true,
                opacity: 0.45
            });

            particlesMesh = new THREE.Points(pointsGeometry, pointsMaterial);
            scene.add(particlesMesh);

            // B. 3D Wireframe Torus Ring (Floating Stage Motif)
            const torusGeo = new THREE.TorusGeometry(3.2, 0.9, 12, 36);
            const torusMat = new THREE.MeshBasicMaterial({
                color: 0x1d273b,
                wireframe: true,
                transparent: true,
                opacity: 0.22
            });
            torusWire = new THREE.Mesh(torusGeo, torusMat);
            torusWire.position.set(4, 1, -2);
            scene.add(torusWire);

            // Resize listener
            window.addEventListener('resize', onResize);
            window.addEventListener('mousemove', (e) => {
                mouseX = (e.clientX / window.innerWidth) - 0.5;
                mouseY = (e.clientY / window.innerHeight) - 0.5;
            });

            animate3D();
        }

        function onResize() {
            const w = stageWrapper.clientWidth;
            const h = stageWrapper.clientHeight;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        }

        function animate3D() {
            requestAnimationFrame(animate3D);

            // Lerp mouse follow
            targetX += (mouseX - targetX) * 0.05;
            targetY += (mouseY - targetY) * 0.05;

            // Slow ambient rotation
            particlesMesh.rotation.y += 0.0008;
            particlesMesh.rotation.x = -targetY * 0.3;
            particlesMesh.rotation.y += targetX * 0.3;

            torusWire.rotation.x += 0.0015;
            torusWire.rotation.y += 0.002;
            torusWire.position.x = 4 + (targetX * 0.8);
            torusWire.position.y = 1 - (targetY * 0.8);

            renderer.render(scene, camera);
        }

        initThreeJS();

        // ==============================================================
        // 2. GSAP INTERACTIVE 3D TILT PADA QR CARD
        // ==============================================================
        const qrCard = document.getElementById('qrCard');
        if (qrCard) {
            window.addEventListener('mousemove', (e) => {
                const rect = qrCard.getBoundingClientRect();
                const cardX = rect.left + rect.width / 2;
                const cardY = rect.top + rect.height / 2;
                const angleX = (e.clientY - cardY) / 35;
                const angleY = (cardX - e.clientX) / 35;

                gsap.to(qrCard, {
                    rotationX: angleX,
                    rotationY: angleY,
                    ease: "power2.out",
                    duration: 0.6,
                    transformPerspective: 800
                });
            });

            qrCard.addEventListener('mouseleave', () => {
                gsap.to(qrCard, {
                    rotationX: 0,
                    rotationY: 0,
                    ease: "power3.out",
                    duration: 0.8
                });
            });
        }

        // ==============================================================
        // 3. DYNAMIC QR CODE
        // ==============================================================
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
            colorDark: "#080b10",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });

        joinUrlText.textContent = joinUrl.replace(/^https?:\/\//, '');
        joinUrlText.href = joinUrl;

        // ==============================================================
        // 4. GSAP SPRING POP BUBBLE CHAT & SQUEEZE PHYSICS
        // ==============================================================
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

            // GSAP Elastic Spring Pop-in (Awwwards Style Physics)
            gsap.fromTo(bubble, 
                { y: 35, scale: 0.3, opacity: 0, scaleY: 0.6 },
                { 
                    y: 0, 
                    scale: 1, 
                    scaleY: 1, 
                    opacity: 1, 
                    duration: 0.65, 
                    ease: "elastic.out(1, 0.55)" 
                }
            );

            // Efek terdesak di bagian atas menggunakan GSAP
            const currentCount = chatContainer.children.length;
            if (currentCount >= MAX_BUBBLES) {
                const firstChild = chatContainer.children[0];
                const secondChild = chatContainer.children[1];
                if (firstChild) {
                    gsap.to(firstChild, { scale: 0.93, scaleY: 0.85, opacity: 0.6, y: -4, duration: 0.4, ease: "power2.out" });
                }
                if (secondChild) {
                    gsap.to(secondChild, { scale: 0.96, scaleY: 0.9, opacity: 0.8, y: -2, duration: 0.4, ease: "power2.out" });
                }
            }

            // Exit animation untuk bubble tertua
            if (currentCount > MAX_BUBBLES) {
                const oldest = chatContainer.firstElementChild;
                gsap.to(oldest, {
                    y: -30,
                    scale: 0.6,
                    scaleY: 0.4,
                    opacity: 0,
                    marginBottom: -42,
                    duration: 0.45,
                    ease: "power3.in",
                    onComplete: () => {
                        if (oldest.parentNode === chatContainer) {
                            chatContainer.removeChild(oldest);
                        }
                    }
                });
            }
        }

        // ==============================================================
        // 5. SMART DELTA POLLING
        // ==============================================================
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
