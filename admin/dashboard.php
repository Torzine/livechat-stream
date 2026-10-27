<?php
// admin/dashboard.php - Master Control Dashboard (Pengaturan Layar, SARA Filter, Ban User & Audit Log)
session_start();
require_once __DIR__ . '/../api/db.php';

if (empty($_SESSION['livechat_admin_logged'])) {
    header('Location: login.php');
    exit;
}

$notice = '';

// 1. Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 2. Handle Clear All Messages
if (isset($_POST['clear_messages'])) {
    $pdo->query("TRUNCATE TABLE messages");
    $notice = 'Semua riwayat chat di layar berhasil dibersihkan.';
}

// 3. Handle Add Attendee (Dengan Password Opsional)
if (isset($_POST['add_attendee'])) {
    $name = trim(strip_tags($_POST['attendee_name'] ?? ''));
    $target = trim(strip_tags($_POST['attendee_target'] ?? 'Semua'));
    $phone = trim(strip_tags($_POST['attendee_phone'] ?? ''));
    $pass = $_POST['attendee_password'] ?? '';

    if (!empty($name)) {
        $hashed = !empty($pass) ? password_hash($pass, PASSWORD_BCRYPT) : null;
        $stmt = $pdo->prepare("INSERT INTO attendees (name, default_recipient, code_or_phone, password_hash) VALUES (:name, :target, :phone, :pass)");
        $stmt->execute([':name' => $name, ':target' => $target, ':phone' => $phone, ':pass' => $hashed]);
        $notice = "Pengguna <strong>$name</strong> berhasil didaftarkan.";
    }
}

// 4. Handle Ban / Unban Attendee
if (isset($_GET['toggle_ban'])) {
    $attId = (int)$_GET['toggle_ban'];
    $stmt = $pdo->prepare("UPDATE attendees SET is_banned = IF(is_banned = 1, 0, 1) WHERE id = :id");
    $stmt->execute([':id' => $attId]);
    header('Location: dashboard.php');
    exit;
}

// 5. Handle Delete Attendee
if (isset($_GET['del_attendee'])) {
    $delId = (int)$_GET['del_attendee'];
    $stmt = $pdo->prepare("DELETE FROM attendees WHERE id = :id");
    $stmt->execute([':id' => $delId]);
    header('Location: dashboard.php');
    exit;
}

// 6. Handle Approve/Delete Flagged Message
if (isset($_GET['approve_msg'])) {
    $msgId = (int)$_GET['approve_msg'];
    $stmt = $pdo->prepare("UPDATE messages SET is_approved = 1 WHERE id = :id");
    $stmt->execute([':id' => $msgId]);
    header('Location: dashboard.php');
    exit;
}
if (isset($_GET['del_msg'])) {
    $msgId = (int)$_GET['del_msg'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = :id");
    $stmt->execute([':id' => $msgId]);
    header('Location: dashboard.php');
    exit;
}

// 7. Handle Save Settings
if (isset($_POST['save_settings'])) {
    $title        = trim(strip_tags($_POST['stream_title'] ?? ''));
    $screenLayout = $_POST['screen_layout'] ?? 'landscape';
    $authMode     = $_POST['auth_mode'] ?? 'registration_open';
    $requireRec   = isset($_POST['require_recipient']) ? '1' : '0';
    $recList      = trim(strip_tags($_POST['recipients_list'] ?? ''));
    $bannedWords  = trim(strip_tags($_POST['banned_words'] ?? ''));

    $stmt = $pdo->prepare("UPDATE settings SET setting_value = :v WHERE setting_key = :k");
    $stmt->execute([':k' => 'stream_title', ':v' => $title]);
    $stmt->execute([':k' => 'screen_layout', ':v' => $screenLayout]);
    $stmt->execute([':k' => 'auth_mode', ':v' => $authMode]);
    $stmt->execute([':k' => 'require_recipient', ':v' => $requireRec]);
    $stmt->execute([':k' => 'recipients_list', ':v' => $recList]);
    $stmt->execute([':k' => 'banned_words', ':v' => $bannedWords]);

    $notice = 'Pengaturan berhasil disimpan! Layar stream otomatis mengikuti orientasi baru.';
}

$settings = get_settings($pdo);

// Ambil 30 pesan terbaru untuk audit & pelacakan IP
$recentMessages = $pdo->query("SELECT * FROM messages ORDER BY id DESC LIMIT 30")->fetchAll();

// Ambil daftar attendees
$attendees = $pdo->query("SELECT * FROM attendees ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Live Control & Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090a10;
            --card-bg: rgba(18, 22, 34, 0.85);
            --card-border: rgba(255, 255, 255, 0.1);
            --accent: #00f2fe;
            --primary: #ff0055;
            --warning: #ffb703;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background-color: var(--bg);
            color: #ffffff;
            min-height: 100vh;
            padding: 24px 20px;
            background-image: radial-gradient(circle at 15% 15%, rgba(112, 0, 255, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 85% 85%, rgba(0, 242, 254, 0.1) 0%, transparent 50%);
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            padding: 16px 24px;
            border-radius: 20px;
            margin-bottom: 24px;
        }
        .logo-area { display: flex; align-items: center; gap: 14px; }
        .logo-icon {
            background: linear-gradient(135deg, var(--primary), #7000ff);
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
        }
        .logo-title { font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 800; }
        .nav-actions { display: flex; gap: 12px; align-items: center; }
        .btn-link {
            text-decoration: none;
            color: rgba(255, 255, 255, 0.75);
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.2s;
        }
        .btn-link:hover { color: #fff; background: rgba(255, 255, 255, 0.12); }
        .btn-logout { background: rgba(255, 0, 85, 0.15); color: #ff3366; border-color: rgba(255, 0, 85, 0.3); }

        .notice-banner {
            background: rgba(0, 242, 254, 0.15);
            border: 1px solid rgba(0, 242, 254, 0.3);
            color: #00f2fe;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13.5px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 22px;
            margin-bottom: 24px;
        }
        @media (max-width: 860px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        }
        .card-header {
            font-family: 'Outfit', sans-serif;
            font-size: 16.5px;
            font-weight: 800;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; font-weight: 700; color: rgba(255, 255, 255, 0.7); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 7px; }
        input[type="text"], input[type="password"], select, textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 11px 14px;
            border-radius: 12px;
            color: #fff;
            font-size: 13.5px;
            outline: none;
            transition: border-color 0.2s;
        }
        select option { background: #121622; color: #fff; }
        input:focus, select:focus, textarea:focus { border-color: var(--accent); }
        .help-text { font-size: 11.5px; color: rgba(255, 255, 255, 0.45); margin-top: 4px; }

        /* Highlight khusus untuk opsi layout */
        .layout-selector-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 6px;
        }
        .layout-opt {
            background: rgba(255, 255, 255, 0.04);
            border: 2px solid rgba(255, 255, 255, 0.12);
            padding: 14px;
            border-radius: 14px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .layout-opt.active {
            border-color: #00f2fe;
            background: rgba(0, 242, 254, 0.1);
            box-shadow: 0 0 20px rgba(0, 242, 254, 0.2);
        }
        .layout-opt input { display: none; }
        .layout-title { font-weight: 800; font-size: 13.5px; margin-bottom: 4px; color: #fff; }
        .layout-sub { font-size: 11.5px; color: rgba(255, 255, 255, 0.55); line-height: 1.3; }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #4facfe);
            border: none;
            color: #08090d;
            font-weight: 800;
            padding: 12px 22px;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(0, 242, 254, 0.3);
            transition: transform 0.1s;
        }
        .btn-primary:active { transform: scale(0.97); }

        .btn-danger {
            background: rgba(255, 0, 85, 0.15);
            border: 1px solid rgba(255, 0, 85, 0.35);
            color: #ff3366;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 12.5px;
        }

        .table-wrap { overflow-x: auto; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th { text-align: left; padding: 9px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.5); font-weight: 600; font-size: 11px; text-transform: uppercase; }
        td { padding: 10px 9px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }

        .badge-ip {
            font-family: monospace;
            background: rgba(255, 255, 255, 0.08);
            padding: 3px 6px;
            border-radius: 6px;
            font-size: 11px;
            color: #00f2fe;
        }
        .badge-flagged {
            background: rgba(255, 0, 85, 0.15);
            border: 1px solid rgba(255, 0, 85, 0.4);
            color: #ff3366;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
        }
        .badge-approved {
            background: rgba(0, 242, 254, 0.12);
            color: #00f2fe;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Navbar -->
        <div class="navbar">
            <div class="logo-area">
                <div class="logo-icon">LC</div>
                <div>
                    <div class="logo-title">Live Control Room</div>
                    <div style="font-size: 12px; color: rgba(255,255,255,0.5);">Admin: <?php echo htmlspecialchars($_SESSION['livechat_admin_user']); ?></div>
                </div>
            </div>
            <div class="nav-actions">
                <a href="../index.php" target="_blank" class="btn-link">Buka Layar Stream</a>
                <a href="dashboard.php?action=logout" class="btn-link btn-logout">Logout</a>
            </div>
        </div>

        <?php if (!empty($notice)): ?>
            <div class="notice-banner"><?php echo $notice; ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Kolom 1: Konfigurasi Layar & SARA -->
            <div class="card">
                <div class="card-header">
                    <span>Pengaturan Layar & Mode Keamanan</span>
                </div>
                <form method="POST" action="dashboard.php">
                    <div class="form-group">
                        <label>Judul Layar Siaran</label>
                        <input type="text" name="stream_title" value="<?php echo htmlspecialchars($settings['stream_title'] ?? ''); ?>" required>
                    </div>

                    <!-- KONTROL ORIENTASI KHUSUS DI PANEL ADMIN -->
                    <div class="form-group">
                        <label>Orientasi Tampilan Layar Siaran</label>
                        <div class="layout-selector-box">
                            <label class="layout-opt <?php echo (($settings['screen_layout'] ?? '') === 'landscape') ? 'active' : ''; ?>">
                                <input type="radio" name="screen_layout" value="landscape" <?php echo (($settings['screen_layout'] ?? '') === 'landscape') ? 'checked' : ''; ?> onchange="updateLayoutRadios()">
                                <div class="layout-title">16:9 Landscape</div>
                                <div class="layout-sub">Proyektor, TV Panggung, YouTube Live</div>
                            </label>
                            <label class="layout-opt <?php echo (($settings['screen_layout'] ?? '') === 'portrait') ? 'active' : ''; ?>">
                                <input type="radio" name="screen_layout" value="portrait" <?php echo (($settings['screen_layout'] ?? '') === 'portrait') ? 'checked' : ''; ?> onchange="updateLayoutRadios()">
                                <div class="layout-title">9:16 Portrait</div>
                                <div class="layout-sub">TikTok Live, Reels, Standing TV Banner</div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Mode Akses Pengguna</label>
                        <select name="auth_mode">
                            <option value="registration_open" <?php echo ($settings['auth_mode'] === 'registration_open') ? 'selected' : ''; ?>>
                                Mode 1: Registrasi Mandiri (Bisa Login & Buat Akun Baru)
                            </option>
                            <option value="registered_only" <?php echo ($settings['auth_mode'] === 'registered_only') ? 'selected' : ''; ?>>
                                Mode 2: Hanya Akun Terdaftar (Whitelist Panitia)
                            </option>
                            <option value="anonymous" <?php echo ($settings['auth_mode'] === 'anonymous') ? 'selected' : ''; ?>>
                                Mode 3: Bebas / Anonim
                            </option>
                        </select>
                    </div>

                    <!-- FILTER SARA DAN KATA KASAR -->
                    <div class="form-group">
                        <label>Sensor SARA & Kata Dilarang (Otomatis Dicekal)</label>
                        <textarea name="banned_words" rows="3"><?php echo htmlspecialchars($settings['banned_words'] ?? ''); ?></textarea>
                        <div class="help-text">Pesan yang mengandung kata di atas akan otomatis dicekal dan TIDAK tayang di layar live sebelum disetujui.</div>
                    </div>

                    <div class="form-group">
                        <label>Daftar Target Penerima (Pisahkan koma)</label>
                        <textarea name="recipients_list" rows="2"><?php echo htmlspecialchars($settings['recipients_list'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none;">
                            <input type="checkbox" name="require_recipient" value="1" <?php echo (($settings['require_recipient'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            <span>Wajibkan penonton memilih "Untuk Siapa"</span>
                        </label>
                    </div>

                    <button type="submit" name="save_settings" class="btn-primary">Simpan Pengaturan</button>
                </form>
            </div>

            <!-- Kolom 2: Manajemen Akun & Whitelist Password -->
            <div class="card">
                <div class="card-header">
                    <span>Kelola Pengguna & Akun Berpassword</span>
                </div>

                <form method="POST" action="dashboard.php" autocomplete="off" style="background: rgba(255,255,255,0.03); padding: 14px; border-radius: 14px; margin-bottom: 18px; border: 1px solid rgba(255,255,255,0.08);">
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label>Nama Pengguna</label>
                        <input type="text" name="attendee_name" placeholder="Misal: Budi Pratama" autocomplete="off" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label>Password Akun (Bisa Dikosongkan)</label>
                        <input type="password" name="attendee_password" placeholder="Password untuk login nanti" autocomplete="new-password">
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Target Default / Instansi</label>
                        <input type="text" name="attendee_target" placeholder="Misal: Wisudawan Ahmad" autocomplete="off">
                    </div>
                    <button type="submit" name="add_attendee" class="btn-primary" style="padding: 9px 16px; font-size: 13px;">+ Daftarkan Akun</button>
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>IP Terakhir</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendees)): ?>
                                <tr><td colspan="4" style="text-align: center; color: rgba(255,255,255,0.4); padding: 16px;">Belum ada akun terdaftar.</td></tr>
                            <?php else: ?>
                                <?php foreach ($attendees as $att): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($att['name']); ?></strong>
                                            <?php if (!empty($att['password_hash'])): ?>
                                                <span style="color: #00f2fe; font-size: 10.5px;">[Password ON]</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge-ip"><?php echo htmlspecialchars($att['ip_address'] ?: '-'); ?></span></td>
                                        <td>
                                            <?php if ($att['is_banned']): ?>
                                                <span style="color: #ff3366; font-weight: 700;">DIBLOKIR</span>
                                            <?php else: ?>
                                                <span style="color: #00e676;">Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="dashboard.php?toggle_ban=<?php echo $att['id']; ?>" style="color: <?php echo $att['is_banned'] ? '#00e676' : '#ffb703'; ?>; text-decoration: none; margin-right: 8px;">
                                                <?php echo $att['is_banned'] ? 'Buka Blokir' : 'Blokir'; ?>
                                            </a>
                                            <a href="dashboard.php?del_attendee=<?php echo $att['id']; ?>" onclick="return confirm('Hapus akun ini?')" style="color: #ff3366; text-decoration: none;">Hapus</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. PELACAKAN KOMENTAR & AUDIT LOG -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span>Pelacakan Komentar & Audit Log Keamanan</span>
                    <span style="font-size: 12px; color: rgba(255,255,255,0.5); font-weight: 400; margin-left: 10px;">(Perekaman IP Address, Kata SARA, & Moderasi)</span>
                </div>
                <form method="POST" action="dashboard.php" onsubmit="return confirm('Hapus semua pesan di layar?')">
                    <button type="submit" name="clear_messages" class="btn-danger">Bersihkan Seluruh Chat</button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengirim</th>
                            <th>Untuk</th>
                            <th>Pesan</th>
                            <th>IP Address & Browser</th>
                            <th>Status & Moderasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentMessages)): ?>
                            <tr><td colspan="6" style="text-align: center; color: rgba(255,255,255,0.4); padding: 24px;">Belum ada riwayat komentar.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentMessages as $msg): ?>
                                <tr>
                                    <td style="color: rgba(255,255,255,0.5); font-size: 11.5px;"><?php echo date('H:i:s', strtotime($msg['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($msg['username']); ?></strong></td>
                                    <td><span style="color: var(--accent);"><?php echo htmlspecialchars($msg['recipient'] ?: 'Semua'); ?></span></td>
                                    <td style="max-width: 250px; word-break: break-word;">
                                        <?php echo htmlspecialchars($msg['message']); ?>
                                        <?php if (!empty($msg['flag_reason'])): ?>
                                            <div style="color: #ff3366; font-size: 11px; margin-top: 3px;">
                                                ⚠️ <?php echo htmlspecialchars($msg['flag_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-ip"><?php echo htmlspecialchars($msg['ip_address'] ?: '-'); ?></span>
                                        <div style="font-size: 10.5px; color: rgba(255,255,255,0.4); max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($msg['user_agent'] ?: ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($msg['is_flagged'] && !$msg['is_approved']): ?>
                                            <span class="badge-flagged">Dicekal (SARA)</span>
                                            <a href="dashboard.php?approve_msg=<?php echo $msg['id']; ?>" style="color: #00f2fe; text-decoration: none; font-size: 11.5px; margin-left: 6px;">Izinkan</a>
                                        <?php else: ?>
                                            <span class="badge-approved">Tayang di Layar</span>
                                        <?php endif; ?>
                                        <a href="dashboard.php?del_msg=<?php echo $msg['id']; ?>" onclick="return confirm('Hapus pesan ini dari layar?')" style="color: #ff3366; text-decoration: none; font-size: 11.5px; margin-left: 8px;">Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function updateLayoutRadios() {
            const radios = document.querySelectorAll('input[name="screen_layout"]');
            radios.forEach(r => {
                const parent = r.closest('.layout-opt');
                if (r.checked) {
                    parent.classList.add('active');
                } else {
                    parent.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
