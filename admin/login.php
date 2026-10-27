<?php
// admin/login.php - Login Khusus Admin
session_start();
require_once __DIR__ . '/../api/db.php';

$error = '';

if (isset($_SESSION['livechat_admin_logged'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['livechat_admin_logged'] = true;
            $_SESSION['livechat_admin_user'] = $admin['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau Password salah.';
        }
    } else {
        $error = 'Harap isi semua kolom.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Live Chat Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background-color: #08090d;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background-image: radial-gradient(circle at 50% 30%, rgba(112, 0, 255, 0.15) 0%, transparent 60%);
        }
        .login-card {
            background: rgba(18, 22, 34, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 36px 30px;
            border-radius: 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
            text-align: center;
        }
        .brand-badge {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #ff0055, #7000ff);
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 24px;
            font-weight: 800;
            box-shadow: 0 10px 25px rgba(255, 0, 85, 0.4);
        }
        h2 { font-family: 'Outfit', sans-serif; font-size: 24px; font-weight: 800; margin-bottom: 6px; }
        p.sub { font-size: 13.5px; color: rgba(255, 255, 255, 0.6); margin-bottom: 26px; }
        .input-group { text-align: left; margin-bottom: 18px; }
        label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: rgba(255, 255, 255, 0.7); margin-bottom: 8px; }
        input {
            width: 100%;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 13px 16px;
            border-radius: 12px;
            color: #fff;
            font-size: 14.5px;
            outline: none;
            transition: all 0.2s;
        }
        input:focus { border-color: #00f2fe; background: rgba(255, 255, 255, 0.1); box-shadow: 0 0 15px rgba(0, 242, 254, 0.25); }
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #00f2fe, #4facfe);
            border: none;
            color: #08090d;
            font-weight: 800;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 10px 25px rgba(0, 242, 254, 0.35);
            transition: transform 0.15s;
        }
        .btn-submit:active { transform: scale(0.97); }
        .error-box {
            background: rgba(255, 0, 85, 0.15);
            border: 1px solid rgba(255, 0, 85, 0.4);
            color: #ff3366;
            padding: 10px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-badge">ADM</div>
        <h2>Panel Kontrol Admin</h2>
        <p class="sub">Kelola Mode Siaran, Penerima, & Pengguna</p>

        <?php if (!empty($error)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="admin" required autofocus autocomplete="off">
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-submit">Masuk Dashboard</button>
        </form>
    </div>
</body>
</html>
