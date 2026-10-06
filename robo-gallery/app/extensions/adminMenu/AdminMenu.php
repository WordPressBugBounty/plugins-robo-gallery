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

namespace RoboGallery\app\extensions\adminMenu;

if (!defined('WPINC')) {
    exit;
}

/**
 * Robo Gallery admin menu: the external links (Pro Version, Support, Gallery
 * Demo, Video Guides) and the menu styling. The links open in a new tab via
 * js/admin/menu.js; opening the menu page itself (no JS, middle click)
 * redirects to the same URL.
 */
class AdminMenu
{
    // Before the other Robo Gallery pages added on admin_menu 10/11 (Statistics,
    // Overview), so the order stays: Pro Version, Support, Gallery Demo,
    // Video Guides, then Settings (settings\SettingsPage, same priority, registered after).
    const MENU_PRIORITY = 9;

    public function register(): void
    {
        add_action('admin_menu', array($this, 'addPages'), self::MENU_PRIORITY);
        add_action('admin_init', array($this, 'redirectExternalPage'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    /**
     * Menu page slug => [menu title, page title, external URL].
     *
     * @return array
     */
    private function links(): array
    {
        $links = array();

        if (!ROBO_GALLERY_TYR) {
            $links['robo-gallery-gopro'] = array('Pro Version', 'Pro Version', ROBO_GALLERY_URL_UPDATEPRO);
        }

        $links['robo-gallery-support'] = array('Support', 'Robo Gallery Support', 'https://robosoft.co/go.php?product=gallery&task=support' . (ROBO_GALLERY_TYR ? '&pro=1' : ''));
        $links['robo-gallery-demo']    = array('Gallery Demo', 'Robo Gallery Demo', 'https://robosoft.co/go.php?product=gallery&task=demo');
        $links['robo-gallery-guides']  = array('Video Guides', 'Robo Gallery Video Guides', 'https://robosoft.co/go.php?product=gallery&task=guides');

        return $links;
    }

    public function addPages(): void
    {
        foreach ($this->links() as $slug => $link) {
            add_submenu_page(
                'edit.php?post_type=' . ROBO_GALLERY_TYPE_POST,
                $link[1],
                $link[0],
                'manage_options',
                $slug,
                array($this, 'renderFallback')
            );
        }
    }

    /**
     * admin_init: a menu link opened as a page goes to its external URL.
     * Fixed URLs only (never taken from the request), so wp_redirect() is fine.
     */
    public function redirectExternalPage(): void
    {
        $link = $this->currentLink();
        if (null === $link || !current_user_can('manage_options')) {
            return;
        }

        wp_redirect($link[2]);
        exit;
    }

    /**
     * Only shown if the redirect couldn't happen (output already started).
     */
    public function renderFallback(): void
    {
        $link = $this->currentLink();
        if (null === $link) {
            return;
        }

        printf(
            '<div class="wrap"><h1>%s</h1><p><a href="%s" target="_blank" rel="noopener">%s</a></p></div>',
            esc_html($link[1]),
            esc_url($link[2]),
            esc_html($link[2])
        );
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_script('robo-gallery-menu', ROBO_GALLERY_URL . 'js/admin/menu.js', array(), ROBO_GALLERY_VERSION, true);
        wp_localize_script('robo-gallery-menu', 'robo_gallery_vars', array(
            'links' => array_map(function ($link) {
                return $link[2];
            }, $this->links()),
        ));

        // no stylesheet file: a handle without src just carries the inline styles
        wp_register_style('robo-gallery-menu', false, array(), ROBO_GALLERY_VERSION);
        wp_enqueue_style('robo-gallery-menu');

        $css = '
            #adminmenu li.menu-icon-robo_gallery_table img,
            #adminmenu li[class*=menu-icon-robo_gallery_table] img {
                opacity: 1;
                max-width: 25px;
                padding-top: 5px;
            }';

        if (!ROBO_GALLERY_TYR) {
            // highlight the Pro Version link (by its URL, not its menu position);
            // js/admin/menu.js swaps the URL for the external one and adds the class.
            // Through the menu item: admin color schemes (Modern...) color an open
            // submenu with "#adminmenu .wp-has-current-submenu .wp-submenu a"
            $css .= '
            #adminmenu li.menu-icon-robo_gallery_table .wp-submenu a[href$="page=robo-gallery-gopro"],
            #adminmenu li.menu-icon-robo_gallery_table .wp-submenu a.robo-gallery-menu-robo-gallery-gopro {
                font-weight: bold;
                color: #3adb76;
            }';
        }

        wp_add_inline_style('robo-gallery-menu', $css);
    }

    /**
     * @return array|null the link of the requested menu page, if it is one of ours
     */
    private function currentLink(): ?array
    {
        $page  = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $links = $this->links();

        return isset($links[$page]) ? $links[$page] : null;
    }
}
