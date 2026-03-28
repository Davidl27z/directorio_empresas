<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_role'] = $user['rol'];

        // Previene session fixation
        session_regenerate_id(true);

        // Redirigir a URL guardada o dashboard según rol
        if (isset($_SESSION['redirect_after_login'])) {
            $redirect = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            header("Location: " . $redirect);
        } else {
            // Redirección por defecto según rol
            if ($user['rol'] == 'admin') {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../user/dashboard.php");
            }
        }
        exit;
    } else {
        $error = "Credenciales incorrectas";
    }
}
?>
<?php include '../templates/header.php'; ?>

<div class="row justify-content-center mt-5">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="text-center mb-4">Iniciar Sesión</h3>
                <?php if(isset($_GET['msg'])): ?>
                    <div class="alert alert-info"><?= htmlspecialchars($_GET['msg']) ?></div>
                <?php endif; ?>
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
                <p class="mt-3 text-center">
                    <?php if (!isset($base_url)) $base_url = '..'; ?>
                    ¿No tienes cuenta? <a href="<?= $base_url ?>/auth/register.php">Regístrate</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>