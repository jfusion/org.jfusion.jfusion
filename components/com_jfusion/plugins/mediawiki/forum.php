<?php
// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage MediaWiki
 * @author     JFusion Team
 * @copyright  2008 JFusion.  All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org/
**/

class JFusionForum_mediawiki extends JFusionForum
{
    /**
     * returns the name of this JFusion plugin
     *
     * @return string name of current JFusion plugin
     */
    function getJname()
    {
        return 'mediawiki';
    }

    /**
     * @param $config
     * @param $view
     * @param JParameter $pluginParam
     *
     * @return string
     */
    function renderActivityModule($config, $view, $pluginParam) {
		$db = JFusionFactory::getDatabase($this->getJname());
		$params = JFusionFactory::getParams($this->getJname());
		defined('_DATE_FORMAT_LC2') or define('_DATE_FORMAT_LC2','%A, %d %B %Y %H:%M');

        $output = '';
		// configuration
		$display_limit_subject = $pluginParam->get('character_limit_subject');
		$display_limit = $pluginParam->get('character_limit');
		$result_limit = $pluginParam->get('result_limit');
		$itemid = $pluginParam->get('itemid');
		$avatar = $pluginParam->get('avatar');
		$avatar_height = $pluginParam->get('avatar_height');
		$avatar_width = $pluginParam->get('avatar_width');
		$avatar_keep_proportional = $pluginParam->get('avatar_keep_proportional', 1);
		$avatar_software = $pluginParam->get('avatar_software');
		$showdate = $pluginParam->get('showdate');
		$custom_date = $pluginParam->get('custom_date');
		$tz_offset = $pluginParam->get('tz_offset');
		$result_order = $pluginParam->get('result_order');
		$showuser = $pluginParam->get('showuser');
		$display_body = $pluginParam->get('display_body');

		if ($params->get('new_window')) {
			$new_window = '_blank';
		} else {
		    $new_window = '_self';
		}

		$query = 'SELECT p.page_id , p.page_title AS title, SUBSTRING(t.old_text,1,'.$display_limit.') as text,
					STR_TO_DATE(r.rev_timestamp, "%Y%m%d%H%i%S") AS created,
					p.page_title AS section,
					r.rev_user_text as user
					FROM #__page AS p
					INNER JOIN #__revision AS r ON r.rev_page = p.page_id AND r.rev_id = p.page_latest
					INNER JOIN #__text AS t on t.old_id = r.rev_text_id ORDER BY r.rev_timestamp DESC LIMIT '.$result_limit;

		$db->setQuery($query);

		$results = $db->loadObjectList();
		//reorder the keys for the for loop
		if(is_array($results)) {
			if ($result_order) {
				$results = array_reverse($results);
			}
			$output .= '<ul>';
			foreach($results as $value ) {
				if (strlen($value->text)) {
					//get the avatar of the logged in user
                    $o_avatar_height = $o_avatar_width = '';
					if ($avatar) {
						// retrieve avatar
						$avatarSrc =& $avatar_software;
						if(!empty($avatarSrc) && $avatarSrc!='jfusion' && !empty($userlookup)) {
							$o_avatar = JFusionFunction::getAltAvatar($avatarSrc, $userlookup->id);
						}
						if(empty($o_avatar)) {
							$o_avatar = JFusionFunction::getJoomlaURL().'components/com_jfusion/images/noavatar.png';
						}
						$maxheight =& $avatar_height;
						$maxwidth =& $avatar_width;
						$size = ($avatar_keep_proportional) ? @getimagesize($o_avatar) : false;
						//size the avatar to fit inside the dimensions if larger
						if($size!==false && ($size[0] > $maxwidth || $size[1] > $maxheight)) {
							$wscale = $maxwidth/$size[0];
							$hscale = $maxheight/$size[1];
							$scale = min($hscale, $wscale);
							$w = floor($scale*$size[0]);
							$h = floor($scale*$size[1]);
						} elseif($size!==false) {
							//the avatar is within the limits
							$w = $size[0];
							$h = $size[1];
						} else {
							//getimagesize failed
							$w = $maxwidth;
							$h = $maxheight;
						}
						$o_avatar_source = $o_avatar;
						$o_avatar_width = $w;
						$o_avatar_height = $h;
					} else {
						$o_avatar = '';
					}
					if ( !empty( $o_avatar_source ) ) {
                        $output .= '<li style="clear:left;">';
                        $output .= '<img style="vertical-align:middle; float:left; margin:3px;" src="'.$o_avatar_source.'" height="'.$o_avatar_height.'" width="'.$o_avatar_width.'" alt="avatar" />';
					} else {
                        $output .= '<li>';
					}
					$url = JFusionFunction::routeURL('index.php?title='.$value->title, $itemid, $this->getJname());
					if (JString::strlen($value->title) > $display_limit_subject) {
						//we need to shorten the subject
						$value->pagename = JString::substr($value->title,0,$display_limit_subject) . '...';
					}
                    $output .= '<a href="'.$url.'" target="'.$new_window.'">'.$value->title.'</a> - ';
					if ($showuser) {
                        $output .= $value->user;
					}
					//process date info
					if($showdate) {
						jimport('joomla.utilities.date');
						$JDate =  new JDate($value->created);
						$JDate->setOffset($tz_offset);
						if (empty($custom_date)) {
                            $output .= ' '.$JDate->toFormat(_DATE_FORMAT_LC2,true);
						} else {
                            $output .= ' '.$JDate->toFormat($custom_date,true);
						}
					}
					if($display_body) {
                        $output .= ' - ' .$value->text;
					}
                    $output .= '</li>';
				}
			}
            $output .= '</ul>';
		}
        return $output;
	}
}