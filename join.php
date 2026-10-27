<?php
// join.php - Halaman dengan Tab Menu Terpisah: [LOGIN] & [DAFTAR]
session_start();
require_once __DIR__ . '/api/db.php';

$settings = get_settings($pdo);
$authMode = $settings['auth_mode'] ?? 'registration_open';
$requireRecipient = ($settings['require_recipient'] ?? '1') === '1';

// Parse list penerima
$recipients = [];
if (!empty($settings['recipients_list'])) {
    $raw = explode(',', $settings['recipients_list']);
    foreach ($raw as $r) {
        $trimmed = trim($r);
        if ($trimmed !== '') $recipients[] = $trimmed;
    }
}
if (empty($recipients)) $recipients = ['Semua / Umum'];

$error = '';
$success = '';
$activeTab = 'login'; // default tab

// Deteksi IP Client
$ipAddress = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
if (strpos($ipAddress, ',') !== false) {
    $ipAddress = trim(explode(',', $ipAddress)[0]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'login';
    $activeTab = $formType;

    if ($formType === 'login') {
        // ==========================================
        // PROSES LOGIN
        // ==========================================
        $name     = trim(strip_tags($_POST['login_name'] ?? ''));
        $password = $_POST['login_password'] ?? '';

        if (empty($name)) {
            $error = 'Silakan masukkan nama akun Anda.';
        } else {
            $stmt = $pdo->prepare("SELECT id, name, default_recipient, password_hash, is_banned, is_allowed FROM attendees WHERE name = :name LIMIT 1");
            $stmt->execute([':name' => $name]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Akun belum terdaftar. Silakan klik tab "Daftar Akun Baru".';
            } elseif ((int)$user['is_banned'] === 1 || (int)$user['is_allowed'] === 0) {
                $error = 'Akun Anda dinonaktifkan oleh administrator.';
            } else {
                // Jika user punya password
                if (!empty($user['password_hash'])) {
                    if (empty($password)) {
                        $error = 'Akun ini memiliki password. Harap masukkan password Anda.';
                    } elseif (!password_verify($password, $user['password_hash'])) {
                        $error = 'Password yang Anda masukkan salah!';
                    } else {
                        // Update IP terakhir
                        $upd = $pdo->prepare("UPDATE attendees SET ip_address = :ip WHERE id = :id");
                        $upd->execute([':ip' => $ipAddress, ':id' => $user['id']]);

                        $_SESSION['livechat_username']  = $user['name'];
                        $_SESSION['livechat_recipient'] = $user['default_recipient'] ?: 'Semua';
                        header('Location: chat.php');
                        exit;
                    }
                } else {
                    // Akun tanpa password (misal didaftarkan admin tanpa pass)
                    $_SESSION['livechat_username']  = $user['name'];
                    $_SESSION['livechat_recipient'] = $user['default_recipient'] ?: 'Semua';
                    header('Location: chat.php');
                    exit;
                }
            }
        }
    } elseif ($formType === 'register') {
        // ==========================================
        // PROSES PENDAFTARAN BARU
        // ==========================================
        $name      = trim(strip_tags($_POST['reg_name'] ?? ''));
        $password  = $_POST['reg_password'] ?? '';
        $recipient = trim(strip_tags($_POST['reg_recipient'] ?? 'Semua'));
        $phone     = trim(strip_tags($_POST['reg_phone'] ?? ''));

        if (empty($name)) {
            $error = 'Nama pendaftaran tidak boleh kosong.';
        } elseif (empty($password)) {
            $error = 'Harap buat password untuk akun baru Anda.';
        } elseif (mb_strlen($password) < 4) {
            $error = 'Password minimal 4 karakter.';
        } else {
            // Cek apakah nama sudah dipakai
            $check = $pdo->prepare("SELECT id FROM attendees WHERE name = :name LIMIT 1");
            $check->execute([':name' => $name]);
            if ($check->fetch()) {
                $error = 'Nama ini sudah terdaftar! Silakan login di tab "Masuk / Login" atau gunakan nama lain.';
            } elseif ($authMode === 'registered_only') {
                $error = 'Pendaftaran ditutup oleh admin (Hanya daftar undangan).';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO attendees (name, default_recipient, code_or_phone, password_hash, ip_address) 
                                      VALUES (:name, :target, :phone, :pass, :ip)");
                $ins->execute([
                    ':name'   => $name,
                    ':target' => $recipient,
                    ':phone'  => $phone,
                    ':pass'   => $hashed,
                    ':ip'     => $ipAddress
                ]);

                $_SESSION['livechat_username']  = $name;
                $_SESSION['livechat_recipient'] = $recipient;
                header('Location: chat.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Masuk / Daftar Live Stream</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background-color: #08090d;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background-image: radial-gradient(circle at 50% 20%, rgba(255, 0, 85, 0.18) 0%, transparent 60%);
        }
        .auth-card {
            background: rgba(18, 22, 34, 0.92);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 30px 24px;
            border-radius: 26px;
            width: 100%;
            max-width: 410px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7);
            text-align: center;
        }
        .avatar-placeholder {
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            background: linear-gradient(135deg, #ff0055, #ff3366);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(255, 0, 85, 0.4);
        }
        h2 { font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        p.desc { font-size: 13px; color: rgba(255, 255, 255, 0.65); margin-bottom: 20px; line-height: 1.4; }

        /* TAB NAVIGATION (LOGIN VS DAFTAR) */
        .tab-switcher {
            display: flex;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 4px;
            border-radius: 14px;
            margin-bottom: 22px;
            gap: 4px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px 14px;
            font-size: 13.5px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.6);
            background: transparent;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(255, 0, 85, 0.8), rgba(255, 51, 102, 0.8));
            box-shadow: 0 4px 15px rgba(255, 0, 85, 0.35);
        }

        .input-group { margin-bottom: 16px; text-align: left; }
        label { display: block; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: rgba(255, 255, 255, 0.7); margin-bottom: 7px; }
        input[type="text"], input[type="password"], select {
            width: 100%;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 12px 16px;
            border-radius: 14px;
            color: white;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        select option { background: #121622; color: #fff; }
        input:focus, select:focus {
            border-color: #00f2fe;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 15px rgba(0, 242, 254, 0.25);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #ff0055, #ff3366);
            border: none;
            color: white;
            padding: 13px;
            border-radius: 14px;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 22px rgba(255, 0, 85, 0.35);
            transition: transform 0.1s;
            margin-top: 6px;
        }
        .btn-submit:active { transform: scale(0.97); }

        .btn-submit-cyan {
            background: linear-gradient(135deg, #00f2fe, #4facfe);
            color: #08090d;
            box-shadow: 0 8px 22px rgba(0, 242, 254, 0.35);
        }

        .error-msg {
            background: rgba(255, 0, 85, 0.15);
            color: #ff3366;
            border: 1px solid rgba(255, 0, 85, 0.35);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: left;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="avatar-placeholder">LIVE</div>
        <h2>Portal Live Chat</h2>
        <p class="desc">Masuk dengan akunmu atau daftar untuk mengirim pesan ke layar live.</p>

        <!-- TAB NAVIGATION -->
        <div class="tab-switcher">
            <button type="button" class="tab-btn <?php echo ($activeTab === 'login') ? 'active' : ''; ?>" onclick="switchTab('login')">Masuk / Login</button>
            <button type="button" class="tab-btn <?php echo ($activeTab === 'register') ? 'active' : ''; ?>" onclick="switchTab('register')">Daftar Akun Baru</button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- FORM 1: LOGIN -->
        <div id="tab-login" class="tab-content <?php echo ($activeTab === 'login') ? 'active' : ''; ?>">
            <form method="POST" action="join.php" autocomplete="off">
                <input type="hidden" name="form_type" value="login">
                <div class="input-group">
                    <label for="login_name">Nama Pengguna</label>
                    <input type="text" id="login_name" name="login_name" placeholder="Nama akun Anda" autocomplete="off" required>
                </div>
                <div class="input-group">
                    <label for="login_password">Password</label>
                    <input type="password" id="login_password" name="login_password" placeholder="Password akun" autocomplete="new-password">
                </div>
                <button type="submit" class="btn-submit btn-submit-cyan">Masuk ke Ruang Chat</button>
            </form>
        </div>

        <!-- FORM 2: REGISTER -->
        <div id="tab-register" class="tab-content <?php echo ($activeTab === 'register') ? 'active' : ''; ?>">
            <form method="POST" action="join.php" autocomplete="off">
                <input type="hidden" name="form_type" value="register">
                <div class="input-group">
                    <label for="reg_name">Nama Panggilan / Lengkap</label>
                    <input type="text" id="reg_name" name="reg_name" placeholder="Buat nama tampilan Anda" maxlength="50" autocomplete="off" required>
                </div>
                <div class="input-group">
                    <label for="reg_password">Buat Password Baru</label>
                    <input type="password" id="reg_password" name="reg_password" placeholder="Minimal 4 karakter" autocomplete="new-password" required>
                </div>

                <?php if ($requireRecipient): ?>
                    <div class="input-group">
                        <label for="reg_recipient">Untuk Siapa (Target Ucapan / Pesan)</label>
                        <select id="reg_recipient" name="reg_recipient">
                            <?php foreach ($recipients as $rec): ?>
                                <option value="<?php echo htmlspecialchars($rec); ?>"><?php echo htmlspecialchars($rec); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="input-group">
                    <label for="reg_phone">No HP / Instansi (Opsional)</label>
                    <input type="text" id="reg_phone" name="reg_phone" placeholder="Contoh: Univ / Jurusan / No WA" autocomplete="off">
                </div>

                <button type="submit" class="btn-submit">Daftar & Langsung Masuk</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            if (tab === 'login') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                document.getElementById('tab-login').classList.add('active');
            } else {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                document.getElementById('tab-register').classList.add('active');
            }
        }
    </script>
</body>
</html>
