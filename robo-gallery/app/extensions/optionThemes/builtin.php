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

/*
 * The built-in option themes (OptionThemes): slug => name, description and the
 * options that differ from the plugin's defaults. The slug is stored in the
 * galleries that follow a theme ("builtin-<slug>"): never rename one. Only
 * options of the free version, so a theme looks the same without PRO.
 * A theme is for every Fusion Grid source; 'sets' (OptionThemes::SETS) limits
 * it to some content types.
 */
return array(

    'classic'   => array(
        'name'        => __('Classic grid', 'robo-gallery'),
        'description' => __('The plugin defaults: an even grid with a sliding caption.', 'robo-gallery'),
        'values'      => array(),
    ),

    'masonry'   => array(
        'name'        => __('Masonry', 'robo-gallery'),
        'description' => __('Images keep their proportions in columns, soft shadows.', 'robo-gallery'),
        'values'      => array(
            'layout'      => 'masonry',
            'spacing'     => 8,
            'shadow'      => 2,
            'hoverEffect' => 'zoe',
        ),
    ),

    'justified' => array(
        'name'        => __('Justified rows', 'robo-gallery'),
        'description' => __('Rows of equal height filling the width, tight gaps.', 'robo-gallery'),
        'values'      => array(
            'layout'          => 'rows',
            'targetRowHeight' => 300,
            'spacing'         => 4,
            'hoverEffect'     => 'sadie',
        ),
    ),

    'columns'   => array(
        'name'        => __('Columns', 'robo-gallery'),
        'description' => __('Images in columns of the same width, airy gaps.', 'robo-gallery'),
        'values'      => array(
            'layout'      => 'columns',
            'spacing'     => 12,
            'shadow'      => 1,
            'hoverEffect' => 'lily',
        ),
    ),

    'minimal'   => array(
        'name'        => __('Minimal', 'robo-gallery'),
        'description' => __('Only the images: no captions, compact menu, few lightbox buttons.', 'robo-gallery'),
        'values'      => array(
            'spacing'           => 2,
            'shadow'            => 0,
            'hoverEffect'       => 'disable',
            'titleSource'       => 'disable',
            'descriptionSource' => 'disable',
            'topMenuMode'       => 'compact',
            'breadcrumbs'       => 'off',
            'sideMenu'          => 'off',
            'lightboxButtons'   => array('fullscreen'),
        ),
    ),

    'dark'      => array(
        'name'        => __('Dark', 'robo-gallery'),
        'description' => __('Dark captions over the images, for dark site designs.', 'robo-gallery'),
        'values'      => array(
            'shadow'                          => 6,
            'hoverEffect'                     => 'static',
            'hoverTitleColor'                 => '#ffffff',
            'hoverTitleBackgroundColor'       => 'rgba(0, 0, 0, 0.85)',
            'hoverDescriptionColor'           => '#e0e0e0',
            'hoverDescriptionBackgroundColor' => 'rgba(0, 0, 0, 0.6)',
            'hoverBackgroundColor'            => 'rgba(0, 0, 0, 0.85)',
            'hoverColor'                      => 'rgba(255, 255, 255, 0.9)',
            'loadingColor'                    => '#9e9e9e',
        ),
    ),
);
