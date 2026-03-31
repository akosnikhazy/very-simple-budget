<?php
/**
* Router.class.php
*
* Based on view name this loads the proper controller class. If you want to add
* more views, this is where you do it.
*
*/
class Router {
    
    private $view;
	
    private const CONTROLLERS = [
			'main'			            => MainController::class,
            'login'                     => LoginController::class,
            'logout'                    => LogoutController::class,
            'transaction-ledger'        => TransactionLedgerController::class,
            'transaction-ledger-export' => TransactionLedgerExportController::class,
            'transaction'               => TransactionController::class,
            'transaction-record'        => TransactionRecordController::class,
            'settings'                  => SettingsController::class,
            'account'                   => AccountController::class,
            'account-create'            => AccountCreateController::class,
            'budget'                    => BudgetController::class,
			'dbdownload'		        => DBDownloadController::class
            // 'four-oh-four'              => FourOhFourController::class
    ];
  
    public function __construct(string $requestedView)
    {
		
        $rawViewName = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $requestedView));
      
        $this->view = (array_key_exists($rawViewName,self::CONTROLLERS))
                      ?$rawViewName
                      :'main'; // :'for-oh-four'; if you implement it
      
    }

    

    public function route(): void
    {
		
        $controller = new (self::CONTROLLERS[$this->view])();
		
        $controller->handle();
    }
}
