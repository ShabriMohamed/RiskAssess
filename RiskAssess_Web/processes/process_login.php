<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Additional server-side validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }

    if (empty($password) || strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters long']);
        exit;
    }

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect user based on their role
            switch ($user['role']) {
                case 'admin':
                    echo json_encode(['status' => 'success', 'redirect' => 'AdminDashboard.php']);
                    break;
                case 'staff':
                    echo json_encode(['status' => 'success', 'redirect' => 'CounsellorDashboard.php']);
                    break;
                case 'customer':
                default:
                    echo json_encode(['status' => 'success', 'redirect' => 'UserDashboard.php']);
                    break;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    $stmt->close();
}
$conn->close();
?>
