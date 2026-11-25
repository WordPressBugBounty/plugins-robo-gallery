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

namespace RoboGallery\app\extensions\restapi;

use RoboGallery\app\extensions\restapi\endpoints\AlbumHierarchyController;
use RoboGallery\app\extensions\restapi\endpoints\GalleryImagesController;
use RoboGallery\app\extensions\restapi\endpoints\ImageImportController;
use RoboGallery\app\extensions\restapi\endpoints\ImageReactionsController;
use RoboGallery\app\extensions\restapi\endpoints\OptionsController;
use RoboGallery\app\extensions\restapi\endpoints\OptionsPanelController;
use RoboGallery\app\extensions\restapi\endpoints\OptionThemesController;
use RoboGallery\app\extensions\restapi\endpoints\PostsController;
use RoboGallery\app\extensions\restapi\endpoints\YoutubeController;
use RoboGallery\app\extensions\restapi\endpoints\ServerFolderController;
use RoboGallery\app\extensions\restapi\endpoints\ShareLinkController;
use RoboGallery\app\extensions\restapi\fields\AttachmentFields;
use RoboGallery\app\extensions\restapi\fields\GalleryFields;

defined('WPINC') || exit;

/**
 * The single place where the plugin's REST API is registered.
 *
 * Own routes (robogallery/v1, endpoints/):
 *  - /robogallery/v1/{gallery_id}/options     OptionsController   - gallery settings (robogrid admin panel)
 *  - /robogallery/v1/{gallery_id}/options-panel OptionsPanelController - the settings panel's config, for the block editor's "Edit options"
 *  - /robogallery/v1/{gallery_id}/share-link  ShareLinkController - secret link: GET current, POST new
 *  - /robogallery/v1/{gallery_id}/images      GalleryImagesController - image manager: GET the list, PUT a new one
 *  - /robogallery/v1/{gallery_id}/images/import ImageImportController - image manager: import from a link
 *  - /robogallery/v1/{gallery_id}/images/video  ImageImportController - image manager: a video by its link (cover + video link)
 *  - /robogallery/v1/{gallery_id}/server-folder ServerFolderController - image manager: a server folder, GET it, POST .../import its images
 *  - /robogallery/v1/{gallery_id}/hierarchy   AlbumHierarchyController - album hierarchy of the gallery's type: GET, PUT a new arrangement
 *  - /robogallery/v1/{gallery_id}/option-theme(s) OptionThemesController - option themes: the one the gallery follows, the site's themes
 *  - /robogallery/v1/{gallery_id}/posts       PostsController - posts gallery: its posts, the hand-picked list, the image of a post, search, types, terms
 *  - /robogallery/v1/{gallery_id}/youtube     YoutubeController - YouTube gallery: its sources, videos and their changes, the order
 *  - /robogallery/v1/{gallery_id}/images/{image_id}/reactions|like|comments ImageReactionsController - likes and comments of the lightbox, for the gallery's visitors
 *
 * Not here: /robogallery/v1/{gallery_id}/proofing... (client proofing, Pro) is registered by the key plugin
 * (robogallerykey, proofing/ProofingController), on the same base class (endpoints\BaseController).
 *
 * Fields on core routes (fields/):
 *  - robofields on galleries                  GalleryFields     - data for the robogrid frontend
 *  - robofields + rsg_* meta on attachments   AttachmentFields  - /wp/v2/media
 *
 * The gallery post type's own routes (/wp/v2/robogallery) are served by
 * endpoints\GalleryPostsController, attached via rest_controller_class in
 * register_post_type() (postType\GalleryPostType).
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
        (new OptionsPanelController())->register_routes();
        (new ShareLinkController())->register_routes();
        (new GalleryImagesController())->register_routes();
        (new ImageImportController())->register_routes();
        (new ServerFolderController())->register_routes();
        (new AlbumHierarchyController())->register_routes();
        (new OptionThemesController())->register_routes();
        (new PostsController())->register_routes();
        (new YoutubeController())->register_routes();
        (new ImageReactionsController())->register_routes();
    }

    public function registerFields(): void
    {
        (new AttachmentFields())->register();
        (new GalleryFields())->register();
    }
}
