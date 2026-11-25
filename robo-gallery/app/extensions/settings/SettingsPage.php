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

use RoboGallery\app\extensions\sitemap\GallerySitemap;
use RoboGallery\app\extensions\staticGallery\StaticGallery;

if (!defined('WPINC')) {
    exit;
}

/**
 * Robo Gallery -> Settings (page slug robo-gallery-settings; linked from the
 * YouTube and custom CSS option panels). Saved through options.php with
 * the WordPress Settings API; every option has a sanitize callback here. Option
 * names and groups are the stored data model - keep them.
 */
class SettingsPage
{
    const SLUG = 'robo-gallery-settings';

    private $activeTab = '';

    public function register(): void
    {
        add_action('admin_init', array($this, 'registerSettings'));
        // right after the adminMenu links, before Overview (admin_menu 11)
        add_action('admin_menu', array($this, 'addMenu'), \RoboGallery\app\extensions\adminMenu\AdminMenu::MENU_PRIORITY);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . ROBO_GALLERY_TYPE_POST,
            'Settings Robo Gallery',
            'Settings',
            'manage_options',
            self::SLUG,
            array($this, 'renderPage')
        );
    }

    /**
     * Tabs in display order: tab => [label, settings group, render method].
     *
     * @return array
     */
    private function tabs(): array
    {
        return array(
            // the key of the former SEO Optimization tab: its links open this one
            'seo'        => array(__('SEO & Feeds', 'robo-gallery'), 'robo_gallery_settings_seo', 'renderSeoTab'),
            // no settings: a button that enables the RightClickPlugin
            'protection' => array(__('Content Protection', 'robo-gallery'), '', 'renderProtectionTab'),
            'youtube'    => array(__('Youtube API', 'robo-gallery'), 'robo_gallery_settings_youtube', 'renderYoutubeTab'),
            'comp'       => array(__('Compatibility Settings', 'robo-gallery'), 'robo_gallery_settings_comp', 'renderCompatibilityTab'),
            // no settings: a button that enables the DuplicatePlugin
            'tools'      => array(__('Tools', 'robo-gallery'), '', 'renderToolsTab'),
        );
    }

    public function registerSettings(): void
    {
        $settings = array(
            // with the custom JS / CSS files of the former Custom JS\CSS tab
            'robo_gallery_settings_comp'       => array(
                'cssFiles'      => array($this, 'sanitizeFileList'),
                'jsFiles'       => array($this, 'sanitizeFileList'),
                'jqueryVersion' => function ($value) {
                    return $this->sanitizeChoice($value, array('build', 'robo', 'forced'), 'robo');
                },
                'fontLoad'      => function ($value) {
                    return $this->sanitizeChoice($value, array('on', 'off'), 'on');
                },
            ),
            'robo_gallery_settings_seo'        => array(
                StaticGallery::NOSCRIPT_OPTION   => array($this, 'sanitizeSwitch'),
                StaticGallery::FEED_LIMIT_OPTION => 'absint',
                StaticGallery::LIMIT_OPTION      => 'absint',
                GallerySitemap::OPTION           => array($this, 'sanitizeSwitch'),
            ),
            'robo_gallery_settings_youtube'    => array(
                'youtubeApiKey'    => array($this, 'sanitizeYoutubeKey'),
                'youtubeCacheTime' => 'absint',
            ),
        );

        foreach ($settings as $group => $options) {
            foreach ($options as $name => $sanitize) {
                register_setting($group, ROBO_GALLERY_PREFIX . $name, array('sanitize_callback' => $sanitize));
            }
        }
    }

    public function renderPage(): void
    {
        $tabs = $this->tabs();
        $tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        // the former Custom JS\CSS tab is part of Compatibility Settings
        if ('assets' === $tab) {
            $tab = 'comp';
        }
        $this->activeTab = isset($tabs[$tab]) ? $tab : key($tabs);
        list(, $group, $render) = $tabs[$this->activeTab];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Robo Gallery Settings', 'robo-gallery') . '</h1>';

        settings_errors();

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $name => $tabConfig) {
            printf(
                '<a href="%s" class="nav-tab%s">%s</a>',
                esc_url(admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&page=' . self::SLUG . '&tab=' . $name)),
                $this->activeTab === $name ? ' nav-tab-active' : '',
                esc_html($tabConfig[0])
            );
        }
        echo '</h2>';

        if ('' === $group) {
            echo '<table class="form-table">';
            $this->$render();
            echo '</table>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        echo '<table class="form-table">';

        settings_fields($group);
        do_settings_sections($group);
        $this->$render();

        echo '</table>';

        submit_button();

        echo '</form>';
        echo '</div>';
    }

    /* ---------- tabs ---------- */

    /* the former Custom JS\CSS tab, now in Compatibility Settings */
    private function renderCustomFilesRows(): void
    {
        $this->fileListRow('cssFiles', __('Css Files', 'robo-gallery'), __('Just add custom CSS files to this field.', 'robo-gallery'), 'wp-content/plugins/robo-gallery/css/custom.css');
        $this->fileListRow('jsFiles', __('JS Files', 'robo-gallery'), __('Just add custom JS files to this field.', 'robo-gallery'), 'wp-content/plugins/robo-gallery/js/custom.js');
    }

    private function renderCompatibilityTab(): void
    {
        $jquery = get_option(ROBO_GALLERY_PREFIX . 'jqueryVersion', 'robo');
        $this->radioRow('jqueryVersion', __('jQuery Version', 'robo-gallery'), $jquery, array(
            'build'  => __('Default'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'robo'   => array(__('Alternative', 'robo-gallery'), '[for the case if you have jQuery version conflicts on page]'),
            'forced' => array(__('Forced include', 'robo-gallery'), '[ for the case when Your theme do not use WordPress API ]'),
        ));

        $font = 'off' === get_option(ROBO_GALLERY_PREFIX . 'fontLoad', 'on') ? 'off' : 'on';
        $this->radioRow('fontLoad', __('Font Awesome', 'robo-gallery'), $font, array(
            'on'  => __('Load'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'off' => array(__('Don\'t load', 'robo-gallery'), '[ ' . __('for the case if Your theme already have awesome fonts loaded', 'robo-gallery') . ' ]'),
        ));

        $this->renderCustomFilesRows();
    }

    private function renderSeoTab(): void
    {
        $this->radioRow(GallerySitemap::OPTION, __('Galleries in sitemaps', 'robo-gallery'), GallerySitemap::isEnabled() ? '1' : '0', $this->enableDisable());
        $this->descriptionRow(__('Lists the gallery pages in the sitemaps of WordPress (wp-sitemap.xml), Yoast SEO and Rank Math. Galleries opened by direct link only are never listed.', 'robo-gallery'));

        $this->radioRow(StaticGallery::NOSCRIPT_OPTION, __('Images for search engines', 'robo-gallery'), StaticGallery::isNoscriptEnabled() ? '1' : '0', $this->enableDisable());
        $this->descriptionRow(__('Adds the gallery images as plain HTML in a <noscript> block: search engines and visitors without JavaScript see them. The gallery itself does not change.', 'robo-gallery'));

        $this->numberRow(StaticGallery::FEED_LIMIT_OPTION, __('Images in feeds', 'robo-gallery'), StaticGallery::limitOption(StaticGallery::FEED_LIMIT_OPTION), __('images, 0 = all', 'robo-gallery'));
        $this->descriptionRow(__('RSS feeds show the gallery as a grid of small linked images.', 'robo-gallery'));

        $this->numberRow(StaticGallery::LIMIT_OPTION, __('Images elsewhere', 'robo-gallery'), StaticGallery::limitOption(StaticGallery::LIMIT_OPTION), __('images, 0 = all', 'robo-gallery'));
        $this->descriptionRow(__('The same grid in the search engine block above, on AMP pages, in embeds and in the REST API.', 'robo-gallery'));
    }

    private function renderYoutubeTab(): void
    {
        $key = $this->sanitizeYoutubeKey(get_option(ROBO_GALLERY_PREFIX . 'youtubeApiKey', ''));
        $id  = ROBO_GALLERY_PREFIX . 'youtubeApiKey';
        ?>
        <tr>
            <th scope="row"><?php esc_html_e('Youtube Api Key', 'robo-gallery'); ?></th>
            <td>
                <fieldset>
                    <input name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($key); ?>" class="regular-text code" type="text">
                    <span id="robo-youtube-api-resultcheck"></span>
                </fieldset>
            </td>
        </tr>
        <?php
        $this->descriptionRow(sprintf(
            '%s <a href="%s" target="_blank">%s</a> %s',
            esc_html__("If you don't know how to create Youtube API key please follow ", 'robo-gallery'),
            esc_url('https://developers.google.com/youtube/v3/getting-started'),
            esc_html__('this official instruction ', 'robo-gallery'),
            esc_html__('for the google developers console', 'robo-gallery')
        ), true);

        $this->numberRow('youtubeCacheTime', __('Clear cache timeout', 'robo-gallery'), $this->intOption('youtubeCacheTime', 12), __('hours', 'robo-gallery'), 'robo-options-block-youtube-api');
        $this->descriptionRow(__('This is timeout for the clear youtube gallery cache option. Value in hours for the cleaning period of the cached resources.', 'robo-gallery'));
    }

    private function renderToolsTab(): void
    {
        $this->pluginResultNotice(DuplicatePlugin::RESULT_ARG, array(
            'enabled' => array('success', __('Duplicate Post RB is installed and active.', 'robo-gallery')),
            'failed'  => array('error', __('Could not install or activate Duplicate Post RB. Install it in Plugins -> Add New.', 'robo-gallery')),
        ));

        $status = DuplicatePlugin::status();
        ?>
        <tr>
            <th scope="row">Duplicate Post RB</th>
            <td>
                <?php if (DuplicatePlugin::isReady()) : ?>
                    <p><?php esc_html_e('Duplicate Post RB is active: its Copy link in the galleries list clones a gallery.', 'robo-gallery'); ?></p>
                <?php elseif (DuplicatePlugin::isOutdated()) : ?>
                    <p>
                        <?php
                        /* translators: %s: plugin version number */
                        echo esc_html(sprintf(__('Galleries can be cloned with Duplicate Post RB %s or newer. Please update it.', 'robo-gallery'), DuplicatePlugin::MIN_VERSION));
                        ?>
                        <?php if (current_user_can('update_plugins')) : ?>
                            <a href="<?php echo esc_url(self_admin_url('plugins.php')); ?>"><?php esc_html_e('Plugins'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress ?></a>
                        <?php endif; ?>
                    </p>
                <?php elseif (DuplicatePlugin::canEnable($status)) : ?>
                    <p><a href="<?php echo esc_url(DuplicatePlugin::enableUrl()); ?>" class="button button-primary">
                        <?php
                        if (DuplicatePlugin::STATUS_MISSING === $status) {
                            esc_html_e('Install and activate', 'robo-gallery');
                        } else {
                            esc_html_e('Activate'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
                        }
                        ?>
                    </a></p>
                <?php else : ?>
                    <p><a href="<?php echo esc_url('https://wordpress.org/plugins/' . DuplicatePlugin::SLUG . '/'); ?>" target="_blank" rel="noopener">Duplicate Post RB</a></p>
                <?php endif; ?>
                <p class="description"><?php esc_html_e('Galleries are cloned, with their images and settings, by the free plugin Duplicate Post RB from wordpress.org.', 'robo-gallery'); ?></p>
            </td>
        </tr>
        <?php
    }

    private function renderProtectionTab(): void
    {
        $this->pluginResultNotice(RightClickPlugin::RESULT_ARG, array(
            'enabled' => array('success', __('Disable Right Click RB is installed and active.', 'robo-gallery')),
            'failed'  => array('error', __('Could not install or activate Disable Right Click RB. Install it in Plugins -> Add New.', 'robo-gallery')),
        ));

        $status = RightClickPlugin::status();
        ?>
        <tr>
            <th scope="row">Disable Right Click RB</th>
            <td>
                <?php if (RightClickPlugin::STATUS_ACTIVE === $status) : ?>
                    <p>
                        <?php esc_html_e('Disable Right Click RB is active.', 'robo-gallery'); ?>
                        <a href="<?php echo esc_url(RightClickPlugin::settingsUrl()); ?>"><?php esc_html_e('Settings'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress ?></a>
                    </p>
                <?php elseif (RightClickPlugin::canEnable($status)) : ?>
                    <p><a href="<?php echo esc_url(RightClickPlugin::enableUrl()); ?>" class="button button-primary">
                        <?php
                        if (RightClickPlugin::STATUS_MISSING === $status) {
                            esc_html_e('Install', 'robo-gallery');
                        } else {
                            esc_html_e('Activate'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
                        }
                        ?>
                    </a></p>
                <?php else : ?>
                    <p><a href="<?php echo esc_url('https://wordpress.org/plugins/' . RightClickPlugin::SLUG . '/'); ?>" target="_blank" rel="noopener">Disable Right Click RB</a></p>
                <?php endif; ?>
                <p class="description"><?php esc_html_e('Protects your galleries and pages from simple copying: with the right click menu turned off, visitors can\'t just save your images ("Save image as...") or copy the text and other content of the page.', 'robo-gallery'); ?></p>
                <p class="description">
                    <?php
                    if (RightClickPlugin::STATUS_MISSING === $status) {
                        esc_html_e('The protection is done by the free plugin Disable Right Click RB: the button installs it from wordpress.org and activates it.', 'robo-gallery');
                    } else {
                        esc_html_e('The protection is done by the free plugin Disable Right Click RB.', 'robo-gallery');
                    }
                    ?>
                </p>
                <?php if (RightClickPlugin::STATUS_ACTIVE !== $status && RightClickPlugin::wasEnabledHere()) : ?>
                    <p class="description"><strong><?php esc_html_e('Right click protection was on in Robo Gallery. It moved to Disable Right Click RB: enable it to keep your images protected.', 'robo-gallery'); ?></strong></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * The result a CompanionPlugin's button came back with.
     *
     * @param string $arg     its query arg
     * @param array  $notices result => [notice type, text]
     */
    private function pluginResultNotice(string $arg, array $notices): void
    {
        $result = isset($_GET[$arg]) ? sanitize_key(wp_unslash($_GET[$arg])) : '';
        if (isset($notices[$result])) {
            printf(
                '<tr><td colspan="2"><div class="notice notice-%s inline"><p>%s</p></div></td></tr>',
                esc_attr($notices[$result][0]),
                esc_html($notices[$result][1])
            );
        }
    }

    /* ---------- field rows ---------- */

    /**
     * @param string $name    option name without prefix
     * @param string $label
     * @param string $current checked value
     * @param array  $choices value => label, or value => [label, description]
     */
    private function radioRow(string $name, string $label, string $current, array $choices): void
    {
        $this->radioField(ROBO_GALLERY_PREFIX . $name, $label, $current, $choices);
    }

    /**
     * radioRow() for a full option name.
     */
    private function radioField(string $field, string $label, string $current, array $choices): void
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo esc_html($label); ?></span></legend>
                    <?php foreach ($choices as $value => $choice) :
                        list($choiceLabel, $description) = is_array($choice) ? $choice : array($choice, '');
                        ?>
                        <label title="<?php echo esc_attr($choiceLabel); ?>">
                            <input type="radio" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($value); ?>" <?php checked($current, (string) $value); ?> />
                            <?php echo esc_html($choiceLabel); ?>
                        </label>
                        <?php if ('' !== $description) : ?>
                            <p class="description"><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                        <br />
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>
        <?php
    }

    private function numberRow(string $name, string $label, int $value, string $unit, string $rowId = ''): void
    {
        $field = ROBO_GALLERY_PREFIX . $name;
        ?>
        <tr<?php echo $rowId ? ' id="' . esc_attr($rowId) . '"' : ''; ?>>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <fieldset>
                    <input name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" value="<?php echo (int) $value; ?>" class="small-text" type="text"> <?php echo esc_html($unit); ?>
                </fieldset>
            </td>
        </tr>
        <?php
    }

    private function fileListRow(string $name, string $label, string $hint, string $sample): void
    {
        $field = ROBO_GALLERY_PREFIX . $name;
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <p><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($hint); ?></label></p>
                <textarea name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" class="large-text code" cols="50" rows="5"><?php echo esc_textarea(trim((string) get_option($field, ''))); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Path for included files from the WordPress Root Directory', 'robo-gallery'); ?><br/>
                    <?php esc_html_e('Sample path:', 'robo-gallery'); ?> <code><?php echo esc_html($sample); ?></code>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * @param string $text
     * @param bool   $isHtml text is already escaped HTML
     */
    private function descriptionRow(string $text, bool $isHtml = false): void
    {
        ?>
        <tr>
            <td colspan="2">
                <p class="description"><?php echo $isHtml ? wp_kses_post($text) : esc_html($text); ?></p>
            </td>
        </tr>
        <?php
    }

    /* ---------- current values ---------- */

    private function enableDisable(): array
    {
        return array('1' => __('Enable'), '0' => __('Disable')); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
    }

    /**
     * @param string $name
     * @param int    $default also shown for a stored 0 ("not set")
     */
    private function intOption(string $name, int $default): int
    {
        $value = (int) get_option(ROBO_GALLERY_PREFIX . $name, $default);
        return $value ?: $default;
    }

    /* ---------- sanitizing ---------- */

    /**
     * @param mixed $value
     * @return string '1' or '0'
     */
    public function sanitizeSwitch($value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * @param mixed  $value
     * @param array  $allowed
     * @param string $default
     * @return string
     */
    private function sanitizeChoice($value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Custom CSS/JS files: paths from the WordPress root (site_url() is put in
     * front of them), one per line or separated by ";". Only path characters,
     * no "..", no scheme or protocol-relative URLs - the result is loaded on
     * every gallery page.
     *
     * @param mixed $value
     * @return string one path per line
     */
    public function sanitizeFileList($value): string
    {
        $paths = preg_split('/[;\r\n]+/', (string) $value);
        $valid = array();

        foreach ($paths as $path) {
            $path = trim(str_replace('\\', '/', $path));
            if ('' === $path || false !== strpos($path, '..') || 0 === strpos($path, '//')
                || !preg_match('#^[A-Za-z0-9_\-./]+(\?[A-Za-z0-9_\-.=&]*)?$#', $path)
            ) {
                continue;
            }
            $valid[] = $path;
        }

        return implode("\n", $valid);
    }

    /**
     * @param mixed $input
     * @return string
     */
    public function sanitizeYoutubeKey($input): string
    {
        $input = sanitize_text_field((string) $input);

        if (preg_match('/^AIza[0-9A-Za-z\-_]{35}$/', $input)) {
            return $input;
        }

        // keep only key characters, at most 39 ("AIza" + 35)
        return substr(preg_replace('/[^0-9A-Za-z\-_]/', '', $input), 0, 39);
    }
}
