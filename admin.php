<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);


session_start();

require_once __DIR__ . '/config/admin_auth.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'config.php';

if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}

function isValidImage($tmp_name) {
    if (!is_file($tmp_name) || filesize($tmp_name) > 2 * 1024 * 1024) {
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);
    return in_array($mime, ['image/jpeg', 'image/png', 'image/gif']);
}

$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
if (!$settings) {
    die("❌ Tabel pengaturan tidak ditemukan. Jalankan SQL pengaturan.");
}

$voting_start = $settings['voting_start'];
$voting_end = $settings['voting_end'];
$current = date('Y-m-d H:i:s');
$voting_active = ($current >= $voting_start && $current <= $voting_end);

$voting_status = 'Belum Dibuka';
$timer_text = '';
if ($current < $voting_start) {
    $voting_status = 'Belum Dibuka';
    $timer_text = "Buka pada: " . date('d M Y H:i', strtotime($voting_start)) . " WIB";
} elseif ($current > $voting_end) {
    $voting_status = 'Sudah Ditutup';
    $timer_text = "Ditutup pada: " . date('d M Y H:i', strtotime($voting_end)) . " WIB";
} else {
    $voting_status = 'Berlangsung';
    $timer_text = "Berakhir pada: " . date('d M Y H:i', strtotime($voting_end)) . " WIB";
}

$success_msg = '';
$error_msg = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['admin_logged_in'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Permintaan tidak valid.";
    } elseif ($_POST['username'] === ADMIN_USERNAME && 
              password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        session_regenerate_id(true);
        header('Location: admin.php');
        exit;
    } else {
        $error = "Username atau password salah.";
    }
}

if (isset($_SESSION['admin_logged_in']) && (time() - ($_SESSION['admin_login_time'] ?? 0)) > 86400) {
    unset($_SESSION['admin_logged_in']);
}

if (!isset($_SESSION['admin_logged_in'])):
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login Admin • Voting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --light: #f8f9fa;
            --dark: #212529;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: white;
            line-height: 1.6;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 40px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            text-align: center;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 4px solid var(--primary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 16px;
            transition: var(--transition);
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }
        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #ffe6e6;
            color: #cc0000;
            text-align: center;
            border-left: 4px solid #e63946;
        }
        .footer-text {
            margin-top: 20px;
            font-size: 14px;
            color: #868e96;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="uploads/logo.png" alt="Logo Sekolah" class="login-logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDI0IDI0Ij48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCIvPjwvc3ZnPg=='">
        <h2 class="login-title"><i class="fas fa-lock"></i> Login Admin</h2>
        <?php if (!empty($error)): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-key"></i> Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login <i class="fas fa-arrow-right"></i></button>
        </form>
        <div class="footer-text">© <?= date('Y') ?> • Voting App</div>
    </div>
</body>
</html>
<?php
exit;
endif;

if (isset($_GET['export']) && $_GET['export'] === 'kartu_peserta') {
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'] ?? '')) {
        die('Akses ditolak.');
    }

    $stmt = $pdo->query("SELECT name, class, login_code FROM voter_accounts ORDER BY name");
    $voters = $stmt->fetchAll();

    if (empty($voters)) {
        die("Tidak ada data pemilih.");
    }

    if (!class_exists('SimplePDF', false)) {
        class SimplePDF {
            public $w = 210;
            public $h = 297;
            private $pages = [];
            private $current_page = '';
            private $y = 20;
            private $x = 20;
            private $k = 72 / 25.4;

            public function __construct() { $this->AddPage(); }
            public function AddPage() {
                if ($this->current_page !== '') $this->pages[] = $this->current_page;
                $this->current_page = ''; $this->y = 20;
            }
            public function SetXY($x, $y) { $this->x = $x; $this->y = $y; }
            public function Cell($w, $h, $txt, $border = 0, $ln = 1) {
                $txt = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $txt);
                $x_pt = $this->x * $this->k;
                $y_pt = ($this->h - $this->y) * $this->k;
                $this->current_page .= "BT /F1 12 Tf $x_pt $y_pt Td ($txt) Tj ET\n";
                if ($border) $this->current_page .= "$x_pt " . ($y_pt - $h * $this->k) . " $w " . ($h * $this->k) . " re S\n";
                $this->y += $h + 2;
            }
            public function Rect($x, $y, $w, $h) {
                $x_pt = $x * $this->k;
                $y_pt = ($this->h - $y) * $this->k;
                $this->current_page .= "$x_pt " . ($y_pt - $h * $this->k) . " " . ($w * $this->k) . " " . ($h * $this->k) . " re S\n";
            }
            public function SetDrawColor($r, $g, $b) {
                $this->current_page .= sprintf("%.3F %.3F %.3F RG\n", $r/255, $g/255, $b/255);
            }
            public function SetTextColor($r, $g, $b) {
                $this->current_page .= sprintf("%.3F %.3F %.3F rg\n", $r/255, $g/255, $b/255);
            }
            public function Output($filename) {
                $this->pages[] = $this->current_page;
                $pdf = "%PDF-1.4\n";
                $objs = [];
                $objs[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
                $objs[] = "2 0 obj\n<< /Type /Pages /Kids [";
                for ($i = 0; $i < count($this->pages); $i++) $objs[] = "3 " . ($i*2+2) . " R ";
                $objs[] = "] /Count " . count($this->pages) . " >>\nendobj\n";
                for ($i = 0; $i < count($this->pages); $i++) {
                    $stream = $this->pages[$i];
                    $len = strlen($stream);
                    $objs[] = (3 + $i*2) . " 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . (595.28) . " " . (841.89) . "] /Contents " . (4 + $i*2) . " 0 R >>\nendobj\n";
                    $objs[] = (4 + $i*2) . " 0 obj\n<< /Length $len >>\nstream\n$stream\nendstream\nendobj\n";
                }
                $xref = strlen($pdf);
                foreach ($objs as $obj) $pdf .= $obj;
                $pdf .= "xref\n0 " . (count($objs)+1) . "\n";
                $pdf .= "0000000000 65535 f \n";
                for ($i = 0; $i < count($objs); $i++) $pdf .= sprintf("%010d 00000 n \n", $xref);
                $pdf .= "trailer\n<< /Size " . (count($objs)+1) . " /Root 1 0 R >>\nstartxref\n" . (strlen("%PDF-1.4\n")) . "\n%%EOF";
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $filename . '"');
                echo $pdf;
                exit;
            }
        }
    }

    $pdf = new SimplePDF();
    $pdf->SetDrawColor(50, 50, 200);

    $cardW = 85;
    $cardH = 50;
    $margin = 5;
    $cols = 2;
    $rows = 3;
    $startX = ($pdf->w - ($cols * $cardW + ($cols - 1) * $margin)) / 2;
    $startY = 20;

    foreach ($voters as $i => $v) {
        if ($i > 0 && $i % ($cols * $rows) == 0) $pdf->AddPage();
        $col = $i % $cols;
        $row = floor($i / $cols) % $rows;
        $x = $startX + $col * ($cardW + $margin);
        $y = $startY + $row * ($cardH + $margin);
        $pdf->Rect($x, $y, $cardW, $cardH);
        $pdf->SetXY($x + 10, $y + 8);
        $pdf->SetTextColor(50, 50, 200);
        $pdf->Cell(0, 5, 'VOTING', 0, 1);
        $pdf->SetXY($x + 10, $y + 18);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 5, 'Nama: ' . $v['name'], 0, 1);
        $pdf->SetXY($x + 10, $y + 26);
        $pdf->Cell(0, 5, 'Kelas: ' . $v['class'], 0, 1);
        $pdf->SetXY($x + 10, $y + 36);
        $pdf->SetTextColor(220, 0, 0);
        $pdf->Cell(0, 5, 'KODE: ' . $v['login_code'], 0, 1);
    }

    $pdf->Output('kartu_peserta_voting.pdf');
    exit;
}

function generateLoginCode($length = 6) {
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    $start = $_POST['voting_start'] . ':00';
    $end = $_POST['voting_end'] . ':00';
    
    if (strtotime($start) >= strtotime($end)) {
        $error_msg = "Waktu mulai harus sebelum waktu berakhir!";
    } else {
        $pdo->prepare("UPDATE settings SET voting_start = ?, voting_end = ? WHERE id = 1")
            ->execute([$start, $end]);
        $success_msg = "✅ Pengaturan berhasil disimpan!";
        header('Location: admin.php?action=settings');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_candidate') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    if ($name) {
        $photo = $_POST['current_photo'];
        if (!empty($_FILES['photo']['tmp_name']) && isValidImage($_FILES['photo']['tmp_name'])) {
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                default => null
            };
            if ($ext) {
                $photo = 'cand_' . bin2hex(random_bytes(8)) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $photo);
            }
        }
        $pdo->prepare("UPDATE candidates SET name = ?, description = ?, photo = ? WHERE id = ?")
            ->execute([$name, $desc, $photo, $id]);
        $success_msg = "✅ Kandidat berhasil diperbarui!";
    } else {
        $error_msg = "Nama kandidat wajib diisi.";
    }
    header('Location: admin.php?action=candidates');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_voter') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    $id = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM voter_accounts WHERE id = ?")->execute([$id]);
    $success_msg = "🗑️ Akun pemilih berhasil dihapus.";
    header('Location: admin.php?action=voters');
    exit;
}

$action = $_GET['action'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_candidate') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    if ($name) {
        $photo = 'default.jpg';
        if (!empty($_FILES['photo']['tmp_name']) && isValidImage($_FILES['photo']['tmp_name'])) {
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                default => null
            };
            if ($ext) {
                $photo = 'cand_' . bin2hex(random_bytes(8)) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $photo);
            }
        }
        $pdo->prepare("INSERT INTO candidates (name, description, photo) VALUES (?, ?, ?)")
            ->execute([$name, $desc, $photo]);
        $success_msg = "✅ Kandidat berhasil ditambahkan!";
    } else {
        $error_msg = "Nama kandidat wajib diisi.";
    }
    $action = 'candidates';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'del_candidate') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    $id = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
    $success_msg = "🗑️ Kandidat berhasil dihapus.";
    $action = 'candidates';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_voter') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    $name = trim($_POST['name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    if ($name && $class) {
        $code = generateLoginCode();
        try {
            $pdo->prepare("INSERT INTO voter_accounts (name, class, login_code) VALUES (?, ?, ?)")
                ->execute([$name, $class, $code]);
            $success_msg = "✅ Akun pemilih berhasil dibuat! Kode login: <strong>$code</strong>";
        } catch (PDOException $e) {
            $error_msg = "❌ Gagal membuat akun. Mungkin duplikat?";
        }
    } else {
        $error_msg = "Nama dan kelas wajib diisi.";
    }
    $action = 'voters';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'import_csv') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    if (!empty($_FILES['csv']['tmp_name'])) {
        $count = 0;
        if (($handle = fopen($_FILES['csv']['tmp_name'], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) >= 2) {
                    $name = trim($data[0]);
                    $class = trim($data[1]);
                    if ($name && $class) {
                        $code = generateLoginCode();
                        try {
                            $pdo->prepare("INSERT INTO voter_accounts (name, class, login_code) VALUES (?, ?, ?)")
                                ->execute([$name, $class, $code]);
                            $count++;
                        } catch (PDOException $e) {}
                    }
                }
            }
            fclose($handle);
            $success_msg = "✅ Berhasil mengimpor $count akun pemilih.";
        } else {
            $error_msg = "❌ Gagal membaca file CSV.";
        }
    } else {
        $error_msg = "❌ Silakan pilih file CSV.";
    }
    $action = 'voters';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_votes') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Permintaan tidak valid.');
    }
    $pdo->exec("DELETE FROM votes; UPDATE voter_accounts SET used = 0");
    $success_msg = "🔄 Semua suara telah direset.";
    $action = 'dashboard';
}

$candidates = $pdo->query("SELECT * FROM candidates")->fetchAll();
$voters = $pdo->query("SELECT * FROM voter_accounts ORDER BY created_at DESC")->fetchAll();
$totalVotes = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$usedVoters = $pdo->query("SELECT COUNT(*) FROM voter_accounts WHERE used = 1")->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Panel • Voting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --light: #f8f9fa;
            --dark: #212529;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        .navbar {
            background: white;
            padding: 16px 24px;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }
        .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            gap: 8px;
        }
        .nav-links a {
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            color: #6c757d;
            font-weight: 500;
            transition: var(--transition);
        }
        .nav-links a:hover,
        .nav-links a.active {
            background: #eef2ff;
            color: var(--primary);
            transform: translateY(-2px);
        }
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
        }
        .hamburger span {
            width: 25px;
            height: 3px;
            background: var(--primary);
            margin: 3px 0;
            border-radius: 2px;
        }
        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 24px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
            transition: var(--transition);
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            margin-bottom: 16px;
            color: var(--secondary);
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 16px;
        }
        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #e63946;
        }
        .btn-danger:hover {
            background: #d90429;
        }
        .btn-success {
            background: #2a9d8f;
        }
        .btn-pdf {
            background: #6a0dad;
        }
        .btn-pdf:hover {
            background: #5a0c9d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--secondary);
        }
        tr:hover td {
            background: #f8f9ff;
            color: var(--primary);
        }
        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            margin: 6px 0;
            font-size: 16px;
            transition: var(--transition);
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        .avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 16px 0;
            font-weight: 500;
            border-left: 4px solid transparent;
            transition: var(--transition);
        }
        .alert-success {
            background: #d1f0e5;
            color: #0d7d5f;
            border-left-color: #0d7d5f;
        }
        .alert-error {
            background: #ffe6e6;
            color: #cc0000;
            border-left-color: #e63946;
        }
        footer {
            text-align: center;
            padding: 24px;
            color: #868e96;
            font-size: 14px;
            margin-top: 40px;
            border-top: 1px solid #eee;
        }
        @media (max-width: 768px) {
            .nav-links {
                position: fixed;
                top: 70px;
                left: -100%;
                flex-direction: column;
                background: white;
                width: 100%;
                text-align: center;
                box-shadow: 0 10px 10px rgba(0,0,0,0.1);
                transition: 0.3s;
                padding: 16px 0;
            }
            .nav-links.active {
                left: 0;
            }
            .hamburger {
                display: flex;
            }
            .card {
                padding: 20px;
            }
            .btn {
                width: 100%;
                margin: 8px 0;
                text-align: center;
            }
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            th, td {
                padding: 10px 8px;
                font-size: 14px;
            }
            .logo-text {
                font-size: 20px;
            }
            .logo-img {
                width: 32px;
                height: 32px;
            }
        }
        @media (max-width: 480px) {
            .card {
                padding: 16px;
            }
            h2 {
                font-size: 20px;
            }
            .btn {
                padding: 12px;
                font-size: 15px;
            }
            input, textarea {
                padding: 10px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo-container">
                <img src="uploads/logo.png" alt="Logo Sekolah" class="logo-img" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDI0IDI0Ij48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCIvPjwvc3ZnPg=='">
                <a href="index.php" class="logo-text">Voting Admin</a>
            </div>
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="nav-links">
                <a href="admin.php" class="<?= $action === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
                <a href="admin.php?action=candidates" class="<?= $action === 'candidates' ? 'active' : '' ?>">👥 Kandidat</a>
                <a href="admin.php?action=voters" class="<?= $action === 'voters' ? 'active' : '' ?>">🎫 Pemilih</a>
                <a href="admin.php?action=settings" class="<?= $action === 'settings' ? 'active' : '' ?>">⚙️ Pengaturan</a>
                <a href="admin.php?logout&csrf_token=<?= $_SESSION['csrf_token'] ?>" style="background:#ffe6e6;color:#e63946;" onclick="return confirm('Yakin logout?')">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($success_msg): ?><div class="alert alert-success"><?= $success_msg ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-error"><?= $error_msg ?></div><?php endif; ?>

        <?php if ($action === 'voters'): ?>
            <a href="admin.php?export=kartu_peserta&csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank" class="btn btn-pdf" style="display:inline-block; margin:16px 0;">
                <i class="fas fa-id-card"></i> Cetak Kartu Peserta (PDF)
            </a>
        <?php endif; ?>

        <?php if ($action === 'dashboard'): ?>
            <div class="card">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                
                <div class="card" style="background:<?= $voting_active ? '#e8f5e9' : ($current < $voting_start ? '#fff3e0' : '#ffebee') ?>; border-left: 4px solid <?= $voting_active ? '#4caf50' : ($current < $voting_start ? '#ff9800' : '#f44336') ?>; padding:20px; border-radius:12px;">
                    <h3>⏰ Status Voting</h3>
                    <p><strong><?= $voting_status ?></strong></p>
                    <p><?= $timer_text ?></p>
                    <?php if ($voting_active): ?>
                        <p id="adminCountdown">Waktu tersisa: <span id="countdownText">Menghitung...</span></p>
                    <?php endif; ?>
                    <a href="admin.php?action=settings" class="btn" style="margin-top:10px;">Ubah Pengaturan Waktu</a>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin:20px 0;">
                    <div class="card" style="text-align:center; padding:24px; background:#f8f9fa; border:1px solid #dee2e6; border-radius:12px;">
                        <h3 style="color:var(--primary);"><i class="fas fa-users"></i> <?= count($candidates) ?></h3>
                        <p>Kandidat</p>
                    </div>
                    <div class="card" style="text-align:center; padding:24px; background:#f8f9fa; border:1px solid #dee2e6; border-radius:12px;">
                        <h3 style="color:#2a9d8f;"><i class="fas fa-id-card"></i> <?= count($voters) ?></h3>
                        <p>Akun Pemilih</p>
                    </div>
                    <div class="card" style="text-align:center; padding:24px; background:#f8f9fa; border:1px solid #dee2e6; border-radius:12px;">
                        <h3 style="color:#e63946;"><i class="fas fa-vote-yea"></i> <?= $totalVotes ?></h3>
                        <p>Suara Masuk</p>
                    </div>
                </div>

                <form method="POST" onsubmit="return confirm('⚠️ Reset SEMUA suara?')">
                    <input type="hidden" name="action" value="reset_votes">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-redo"></i> Reset Semua Vote</button>
                </form>
            </div>

        <?php elseif ($action === 'candidates'): ?>
            <div class="card">
                <h2><i class="fas fa-user-plus"></i> Tambah Kandidat Baru</h2>
                <form method="POST" enctype="multipart/form-data" action="admin.php?action=add_candidate">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="text" name="name" placeholder="Nama Kandidat" required>
                    <textarea name="desc" placeholder="Deskripsi" rows="3"></textarea>
                    <input type="file" name="photo" accept="image/*">
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Tambah</button>
                </form>
            </div>
            <div class="card">
                <h2>Daftar Kandidat</h2>
                <?php if (empty($candidates)): ?>
                    <p>Belum ada kandidat.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Foto</th><th>Nama</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($candidates as $c): ?>
                                <tr>
                                    <td><img src="uploads/<?= htmlspecialchars($c['photo']) ?>" class="avatar-sm" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDI0IDI0Ij48Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCIvPjwvc3ZnPg=='"></td>
                                    <td><?= htmlspecialchars($c['name']) ?></td>
                                    <td><?= htmlspecialchars($c['description']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kandidat ini?')">
                                            <input type="hidden" name="action" value="del_candidate">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding:6px 10px;font-size:12px;"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <a href="#" onclick="editCandidate(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>', '<?= addslashes(htmlspecialchars($c['description'])) ?>', '<?= addslashes(htmlspecialchars($c['photo'])) ?>')" class="btn btn-success" style="padding:6px 10px;font-size:12px;margin-left:5px;"><i class="fas fa-edit"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div id="editModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
                <div class="card" style="max-width:500px; margin:50px auto; background:white; padding:28px; border-radius:16px;">
                    <h3>Edit Kandidat</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_candidate">
                        <input type="hidden" name="id" id="editId">
                        <input type="hidden" name="current_photo" id="editCurrentPhoto">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="text" id="editName" name="name" placeholder="Nama Kandidat" required style="width:100%; padding:12px; margin:10px 0; border:1px solid #ddd; border-radius:8px;">
                        <textarea id="editDesc" name="desc" placeholder="Deskripsi" rows="3" style="width:100%; padding:12px; margin:10px 0; border:1px solid #ddd; border-radius:8px;"></textarea>
                        <input type="file" name="photo" accept="image/*" style="margin:10px 0; width:100%;">
                        <div style="display:flex; gap:10px; margin-top:16px;">
                            <button type="submit" class="btn btn-success" style="flex:1;"><i class="fas fa-save"></i> Simpan</button>
                            <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn" style="flex:1;">Batal</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'voters'): ?>
            <div class="card">
                <h2><i class="fas fa-user-tag"></i> Tambah Akun Pemilih Manual</h2>
                <form method="POST" action="admin.php?action=add_voter">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="text" name="name" placeholder="Nama Lengkap" required>
                    <input type="text" name="class" placeholder="Kelas" required>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Buat Akun</button>
                </form>
            </div>
            <div class="card">
                <h2><i class="fas fa-file-upload"></i> Impor dari CSV</h2>
                <p>Format: <code>nama,kelas</code></p>
                <form method="POST" enctype="multipart/form-data" action="admin.php?action=import_csv">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="file" name="csv" accept=".csv" required>
                    <button type="submit" class="btn"><i class="fas fa-upload"></i> Upload</button>
                </form>
            </div>
            <div class="card">
                <h2>Daftar Akun Pemilih (<?= count($voters) ?>)</h2>
                <?php if (empty($voters)): ?>
                    <p>Belum ada.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Nama</th><th>Kelas</th><th>Kode Login</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($voters as $v): ?>
                                <tr>
                                    <td><?= htmlspecialchars($v['name']) ?></td>
                                    <td><?= htmlspecialchars($v['class']) ?></td>
                                    <td><code><?= htmlspecialchars($v['login_code']) ?></code></td>
                                    <td><?= $v['used'] ? '<span style="color:#2a9d8f;"><i class="fas fa-check-circle"></i> Sudah</span>' : '<span style="color:#e63946;"><i class="fas fa-clock"></i> Belum</span>' ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus akun ini?')">
                                            <input type="hidden" name="action" value="delete_voter">
                                            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding:6px 10px;font-size:12px;"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'settings'): ?>
            <div class="card">
                <h2><i class="fas fa-cog"></i> Pengaturan Voting</h2>
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('⚠️ Yakin ingin mengubah waktu voting? Ini akan memengaruhi seluruh proses voting.')">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div style="margin-bottom:16px;">
                        <label>Waktu Mulai Voting</label>
                        <input type="datetime-local" name="voting_start" 
                               value="<?= date('Y-m-d\TH:i', strtotime($voting_start)) ?>" 
                               required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                    </div>
                    <div style="margin-bottom:16px;">
                        <label>Waktu Berakhir Voting</label>
                        <input type="datetime-local" name="voting_end" 
                               value="<?= date('Y-m-d\TH:i', strtotime($voting_end)) ?>" 
                               required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan Pengaturan</button>
                </form>
            </div>

        <?php endif; ?>

        <footer>&copy; <?= date('Y') ?> • Voting App</footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const navLinks = document.querySelector('.nav-links');
            if (hamburger && navLinks) {
                hamburger.addEventListener('click', () => navLinks.classList.toggle('active'));
                document.addEventListener('click', e => {
                    if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                        navLinks.classList.remove('active');
                    }
                });
            }

            <?php if ($action === 'dashboard' && $voting_active): ?>
            function updateAdminCountdown() {
                const endTime = new Date("<?= $voting_end ?>").getTime();
                const now = new Date().getTime();
                const distance = endTime - now;
                if (distance < 0) {
                    document.getElementById("countdownText").innerHTML = "Voting telah ditutup";
                    return;
                }
                const h = Math.floor(distance / (1000 * 60 * 60));
                const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((distance % (1000 * 60)) / 1000);
                document.getElementById("countdownText").innerHTML = h + "j " + m + "m " + s + "d";
            }
            updateAdminCountdown();
            setInterval(updateAdminCountdown, 1000);
            <?php endif; ?>
        });

        function editCandidate(id, name, desc, photo) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editDesc').value = desc;
            document.getElementById('editCurrentPhoto').value = photo;
            document.getElementById('editModal').style.display = 'block';
        }
    </script>
</body>
</html>