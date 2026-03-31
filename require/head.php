<?php
/**
* 
* head.php
*
* Ini
* - Defines global constants
* - Registeres autoloader
* - Connects to database
*
*/
define('INIT'               ,true);
define('AUTHFILE'           ,'auth.yzhk');
define('APPKEY'             ,'7a159d53e581bd7883e1af3d29e305fcb0a849ab3fae1ecd85edec05ec478a74'); // this is pepper for the salt, change this for your own use. If you change it you have to regenerate password too
define('DEBUGMODE'          ,false);
define('CSSCACHE'           ,false);
define('DATABASE'           ,'main.db');
define('DATABASE_BACKUP_DIR','dbbackup');
define('MAX_BACKUPS'        ,10);

function registerAutoloader(string $basePath, string $suffix = '.class.php'): void
{
    spl_autoload_register(function (string $class) use ($basePath, $suffix) {
        $file = $basePath . $class . $suffix;
        if (file_exists($file)) {
            require $file;
        }
    });
}

registerAutoloader('classes/');
registerAutoloader('controller/', '.php');


$pdo = new PDO('sqlite:' . DATABASE);

/* Check if SQLCipher is available. Never tested, feedback is appreciated */
$stmt = $pdo -> query("PRAGMA cipher_version;");

$SQLCipherIsPresent = $stmt -> fetchColumn();

if ($SQLCipherIsPresent) 
	$pdo->exec('PRAGMA key = ' . APPKEY);


/* DEBUG */
function vd($anything)
{
    if(!DEBUGMODE) return;

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($trace[1])) {
        $caller = $trace[1];
        $file   = $caller['file'] ?? 'unknown file';
        $line   = $caller['line'] ?? 'unknown line';
        echo "Called from {$file}:{$line}\n";
    } else {
        echo "No caller information available.\n";
    }
    echo '<pre>';
    var_dump($anything);
    echo '</pre>';
}

header("X-Frame-Options: DENY");
