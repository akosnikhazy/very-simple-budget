<?php
/**
 * LogoutController.php
 *
 * Controller for logging out. It destroys the session and redirects to login
 * view 
 *
 */
class LogoutController extends BaseController
{
    public function handle(): void
    {
        
        session_destroy();

        header('location:?view=login');
        exit();
        
    }

}
