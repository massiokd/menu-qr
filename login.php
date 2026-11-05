<?php
session_start();
require 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = true");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Vérification du mot de passe (admin123)
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['restaurant_id'] = $user['restaurant_id'];
        header('Location: admin.php');
        exit;
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Gérant</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm">
        <h1 class="text-2xl font-bold mb-6 text-center">Espace Gérant</h1>
        <?php if($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input type="email" name="email" value="gerant@pizzeria.com" class="w-full border rounded px-3 py-2" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Mot de passe</label>
                <input type="password" name="password" class="w-full border rounded px-3 py-2" required>
            </div>
            <button type="submit" class="w-full bg-orange-500 text-white font-bold py-2 px-4 rounded hover:bg-orange-600">Se connecter</button>
        </form>
    </div>
</body>
</html>