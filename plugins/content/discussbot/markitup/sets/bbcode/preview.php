<?php
 //entities must be decoded to prevent encoding already encoded entities
$text = $_POST['text'];
if(!class_exists('BBCode_Parser')) {
    $joomla_15 = '../../../../../../administrator/components/com_jfusion/models/parsers/nbbc.php';
    $joomla_16 = '../../../../../../../administrator/components/com_jfusion/models/parsers/nbbc.php';

	if (file_exists($joomla_15)) {
	    require_once $joomla_15;
	} elseif (file_exists($joomla_16)) {
	    require_once $joomla_16;
	} else {
	    die('NBBC parser not found!');
	}
}

$bbcode = new BBCode_Parser();
define('_JEXEC',1);

$url = (!empty($_SERVER['HTTPS'])) ? "https://".$_SERVER['SERVER_NAME'] : "http://".$_SERVER['SERVER_NAME'];
if($_SERVER['SERVER_PORT']!='80') $url .= ':'.$_SERVER['SERVER_PORT'];
$url .= '/components/com_jfusion/images/smileys';

$bbcode->SetSmileyURL($url);
$text = $bbcode->Parse($text);

echo $text;