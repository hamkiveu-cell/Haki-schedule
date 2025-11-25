<?php
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            $pdo = db();
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already taken. Please choose another one.';
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
                if ($stmt->execute([$username, $hashed_password])) {
                    $message = 'Registration successful! You can now <a href="login.php">login</a>.';
                } else {
                    $error = 'Failed to register user.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Haki Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo time(); ?>">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">Haki Schedule</a>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body p-4">
                        <h1 class="h3 fw-bold text-center mb-4">Create an Admin Account</h1>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if (!$message): ?>
                        <form action="register.php" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Register</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a>.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white py-4 mt-5"><div class="container text-center"><p>&copy; <?php echo date("Y"); ?> Haki Schedule. All Rights Reserved.</p></div></footer>
</body>
</html>
