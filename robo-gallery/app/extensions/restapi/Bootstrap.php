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

namespace RoboGallery\app\extensions\restapi;

use RoboGallery\app\extensions\restapi\endpoints\ImagesController;
use RoboGallery\app\extensions\restapi\endpoints\OptionsController;
use RoboGallery\app\extensions\restapi\endpoints\ShareLinkController;
use RoboGallery\app\extensions\restapi\fields\AttachmentFields;
use RoboGallery\app\extensions\restapi\fields\GalleryFields;

defined('WPINC') || exit;

/**
 * The single place where the plugin's REST API is registered.
 *
 * Own routes (robogallery/v1, endpoints/):
 *  - /robogallery/v1/{gallery_id}/options     OptionsController   - gallery settings (robogrid admin panel)
 *  - /robogallery/v1/{gallery_id}/share-link  ShareLinkController - secret link: GET current, POST new
 *  - /robogallery/v1/images/{ids}             ImagesController    - "Images" field previews
 *
 * Fields on core routes (fields/):
 *  - robofields on galleries                  GalleryFields     - data for the robogrid frontend
 *  - robofields + rsg_* meta on attachments   AttachmentFields  - /wp/v2/media
 *
 * The gallery post type's own routes (/wp/v2/robogallery) are served by
 * endpoints\GalleryPostsController, attached via rest_controller_class in
 * register_post_type() (includes/rbs_gallery_init.php).
 */
class Bootstrap
{
    public function run(): void
    {
        add_action('rest_api_init', array($this, 'registerFields'));
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes(): void
    {
        (new OptionsController())->register_routes();
        (new ShareLinkController())->register_routes();
        (new ImagesController())->register_routes();
    }

    public function registerFields(): void
    {
        (new AttachmentFields())->register();
        (new GalleryFields())->register();
    }
}
