<?php
/* 
*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('WPINC') || exit;

$type   = rbsGalleryUtils::getTypeGallery();
$source = rbsGalleryUtils::getSourceGallery();

// the settings panel of every type: the options of its set (GalleryOptions::setForType())
return array(
    'active'   => true,
    // first in the editor, where the old "Current Gallery Type" box was
    'order'    => 0,
    'settings' => array(
        'id'            => 'robo-gallery-robogrid-general',
        'title'         => __('Options', 'robo-gallery'),
        'screen'        => array(ROBO_GALLERY_TYPE_POST),
        'context'       => 'normal',
        'priority'      => 'high', //'default',
        'callback_args' => null,
    ),
    'view'     => 'default',
    'state'    => 'open',
    'content'      => 'template::content/robogrid/options',
    // the type of a new gallery (?rsg_gallery_type=) is saved from these; every type has this box,
    // and the 'for' conditions of the other boxes read the posted rsg_gallery_type on save
    'fields'   => array(
        array(
            'type'    => 'hidden',
            'view'    => 'default',
            'name'    => 'gallery_type',
            // an empty stored type (shown back in this field) is saved as grid
            'cb_sanitize' => 'RoboGallery\app\extensions\galleryType\GalleryTypeList::sanitizeType',
            'default' => $type,
        ),

        array(
            'type'    => 'hidden',
            'view'    => 'default',
            'name'    => 'gallery_type_source',
            'cb_sanitize' => 'RoboGallery\app\extensions\galleryType\GalleryTypeList::sanitizeSource',
            'default' => $source,
        ),
    ),
);
