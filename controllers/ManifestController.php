<?php

/**
 * Controller for IIIF manifests
 * @package controllers
 */
class IiifItems_ManifestController extends IiifItems_BaseController {
    protected static $allowedThings = array('Collection', 'Item', 'File', 'ExhibitPageBlock');
    
    public function manifestAction() {
        //Get and check the thing's existence
        $version = $this->getParam('version');
        $id = $this->getParam('id');
        $type = $this->getParam('things');
        $class = Inflector::camelize(Inflector::singularize($type));
        $thing = get_record_by_id($class, $id);
        if (empty($thing) || !in_array($class, self::$allowedThings)) {
            throw new Omeka_Controller_Exception_404;
        }

        //Respond with JSON
        $manifestHelper = self::$manifestHelperRegistry[$version];
        try {
            switch ($class) {
                case 'Collection':
                    $jsonData = $manifestHelper::buildManifest($thing, is_admin_theme()); 
                    IiifItems_Util_Search::insertSearchApiFor($thing, $jsonData);
                break;
                case 'Item': 
                    $jsonData = $manifestHelper::buildItemManifest($thing);
                    if ($thing->item_type_id != get_option('iiifitems_annotation_item_type')) {
                        IiifItems_Util_Search::insertSearchApiFor($thing, $jsonData); 
                    }
                break;
                case 'File': $jsonData = $manifestHelper::buildFileManifest($thing); break;
                case 'ExhibitPageBlock': $jsonData = $manifestHelper::buildExhibitPageBlockManifest($thing); break;
            }
            $this->__respondWithJson($jsonData);
        } catch (Exception $e) {
            $this->__respondWithJson(array(
                'message' => $e->getMessage()
            ), 500);
        }
    }
}
