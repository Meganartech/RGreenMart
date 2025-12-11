<?php
require_once "dbconf.php";

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $mobile = trim($_POST["mobile"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

       if (empty($mobile)) $errors[] = "mobile is required.";
     if (empty($email)) $errors[] = "Email is required.";
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($password)) $errors[] = "Password is required.";

    if (!$errors) {
        // Check duplicates
        $sql = "SELECT * FROM users WHERE mobile = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$mobile ?: null, $email ?: null]);

        if ($stmt->rowCount() > 0) {
            $errors[] = "Mobile or Email already registered.";
        } else {
            // Insert user
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $insert = $conn->prepare("INSERT INTO users (name, mobile, email, password_hash) VALUES (?, ?, ?, ?)");
            $insert->execute([$name, $mobile ?: null, $email ?: null, $hash]);

            $success = "Account created successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body >
 <?php include "includes/header.php"; ?>
 <div class="flex items-center justify-center bg-gray-100 p-8">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">

        <h2 class="text-2xl font-bold mb-4 text-center">Create Account</h2>

        <!-- Error Messages -->
        <?php if ($errors): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
                <ul class="list-disc ml-4">
                    <?php foreach ($errors as $e): ?>
                        <li><?= $e ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">

            <input type="text" name="name" placeholder="Full Name"
                   class="w-full p-3 border rounded-lg" required>

            <input type="text" name="mobile" placeholder="Mobile Number"
                   class="w-full p-3 border rounded-lg">

            <input type="email" name="email" placeholder="Email Address"
                   class="w-full p-3 border rounded-lg">

            <input type="password" name="password" placeholder="Password"
                   class="w-full p-3 border rounded-lg" required>

            <button class="w-full bg-green-600 text-white p-3 rounded-lg hover:bg-green-700">
                Register
            </button>

            <p class="text-center text-sm mt-3">
                Already have an account?
                <a href="login.php" class="text-green-600 font-medium">Login</a>
            </p>

        </form>
    </div>
</div>
  <?php include "includes/footer.php"; ?>
</body>
</html>
