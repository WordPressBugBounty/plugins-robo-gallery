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

namespace RoboGallery\app\extensions\settings;

defined('WPINC') || exit;

/**
 * Right click protection is the free plugin Disable Right Click RB (wordpress.org);
 * the Content Protection tab of the settings installs and activates it in one
 * click. Replaces the plugin's own "Right click" switch (option
 * rbs_opt_protectionEnable): a site that had it on is reminded in the tab
 * until the plugin is enabled, then the option is removed.
 */
class RightClickPlugin extends CompanionPlugin
{
    const SLUG          = 'disable-right-click-rb';
    const FILE          = 'disable-right-click-rb/disable-right-click-rb.php';
    const SETTINGS_PAGE = 'rb_disable_right_click_settings';
    const OLD_OPTION    = ROBO_GALLERY_PREFIX . 'protectionEnable';

    const ACTION     = 'robo_gallery_enable_right_click';
    const RESULT_ARG = 'robo_gallery_right_click';
    const TAB        = 'protection';

    public static function settingsUrl(): string
    {
        return admin_url('admin.php?page=' . self::SETTINGS_PAGE);
    }

    public static function wasEnabledHere(): bool
    {
        return (bool) get_option(self::OLD_OPTION, 0);
    }

    protected function onEnabled(): void
    {
        delete_option(self::OLD_OPTION);
    }
}
