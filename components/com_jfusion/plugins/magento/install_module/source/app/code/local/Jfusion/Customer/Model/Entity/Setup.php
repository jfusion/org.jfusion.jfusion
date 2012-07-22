<?php
/**
 * Jfusion_Customer_Model_Entity_Setup class
 *
 * @category   JFusion
 * @package    Model
 * @subpackage Jfusion_Customer_Model_Entity_Setup
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Jfusion_Customer_Model_Entity_Setup extends Mage_Customer_Model_Entity_Setup{

    /**
     * @return mixed
     */
    public function getDefaultEntities(){
        $entities = parent::getDefaultEntities();
        
        $entities['customer']['attributes'] = array(
            'username' => array(
                'type'	=> 'varchar',
                'input'	=> 'text',
                'label'         => 'Username',
                'sort_order'    => 44,  
            )
        );
        
        return $entities;
    }
}