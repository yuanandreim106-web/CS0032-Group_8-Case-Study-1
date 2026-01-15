<?php
// Simple autoloader for required libraries
// In production, use Composer for proper dependency management

// Define paths to manually downloaded libraries
define('PHPSPREADSHEET_PATH', __DIR__ . '/vendor/phpoffice/phpspreadsheet');
define('TCPDF_PATH', __DIR__ . '/vendor/tecnickcom/tcpdf');

// Simple class autoloader
spl_autoload_register(function ($class) {
    // PhpSpreadsheet classes
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet') === 0) {
        $path = str_replace('PhpOffice\\PhpSpreadsheet\\', '', $class);
        $path = str_replace('\\', '/', $path);
        $file = PHPSPREADSHEET_PATH . '/src/' . $path . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    // TCPDF class
    if ($class === 'TCPDF') {
        $file = TCPDF_PATH . '/tcpdf.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Include TCPDF configuration if it exists
if (file_exists(TCPDF_PATH . '/tcpdf_autoconfig.php')) {
    require_once TCPDF_PATH . '/tcpdf_autoconfig.php';
}
?></content>
<parameter name="filePath">c:\xampp\htdocs\Case Study 1\vendor\autoload.php