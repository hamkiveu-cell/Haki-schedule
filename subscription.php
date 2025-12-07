<?php
session_start();
require_once __DIR__ . '/db/config.php';

// Ensure user has just registered
if (!isset($_SESSION['user_id_for_activation'])) {
    header('Location: register.php');
    exit;
}

$user_id = $_SESSION['user_id_for_activation'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['activate'])) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                // Activation successful
                unset($_SESSION['user_id_for_activation']);
                session_destroy(); // Clean up session

                // Redirect to login with a success message
                header('Location: login.php?status=activated');
                exit;
            } else {
                $error = "Failed to activate your account. Please contact support.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Your Account - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card text-center shadow">
                    <div class="card-body p-5">
                        <h1 class="h2 fw-bold mb-3">One More Step!</h1>
                        <p class="lead mb-4">Your account has been created, but you need to activate it to get access to the application.</p>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <div class="p-4 bg-light rounded border">
                            <h3 class="h5 fw-bold">Basic Plan</h3>
                            <p class="fs-1 fw-bold mb-2">$10<span class="fs-6 fw-normal">/month</span></p>
                            <p class="text-muted">Full access for one school administrator.</p>
                            <form action="subscription.php" method="POST">
                                <div class="d-grid">
                                    <button type="submit" name="activate" class="btn btn-primary btn-lg">Activate Your Account</button>
                                </div>
                            </form>
                        </div>
                        <p class="mt-4 text-muted small">For now, clicking 'Activate' will simulate a successful payment. In a real application, you would be redirected to a payment processor.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white py-4 mt-5"><div class="container text-center"><p>&copy; <?php echo date("Y"); ?> Haki Schedule. All Rights Reserved.</p></div></footer>
</body>
</html>
