<?php
class Jfusion_Customer_Model_Entity_Setup extends Mage_Customer_Model_Entity_Setup{

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