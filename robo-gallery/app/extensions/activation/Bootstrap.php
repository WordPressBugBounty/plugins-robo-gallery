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

namespace RoboGallery\app\extensions\activation;

if (!defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Plugin lifecycle:
 *  - Install - activation, post-install/update refresh (the only rewrite rules flush), overview redirect
 *  - Upgrade - once per new plugin version: settings defaults for existing galleries
 * Runs at plugin load: register_activation_hook() must be called then.
 */
class Bootstrap
{
    public function run(): void
    {
        (new Install())->register();
        (new Upgrade())->register();
    }
}
