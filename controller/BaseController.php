<?php
/**
* BaseController.php
*
* Abstract controller. Implements global helper functionality for other
* controllers. Also ther render method templates globally needed template
* tags.
*
*/
abstract class BaseController
{

    public $pdo = null;
    private string $cspNonce = '';
	private $text = null;
    function __construct() 
	{
        global $pdo;
		
        $this -> pdo = $pdo;
    	$this -> text = new Text('page-titles');


       
        $user = new User();
        if (!$user->getLoginStatus() && ($_GET['view'] ?? '') !== 'login') {
            header('location:?view=login');
            die();
        }
    }
    public function formatDate($string):string
    {
        $timestamp = strtotime($string);
        
        return $timestamp !== false ? date('Y-m-d', $timestamp) : '';
        
    }
    private function buildSiteHeader(string $title = null):string
    {
        $siteHeader = new Template('site-header');

        $siteHeader -> tagList['title']     = $this -> text -> GetText($title);
		
        $siteHeader -> tagList['nocache']   = CSSCACHE ? '' : '?'.time();
        
        return $siteHeader -> Templating();
    }


    private function buildMenu(string $selected = null):string
    {
        $menuTemplate = new Template('menu');

        $menuTemplate -> tagList['selectedmain']    =
        $menuTemplate -> tagList['selectedtransaction-ledger']  =
        $menuTemplate -> tagList['selectedsettings'] =
  		$menuTemplate -> tagList['selectedbudget'] = '';
		
        if($selected)
            $menuTemplate -> tagList['selected' . $selected] = 'activemenu';


        return $menuTemplate -> Templating();
    }

    private function buildHiddenViewInput(string $view)
    {

        $inputTemplate = new Template('hidden-view-input');

        $inputTemplate -> tagList['view'] = $view;

        return $inputTemplate -> Templating();
    }

	private function buildCSRFField():string
	{
		if(!isset($_SESSION['csrf'])) 
		{
			$CSRFToken = $_SESSION['csrf'] = bin2hex(random_bytes(32));
		} 
		else 
		{
			$CSRFToken = $_SESSION['csrf'];
		}
		
		$inputTemplate = new Template('hidden-csrf-input');

		$inputTemplate -> tagList['csrf'] = $CSRFToken;

        return $inputTemplate -> Templating();
		
	}
	
    public function getSetting(string $settingCode,string $defaultValue = ''):string
    {
        $sql = 'SELECT
                    setting_code,
                    COALESCE(setting_value, setting_default_value) AS val
                FROM
                    setting
                WHERE
                    setting_code = :settingcode';

        $stmt = $this -> pdo->prepare($sql);
        
        $stmt->execute([
                ':settingcode'   => $settingCode
        ]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        

        return $row['val']??$defaultValue;

    }

    public function setSetting(string $settingCode,string $settingValue):array
    {
        $sql = 'UPDATE setting
                SET setting_value = :sval
                WHERE setting_code = :scode';
        try {
             
            $this -> pdo -> beginTransaction();

            $stmt = $this -> pdo -> prepare($sql); 

            $stmt->execute([
                ':sval'   => $settingValue,
                ':scode'  => $settingCode
            ]);
            $this -> pdo->commit();
            return ['success','setting-set'];
        }    
        catch (PDOException $e) 
        {

            if ($this -> pdo->inTransaction()) $this -> pdo->rollBack();
            
            return ['error','database-error', $e->getMessage()];
        }    
    }
	
	public function checkCSRF():void
	{	
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		    $csrf = $_POST['csrftoken'] ?? '';
		    if (!hash_equals($_SESSION['csrf'], $csrf)) {
		        http_response_code(403);
		        die('Invalid request');
		    }
		}
	}
	public function validatePassword(string $password)
    {
        $passwordWorker = new Password(APPKEY);
        $userData = explode(':',file_get_contents(AUTHFILE));

        if(!$passwordWorker -> testPassword($password,$userData[1],$userData[2])) 
            return false;
        
        return true;

    }

	private function setSecurityHeaders(): void
    {
        $nonce = $this->generateCspNonce();
        
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Content-Security-Policy: ' . implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'"
        ]));
    }

    private function generateCspNonce(): string
    {
      
        $this -> cspNonce = base64_encode(random_bytes(16));
        return $this -> cspNonce;
    }

    protected function render(string $viewName, array $data = []): void
    {	
		$this->setSecurityHeaders();
        
        
        $template = new Template($viewName);
        
        $template -> tagList = $data;
       
        // these are {{tags}} the controllers' templates can/should all have
            
            // From doctype to <body> tag
            $template -> tagList['siteheader'] = $this -> buildSiteHeader($viewName);

            // A 🍔 menu
            $template -> tagList['menu']  = $this -> buildMenu($viewName);

            // This will make a hidden input named "view" with the name of the view for form method get
            $template -> tagList['thispageview'] = $this -> buildHiddenViewInput($viewName);

		   // This will make a hidden csrf input
           $template -> tagList['csrf'] = $this -> buildCSRFField();

           // Script nonce
           $template -> tagList['cspnonce'] = $this -> cspNonce;
        exit(
            $template->Templating(true,true) // this makes the whole thing one liner
        );
    }
}
