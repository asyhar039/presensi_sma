<?php
/**
 * Middleware & Security
 */

/**
 * Sanitize input
 */
function sanitize($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number
 */
function isValidPhone($phone) {
    return preg_match('/^(\+62|0)[0-9]{9,12}$/', $phone);
}

/**
 * Validate date format
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * CSRF Token Generation
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token Verification
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Error handling
 */
function showError($message) {
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Success message
 */
function showSuccess($message) {
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Sukses!</strong> ' . htmlspecialchars($message) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Get flash message
 */
function getFlashMessage() {
    $message = '';
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
    }
    return $message;
}

/**
 * Set flash message
 */
function setFlashMessage($type, $text) {
    $_SESSION['message'] = $type === 'error' ? showError($text) : showSuccess($text);
}

/**
 * Log activity
 */
function logActivity($action, $details, $conn) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $timestamp = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $action, $details, $timestamp);
        $stmt->execute();
    } catch (Exception $e) {
        // Log silently
    }
}
?>
