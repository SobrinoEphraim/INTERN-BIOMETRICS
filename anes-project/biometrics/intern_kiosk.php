<?php
// ============================================================
// Intern Biometrics Kiosk — same idea as the main biometrics
// station, but ONLY accepts intern accounts. Their Time In / Out
// records stay exclusive to the Super Admin's Biometrics Device
// module and the intern's own DTR view.
// ============================================================
session_start();
require_once __DIR__ . '/../config/db_connect.php';

$upload_dir = __DIR__ . '/../uploads/dtr/';
if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $photo_data = $_POST['photo_data'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['role'] !== 'intern') {
            $error = 'This station is for intern accounts only.';
        } else {
            $last_stmt = $pdo->prepare('SELECT log_type FROM dtr_logs WHERE user_id = ? ORDER BY log_time DESC LIMIT 1');
            $last_stmt->execute([$user['id']]);
            $last_type = $last_stmt->fetchColumn();
            $next_type = ($last_type === 'time_in') ? 'time_out' : 'time_in';

            $photo_filename = null;
            if ($photo_data && preg_match('/^data:image\/(\w+);base64,/', $photo_data)) {
                $raw = base64_decode(substr($photo_data, strpos($photo_data, ',') + 1));
                if ($raw !== false) {
                    $photo_filename = 'dtr_' . $user['id'] . '_' . time() . '.jpg';
                    file_put_contents($upload_dir . $photo_filename, $raw);
                }
            }

            $ins = $pdo->prepare('INSERT INTO dtr_logs (user_id, log_type, photo_path) VALUES (?, ?, ?)');
            $ins->execute([$user['id'], $next_type, $photo_filename]);

            $result = [
                'name'  => $user['full_name'],
                'type'  => $next_type,
                'time'  => date('h:i A'),
                'date'  => date('F j, Y'),
                'photo' => $photo_filename,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Intern Biometrics - Time In / Time Out</title>
<style>
    * { box-sizing: border-box; }
    html, body {
        margin: 0; height: 100%; font-family: 'Segoe UI', Arial, sans-serif;
        background: #0d2d4e; display:flex; align-items:center; justify-content:center;
    }
    .kiosk-card {
        width: 480px; max-width: 94%; background: #fff; border-radius: 10px;
        border-top: 5px solid #0ea5e9; box-shadow: 0 20px 50px rgba(0,0,0,0.35);
        padding: 34px; text-align: center;
    }
    .kiosk-card h1 { font-size: 20px; color: #1a1a1a; margin: 0 0 4px 0; }
    .kiosk-card .subtitle { font-size: 13px; color: #6b7280; margin-bottom: 22px; }

    .camera-box {
        width: 100%; aspect-ratio: 4/3; background: #111; border-radius: 8px;
        overflow: hidden; margin-bottom: 18px; position: relative;
    }
    .camera-box video, .camera-box img { width: 100%; height: 100%; object-fit: cover; display:block; }
    .camera-box video { transform: scaleX(-1); }
    .camera-hint { position:absolute; bottom:8px; left:0; right:0; text-align:center; color:#fff; font-size:11px; opacity:0.8; }

    .form-group { text-align: left; margin-bottom: 16px; }
    .form-group label { display:block; font-size:12px; font-weight:bold; color:#4b5563; margin-bottom:6px; text-transform:uppercase; }
    .form-group input {
        width: 100%; padding: 13px 14px; border: 1px solid #d1d5db;
        border-radius: 6px; font-size: 15px;
    }

    .error-box { background:#fdecea; color:#b3261e; border:1px solid #f5c2c0; border-radius:6px; padding:10px 14px; font-size:13px; margin-bottom:16px; text-align:left; }

    .submit-btn {
        width: 100%; padding: 15px; border: none; border-radius: 6px;
        background: #0ea5e9; color: #fff; font-size: 16px; font-weight: bold; cursor: pointer;
    }
    .submit-btn:hover { background: #0c8bc4; }

    .confirm-icon { font-size: 46px; margin-bottom: 10px; }
    .confirm-icon.in { color: #1e7a34; }
    .confirm-icon.out { color: #a06b0a; }
    .confirm-name { font-size: 20px; font-weight: bold; color: #1a1a1a; margin-bottom: 4px; }
    .confirm-type { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
    .confirm-type.in { color: #1e7a34; }
    .confirm-type.out { color: #a06b0a; }
    .confirm-time { font-size: 14px; color: #6b7280; margin-bottom: 20px; }
    .confirm-photo { width: 160px; height: 160px; border-radius: 8px; object-fit: cover; margin: 0 auto 20px auto; display:block; border: 3px solid #eef0f3; }
    .redirect-note { font-size: 12px; color: #9ca3af; }
</style>
</head>
<body>

<?php if ($result): ?>
    <div class="kiosk-card">
        <div class="confirm-icon <?= $result['type'] === 'time_in' ? 'in' : 'out' ?>">
            <?= $result['type'] === 'time_in' ? '&#10003;' : '&#8594;' ?>
        </div>
        <?php if ($result['photo']): ?>
            <img class="confirm-photo" src="../uploads/dtr/<?= htmlspecialchars($result['photo']) ?>" alt="Captured photo">
        <?php endif; ?>
        <div class="confirm-name"><?= htmlspecialchars($result['name']) ?></div>
        <div class="confirm-type <?= $result['type'] === 'time_in' ? 'in' : 'out' ?>">
            <?= $result['type'] === 'time_in' ? 'TIME IN recorded' : 'TIME OUT recorded' ?>
        </div>
        <div class="confirm-time"><?= $result['time'] ?> &middot; <?= $result['date'] ?></div>
        <div class="redirect-note">Returning to the scanner in <span id="countdown">4</span> seconds...</div>
    </div>
    <script>
        let secs = 4;
        const el = document.getElementById('countdown');
        const timer = setInterval(() => {
            secs--;
            el.textContent = secs;
            if (secs <= 0) {
                clearInterval(timer);
                window.location.href = 'intern_kiosk.php';
            }
        }, 1000);
    </script>

<?php else: ?>
    <div class="kiosk-card">
        <h1>Intern Time In / Time Out</h1>
        <div class="subtitle">NKTI Anesthesiology &middot; Intern Biometrics Station</div>

        <div class="camera-box">
            <video id="video" autoplay playsinline></video>
            <div class="camera-hint">Look at the camera</div>
        </div>
        <canvas id="canvas" style="display:none;"></canvas>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="intern_kiosk.php" id="kioskForm">
            <input type="hidden" name="photo_data" id="photoData">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="submit-btn">Record Time In / Out</button>
        </form>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(stream => { video.srcObject = stream; })
                .catch(() => { document.querySelector('.camera-hint').textContent = 'Camera unavailable — continuing without photo'; });
        }

        document.getElementById('kioskForm').addEventListener('submit', function () {
            try {
                canvas.width = video.videoWidth || 320;
                canvas.height = video.videoHeight || 240;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                document.getElementById('photoData').value = canvas.toDataURL('image/jpeg', 0.8);
            } catch (e) {
                // no camera available — submit without a photo
            }
        });
    </script>
<?php endif; ?>

</body>
</html>
