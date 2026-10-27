<?php
// join.php - Halaman Masuk & Daftar (GSAP Smooth Interactions)
session_start();
require_once __DIR__ . '/api/db.php';

$settings = get_settings($pdo);
$authMode = $settings['auth_mode'] ?? 'registration_open';
$requireRecipient = ($settings['require_recipient'] ?? '1') === '1';

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
$activeTab = 'login';

$ipAddress = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
if (strpos($ipAddress, ',') !== false) {
    $ipAddress = trim(explode(',', $ipAddress)[0]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'login';
    $activeTab = $formType;

    if ($formType === 'login') {
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
                if (!empty($user['password_hash'])) {
                    if (empty($password)) {
                        $error = 'Akun ini memiliki password. Harap masukkan password Anda.';
                    } elseif (!password_verify($password, $user['password_hash'])) {
                        $error = 'Password yang Anda masukkan salah!';
                    } else {
                        $upd = $pdo->prepare("UPDATE attendees SET ip_address = :ip WHERE id = :id");
                        $upd->execute([':ip' => $ipAddress, ':id' => $user['id']]);

                        $_SESSION['livechat_username']  = $user['name'];
                        $_SESSION['livechat_recipient'] = $user['default_recipient'] ?: 'Semua';
                        header('Location: chat.php');
                        exit;
                    }
                } else {
                    $_SESSION['livechat_username']  = $user['name'];
                    $_SESSION['livechat_recipient'] = $user['default_recipient'] ?: 'Semua';
                    header('Location: chat.php');
                    exit;
                }
            }
        }
    } elseif ($formType === 'register') {
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
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .auth-card {
            background: var(--surface-card);
            border: 1px solid var(--surface-border);
            padding: 32px 26px;
            border-radius: 22px;
            width: 100%;
            max-width: 410px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            text-align: center;
            opacity: 0;
            transform: translateY(20px);
        }
        .avatar-placeholder {
            width: 60px;
            height: 60px;
            margin: 0 auto 14px;
            background: var(--brand-primary);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
        }
        h2 { font-family: 'Outfit', sans-serif; font-size: 21px; font-weight: 800; margin-bottom: 4px; }
        p.desc { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.4; }

        .tab-switcher {
            display: flex;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--surface-border);
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 22px;
            gap: 4px;
        }
        .tab-btn {
            flex: 1;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: #fff;
            background: var(--brand-primary);
        }

        .input-group { margin-bottom: 16px; text-align: left; }
        label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 7px; }
        input[type="text"], input[type="password"], select {
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--surface-border);
            padding: 12px 14px;
            border-radius: 10px;
            color: white;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        select option { background: #131822; color: #fff; }
        input:focus, select:focus {
            border-color: var(--brand-accent);
        }

        .btn-submit {
            width: 100%;
            background: var(--brand-primary);
            border: none;
            color: white;
            padding: 13px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.1s, opacity 0.2s;
            margin-top: 6px;
        }
        .btn-submit:active { transform: scale(0.98); }

        .btn-submit-cyan {
            background: var(--brand-accent);
            color: #080b10;
        }

        .error-msg {
            background: rgba(230, 57, 70, 0.15);
            color: #ff6b77;
            border: 1px solid rgba(230, 57, 70, 0.35);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: left;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="auth-card" id="authCard">
        <div class="avatar-placeholder">LIVE</div>
        <h2>Portal Live Chat</h2>
        <p class="desc">Masuk dengan akun Anda atau daftar baru untuk mengirim pesan.</p>

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
                    <input type="text" id="reg_name" name="reg_name" placeholder="Nama tampilan Anda" maxlength="50" autocomplete="off" required>
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
        // GSAP Enter Animation
        gsap.to("#authCard", {
            opacity: 1,
            y: 0,
            duration: 0.7,
            ease: "power3.out"
        });

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            if (tab === 'login') {
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
                const target = document.getElementById('tab-login');
                target.classList.add('active');
                gsap.fromTo(target, { opacity: 0, y: 10 }, { opacity: 1, y: 0, duration: 0.35, ease: "power2.out" });
            } else {
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
                const target = document.getElementById('tab-register');
                target.classList.add('active');
                gsap.fromTo(target, { opacity: 0, y: 10 }, { opacity: 1, y: 0, duration: 0.35, ease: "power2.out" });
            }
        }
    </script>
</body>
</html>
