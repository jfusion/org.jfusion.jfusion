<?php
/**
 * @var JFusionDiscussBotHelper $this
 */
foreach ($this->output['buttons'] AS $name => $html) :

echo '<a id="jfusionBtn' . ucfirst($name) . $this->article->id .'" class="readon jfusionButton" target="'.$html['target'].'" href="'.$html['href'].'"';

if(isset($html['js'])) :
	foreach($html['js'] AS $func => $js) :
		echo $func.' = "'.$js.'"';
	endforeach;
endif;

//close opening a tag
echo '><span>';

echo $html['text'];

//add the number of replies to the discuss button html if set to do so
if($this->params->get('show_reply_num') && $name=='discuss') :
	$post = ($this->reply_count==1) ? 'REPLY' : 'REPLIES';
	if ($html['text']) {
		echo ' ';
	}
	echo '['.$this->reply_count.' '.JText::_($post).']';
endif;

echo '</span></a>';

endforeach;