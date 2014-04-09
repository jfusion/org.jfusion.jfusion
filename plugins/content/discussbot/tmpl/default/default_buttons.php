<?php
/**
 * @var JFusionDiscussBotHelper $this
 */
//var_dump($this->output['buttons']);
foreach ($this->output['buttons'] AS $name => $html) {
	$id = ucfirst($name);

	$extras = '';
	if(isset($html['js'])) {
		foreach($html['js'] AS $func => $js) {
			$extras .= $func . ' = "' . $js . '" ';
		}
	}

	$html =<<<HTML
	<a class="readon jfusionButton jfusionBtn{$id}" target="{$html['target']}" {$extras} href="{$html['href']}"><span>{$html['text']}</span></a>
HTML;
	echo $html;
}