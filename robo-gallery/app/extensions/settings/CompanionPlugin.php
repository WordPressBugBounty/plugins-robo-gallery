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
 * A free plugin from wordpress.org that does a job for Robo Gallery: a tab of the
 * settings installs and activates it in one click. A subclass names the plugin in
 * the constants below.
 */
abstract class CompanionPlugin
{
    const SLUG       = '';
    const FILE       = '';
    const ACTION     = '';
    const RESULT_ARG = '';
    const TAB        = ''; // the settings tab of its button

    const STATUS_ACTIVE    = 'active';
    const STATUS_INSTALLED = 'installed';
    const STATUS_MISSING   = 'missing';

    public function register(): void
    {
        add_action('admin_post_' . static::ACTION, array($this, 'enable'));
        add_filter('removable_query_args', array($this, 'addRemovableArg'));
    }

    public static function status(): string
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active(static::FILE)) {
            return self::STATUS_ACTIVE;
        }

        return array_key_exists(static::FILE, get_plugins()) ? self::STATUS_INSTALLED : self::STATUS_MISSING;
    }

    /**
     * Whether the current user may do what "Enable" needs for this status.
     */
    public static function canEnable(string $status): bool
    {
        if (self::STATUS_MISSING === $status && !current_user_can('install_plugins')) {
            return false;
        }

        return current_user_can('activate_plugins');
    }

    public static function enableUrl(): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=' . static::ACTION), static::ACTION);
    }

    public static function tabUrl(): string
    {
        return add_query_arg(
            'tab',
            static::TAB,
            admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&page=' . SettingsPage::SLUG)
        );
    }

    /**
     * admin-post: installs the plugin from wordpress.org if it is missing, then activates it.
     */
    public function enable(): void
    {
        check_admin_referer(static::ACTION);

        $status = static::status();
        if (!current_user_can('manage_options') || !static::canEnable($status)) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
        }

        if (self::STATUS_MISSING === $status) {
            $installed = $this->install();

            if (null === $installed) {
                // the site needs FTP credentials: WordPress's own install screen asks for them
                wp_safe_redirect(add_query_arg(
                    array(
                        'action'   => 'install-plugin',
                        'plugin'   => static::SLUG,
                        '_wpnonce' => wp_create_nonce('install-plugin_' . static::SLUG),
                    ),
                    self_admin_url('update.php')
                ));
                exit;
            }

            if (!$installed) {
                $this->redirectBack('failed');
            }
        }

        if (self::STATUS_ACTIVE !== $status && is_wp_error(activate_plugin(static::FILE))) {
            $this->redirectBack('failed');
        }

        $this->onEnabled();
        $this->redirectBack('enabled');
    }

    /**
     * @param array $args
     * @return array
     */
    public function addRemovableArg($args)
    {
        $args[] = static::RESULT_ARG;
        return $args;
    }

    protected function onEnabled(): void
    {
    }

    /**
     * @return bool|null null when the filesystem needs credentials
     */
    private function install()
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        if (!$this->filesystemReady()) {
            return null;
        }

        $api = plugins_api('plugin_information', array(
            'slug'   => static::SLUG,
            'fields' => array('sections' => false),
        ));
        if (is_wp_error($api) || empty($api->download_link)) {
            return false;
        }

        // no output: the result is shown after the redirect
        $result = (new \Plugin_Upgrader(new \WP_Ajax_Upgrader_Skin()))->install($api->download_link);

        return true === $result;
    }

    /**
     * False when WordPress can't write plugins without asking for credentials.
     */
    private function filesystemReady(): bool
    {
        ob_start();
        $credentials = request_filesystem_credentials('', '', false, WP_PLUGIN_DIR);
        ob_end_clean();

        return false !== $credentials && WP_Filesystem($credentials, WP_PLUGIN_DIR);
    }

    private function redirectBack(string $result): void
    {
        wp_safe_redirect(add_query_arg(static::RESULT_ARG, $result, static::tabUrl()));
        exit;
    }
}
