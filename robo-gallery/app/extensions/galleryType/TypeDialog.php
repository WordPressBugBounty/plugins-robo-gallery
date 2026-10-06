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

namespace RoboGallery\app\extensions\galleryType;

use RoboGallery\app\GalleryUtils;

defined('WPINC') || exit;

/**
 * The gallery type dialog (React bundle in build/): a new gallery is created
 * only with a type picked there (post-new.php?rsg_gallery_type=<source>), and
 * the editor's "Change gallery type" uses it too (TypeChange).
 *
 * Every way to add a gallery leads to it: the admin menu and the list's
 * "Add New" (js/themes.select.js opens it in place), the admin bar "New" item,
 * and post-new.php without a type (redirected to the list with ?showDialog=1).
 */
class TypeDialog
{
    const SHOW_ARG = 'showDialog';

    /** @var string */
    private $moduleUrl;

    public function __construct()
    {
        $this->moduleUrl = plugin_dir_url(__FILE__);
    }

    public function register(): void
    {
        // the admin bar is also shown on the site
        add_action('admin_bar_menu', array($this, 'fixAdminBarLink'), 999);

        if (!is_admin()) {
            return;
        }

        add_action('in_admin_header', array($this, 'enqueueAssets'));
        add_action('in_admin_header', array($this, 'printRoot'));
        add_action('admin_menu', array($this, 'fixMenuLink'));
        add_action('load-post-new.php', array($this, 'requireType'));
    }

    /**
     * The dialog is opened from the admin menu and the admin bar on any admin
     * page, so it is loaded everywhere - but only for users who may create a
     * gallery (the same users core shows those links to).
     */
    private function canCreateGalleries(): bool
    {
        $postType = get_post_type_object(ROBO_GALLERY_TYPE_POST);

        return $postType && current_user_can($postType->cap->create_posts);
    }

    private function listUrlWithDialog(): string
    {
        return admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&' . self::SHOW_ARG . '=1');
    }

    /**
     * post-new.php without a type (core links, "Add New" without JS) goes to the
     * list with the dialog open: otherwise the gallery got an empty type source
     * and no preset. Runs before core creates the auto-draft.
     */
    public function requireType(): void
    {
        global $typenow;

        if (ROBO_GALLERY_TYPE_POST !== $typenow || !empty($_GET[ROBO_GALLERY_PREFIX . 'gallery_type'])) {
            return;
        }

        wp_safe_redirect($this->listUrlWithDialog());
        exit;
    }

    /**
     * Admin menu "Add New": the page itself opens the dialog when JS did not
     * catch the click.
     */
    public function fixMenuLink(): void
    {
        global $submenu;

        $parent = 'edit.php?post_type=' . ROBO_GALLERY_TYPE_POST;
        $addNew = 'post-new.php?post_type=' . ROBO_GALLERY_TYPE_POST;

        if (!isset($submenu[$parent])) {
            return;
        }

        foreach ($submenu[$parent] as $key => $item) {
            if (isset($item[2]) && $addNew === $item[2]) {
                $submenu[$parent][$key][2] = $addNew . '&' . self::SHOW_ARG . '=1';
            }
        }
    }

    /**
     * Admin bar "New -> Robo Gallery" (core decides who gets it): opens the
     * dialog in place where it is loaded (admin), otherwise the list with it.
     *
     * @param \WP_Admin_Bar $adminBar
     */
    public function fixAdminBarLink($adminBar): void
    {
        $node = $adminBar->get_node('new-' . ROBO_GALLERY_TYPE_POST);
        if (!$node) {
            return;
        }

        $adminBar->add_node(array(
            'id'   => $node->id,
            'href' => $this->listUrlWithDialog(),
            'meta' => array(
                'onclick' => 'if(window.showRoboDialog){window.showRoboDialog();return false;}',
            ),
        ));
    }

    public function enqueueAssets(): void
    {
        if (!$this->canCreateGalleries()) {
            return;
        }

        $customThemeCode = apply_filters('robogallery_theme_initcustomcode', '');
        $configHandle    = ROBO_GALLERY_ASSETS_PREFIX . 'admin-dialog-v2-cfg';

        // j.js is an empty file: a handle to attach robo_js_config to, printed before the bundle
        wp_register_script($configHandle, $this->moduleUrl . 'build/j.js', array(), ROBO_GALLERY_VERSION, true);

        wp_localize_script($configHandle, 'robo_js_config', array(
            'imagesUrl'         => $this->moduleUrl . 'build/',
            'createUrl'         => admin_url('post-new.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&' . ROBO_GALLERY_PREFIX . 'gallery_type='),
            // the bundle appends "<source>&post=<id>", so the type arg must stay
            // last and the nonce goes before it
            'changeUrl'         => add_query_arg(
                TypeChange::NONCE_NAME,
                wp_create_nonce(TypeChange::getNonceAction(GalleryUtils::getIdGallery())),
                admin_url('post.php?action=edit')
            ) . '&' . TypeChange::NEW_TYPE_ARG . '=',
            'premiumVersion'    => ROBO_GALLERY_TYR,
            'customThemeEnable' => $customThemeCode ? true : false,
            'customThemeCode'   => $customThemeCode,
            'showDialog'        => empty($_GET[self::SHOW_ARG]) ? 0 : 1,
            // used by js/themes.select.js
            'bodyClass'         => GalleryScreens::BODY_CLASS,
        ));
        wp_enqueue_script($configHandle);

        // opens the dialog from the "Add New" links instead of following them
        wp_enqueue_script(ROBO_GALLERY_ASSETS_PREFIX . 'admin-dialog-links', $this->moduleUrl . 'js/themes.select.js', array($configHandle), ROBO_GALLERY_VERSION, true);

        wp_enqueue_script(ROBO_GALLERY_ASSETS_PREFIX . 'admin-dialog-v2', $this->moduleUrl . 'build/static/js/bundle.min.js', array(), ROBO_GALLERY_VERSION, true);
    }

    public function printRoot(): void
    {
        if ($this->canCreateGalleries()) {
            echo '<div id="rootRoboTypeDialog"></div>';
        }
    }
}
