<?php
// chat.php - Halaman Pengiriman Komentar Khusus Penonton Mobile
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

// Daftar penerima jika ingin ganti
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
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background-color: #08090d;
            color: #ffffff;
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
            background: rgba(22, 27, 42, 0.85);
            backdrop-filter: blur(16px);
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-tag { display: flex; align-items: center; gap: 10px; }
        .user-badge {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #00f2fe, #4facfe);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #08090d;
            font-size: 15px;
            font-family: 'Outfit', sans-serif;
        }
        .user-details { font-size: 13px; }
        .user-name { font-weight: 700; font-size: 14.5px; }
        .user-target { color: #00f2fe; font-size: 11.5px; }
        .logout-link {
            color: rgba(255, 255, 255, 0.5);
            font-size: 11.5px;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
        }

        .center-card {
            background: rgba(22, 27, 42, 0.6);
            border: 1px dashed rgba(255, 255, 255, 0.15);
            border-radius: 22px;
            padding: 24px;
            text-align: center;
            margin: auto 0;
        }
        .center-card h3 { font-family: 'Outfit', sans-serif; font-size: 17px; margin-bottom: 6px; }
        .center-card p { font-size: 13px; color: rgba(255, 255, 255, 0.6); line-height: 1.45; }

        .sent-indicator {
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 13px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        .sent-indicator.success {
            color: #00f2fe;
            background: rgba(0, 242, 254, 0.12);
            border-radius: 12px;
            border: 1px solid rgba(0, 242, 254, 0.25);
            padding: 8px 14px;
        }
        .sent-indicator.error {
            color: #ff3366;
            background: rgba(255, 0, 85, 0.12);
            border-radius: 12px;
            border: 1px solid rgba(255, 0, 85, 0.25);
            padding: 8px 14px;
        }

        .compose-area {
            background: rgba(22, 27, 42, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 22px;
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.5);
        }
        .recipient-select-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            background: rgba(255, 255, 255, 0.05);
            padding: 6px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .recipient-select-wrap span { font-size: 12px; color: rgba(255, 255, 255, 0.6); white-space: nowrap; font-weight: 600; }
        .recipient-select-wrap select {
            background: transparent;
            border: none;
            color: #00f2fe;
            font-weight: 700;
            font-size: 13px;
            outline: none;
            width: 100%;
        }
        .recipient-select-wrap select option { background: #121622; color: #fff; }

        .color-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .color-label { font-size: 11.5px; color: rgba(255, 255, 255, 0.6); white-space: nowrap; }
        .color-pill {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.15s, border-color 0.15s;
            flex-shrink: 0;
        }
        .color-pill.active { transform: scale(1.2); border-color: #ffffff; }

        .input-row { display: flex; gap: 10px; }
        .input-row input {
            flex: 1;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 13px 16px;
            border-radius: 14px;
            color: white;
            font-size: 14.5px;
            outline: none;
        }
        .input-row input:focus { border-color: #ff0055; }
        .btn-send {
            background: linear-gradient(135deg, #ff0055, #ff3366);
            border: none;
            color: white;
            padding: 0 20px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(255, 0, 85, 0.35);
            transition: transform 0.1s;
        }
        .btn-send:active { transform: scale(0.96); }
        .btn-send:disabled { background: #444; cursor: not-allowed; box-shadow: none; }
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

    <div class="center-card">
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
                <div class="color-pill active" data-color="#ff0055" style="background: #ff0055;"></div>
                <div class="color-pill" data-color="#00f2fe" style="background: #00f2fe;"></div>
                <div class="color-pill" data-color="#ffd166" style="background: #ffd166;"></div>
                <div class="color-pill" data-color="#06d6a0" style="background: #06d6a0;"></div>
                <div class="color-pill" data-color="#e0aaff" style="background: #e0aaff;"></div>
            </div>

            <div class="input-row">
                <input type="text" id="messageInput" placeholder="Tulis komentar atau ucapan..." maxlength="200" autocomplete="off" required>
                <button type="submit" id="btnSend" class="btn-send">Kirim</button>
            </div>
        </form>
    </div>

    <script>
        const composeForm = document.getElementById('composeForm');
        const messageInput = document.getElementById('messageInput');
        const btnSend = document.getElementById('btnSend');
        const sentIndicator = document.getElementById('sentIndicator');
        const colorPills = document.querySelectorAll('.color-pill');
        const recipientSelect = document.getElementById('recipientSelect');
        const targetLabel = document.getElementById('targetLabel');

        let selectedColor = '#ff0055';

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
                    messageInput.value = '';
                } else {
                    sentIndicator.className = 'sent-indicator error';
                    sentIndicator.textContent = resData.message || 'Gagal mengirim pesan.';
                }
            } catch (err) {
                sentIndicator.className = 'sent-indicator error';
                sentIndicator.textContent = 'Gagal menghubungi server.';
            } finally {
                btnSend.disabled = false;
                btnSend.textContent = 'Kirim';
                setTimeout(() => {
                    if (sentIndicator.classList.contains('success')) {
                        sentIndicator.textContent = '';
                        sentIndicator.className = 'sent-indicator';
                    }
                }, 4000);
            }
        });
    </script>
</body>
</html>
