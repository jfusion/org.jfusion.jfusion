<?php

/**
 * file containing hook function for dokuwiki
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
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
 * @subpackage DokuWiki
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class JFusionDokuWikiHook {
    /**
     * Register its handlers with the DokuWiki's event controller
     *
     * show off @method
     *
     * @param Doku_Event_Handler &$controller
     */
    function register(&$controller) {
        $controller->register_hook('ACTION_SHOW_REDIRECT', 'BEFORE', $this, '_ACTION_SHOW_REDIRECT');
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, '_ACTION_ACT_PREPROCESS');
        $controller->register_hook('DOKUWIKI_STARTED', 'BEFORE', $this, '_DOKUWIKI_STARTED');
    }

    /**
     * Register its handlers with the DokuWiki's event controller
     *
     * @param object &$event
     * @param object $param
     */
    function _ACTION_SHOW_REDIRECT(&$event, $param) {
        /*
        $mainframe = JFactory::getApplication('site');

        //var_dump($mainframe);
        //die();
        //        $mainframe = JFactory::getApplication('site');
        $my = JFactory::getUser();
        //        echo $mainframe->getClientId();

        //        var_dump($mainframe);

        //        echo $my->get('id');
        // logout any joomla users
        $mainframe->logout();
        //        die();
        // clean up session
        $session = JFactory::getSession();
        $session->close();
        */
        $event->data['id'] = str_replace(':', ';', $event->data['id']);
        $baseURL = JFusionFunction::getPluginURL(JRequest::getInt('Itemid'), false);
        if (is_array($event->data['preact'])) {
            $q = 'doku.php?id=' . $event->data['id'];
        } else {
            $q = 'doku.php?id=' . $event->data['id'] . '&do=' . $event->data['preact'];
        }
        if (substr($baseURL, -1) != '/') {
            //non-SEF mode
            $q = str_replace('?', '&', $q);
            $url = $baseURL . '&jfile=' . $q;
        } else {
            global $jname;
            $params = JFusionFactory::getParams($jname);
            $sefmode = $params->get('sefmode');
            if ($sefmode == 1) {
                $url = JFusionFunction::routeURL($q, JRequest::getInt('Itemid'));
            } else {
                //we can just append both variables
                $url = $baseURL . $q;
            }
        }
        header('Location: ' . htmlspecialchars_decode($url));
        exit();
    }

    /**
     * @param $event
     * @param $param
     */
    function _ACTION_ACT_PREPROCESS(&$event, $param) {
        ini_set("session.save_handler", "files");
    }

    /**
     * @param $event
     * @param $param
     */
    function _DOKUWIKI_STARTED(&$event, $param) {
        global $ID;
    }
}
$hook = new JFusionDokuWikiHook();
/**
 * @ignore
 * @var $EVENT_HANDLER Doku_Event_Handler
 */
$hook->register($EVENT_HANDLER);
