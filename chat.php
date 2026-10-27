<?php
// chat.php - Halaman Pengiriman Komentar Khusus Penonton Mobile (GSAP Enhanced)
session_start();
require_once __DIR__ . '/api/db.php';

if (empty($_SESSION['livechat_username'])) {
    header('Location: join.php');
    exit;
}

$currentUser = htmlspecialchars($_SESSION['livechat_username']);
$currentRecipient = htmlspecialchars($_SESSION['livechat_recipient'] ?? 'Semua');

$settings = get_settings($pdo);
$requireRecipient = ($settings['require_recipient'] ?? '1') === '1';

$recipients = [];
if (!empty($settings['recipients_list'])) {
    $raw = explode(',', $settings['recipients_list']);
    foreach ($raw as $r) {
        $trimmed = trim($r);
        if ($trimmed !== '') $recipients[] = $trimmed;
    }
}
if (empty($recipients)) $recipients = ['Semua'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kirim Komentar - Live Stream</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
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

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background-color: var(--bg-deep);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 100vh;
            padding: 16px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--surface-card);
            border-radius: 16px;
            border: 1px solid var(--surface-border);
        }
        .user-tag { display: flex; align-items: center; gap: 10px; }
        .user-badge {
            width: 36px;
            height: 36px;
            background: var(--brand-accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #080b10;
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
        }
        .user-details { font-size: 13px; }
        .user-name { font-weight: 700; font-size: 14px; }
        .user-target { color: var(--brand-accent); font-size: 11px; }
        .logout-link {
            color: var(--text-muted);
            font-size: 11.5px;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--surface-border);
        }

        .center-card {
            background: var(--surface-card);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            padding: 24px;
            text-align: center;
            margin: auto 0;
            opacity: 0;
            transform: scale(0.96);
        }
        .center-card h3 { font-family: 'Outfit', sans-serif; font-size: 17px; margin-bottom: 6px; }
        .center-card p { font-size: 13px; color: var(--text-muted); line-height: 1.45; }

        .sent-indicator {
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 12.5px;
            margin-bottom: 10px;
        }
        .sent-indicator.success {
            color: #10b981;
            background: rgba(16, 185, 129, 0.12);
            border-radius: 10px;
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 8px 14px;
        }
        .sent-indicator.error {
            color: #ff6b77;
            background: rgba(230, 57, 70, 0.12);
            border-radius: 10px;
            border: 1px solid rgba(230, 57, 70, 0.25);
            padding: 8px 14px;
        }

        .compose-area {
            background: var(--surface-card);
            border-radius: 18px;
            padding: 14px;
            border: 1px solid var(--surface-border);
            box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.4);
        }
        .recipient-select-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            background: rgba(255, 255, 255, 0.04);
            padding: 6px 12px;
            border-radius: 10px;
            border: 1px solid var(--surface-border);
        }
        .recipient-select-wrap span { font-size: 11.5px; color: var(--text-muted); white-space: nowrap; font-weight: 600; }
        .recipient-select-wrap select {
            background: transparent;
            border: none;
            color: var(--brand-accent);
            font-weight: 700;
            font-size: 13px;
            outline: none;
            width: 100%;
        }
        .recipient-select-wrap select option { background: #131822; color: #fff; }

        .color-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .color-label { font-size: 11px; color: var(--text-muted); white-space: nowrap; }
        .color-pill {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.15s, border-color 0.15s;
            flex-shrink: 0;
        }
        .color-pill.active { transform: scale(1.2); border-color: #ffffff; }

        .input-row { display: flex; gap: 8px; }
        .input-row input {
            flex: 1;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--surface-border);
            padding: 12px 14px;
            border-radius: 10px;
            color: white;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-row input:focus { border-color: var(--brand-primary); }
        .btn-send {
            background: var(--brand-primary);
            border: none;
            color: white;
            padding: 0 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: transform 0.1s;
        }
        .btn-send:active { transform: scale(0.96); }
        .btn-send:disabled { background: #262f40; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="header">
        <div class="user-tag">
            <div class="user-badge"><?php echo strtoupper(mb_substr($currentUser, 0, 1)); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo $currentUser; ?></div>
                <div class="user-target" id="targetLabel">➔ Untuk: <?php echo $currentRecipient; ?></div>
            </div>
        </div>
        <a href="join.php" class="logout-link">Ganti Akun</a>
    </div>

    <div class="center-card" id="centerCard">
        <h3>Siap Tayang di Layar Live</h3>
        <p>Pesan dan sasaran penerima Anda akan tampil langsung pada bubble chat layar siaran.</p>
    </div>

    <div>
        <div class="sent-indicator" id="sentIndicator"></div>

        <form class="compose-area" id="composeForm">
            <?php if ($requireRecipient): ?>
                <div class="recipient-select-wrap">
                    <span>Ditujukan Ke:</span>
                    <select id="recipientSelect">
                        <?php foreach ($recipients as $rec): ?>
                            <option value="<?php echo htmlspecialchars($rec); ?>" <?php echo ($rec === $currentRecipient) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rec); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="color-selector">
                <span class="color-label">Warna Aksen:</span>
                <div class="color-pill active" data-color="#e63946" style="background: #e63946;"></div>
                <div class="color-pill" data-color="#00b4d8" style="background: #00b4d8;"></div>
                <div class="color-pill" data-color="#10b981" style="background: #10b981;"></div>
                <div class="color-pill" data-color="#f59e0b" style="background: #f59e0b;"></div>
                <div class="color-pill" data-color="#8b5cf6" style="background: #8b5cf6;"></div>
            </div>

            <div class="input-row">
                <input type="text" id="messageInput" placeholder="Tulis komentar atau ucapan..." maxlength="200" autocomplete="off" required>
                <button type="submit" id="btnSend" class="btn-send">Kirim</button>
            </div>
        </form>
    </div>

    <script>
        gsap.to("#centerCard", { opacity: 1, scale: 1, duration: 0.6, ease: "power2.out" });

        const composeForm = document.getElementById('composeForm');
        const messageInput = document.getElementById('messageInput');
        const btnSend = document.getElementById('btnSend');
        const sentIndicator = document.getElementById('sentIndicator');
        const colorPills = document.querySelectorAll('.color-pill');
        const recipientSelect = document.getElementById('recipientSelect');
        const targetLabel = document.getElementById('targetLabel');

        let selectedColor = '#e63946';

        if (recipientSelect) {
            recipientSelect.addEventListener('change', () => {
                targetLabel.textContent = `➔ Untuk: ${recipientSelect.value}`;
            });
        }

        colorPills.forEach(pill => {
            pill.addEventListener('click', () => {
                colorPills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                selectedColor = pill.getAttribute('data-color');
            });
        });

        composeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;

            const recipient = recipientSelect ? recipientSelect.value : 'Semua';

            btnSend.disabled = true;
            btnSend.textContent = '...';

            try {
                const response = await fetch('api/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: "<?php echo addslashes($currentUser); ?>",
                        recipient: recipient,
                        message: message,
                        color: selectedColor
                    })
                });

                const resData = await response.json();

                if (response.ok && resData.status === 'success') {
                    sentIndicator.className = 'sent-indicator success';
                    sentIndicator.textContent = `Terkirim ke Layar (Untuk ${recipient}): "${message}"`;
                    gsap.fromTo(sentIndicator, { opacity: 0, y: 5 }, { opacity: 1, y: 0, duration: 0.3 });
                    messageInput.value = '';
                } else {
                    sentIndicator.className = 'sent-indicator error';
                    sentIndicator.textContent = resData.message || 'Gagal mengirim pesan.';
                    gsap.fromTo(sentIndicator, { opacity: 0, y: 5 }, { opacity: 1, y: 0, duration: 0.3 });
                }
            } catch (err) {
                sentIndicator.className = 'sent-indicator error';
                sentIndicator.textContent = 'Gagal menghubungi server.';
            } finally {
                btnSend.disabled = false;
                btnSend.textContent = 'Kirim';
                setTimeout(() => {
                    if (sentIndicator.classList.contains('success')) {
                        gsap.to(sentIndicator, { opacity: 0, duration: 0.4, onComplete: () => {
                            sentIndicator.textContent = '';
                            sentIndicator.className = 'sent-indicator';
                        }});
                    }
                }, 4000);
            }
        });
    </script>
</body>
</html>
