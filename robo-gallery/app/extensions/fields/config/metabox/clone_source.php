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

use RoboGallery\app\GalleryOptions;

$galleryId = rbsGalleryUtils::getIdGallery();

// "Clone Gallery" (was a CMB2 box: same box id, so the editors' closed / hidden / moved state stays, same
// field name and meta rsg_options): an existing classic gallery only - the robogrid and the slider have
// no clone source, a new one is offered no source yet.
$active = $galleryId > 0
    && ROBO_GALLERY_TYPE_POST === get_post_type($galleryId)
    && 'auto-draft' !== get_post_status($galleryId)
    && GalleryOptions::SET_CLASSIC === GalleryOptions::setOf($galleryId);

return array(
    'active'   => $active,
    // after the settings panel (robogrid-general.php, 0) and the YouTube box (gallery_youtube.php, 1)
    'order'    => 2,
    'settings' => array(
        'id'       => ROBO_GALLERY_PREFIX . 'copy_metabox',
        // the old string, kept with its space: the translations have it so
        'title'    => __(' Clone Gallery', 'robo-gallery'),
        'screen'   => array(ROBO_GALLERY_TYPE_POST),
        'context'  => 'normal',
        'priority' => 'high',
    ),
    'view'     => 'default',
    'state'    => 'open',
    'fields'   => array(
        array(
            // roboGalleryFieldsFieldCloneSource: the choices and the check need the gallery's ID
            'type'          => 'clone_source',
            'view'          => 'default',
            'name'          => 'options',
            'default'       => '0',
            'label'         => __('Source Gallery', 'robo-gallery'),
            'contentBefore' => esc_html__('When you select here to inherit settings from another gallery you\'ll not be able to edit some of the options. Gallery will copy all settings from selected source.', 'robo-gallery'),
            'description'   => esc_html__('Very useful option for the webmasters who planning to create a lot of galleries. You don\'t have to configure it every time. Just setup styles of the gallery in one place and use the same options for another galleries on your website in another galleries.  Very fast, comfortable, advanced tool to speed up your work flow!', 'robo-gallery'),
            'options'       => array(
                'column' => '12 medium-8 large-6',
            ),
        ),
    ),
);
