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

namespace RoboGallery\app\extensions\restapi\models;

defined('WPINC') || exit;

/**
 * Imports one image from a URL into the media library: a direct image link, or
 * a page (a social network post, a photo site) that names its image in
 * og:image / twitter:image - what a link preview shows. Pages that show their
 * image only to logged-in visitors (often Instagram, Facebook) cannot be read.
 *
 * Safety:
 *  - wp_safe_remote_get(): http(s) only, no local or private addresses, the
 *    same check for every redirect; links to this site itself are refused
 *    too (core lets its own host through);
 *  - a size limit on every download, one hop from a page to its image;
 *  - the file type comes from the bytes (wp_get_image_mime()), not from the
 *    server's header or the URL, and only raster image types are accepted;
 *  - the file goes through media_handle_sideload(), the core upload checks.
 */
class ImageImporter
{
    const TIMEOUT        = 20;
    const MAX_PAGE_BYTES = 2097152; // 2 MB of HTML is far more than the <head> needs
    const MAX_REDIRECTS  = 3;

    // extension by the type found in the bytes
    const IMAGE_TYPES = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    );

    /**
     * @param string $url
     * @param int    $galleryId the new attachment's parent, as for uploads in the editor
     * @return int|\WP_Error the attachment ID
     */
    public static function import(string $url, int $galleryId)
    {
        // wp_tempnam() and the sideload: admin code, not loaded in a REST request
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $url = esc_url_raw(trim($url), array('http', 'https'));
        if ('' === $url || !wp_http_validate_url($url)) {
            return self::error('robogallery_import_bad_url', __('This is not a valid public http(s) link.', 'robo-gallery'));
        }
        if (self::isOwnHost($url)) {
            return self::ownHostError();
        }

        $title = '';
        $file  = self::download($url, self::maxImageBytes() + 1);
        if (is_wp_error($file)) {
            return $file;
        }

        if (!self::isImageFile($file['path'])) {
            if (!self::isHtml($file['type'])) {
                @unlink($file['path']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
                return self::error('robogallery_import_not_image', __('The link leads neither to an image nor to a page with one.', 'robo-gallery'));
            }

            $page = self::readHead($file['path']);
            @unlink($file['path']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already

            $imageUrl = self::pageImage($page, $file['url']);
            if ('' === $imageUrl) {
                return self::error('robogallery_import_no_image', __('No image was found on this page. It may be shown only to logged-in visitors: open it, copy the image address and import that.', 'robo-gallery'));
            }
            if (self::isOwnHost($imageUrl)) {
                return self::ownHostError();
            }
            $title = self::pageTitle($page);

            // one hop only: the image itself, never another page
            $file = self::download($imageUrl, self::maxImageBytes() + 1);
            if (is_wp_error($file)) {
                return $file;
            }
            if (!self::isImageFile($file['path'])) {
                @unlink($file['path']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
                return self::error('robogallery_import_not_image', __('The image of this page is not a JPEG, PNG, GIF or WebP file.', 'robo-gallery'));
            }
        }

        return self::store($file, $galleryId, $title);
    }

    /**
     * One image by its own address (no page to read), with the same checks: a
     * video cover (models\VideoImporter). $name: the file name without extension.
     *
     * @return int|\WP_Error the attachment ID
     */
    public static function importImage(string $imageUrl, int $galleryId, string $title, string $name = '')
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $imageUrl = esc_url_raw(trim($imageUrl), array('http', 'https'));
        if ('' === $imageUrl || !wp_http_validate_url($imageUrl)) {
            return self::error('robogallery_import_bad_url', __('This is not a valid public http(s) link.', 'robo-gallery'));
        }
        if (self::isOwnHost($imageUrl)) {
            return self::ownHostError();
        }

        $file = self::download($imageUrl, self::maxImageBytes() + 1);
        if (is_wp_error($file)) {
            return $file;
        }
        if (!self::isImageFile($file['path'])) {
            @unlink($file['path']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            return self::error('robogallery_import_not_image', __('The cover is not a JPEG, PNG, GIF or WebP file.', 'robo-gallery'));
        }

        return self::store($file, $galleryId, $title, $name);
    }

    /**
     * The downloaded file into the media library (the core upload checks).
     *
     * @param array $file { path, url } of download()
     * @return int|\WP_Error the attachment ID
     */
    private static function store(array $file, int $galleryId, string $title, string $name = '')
    {
        if (filesize($file['path']) > self::maxImageBytes()) {
            @unlink($file['path']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            return self::error('robogallery_import_too_large', __('The image is larger than the upload limit of this site.', 'robo-gallery'));
        }

        $mime = wp_get_image_mime($file['path']);
        $name = sanitize_file_name($name);

        $fileArray = array(
            'name'     => '' !== $name ? $name . '.' . self::IMAGE_TYPES[$mime] : self::fileName($file['url'], $mime),
            'tmp_name' => $file['path'],
        );

        $id = media_handle_sideload($fileArray, $galleryId, '' !== $title ? $title : null);
        if (is_wp_error($id)) {
            @unlink($file['path']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            return self::error('robogallery_import_failed', $id->get_error_message());
        }

        return (int) $id;
    }

    /**
     * @return array|\WP_Error { path, type, url } - url is where the file came from after redirects
     */
    private static function download(string $url, int $maxBytes)
    {
        $path = wp_tempnam($url);
        if (!$path) {
            return self::error('robogallery_import_failed', __('Could not create a temporary file.', 'robo-gallery'), 500);
        }

        $response = wp_safe_remote_get($url, array(
            'timeout'             => self::TIMEOUT,
            'redirection'         => self::MAX_REDIRECTS,
            'stream'              => true,
            'filename'            => $path,
            'limit_response_size' => $maxBytes,
        ));

        if (is_wp_error($response)) {
            @unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            return self::error('robogallery_import_unreachable', __('The link could not be opened.', 'robo-gallery') . ' ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            @unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            /* translators: %d: HTTP status code */
            return self::error('robogallery_import_unreachable', sprintf(__('The link answered with HTTP %d.', 'robo-gallery'), $code));
        }

        $finalUrl = $url;
        if (isset($response['http_response']) && is_object($response['http_response']) && method_exists($response['http_response'], 'get_response_object')) {
            $object = $response['http_response']->get_response_object();
            if (is_object($object) && isset($object->url) && is_string($object->url)) {
                $finalUrl = $object->url;
            }
        }

        // a public link that redirected to this site: not imported either
        if (self::isOwnHost($finalUrl)) {
            @unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            return self::ownHostError();
        }

        return array(
            'path' => $path,
            'type' => (string) wp_remote_retrieve_header($response, 'content-type'),
            'url'  => $finalUrl,
        );
    }

    private static function isImageFile(string $path): bool
    {
        return array_key_exists((string) wp_get_image_mime($path), self::IMAGE_TYPES);
    }

    private static function isHtml(string $contentType): bool
    {
        return false !== stripos($contentType, 'text/html') || false !== stripos($contentType, 'application/xhtml');
    }

    /**
     * The page up to its </head> (where the link preview tags are), at most MAX_PAGE_BYTES.
     */
    private static function readHead(string $path): string
    {
        $html = (string) file_get_contents($path, false, null, 0, self::MAX_PAGE_BYTES); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local temp file
        $end  = stripos($html, '</head>');

        return false === $end ? $html : substr($html, 0, $end);
    }

    /**
     * The link preview image of a page: og:image (or its secure_url),
     * twitter:image, <link rel="image_src">; '' when there is none.
     */
    private static function pageImage(string $html, string $pageUrl): string
    {
        $tags = self::metaTags($html);
        foreach (array('og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src') as $name) {
            if (!empty($tags[$name])) {
                return self::absoluteUrl($tags[$name], $pageUrl);
            }
        }

        if (preg_match('/<link\b[^>]*\brel=["\']?image_src["\']?[^>]*>/i', $html, $link)
            && preg_match('/\bhref=("([^"]*)"|\'([^\']*)\')/i', $link[0], $href)) {
            return self::absoluteUrl(html_entity_decode($href[2] . (isset($href[3]) ? $href[3] : ''), ENT_QUOTES), $pageUrl);
        }

        return '';
    }

    private static function pageTitle(string $html): string
    {
        $tags = self::metaTags($html);
        foreach (array('og:title', 'twitter:title') as $name) {
            if (!empty($tags[$name])) {
                return sanitize_text_field($tags[$name]);
            }
        }

        return '';
    }

    /**
     * <meta property|name="..." content="..."> of the page: name => content, the first of each.
     */
    private static function metaTags(string $html): array
    {
        $tags = array();
        if (!preg_match_all('/<meta\b[^>]*>/i', $html, $matches)) {
            return $tags;
        }

        foreach ($matches[0] as $tag) {
            $name    = self::attribute($tag, 'property');
            $name    = '' !== $name ? $name : self::attribute($tag, 'name');
            $content = self::attribute($tag, 'content');
            $name    = strtolower($name);
            if ('' !== $name && '' !== $content && !isset($tags[$name])) {
                $tags[$name] = $content;
            }
        }

        return $tags;
    }

    private static function attribute(string $tag, string $name): string
    {
        if (!preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $m)) {
            return '';
        }
        $value = isset($m[4]) && '' !== $m[4] ? $m[4] : (isset($m[3]) && '' !== $m[3] ? $m[3] : $m[2]);

        return trim(html_entity_decode($value, ENT_QUOTES));
    }

    private static function absoluteUrl(string $url, string $base): string
    {
        $absolute = \WP_Http::make_absolute_url($url, $base);

        return esc_url_raw($absolute, array('http', 'https'));
    }

    /**
     * A file name from the URL, with the extension of the real type.
     */
    private static function fileName(string $url, string $mime): string
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $name = sanitize_file_name(pathinfo(rawurldecode(wp_basename($path)), PATHINFO_FILENAME));
        if ('' === $name) {
            $name = 'imported-image';
        }

        return $name . '.' . self::IMAGE_TYPES[$mime];
    }

    /**
     * A link to this site. wp_http_validate_url() lets the site's own host through
     * without the private address check; its images are in the media library
     * already, so such links are refused instead of fetched.
     */
    private static function isOwnHost(string $url): bool
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ('' === $host) {
            return false;
        }

        foreach (array(home_url(), site_url()) as $siteUrl) {
            if (strtolower((string) wp_parse_url($siteUrl, PHP_URL_HOST)) === $host) {
                return true;
            }
        }

        return false;
    }

    private static function ownHostError(): \WP_Error
    {
        return self::error('robogallery_import_own_site', __('This image is on this site already: add it from the media library.', 'robo-gallery'));
    }

    private static function maxImageBytes(): int
    {
        return (int) wp_max_upload_size();
    }

    private static function error(string $code, string $message, int $status = 400): \WP_Error
    {
        return new \WP_Error($code, $message, array('status' => $status));
    }
}
