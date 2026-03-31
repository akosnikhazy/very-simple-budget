<?php
/**
* BudgetController.php
*
* Controller for budget view. Implements CRUD for budgets. Budgets are monthly.
*
*/
class BudgetController extends BaseController
{
	
	private $categories = [
			'housing',
			'food',
			'transportation',
			'utilities',
			'insurance',
			'medical',
			'saving',
			'debt',
			'entertainment',
			'subscription',
			'other',
		];
	
   private function percentage(float $spent, float $budget,int $decimal = 2): float
   {
		return ($budget != 0) ? round(($spent / $budget) * 100, $decimal) : 0.0;
   }
   
   private function postBudget(): array
    {
        if(isset($_POST['makebudget']))
        {
            $this -> checkCSRF();
            
            $requiredFields = [
                'budgethousing',
                'budgetfood',
                'budgettransportation',
                'budgetutilities',
                'budgetinsurance',
                'budgetmedical',
                'budgetsaving',
                'budgetdebt',
                'budgetentertainment',
				'budgetsubscription',
                'budgetother'
            ];

            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field])) {
                   
                    return ['error', 'no-' . $field];
                }
            }

            try {
                $this -> pdo->beginTransaction();

                $sql = 'UPDATE budget
                        SET    budget_active = 0
                        WHERE  budget_active = 1';

                $this -> pdo -> exec($sql);

                $sql = '
                    INSERT INTO budget (
                        budget_name,
                        budget_created_at,
                        budget_housing,
                        budget_food,
                        budget_transportation,
                        budget_utilities,
                        budget_insurance,
                        budget_medical,
                        budget_saving,
                        budget_debt,
                        budget_entertainment,
						budget_subscription,
                        budget_other
                    ) VALUES (
                        :budget_name,
                        datetime(\'now\'),
                        :budget_housing,
                        :budget_food,
                        :budget_transportation,
                        :budget_utilities,
                        :budget_insurance,
                        :budget_medical,
                        :budget_saving,
                        :budget_debt,
                        :budget_entertainment,
						:budget_subscription,
                        :budget_other
                    )
                ';

                $stmt = $this -> pdo->prepare($sql);

                $stmt->execute([
                    ':budget_name'            => $_POST['budgetname'],
                    ':budget_housing'         => (int)$_POST['budgethousing'],
                    ':budget_food'            => (int)$_POST['budgetfood'],
                    ':budget_transportation'  => (int)$_POST['budgettransportation'],
                    ':budget_utilities'       => (int)$_POST['budgetutilities'],
                    ':budget_insurance'       => (int)$_POST['budgetinsurance'],
                    ':budget_medical'         => (int)$_POST['budgetmedical'],
                    ':budget_saving'          => (int)$_POST['budgetsaving'],
                    ':budget_debt'            => (int)$_POST['budgetdebt'],
                    ':budget_entertainment'   => (int)$_POST['budgetentertainment'],
                    ':budget_subscription'    => (int)$_POST['budgetsubscription'],
                    ':budget_other'           => (int)$_POST['budgetother']
                ]);
                                
                
                $this -> pdo->commit();
                return ['success','budget-posted'];
            } catch (PDOException $e) {
                if ($this -> pdo->inTransaction()) $this -> pdo->rollBack();
               
                return ['error','database-error', $e->getMessage()];
            }

        }

        return [];
    } 

    public function handle(): void
    {
        $status = $this -> postBudget();
        $handleStatus = new StatusMessage();

       
        $handleStatus -> collectStatus($status);
        
        $baseCurrency = $this -> getSetting('base_currency');
      
        $wantsAndNeedsOption = $this -> getSetting('wants_needs');

        $selecteddate  = date('Y-m-d');
        
        if(isset($_GET['month']))
        {
            $selecteddate = $this -> formatDate($_GET['month']);
        }

        

        $stmt = $this -> pdo->prepare(
            'SELECT
                budget_name,
                budget_created_at,
                budget_housing,
                budget_food,
                budget_transportation,
                budget_utilities,
                budget_insurance,
                budget_medical,
                budget_saving,
                budget_debt,
                budget_entertainment,
				budget_subscription,
                budget_other,
                budget_active
            FROM budget
            WHERE budget_active = 1
            ORDER BY budget_created_at DESC
            LIMIT 1'
        );

        $stmt->execute();

        $row = $stmt->fetch();

        $sql = 'SELECT
                    COALESCE(SUM(CASE WHEN transaction_category = "housing"        THEN transaction_amount END )*-1, 0) AS spent_housing,
                    COALESCE(SUM(CASE WHEN transaction_category = "food"           THEN transaction_amount END )*-1, 0) AS spent_food,
                    COALESCE(SUM(CASE WHEN transaction_category = "transportation" THEN transaction_amount END )*-1, 0) AS spent_transportation,
                    COALESCE(SUM(CASE WHEN transaction_category = "utilities"      THEN transaction_amount END )*-1, 0) AS spent_utilities,
                    COALESCE(SUM(CASE WHEN transaction_category = "insurance"      THEN transaction_amount END )*-1, 0) AS spent_insurance,
                    COALESCE(SUM(CASE WHEN transaction_category = "medical"        THEN transaction_amount END )*-1, 0) AS spent_medical,
                    COALESCE(SUM(CASE WHEN transaction_category = "saving"         THEN transaction_amount END )*-1, 0) AS spent_saving,
                    COALESCE(SUM(CASE WHEN transaction_category = "debt"           THEN transaction_amount END )*-1, 0) AS spent_debt,
                    COALESCE(SUM(CASE WHEN transaction_category = "entertainment"  THEN transaction_amount END )*-1, 0) AS spent_entertainment,
					COALESCE(SUM(CASE WHEN transaction_category = "subscription"   THEN transaction_amount END )*-1, 0) AS spent_subscription,
                    COALESCE(SUM(CASE WHEN transaction_category = "other"          THEN transaction_amount END )*-1, 0) AS spent_other
                FROM "transaction" AS t JOIN account AS a ON t.transaction_account_id = a.account_id
                WHERE
                    transaction_is_openening_balance = 0
					AND transaction_amount < 0
                    AND strftime("%Y-%m", transaction_posted_at) = :month
                    AND a.account_currency = :ac';

        $stmt = $this -> pdo->prepare($sql);
        $stmt->execute([':month' => date('Y-m', strtotime($selecteddate)),
                        ':ac'    => $baseCurrency 
                       ]);
        
        $spendingRow  = $stmt->fetch(PDO::FETCH_ASSOC);
   
        $spending = array_sum($spendingRow);
     
		$sql = 'SELECT
					COALESCE(SUM(transaction_amount) * -1, 0) AS today_spending
				FROM "transaction" AS t
				JOIN account AS a ON t.transaction_account_id = a.account_id
				WHERE
					transaction_is_openening_balance = 0
					AND transaction_amount < 0
					AND date(transaction_posted_at) = :today
					AND a.account_currency = :ac';

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			':today' => date('Y-m-d', strtotime($selecteddate)),
			':ac'    => $baseCurrency
		]);

		$todaySpendingRow = $stmt->fetch(PDO::FETCH_ASSOC);
		$todaySpending = $todaySpendingRow['today_spending'] ?? 0;
			
		$sql = 'SELECT
					COALESCE(SUM(CASE WHEN transaction_category = "food"          THEN transaction_amount END) * -1, 0) AS today_food,
					COALESCE(SUM(CASE WHEN transaction_category = "entertainment" THEN transaction_amount END) * -1, 0) AS today_entertainment
				FROM "transaction" AS t
				JOIN account AS a ON t.transaction_account_id = a.account_id
				WHERE
					transaction_is_openening_balance = 0
					AND transaction_amount < 0
					AND date(transaction_posted_at) = :today
					AND a.account_currency = :ac';

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			':today' => date('Y-m-d', strtotime($selecteddate)),
			':ac'    => $baseCurrency
		]);

		$todayRow = $stmt->fetch(PDO::FETCH_ASSOC);	
			
        $income = 0;

        $sql = 'SELECT
                    a.account_currency,
                    SUM(t.transaction_amount) AS total
                FROM
                    "transaction" AS t
                JOIN
                    "account" AS a
                    ON t.transaction_account_id = a.account_id
                WHERE
                    t.transaction_amount > 0                   
                    AND t.transaction_is_move = 0
                    AND strftime("%Y-%m", t.transaction_posted_at) = :month
                    AND a.account_currency = :ac
                GROUP BY
                    a.account_currency;';
                    
        $stmt = $this -> pdo->prepare($sql);
        $stmt->execute([':month' => date('Y-m', strtotime($selecteddate)),
                        ':ac'    => $baseCurrency
                        ]);

        $incomeRow = $stmt->fetch();
        
        $income = $incomeRow['total'] ?? 0;
        

        $sql = 'SELECT 
                    SUM(CASE WHEN transaction_want_need = 1 THEN transaction_amount ELSE 0 END) AS total_wants,
                    SUM(CASE WHEN transaction_want_need = 0 THEN transaction_amount ELSE 0 END) AS total_needs
                FROM "transaction"
                JOIN
                    "account" AS a
                    ON transaction_account_id = a.account_id
                WHERE transaction_want_need IS NOT NULL
                      AND strftime("%Y-%m", transaction_posted_at) = :month
                      AND a.account_currency = :ac';

        $stmt = $this -> pdo->prepare($sql);
        $stmt->execute([':month' => date('Y-m', strtotime($selecteddate)),
                        ':ac'    => $baseCurrency
                        ]);
                        
        $wantsNeedsRow = $stmt -> fetch();               
        $wantsAndNeeds = '';

        if($wantsAndNeedsOption == 1)
        {
            $wantsAndNeedsHTML = new Template('budget-wantsneeds');
            
            $wants = $wantsNeedsRow['total_wants'] * -1;
            $needs = $wantsNeedsRow['total_needs'] * -1;
            $totalWantsNeeds = $wants + $needs;

            $wantsAndNeedsHTML -> tagList['wvalue'] = $wants;
            $wantsAndNeedsHTML -> tagList['nvalue'] = $needs;
            
            
            $wantsAndNeedsHTML -> tagList['basecurrency'] = $baseCurrency;

            $wantsperc = $this->percentage((float)$wants,(float)$totalWantsNeeds);

            $wantsAndNeedsHTML -> tagList['wpercent'] = $wantsperc;
            $wantsAndNeedsHTML -> tagList['npercent'] = 100 - $wantsperc;
            
            $wantsAndNeeds = $wantsAndNeedsHTML -> Templating();
        }


        $budget   = [];
		$spent    = [];
		$diff     = [];
		$perc     = [];
		
		
		foreach ($this -> categories as $cat) 
		{
			$budget[$cat] = $row["budget_{$cat}"]??0;
			$spent[$cat]  = $spendingRow["spent_{$cat}"];
			$diff[$cat]   = $budget[$cat] - $spent[$cat];
			$perc[$cat]   = $this->percentage((float)$spent[$cat], (float)$budget[$cat]);
		}
		
		$daysInMonth = (int) date('t', strtotime($selecteddate));
		$totalBudget = array_sum($budget);
		$todayBudget =  floor(($totalBudget / $daysInMonth) * 100) / 100;

		
		$todayBudgetFood          = floor(($budget['food']          / $daysInMonth) * 100) / 100;
		$todayBudgetEntertainment = floor(($budget['entertainment'] / $daysInMonth) * 100) / 100;

        $statusMessage = '';
        $statusMessage = $handleStatus -> buildStatusMessage();
        
        $templateData = [
            'statusmessage'        => $statusMessage,
            'selecteddate'         => $selecteddate,
            'income'               => $income,
            'spending'             => $spending,
            'balance'              => $income - $spending,
			'percbalance'		   => $this -> percentage($spending,$income),
			'wantsandneeds'        => $wantsAndNeeds,

            'budgethousing'        => $budget['housing'],
			'spenthousing'         => $spent['housing'],
			'difhousing'           => $diff['housing'],
			'perchousing'          => $perc['housing'],

			'budgetfood'           => $budget['food'],
			'spentfood'            => $spent['food'],
			'diffood'              => $diff['food'],
			'percfood'             => $perc['food'],

			'budgettransportation' => $budget['transportation'],
			'spenttransportation'  => $spent['transportation'],
			'diftransportation'    => $diff['transportation'],
			'perctransportation'   => $perc['transportation'],

			'budgetutilities'      => $budget['utilities'],
			'spentutilities'       => $spent['utilities'],
			'difutilities'         => $diff['utilities'],
			'percutilities'        => $perc['utilities'],

			'budgetinsurance'      => $budget['insurance'],
			'spentinsurance'       => $spent['insurance'],
			'difinsurance'         => $diff['insurance'],
			'percinsurance'        => $perc['insurance'],

			'budgetmedical'        => $budget['medical'],
			'spentmedical'         => $spent['medical'],
			'difmedical'           => $diff['medical'],
			'percmedical'          => $perc['medical'],

			'budgetsaving'         => $budget['saving'],
			'spentsaving'          => $spent['saving'],
			'difsaving'            => $diff['saving'],
			'percsaving'           => $perc['saving'],

			'budgetdebt'           => $budget['debt'],
			'spentdebt'            => $spent['debt'],
			'difdebt'              => $diff['debt'],
			'percdebt'             => $perc['debt'],

			'budgetentertainment'  => $budget['entertainment'],
			'spententertainment'   => $spent['entertainment'],
			'difentertainment'     => $diff['entertainment'],
			'percentertainment'    => $perc['entertainment'],
			
			'budgetsubscription'   => $budget['subscription'],
			'spentsubscription'    => $spent['subscription'],
			'difsubscription'      => $diff['subscription'],
			'percsubscription'     => $perc['subscription'],

			'budgetother'          => $budget['other'],
			'spentother'           => $spent['other'],
			'difother'             => $diff['other'],
			'percother'            => $perc['other'],
        
			'todaybudget'   	   => $todayBudget,
			'todayspending' 	   => $todaySpending,
			
			'todaybudgetfood'              => $todayBudgetFood,
			'todayspendingfood'            => $todayRow['today_food'],

			'todaybudgetentertainment'     => $todayBudgetEntertainment,
			'todayspendingentertainment'   => $todayRow['today_entertainment'],
		];

        $this->render('budget', $templateData);
    }
}
