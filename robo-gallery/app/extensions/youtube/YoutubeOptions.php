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

namespace RoboGallery\app\extensions\youtube;

defined('WPINC') || exit;

/**
 * Settings of the YouTube type (youtubegrid): how many videos and in which order.
 * They are the "youtube" group of the youtube set of GalleryOptions
 * (robo-gallery-options meta, autosaved by the settings panel); the rest of the
 * set is the Fusion Grid one, the gallery is drawn by the same script. Where the
 * videos come from and what is changed per video are not options: YoutubeSource
 * keeps them in meta of their own.
 */
class YoutubeOptions
{
    // the most videos one gallery shows (the grid gets them in one REST answer)
    const MAX_VIDEOS = 200;

    /**
     * @return array option_id => definition, as in GalleryOptions::getOptionConfig()
     */
    public static function getOptionConfig(): array
    {
        return [

            'youtubeCount'      => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 24,
                'group'    => 'youtube',
                'params'   => ['min' => 1, 'max' => self::MAX_VIDEOS],
            ],

            // source: as the sources give them, one source after the other; custom: the order set
            // in the panel (rsg_youtubeOrder), new videos after it
            'youtubeOrder'      => [
                'type'    => 'select',
                'options' => ['source', 'newest', 'oldest', 'title', 'custom'],
                'default' => 'source',
                'group'   => 'youtube',
            ],

            // words of the video's description (the caption / description of the card); 0 = none
            'youtubeTextLength' => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 20,
                'group'    => 'youtube',
                'params'   => ['min' => 0, 'max' => 100],
            ],

        ];
    }
}
