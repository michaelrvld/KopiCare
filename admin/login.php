<?php
require_once '../functions.php';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $query->execute([trim($_POST['email'] ?? '')]);
    $account = $query->fetch();

    if ($account && password_verify($_POST['password'] ?? '', $account['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int)$account['id'],
            'name' => $account['name'],
            'email' => $account['email'],
            'role' => 'admin',
        ];
        header('Location: '.app_url('admin/index.php'));
        exit;
    }

    $err = 'Akun admin tidak ditemukan atau kata sandi salah.';
}

header_html('Login admin');
?>
<section class="auth card">
    <span class="eyebrow">AREA TERBATAS</span>
    <h1>Login admin</h1>
    <?php if ($err): ?><div class="alert"><?=e($err)?></div><?php endif; ?>
    <form class="form" method="post">
        <label>Email<input type="email" name="email" required></label>
        <label>Kata sandi<input type="password" name="password" required></label>
        <button class="button">Login admin</button>
    </form>
    <p><a href="<?=e(app_url('admin/register.php'))?>">Register admin pertama</a></p>
</section>
<?php footer_html(); ?>
