<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                // Role-based redirection
                $redirect_url = '';
                switch ($user['role']) {
                    case 'admin':
                        $redirect_url = './modules/admin/dashboard/dashboard.php';
                        break;
                    case 'verifier':
                        $redirect_url = './modules/verifier/dashboard/dashboard.php';
                        break;
                    case 'landowner':
                    default:
                        $redirect_url = './modules/landowner/dashboard/dashboard.php';
                        break;
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Login successful',
                    'redirect' => $redirect_url,
                    'role' => $user['role']
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid password'
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Login failed: ' . $e->getMessage()
        ]);
    }
}