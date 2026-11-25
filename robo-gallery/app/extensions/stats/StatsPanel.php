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

namespace RoboGallery\app\extensions\stats;

use RoboGallery\app\PluginConstants;

defined('WPINC') || exit;

/**
 * Statistics of the galleries list (edit.php?post_type=robo_gallery_table): a
 * "Statistics" button next to "Add New" opens a panel with the totals
 * (js/admin/stats.js) and its Reset button. Always shown (the former Settings ->
 * Statistics switch is removed); views are counted by ViewCounter.
 *
 * Replaces the old Robo Gallery -> Statistics page (includes/extensions/stats):
 * its URL now opens the list with the panel.
 */
class StatsPanel
{
    // the old Statistics page needed it
    const CAPABILITY = 'manage_options';

    const PANEL_ID     = 'robo-gallery-stats';
    const OLD_PAGE     = 'robo-gallery-stats';
    const RESET_ACTION = 'robo_gallery_reset_stats';
    const RESET_ARG    = 'robo_gallery_stats_reset';

    // the statuses the old page counted views and images of (an admin WP_Query's default)
    const SKIPPED_STATUSES = array('trash', 'auto-draft', 'inherit');

    public function register(): void
    {
        // the old page is no longer registered: WordPress denies it before admin_init
        add_action('admin_page_access_denied', array($this, 'redirectOldPage'));
        add_action('admin_post_' . self::RESET_ACTION, array($this, 'reset'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('admin_notices', array($this, 'renderPanel'));
        add_filter('removable_query_args', array($this, 'addRemovableArg'));
    }

    public function redirectOldPage(): void
    {
        global $pagenow;

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('edit.php' !== $pagenow || self::OLD_PAGE !== $page || !$this->canView()) {
            return;
        }

        wp_safe_redirect($this->listUrl() . '#' . self::PANEL_ID);
        exit;
    }

    public function reset(): void
    {
        if (!$this->canView()) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.'), 403); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
        }
        check_admin_referer(self::RESET_ACTION);

        global $wpdb;

        // every gallery that has a counter, the trashed ones too: a restored one would bring its old views back
        $galleryIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.post_id
               FROM {$wpdb->postmeta} pm
              INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE p.post_type = %s AND pm.meta_key = %s",
            ROBO_GALLERY_TYPE_POST,
            PluginConstants::VIEWS_META_KEY
        ));

        foreach ($galleryIds as $galleryId) {
            update_post_meta((int) $galleryId, PluginConstants::VIEWS_META_KEY, '0');
        }

        wp_safe_redirect(add_query_arg(self::RESET_ARG, 1, $this->listUrl()) . '#' . self::PANEL_ID);
        exit;
    }

    /**
     * @param string $hook
     */
    public function enqueueAssets($hook): void
    {
        if ('edit.php' !== $hook || !$this->isListScreen()) {
            return;
        }

        wp_enqueue_script('robo-gallery-stats', ROBO_GALLERY_URL . 'js/admin/stats.js', array(), ROBO_GALLERY_VERSION, true);
        wp_localize_script('robo-gallery-stats', 'robo_gallery_stats', array(
            'panelId' => self::PANEL_ID,
            'button'  => __('Statistics', 'robo-gallery'),
            'confirm' => __('Reset the views of all galleries?', 'robo-gallery'),
        ));

        wp_enqueue_style('robo-gallery-stats', ROBO_GALLERY_URL . 'css/admin/stats.css', array(), ROBO_GALLERY_VERSION);
    }

    /**
     * Printed with the notices, hidden; js/admin/stats.js moves it under the
     * page title and adds the button that shows it.
     */
    public function renderPanel(): void
    {
        if (!$this->isListScreen()) {
            return;
        }

        $posts  = wp_count_posts(ROBO_GALLERY_TYPE_POST);
        $totals = array(
            __('Total Views', 'robo-gallery')     => $this->totalViews(),
            __('Total Images', 'robo-gallery')    => $this->totalImages(),
            __('Total Galleries', 'robo-gallery') => $posts->publish + $posts->draft + $posts->trash,
            __('Published', 'robo-gallery')       => $posts->publish,
            __('Drafts', 'robo-gallery')          => $posts->draft,
            __('Trash', 'robo-gallery')           => $posts->trash,
        );

        if (isset($_GET[self::RESET_ARG])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s</strong></p></div>',
                esc_html__('Statistics reset successfully!', 'robo-gallery')
            );
        }
        ?>
        <div id="<?php echo esc_attr(self::PANEL_ID); ?>" class="robo-gallery-stats" hidden>
            <h2 class="screen-reader-text"><?php esc_html_e('Robo Gallery Statistics', 'robo-gallery'); ?></h2>
            <ul class="robo-gallery-stats-list">
                <?php foreach ($totals as $label => $value) : ?>
                    <li>
                        <span class="robo-gallery-stats-value"><?php echo esc_html(number_format_i18n((int) $value)); ?></span>
                        <span class="robo-gallery-stats-label"><?php echo esc_html($label); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="robo-gallery-stats-reset">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::RESET_ACTION); ?>" />
                <?php wp_nonce_field(self::RESET_ACTION); ?>
                <button type="submit" class="button"><?php esc_html_e('Reset', 'robo-gallery'); ?></button>
            </form>
        </div>
        <?php
    }

    /**
     * @param array $args
     * @return array
     */
    public function addRemovableArg($args)
    {
        $args[] = self::RESET_ARG;
        return $args;
    }

    private function canView(): bool
    {
        return current_user_can(self::CAPABILITY);
    }

    private function isListScreen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen && 'edit' === $screen->base && ROBO_GALLERY_TYPE_POST === $screen->post_type && $this->canView();
    }

    private function listUrl(): string
    {
        return admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST);
    }

    private function totalViews(): int
    {
        global $wpdb;

        // only numbers: ViewCounter restarts any other value
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
               FROM {$wpdb->postmeta} pm
              INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE p.post_type = %s AND p.post_status NOT IN (%s, %s, %s)
                AND pm.meta_key = %s AND pm.meta_value REGEXP '^[0-9]+$'",
            array_merge(array(ROBO_GALLERY_TYPE_POST), self::SKIPPED_STATUSES, array(PluginConstants::VIEWS_META_KEY))
        ));
    }

    private function totalImages(): int
    {
        global $wpdb;

        $lists = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.meta_value
               FROM {$wpdb->postmeta} pm
              INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE p.post_type = %s AND p.post_status NOT IN (%s, %s, %s) AND pm.meta_key = %s",
            array_merge(array(ROBO_GALLERY_TYPE_POST), self::SKIPPED_STATUSES, array(ROBO_GALLERY_PREFIX . 'galleryImages'))
        ));

        $images = 0;
        foreach ($lists as $list) {
            $list = maybe_unserialize($list);
            if (is_array($list)) {
                $images += count($list);
            }
        }

        return $images;
    }
}
