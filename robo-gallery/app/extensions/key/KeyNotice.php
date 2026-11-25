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

namespace RoboGallery\app\extensions\key;

defined('WPINC') || exit;

/**
 * "Update the Pro key": on the gallery list and in the gallery editor, when the loaded key is older
 * than this plugin needs (KeyPlugin::REQUIRED_VERSION). The key has no updates of its own: who may
 * install plugins gets a link to the upload screen (network_admin_url: on a single site it is admin_url,
 * on a multisite the screen is only in the network admin), where the new zip replaces the old key.
 */
final class KeyNotice
{
    public function register(): void
    {
        add_action('admin_notices', array($this, 'render'));
    }

    public function render(): void
    {
        if (!KeyPlugin::needsUpdate() || !$this->isGalleryScreen()) {
            return;
        }

        $text = sprintf(
            /* translators: 1: the installed key's version, 2: the version this plugin needs */
            __('The Robo Gallery Pro key is version %1$s; this version of Robo Gallery needs version %2$s or newer.', 'robo-gallery'),
            KeyPlugin::version(),
            KeyPlugin::REQUIRED_VERSION
        );

        $action = current_user_can('install_plugins')
            ? sprintf(
                '<a href="%s">%s</a>',
                esc_url(network_admin_url('plugin-install.php?tab=upload')),
                esc_html__('Upload the zip of the new key', 'robo-gallery')
            )
            : esc_html__('Ask the site administrator to install the new Robo Gallery Pro key.', 'robo-gallery');

        printf(
            '<div class="notice notice-warning robo-gallery-key-update-notice"><p><strong>%s</strong> %s</p></div>',
            esc_html($text),
            $action // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
        );
    }

    /* the gallery list, the editor of a gallery (new or saved) */
    private function isGalleryScreen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen && ROBO_GALLERY_TYPE_POST === $screen->post_type && in_array($screen->base, array('edit', 'post'), true);
    }
}
