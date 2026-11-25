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

namespace RoboGallery\app\extensions\popup;

use RoboGallery\app\extensions\galleryType\GalleryTypeList;

defined('WPINC') || exit;

/**
 * A gallery shown in a popup: the page gets a button or a link, the gallery is
 * in a hidden window after it. Asked by the shortcode attribute popup="button|link"
 * (the block's popup / popupText, Block::render()) with popup_text for the label.
 *
 * Only the types drawn by the Fusion Grid script (GalleryTypeList::isGridScriptType()):
 * their container is marked data-robo-popup (fusionGrid/FusionGrid.php), the script
 * (react_parts/grid src/popup/) does not mount it with the page, but when the popup
 * is opened the first time - the grid measures its width and loads its images then.
 * The other types are shown on the page as before.
 *
 * popup="window": the window only, no button / link of its own - it is opened by links in the text
 * (PopupLinks: <a href="#rg<id>">, the block editor's "Robo Gallery" link format).
 */
class GalleryPopup
{
    const MODES = array('button', 'link', self::WINDOW);

    // the window without a trigger: links in the text open it (PopupLinks)
    const WINDOW = 'window';

    const MAX_TEXT = 200;

    /**
     * The popup mode the shortcode asks for, if the gallery's type has one.
     *
     * @param array  $attr shortcode attributes
     * @param string $type stored gallery type
     * @return string a MODES value, '' = on the page
     */
    public static function mode($attr, $type): string
    {
        $mode = is_array($attr) && isset($attr['popup']) && is_string($attr['popup']) ? strtolower(trim($attr['popup'])) : '';

        if (!in_array($mode, self::MODES, true) || !GalleryTypeList::isGridScriptType($type)) {
            return '';
        }
        return $mode;
    }

    /**
     * The label of the button / link: popup_text, else a default.
     *
     * @param array $attr shortcode attributes
     */
    public static function text($attr): string
    {
        $text = is_array($attr) && isset($attr['popup_text']) && is_string($attr['popup_text'])
            ? sanitize_text_field($attr['popup_text'])
            : '';
        $text = function_exists('mb_substr') ? mb_substr($text, 0, self::MAX_TEXT) : substr($text, 0, self::MAX_TEXT);

        return '' !== $text ? $text : __('Open gallery', 'robo-gallery');
    }

    /**
     * The trigger and the popup with the gallery in it.
     *
     * @param int    $galleryId
     * @param string $uid         unique on the page (roboGallery::$galleryId)
     * @param string $mode        a MODES value
     * @param string $text        of text(), not shown for WINDOW
     * @param string $galleryHtml the gallery as it is put on a page
     */
    public static function render(int $galleryId, string $uid, string $mode, string $text, string $galleryHtml): string
    {
        $popupId = 'robo-gallery-popup-' . sanitize_html_class($uid);

        $title = trim(wp_strip_all_tags(get_the_title($galleryId)));
        if ('' === $title) {
            $title = __('Gallery', 'robo-gallery');
        }

        $triggerClass = 'link' === $mode
            ? 'robo-gallery-popup-trigger robo-gallery-popup-trigger--link'
            : 'robo-gallery-popup-trigger robo-gallery-popup-trigger--button wp-element-button';

        $trigger = self::WINDOW === $mode
            ? ''
            : '<button type="button" class="' . esc_attr($triggerClass) . '"'
                . ' data-robo-popup-open="' . esc_attr($popupId) . '" aria-haspopup="dialog" aria-controls="' . esc_attr($popupId) . '">'
                . esc_html($text)
                . '</button>';

        return '<div class="robo-gallery-popup-wrap">'
            . $trigger
            . '<div id="' . esc_attr($popupId) . '" class="robo-gallery-popup" role="dialog" aria-modal="true" aria-label="' . esc_attr($title) . '" hidden>'
            . '<div class="robo-gallery-popup__overlay" data-robo-popup-close></div>'
            // focusable: a click in it keeps the focus in the popup, so Escape reaches it
            . '<div class="robo-gallery-popup__window" tabindex="-1">'
            . '<button type="button" class="robo-gallery-popup__close" data-robo-popup-close aria-label="' . esc_attr__('Close', 'robo-gallery') . '">&times;</button>'
            . '<div class="robo-gallery-popup__body">' . $galleryHtml . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
