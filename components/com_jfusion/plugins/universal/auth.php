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
 * For detailed descriptions on these functions please check the model.abstractauth.php
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
		$user_auth = $this->params->get('user_auth');

		$user_auth = rtrim(trim($user_auth),';');
    	ob_start();
		$testcrypt = eval('return '. $user_auth . ';');
		$error = ob_get_contents();
		ob_end_clean();
		if ($testcrypt===false && strlen($error)) {
			die($error);
		}
        return $testcrypt;
    }
}
