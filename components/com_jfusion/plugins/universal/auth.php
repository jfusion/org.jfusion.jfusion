<?php

/**
 * @package JFusion_universal
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Authentication Class for universal
 * For detailed descriptions on these functions please check JFusionAuth
 * @package JFusion_universal
 */
class JFusionAuth_universal extends JFusionAuth {

    /**
     * @return string
     */
    function getJname()
    {
        return 'universal';
    }

    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo)
    {
        $testcrypt = null;
        $password = $this->helper->getFieldType('PASSWORD');
        if(empty($password)) {
            throw new RuntimeException(JText::_('UNIVERSAL_NO_PASSWORD_SET'));
        } else {
            $testcrypt = $this->helper->getHashedPassword($password->fieldtype, $password->value, $userinfo);
        }
        return $testcrypt;
    }

    /**
     * used by framework to ensure a password test
     *
     * @param object $userinfo userdata object containing the userdata
     *
     * @return boolean
     */
    function checkPassword($userinfo) {
        $user_auth = $this->params->get('user_auth');

        $user_auth = rtrim(trim($user_auth),';');
        ob_start();
        $check = eval($user_auth . ';');
        $error = ob_get_contents();
        ob_end_clean();
        if ($check===false && strlen($error)) {
            die($error);
        }
        if ($check === true) {
            return true;
        } else {
            return false;
        }
    }
}
