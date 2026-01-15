<?php

/**
 * Centralized Authentication Module
 * Handles session management and authentication checks
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth configuration
$valid_username = 'admin';
$valid_password = 'password';

/**
 * Check if user is logged in
 * Returns false if not logged in, true if logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require authentication - redirect to login if not authenticated
 * Call at the top of pages that require login
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ' . getLoginUrl());
        exit;
    }
}

/**
 * Get the login page URL (works from any subdirectory)
 */
function getLoginUrl()
{
    // Determine the root path based on current directory
    $currentDir = dirname($_SERVER['SCRIPT_FILENAME']);
    $rootDir = dirname(dirname($currentDir)); // Go up to Case Study 1

    if (strpos($currentDir, 'dashboards') !== false) {
        return '../login.php';
    } else {
        return 'login.php';
    }
}

/**
 * Get the main index URL (works from any subdirectory)
 */
function getIndexUrl()
{
    $currentDir = dirname($_SERVER['SCRIPT_FILENAME']);

    if (strpos($currentDir, 'dashboards') !== false) {
        return '../index.php';
    } else {
        return 'index.php';
    }
}

/**
 * Authenticate user with credentials
 * Returns true on success, sets error message on failure
 */
function authenticateUser($username, $password, &$errorMessage = '')
{
    global $valid_username, $valid_password;

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['logged_in'] = true;
        return true;
    } else {
        $errorMessage = "Invalid username or password.";
        return false;
    }
}

/**
 * Logout user
 */
function logoutUser()
{
    session_destroy();
    header('Location: ' . getLoginUrl());
    exit;
}
