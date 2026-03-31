<?php
	$expires = 60*60*24;
	$cssFile = 'main.css';
	
	header("Pragma: public");
	header("Cache-Control: maxage=" . $expires);
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
	header("Content-type: text/css", true);

    exit(str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', str_replace(': ', ':', preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', file_get_contents($cssFile)))));
