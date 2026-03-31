<?php
/**
* User.class.php
*
* Creates user session. Gives info about logged in user. 
*
*/
class User {
    
	private $isLoggedIn = false;
	private $userName = 'johndoe';
	
    public function __construct()
    {
		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_name('vsdebphp');
			session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Lax']);
            session_start();
        }
		
		/*
			COOKIE check would go here. But for my use case I do not 
			implement it. If cookies are allrigh
			you set the SESSION variables
			if (!empty($_COOKIE['auth_token'])) {}
		
		*/
		
		if(isset($_SESSION['in']))
		{
			
			$this -> setLoginStatus(true);
			
			if(isset($_SESSION['user_name']))
			{
				$this -> setUserName($_SESSION['user_name']);
				
			}
			
			
		}
		
	}
	
	public function createUserSession($userName): void
	{

		$_SESSION['in'] 	   = 1;
		$_SESSION['user_name'] = $userName;

	}
	
	private function setLoginStatus(bool $value): void
	{
		$this -> isLoggedIn = $value;
	}
	
	public function getLoginStatus(): bool
	{
		return $this -> isLoggedIn;
	}
	
	private function setUserName(string $value): void
	{
		$this -> userName = $value;
	}
	
	public function getUserName(): string
	{
		return $this -> userName;
	}
	
}
