<?php

/**
 * @package JFusion_mediawiki
 * @author JFusion development team
 * @copyright Copyright (C) 2008 JFusion. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC' ) or die('Restricted access' );

/**
 * JFusion Authentication Class for mediawiki 1.1.x
 * For detailed descriptions on these functions please check the model.abstractauth.php
 * @package JFusion_mediawiki
 */
class JFusionAuth_mediawiki extends JFusionAuth{

    /**
     * @param array|object $userinfo
     * @return string
     */
    function generateEncryptedPassword($userinfo)
    {
        return ':A:' . md5( $userinfo->password_clear);
    }
}
