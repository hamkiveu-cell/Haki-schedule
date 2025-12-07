<?php
require_once __DIR__ . '/db/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $school_name = $_POST['school_name'] ?? null;
    $email = $_POST['email'] ?? null;

    if (empty($username) || empty($password) || empty($school_name) || empty($email)) {
        $error = 'All fields are required.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Check if school name already exists
            $stmt = $pdo->prepare("SELECT id FROM schools WHERE name = ?");
            $stmt->execute([$school_name]);
            if ($stmt->fetch()) {
                $error = 'School name already taken. Please choose another one.';
                $pdo->rollBack();
            } else {
                // Insert new school
                $stmt = $pdo->prepare("INSERT INTO schools (name) VALUES (?)" );
                $stmt->execute([$school_name]);
                $school_id = $pdo->lastInsertId();

                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already taken. Please choose another one.';
                    $pdo->rollBack();
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, school_id, role) VALUES (?, ?, ?, ?, 'admin')");
                    if ($stmt->execute([$username, $hashed_password, $email, $school_id])) {
                        $pdo->commit();
                        
                        // Start session and store user ID for activation
                        session_start();
                        $_SESSION['user_id_for_activation'] = $pdo->lastInsertId();

                        // Redirect to subscription page
                        header("Location: subscription.php");
                        exit;
                    } else {
                        $error = 'Failed to register user.';
                        $pdo->rollBack();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
                                <label for="school_name" class="form-label">School Name</label>
                                <input type="text" class="form-control" id="school_name" name="school_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Admin Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                             <div class="mb-3">
                                <label for="email" class="form-label">Admin Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
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