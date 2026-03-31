<?php
/**
 * MainController.php
 *
 * Controller for the landing page. User can make transactions here. Also it
 * shows the money amounts in table.
 *
 */
class MainController extends BaseController
{

    private $budgetCategories = ["housing",
                                 "food",
                                 "transportation",
                                 "utilities",
                                 "insurance",
                                 "medical",
                                 "saving",
                                 "debt",
                                 "entertainment",
								 "subscription",
                                 "other",
                                 "0"];

    private function postTransaction(): array
    {
        $wantsNeedsValue = $this -> getSetting('wants_needs');
        if(isset($_GET['backuperror']))
        {
            return ['error','backup-error'];
        }

        if(isset($_POST['transaction']))
        {
            $this -> checkCSRF();

            if(!isset($_POST['direction']))     
                return ['error','no-direction'];
            
            if(!in_array($_POST['direction'],['from','to'])) 
                return ['error','bad-data-for-direction'];
            
            if(!isset($_POST['amount']))    
                return ['error','no-amount'];
            
            if(!(float)$_POST['amount']) 
                return ['error','amount-no-value'];
            
            if((float)$_POST['amount'] < 0) 
                return ['error','amount-less-than-zero'];

            if(!isset($_POST['account']))   
                return['error','no-account'];
            
            if($_POST['account'] == 0) 
                return ['error','no-account-selected'];

            if($_POST['direction'] == 'from' && $_POST['budget'] == '0')
                return ['error','no-budget-selected'];
          
            $direction  = $_POST['direction'];

            if(isset($_POST['budget']) && !in_array($_POST['budget'],$this -> budgetCategories) && $direction == 'from')
                return ['error','invalid-budget-value'];

           

            $wantsNeeds = null;
            if($wantsNeedsValue == 1 && $direction == 'from'&& $_POST['budget'] != 'saving')
            {
                if(!isset($_POST['wantsneeds']))
                    return ['error','no-wants-needs']; 

                $wantsNeeds = 3;
                if($_POST['wantsneeds'] == 'want') $wantsNeeds = 1;
                if($_POST['wantsneeds'] == 'need') $wantsNeeds = 0;

                if($wantsNeeds == 3)
                    return ['error','wrong-wants-needs'];

               
            }

           
            
            $direction  = $_POST['direction'];
            $amount     = (float)$_POST['amount'];
            $accountId  = (int)$_POST['account'];
            $description= trim($_POST['description']);
            $budget     = '';
			
			if($direction == 'from') $budget = $_POST['budget'];

           
            

            $amount = ($direction === 'from')
                    ?$amount*-1
                    :$amount;

            $wantsNeeds = ($direction === 'from')
                    ?$wantsNeeds
                    :null;

            $sql = 'INSERT INTO "transaction" (transaction_amount,
                                               transaction_account_id,
                                               transaction_category,
                                               transaction_description,
                                               transaction_want_need)
                    VALUES (:amount,:account,:category,:descript,:wantneed)';
            try {
             
                $this -> pdo->beginTransaction();
                $stmt = $this -> pdo->prepare($sql);
                $stmt->execute([
                    ':amount'   => $amount,
                    ':account'  => $accountId,
                    ':category' => $budget,
                    ':descript' => $description,
                    ':wantneed' => $wantsNeeds
                ]);

                $sql = 'UPDATE amount_total
                        SET amount_total_sum = amount_total_sum + :amount,
							amount_total_last_update = CURRENT_TIMESTAMP
                        WHERE amount_total_account_id = :account';
                
                $stmt2 = $this -> pdo->prepare($sql);
                $stmt2->execute([
                    ':amount'   => $amount,
                    ':account'  => $accountId
                ]);

                $this -> pdo->commit();

                return ['success','transaction-posted'];
            }
            catch (PDOException $e) 
            {
    
                if ($this -> pdo->inTransaction()) $this -> pdo->rollBack();
                
                return ['error','database-error', $e->getMessage()];
            }
           
        }

        if(isset($_POST['move']))
        {   
            $this -> checkCSRF();

           
            if(!isset($_POST['amount']))    
                return ['error','no-amount'];
            
            if(!(int)$_POST['amount']) 
                return ['error','amount-no-value'];
            
            if((int)$_POST['amount'] < 0) 
                return ['error','amount-less-than-zero'];

            if(!isset($_POST['from']))   
                return['error','no-account'];

            if(!isset($_POST['to']))   
                return['error','no-account'];

            $accountA = explode('_',$_POST['from']);
            $accountB = explode('_',$_POST['to']);

            if($accountA[0] == 0) 
                return ['error','no-account-selected'];
			
			if($accountA[0] == $accountB[0])
				 return ['error','same-account-selected'];
			 
            $amount  = 
            $amountB = (float)$_POST['amount'];

            if($accountA[1] !== $accountB[1])
            { 
                if($_POST['amountother'] == 0) 
                    return ['error','amount-no-value'];

                if((int)$_POST['amountother'] < 0) 
                    return ['error','amount-less-than-zero'];
                
                $amountB = (float)$_POST['amountother'];

            }

            
            $accountAId = (int) $accountA[0];
            $accountBId = (int) $accountB[0];
            $description= trim($_POST['description']);
                
            $sql = 'INSERT INTO "transaction" (transaction_amount,transaction_account_id,transaction_description,transaction_category,transaction_is_move,transaction_move_group)
                    VALUES (:amount,:account,:descript,"move",1,:movegroup)';

            $sql2 = 'UPDATE amount_total
                        SET amount_total_sum = amount_total_sum + :amount
                        WHERE amount_total_account_id = :account';
                
          
            try {
               
                $this -> pdo->beginTransaction();

                $stmt = $this -> pdo->prepare($sql);

				$moveGroup = bin2hex(random_bytes(16));

                $stmt->execute([
                    ':amount'   => -$amount,
                    ':account'  => $accountAId,
                    ':descript' => $description,
					':movegroup'=> $moveGroup
                ]);
                
                $stmt->execute([
                    ':amount'   => $amountB,
                    ':account'  => $accountBId,
                    ':descript' => $description,
					':movegroup'=> $moveGroup
                ]);

                
                $stmt2 = $this -> pdo->prepare($sql2);
                $stmt2->execute([
                    ':amount'   => -$amount,
                    ':account'  => $accountAId
                ]);

                $stmt2->execute([
                    ':amount'   => $amountB,
                    ':account'  => $accountBId
                ]);


                $this -> pdo->commit();

                return ['success','transactions-posted'];
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
        $status = $this -> postTransaction();
        $handleStatus = new StatusMessage();

      
        $handleStatus -> collectStatus($status);
      
        $baseCurrency = $this -> getSetting('base_currency');
      
        if(DEBUGMODE)$this -> pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // net worth
        $sql = 'SELECT
                    a.account_currency,
                    SUM(at.amount_total_sum) AS total
                FROM   account a
                JOIN   amount_total at
                    ON at.amount_total_account_id = a.account_id
                GROUP BY a.account_currency';
        $stmt = $this -> pdo -> prepare($sql);
        
        $stmt -> execute();

        $currencyListTr = new Template('currency-list-tr');

        $currencyList = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $currencyListTr -> tagList['budgetcurrency']    = ($row['account_currency'] == $baseCurrency)?'budgetcurrency':'';
            $currencyListTr -> tagList['currency']          = $row['account_currency'];
            $currencyListTr -> tagList['amount']            = $row['total'];

            $currencyList[] = $currencyListTr -> Templating();

        }

        $baseCurrencyAmount = $row['total'] ?? 0;     

        // accounts
        $sql = 'SELECT account_id, 
                       account_name,
                       account_currency 
                FROM account
                WHERE account_disabled = 0';

        $stmt = $this -> pdo->prepare($sql);
        $stmt->execute();

        $option     = new Template('option');
        $accounts   = [];
        $accountsWithCurrency = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            
            $option -> tagList['value'] = $row['account_id'];
            $option -> tagList['text']  = $row['account_name'];

            $accounts[] = $option -> Templating();

            $option -> tagList['value'] = $row['account_id'] . '_' . $row['account_currency'];

            $accountsWithCurrency[] = $option -> Templating();
        }

        $statusMessage = '';
        $statusMessage = $handleStatus -> buildStatusMessage();
            

        $wantsNeeds = '';

        $wantsNeedsValue   = $this -> getSetting('wants_needs');
        
        if($wantsNeedsValue == 1)
        {
            $wantsNeedsForm = new Template('wants-needs-form');

            $wantsNeeds = $wantsNeedsForm -> Templating();

        }


        $templateData = [
            'accounts'          => $accounts,
            'accounts-move'     => $accountsWithCurrency,
            'amounts'           => $currencyList,
            'statusmessage'     => $statusMessage,
            'wantsneeds'        => $wantsNeeds
        ];

        $this->render('main',  $templateData);
    }
}
