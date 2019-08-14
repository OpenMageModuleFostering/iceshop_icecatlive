<?php
/**
 * Class provides category page with images, cron processing
 *
 *
 */
class Iceshop_Icecatlive_Model_Observer
{
    /**
     * Our process ID.
     */
    private $connection;


    protected $_connectorDir = '/iceshop/icecatlive/';
    public $_connectorCacheDir = '/iceshop/icecatlive/cache/';
    protected $_productFile;
    protected $_supplierFile;
    protected $_heap = array();
    protected $_current_id = null;
    protected $_cron_full_icecat_counter = 0;

    protected function _construct()
    {
        $this->_init('icecatlive/observer');
    }

    public function loadProductInfoIntoCache($import_id = 0, $crone=0,$real_time=0){

      try{
        $start_import_time = microtime(true);
        $product_onlynewproducts = Mage::getStoreConfig('icecat_root/icecat/product_onlynewproducts');
        $import_info = array();

        $DB_loger = Mage::helper('icecatlive/db');
        $file_logger = Mage::helper('icecatlive/log');

        $file_logger->openLogFile();

        $import_info['process_hash'] = $DB_loger->getLogValue('icecatlive_process_hash');
        $import_info['process_hash_time'] = $DB_loger->getLogValue('icecatlive_process_hash_time');
        $import_time_product = $DB_loger->getLogValue('import_time_one_product');
        $import_info['current_product'] = $DB_loger->getLogValue('icecatlive_current_product_temp');
        $import_info['count_products'] = $DB_loger->getLogValue('icecatlive_count_products_temp');

        if(empty($import_info['process_hash_time'])){
          $import_info['process_hash_time'] = microtime(true);
          $DB_loger->insetrtUpdateLogValue('icecatlive_process_hash_time', $import_info['process_hash_time']);
        }

        if(!$real_time && $crone == 0){
        $process_running = Mage::getConfig()->getNode('default/icecatlive/icecatlive_process_running')->icecatlive_error_text;
        if(empty($import_info['process_hash'])){
            $import_info['process_hash'] = md5($import_info['process_hash_time']);
            $DB_loger->insetrtUpdateLogValue('icecatlive_process_hash', $import_info['process_hash']);
        } else {
            $time = microtime(true) - $import_info['process_hash_time'];
            $time = (int)(round($time)*1000)/1000;

            if($_GET['process_hash'] != $import_info['process_hash'] && $time<300 && $time>30){
              if($file_logger->fileExists() && $file_logger->lastUpdate() < 10) {
                  $import_info['done'] = 1;
                  if ((300 - $time) < 60) {
                      $import_info['error'] = $process_running . '<h4>Time wait: ' . (300 - $time) . ' second</h4>';
                  } elseif ((300 - $time) > 60) {
                      $import_info['error'] = $process_running . '<h4>Time wait: ' . round((300 - $time) / 60, 0) . ' minute</h4>';
                  }
                  $import_info['count_products'] = 0;
                  $import_info['current_product'] = 0;
                  echo json_encode($import_info);
                  die();
              }else if($file_logger->fileExists() && $file_logger->lastUpdate() > 10) {
                  echo json_encode($import_info);
                  die();
              }
            } elseif($_GET['process_hash'] != $import_info['process_hash'] && $time<300){
                if($file_logger->fileExists() && $file_logger->lastUpdate() < 10) {
                    if (!empty($import_time_product) && !empty($import_info['current_product']) && !empty($import_info['count_products'])) {
                        $time_last = $import_time_product * ($import_info['count_products'] - $import_info['current_product']);
                    }
                    $import_info['done'] = 1;

                    if (!empty($time_last)) {
                        if ($time_last < 60) {
                            $import_info['error'] = $process_running . '<h4>Time wait: ' . $time_last . ' second</h4>';
                        } else {
                            $import_info['error'] = $process_running . '<h4>Time wait: ' . round($time_last / 60, 0) . ' minute</h4>';
                        }
                    } else {
                        $import_info['error'] = $process_running;
                    }
                    $import_info['count_products'] = 0;
                    $import_info['current_product'] = 0;
                    echo json_encode($import_info);
                    die();
                }else if($file_logger->fileExists() && $file_logger->lastUpdate() > 10) {
                    echo json_encode($import_info);
                    die();
                }
            } else {
                //file_put_contents('D://file.txt', "Time passed ". $time. " sec.\nLast update: ". $file_logger->lastUpdate(). " sec.");
                $DB_loger->insetrtUpdateLogValue('icecatlive_process_hash_time', microtime(true));
            }
        }
        }

        if(!$import_id){
            if($_GET['full_import'] == 1){
                $DB_loger->deleteLogKey('icecatlive_full_icecat_product_temp');
                $DB_loger->deleteLogKey('icecatlive_current_product_temp');
                $DB_loger->deleteLogKey('icecatlive_error_imported_product_temp');
                $DB_loger->deleteLogKey('icecatlive_success_imported_product_temp');
                $DB_loger->deleteLogKey('icecatlive_count_products_temp');
                $DB_loger->deleteLogKey('import_icecat_server_error_message');
            }


            if(empty($import_info['count_products'])){
                if($_GET['update'] != 1 && !$import_id){
                    $DB_loger->deleteLogKey('icecatlive_enddate_imported_product');
                    $DB_loger->deleteLogKey('icecatlive_startdate_update_product');
                    $DB_loger->deleteLogKey('icecatlive_enddate_update_product');
                    $DB_loger->insetrtUpdateLogValue('icecatlive_startdate_imported_product', date('Y-m-d H:i:s'));
                } elseif (!$import_id) {
                    $DB_loger->deleteLogKey('icecatlive_enddate_update_product');
                    $DB_loger->insetrtUpdateLogValue('icecatlive_startdate_update_product', date('Y-m-d H:i:s'));
                }
            }
            $import_info['success_imported_product'] = $DB_loger->getLogValue('icecatlive_success_imported_product_temp');
            $import_info['error_imported_product'] = $DB_loger->getLogValue('icecatlive_error_imported_product_temp');
            $import_info['full_icecat_product'] = $DB_loger->getLogValue('icecatlive_full_icecat_product_temp');
            if(empty($import_info['current_product'])){
                $import_info['current_product'] = 1;
                $DB_loger->insetrtUpdateLogValue('icecatlive_current_product_temp', $import_info['current_product']);
            }else{
                if($_GET['error_import'] != 1){
                    $import_info['current_product'] = $import_info['current_product'] + 1;
                }
                    $DB_loger->insetrtUpdateLogValue('icecatlive_current_product_temp', $import_info['current_product']);
            }
        }

        $db_res = Mage::getSingleton('core/resource')->getConnection('core_write');
        $catalog_product_model = Mage::getModel('catalog/product');
        $userName = Mage::getStoreConfig('icecat_root/icecat/login');
        $userPass = Mage::getStoreConfig('icecat_root/icecat/password');
        $locale = Mage::getStoreConfig('icecat_root/icecat/language');
        if ($locale == '0') {
            $systemLocale = explode("_", Mage::app()->getLocale()->getLocaleCode());
            $locale = $systemLocale[0];
        }
        $tablePrefix = '';
        $tPrefix = (array)Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
            $tablePrefix = $tPrefix[0];
        }

        if(!$import_id){
          if(empty($import_info['count_products'])){
              if($_GET['update'] != 1){
                  if(!$product_onlynewproducts){
                      $query = "SELECT COUNT(*) FROM `" . $tablePrefix . "catalog_product_entity`";
                  } else {
                      $query = "SELECT COUNT(*) FROM `" . $tablePrefix . "catalog_product_entity` AS cpe
                                                LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_noimport_products_id` AS iinp
                                                       ON cpe.`entity_id` = iinp.`prod_id`
                                            WHERE iinp.`prod_id` IS NULL;";
                  }
                  $count_products = $db_res->fetchRow($query);
                  $import_info['count_products'] = $count_products['COUNT(*)'];
                  $DB_loger->insetrtUpdateLogValue('icecatlive_count_products_temp', $import_info['count_products']);

              }else{


                  if(!$product_onlynewproducts){
                      $query = "SELECT COUNT(*) FROM `" . $tablePrefix . "catalog_product_entity` LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_products_titles` ON entity_id = prod_id WHERE prod_id IS NULL";
                  } else {
                      $query = "SELECT COUNT(*) FROM `" . $tablePrefix . "catalog_product_entity`  AS cpe
                                      LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_products_titles`  AS iipt
                                              ON cpe.`entity_id`= iipt.`prod_id`
                                      LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_noimport_products_id` AS iinpi
                                              ON iinpi.`prod_id` = cpe.`entity_id`
                                WHERE iipt.`prod_id` IS NULL AND iinpi.`prod_id` IS NULL;";
                  }
                  $count_products = $db_res->fetchRow($query);
                  $import_info['count_products'] = $count_products['COUNT(*)'];
                  $DB_loger->insetrtUpdateLogValue('icecatlive_count_products_temp', $import_info['count_products']);
              }
          }

          if($_GET['update'] != 1){
                $prev_current_product = $import_info['current_product'] - 1;
             if(!$product_onlynewproducts){
                  $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity` LIMIT ". $prev_current_product .", 1";
              } else {
                  $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity` AS cpe
                                    LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_noimport_products_id` AS iinpi
                                                  ON cpe.`entity_id` = iinpi.`prod_id`
                            WHERE iinpi.`prod_id` IS NULL LIMIT ". $prev_current_product .", 1";
              }
              $entity_id = $db_res->fetchRow($query);
              $entity_id = $entity_id['entity_id'];
          }else{
              $prev_current_product = $import_info['current_product'] - 1;
              if(!$product_onlynewproducts){
                  $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity` LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_products_titles` ON entity_id = prod_id WHERE prod_id IS NULL LIMIT ". $prev_current_product .", 1";
              } else {
                  $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity` AS cpe
                                          LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_products_titles` AS iipt
                                                 ON cpe.`entity_id` = iipt.`prod_id`
                                          LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_noimport_products_id` AS iinpi
                                                 ON iinpi.`prod_id` = cpe.`entity_id`
                                  WHERE iipt.`prod_id` IS NULL AND iinpi.`prod_id` IS NULL LIMIT ". $prev_current_product .", 1";
              }
              $entity_id = $db_res->fetchRow($query);
              $entity_id = $entity_id['entity_id'];
          }
        }

        if($import_id){
            $entity_id = $import_id;
        }

        if(!empty($entity_id)){
            $_product = $catalog_product_model->load($entity_id);
            $ean_code = $_product->getData(Mage::getStoreConfig('icecat_root/icecat/ean_code'));
//            $vendorName = $_product->getData(Mage::getStoreConfig('icecat_root/icecat/manufacturer'));
            $vendorName = $this->getVendorName($entity_id);

            $mpn = $_product->getData(Mage::getStoreConfig('icecat_root/icecat/sku_field'));
            $dataUrl = ''.Mage::getConfig()->getNode('default/icecatlive/icecatlive_icecat_dataurl')->icecat_dataurl;
            $dataUrlProduct = '';

            $icecatliveImportModel = new Iceshop_Icecatlive_Model_Import();
            $icecatliveImportModel->entityId = $entity_id;

            $successRespondByMPNVendorFlag = false;
            if (!empty($mpn) && !empty($vendorName)) {
                $resultString = $icecatliveImportModel->_getIceCatData($userName, $userPass, $dataUrl, array(
                    "prod_id" => $mpn,
                    "lang" => $locale,
                    "vendor" => $vendorName,
                    "output" => 'productxml'
                ));
                $dataUrlProduct = $dataUrl . "?prod_id=$mpn;vendor=$vendorName;lang=$locale;output=productxml";
                if ($xml_result = $this->_parseXml($resultString)) {
                    if (!$icecatliveImportModel->checkIcecatResponse($xml_result->Product['ErrorMessage'])) {
                        $successRespondByMPNVendorFlag = true;
                    }
                }

            }

            // if get data by MPN & brand name wrong => trying by Ean code
            $error = false;
            if (!$successRespondByMPNVendorFlag) {
                if (!empty($ean_code)) {
                    $resultString = $icecatliveImportModel->_getIceCatData($userName, $userPass, $dataUrl, array(
                        'ean_upc' => trim($ean_code),
                        'lang' => $locale,
                        'output' => 'productxml'
                    ));

                    $dataUrlProduct = $dataUrl . "?ean_upc=".trim($ean_code).";lang=$locale;output=productxml";
                    if ($xml_result = $this->_parseXml($resultString)) {
                        if ($icecatliveImportModel->checkIcecatResponse($xml_result->Product['ErrorMessage'])) {
                            $error = true;
                        }
                    }
                } else {
                    $error = true;
                }
            }

            if(!$error){
                if(!$import_id){
                  if(empty($import_info['success_imported_product'])){
                      $import_info['success_imported_product'] = 1;
                      $DB_loger->insetrtUpdateLogValue('icecatlive_success_imported_product_temp', $import_info['success_imported_product']);
                  }else{
                      $import_info['success_imported_product'] += 1;
                      $DB_loger->insetrtUpdateLogValue('icecatlive_success_imported_product_temp', $import_info['success_imported_product']);
                  }
                }
                $this->_prepareCacheDir();
                if(!$this->_saveXmltoIcecatLiveCache($resultString, $entity_id, $locale)){
                  $import_info['error'] = Mage::getConfig()->getNode('default/icecatlive/icecatlive_permission_error')->icecatlive_error_text .
                          Mage::getBaseDir('var') . $this->_connectorCacheDir . 'iceshop_icecatlive_' . $entity_id . '_' . $locale;
                }
                $prod_title = (string)$xml_result->Product['Title'];

                $this->_saveProductTitle($entity_id, $prod_title);
                $icecatliveImportModel->_saveProductCatalogImage($entity_id, $xml_result->Product);
                $icecatliveImportModel->loadGalleryPhotos($xml_result->Product->ProductGallery->ProductPicture);
                $this->_saveImportMessage($entity_id, 'Success imported product from Icecat.');

                if($import_id){
                    return $_product = $catalog_product_model->load($entity_id);
                }

            }else{
                $this->deleteIdProductTitles($entity_id);
                if ($import_id) {
                    if (preg_match('/^Warning: You are not allowed to have Full ICEcat access$/', $icecatliveImportModel->getErrorMessage())) {
                        $this->_cron_full_icecat_counter++;
                    }
                    return false;
                }
                // insert not import product id in table
                if($this->_saveNotImportProductID($entity_id)){
                      if($product_onlynewproducts){
                          $import_info['current_product'] -= 1;
                          $DB_loger->insetrtUpdateLogValue('icecatlive_current_product_temp', $import_info['current_product']);
                          $import_info['count_products'] = $import_info['count_products'] - 1;
                          $DB_loger->insetrtUpdateLogValue('icecatlive_count_products_temp', $import_info['count_products']);
                      }
                }
                $import_info['error'] = $icecatliveImportModel->getErrorMessage();
                $import_info['system_error'] = $icecatliveImportModel->getSystemError();
                $dataUrlProduct = "The product URL: {$dataUrlProduct}";
                $this->_saveImportMessage($entity_id, $import_info['error'].$import_info['system_error']. $dataUrlProduct);
                if (preg_match('/^Warning: You are not allowed to have Full ICEcat access$/', $import_info['error'])) {
                    if(empty($import_info['full_icecat_product'])){
                        $import_info['full_icecat_product'] = 1;
                        $DB_loger->insetrtUpdateLogValue('icecatlive_full_icecat_product_temp', $import_info['full_icecat_product']);
                    }else{
                        $import_info['full_icecat_product'] += 1;
                        $DB_loger->insetrtUpdateLogValue('icecatlive_full_icecat_product_temp', $import_info['full_icecat_product']);
                    }
                }
                if(empty($import_info['error_imported_product'])){
                    $import_info['error_imported_product'] = 1;
                    $DB_loger->insetrtUpdateLogValue('icecatlive_error_imported_product_temp', $import_info['error_imported_product']);
                }else{
                    $import_info['error_imported_product'] += 1;
                    $DB_loger->insetrtUpdateLogValue('icecatlive_error_imported_product_temp', $import_info['error_imported_product']);
                }
            }
        }else{
            $import_info['current_product'] = 0;
            $import_info['count_products'] = $this->getCountProducts($product_onlynewproducts);
            if($import_info['count_products']){
                $DB_loger->insetrtUpdateLogValue('icecatlive_current_product_temp',$import_info['current_product']);
                $import_info['success_imported_product'] = 0;
                $DB_loger->insetrtUpdateLogValue('icecatlive_success_imported_product', $import_info['success_imported_product']);
                $DB_loger->insetrtUpdateLogValue('icecatlive_success_imported_product_temp', $import_info['success_imported_product']);
                $DB_loger->insetrtUpdateLogValue('icecatlive_count_products_temp', $import_info['count_products']);
                $import_info['error_imported_product'] = 0;
                $DB_loger->insetrtUpdateLogValue('icecatlive_error_imported_product_temp', $import_info['error_imported_product']);
            }
        }

        if($import_info['current_product'] == $import_info['count_products']){
            $import_info['done'] = 1;
            $DB_loger->insetrtUpdateLogValue('icecatlive_error_imported_product', $import_info['error_imported_product']);
            $DB_loger->insetrtUpdateLogValue('icecatlive_success_imported_product', $import_info['success_imported_product']);
            $DB_loger->insetrtUpdateLogValue('icecatlive_full_icecat_product', $import_info['full_icecat_product']);
            $DB_loger->deleteLogKey('icecatlive_full_icecat_product_temp');
            $DB_loger->deleteLogKey('icecatlive_current_product_temp');
            $DB_loger->deleteLogKey('icecatlive_error_imported_product_temp');
            $DB_loger->deleteLogKey('icecatlive_success_imported_product_temp');
            $DB_loger->deleteLogKey('icecatlive_count_products_temp');
            $DB_loger->deleteLogKey('import_time_one_product');
            $DB_loger->deleteLogKey('icecatlive_process_hash');
            $DB_loger->deleteLogKey('icecatlive_process_hash_time');

            $file_logger->deleteLogFile();

            if($_GET['update'] != 1 && !$import_id){
                $DB_loger->insetrtUpdateLogValue('icecatlive_enddate_imported_product', date('Y-m-d H:i:s'));
            } elseif (!$import_id) {
                $DB_loger->insetrtUpdateLogValue('icecatlive_enddate_update_product', date('Y-m-d H:i:s'));
            }
            $DB_loger->insetrtUpdateLogValue('icecatlive_lastinsert_product_id', $entity_id);
            if($crone){
                return $import_info;
            }
            echo json_encode($import_info);
        }else{
            $DB_loger->insetrtUpdateLogValue('icecatlive_error_imported_product', $import_info['error_imported_product']);
            $DB_loger->insetrtUpdateLogValue('icecatlive_success_imported_product', $import_info['success_imported_product']);
            $DB_loger->insetrtUpdateLogValue('icecatlive_full_icecat_product', $import_info['full_icecat_product']);
            $import_info['done'] = 0;
            $DB_loger->insetrtUpdateLogValue('icecatlive_lastinsert_product_id', $entity_id);
            if($crone){
                return $import_info;
            }
            echo json_encode($import_info);
        }


        $import_time_one_product = microtime(true) - $start_import_time;
        $import_time_one_product = (int)(round($import_time_one_product)*1000)/1000;

        if(empty($import_time_product)){
            $DB_loger->insetrtUpdateLogValue('import_time_one_product', $import_time_one_product);
        } else {
            if($import_time_product < $import_time_one_product){
                $DB_loger->insetrtUpdateLogValue('import_time_one_product', $import_time_one_product);
            }
        }

     } catch (Exception $e){
       $DB_loger->deleteLogKey('icecatlive_process_hash');
       $DB_loger->deleteLogKey('icecatlive_process_hash_time');
       throw new Exception($e->getMessage());
     }
   }

    /**
     * Write success import product title to iceshop_icecatlive_products_titles db table
     * @param integer $prod_id
     * @param string $prod_title
     */
    public function _saveProductTitle($prod_id, $prod_title){
        $connection = $this->getDbConnection();
        $db_res = Mage::getSingleton('core/resource')->getConnection('core_write');
        $tablePrefix = '';
        $tPrefix = (array) Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
          $tablePrefix = $tPrefix[0];
        }
        $productsTitleTable = Mage::getSingleton('core/resource')->getTableName('icecatlive/products_titles');
        try{
            $sql = " INSERT INTO `" . $tablePrefix . "iceshop_icecatlive_products_titles` ( prod_id, prod_title)
                    VALUES('" . $prod_id . "', '" . $prod_title . "')
                    ON DUPLICATE KEY UPDATE
                    prod_title = VALUES(prod_title)";

            $connection->query($sql);
        } catch (Exception $e) {
            Mage::log("connector issue: {$e->getMessage()}");
        }
  }

    /**
     * Return count product for imports
     * @param integer $product_onlynewproducts
     * @return integer
     */
    public function getCountProducts($product_onlynewproducts = 0) {
        $db_res = Mage::getSingleton('core/resource')->getConnection('core_write');
        $tablePrefix = '';
        $tPrefix = (array) Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
          $tablePrefix = $tPrefix[0];
        }
        $productsTitleTable = Mage::getSingleton('core/resource')->getTableName('icecatlive/products_titles');
        $productsIdTable = Mage::getConfig()->getNode('default/icecatlive/noimportid_tables')->table_name;
        $productsIdTable = $tablePrefix . $productsIdTable;
        if (!$product_onlynewproducts) {
          $query = "SELECT COUNT(*) FROM `" . $tablePrefix . "catalog_product_entity` LEFT JOIN `" . $tablePrefix ."iceshop_icecatlive_products_titles` ON entity_id = prod_id WHERE prod_id IS NULL";
        } else {
          $query = "SELECT COUNT(*) FROM `" . $tablePrefix . "catalog_product_entity`  AS cpe
                                          LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_products_titles`  AS iipt
                                                  ON cpe.`entity_id`= iipt.`prod_id`
                                          LEFT JOIN `" . $productsIdTable . "` AS iinpi
                                                  ON iinpi.`prod_id` = cpe.`entity_id`
                                    WHERE iipt.`prod_id`  IS NULL AND iinpi.`prod_id` IS NULL;";
        }
        $count_products = $db_res->fetchRow($query);
        return $count_products['COUNT(*)'];
    }

    /**
     * Insert to table noimport_product_id this prod_id
     * @param integer $prod_id
     */
    public function _saveNotImportProductID($prod_id){
        $connection = $this->getDbConnection();

        $tablePrefix = '';
        $tPrefix = (array)Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
            $tablePrefix = $tPrefix[0];
        }
        $productsIdTable = Mage::getConfig()->getNode('default/icecatlive/noimportid_tables')->table_name;
        $productsIdTable = $tablePrefix . $productsIdTable;

        try{
            $sql = " REPLACE INTO `" . $productsIdTable . "` ( prod_id )
                    VALUES(" . $prod_id . ")";
            $connection->query($sql);
        } catch (Exception $e) {
            Mage::log("connector issue: {$e->getMessage()}");
        }
        return true;
    }

    /**
     * Mathod add Icecat import message to icecatlive_status product status
     * @param integer $entity_id
     * @param string $message
     */
    public function _saveImportMessage($entity_id, $message){

        $connection = $this->getDbConnection();
        $tablePrefix = '';
        $tPrefix = (array)Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
            $tablePrefix = $tPrefix[0];
        }
        $query = '';
        try{
            $sql = "SELECT ea.`attribute_id`, eea.`entity_type_id`, eea.`attribute_set_id` FROM `{$tablePrefix}eav_attribute` AS ea
                        LEFT JOIN `{$tablePrefix}eav_entity_attribute` AS eea
                                ON eea.`attribute_id` = ea.`attribute_id`
                      WHERE ea.`attribute_code`='icecatlive_status';";
            $query = $connection->fetchRow($sql);
            if(!empty($query)){
                $attribute_id = $query['attribute_id'];
                $entity_type_id = $query['entity_type_id'];
                $attribute_set_id = $query['attribute_set_id'];

                $sql = "SELECT cpev.`store_id` FROM `{$tablePrefix}eav_attribute` AS ea
                                LEFT JOIN `{$tablePrefix}catalog_product_entity_varchar` AS cpev
                                  ON ea.`attribute_id` = cpev.`attribute_id`
                        WHERE cpev.`entity_id`=' . $entity_id . ' AND (ea.`attribute_code` = 'sku_type' OR ea.`attribute_code` = 'mpn'  OR  ea.`attribute_code` = 'ean' )
                        GROUP BY cpev.`entity_id`;";
                $query = $connection->fetchRow($sql);
                if(!empty($query['store_id'])){
                    $store_id = $query['store_id'];
                } else {
                    $store_id = 1;
                }

                $sql = "DELETE FROM `{$tablePrefix}catalog_product_entity_varchar` WHERE entity_id=".$entity_id.
                        " AND attribute_id=".$attribute_id." AND store_id=".$store_id.";";
                $connection->query($sql);

                $sql = "INSERT INTO `{$tablePrefix}catalog_product_entity_varchar` ( value_id, entity_type_id, attribute_id, store_id, entity_id, value)
                        VALUES(NULL, " . $entity_type_id . ", " .$attribute_id. ", ".$store_id. ", " .$entity_id.", '".$message. "')";
                $connection->query($sql);
            }
        } catch (Exception $e) {
            Mage::log("connector issue: {$e->getMessage()}");
        }
    }


    /**
     * Write to cache file import product information
     * @param string $resultString
     * @param integer $entity_id
     * @param string $locale
     * @return boolean
     */
    public function _saveXmltoIcecatLiveCache($resultString, $entity_id, $locale){
        $current_prodCacheXml =  Mage::getBaseDir('var') . $this->_connectorCacheDir . 'iceshop_icecatlive_' . $entity_id . '_' . $locale;
        if(is_writable(Mage::getBaseDir('var') . $this->_connectorCacheDir)){
            $current_prodCacheHandler = fopen($current_prodCacheXml, "w");
            fwrite($current_prodCacheHandler, $resultString);
            fclose ($current_prodCacheHandler);
            if(file_exists($current_prodCacheXml)){
              return true;
            } else {
                return false;
            }
        } else {
          return false;
        }
    }
    /**
     * parse given XML to SIMPLE XML
     * @param string $stringXml
     */
    protected function _parseXml($stringXml)
    {
        libxml_use_internal_errors(true);
        $simpleDoc = simplexml_load_string($stringXml);
        if ($simpleDoc) {
            return $simpleDoc;
        }
        $simpleDoc = simplexml_load_string(utf8_encode($stringXml));
        if ($simpleDoc) {
            return $simpleDoc;
        }
        return false;
    }

    /**
     * Singletong for DB connection
     */
    private function getDbConnection()
    {
        if ($this->connection) {
            return $this->connection;
        }
        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        return $this->connection;
    }

    /**
     * Prepares file and folder for futur download
     * @param string $fileName
     */
    protected function _prepareFile($fileName)
    {
        $varDir = Mage::getBaseDir('var') . $this->_connectorDir;
        $filePath = $varDir . $fileName;
        if (!is_dir($varDir)) {
            mkdir($varDir, 0777, true);
        }
        return $filePath;
    }
    protected function _prepareCacheDir()
    {
        $varDir = Mage::getBaseDir('var') . $this->_connectorCacheDir;
        if (!is_dir($varDir)) {
            mkdir($varDir, 0777, true);
        }
    }

    /**
     * Method run import data in crontab jobs
     * @param int $update param set update or import data
     * @param bool $full_import_stat flag for set full_import
     */
    public function load($update = 0, $full_import_stat = false, $error_import = 0, $crone_start = 1)
    {
        if ($crone_start) {
            $date_crone_start = date('Y-m-d H:i:s');
            $this->setCroneStatus('running', $date_crone_start, 'icecatlive_load_data');
        }
        $DB_loger = Mage::helper('icecatlive/db');
        $DB_loger->insetrtUpdateLogValue('icecatlive_startdate_imported_product', date('Y-m-d H:i:s'));
        $DB_loger->deleteLogKey('icecatlive_error_imported_product');
        $DB_loger->deleteLogKey('icecatlive_success_imported_product');
        try {
            $DB_loger = Mage::helper('icecatlive/db');
            if (!$full_import_stat) {
                $_GET['full_import'] = 1;
            } else {
                $_GET['full_import'] = '';
            }
            $_GET['error_import'] = $error_import;
            $_GET['update'] = 0;

            $this->getEntityData();

            if (!empty($this->_heap)) {
                $this->runFetchData();
            }
            $DB_loger->insetrtUpdateLogValue('icecatlive_enddate_imported_product', date('Y-m-d H:i:s'));
        } catch (Exception $e) {
            $DB_loger->insetrtUpdateLogValue('import_icecat_server_error_message', $e->getMessage());
            if ($this->_current_id !== null) {
                unset($this->_heap[$this->_current_id]);
                $this->load(0, true, 1, 0);
            }
        }
    }

    /**
     * Method run updata in crontab jobs
     */
    public function loadUpdate($error_import = 0, $crone_start = 1)
    {
        if ($crone_start) {
            $date_crone_start = date('Y-m-d H:i:s');
            $this->setCroneStatus('running', $date_crone_start, 'icecatlive_load_updata');
        }
        $DB_loger = Mage::helper('icecatlive/db');
        $DB_loger->insetrtUpdateLogValue('icecatlive_startdate_update_product', date('Y-m-d H:i:s'));
        $DB_loger->deleteLogKey('icecatlive_error_imported_product');
        $DB_loger->deleteLogKey('icecatlive_success_imported_product');
        try {
            $_GET['update'] = 1;
            $_GET['error_import'] = $error_import;

            $this->getEntityData();
            if (!empty($this->_heap)) {
                $this->runFetchData();
            }
            $DB_loger->deleteLogKey('import_icecat_server_error_message_update');
            $DB_loger->insetrtUpdateLogValue('icecatlive_enddate_update_product', date('Y-m-d H:i:s'));
        } catch (Exception $e) {
            $DB_loger->insetrtUpdateLogValue('import_icecat_server_error_message_update', $e->getMessage());
            $this->loadUpdate(1, 0);
        }
    }

    /**
     * Delete row from products_titles tables
     * if Icecat return error to this product
     * @param integer $entity_id
     */
    public function deleteIdProductTitles($entity_id){
        $connection = $this->getDbConnection();
        $tablePrefix = '';
        $tPrefix = (array)Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
            $tablePrefix = $tPrefix[0];
        }
        $query = '';
        try{
            $sql = "DELETE FROM `".$tablePrefix."iceshop_icecatlive_products_titles` WHERE prod_id=".$entity_id.";";
            $connection->query($sql);
            $this->deleteCacheFile($entity_id);
        } catch (Exception $e) {
            Mage::log("connector issue: {$e->getMessage()}");
        }
    }

    /**
     * Delete product import file from cache folder
     * @param integer $entity_id
     */
    public function deleteCacheFile($entity_id){
        $import = new Iceshop_Icecatlive_Model_Observer();
        $locale = Mage::getStoreConfig('icecat_root/icecat/language');
        if ($locale == '0') {
            $systemLocale = explode("_", Mage::app()->getLocale()->getLocaleCode());
            $locale = $systemLocale[0];
        }
        $cache_file =Mage::getBaseDir('var') . $import->_connectorCacheDir . 'iceshop_icecatlive_'.$entity_id .'_' . $locale;
        if(file_exists($cache_file)){
            unlink($cache_file);
        }
    }


    /**
     * Change crone status in table `cron_schedule`
     * @param string $status
     * @param string $date_crone_start
     * @param string $job_code
     */
    public function setCroneStatus($status = 'pending',$date_crone_start, $job_code){
      try{
          $db_res = Mage::getSingleton('core/resource')->getConnection('core_write');
          $tablePrefix = '';
          $tPrefix = (array)Mage::getConfig()->getTablePrefix();
          if (!empty($tPrefix)) {
              $tablePrefix = $tPrefix[0];
          }
          $db_res->query("UPDATE `{$tablePrefix}cron_schedule` SET status='$status' WHERE job_code = '$job_code' AND executed_at='$date_crone_start'");
      } catch (Exception $e){
           Mage::log("Set crone status error: {$e->getMessage()}");
      }
    }

    /**
     * Method return vendor name
     * @param type $entity_id Product id
     * @return string vendor name
     */
    public function getVendorName($entity_id){
        $storeId = Mage::app()->getStore()->getStoreId();
        $db_res = Mage::getSingleton('core/resource')->getConnection('core_write');
        $icecat_manufacturer_attribute_code = Mage::getStoreConfig('icecat_root/icecat/manufacturer');
        $tablePrefix = '';
        $tPrefix = (array)Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
            $tablePrefix = $tPrefix[0];
        }
        $catalog_product_model = Mage::getModel('catalog/product');
        $_product = $catalog_product_model->load($entity_id);
        if(!empty($_product)){
          $vendorName = $_product->getData($icecat_manufacturer_attribute_code);
          if(!empty($vendorName)){
              $query = "SELECT * FROM `{$tablePrefix}eav_attribute`  AS ea WHERE ea.`attribute_code`='{$icecat_manufacturer_attribute_code}';";
              $attribute = $db_res->fetchRow($query);
              if($attribute['backend_type'] == 'int'){
                  $query = "SELECT eaov.`value` FROM `{$tablePrefix}eav_attribute_option_value` AS eaov
                                      LEFT JOIN `{$tablePrefix}eav_attribute_option` AS eao
                                              ON eao.`option_id` = eaov.`option_id`
                                      LEFT JOIN `{$tablePrefix}eav_attribute`  AS ea
                                              ON ea.`attribute_id`=eao.`attribute_id`
                             WHERE ea.`attribute_code`='{$icecat_manufacturer_attribute_code}' AND eaov.`option_id`={$vendorName} AND eaov.`store_id`={$storeId} ;";
                  $attribute = $db_res->fetchRow($query);

                  return $attribute['value'];

              } elseif($attribute['backend_type'] == 'varchar') {
                  return $vendorName;
              }

          }

        }
        return '';
    }

    /**
     * Runs function of fetch data
     * @throws Exception
     */
    public function runFetchData()
    {
        $DB_loger = Mage::helper('icecatlive/db');
        $success = 0;
        $failed = 0;
        if (!empty($this->_heap)) {
            foreach ($this->_heap as $ids) {
                $this->_current_id = $ids;
                try {
                    $result = $this->loadProductInfoIntoCache($ids, 1);
                    if ($result == false) {
                        $failed++;
                        $DB_loger->insetrtUpdateLogValue('icecatlive_error_imported_product', $failed);
                    } else {
                        $success++;
                        $DB_loger->insetrtUpdateLogValue('icecatlive_success_imported_product', $success);
                    }
                    $DB_loger->insetrtUpdateLogValue('icecatlive_full_icecat_product', $this->_cron_full_icecat_counter);
                    unset($this->_heap[$ids]);
                } catch (Exception $e) {
                    $failed++;
                    $DB_loger->insetrtUpdateLogValue('icecatlive_error_imported_product', $failed);
                    unset($this->_heap[$ids]);
                }
            }
        }
    }

    /**
     * Fill $this->_heap property with products id`s to last it processed
     */
    public function getEntityData()
    {
        $db_res = Mage::getSingleton('core/resource')->getConnection('core_write');
        $product_onlynewproducts = Mage::getStoreConfig('icecat_root/icecat/product_onlynewproducts');
        $tablePrefix = '';
        $tPrefix = (array)Mage::getConfig()->getTablePrefix();
        if (!empty($tPrefix)) {
            $tablePrefix = $tPrefix[0];
        }
        $heap = array();
        if (empty($this->_heap)) {
            if ($_GET['update'] != 1) {
                if (!$product_onlynewproducts) {
                    $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity`";
                } else {
                    $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity` AS cpe
                                    LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_noimport_products_id` AS iinpi
                                                  ON cpe.`entity_id` = iinpi.`prod_id`
                            WHERE iinpi.`prod_id` IS NULL";
                }
                $heap = $db_res->fetchAll($query);
            } else {
                if (!$product_onlynewproducts) {
                    $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity` LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_products_titles` ON entity_id = prod_id WHERE prod_id IS NULL";
                } else {
                    $query = "SELECT `entity_id` FROM `" . $tablePrefix . "catalog_product_entity` AS cpe
                                          LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_products_titles` AS iipt
                                                 ON cpe.`entity_id` = iipt.`prod_id`
                                          LEFT JOIN `" . $tablePrefix . "iceshop_icecatlive_noimport_products_id` AS iinpi
                                                 ON iinpi.`prod_id` = cpe.`entity_id`
                                  WHERE iipt.`prod_id` IS NULL AND iinpi.`prod_id` IS NULL1";
                }
                $heap = $db_res->fetchAll($query);
            }
            if (!empty($heap)) {
                foreach ($heap as $key => $value) {
                    if (isset($value['entity_id'])) {
                        $this->_heap[$value['entity_id']] = $value['entity_id'];
                    }
                }
            }
        }
    }

}

?>
