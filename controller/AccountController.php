<?php
/**
* AccountController.php
*
* Controller for account view. Implements CRUD for accounts.
*
*/
class AccountController extends BaseController
{

    

    private function postAccount(): array
    {
        $wantsNeedsValue = $this -> getSetting('wants_needs');

        if(isset($_POST['newaccount']))
        {   
            $this -> checkCSRF();

            if(!isset($_POST['name']))     
                return ['error','no-newaccount-name'];

            if(!isset($_POST['currency']))     
               return ['error','no-newaccount-currency'];
            
            if(!isset($_POST['type']))     
                return ['error','no-newaccount-type'];

            $accountName        = trim($_POST['name']); 
            $accountCurrency    = $_POST['currency'];
            $accountType        = $_POST['type'];
            $accountDescription = trim($_POST['description']);

            try {

                $sql = 'INSERT INTO account (
                            account_name,
                            account_currency,
                            account_type,
                            account_description,
                            account_created_at
                        ) VALUES (
                            :name,
                            :currency,
                            :type,
                            :description,
                            :now
                        )';
             
                $this -> pdo->beginTransaction();

                $stmt = $this -> pdo->prepare($sql);
                $stmt->execute([
                    ':name'        => $accountName,
                    ':currency'    => $accountCurrency,
                    ':type'        => $accountType,
                    ':description' => $accountDescription,
                    ':now'         => date('Y-m-d h:i:s')
                ]);

                $accountId = $this -> pdo->lastInsertId();

                $sql = 'INSERT INTO amount_total (
                            amount_total_account_id,
                            amount_total_sum
                        ) VALUES (
                            :account_id,
                            :sum
                        );';
                
                $stmt2 = $this -> pdo->prepare($sql);
                $stmt2->execute([
                    ':account_id' => $accountId,
                    ':sum'        => 0.0,
                ]);

                $this -> pdo->commit();

                return ['success','new-account-created'];
            }
            catch (PDOException $e) 
            {
    
                if ($this -> pdo->inTransaction()) $this -> pdo->rollBack();
                
                return ['error','database-error', $e->getMessage()];
            }
           
        }

        if(isset($_POST['editaccount']))
        {
            $this -> checkCSRF();

            if(!isset($_POST['name']))     
                return ['error','no-newaccount-name'];

            if(!isset($_POST['type']))     
                return ['error','no-newaccount-type'];

            $accountId          = (int)$_POST['accountid'];
            $accountName        = trim($_POST['name']); 
            $accountType        = $_POST['type'];
            $accountDescription = trim($_POST['description']);

            try {
                $sql = 'UPDATE account SET
                                account_name = :name,
                                account_type = :type,
                                account_description = :description
                        WHERE account_id = :id';
                
                $this -> pdo->beginTransaction();
                $stmt = $this -> pdo->prepare($sql);
                $stmt->execute([
                        ':name'        => $accountName,
                        ':type'        => $accountType,
                        ':description' => $accountDescription,
                        ':id'          => $accountId
                ]);
                $this -> pdo->commit();

                return ['success','account-edited'];
            }
            catch (PDOException $e) 
            {
    
                if ($this -> pdo->inTransaction()) $this -> pdo->rollBack();
                
                return ['error','database-error', $e->getMessage()];
            }

        }

        if(isset($_POST['disable']))
        {
            $this -> checkCSRF();
            
            $accountId          = (int)$_POST['accountid'];
            
            $sql = 'SELECT account_disabled
                    FROM account
                    WHERE account_id = :id';
            
            $stmt = $this -> pdo->prepare($sql);
            $stmt->execute([':id' => $accountId]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$row)
                return ['error','no-such-account'];

            $disable = ($row['account_disabled']==1)?0:1;

            try {
                $sql = 'UPDATE account SET
                                account_disabled = :disabled
                        WHERE account_id = :id';
                
                $this -> pdo->beginTransaction();
                $stmt = $this -> pdo->prepare($sql);
                $stmt->execute([
                        ':disabled'    => $disable,
                        ':id'          => $accountId
                ]);
                $this -> pdo->commit();
                
                if($disable)
                     return ['success','account-disabled'];
                
                return ['success','account-enabled'];
               
            }
            catch (PDOException $e) 
            {
    
                if ($this -> pdo->inTransaction()) $this -> pdo->rollBack();
                
                return ['error','database-error', $e->getMessage()];
            }
        }

        return [];
    } 

    public function handle(): void
    {
        $status = $this -> postAccount();
        $handleStatus = new StatusMessage();

      
        $handleStatus -> collectStatus($status);
      
        $baseCurrency = $this -> getSetting('base_currency');
      
        if(DEBUGMODE) $this -> pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = 'SELECT account_id, 
                       account_name,
                       account_currency,
                       account_description,
                       account_type,
                       account_disabled,
					   amount_total_sum,
					   account_created_at
                FROM account as a JOIN amount_total as b ON a.account_id = b.amount_total_account_id
                ORDER BY account_disabled ASC,
                         account_name ASC';

        $stmt = $this -> pdo->prepare($sql);
        $stmt->execute();

        $accountTr = new Template('account-list-tr');

        $accounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
           
            $accountTr -> tagList['id']             = $row['account_id'];
            $accountTr -> tagList['name']           = $row['account_name'];
            $accountTr -> tagList['currency']       = $row['account_currency'];
            $accountTr -> tagList['holding']        = $row['amount_total_sum'];
            $accountTr -> tagList['description']    = $row['account_description'];
            $accountTr -> tagList['type']           = $row['account_type'];
            $accountTr -> tagList['disabled']       = ($row['account_disabled'] == 1)?'accountdisabled':'';
            $accountTr -> tagList['createdat']      = $row['account_created_at'];
            

            $accounts[] = $accountTr -> Templating();

            
        }

        $sql = 'SELECT
                    currency_code,
                    currency_name
                FROM
                    currency
                GROUP BY
                    currency_name,
                    currency_code
                ORDER BY currency_code';
        
        $stmt = $this->pdo->query($sql);

        $currencyList = [];

        $option = new Template('option');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) 
        {
            $option -> tagList['value'] = $row['currency_code'];
            $option -> tagList['text'] = sprintf('%s (%s)',$row['currency_code'],$row['currency_name']);
            $option -> tagList['selected'] = ($baseCurrency == $row['currency_code'])?'selected':'';
            
            $currencyList[] = $option -> Templating();
        }

        $statusMessage = '';
        $statusMessage = $handleStatus -> buildStatusMessage();

        $templateData = [
            'accounttr'         => $accounts,
            'currencylist'      => $currencyList,
            'statusmessage'     => $statusMessage
            
        ];

        $this->render('account',  $templateData);
    }
}
