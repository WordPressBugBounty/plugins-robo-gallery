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

namespace RoboGallery\app\extensions\dashboard;

defined('WPINC') || exit;

/**
 * Robo Gallery -> Overview page: welcome text and tabs (Overview, Help & Support
 * render templates from this folder; the rest are links). Activation / update
 * redirects here once (activation\Install::maybeRedirectToOverview()).
 */
class Dashboard
{
    const PAGE_SLUG = 'overview';

    // who sees the page: whoever may activate plugins, i.e. who installed this one
    // (activation\Install redirects them here once after activation / update)
    const CAPABILITY = 'activate_plugins';

    const URL_DEMOS = 'https://www.robogallery.co/go.php?product=gallery&task=showcase';
    const URL_GOPRO = 'https://www.robogallery.co/go.php?product=gallery&task=gopro';
    const URL_SUPPORT = 'https://wordpress.org/support/plugin/robo-gallery';

    public function register(): void
    {
        // last Robo Gallery menu item, after Statistics (admin_menu 10)
        add_action('admin_menu', array($this, 'addPage'), 11);
    }

    public function addPage(): void
    {
        $hook = add_submenu_page(
            self::parentSlug(),
            'Robo Gallery Overview',
            __('Overview', 'robo-gallery'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            array($this, 'render')
        );

        add_action('admin_print_styles-' . $hook, array($this, 'enqueueStyles'));
    }

    public function enqueueStyles(): void
    {
        wp_enqueue_style('robo-gallery-overview', plugins_url('assets/style.css', __FILE__), array(), ROBO_GALLERY_VERSION);
    }

    public function render(): void
    {
        $tabs      = $this->tabs();
        $activeTab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if (!isset($tabs[$activeTab]['template'])) {
            $activeTab = 'overview';
        }
        ?>
        <div class="wrap about-wrap">
            <div class="rbsDashboardGallery-external-button">
                <h1 class="rbsDashboardGallery-title"><?php esc_html_e('Welcome to Robo Gallery', 'robo-gallery'); ?></h1>
            </div>

            <div class="about-text">
                <?php
                esc_html_e('Robo Gallery is an advanced, responsive photo gallery plugin with flexible image management tools.
                        It supports links, video, slider, and a built-in lightbox.
                        Easily customize layouts and interface styles to match your needs.
                        If you have any questions or need assistance with installation or configuration, feel free to reach out.', 'robo-gallery');

                // [ DEMOS | Get Pro Version | Support ], printed without whitespace between the tags
                foreach ($this->links() as $i => $link) {
                    printf(
                        '<span class="rbsDashboardGallery-sep">%s</span><a href="%s" target="_blank" rel="noopener">%s</a>',
                        $i ? '|' : '[',
                        esc_url($link['url']),
                        esc_html($link['title'])
                    );
                }
                ?><span class="rbsDashboardGallery-sep">]</span>
            </div>

            <h2 class="nav-tab-wrapper"><?php $this->renderTabs($tabs, $activeTab); ?></h2>

            <?php include __DIR__ . '/' . $tabs[$activeTab]['template']; ?>
        </div>
        <?php
    }

    /**
     * Tab key => title + a template in this folder, or an URL (external ones open in a new tab).
     *
     * @return array
     */
    private function tabs(): array
    {
        $tabs = array(
            'overview'    => array('title' => __('Overview', 'robo-gallery'), 'template' => 'overview.php'),
            'video-guide' => array('title' => __('Help & Support', 'robo-gallery'), 'template' => 'video_guide.php'),
            'demos'       => array('title' => __('Demos', 'robo-gallery'), 'url' => self::URL_DEMOS, 'external' => true),
        );

        if (!ROBO_GALLERY_TYR) {
            $tabs['gopro'] = array('title' => __('Get Pro Version', 'robo-gallery'), 'url' => self::URL_GOPRO, 'external' => true);
        }

        return $tabs;
    }

    private function renderTabs(array $tabs, string $activeTab): void
    {
        foreach ($tabs as $key => $tab) {
            $url = isset($tab['template'])
                ? add_query_arg(array('page' => self::PAGE_SLUG, 'tab' => $key), admin_url(self::parentSlug()))
                : $tab['url'];

            printf(
                '<a href="%s" class="nav-tab%s"%s>%s</a>',
                esc_url($url),
                $key === $activeTab ? ' nav-tab-active' : '',
                empty($tab['external']) ? '' : ' target="_blank" rel="noopener"',
                esc_html($tab['title'])
            );
        }
    }

    /**
     * The links under the welcome text.
     *
     * @return array[] each array('url' => ..., 'title' => ...)
     */
    private function links(): array
    {
        $links = array(array('url' => self::URL_DEMOS, 'title' => __('DEMOS', 'robo-gallery')));
        if (!ROBO_GALLERY_TYR) {
            $links[] = array('url' => self::URL_GOPRO, 'title' => __('Get Pro Version', 'robo-gallery'));
        }
        $links[] = array('url' => self::URL_SUPPORT, 'title' => __('Support', 'robo-gallery'));

        return $links;
    }

    private static function parentSlug(): string
    {
        return 'edit.php?post_type=' . ROBO_GALLERY_TYPE_POST;
    }
}
