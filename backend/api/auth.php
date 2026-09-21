<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Auth API Endpoint
 * Handles login, registration, session status, and logout.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../services/blood_service.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

$inputData = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
}
$data = array_merge($_POST, $inputData);

switch ($action) {
    case 'me':
        $user = currentUser();
        if ($user) {
            $donor = null;
            if ($user['role'] === 'DONOR') {
                $donor = queryOne("
                    SELECT d.*, bg.group_name 
                    FROM donors d 
                    JOIN blood_groups bg ON d.blood_group_id = bg.blood_group_id 
                    WHERE d.user_id = ?
                ", [$user['user_id']]);
            }
            jsonSuccess([
                'user' => $user,
                'donor' => $donor
            ], 'User is authenticated.');
        } else {
            jsonResponse(['success' => false, 'data' => null, 'message' => 'Not authenticated.'], 200);
        }
        break;

    case 'login':
        if ($method !== 'POST') {
            jsonError('Method Not Allowed. Use POST.', 405);
        }
        
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($email) || empty($password)) {
            jsonError('Please provide both email and password.');
        }

        $user = queryOne("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user) {
            jsonError('Invalid email or password.', 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            jsonError('Invalid email or password.', 401);
        }

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        unset($user['password_hash']);
        jsonSuccess($user, 'Signed in successfully.');
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        jsonSuccess(null, 'Signed out successfully.');
        break;

    case 'register':
        if ($method !== 'POST') {
            jsonError('Method Not Allowed. Use POST.', 405);
        }

        $fullName = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $role = strtoupper(trim($data['role'] ?? 'DONOR'));
        $password = trim($data['password'] ?? '');

        if (empty($fullName) || empty($email) || empty($password) || empty($role)) {
            jsonError('Full name, email, role, and password are required.');
        }

        if (!in_array($role, ['ADMIN', 'DONOR', 'REQUESTER'])) {
            jsonError('Invalid role specified.');
        }

        $existing = queryOne("SELECT user_id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            jsonError('An account with this email address already exists.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();

            $insertUserStmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, phone, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertUserStmt->execute([$fullName, $email, $passwordHash, $phone, $role]);
            $userId = $pdo->lastInsertId();

            if ($role === 'DONOR') {
                $bloodGroupId = !empty($data['blood_group_id']) ? intval($data['blood_group_id']) : 1;
                $gender = trim($data['gender'] ?? 'OTHER');
                $dob = !empty($data['date_of_birth']) ? trim($data['date_of_birth']) : (!empty($data['dob']) ? trim($data['dob']) : '1995-01-01');
                $address = trim($data['address'] ?? 'Dhaka, Bangladesh');
                $city = trim($data['city'] ?? 'Dhaka');

                $insertDonorStmt = $pdo->prepare("
                    INSERT INTO donors (user_id, blood_group_id, date_of_birth, gender, address, city, is_eligible)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $insertDonorStmt->execute([$userId, $bloodGroupId, $dob, $gender, $address, $city]);
            }

            $pdo->commit();

            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $fullName;

            jsonSuccess([
                'user_id' => $userId,
                'full_name' => $fullName,
                'email' => $email,
                'role' => $role
            ], 'Registration completed successfully.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonError('Registration failed: ' . $e->getMessage(), 500);
        }
        break;

    default:
        jsonError('Unknown auth action.', 400);
        break;
}
