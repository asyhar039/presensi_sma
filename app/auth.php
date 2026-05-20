<?php
/**
 * Authentication Module
 */

session_start();

/**
 * Login user
 */
function login($username, $password, $conn) {
    try {
        $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, role FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Verify password
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role'] = $user['role'];
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check user role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Get dynamic base URL for subfolder or root hosting
 */
function getBaseUrl() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('/^(\/[^\/]+)/', $script, $matches)) {
        $first_dir = $matches[1];
        if (in_array($first_dir, ['/public', '/modules', '/config', '/app'])) {
            return '';
        }
        return $first_dir;
    }
    return '';
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . getBaseUrl() . "/public/login.php");
        exit();
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header("Location: " . getBaseUrl() . "/public/dashboard.php");
        exit();
    }
}

/**
 * Logout user
 */
function logout() {
    session_destroy();
    header("Location: " . getBaseUrl() . "/public/login.php");
    exit();
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'nama_lengkap' => $_SESSION['nama_lengkap'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}
?>
