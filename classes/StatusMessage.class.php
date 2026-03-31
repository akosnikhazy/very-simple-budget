<?php
/**
* SatusMessage.class.php
*
* Tools to build and template status messages. Both error and success message
* is possible.
*
*/
class StatusMessage {
    
	private $messages = []; 

    public function __construct()
    {
	
		
	}
	
	// $status shoud be ['error','code','optional additional string']
	public function collectStatus(array $status): void
	{
		
		if($status)
			$this -> messages[] = $status;

	}

	public function buildStatusMessage(): string
	{
		if(count($this -> messages) === 0) return '';
		
		$text = new Text('status-list');
		
		
		$statusMessageError 	= new Template('status-message');
		$statusMessageSuccess	= new Template('status-message');
		$statusMessageLine 		= new Template('li');
		
		$statusMessageError 	-> tagList['errororsuccess'] = 'errorbox';
		$statusMessageSuccess 	-> tagList['errororsuccess'] = 'successbox';
		
		$errors =
		$successes = [];

		foreach($this -> messages as $message)
		{
			
			
			$type = $message[0];
			$msg  = $message[1];
		
			$plus = '';
			if(isset($message[2]))
				$plus = $message[2];
			
			

			if($plus)
				$statusMessageLine -> tagList['item'] = $text -> GetText($msg,$plus);
			else
				$statusMessageLine -> tagList['item'] = $text -> GetText($msg);

			if('error' == $type) 
			{
				$errors[] = $statusMessageLine -> Templating();
				continue;
			} 
			
			if('database-error' == $type)
			{
			
				$errors[] = $msg;
				continue;
			}
			
			$successes[] = $statusMessageLine -> Templating();
			

		}
		
		$statusMessageError   -> tagList['list'] = $errors;
		$statusMessageSuccess -> tagList['list'] = $successes;
	
		$html = '';

		if(count($errors) > 0) 		$html .= $statusMessageError -> Templating();
		if(count($successes) > 0) 	$html .= $statusMessageSuccess -> Templating();
		

		return $html;
	}
	
}
