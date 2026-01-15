<?php

/**
 * Logout Handler
 * Uses centralized authentication from includes/auth.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Logger.php';

// Log logout event
Logger::info('User logged out');

// Perform logout
logoutUser();
