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

namespace RoboGallery\app\extensions\staticGallery;

defined('WPINC') || exit;

/**
 * The gallery as plain HTML - linked images with their captions, no scripts or
 * styles - for the places where the gallery's script can't run: feeds, oEmbed,
 * AMP pages and the content of REST answers get only this (roboGallery::getGallery()),
 * regular pages get it in a <noscript> for visitors and crawlers without JavaScript
 * unless Settings -> SEO & Feeds switches that off. The number of images is limited
 * there too: one limit for feeds, one for the rest.
 *
 * Filter robo_gallery_static_request (bool $static) adds other such places, e.g.
 * a PDF or newsletter plugin rendering the content.
 */
class StaticGallery
{
    // option names without ROBO_GALLERY_PREFIX (settings\SettingsPage); 0 images = all
    const NOSCRIPT_OPTION   = 'noscriptImages';
    const FEED_LIMIT_OPTION = 'feedImagesLimit';
    const LIMIT_OPTION      = 'staticImagesLimit';
    const DEFAULT_LIMIT     = 20;

    // small square previews (even grid cells), no srcset (a browser would pick a large one
    // from it); the link opens the full size
    const IMAGE_SIZE = 'thumbnail';

    /*
     * A grid of inline blocks: feed readers drop <style>, so the layout goes into
     * the attributes, and many of them and mail clients don't know flex or grid.
     * Without the styles it is a plain list.
     */
    const FIGURE_STYLE  = 'display:inline-block;vertical-align:top;width:150px;max-width:100%;margin:0 8px 8px 0;';
    const IMAGE_STYLE   = 'display:block;width:100%;height:auto;';
    const CAPTION_STYLE = 'font-size:12px;line-height:1.3;margin-top:4px;';

    public static function isStaticRequest(): bool
    {
        $static = is_feed()
            || is_embed()
            || (defined('REST_REQUEST') && REST_REQUEST)
            || (function_exists('amp_is_request') && amp_is_request());

        return (bool) apply_filters('robo_gallery_static_request', $static);
    }

    /* the <noscript> images of regular pages; on unless switched off */
    public static function isNoscriptEnabled(): bool
    {
        return '1' === (string) get_option(ROBO_GALLERY_PREFIX . self::NOSCRIPT_OPTION, '1');
    }

    /**
     * @return int images shown in the current request, 0 = all
     */
    public static function limit(): int
    {
        return self::limitOption(is_feed() ? self::FEED_LIMIT_OPTION : self::LIMIT_OPTION);
    }

    /**
     * @param string $name option name without prefix
     */
    public static function limitOption(string $name): int
    {
        return absint(get_option(ROBO_GALLERY_PREFIX . $name, self::DEFAULT_LIMIT));
    }

    /**
     * @param array $items items of the gallery's source (roboGalleryModuleSource::getItems())
     *                     or attachment ids
     * @param int   $limit images to show, 0 = all; items without an image don't count
     * @return string empty when no item has an image
     */
    public static function render(array $items, int $limit): string
    {
        $figures = '';
        $count   = 0;
        foreach ($items as $item) {
            $figure = is_array($item) ? self::renderItem($item) : self::renderAttachment((int) $item, '');
            if ('' === $figure) {
                continue;
            }
            $figures .= $figure;
            if (++$count === $limit) {
                break;
            }
        }

        return '' === $figures ? '' : '<div class="robo-gallery-static">' . $figures . '</div>';
    }

    private static function renderItem(array $item): string
    {
        // a post of a posts gallery (posts\PostItem::forStatic()): its image and title link to it
        if (isset($item['galleryType']) && 'posts' === $item['galleryType']) {
            return self::renderPost($item);
        }


        // a YouTube source item is a video, not an attachment: its thumbnail links to it
        if (isset($item['galleryType']) && 'youtube' === $item['galleryType']) {
            if (empty($item['thumb'])) {
                return '';
            }
            $title = isset($item['data']->post_title) ? (string) $item['data']->post_title : '';
            $image = self::image((string) $item['thumb'], $title, (int) ($item['sizeW'] ?? 0), (int) ($item['sizeH'] ?? 0));

            return self::figure($image, (string) ($item['videolink'] ?? ''), $title);
        }

        return self::renderAttachment((int) ($item['id'] ?? 0), (string) ($item['videolink'] ?? ''));
    }

    /**
     * A post without an image is still listed: its linked title.
     */
    private static function renderPost(array $item): string
    {
        $href  = (string) ($item['link'] ?? '');
        $title = (string) ($item['title'] ?? '');
        $id    = (int) ($item['id'] ?? 0);
        $src   = $id ? wp_get_attachment_image_src($id, self::IMAGE_SIZE) : false;

        if (!$src) {
            if ('' === $href || '' === $title) {
                return '';
            }
            return '<figure class="robo-gallery-static-item" style="' . self::FIGURE_STYLE . '">'
                . '<figcaption style="' . self::CAPTION_STYLE . '"><a href="' . esc_url($href) . '">' . esc_html($title) . '</a></figcaption>'
                . '</figure>';
        }

        $alt   = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
        $image = self::image($src[0], '' !== $alt ? $alt : $title, (int) $src[1], (int) $src[2]);

        return self::figure($image, $href, $title);
    }

    /**
     * @param string $href the item's video, else the full size image
     */
    private static function renderAttachment(int $id, string $href): string
    {
        $src = $id ? wp_get_attachment_image_src($id, self::IMAGE_SIZE) : false;
        if (!$src) {
            return '';
        }

        $image = self::image($src[0], (string) get_post_meta($id, '_wp_attachment_image_alt', true), (int) $src[1], (int) $src[2]);

        return self::figure($image, $href ?: (string) wp_get_attachment_url($id), (string) wp_get_attachment_caption($id));
    }

    private static function image(string $src, string $alt, int $width, int $height): string
    {
        return sprintf(
            '<img src="%s" alt="%s"%s loading="lazy" style="%s" />',
            esc_url($src),
            esc_attr($alt),
            $width && $height ? sprintf(' width="%d" height="%d"', $width, $height) : '',
            self::IMAGE_STYLE
        );
    }

    /**
     * @param string $image escaped <img>
     */
    private static function figure(string $image, string $href, string $caption): string
    {
        if ('' !== $href) {
            $image = '<a href="' . esc_url($href) . '">' . $image . '</a>';
        }

        return '<figure class="robo-gallery-static-item" style="' . self::FIGURE_STYLE . '">' . $image
            . ('' !== $caption ? '<figcaption style="' . self::CAPTION_STYLE . '">' . esc_html($caption) . '</figcaption>' : '')
            . '</figure>';
    }
}
