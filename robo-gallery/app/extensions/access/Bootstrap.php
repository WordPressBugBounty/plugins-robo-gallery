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

namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Gallery access (direct link only / token), on top of WordPress's own
 * status and password rules:
 *  - GalleryAccessManager - who may see a gallery; 403 on a token gallery's page
 *  - GalleryToken         - the per-gallery token (created on save, looked up by share links)
 *  - ExcludeListManager   - list of direct-link-only galleries, synced on options save
 *  - ListingFilter        - keeps them out of listings, search, sitemap; noindex
 *  - ShareLinks           - /gallery/share/t/<token>/ and ?rg_token= routing, editor permalinks
 * Each class hooks itself in register().
 */
class Bootstrap
{
    public function run(): void
    {
        $token          = new GalleryToken();
        $access         = new GalleryAccessManager($token);
        $excludeManager = new ExcludeListManager();

        $token->register();
        $access->register();
        $excludeManager->register();
        (new ListingFilter($excludeManager, $access))->register();
        (new ShareLinks($token, $access))->register();
    }
}
