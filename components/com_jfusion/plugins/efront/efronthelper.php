<?php
/**
 * file containing helper functions for eFront
 * 
 * PHP version 5
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage eFront 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2009 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * JFusion Hooks for dokuwiki
 * 
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage eFront 
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2009 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionEfrontHelper {

    function delete_directory($dir){
        if ($handle = opendir($dir)) {
            $array = array();
            while (false !== ($file = readdir($handle))){
                if ($file != "." && $file != ".."){
                    if(is_dir($dir.$file)){
                        if(!@rmdir($dir.$file)){ // Empty directory? Remove it
                            $this->delete_directory($dir.$file.'/'); // Not empty? Delete the files inside it
                        }
                    } else {
                        @unlink($dir.$file);
                    }
                }
            }
            closedir($handle);
            @rmdir($dir);
        }
    }
    function groupNameToID($user_type,$user_types_ID){
        $group_id = 0;
        if ($user_types_ID == 0){
            switch ($user_type){
                case 'professor': 
                    $group_id = 1;
                    break;
                case 'administrator': 
                    $group_id = 2;
                    break;
           }    
        } else {
            $group_id = $user_types_ID +2;
        }
        return $group_id;
    }
    function groupIdToName ($group_id){
        switch ($group_id){
           case 0: return 'student';
           case 1: return 'professor';
           case 2: return 'administrator';
           default:
                // correct id
                $group_id = $group_id - 2;
                $db = JFusionFactory::getDatabase($this->getJname());
                if (!empty($db)){ 
                    $query = 'SELECT name, basic_user_type from #__user_types WHERE id = '.$group_id;
                    $db->setQuery($query);
                    $user_type = (array)$db->loadObject();
                    return $user_type['name'].' ('.$user_type['basic_user_type'].')';
                }
        }
    }
    function getUsergroupList() {
        // efront has three build in user_types: student, professor and administrator
        // you can add additional usertypes from these,
        // but every additional usertype forks from the above basic types
        // in order to map this to the linear usergroup list of jFusion and to allow extended
        // group synchronisation the list will be build as follows (ID= internal jFusion usergroup id)
        // Id   type
        //  0   student (basic new account)
        //  1   professor
        //  2   administrator
        //  3   first record (1) user_types table
        //  4   next record in yser_types table
        //  etc
        // as there is no protection for duplicate usertype names we will display
        // the basic usertype between brackets, eg  record 3 is diaplayed as testtype (student)
        // if it has a basic type : student.
        // there is no check in the admin software of efront on duplicate usertypes (samen name/basic usertype)
        // this won't harm jFusion but can confuse admin when selection usergroups to sync.
        // should make note so duplicate groups in efront is not a jFusion bug.

        $user_types = array();
        $user_types[0]['id']='0';
        $user_types[0]['name']='student';
        $user_types[1]['id']='1';
        $user_types[1]['name']='professor';
        $user_types[2]['id']='2';
        $user_types[2]['name']='administrator';

        //get the connection to the db
        $db = JFusionFactory::getDatabase($this->getJname());
        $query = 'SELECT id, name, basic_user_type from #__user_types;';
        $db->setQuery($query);
        //getting the results
        $additional_types = $db->loadObjectList();
        // constrct the array
        $i = 3;
        foreach ($additional_types as $usertype1){
            $usertype = (array)$usertype1;
            $user_types[$i]['id'] = $usertype['id']+2; // correct id
            $user_types[$i]['name'] = $usertype['name'].' ('.$usertype['basic_user_type'].')';
            $i++;
        }
        return $user_types;
    }
/**
 * connects to api, using userbame and password
 * returns token, or empty string when not successful
 */
    
    
    function send_to_api($curl_options,$status) {
        $status = array();
        $status['debug'] = array();
        $status['error'] = array();
    	        $params = JFusionFactory::getParams($this->getJname());
        $source_url = $params->get('source_url');
        // prevent usererror by not supplying trailing backslash. 
        if (!(substr($source_url, -1) == "/")) {
            $source_url = $source_url."/";
        }    
        //prevent usererror by preventing a heading forwardslash
        ltrim($source_url);
        $apipath = $source_url.'api.php?action=';
        $post_url = $apipath.$curl_options['action'].$curl_options['parms'];
 //       $status['debug'][] = JText::_('EFRONT_API_POST')." post url: ".$post_url;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_REFERER, "");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_URL, $post_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if (!empty($curl_options['httpauth'])) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$curl_options['httpauth_username']}:{$curl_options['httpauth_password']}");

            switch ($curl_options['httpauth']) {
            case "basic":
                $curl_options['httpauth'] = CURLAUTH_BASIC;
                break;
            case "gssnegotiate":
                $curl_options['httpauth'] = CURLAUTH_GSSNEGOTIATE;
                break;
            case "digest":
                $curl_options['httpauth'] = CURLAUTH_DIGEST;
                break;
            case "ntlm":
                $curl_options['httpauth'] = CURLAUTH_NTLM;
                break;
            case "anysafe":
                $curl_options['httpauth'] = CURLAUTH_ANYSAFE;
                break;
            case "any":
            default:
                $curl_options['httpauth'] = CURLAUTH_ANY;
            }

            curl_setopt($ch, CURLOPT_HTTPAUTH, $curl_options['httpauth']);
        }
        $remotedata = curl_exec($ch);
        if (curl_error($ch)) {
            $status['error'][] = 'EFRONT_API_POST'.' '."CURL_ERROR_MSG".": ".curl_error($ch);
            curl_close($ch);
            return $status;
        }
        curl_close($ch);
        $status['result'][] = simplexml_load_string($remotedata);
        return $status;
    }
    
}    
?>