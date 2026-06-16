<?php
// includes/db_config.php

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'nibash';

if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/Nibash/');
}

// Create connection
$conn = mysqli_connect($host, $user, $password, $dbname);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
// If a user session exists, ensure the account isn't suspended. If suspended, destroy session and redirect.
if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (!empty($_SESSION['user_id'])) {
        $uid = intval($_SESSION['user_id']);
        $res = @mysqli_query($conn, "SELECT status FROM users WHERE id={$uid} LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $u = mysqli_fetch_assoc($res);
            if (isset($u['status']) && $u['status'] === 'suspended') {
                // Destroy session and redirect to login with message
                session_unset();
                session_destroy();
                header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . 'login.php?error=account_suspended');
                exit();
            }
        }
    }
}

// Global Language Translator Injector
if (php_sapi_name() !== 'cli' && !isset($GLOBALS['LANGUAGE_TRANSLATOR_INJECTED'])) {
    $GLOBALS['LANGUAGE_TRANSLATOR_INJECTED'] = true;
    ob_start(function($buffer) {
        $is_api = stripos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
        $is_json = false;
        foreach (headers_list() as $header) {
            if (stripos($header, 'application/json') !== false) {
                $is_json = true;
                break;
            }
        }
        
        if ($is_api || $is_json || (stripos($buffer, '<html') === false && stripos($buffer, '<body') === false)) {
            return $buffer;
        }
        
        // Read cookie for dark mode
        $is_dark = isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark';
        if ($is_dark && stripos($buffer, '<html') !== false) {
            if (preg_match('/<html[^>]*class=(["\'])(.*?)\1/i', $buffer)) {
                $buffer = preg_replace('/(<html[^>]*class=["\'])(.*?)(["\'])/i', '$1dark $2$3', $buffer);
            } else {
                $buffer = preg_replace('/<html([^>]*)>/i', '<html$1 class="dark">', $buffer);
            }
        }
        
        $translator_file = __DIR__ . '/language_translator.php';
        if (file_exists($translator_file)) {
            $translator_html = file_get_contents($translator_file);
            if (stripos($buffer, '</body>') !== false) {
                $buffer = str_ireplace('</body>', $translator_html . "\n" . '</body>', $buffer);
            } else {
                $buffer .= $translator_html;
            }
        }

        $dark_mode_file = __DIR__ . '/dark_mode.php';
        if (file_exists($dark_mode_file)) {
            $dark_html = file_get_contents($dark_mode_file);
            if (stripos($buffer, '</head>') !== false) {
                $buffer = str_ireplace('</head>', $dark_html . "\n" . '</head>', $buffer);
            } else {
                $buffer .= $dark_html;
            }
        }
        
        return $buffer;
    });
}
?>