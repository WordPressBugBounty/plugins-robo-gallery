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

namespace RoboGallery\app\extensions\language;

defined('WPINC') || exit;

/**
 * The plugin's own translations from languages/ (robo-gallery-<locale>.mo);
 * translations from wordpress.org in wp-content/languages/plugins/ are loaded
 * by core itself and win over these.
 */
final class TextDomain
{
    const DOMAIN = 'robo-gallery';

    public function register(): void
    {
        add_action('init', array($this, 'load'));
    }

    public function load(): void
    {
        load_plugin_textdomain(self::DOMAIN, false, dirname(plugin_basename(ROBO_GALLERY_MAIN_FILE)) . '/languages');
    }
}
