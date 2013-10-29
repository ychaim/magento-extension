<?php

/**
 * Batch Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 * 
 * @property int $maxRecords    maximum number of records to process
 * @property int $maxAttempts   maximum attempts before failure
 * @property int $maxSize       maximum size of json object in bytes
 * 
 */

class Jirafe_Analytics_Model_Batch extends Jirafe_Analytics_Model_Abstract
{
    
    protected $_maxAttempts = null;
    
    protected $_maxRecords = null;
    
    /**
     * Class construction & resource initialization
     *
     * Load user configurable variables from Mage::getStoreConfig() into object property scope
     */
    
    protected function _construct()
    {
        $this->_init('jirafe_analytics/batch');
        
        /**
         * User configurable maximum number of attempts with error before marking record as failure
         */
        $this->maxAttempts =  intval(Mage::getStoreConfig('jirafe_analytics/curl/max_attempts'));
        
        /**
         * User configurable maximum number of records per processing
         */
        $this->maxRecords = intval(Mage::getStoreConfig('jirafe_analytics/curl/max_records'));
        
        /**
         * User configurable maximum size of json object in bytes
         */
        $this->maxSize = Mage::getStoreConfig('jirafe_analytics/curl/max_size');
    }
    
    public function getDataStores() {
        $stores =  Mage::getModel('core/store')->getCollection()
            ->addFieldToSelect(array('store_id'))
            ->getSelect()
            ->join( array('d'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/data')), "`main_table`.`store_id` = `d`.`store_id`", array())
            ->joinLeft( array('bd'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch_data')), "`d`.`id` = `bd`.`data_id`", array())
            ->where('`bd`.`batch_id` is NULL')
            ->distinct(true);
        return $stores->query();
    }
    
    public function getDataStoreTypes( $storeId = null ) {
        
        if ($storeId) {
            $types =  Mage::getModel('jirafe_analytics/data_type')
                ->getCollection()
                ->addFieldToSelect(array('type'))
                ->getSelect()
                ->join( array('d'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/data')), "`main_table`.`id` = `d`.`type_id` AND `d`.`content` is not null AND `d`.`store_id` = $storeId",array())
                ->joinLeft( array('bd'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch_data')), "`d`.`id` = `bd`.`data_id`",array())
                ->where('`bd`.`batch_id` is NULL')
                ->distinct(true);
        return $types->query();
        } else {
            return array();
        }
    }
    
    
    public function convertData() {
        $data = Mage::getModel('jirafe_analytics/data')->getCollection()
        ->addFieldToSelect(array('content','store_id'))
        ->addFieldToFilter('`main_table`.`content`', array('neq' => ''))
        ->getSelect()
        ->join( array('qt'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch_type')), "`main_table`.`type_id` = `qt`.`id` AND `qt`.`description` != 'batch'", array('qt.description as type'))
        ->order(array('main_table.created_dt ASC'));
    }
    
    
    
    /**
     * Process batch of records via cron or direct call
     * Get all records that need to be sent to Jirafe and pass to api
     * 
     * @param array $params    params for overriding default collection filters
     * @throws Exception if unable to process batch
     */
    
    public function process( $params = null ) 
    {
        try {
            
           // if ( $this->jsonType == 'batch' ) {
                $rawData = Mage::getModel('jirafe_analytics/batch')->getCollection()
                    ->addFieldToSelect(array('content','store_id'))
                    ->addFieldToFilter('`main_table`.`completed_dt`', array('is' => new Zend_Db_Expr('null')))
                    ->addFieldToFilter('`main_table`.`content`', array('neq' => ''))
                    ->getSelect()
                    ->join( array('qt'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch_type')), "`main_table`.`type_id` = `qt`.`id` AND `qt`.`description` != 'batch'", array('qt.description as type'))
                    ->order(array('main_table.created_dt ASC'));
                
                $data = $this->prepareBatches($rawData->query());
               Zend_Debug::dump($data);
                
          /*  }
            $data = $this->getCollection()
                ->addFieldToSelect(array('content','store_id'))
                ->addFieldToFilter('`main_table`.`completed_dt`', array('is' => new Zend_Db_Expr('null')))
                ->addFieldToFilter('`main_table`.`content`', array('neq' => ''))
                ->getSelect()
                ->join( array('qt'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch_type')), '`main_table`.`type_id` = `qt`.`id`', array('qt.description as type'))
                ->order(array('main_table.created_dt ASC'));
            
            if (is_numeric($this->maxRecords)) {
                $data->limit($this->maxRecords);
            }
            
            **
             * Record API attempt. 
             * Update batch with information from attempt
             *
            
            if (  $response = Mage::getModel('jirafe_analytics/curl')->sendJson( $data->query() ) ) {
                foreach ($response as $batch) {
                    foreach ($batch as $attempt) {
                        $this->updateBatch( $attempt );
                        Mage::getModel('jirafe_analytics/batch_attempt')->add( $attempt );
                    }
                }
                
                return true;
            } else {
                **
                 * No data to process
                 *
                return false;
            }*/
        } catch (Exception $e) {
            Mage::throwException('ERROR', 'Jirafe_Analytics_Model_Batch::process()', $e->getMessage());
        }
    }
    
    /**
     * Update batch with information from attempt
     *  
     * @param array $attempt    cURL attempt data
     * @return boolean
     * @throws Exception if unable to update jirafe_analytics_batch
     */
    
    public function updateBatch( $attempt = null )
    {
        try {
            if ($attempt) {
                $batch = Mage::getModel('jirafe_analytics/batch')->load( $attempt['batch_id'] );
                $attemptNum = intval($batch->getAttemptCount()+1);
                $batch->setAttemptCount($attemptNum);
                
                if ($attempt['http_code'] == '200' || $attemptNum >= $this->maxAttempts) {
                    $batch->setCompletedDt( $attempt['created_dt'] );
                    
                    if ($attempt['http_code'] == '200') {
                        $batch->setSuccess( 1 );
                    }
                }
                
                $batch->save();
                return true;
            } else {
                $this->_log( 'ERROR', 'Jirafe_Analytics_Model_Batch::updateBatch()' ,'attempt object null');
                return false;
            }
        } catch (Exception $e) {
             Mage::throwException('ERROR', 'Jirafe_Analytics_Model_Batch::updateBatch()', $e->getMessage());
        }
    }
    
    
    /**
     * Create new batch row for batch
     *
     * @return boolean
     * @throws Exception if unable to update batch
     */
    protected function _createBatchBatch( $content = null )
    {
        try {
            $batch = Mage::getModel('jirafe_analytics/batch');
            $batch->setTypeId( Jirafe_Analytics_Model_Batch_Type::BATCH );
            $batch->setCreatedDt( $this->_getCurrentDt() );
            $batch->setContent( $content );
            $batch->save();
            return $batch->getData();
    
        } catch (Exception $e) {
            Mage::throwException('BATCH ERROR: Jirafe_Analytics_ModelJirafe_Analytics_Model_Curl::_createBatchBatch(): ' . $e->getMessage());
        }
    }
    /**
     * Mark batch as batch
     *
     * @return void
     * @throws Exception if unable to update batch
     */
    protected function _updateBatchBatch( $batchId = null, $batchId = null )
    {
        try {
            $batch = Mage::getModel('jirafe_analytics/batch')->load( $batchId );
            $batch->setBatched( 1 );
            // $batch->setCompletedDt( $this->_getCurrentDt() );
            $batch->save();
    
            $batch = Mage::getModel('jirafe_analytics/batch_batch');
            $batch->setBatchId( $batchId );
            $batch->setBatchId( $batchId );
            $batch->save();
        } catch (Exception $e) {
            Mage::throwException('BATCH ERROR: Jirafe_Analytics_ModelJirafe_Analytics_Model_Curl::_updateBatchBatch(): ' . $e->getMessage());
        }
    }
    
    /**
     * Convert raw collection data into to batches of items or single items
     * based on users selection in Mage::getStoreConfig('jirafe_analytics/general/json_type');
     *
     * @return
     * @throws Exception if unable to prepare data
     */
    public function prepareBatches( $rawData = null )
    {
        try {
            if ( $rawData ) {
                $data = array();
                $batch = null;
                $batchId = Mage::getModel('jirafe_analytics/batch')->getBatchId();
                
                foreach($rawData as $item) {
                    $content = '"' . $item['type'] . '": [ ' . $item['content'] . '] ,';
                    if ( strlen($batch . $content) <= $this->jsonMaxSize ) {
                        $batch .= $content;
                        $this->_updateBatchBatch( $item['id'], $batchId );
                    } else {
                        $batchItem = $this->_createBatchBatch( '{ ' . $batch . ' }' );
                        $data[] = $batchItem;
                        $batch = $content;
                        $batchId = Mage::getModel('jirafe_analytics/batch')->getBatchId();
                    }
                    
                }
                
                $batchItem = $this->_createBatchBatch( '{ ' . $batch . ' }' );
                $data[] = $batchItem;
                return $data;
            }
            
        } catch (Exception $e) {
            Mage::throwException('ERROR: Jirafe_Analytics_Model_Batch::prepareBatches(): ' . $e->getMessage());
        }
    }
    
}