<?php
/**
* Text.class.php
*
* Loading text from a json file in text folder. Useful to keep text out of 
* code, also good base for future multi language support. It is part of my
* old Templating framework.
* https://github.com/akosnikhazy/Small-Template-Framework
*
* Usage in the original comments.
*
*/
/***********************
	Nikházy Ákos

Text.class.php loading text from .json

Text file looks like this:
{
	"textid1":"text",
	"textid2":"text2,
	"something":"A whole paragraph",
	"formatedText":"This text will be formated you can add as many %s and %d and %e etc as you wish"

}

Usage:
$text = new Text('yourtextfile.json');

$text -> textFolder = 'example-text-folder'

// print a simple text
echo $text -> PrintText('textid2');

// print a formated text
echo $text -> PrintText('formatedText','string',3,3.3);



***********************/

class Text{
	

	public  $textFolder	= 'text';
	
	private $textFile = '';
	
	
	function __construct($_fileName) 
	{
	
		$this->textFile	= json_decode (file_get_contents($this->textFolder . '/' . $_fileName .  '.json'),true);
		
	}
	
	public function getTextArray()
	{
		return $this->textFile;
	}
	
	
	public function GetText()
	{
		$nargs = func_num_args();
		// no args => that is a real problem
		if($nargs < 1) throw new Exception('Missing args. At least one needed');
		
		$id = func_get_arg(0);

		// no such id? We just tell it to the user
		if(!array_key_exists($id,$this->textFile)) return $id;

		if($nargs > 1)
		{
			$otherArgs = func_get_args();
			array_shift($otherArgs);
			
			return sprintf($this->textFile[$id],...$otherArgs);
		}

		return $this->textFile[$id];
		
	}

}
?>
