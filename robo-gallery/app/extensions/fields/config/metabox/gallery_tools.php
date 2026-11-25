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

// "Gallery Tools" in the editor's sidebar: small tools of the gallery, one section each
// (template content/galleryTools/tools). It has the id of the old "Gallery Shortcode" box, so the place and the
// closed state the editors saved for that box (user meta, by box id) apply to this one.
return array(
    'active'   => true,
    'order'    => 3,
    'settings' => array(
        'id'       => 'robo_gallery_shortcode',
        'title'    => __('Gallery Tools', 'robo-gallery'),
        'screen'   => array(ROBO_GALLERY_TYPE_POST),
        'context'  => 'side',
        'priority' => 'default',
    ),
    'view'     => 'default',
    'state'    => 'open',
    'content'  => 'template::content/galleryTools/tools',
);
