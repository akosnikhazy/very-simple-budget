<?php
/**
 * SettingsController.php
 *
 * Controller for the settings page.
 *
 */
class SettingsController extends BaseController
{
    

    private function postSettings(): array
    {
      
       
        
        if(isset($_POST['general']))
        {
            /* keeping the gates */
            $this -> checkCSRF();
            
            if(!isset($_POST['basecurrency']))     
                    return ['error','no-base-currency'];

            if(!isset($_POST['wantsneeds']))
                    return ['error','no-wants-needs']; 
            
            if(!isset($_POST['oldpw']))     
                return ['error','no-oldpw'];
        
            if(!$this -> validatePassword($_POST['oldpw']))
                return ['error','wrong-old-password'];

            $wantsNeeds = 3;
            if($_POST['wantsneeds'] == 'on')    $wantsNeeds = 1;
            if($_POST['wantsneeds'] == 'off')   $wantsNeeds = 0;

            if($wantsNeeds == 3)
                return ['error','wrong-wants-needs']; 

            $baseCurrency = trim(
                                strtoupper(
                                    $_POST['basecurrency']
                                )
                            );
            
            $stmt = $this->pdo->prepare('SELECT 1 
                                   FROM currency 
                                   WHERE currency_code = :code LIMIT 1');
            $stmt->execute([':code' => $baseCurrency]);

            if ($stmt->fetchColumn() === false)
                return ['error', 'no-base-currency'];

            
            
            if($this -> setSetting('base_currency',$baseCurrency) && 
                    $this -> setSetting('wants_needs',$wantsNeeds)) 
                return ['success','setting-set'];
               
        }

        if(isset($_POST['user']))
        {
            $this -> checkCSRF();
            if(!isset($_POST['username']))     
                return ['error','no-username'];

            if($_POST['username'] == '')     
                return ['error','no-username'];

            if(!isset($_POST['oldpw']))     
                return ['error','no-oldpw'];

            if($_POST['oldpw'] == '')  
                return ['error','no-oldpw'];
            
            if(!isset($_POST['newpw']))     
                return ['error','no-newpw'];

            if(!isset($_POST['newpw2']))     
                return ['error','no-newpw2'];

            if($_POST['newpw'] != $_POST['newpw2'])
                return ['error','wrong-pw-repeat'];

            if(!isset($_POST['originalname']))
                return []; //silent error for silent field

            $passwordWorker = new Password(APPKEY);

            $userData = explode(':',file_get_contents(AUTHFILE));
           
            if($userData[0] != $_POST['originalname'])
                return []; // if this happens someone is doing strange shit

            if(!$this -> validatePassword($_POST['oldpw']))
                return ['error','wrong-old-password'];

            if($_POST['newpw'] == '' && $_POST['originalname'] != $_POST['username'])
            {
                $userData[0] = $_POST['username'];

                // rehashing old pw for good measure
                $passwordData = $passwordWorker -> createPasswordHash($_POST['oldpw']);

                $userData[1] = $passwordData['hash'];
                $userData[2] = '';

                $newData = implode(':',$userData);
               
                
                $_SESSION['user_name'] = $userData[0];
                file_put_contents(AUTHFILE,$newData);

                return ['success','setting-username'];
            }
            
            if($_POST['newpw'] != '')
            { // this changes password but not username

                $passwordData = $passwordWorker -> createPasswordHash($_POST['newpw']);

                $userData[1] = $passwordData['hash'];
                $userData[2] = $passwordData['salt'];
                
                $newData = implode(':',$userData);

                file_put_contents(AUTHFILE,$newData);

                return ['success','setting-password'];
            }
            
        }

        if(isset($_POST['backuponoff']))
        {
            $this -> checkCSRF();
            if(!isset($_POST['oldpw']))     
                return ['error','no-oldpw'];

            if(!$this -> validatePassword($_POST['oldpw']))
                return ['error','wrong-old-password'];

            if(isset($_POST['backups']))
            {
                $this -> setSetting('auto_backup',1);
                return ['success','setting-auto-backup-on'];
            }
            
            $this -> setSetting('auto_backup',0);
            return ['success','setting-auto-backup-off'];
        }
        return [];
    } 

    public function handle(): void
    {
        $status = $this -> postSettings();
        $handleStatus = new StatusMessage();

      
        $handleStatus -> collectStatus($status);

        $baseCurrency = $this -> getSetting('base_currency');
        $wantsNeeds   = $this -> getSetting('wants_needs');
        $autoBackup   = $this -> getSetting('auto_backup');

        $wantsNeedsOnSelected =
        $wantsNeedsOffSelected = '';

        $autoBackupOn = '';

        if($wantsNeeds == 1)
            $wantsNeedsOnSelected = 'checked';
        else
            $wantsNeedsOffSelected = 'checked';


        if($autoBackup == 1)
            $autoBackupOn = 'checked';

        

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

        $username = $_SESSION['user_name'];

		$dbSize = '';
		
		if($fileSize = filesize(DATABASE))
		{
			$units = ['bytes', 'kB', 'MB', 'GB', 'TB'];
			
			$i = 0;

			while ($fileSize >= 1024 && $i < count($units) - 1)
			{
				$fileSize /= 1024;
				$i++;
			}

			$dbSize = round($fileSize, 2) . $units[$i];
		}

        $templateData = [
            'wantsneedsonselected'  => $wantsNeedsOnSelected,
            'wantsneedsoffselected' => $wantsNeedsOffSelected,
            'username'              => $username,
            'backupschecked'        => $autoBackupOn,
            'currencylist'          => $currencyList,
			'dbsize'				=> $dbSize,
            'statusmessage'         => $statusMessage
        ];

        $this->render('settings',  $templateData);
    }
}
