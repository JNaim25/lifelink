<?php
/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Session & Authentication Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

/**
 * Retrieve the current authenticated user from session & database.
 */
function currentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return queryOne("SELECT user_id, full_name, email, phone, role FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
}

/**
 * Enforce that the request is made by an authenticated session.
 * For JSON APIs: returns 401 Unauthorized JSON response.
 */
function requireLoginAPI() {
    $user = currentUser();
    if (!$user) {
        jsonError('Authentication required. Please sign in.', 401);
    }
    return $user;
}

/**
 * Enforce that the request is made by a user with one of the allowed roles.
 */
function requireRoleAPI(...$allowedRoles) {
    $user = requireLoginAPI();
    if (!in_array($user['role'], $allowedRoles)) {
        jsonError('Access denied. Insufficient permissions for this action.', 403);
    }
    return $user;
}

function requireAdminAPI() {
    return requireRoleAPI('ADMIN');
}

function requireDonorAPI() {
    return requireRoleAPI('DONOR', 'ADMIN');
}

function requireRequesterAPI() {
    return requireRoleAPI('REQUESTER', 'ADMIN');
}
