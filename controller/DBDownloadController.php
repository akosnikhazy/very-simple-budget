<?php
/**
* AccountController.php
*
* Controller for database download.
* Intended only for logged‑in users who provide the correct password.
*
*/
class DBDownloadController extends BaseController
{
    public function handle(): void
    {
        if(!isset($_POST['download'])) die();
		
		$this -> checkCSRF();
		
		$user = new User();

		if(!$user -> getLoginStatus()) die();

		if(!$this -> validatePassword($_POST['oldpw'])) die();
		
		
		header('Content-Type: application/octet-stream');
		header("Content-Transfer-Encoding: Binary"); 
		header("Content-disposition: attachment; filename=\"" . basename(DATABASE) . "\""); 
		readfile(DATABASE); 
        
    }

}
