<?php
/**
 * TransactionLedgerExportController.php
 *
 * Controller for exporting the ledger.
 *
 */
class TransactionLedgerExportController extends BaseController
{
    private function buildQuery(): array
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
                      ORDER BY a.transaction_posted_at %s';

        $where = '1';
        $order = 'DESC';
		

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

		return [sprintf($baseQuery, $where, $order), $params];
    }

    public function handle(): void
    {
        [$sql, $params] = $this->buildQuery();
		$stmt = $this->pdo->prepare($sql);
		
		$stmt->execute($params);
		
		$result = $stmt;

        $filename = 'transaction_ledger_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'Posted At',
            'Account',
            'Amount',
            'Category',
            'Currency',
            'Description',
        ],";");

        while ($row = $result->fetch(PDO::FETCH_ASSOC))
        {
           
            fputcsv($output, [
                $row['transaction_posted_at'],
                $row['account_name'],
                $row['transaction_amount'],
                ($row['transaction_category'] == '0' || $row['transaction_category'] === null) ? '-' : $row['transaction_category'],
                $row['account_currency'],
                $row['transaction_description'],
            ],";");
        }

        fclose($output);
        exit;
    }
}
