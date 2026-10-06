<?php
/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace RoboGallery\app\extensions\imageResize;

defined('WPINC') || exit;

/**
 * Extra image sizes WordPress generates for every uploaded image. The names
 * are stored in the attachments' metadata - keep them.
 */
final class ImageSizes
{
    const MASONRY_CENTER = 'RoboGalleryMansoryImagesCenter';

    // RoboGalleryMansoryImagesTop (600x1024, cropped from the top) is no longer
    // made: masonry tiles use 'large' (base-grid/resize.php). Older uploads still
    // have its files and metadata entries.

    const PRELOAD = 'RoboGalleryPreload';

    public function register(): void
    {
        add_image_size(self::MASONRY_CENTER, 600, 1024, array('center', 'center'));
        add_image_size(self::PRELOAD, 100);
    }
}
