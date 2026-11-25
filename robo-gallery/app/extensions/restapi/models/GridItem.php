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

namespace RoboGallery\app\extensions\restapi\models;

defined('WPINC') || exit;

/**
 * Parts of the /wp/v2/media item form in which the galleries of other things
 * than images (posts\PostItem, youtube\VideoItem) hand their items to the
 * Fusion Grid script (robofields.items, react_parts/grid schemas/image.js).
 */
class GridItem
{
    // the sizes the grid takes from a /wp/v2/media item (react_parts/grid counterUtils.js)
    const SIZES = array('medium', 'large', 'full');

    /**
     * @return array|null size name => file, width, height, source_url; null when the
     *                    attachment is not an image with a file
     */
    public static function attachmentSizes(int $imageId)
    {
        if ($imageId < 1) {
            return null;
        }

        $sizes = array();
        foreach (self::SIZES as $size) {
            $src = wp_get_attachment_image_src($imageId, $size);
            if ($src) {
                $sizes[$size] = self::size((string) $src[0], (int) $src[1], (int) $src[2]);
            }
        }
        return isset($sizes['full']) ? $sizes : null;
    }

    public static function size(string $url, int $width, int $height): array
    {
        return array(
            'file'       => wp_basename((string) wp_parse_url($url, PHP_URL_PATH)),
            'width'      => $width,
            'height'     => $height,
            'source_url' => $url,
        );
    }

    /**
     * The media_details of an item from its sizes (they must have full).
     */
    public static function mediaDetails(array $sizes): array
    {
        return array(
            'width'  => $sizes['full']['width'],
            'height' => $sizes['full']['height'],
            'file'   => $sizes['full']['file'],
            'sizes'  => $sizes,
        );
    }
}
