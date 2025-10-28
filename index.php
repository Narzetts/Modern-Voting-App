<?php
session_start();
require_once 'config.php';

try {
    $settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch();
    if (!$settings) {
        die("❌ Pengaturan voting belum diatur. Silakan atur di halaman admin.");
    }
    $voting_start = $settings['voting_start'];
    $voting_end = $settings['voting_end'];
} catch (Exception $e) {
    die("❌ Gagal mengambil pengaturan: " . htmlspecialchars($e->getMessage()));
}

$current = date('Y-m-d H:i:s');
$voting_active = ($current >= $voting_start && $current <= $voting_end);

if (isset($_GET['api']) && $_GET['api'] === 'results') {
    try {
        $totalVotes = (int)$pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
        $totalVoters = (int)$pdo->query("SELECT COUNT(*) FROM voter_accounts")->fetchColumn();
        $participation = $totalVoters > 0 ? ($totalVotes / $totalVoters) * 100 : 0;

        $stmt = $pdo->query("
            SELECT c.id, c.name, c.photo, c.description, COUNT(v.id) AS votes
            FROM candidates c
            LEFT JOIN votes v ON c.id = v.candidate_id
            GROUP BY c.id
            ORDER BY votes DESC, c.id ASC
        ");
        $results = $stmt->fetchAll();

        $output = [
            'total_votes' => $totalVotes,
            'total_voters' => $totalVoters,
            'participation' => round($participation, 1),
            'results' => []
        ];

        foreach ($results as $row) {
            $pct = $totalVotes > 0 ? (($row['votes'] / $totalVotes) * 100) : 0;
            $output['results'][] = [
                'id' => (int)$row['id'],
                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                'photo' => htmlspecialchars($row['photo'], ENT_QUOTES, 'UTF-8'),
                'description' => htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'),
                'votes' => (int)$row['votes'],
                'percentage' => round($pct, 1)
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($output);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal memuat hasil']);
        exit;
    }
}

if (isset($_GET['page']) && $_GET['page'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_code'])) {
    if (!$voting_active) {
        $login_error = "Voting belum dibuka atau telah ditutup.";
    } else {
        $code = trim(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['login_code']));
        if (empty($code)) {
            $login_error = "Masukkan kode login yang valid!";
        } else {
            $stmt = $pdo->prepare("SELECT id, name, used FROM voter_accounts WHERE login_code = ?");
            $stmt->execute([$code]);
            $voter = $stmt->fetch();

            if ($voter) {
                $_SESSION['voter_id'] = $voter['id'];
                $_SESSION['voter_name'] = htmlspecialchars($voter['name'], ENT_QUOTES, 'UTF-8');
                $_SESSION['voter_used'] = (bool)$voter['used'];
                header('Location: index.php?page=vote');
                exit;
            } else {
                $login_error = "Kode login tidak valid!";
            }
        }
    }
}


$page = $_GET['page'] ?? 'login';

if ($page !== 'login' && $page !== 'rules' && (!isset($_SESSION['voter_id']) || !$_SESSION['voter_id'])) {
    header('Location: index.php?page=login');
    exit;
}


$vote_error = '';
$vote_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    if (!$voting_active) {
        $vote_error = "Voting telah ditutup.";
    } elseif ($_SESSION['voter_used'] ?? false) {
        $vote_success = true;
    } else {
        $cid = (int)$_POST['candidate_id'];
        $voter_id = $_SESSION['voter_id'];

        $stmt = $pdo->prepare("SELECT id FROM candidates WHERE id = ?");
        $stmt->execute([$cid]);
        if (!$stmt->fetch()) {
            $vote_error = "Kandidat tidak valid!";
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO votes (voter_account_id, candidate_id) VALUES (?, ?)")->execute([$voter_id, $cid]);
                $pdo->prepare("UPDATE voter_accounts SET used = 1 WHERE id = ?")->execute([$voter_id]);
                $pdo->commit();
                $_SESSION['voter_used'] = true;
                $vote_success = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $vote_error = "Gagal menyimpan suara. Coba lagi.";
            }
        }
    }
}

$candidates = [];
$totalVotes = 0;
$totalVoters = 0;
$participation = 0;
$results = [];

if (isset($_SESSION['voter_id'])) {
    $candidates = $pdo->query("SELECT id, name, description, photo FROM candidates ORDER BY id")->fetchAll();
    $totalVotes = (int)$pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
    $totalVoters = (int)$pdo->query("SELECT COUNT(*) FROM voter_accounts")->fetchColumn();
    $participation = $totalVoters > 0 ? ($totalVotes / $totalVoters) * 100 : 0;
    $results = $pdo->query("
        SELECT c.id, c.name, c.photo, c.description, COUNT(v.id) AS votes
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id
        GROUP BY c.id
        ORDER BY votes DESC, c.id ASC
    ")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --glass-bg: rgba(255, 255, 255, 0.88);
            --glass-border: rgba(255, 255, 255, 0.5);
            --text: #1e293b;
            --text-light: #64748b;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 24px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-top: 30px;
        }

        .container {
            max-width: 900px;
            width: 100%;
        }

        /* === NAVBAR === */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            background: var(--glass-bg);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            margin-bottom: 32px;
            animation: slideInDown 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
            opacity: 0;
        }

        .navbar-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .navbar-logo-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .logo-text {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-light);
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 14px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover, .nav-links a.active {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.12);
        }

        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-radius: 28px;
            padding: 40px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            margin-bottom: 32px;
            text-align: center;
            animation: fadeInUp 0.8s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
            opacity: 0;
            transform: translateY(30px);
        }

        /* === LOGO === */
        .login-logo,
        .animated-logo {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 18px;
            margin: 0 auto 24px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            opacity: 0;
            transform: scale(0.8);
            animation: logoPopIn 0.8s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }

        .animated-logo {
            width: 100px;
            height: 100px;
            animation: logoFloatIn 1.2s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }

        @keyframes logoPopIn {
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes logoFloatIn {
            0% { opacity: 0; transform: translateY(20px) scale(0.9); }
            70% { transform: translateY(-8px) scale(1.02); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .card-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* === TIMER === */
        .countdown {
            background: linear-gradient(90deg, #dbeafe, #eff6ff);
            padding: 14px 24px;
            border-radius: 18px;
            margin: 20px auto;
            font-weight: 700;
            color: var(--primary-dark);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.18);
        }

        .countdown-value {
            font-size: 20px;
            font-family: monospace;
        }

        /* === CARD KANDIDAT BARU === */
        .candidate-card {
            display: flex;
            align-items: flex-start;
            padding: 24px;
            background: white;
            border-radius: 20px;
            border: 2px solid transparent;
            transition: all 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-align: left;
        }

        .candidate-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: 0 12px 24px rgba(99, 102, 241, 0.25);
        }

        .candidate-photo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 16px;
            margin-right: 24px;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .candidate-card:hover .candidate-photo {
            transform: scale(1.05);
        }

        .candidate-info {
            flex: 1;
        }

        .candidate-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }

        .candidate-vision {
            font-size: 16px;
            line-height: 1.6;
            color: var(--text-light);
            font-style: italic;
        }

        .candidate-card.selected {
            border-color: var(--success);
            background: #f0fdf4;
        }

        .candidate-card.selected .candidate-name {
            color: var(--success);
        }

        /* === HASIL === */
        .results-grid {
            display: grid;
            gap: 24px;
            margin-top: 20px;
        }

        .result-card {
            display: flex;
            align-items: flex-start;
            padding: 24px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .result-stats {
            margin-left: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 130px;
        }

        .vote-count {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
        }

        .vote-pct {
            font-size: 17px;
            font-weight: 600;
            color: var(--warning);
            background: rgba(245, 158, 11, 0.12);
            padding: 5px 12px;
            border-radius: 12px;
            margin-top: 8px;
        }

        .pulse {
            animation: pulse 0.8s ease;
        }

        /* === TOMBOL === */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 16px 32px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 18px;
            font-weight: 700;
            font-size: 17px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 12px auto;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
        }

        .btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.6);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            box-shadow: none;
        }

        .btn-outline:hover {
            background: rgba(99, 102, 241, 0.08);
            transform: translateY(-3px);
        }

        .btn-success {
            background: linear-gradient(90deg, #10b981, #059669);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-success:hover {
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.55);
        }

        /* === MODAL KONFIRMASI === */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(6px);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            margin: 8% auto;
            padding: 32px;
            border-radius: 24px;
            width: 90%;
            max-width: 520px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            animation: popIn 0.4s forwards;
        }

        .confirm-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 18px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.18);
        }

        .confirm-name {
            font-size: 24px;
            font-weight: 700;
            margin: 12px 0;
            color: var(--text);
        }

        .confirm-vision {
            font-size: 16px;
            color: var(--text-light);
            font-style: italic;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }

        footer {
            text-align: center;
            color: var(--text-light);
            font-size: 14px;
            margin-top: 32px;
            opacity: 0.8;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInDown {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes popIn {
            to { transform: scale(1); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }

        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 16px; }
            .navbar-logo { justify-content: center; }
            .card { padding: 30px 20px; }
            .candidate-card,
            .result-card {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }
            .candidate-photo {
                margin-right: 0;
                margin-bottom: 20px;
                width: 100px;
                height: 100px;
            }
            .result-stats {
                margin-left: 0;
                margin-top: 20px;
                align-items: center;
            }
            .login-logo, .animated-logo { width: 80px; height: 80px; }
            .navbar-logo-img { width: 36px; height: 36px; }
            .modal-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($page !== 'login'): ?>
            <nav class="navbar" style="animation-delay: 0.1s;">
                <div class="navbar-logo">
                    <img 
                        src="uploads/logo.png" 
                        alt="Logo" 
                        class="navbar-logo-img"
                        onerror="this.src='image/svg+xml;charset=utf-8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2242%22 height=%2242%22 viewBox=%220 0 24 24%22 fill=%22%23cbd5e1%22><rect x=%224%22 y=%224%22 width=%2216%22 height=%2216%22 rx=%224%22/></svg>'"
                    >
                    <a href="index.php?page=vote" class="logo-text">VOTE</a>
                </div>
                <div class="nav-links">
                    <a href="index.php?page=vote" class="<?= $page === 'vote' ? 'active' : '' ?>">Voting</a>
                    <a href="index.php?page=results" class="<?= $page === 'results' ? 'active' : '' ?>">Hasil</a>
                    <a href="index.php?page=rules" class="<?= $page === 'rules' ? 'active' : '' ?>">Aturan</a>
                    <a href="index.php?page=logout">Logout</a>
                </div>
            </nav>
        <?php endif; ?>

        <?php if ($page === 'results'): ?>
            <div class="card" style="animation-delay: 0.2s;">
                <div class="card-header">
                    <h1 class="card-title">Hasil Voting</h1>
                    <div style="display:flex; justify-content:center; gap:24px; margin:20px 0; flex-wrap:wrap;">
                        <div style="background:white; padding:12px 24px; border-radius:16px; box-shadow:0 4px 12px rgba(0,0,0,0.06);">
                            <div style="font-size:22px; font-weight:800; color:var(--primary);"><?= number_format($totalVotes) ?></div>
                            <div style="font-size:13px; color:var(--text-light);">Total Suara</div>
                        </div>
                        <div style="background:white; padding:12px 24px; border-radius:16px; box-shadow:0 4px 12px rgba(0,0,0,0.06);">
                            <div style="font-size:22px; font-weight:800; color:var(--warning);"><?= round($participation, 1) ?>%</div>
                            <div style="font-size:13px; color:var(--text-light);">Partisipasi</div>
                        </div>
                    </div>
                </div>

                <?php if ($totalVotes == 0): ?>
                    <div style="padding: 30px; background: white; border-radius: 20px; margin-top: 20px; color: var(--text-light);">
                        Belum ada suara yang masuk.
                    </div>
                <?php else: ?>
                    <div class="results-grid">
                        <?php foreach ($results as $row): 
                            $votes = (int)$row['votes'];
                            $pct = $totalVotes > 0 ? (($votes / $totalVotes) * 100) : 0;
                        ?>
                            <div class="result-card">
                                <img 
                                    src="uploads/<?= htmlspecialchars($row['photo']) ?>" 
                                    alt="<?= htmlspecialchars($row['name']) ?>"
                                    class="candidate-photo"
                                    onerror="this.src='image/svg+xml;charset=utf-8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22 viewBox=%220 0 24 24%22 fill=%22%23e2e8f0%22><circle cx=%2212%22 cy=%2212%22 r=%2210%22/></svg>'"
                                >
                                <div class="candidate-info">
                                    <div class="candidate-name"><?= htmlspecialchars($row['name']) ?></div>
                                    <div class="candidate-vision"><?= htmlspecialchars($row['description']) ?></div>
                                </div>
                                <div class="result-stats">
                                    <div class="vote-count" id="votes-<?= (int)$row['id'] ?>"><?= number_format($votes) ?></div>
                                    <div class="vote-pct" id="pct-<?= (int)$row['id'] ?>"><?= round($pct, 1) ?>%</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 32px;">
                    <a href="index.php?page=vote" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($page === 'vote'): ?>
            <?php if ($_SESSION['voter_used'] ?? false): ?>
                <div class="card" style="animation-delay: 0.2s; text-align: center;">

                    <div style="font-size: 60px; color: var(--success); margin: 10px 0;">✅</div>
                    <h2 style="font-size: 28px; margin: 16px 0; color: var(--text);">Terima kasih telah memilih!</h2>
                    <p style="color: var(--text-light); margin-bottom: 28px; max-width: 500px; margin-left: auto; margin-right: auto;">
                        Suara Anda sangat berarti untuk kemajuan bersama.
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap: 16px; justify-content: center; margin-top: 20px;">
                        <a href="index.php?page=results" class="btn"> Lihat Hasil
                        </a>
                        <a href="index.php?page=logout" class="btn btn-outline"> Logout
                        </a>
                    </div>
                </div>

                <?php if ($vote_success): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            playSuccessSound();
                            launchConfetti();
                        });
                    </script>
                <?php endif; ?>

            <?php else: ?>
                <div class="card" style="animation-delay: 0.2s;">
                    <h2 style="font-size:28px; margin-bottom:16px;">🗳️ Halo, <?= htmlspecialchars($_SESSION['voter_name'] ?? 'Pemilih') ?>!</h2>
                    
                    <?php if ($voting_active): ?>
                        <div class="countdown">
                            <i class="fas fa-clock"></i>
                            <span class="countdown-value" id="countdown">Menghitung...</span>
                        </div>
                    <?php endif; ?>

                    <p style="margin-bottom:24px; color: var(--text-light);">Pilih salah satu kandidat di bawah ini.</p>
                    
                    <?php if (!empty($vote_error)): ?>
                        <div style="background:#fee; color:#c00; padding:14px; border-radius:12px; margin:16px 0;"><?= htmlspecialchars($vote_error) ?></div>
                    <?php endif; ?>
                    
                    <?php if (empty($candidates)): ?>
                        <p style="color: var(--text-light);">Belum ada kandidat yang tersedia.</p>
                    <?php else: ?>
                        <form id="voteForm" method="POST">
                            <?php foreach ($candidates as $c): ?>
                                <label class="candidate-card" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-photo="<?= htmlspecialchars($c['photo']) ?>" data-vision="<?= htmlspecialchars($c['description']) ?>">
                                    <input type="radio" name="candidate_id" value="<?= (int)$c['id'] ?>" style="display:none;">
                                    <img 
                                        src="uploads/<?= htmlspecialchars($c['photo']) ?>" 
                                        alt="<?= htmlspecialchars($c['name']) ?>"
                                        class="candidate-photo"
                                        onerror="this.src='image/svg+xml;charset=utf-8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22 viewBox=%220 0 24 24%22 fill=%22%23e2e8f0%22><circle cx=%2212%22 cy=%2212%22 r=%2210%22/></svg>'"
                                    >
                                    <div class="candidate-info">
                                        <div class="candidate-name"><?= htmlspecialchars($c['name']) ?></div>
                                        <div class="candidate-vision"><?= htmlspecialchars($c['description']) ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                            <button type="submit" class="btn" id="submitBtn" disabled style="margin-top:24px; width: auto; padding: 14px 36px;">
                                <i class="fas fa-paper-plane"></i> Kirim Suara
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($page === 'login'): ?>
            <div class="card" style="animation-delay: 0.2s;">
                <img 
                    src="uploads/logo.png" 
                    alt="Logo Sekolah" 
                    class="login-logo"
                    onerror="this.src='image/svg+xml;charset=utf-8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2290%22 height=%2290%22 viewBox=%220 0 24 24%22 fill=%22%23cbd5e1%22><rect x=%222%22 y=%222%22 width=%2220%22 height=%2220%22 rx=%226%22/></svg>'"
                >
                <h2 style="font-size:28px; margin-bottom:20px; color:var(--primary);">Login Pemilih</h2>
                <?php if (!$voting_active): ?>
                    <div style="background:rgba(239,247,255,0.8); padding:16px; border-radius:16px; margin:20px 0; color:var(--text-light);">
                        <?php if ($current < $voting_start): ?>
                            <i class="fas fa-clock"></i> Voting belum dibuka.<br>
                            Buka pada: <?= htmlspecialchars(date('d M Y H:i', strtotime($voting_start))) ?> WIB
                        <?php else: ?>
                            <i class="fas fa-check-circle"></i> Voting telah ditutup.<br>
                            Ditutup pada: <?= htmlspecialchars(date('d M Y H:i', strtotime($voting_end))) ?> WIB
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php if (!empty($login_error)): ?>
                        <div style="background:#fee; color:#c00; padding:14px; border-radius:12px; margin:16px 0;"><?= htmlspecialchars($login_error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="text" name="login_code" placeholder="Kode Login" required
                               style="width:100%; padding:16px; border:1px solid #ddd; border-radius:14px; font-size:16px; margin-bottom:20px;">
                        <button type="submit" class="btn">Masuk</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'rules'): ?>
            <div class="card" style="animation-delay: 0.2s;">
                <h2 style="font-size:28px; margin-bottom:20px;">📜 Aturan</h2>
                <ul style="text-align:left; padding-left:20px; line-height:1.8; color:var(--text-light);">
                    <li>Hanya pemilih terdaftar yang bisa memilih.</li>
                    <li>Satu pemilih = satu suara.</li>
                    <li>Voting: <?= htmlspecialchars(date('d M Y H:i', strtotime($voting_start))) ?> – <?= htmlspecialchars(date('d M Y H:i', strtotime($voting_end))) ?> WIB</li>
                </ul>
                <a href="index.php?page=vote" class="btn btn-outline" style="margin-top:24px;">Kembali</a>
            </div>
        <?php endif; ?>

        <footer>&copy; <?= date('Y') ?> • Voting Online</footer>
    </div>

    <!-- MODAL KONFIRMASI -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div style="font-size:48px; color:var(--warning); margin-bottom:12px;">⚠️</div>
            <h3>Konfirmasi Pilihan</h3>
            <img id="confirmPhoto" class="confirm-photo" src="" alt="Kandidat">
            <div class="confirm-name" id="confirmName"></div>
            <div class="confirm-vision" id="confirmVision"></div>
            <p>Anda yakin ingin memilih kandidat ini?</p>
            <div class="modal-buttons">
                <button id="confirmYes" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Ya, Pilih
                </button>
                <button id="confirmNo" class="btn btn-outline">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>

    <script>

        <?php if ($voting_active && ($page === 'vote' || $page === 'login')): ?>
        function updateCountdown() {
            const endTime = new Date("<?= htmlspecialchars($voting_end) ?>").getTime();
            const now = new Date().getTime();
            const distance = endTime - now;

            if (distance < 0) {
                document.getElementById("countdown").textContent = "Voting telah ditutup";
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            let timeStr = '';
            if (days > 0) timeStr += days + "h ";
            timeStr += String(hours).padStart(2, '0') + ":" + 
                       String(minutes).padStart(2, '0') + ":" + 
                       String(seconds).padStart(2, '0');

            document.getElementById("countdown").textContent = timeStr;
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateCountdown();
            setInterval(updateCountdown, 1000);
        });
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', () => {
            const candidateCards = document.querySelectorAll('.candidate-card');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('voteForm');
            const confirmModal = document.getElementById('confirmModal');
            const confirmPhoto = document.getElementById('confirmPhoto');
            const confirmName = document.getElementById('confirmName');
            const confirmVision = document.getElementById('confirmVision');
            let selectedCandidate = null;

            candidateCards.forEach(card => {
                card.addEventListener('click', () => {
                    candidateCards.forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    selectedCandidate = {
                        id: card.dataset.id,
                        name: card.dataset.name,
                        photo: card.dataset.photo,
                        vision: card.dataset.vision
                    };
                    submitBtn.disabled = false;
                });
            });

            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    if (!selectedCandidate) {
                        alert('Pilih kandidat terlebih dahulu!');
                        return;
                    }

                    confirmName.textContent = selectedCandidate.name;
                    confirmVision.textContent = selectedCandidate.vision || 'Tidak ada visi/misi.';
                    confirmPhoto.src = selectedCandidate.photo 
                        ? 'uploads/' + selectedCandidate.photo 
                        : 'image/svg+xml;charset=utf-8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="%23e2e8f0"><circle cx="12" cy="12" r="10"/></svg>';
                    
                    confirmModal.style.display = 'block';
                });
            }

            document.getElementById('confirmYes').onclick = () => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'candidate_id';
                input.value = selectedCandidate.id;
                form.appendChild(input);
                form.submit();
            };

            document.getElementById('confirmNo').onclick = () => {
                confirmModal.style.display = 'none';
            };

            window.onclick = (e) => {
                if (e.target === confirmModal) {
                    confirmModal.style.display = 'none';
                }
            };
        });

        function playSuccessSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = ctx.createOscillator();
                const gainNode = ctx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(ctx.destination);
                oscillator.type = 'sine';
                oscillator.frequency.value = 800;
                gainNode.gain.setValueAtTime(0.3, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                oscillator.start(ctx.currentTime);
                oscillator.stop(ctx.currentTime + 0.5);
            } catch (e) {
                console.log("Audio not supported");
            }
        }

        function launchConfetti() {
            const end = Date.now() + 3000;
            const colors = ['#6366f1', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b'];
            (function frame() {
                if (Date.now() > end) return;
                confetti({
                    particleCount: 3,
                    angle: 60,
                    spread: 55,
                    startVelocity: 60,
                    origin: { x: 0, y: 0.5 },
                    colors: colors
                });
                confetti({
                    particleCount: 3,
                    angle: 120,
                    spread: 55,
                    startVelocity: 60,
                    origin: { x: 1, y: 0.5 },
                    colors: colors
                });
                requestAnimationFrame(frame);
            }());
        }

        <?php if ($page === 'results'): ?>
        function updateResults() {
            fetch('?api=results')
                .then(res => res.json())
                .then(data => {
                    data.results.forEach(item => {
                        const voteEl = document.getElementById('votes-' + item.id);
                        const pctEl = document.getElementById('pct-' + item.id);
                        if (voteEl && pctEl) {
                            const currentVotes = parseInt(voteEl.textContent.replace(/\./g, '') || '0');
                            if (currentVotes !== item.votes) {
                                voteEl.classList.remove('pulse');
                                pctEl.classList.remove('pulse');
                                void voteEl.offsetWidth;
                                voteEl.textContent = item.votes.toLocaleString('id-ID');
                                pctEl.textContent = item.percentage + '%';
                                voteEl.classList.add('pulse');
                                pctEl.classList.add('pulse');
                            }
                        }
                    });
                })
                .catch(console.warn);
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateResults();
            setInterval(updateResults, 6000);
        });
        <?php endif; ?>
    </script>
</body>
</html>