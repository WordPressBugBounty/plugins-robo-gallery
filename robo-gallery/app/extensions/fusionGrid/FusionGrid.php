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

namespace RoboGallery\app\extensions\fusionGrid;

use RoboGallery\app\extensions\access\GalleryAccessManager;
use RoboGallery\app\extensions\popup\GalleryPopup;
use RoboGallery\app\extensions\posts\PostItem;
use RoboGallery\app\extensions\posts\PostsSource;
use RoboGallery\app\extensions\restapi\models\GalleryModel;
use RoboGallery\app\extensions\staticGallery\StaticGallery;
use RoboGallery\app\extensions\validation\CssUnits;
use RoboGallery\app\extensions\youtube\VideoItem;
use RoboGallery\app\extensions\youtube\YoutubeSource;
use RoboGallery\app\GalleryOptions;

defined('WPINC') || exit;

/**
 * The page output of a Fusion Grid gallery (GalleryTypeList::isGridScriptType(): robogrid, posts, youtubegrid),
 * called by the [robo-gallery] shortcode (shortcode/GalleryShortcode.php) once the gallery may be
 * shown; the classic types stay with includes/frontend (roboGallery).
 *
 * An empty container .RoboGalleryV5 and its config window.robogallery_config_id_<id>: the grid script
 * (react_parts/grid, assets/main.js) mounts it and loads the gallery over REST (robofields). Around it the
 * texts before / after (rsg_pretext / rsg_aftertext); in a popup when asked (GalleryPopup); the plain list
 * of the gallery in a <noscript> after it, and only that list where no script runs (StaticGallery).
 * The test stand compares this markup: keep it as it is.
 */
class FusionGrid
{
    /** @var int */
    private $id;

    /** @var string */
    private $type;

    /** @var array the shortcode's attributes */
    private $attr;

    /** @var array the gallery's settings: option id => value */
    private $options = array();

    public function __construct(int $id, string $type, array $attr)
    {
        $this->id   = $id;
        $this->type = $type;
        $this->attr = $attr;
    }

    public function render(): string
    {
        if (StaticGallery::isStaticRequest()) {
            return $this->renderStaticPage();
        }

        foreach (GalleryOptions::getWithValues($this->id) as $optionId => $option) {
            $this->options[$optionId] = $option['value'];
        }

        // one id per rendered gallery, as the classic galleries' (the popup's id is made of it)
        $uid   = 'rbs_gallery_' . uniqid();
        $popup = GalleryPopup::mode($this->attr, $this->type);

        $html = $this->textBefore()
            . '<div '
            . ' robogallery_id="' . $this->id . '" '
            // in a popup (extensions/popup): the script mounts it when the popup is opened
            . ($popup ? ' data-robo-popup="1"' : '')
            . ' class="RoboGalleryV5 RoboGallery_ID' . $this->id . '"  style="' . $this->containerStyle() . '"'
            . '>'
            . '</div>'
            . '<script>' . $this->config() . '</script>'
            . $this->textAfter()
            . FusionGridAssets::add($this->attr);

        if ($popup) {
            $html = GalleryPopup::render($this->id, $uid, $popup, GalleryPopup::text($this->attr), $html);
        }

        // after the popup, not in it: without the script its button does nothing
        $static = StaticGallery::isNoscriptEnabled() ? $this->renderStatic() : '';
        if ($static) {
            $html .= '<noscript>' . $static . '</noscript>';
        }

        return $html;
    }

    /**
     * Where the gallery's script can't run (StaticGallery::isStaticRequest()):
     * the texts and the images only, no assets or styles.
     */
    private function renderStaticPage(): string
    {
        $static = $this->renderStatic();
        if (!$static) {
            return '';
        }

        return $this->textBefore() . $static . $this->textAfter();
    }

    private function textBefore(): string
    {
        $text = get_post_meta($this->id, ROBO_GALLERY_PREFIX . 'pretext', true);
        return $text ? '<div>' . wp_kses_post($text) . '</div>' : '';
    }

    private function textAfter(): string
    {
        $text = get_post_meta($this->id, ROBO_GALLERY_PREFIX . 'aftertext', true);
        return $text ? '<div>' . wp_kses_post($text) . '</div>' : '';
    }

    private function renderStatic(): string
    {
        $limit = StaticGallery::limit();
        return (string) StaticGallery::render($this->staticItems($limit), $limit);
    }

    /**
     * The gallery's items for the plain list: the posts of a posts gallery (extensions/posts), the videos
     * of a youtubegrid one (extensions/youtube), else its own images in the order of its settings.
     *
     * @param int $limit 0 = all
     */
    private function staticItems(int $limit): array
    {
        if ('posts' === $this->type) {
            return array_map(array(PostItem::class, 'forStatic'), PostsSource::items($this->id, $limit));
        }

        if (YoutubeSource::TYPE === $this->type) {
            // with an empty cache YouTube is asked while the page renders, as for the old YouTube type; the REST request of the script then finds it cached
            $items = YoutubeSource::items($this->id)['items'];
            return array_map(array(VideoItem::class, 'forStatic'), $limit ? array_slice($items, 0, $limit) : $items);
        }

        $options = GalleryOptions::getEffective($this->id);
        $ids     = GalleryModel::get_gallery_images(
            $this->id,
            isset($options['orderby']) ? sanitize_text_field($options['orderby']) : 'order',
            $limit
        );
        // one query for the posts and their meta instead of a few per image
        if ($ids) {
            _prime_post_caches($ids, false, true);
        }
        return $ids;
    }

    /* the container's width, max width and alignment: numbers and allowlisted units only */
    private function containerStyle(): string
    {
        $style = 'width:' . esc_attr($this->width()) . ';';

        $maxWidth = $this->maxWidth();
        if ($maxWidth) {
            $style .= 'max-width:' . esc_attr($maxWidth) . ';';
        }

        // with the full width too: a max width narrower than the container needs it, or the gallery stays on the left
        $align = $this->align();
        if ($align) {
            $style .= 'margin:' . esc_attr($align) . ';';
        }

        return $style;
    }

    private function width(): string
    {
        if (!empty($this->options['widthAuto'])) {
            return '100%';
        }

        if (isset($this->options['widthValue']) && (int) $this->options['widthValue']) {
            $unit = !empty($this->options['widthType']) ? CssUnits::getCorrectSizeUnits($this->options['widthType']) : '%';
            return (int) $this->options['widthValue'] . $unit;
        }

        return '100%';
    }

    private function maxWidth(): string
    {
        if (isset($this->options['maxWidthValue']) && (int) $this->options['maxWidthValue']) {
            $unit = !empty($this->options['maxWidthType']) ? CssUnits::getCorrectSizeUnits($this->options['maxWidthType']) : '%';
            return (int) $this->options['maxWidthValue'] . $unit;
        }

        return '';
    }

    private function align(): string
    {
        switch (isset($this->options['align']) ? $this->options['align'] : '') {
            case 'right':
                return '0 0 0 auto';
            case 'left':
                return '0 auto 0 0';
            case 'center':
                return '0 auto';
        }
        return '';
    }

    /* window.robogallery_config_id_<id>: what the script needs before its first request (Block::previewConfig() too) */
    private function config(): string
    {
        // token-mode galleries: the script sends it back as rg_token in its REST
        // request; only a token the visitor already brought in the URL is echoed
        $access      = new GalleryAccessManager();
        $accessToken = $access->getValidUrlToken($this->id);

        // the REST nonce only for a logged-in visitor: a guest's request is anonymous anyway, and
        // REST answers 403 to an expired nonce - a guest page kept by a cache plugin would stop
        // working after a day. A logged-in visitor's stale nonce: the script asks admin-ajax for a new one
        $restNonce = is_user_logged_in() ? wp_create_nonce('wp_rest') : '';

        // the loading bar is shown while the gallery's settings are still being requested
        $optionConfig = GalleryOptions::getOptionConfig();
        $loadingColor = GalleryOptions::sanitizeColor(
            isset($this->options['loadingColor']) ? $this->options['loadingColor'] : '',
            $optionConfig['loadingColor']
        );
        $loadingSize = isset($this->options['loadingSize']) ? (int) $this->options['loadingSize'] : (int) $optionConfig['loadingSize']['default'];
        $loadingSize = max(1, min(20, $loadingSize));

        return ' var robogallery_config_id_' . $this->id . ' = {
            "restUrl": "' . esc_attr(get_rest_url()) . '",
            "wp_rest": ' . wp_json_encode($restNonce) . ',
            "ajaxUrl": ' . wp_json_encode(admin_url('admin-ajax.php')) . ',
            "loadingColor": ' . wp_json_encode($loadingColor) . ',
            "loadingSize": ' . $loadingSize . ',
            "errorImageUrl": "' . esc_url(ROBO_GALLERY_URL . 'images/') . '",
            "accessToken": ' . wp_json_encode($accessToken) . ','
            . $this->proofingConfig($access) . '
            "debug": false
        };';
    }

    /**
     * A proofing client opened the gallery by its link (proofing\Proofing, only with the key): the script lets it
     * choose images, sending the token as rg_token. Only then: other pages keep their markup.
     */
    private function proofingConfig(GalleryAccessManager $access): string
    {
        $client = $access->getUrlClient($this->id);
        if (!$client) {
            return '';
        }

        return '
            "proofing": ' . wp_json_encode(array(
                'token' => $client['token'],
                'name'  => $client['name'],
            )) . ',';
    }
}
