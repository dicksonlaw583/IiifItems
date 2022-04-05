<?php

/**
 * Utilities for IIIFv3 manifests.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_Manifest3 extends IiifItems_Util_Manifest {
    /**
     * Basic template for IIIF Presentation API manifest
     * @param string $atId The unique URI ID for this manifest
     * @param string $seqId The sequence ID for the main sequence
     * @param string $label The title of this manifest
     * @param array $canvases (optional) An array of IIIF Presentation API canvases
     * @return array
     */
    public static function blankTemplate($atId, $seqId, $label, $canvases=array()) {
        return array(
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $atId,
            'type' => 'Manifest',
            'label' => array('en' => array($label)),
            'items' => $canvases,
        );
    }
    
    /**
     * Bare minimum template for a manifest, for embedding in a collection listing
     * @param string $atId The unique URI ID for this manifest
     * @param string $label The title of this manifest
     * @return array
     */
    public static function bareTemplate($atId, $label) {
        return array(
            'id' => $atId,
            'type' => 'Manifest',
            'label' => array('en' => array($label)),
        );
    }

    /**
     * Return the IIIF Presentation API manifest representation of the Omeka collection
     * @param Collection $collection
     * @param boolean $bare Whether to exclude the annotation list references
     * @return array
     */
    public static function buildManifest($collection, $bare=false) {
        // Set default IDs and titles
        $atId = public_full_url(array('version' => 'iiifv3', 'things' => 'collections', 'id' => $collection->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $label = metadata($collection, array('Dublin Core', 'Title'), array('no_escape' => true));
        // Do it only for manifests with appropriate authorization
        if (self::isManifest($collection)) {
            // Decide which cache entry to consider
            $cacheEntryName = $bare ? 'private_bare_manifest3' : (
                current_user() ? 'private_manifest3' : 'public_manifest3'
            );
            if ($json = get_cached_iiifitems_value_for($collection, $cacheEntryName)) {
                return $json;
            }
            // Try to find template; if it does not already exist, use the blank template
            if (!($json = parent::fetchJsonData($collection))) {
                $json = self::blankTemplate($atId, '', $label);
            }
            // Override the IDs, titles and DC metadata
            $json['id'] = $atId;
            $json['items'] = self::findCanvasesFor($collection);
            parent::addDublinCoreMetadataV3($json, $collection);
            // Cache accordingly
            cache_iiifitems_value_for($collection, $json, $cacheEntryName);
            // Done
            return $json;
        }
        return self::blankTemplate($atId, '', $label);
    }

    /**
     * Return the IIIF Presentation API manifest representation of the Omeka Item
     * @param Item $item
     * @return array
     */
    public static function buildItemManifest($item) {
        // Set default IDs and titles
        $atId = public_full_url(array('version' => 'iiifv3', 'things' => 'items', 'id' => $item->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $label = metadata($item, array('Dublin Core', 'Title'), array('no_escape' => true));
        // If it is an annotation, use the special annotation canvas utility
        if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
            $json = self::blankTemplate($atId, '', $label, array(
                IiifItems_Util_Canvas3::buildAnnotationCanvas($item)
            ));
        }
        // Otherwise, use the standard item-to-canvas utility
        else {
            $json = self::blankTemplate($atId, '', $label, array(
                IiifItems_Util_Canvas3::buildCanvas($item)
            ));
        }
        // Override DC metadata
        parent::addDublinCoreMetadataV3($json, $item);
        if ($item->collection_id !== null) {
            $json['label'] = array('en' => array(metadata(get_record_by_id('Collection', $item->collection_id), array('Dublin Core', 'Title'), array('no_escape' => true))));
        }
        // Done
        return $json;
    }

    /**
     * Return the IIIF Presentation API manifest representation of the Omeka File
     * @param File $file
     * @return array
     */
    public static function buildFileManifest($file) {
        // Set default IDs and titles
        $atId = public_full_url(array('version' => 'iiifv3', 'things' => 'files', 'id' => $file->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $label = metadata($file, 'display_title', array('no_escape' => true));
        // Use standard file-to-canvas utility
        $json = self::blankTemplate($atId, '', $label, array(
            IiifItems_Util_Canvas3::fileCanvasJson($file)
        ));
        // Override DC metadata
        parent::addDublinCoreMetadataV3($json, $file);
        // Done
        return $json;
    }
    
    /**
     * Return the IIIF Presentation API manifest representation of the exhibit block's attached items
     * @param ExhibitPageBlock $block
     * @return array
     */
    public static function buildExhibitPageBlockManifest($block) {
        // Set default IDs and titles
        $atId = public_full_url(array('version' => 'iiifv3', 'things' => 'exhibit_page_blocks', 'id' => $block->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
        $label = $block->getPage()->title;
        // Find attached items in order
        $canvases = array();
        foreach ($block->getAttachments() as $attachment) {
            if ($item = $attachment->getItem()) {
                // If it is an annotation, use the special annotation canvas utility
                if ($item->item_type_id == get_option('iiifitems_annotation_item_type')) {
                    $canvases[] = IiifItems_Util_Canvas3::buildAnnotationCanvas($item);
                }
                // Otherwise, use the standard item-to-canvas utility
                else {
                    $canvases[] = IiifItems_Util_Canvas3::buildCanvas($item);
                }
            }
        }
        // Generate from template
        $json = self::blankTemplate($atId, '', $label, $canvases);
        if ($block->text) {
            $json['description'] = $block->text;
        }
        // Done
        return $json;
    }

    /**
     * Return a list of canvases for this collection
     * @param Collection $collection
     * @return array
     */
    public static function findCanvasesFor($collection) {
        $canvases = array();
        foreach (get_db()->getTable('Item')->findBy(array('collection' => $collection)) as $item) {
            if (raw_iiif_metadata($item, 'iiifitems_item_display_element') != 'Never') {
                $canvases[] = IiifItems_Util_Canvas3::buildCanvas($item);
            }
        }
        return $canvases;
    }
}