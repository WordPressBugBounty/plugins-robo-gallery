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

namespace RoboGallery\app\extensions\elementor;

defined('WPINC') || exit;

/**
 * The Robo Gallery widget of Elementor (GalleryWidget). The action runs only when Elementor is
 * active, so the widget class - it extends Elementor's - is not loaded without it.
 */
class ElementorIntegration
{
    public function register(): void
    {
        // Elementor 3.5+
        add_action('elementor/widgets/register', array($this, 'registerWidget'));
    }

    /**
     * @param \Elementor\Widgets_Manager $widgets
     */
    public function registerWidget($widgets): void
    {
        $widgets->register(new GalleryWidget());
    }
}
