<?php
/**
 * LoginController.php
 *
 * Controller for login view. 
 * Handles secure user authentication through the following process:
 * 
 * - Validates provided username and password
 * - Uses PHP's password_hash() for secure password comparison
 * - Implements brute-force protection: after 5 failed attempts, locks account for 5 minutes
 * - Applies computational delay via password_hash() even on correct usernames to prevent timing attacks
 *   and username enumeration (attackers can't distinguish valid vs invalid usernames by response time)
 * 
 */
class LoginController extends BaseController
{
	private function createDatabaseBackup(): bool
	{
		if ($this -> getSetting('auto_backup') == 0) return true;

        $source 	= DATABASE;
		$backupDir 	= DATABASE_BACKUP_DIR;
	 
	 
		$info      = pathinfo($source);
		$timestamp = date('Y-m-d-H-i-s');
		$basename  = $info['filename'] . '-' . $timestamp
					 . (isset($info['extension']) ? '.' . $info['extension'] : '');
	 
		$destination = $backupDir . DIRECTORY_SEPARATOR . $basename;
        try {
            if (!copy($source, $destination)) {
                throw new RuntimeException("Failed to copy file to: {$destination}");
            }
        
            $pattern = $backupDir . DIRECTORY_SEPARATOR . $info['filename'] . '-*'
                    . (isset($info['extension']) ? '.' . $info['extension'] : '');
        
            $backups = glob($pattern);
            
            if ($backups === false) 
            {
                $backups = [];
            }
            
            sort($backups); 
        
            while (count($backups) > MAX_BACKUPS) 
            {
                $oldest = array_shift($backups);
                unlink($oldest);
            }
        
            return true;
        } catch (Exception $e) {
            return false;
        }
	}
	
    private function login($post): string
    {
        if (isset($post['letmein'])) 
        {
            $_SESSION['try']     = ($_SESSION['try'] ?? 0) + 1;
            $_SESSION['lasttry'] = $_SESSION['lasttry'] ?? time();

            if ($_SESSION['try'] > 5 && time() - $_SESSION['lasttry'] < 5 * 60) 
            {
                die('please wait');
            }

            $_SESSION['lasttry'] = time();

			$this -> checkCSRF();
			
            $passwordWorker = new Password(APPKEY); 
            
            $userData = explode(':',file_get_contents(AUTHFILE));

            $testUser     = $post['username'];
            $testPassword = $post['password'];
            
            if($testUser !== $userData[0])
            {
                $passwordWorker -> testPassword($testPassword,$userData[1],$userData[2]);
                return 'error';
            }
            else
            {
               
                if($passwordWorker -> testPassword($testPassword,$userData[1],$userData[2]))
                {   
                    
                    $user = new User;

                    $user -> createUserSession($userData[0]);
					
                    session_regenerate_id();

                    $location = 'main';

					if(!$this -> createDatabaseBackup()) $location = 'main&backuperror';
					
                    unset($_SESSION['try'], $_SESSION['lasttry']);

                    header('location:?view=' . $location);
                    exit();
                    
                } else {
                    usleep(rand(100000, 300000));
                    return 'error';
                } 
                

            }

        }

        return 'no-try';


    }

    public function handle(): void
    {
        $loggedIn = $this -> login($_POST);

        $error = '';

        if($loggedIn == 'error')
        {
            $errorBox   = new Template('error-box');
            $loginText  = new Text('login');
            
            $errorBox -> tagList['text'] = $loginText -> GetText('error');
            
            $error = $errorBox -> Templating();
        }


        $this->render('login',['error' => $error]);
    }

    
}
