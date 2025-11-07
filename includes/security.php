<?php
// Security functions for Digital Salon

// Input sanitization
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// SQL injection protection
function escapeString($string) {
    global $pdo;
    return $pdo->quote($string);
}

// XSS protection
function cleanOutput($output) {
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}

// CSRF token generation
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate limiting (simple implementation)
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    $key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $timeWindow];
    }
    
    $data = $_SESSION[$key];
    
    // Reset if time window has passed
    if (time() > $data['reset_time']) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $timeWindow];
        return true;
    }
    
    // Check if limit exceeded
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

// Password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Password verification
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate random string
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (Turkish format)
function isValidPhone($phone) {
    return preg_match('/^5[0-9]{9}$/', $phone);
}

// Validate username
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

// File upload security
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4']) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        return false;
    }
    
    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return false;
    }
    
    return true;
}

// Generate secure filename
function generateSecureFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $filename = generateRandomString(16) . '.' . $extension;
    return $filename;
}

// Check if user is admin
function isAdmin($userRole) {
    return in_array($userRole, ['super_admin', 'moderator']);
}

// Check if user is super admin
function isSuperAdmin($userRole) {
    return $userRole === 'super_admin';
}

// Log security events
function logSecurityEvent($event, $details = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    error_log('SECURITY: ' . json_encode($logData));
}

// Prevent directory traversal
function preventDirectoryTraversal($path) {
    $path = str_replace(['../', '..\\', '..'], '', $path);
    return $path;
}

// Validate file path
function validateFilePath($path) {
    $path = preventDirectoryTraversal($path);
    
    // Only allow certain directories
    $allowedDirs = ['uploads/', 'profiles/', 'events/', 'media/', 'stories/'];
    
    foreach ($allowedDirs as $dir) {
        if (strpos($path, $dir) === 0) {
            return true;
        }
    }
    
    return false;
}

// Session security
function secureSession() {
    // Only set session parameters if session is not active
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
        ini_set('session.use_strict_mode', 1);
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Initialize security (only if session is not already started)
if (session_status() === PHP_SESSION_NONE) {
    secureSession();
}
?>
