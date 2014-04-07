<?php namespace JFusion\Plugins\mediawiki;

// no direct access
use DateTimeZone;
use Exception;
use JDate;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Plugin\Platform\Joomla;
use JFusion\User\Userinfo;
use Joomla\String\String;
use JFusionFunction;
use JRegistry;

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

class Platform_Joomla extends Joomla
{
    /**
     * @param $config
     * @param $view
     * @param JRegistry $pluginParam
     *
     * @return string
     */
    function renderActivityModule($config, $view, $pluginParam) {
	    $output = '';
	    try {
		    $db = Factory::getDatabase($this->getJname());
		    defined('_DATE_FORMAT_LC2') or define('_DATE_FORMAT_LC2','Y M d h:i:s A');

		    // configuration
		    $display_limit_subject = $pluginParam->get('character_limit_subject');
		    $display_limit = $pluginParam->get('character_limit');
		    $result_limit = $pluginParam->get('result_limit', 0);
		    $itemid = $pluginParam->get('itemid');
		    $avatar = $pluginParam->get('avatar');
		    $avatar_height = $pluginParam->get('avatar_height');
		    $avatar_width = $pluginParam->get('avatar_width');
		    $avatar_keep_proportional = $pluginParam->get('avatar_keep_proportional', 1);
		    $avatar_software = $pluginParam->get('avatar_software');
		    $showdate = $pluginParam->get('showdate');
		    $custom_date = $pluginParam->get('custom_date');
		    $result_order = $pluginParam->get('result_order');
		    $showuser = $pluginParam->get('showuser');
		    $display_body = $pluginParam->get('display_body');

		    if ($this->params->get('new_window')) {
			    $new_window = '_blank';
		    } else {
			    $new_window = '_self';
		    }

		    $query = $db->getQuery(true)
			    ->select('p.page_id , p.page_title AS title, SUBSTRING(t.old_text,1,' . $display_limit . ') as text,
					STR_TO_DATE(r.rev_timestamp, "%Y%m%d%H%i%S") AS created,
					p.page_title AS section,
					r.rev_user_text as user,
					r.rev_user as userid')
			    ->from('#__page AS p')
		        ->innerJoin('#__revision AS r ON r.rev_page = p.page_id AND r.rev_id = p.page_latest')
			    ->innerJoin('#__text AS t on t.old_id = r.rev_text_id')
		        ->order('r.rev_timestamp DESC');

		    $db->setQuery($query, 0 , (int)$result_limit);

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
						    $userlookup = new Userinfo($this->getJname());
						    $userlookup->userid = $value->userid;

						    $PluginUser = Factory::getUser('joomla_int');
						    $userlookup = $PluginUser->lookupUser($userlookup);

						    // retrieve avatar
						    if(!empty($avatar_software) && $avatar_software != 'jfusion' && $userlookup) {
							    $o_avatar = Framework::getAltAvatar($avatar_software, $userlookup);
						    }
						    if(empty($o_avatar)) {
							    $o_avatar = JFusionFunction::getJoomlaURL() . 'components/com_jfusion/images/noavatar.png';
						    }
						    $maxheight = $avatar_height;
						    $maxwidth = $avatar_width;


						    $size = ($avatar_keep_proportional) ? Framework::getImageSize($o_avatar) : false;
						    //size the avatar to fit inside the dimensions if larger
						    if($size!==false && ($size->width > $maxwidth || $size->height > $maxheight)) {
							    $wscale = $maxwidth/$size->width;
							    $hscale = $maxheight/$size->height;
							    $scale = min($hscale, $wscale);
							    $w = floor($scale*$size->width);
							    $h = floor($scale*$size->height);
						    } elseif($size!==false) {
							    //the avatar is within the limits
							    $w = $size->width;
							    $h = $size->height;
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
					    if (!empty($o_avatar_source)) {
						    $output .= '<li style="clear:left;">';
						    $output .= '<img style="vertical-align:middle; float:left; margin:3px;" src="' . $o_avatar_source . '" height="' . $o_avatar_height . '" width="' . $o_avatar_width . '" alt="avatar" />';
					    } else {
						    $output .= '<li>';
					    }
					    $url = Framework::routeURL('index.php?title=' . $value->title, $itemid, $this->getJname());
					    if (String::strlen($value->title) > $display_limit_subject) {
						    //we need to shorten the subject
						    $value->pagename = String::substr($value->title, 0, $display_limit_subject) . '...';
					    }
					    $output .= '<a href="' . $url . '" target="' . $new_window . '">' . $value->title . '</a> - ';
					    if ($showuser) {
						    $output .= $value->user;
					    }
					    //process date info
					    if($showdate) {
						    jimport('joomla.utilities.date');
						    $JDate =  new JDate($value->created);
						    $JDate->setTimezone(new DateTimeZone(JFusionFunction::getJoomlaTimezone()));
						    if (empty($custom_date)) {
							    $output .= ' ' . $JDate->format(_DATE_FORMAT_LC2, true);
						    } else {
							    $output .= ' ' . $JDate->format($custom_date, true);
						    }
					    }
					    if($display_body) {
						    $output .= ' - ' . $value->text;
					    }
					    $output .= '</li>';
				    }
			    }
			    $output .= '</ul>';
		    }
	    } catch (Exception $e) {
			Framework::raiseError($e, $this->getJname());
	    }
        return $output;
	}
}