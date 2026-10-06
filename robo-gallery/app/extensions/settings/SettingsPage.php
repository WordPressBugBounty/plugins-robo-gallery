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

namespace RoboGallery\app\extensions\settings;

if (!defined('WPINC')) {
    exit;
}

/**
 * Robo Gallery -> Settings (page slug robo-gallery-settings; linked from the
 * YouTube, cache and custom CSS option panels). Saved through options.php with
 * the WordPress Settings API; every option has a sanitize callback here. Option
 * names and groups are the stored data model - keep them.
 */
class SettingsPage
{
    const SLUG = 'robo-gallery-settings';

    // shows Robo Gallery -> Statistics (includes/extensions/stats); gallery views are counted always.
    // Former "Statistics" add-on switch of the Add-ons page - the name is stored data.
    const STATS_OPTION = ROBO_GALLERY_OPTIONS . 'addon_stats';

    private $activeTab = '';

    /**
     * On unless switched off: the option is stored only once someone changes it
     * (here, or on the old Add-ons page), so a site that never did gets the page.
     */
    public static function isStatsEnabled(): bool
    {
        return (bool) get_option(self::STATS_OPTION, '1');
    }

    public function register(): void
    {
        add_action('admin_init', array($this, 'registerSettings'));
        // right after the adminMenu links, before Statistics (admin_menu 10) and Overview (11)
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
            'cache'      => array(__('Cache Settings', 'robo-gallery'), 'robo_gallery_settings_cache', 'renderCacheTab'),
            'assets'     => array(__('Custom JS\CSS', 'robo-gallery'), 'robo_gallery_settings_assets', 'renderAssetsTab'),
            'comp'       => array(__('Compatibility Settings', 'robo-gallery'), 'robo_gallery_settings_comp', 'renderCompatibilityTab'),
            'post'       => array(__('Create Post Settings', 'robo-gallery'), 'robo_gallery_settings_post', 'renderPostTab'),
            'seo'        => array(__('SEO Optimization', 'robo-gallery'), 'robo_gallery_settings_seo', 'renderSeoTab'),
            'youtube'    => array(__('Youtube API', 'robo-gallery'), 'robo_gallery_settings_youtube', 'renderYoutubeTab'),
            'protection' => array(__('Content Protection', 'robo-gallery'), 'robo_gallery_settings_protection', 'renderProtectionTab'),
            'stats'      => array(__('Statistics', 'robo-gallery'), 'robo_gallery_settings_stats', 'renderStatsTab'),
        );
    }

    public function registerSettings(): void
    {
        $settings = array(
            'robo_gallery_settings_cache'      => array(
                'cache' => 'absint',
            ),
            'robo_gallery_settings_comp'       => array(
                'categoryShow'  => array($this, 'sanitizeSwitch'),
                'jqueryVersion' => function ($value) {
                    return $this->sanitizeChoice($value, array('build', 'robo', 'forced'), 'robo');
                },
                'fontLoad'      => function ($value) {
                    return $this->sanitizeChoice($value, array('on', 'off'), 'on');
                },
                'expressPanel'  => array($this, 'sanitizeSwitch'),
            ),
            'robo_gallery_settings_post'       => array(
                'cloneBlock' => array($this, 'sanitizeSwitch'),
            ),
            'robo_gallery_settings_seo'        => array(
                'seo' => function ($value) {
                    return $this->sanitizeChoice((string) $value, array('0', '1', '2'), '0');
                },
            ),
            'robo_gallery_settings_assets'     => array(
                'cssFiles' => array($this, 'sanitizeFileList'),
                'jsFiles'  => array($this, 'sanitizeFileList'),
            ),
            'robo_gallery_settings_youtube'    => array(
                'youtubeApiKey'    => array($this, 'sanitizeYoutubeKey'),
                'youtubeCacheTime' => 'absint',
            ),
            'robo_gallery_settings_protection' => array(
                'protectionEnable' => array($this, 'sanitizeSwitch'),
            ),
        );

        foreach ($settings as $group => $options) {
            foreach ($options as $name => $sanitize) {
                register_setting($group, ROBO_GALLERY_PREFIX . $name, array('sanitize_callback' => $sanitize));
            }
        }

        register_setting('robo_gallery_settings_stats', self::STATS_OPTION, array('sanitize_callback' => array($this, 'sanitizeSwitch')));
    }

    public function renderPage(): void
    {
        $tabs = $this->tabs();
        $tab  = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        $this->activeTab = isset($tabs[$tab]) ? $tab : 'cache';
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

    private function renderCacheTab(): void
    {
        $this->numberRow('cache', __('Clear cache timeout', 'robo-gallery'), $this->intOption('cache', 12), __('hours', 'robo-gallery'));
        $this->descriptionRow(__('This is timeout for the clear gallery cache option. Value in hours for the cleaning period of the cached resources.', 'robo-gallery'));
    }

    private function renderAssetsTab(): void
    {
        $this->fileListRow('cssFiles', __('Css Files', 'robo-gallery'), __('Just add custom CSS files to this field.', 'robo-gallery'), 'wp-content/plugins/robo-gallery/css/custom.css');
        $this->fileListRow('jsFiles', __('JS Files', 'robo-gallery'), __('Just add custom JS files to this field.', 'robo-gallery'), 'wp-content/plugins/robo-gallery/js/custom.js');
    }

    private function renderCompatibilityTab(): void
    {
        $this->radioRow('categoryShow', __('Categories Manager', 'robo-gallery'), $this->switchValue('categoryShow'), array(
            '0' => __('Show'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            '1' => __('Hide'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
        ));

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

        $this->radioRow('expressPanel', __('Express panel', 'robo-gallery'), $this->switchValue('expressPanel'), $this->enableDisable());
    }

    private function renderPostTab(): void
    {
        $this->radioRow('cloneBlock', __('Clone Block', 'robo-gallery'), $this->switchValue('cloneBlock'), array(
            '0' => __('Show'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            '1' => __('Hide'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
        ));
    }

    private function renderSeoTab(): void
    {
        $seo = (string) get_option(ROBO_GALLERY_PREFIX . 'seo', '0');
        $this->radioRow('seo', __('Add SEO content', 'robo-gallery'), in_array($seo, array('1', '2'), true) ? $seo : '0', array(
            '2' => __('Enable [thumbs]', 'robo-gallery'),
            '1' => __('Enable [thumbs + link]', 'robo-gallery'),
            '0' => __('Disable', 'robo-gallery'),
        ));
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

    private function renderProtectionTab(): void
    {
        $this->radioRow('protectionEnable', __('Right click', 'robo-gallery'), get_option(ROBO_GALLERY_PREFIX . 'protectionEnable', 0) ? '1' : '0', $this->enableDisable());
    }

    private function renderStatsTab(): void
    {
        $this->radioField(self::STATS_OPTION, __('Statistics page', 'robo-gallery'), self::isStatsEnabled() ? '1' : '0', $this->enableDisable());
        $this->descriptionRow(__('Adds the Robo Gallery -> Statistics page with the number of views of every gallery.', 'robo-gallery'));
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

    /* 0/1 switch as stored by the radios ('1' checked only for exactly 1). */
    private function switchValue(string $name): string
    {
        return 1 == get_option(ROBO_GALLERY_PREFIX . $name, '') ? '1' : '0';
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
