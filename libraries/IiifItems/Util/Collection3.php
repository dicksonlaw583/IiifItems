<?php

/**
 * Utilities for IIIFv3 collections.
 * @package IiifItems
 * @subpackage Util
 */
class IiifItems_Util_Collection3 extends IiifItems_Util_Collection {
    /**
     * Basic template for IIIF Presentation API Collection, in manifest-collection form.
     * 
     * @param string $atId The IIIF ID to attach
     * @param string $label The label to attach
     * @param array $manifests List of manifests in IIIF JSON form
     * @param array $collections List of collections in IIIF JSON form
     * @return array
     */
    public static function blankTemplate($atId, $label, $manifests=array(), $collections=array()) {
        return self::blankMembersTemplate($atId, $label, $collections+$manifests);
    }

    /**
     * Basic template for IIIF Presentation API Collection, in members form.
     * 
     * @param string $atId The IIIF ID to attach
     * @param string $label The label to attach
     * @param array $manifests List of manifests in IIIF JSON form
     * @param array $collections List of collections in IIIF JSON form
     * @return array
     */
    public static function blankMembersTemplate($atId, $label, $members=array()) {
        return array(
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $atId,
            'type' => 'Collection',
            'label' => array('en' => array($label)),
            'items' => $members,
        );
    }
    
    /**
     * Bare minimum template for a collection, for embedding in a collection listing
     * @param string $atId The unique URI ID for this collection
     * @param string $label The title of this collection
     * @return array
     */
    public static function bareTemplate($atId, $label) {
        return array(
            '@id' => $atId,
            'type' => 'Collection',
            'label' => array('en' => array($label)),
        );
    }

    /**
     * Return the IIIF Presentation API collection representation of the Omeka collection, in collection-manifest form
     * @param Collection $collection
     * @return array
     */
    public static function buildCollection($collection, $cacheAs=null) {
        // Set default IDs and titles
        $atId = public_full_url(array('version' => 'iiifv3', 'things' => 'collections', 'id' => $collection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
        $label = array('en' => array(metadata($collection, array('Dublin Core', 'Title'), array('no_escape' => true))));
        // Do it only for collections
        if (self::isCollection($collection)) {
            // Try to find cached copy
            if ($cacheAs !== null) {
                if ($json = get_cached_iiifitems_value_for($collection, $cacheAs)) {
                    return $json;
                }
            }
            // Try to find template; if it does not already exist, use the blank template
            if (!($json = parent::fetchJsonData($collection))) {
                $json = self::blankTemplate($atId,$label);
            }
            if (isset($json['items'])) {
                unset($json['items']);
            }
            // Override the entries
            $json['items'] = array();
            foreach (self::findSubcollectionsFor($collection) as $subcollection) {
                $subAtId = public_full_url(array('version' => 'iiifv3', 'things' => 'collections', 'id' => $subcollection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
                $label = metadata($subcollection, array('Dublin Core', 'Title'), array('no_escape' => true));
                $json['items'][] = iIiifItems_Util_Collection3::bareTemplate($subAtId, $label);
            }
            foreach (self::findSubmanifestsFor($collection) as $submanifest) {
                $subAtId = public_full_url(array('version' => 'iiifv3', 'things' => 'collections', 'id' => $submanifest->id, 'typeext' => 'manifest.json'), 'iiifitems_oa_uri');
                $label = metadata($submanifest, array('Dublin Core', 'Title'), array('no_escape' => true));
                $json['items'][] = IiifItems_Util_Manifest::bareTemplate($subAtId, $label);
            }
            // Override the IDs, titles and DC metadata
            $json['@id'] = $atId;
            parent::addDublinCoreMetadataV3($json, $collection);
            // Override within
            if ($parentCollection = self::findParentFor($collection)) {
                $json['within'] = public_full_url(array('version' => 'iiifv3', 'things' => 'collections', 'id' => $parentCollection->id, 'typeext' => 'collection.json'), 'iiifitems_oa_uri');
            } else if (isset($json['within'])) {
                unset($json['within']);
            }
            // Cache accordingly
            if ($cacheAs !== null) {
                cache_iiifitems_value_for($collection, $json, $cacheAs);
            }
            // Done
            return $json;
        }
        return self::blankTemplate($atId, $label);
    }
    
    /**
     * Return the IIIF Presentation API collection representation of the Omeka collection, in members form
     * @param Collection $collection
     * @return array
     */
    public static function buildMembersCollection($collection, $cacheAs=null) {
        return self::buildCollection($collection, $cacheAs);
    }
}
