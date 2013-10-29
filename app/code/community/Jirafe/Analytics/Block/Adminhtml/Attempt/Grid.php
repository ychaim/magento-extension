<?php

/**
 * Adminhtml Attempt Block Grid
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Block_Adminhtml_Attempt_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('batchId');
        $this->setDefaultSort('created_dt');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Prepare collection
     *
     * @return Jirafe_Analytics_Block_Adminhtml_Attemp_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('jirafe_analytics/batch_attempt')->getCollection();
        $collection->getSelect()->join( array('b'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch')), 'main_table.batch_id = b.id', array('b.content'), array());
        $collection->getSelect()->joinLeft( array('e'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch_error')), 'main_table.id = e.batch_attempt_id', array('e.response'), array());
        $this->setCollection($collection);
        $collection->addFilterToMap('id', 'main_table.id');
        $collection->addFilterToMap('created_dt', 'main_table.created_dt');
        parent::_prepareCollection();
        return $this;
    }

    /**
     * Prepare columns
     *
     * @return Jirafe_Analytics_Block_Adminhtml_Attemp_Grid
     */
    protected function _prepareColumns()
    {
        
        $this->addColumn(
            'id',
            array(
                'header'    => Mage::helper('jirafe_analytics')->__('ID'),
                'align'     =>'left',
                'index'     => 'id',
            )
        );
        
        $this->addColumn(
            'http_code',
            array(
                'header'    => Mage::helper('jirafe_analytics')->__('HTTP CODE'),
                'align'     =>'left',
                'index'     => 'http_code',
            )
        );
        
        $this->addColumn(
            'content',
            array(
                'header'    => Mage::helper('jirafe_analytics')->__('JSON'),
                'index'     => 'content',
            )
        );
        $this->addColumn(
            'response',
            array(
                'header'    => Mage::helper('jirafe_analytics')->__('ERROR'),
                'index'     => 'response',
            )
        );
        $this->addColumn(
            'created_dt',
            array(
                'header'    => Mage::helper('jirafe_analytics')->__('CREATED'),
                'align'     => 'left',
                'width'     => '120px',
                'type'      => 'datetime',
                'index'     => 'created_dt',
                'format'    => Mage::app()->getLocale()->getDateFormat()
            )
        );
        
        return parent::_prepareColumns();
    }

}
