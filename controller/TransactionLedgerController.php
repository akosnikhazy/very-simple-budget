<?php
/**
 * TransactionLedgerController.php
 *
 * Controller for the ledger page. Lists all transactions and transactions
 * can be deactivated here.
 * 
 */
class TransactionLedgerController extends BaseController
{
	private function deleteTransaction(): array
	{
		if (!isset($_POST['deletetransaction'])) return [];

		$this->checkCSRF();

		if (!isset($_POST['transactionid']) || !(int)$_POST['transactionid'])
			return ['error', 'no-transaction-id'];

		$transactionId = (int)$_POST['transactionid'];

		$sql = 'SELECT transaction_id,
					   transaction_amount,
					   transaction_account_id,
					   transaction_is_move,
					   transaction_move_group
				FROM "transaction"
				WHERE transaction_id = :id';

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([':id' => $transactionId]);
		$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$transaction)
			return ['error', 'transaction-not-found'];

		$toDiscard = [$transaction];

		if ($transaction['transaction_is_move'] && $transaction['transaction_move_group'])
		{
			$sql = 'SELECT transaction_id,
						   transaction_amount,
						   transaction_account_id
					FROM "transaction"
					WHERE transaction_move_group = :movegroup
					  AND transaction_id != :id';

			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				':movegroup' => $transaction['transaction_move_group'],
				':id'        => $transactionId
			]);

			$pairedLeg = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($pairedLeg) $toDiscard[] = $pairedLeg;
		}

		try {
			$this->pdo->beginTransaction();

			$discardSql = 'UPDATE "transaction"
						   SET transaction_discarded = CURRENT_TIMESTAMP
						   WHERE transaction_id = :id';

			$reverseSql = 'UPDATE amount_total
						   SET amount_total_sum = amount_total_sum - :amount,
							   amount_total_last_update = CURRENT_TIMESTAMP
						   WHERE amount_total_account_id = :account';

			$discardStmt = $this->pdo->prepare($discardSql);
			$reverseStmt = $this->pdo->prepare($reverseSql);

			foreach ($toDiscard as $leg)
			{
				$reverseStmt->execute([
					':amount'  => $leg['transaction_amount'],
					':account' => $leg['transaction_account_id']
				]);

				$discardStmt->execute([':id' => $leg['transaction_id']]);
			}

			$this->pdo->commit();

			return ['success', 'transaction-deleted'];
		}
		catch (PDOException $e)
		{
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();

			return ['error', 'database-error', $e->getMessage()];
		}
	}
		
    private function buildQuery() : array 
    {
        $baseQuery = 'SELECT a.transaction_id,
							 a.transaction_posted_at,
                             b.account_name,
                             a.transaction_amount,
                             a.transaction_category,
                             b.account_currency,
                             a.transaction_description
                      FROM "transaction" AS a JOIN "account" AS b ON a.transaction_account_id = b.account_id 
                      WHERE %s
                      ORDER BY a.transaction_posted_at %s
                      LIMIT %s';

        $where = '1';
        $order = 'DESC';
        $limit = '1000';
		$params = [];

		$potentialWhere = [];
		$potentialWhere[] = 'a.transaction_discarded IS NULL';

		if (isset($_GET['account']) && (int)$_GET['account'] !== 0)
		{
			$potentialWhere[] = 'b.account_id = :account_id';
			$params[':account_id'] = (int)$_GET['account'];
		}

		if (isset($_GET['rangefrom']) || isset($_GET['rangeto']))
		{
			$from = $this->formatDate($_GET['rangefrom'] ?? '1900-01-01');
			$to   = $this->formatDate($_GET['rangeto']   ?? '9999-12-31');

			if ($from === '') $from = '1900-01-01';
			if ($to   === '') $to   = '9999-12-31';

			$potentialWhere[] = 'transaction_posted_at BETWEEN :range_from AND :range_to';
			$params[':range_from'] = $from;
			$params[':range_to']   = $to;
		}

		if ($potentialWhere) $where = implode(' AND ', $potentialWhere);

		if (isset($_GET['order']) && $_GET['order'] === 'ASC') $order = 'ASC';

		if (isset($_GET['limit'])) $limit = min((int)($_GET['limit'] ?? 1000), 5000);

		return [sprintf($baseQuery, $where, $order, $limit), $params];
    }

  

    public function handle(): void
    {
        $transactionTr = new Template('main-transaction-tr');
        $status = $this->deleteTransaction();
		$handleStatus = new StatusMessage();
		$handleStatus->collectStatus($status);
		
		$statusMessage  = '';
		$statusMessage = $handleStatus->buildStatusMessage();
		
		
        $rangeFrom      = (new DateTime())->modify('-1 month')->format('Y-m-d');
        $rangeTo        = date('Y-m-d');
        $buttonOrder    = 'ASC';
        $buttonArrow    = '↧'; // ↧↥
        $queryString    = '';
        $numRows        = 0;
        $allRows        = 0;
		$selectedAccount= 0;
		
        $sql = 'SELECT account_id, 
                       account_name,
                       account_currency 
                FROM account';

        $stmt = $this -> pdo->prepare($sql);
        $stmt->execute();

        $option     = new Template('option');
        $accounts   = [];
        $selectOne  = isset($_GET['account']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            
            $option -> tagList['value']     = $row['account_id'];
            $option -> tagList['text']      = $row['account_name'];
            $option -> tagList['selected']  = '';

            if($selectOne){
				if($row['account_id'] == (int)$_GET['account']) 
				{
					$option -> tagList['selected']  = 'selected';
					$selectedAccount = (int)$_GET['account'];
				}
				
			}
                

            $accounts[] = $option -> Templating();

        }
        
        if (isset($_GET['rangefrom'])) 
        {
            
            $rangeFrom = $this -> formatDate($_GET['rangefrom']);
        }

        if (isset($_GET['rangeto'])) 
        {

            $rangeTo = $this -> formatDate($_GET['rangeto']);
        }

        [$sql, $params] = $this->buildQuery();
		$stmt = $this->pdo->prepare($sql);
		
		$stmt->execute($params);
		
		$result = $stmt;
        
        
        
        $transactions = [];
        while($row = $result -> fetch(PDO::FETCH_ASSOC))
        {	
	
			$transactionTr -> tagList['csrf']   	= $_SESSION['csrf'];
			$transactionTr -> tagList['id']   		= $row['transaction_id'];
            $transactionTr -> tagList['postedat']   = $row['transaction_posted_at'];
            $transactionTr -> tagList['account']    = $row['account_name'];
            $transactionTr -> tagList['amount']     = $row['transaction_amount'];
            $transactionTr -> tagList['category']   = ($row['transaction_category'] == "0")?'-':$row['transaction_category']??'-';
            $transactionTr -> tagList['currency']   = $row['account_currency'];
            
            $transactionTr -> tagList['description'] = $row['transaction_description'];

            $transactions[] = $transactionTr -> Templating();

        }
        
        $numRows = count($transactions);

        $sql = 'SELECT COUNT(*) AS "C" FROM "transaction"';

        $result = $this -> pdo -> query($sql);

        $allRows = $result -> fetch(PDO::FETCH_ASSOC);

        if(isset($_GET['order']))
        {
            switch($_GET['order'])
            {
                case 'DESC':
                    $buttonOrder = 'ASC';
                    $buttonArrow    = '↧';
                    break;
                case 'ASC':
                    $buttonOrder = 'DESC';
                    $buttonArrow    = '↥';
                    break;
            }
           
        }
        
        $queryStringParts = [];
		
        foreach($_GET as $key => $value)
        {
            if($key == 'order' || $key == 'view') continue;

            
            $queryStringParts[] = $key . '=' . $value;

        }
		
        if($queryStringParts)
        {
            $queryString = implode('&',$queryStringParts) . '&';
        }

        $templateData = [
            'querystring'   	=> $queryString,
            'statusmessage' 	=> $statusMessage,
			'selectedaccount' 	=> $selectedAccount,
            'rangefrom'     	=> $rangeFrom,
            'rangeto'       	=> $rangeTo,
            'orderarrow'    	=> $buttonArrow,
            'buttonorder'   	=> $buttonOrder,
            'accounts'      	=> $accounts,
            'numrows'       	=> $numRows,
            'allrows'       	=> $allRows,
            'transactions'  	=> $transactions
        ];

        $this->render('transaction-ledger', $templateData);
    }
}
