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

use RoboGallery\app\extensions\dashboard\Dashboard;

if (!defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Plugin lifecycle - the one place for what has to happen once after the
 * plugin is activated or updated, and the only place that flushes rewrite rules.
 *
 *  - Activation and a new plugin version (Upgrade) call requestRefresh(); the
 *    next admin request flushes the rewrite rules and redirects once to the
 *    overview page.
 *  - REWRITE_VERSION: bump it when the plugin's own rewrite rules change (the
 *    gallery post type, share links in access\ShareLinks); any request - also
 *    the frontend - then flushes once, without waiting for an admin visit.
 */
class Install
{
    // Bump when the plugin's rewrite rules change.
    const REWRITE_VERSION = '2';

    const OPTION_PENDING  = 'robo_gallery_after_install';     // set on activation / update
    const OPTION_REWRITE  = 'robo_gallery_rewrite_version';   // REWRITE_VERSION the stored rules match
    const OPTION_REDIRECT = 'robo_gallery_redirect_overview'; // redirect to the overview page once

    public function register(): void
    {
        register_activation_hook(ROBO_GALLERY_MAIN_FILE, array($this, 'activation'));

        // after every rewrite rule is registered (post type: init 10, share links: init 20)
        add_action('init', array($this, 'maybeRefresh'), 99);

        add_action('admin_init', array($this, 'maybeRedirectToOverview'));
    }

    public function activation(): void
    {
        self::requestRefresh();
    }

    /**
     * Flush the rewrite rules and show the overview page on the next admin request.
     */
    public static function requestRefresh(): void
    {
        update_option(self::OPTION_PENDING, '1');
    }

    /**
     * init (late): flush when an activation/update is pending (admin requests)
     * or when the plugin's rules changed (REWRITE_VERSION, any request).
     */
    public function maybeRefresh(): void
    {
        $pending  = is_admin() && '1' === (string) get_option(self::OPTION_PENDING, '0');
        $outdated = get_option(self::OPTION_REWRITE) !== self::REWRITE_VERSION;

        if (!$pending && !$outdated) {
            return;
        }

        // soft flush: the plugin's rules live in the rewrite_rules option, nothing
        // for .htaccess to change
        flush_rewrite_rules(false);
        update_option(self::OPTION_REWRITE, self::REWRITE_VERSION);

        if ($pending && delete_option(self::OPTION_PENDING)) {
            update_option(self::OPTION_REDIRECT, true);
        }
    }

    /**
     * admin_init: the one-time redirect to the overview page after activation /
     * update. Not in AJAX requests (admin_init runs there too), not during a
     * bulk activation and not for a user who may not open that page (e.g. an
     * author or subscriber is first in the admin after an automatic update) -
     * it waits for the next normal admin page of someone who may.
     */
    public function maybeRedirectToOverview(): void
    {
        if (!get_option(self::OPTION_REDIRECT, false) || wp_doing_ajax() || isset($_GET['activate-multi'])
            || !current_user_can(Dashboard::CAPABILITY)
        ) {
            return;
        }

        delete_option(self::OPTION_REDIRECT);
        wp_safe_redirect(admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&page=' . Dashboard::PAGE_SLUG));
        exit;
    }
}
