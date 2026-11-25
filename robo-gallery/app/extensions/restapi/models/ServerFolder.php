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

use RoboGallery\app\extensions\media\MediaFields;

defined('WPINC') || exit;

/**
 * Images in a folder on the server (uploaded there by FTP, say) into the media
 * library: the folders and images under a root (ABSPATH by default), and an
 * optional file_list.txt of the folder - JSON, file name => { long_name,
 * description, caption, alternative_text, link, type_link, video_link, tags } -
 * the image data to take along (the format of the Robo Bulk Uploader plugin). What
 * it does not give comes from the IPTC / EXIF data of the image itself (fileData()),
 * unless that is turned off.
 *
 * Safety:
 *  - only for users with the capability (manage_options by default) and upload_files;
 *  - every folder is resolved with realpath() and must be inside the root: no ".."
 *    and no symlink leads out; a file name is a name only, and the file itself must
 *    be in that folder; names starting with a dot are not listed or taken;
 *  - images only: by extension to list, by the bytes (wp_get_image_mime()) to import,
 *    within the upload size limit; the media library gets a copy (media_handle_sideload);
 *  - the original is deleted only when asked, and only after its copy was added.
 */
class ServerFolder
{
    const LIST_FILE = 'file_list.txt';

    // the file an image was added from (sourceKey()); "_": hidden from Custom Fields
    const SOURCE_META = '_robogallery_source_file';

    // a folder with more is listed up to this, the rest is said to be there
    const MAX_ENTRIES = 3000;

    // file_list.txt larger than this is not read
    const MAX_LIST_BYTES = 1048576;

    const EXTENSIONS = array('jpg', 'jpeg', 'png', 'gif', 'webp');

    const IMAGE_TYPES = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');

    /**
     * Who may look into the server's folders: by default administrators only,
     * since the folder names of the whole site are seen.
     */
    public static function canBrowse(): bool
    {
        /**
         * The capability needed to add images from a server folder.
         *
         * @param string $capability default manage_options
         */
        $capability = (string) apply_filters('robo_gallery_server_folder_capability', 'manage_options');

        return current_user_can('upload_files') && current_user_can($capability);
    }

    /**
     * The root of the browsing, normalized with a trailing slash; '' when it is not there.
     */
    public static function root(): string
    {
        /**
         * The top folder the image manager may browse.
         *
         * @param string $root default ABSPATH
         */
        $root = realpath((string) apply_filters('robo_gallery_server_folder_root', ABSPATH));

        return $root && is_dir($root) ? self::normalize($root) : '';
    }

    /**
     * A folder under the root by its path relative to the root ("photos/2024").
     *
     * @return string|\WP_Error the absolute folder, normalized with a trailing slash
     */
    public static function resolve(string $relative)
    {
        $root = self::root();
        if ('' === $root) {
            return self::error('robogallery_folder_no_root', __('The folder to browse is not on this server.', 'robo-gallery'), 500);
        }

        $relative = wp_normalize_path($relative);
        $parts    = array();
        foreach (explode('/', $relative) as $part) {
            if ('' === $part || '.' === $part) {
                continue;
            }
            if ('..' === $part || false !== strpos($part, "\0") || '.' === $part[0]) {
                return self::error('robogallery_folder_bad_path', __('This folder cannot be opened.', 'robo-gallery'));
            }
            $parts[] = $part;
        }

        $real = realpath($root . implode('/', $parts));
        if (!$real || !is_dir($real)) {
            return self::error('robogallery_folder_not_found', __('The folder was not found.', 'robo-gallery'), 404);
        }

        // a symlink may point anywhere: what counts is where it really is
        $folder = self::normalize($real);
        if (0 !== strpos($folder, $root)) {
            return self::error('robogallery_folder_bad_path', __('This folder cannot be opened.', 'robo-gallery'));
        }

        return $folder;
    }

    /**
     * The path of a folder relative to the root, as its parts.
     *
     * @return string[]
     */
    public static function segments(string $folder): array
    {
        $relative = substr($folder, strlen(self::root()));

        return array_values(array_filter(explode('/', (string) $relative), 'strlen'));
    }

    /**
     * @return array { folders: [{ name }], files: [{ name, size, url, title, hasData }], truncated }
     */
    public static function contents(string $folder): array
    {
        $names = scandir($folder);
        $data  = self::listData($folder);

        $folders   = array();
        $files     = array();
        $truncated = false;

        foreach ((array) $names as $name) {
            if ('' === $name || '.' === $name[0]) {
                continue;
            }
            if (count($folders) + count($files) >= self::MAX_ENTRIES) {
                $truncated = true;
                break;
            }

            $path = $folder . $name;
            if (is_dir($path)) {
                $folders[] = array('name' => $name);
            } elseif (is_file($path) && self::hasImageExtension($name)) {
                $item    = isset($data[$name]) ? $data[$name] : array();
                $files[] = array(
                    'name'    => $name,
                    'size'    => (int) filesize($path),
                    'url'     => self::url($path),
                    'title'   => isset($item['long_name']) && is_scalar($item['long_name']) ? sanitize_text_field((string) $item['long_name']) : '',
                    'hasData' => !empty($item),
                );
            }
        }

        $byName = function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        };
        usort($folders, $byName);
        usort($files, $byName);

        return array('folders' => $folders, 'files' => $files, 'truncated' => $truncated);
    }

    /**
     * One image of the folder into the media library, with its file_list.txt data.
     *
     * @param bool $deleteOriginal delete the file in the folder once its copy is added
     * @param bool $readFileData   take what file_list.txt does not give from the image's own data
     * @return array|\WP_Error { id, deleted }
     */
    public static function import(string $folder, string $name, int $galleryId, bool $deleteOriginal, bool $readFileData = true)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // a name, never a path: the file must be in this folder
        if ('' === $name || wp_basename($name) !== $name || '.' === $name[0] || false !== strpos($name, "\0")) {
            return self::error('robogallery_folder_bad_file', __('This file cannot be added.', 'robo-gallery'));
        }

        $real = realpath($folder . $name);
        if (!$real || !is_file($real) || self::normalize(dirname($real)) !== $folder) {
            return self::error('robogallery_folder_bad_file', __('The file was not found in this folder.', 'robo-gallery'), 404);
        }
        if (!self::hasImageExtension($name) || !in_array((string) wp_get_image_mime($real), self::IMAGE_TYPES, true)) {
            return self::error('robogallery_folder_not_image', __('Not a JPEG, PNG, GIF or WebP image.', 'robo-gallery'));
        }
        if (filesize($real) > wp_max_upload_size()) {
            return self::error('robogallery_folder_too_large', __('The image is larger than the upload limit of this site.', 'robo-gallery'));
        }

        // added before (an answer lost on the way, the same file again): that image, not a second copy
        $source   = self::sourceKey($real);
        $existing = self::findImported($source);
        if ($existing) {
            $deleted = $deleteOriginal ? @unlink($real) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- reported to the user when it fails
            return array('id' => $existing, 'deleted' => $deleted, 'reused' => true);
        }

        // the library gets a copy: media_handle_sideload() moves the file it is given
        $tmp = wp_tempnam($name);
        if (!$tmp || !copy($real, $tmp)) {
            if ($tmp) {
                @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            }
            return self::error('robogallery_folder_copy_failed', __('The file could not be read.', 'robo-gallery'), 500);
        }

        $data     = self::listData($folder);
        $item     = isset($data[$name]) ? $data[$name] : array();
        $text     = function ($key, $multiline = false) use ($item) {
            if (!isset($item[$key]) || !is_scalar($item[$key])) {
                return '';
            }
            return $multiline ? sanitize_textarea_field((string) $item[$key]) : sanitize_text_field((string) $item[$key]);
        };
        $fromFile    = $readFileData ? self::fileData($real) : array('title' => '', 'description' => '', 'tags' => '');
        $title       = $text('long_name');
        $title       = '' !== $title ? $title : $fromFile['title'];
        // not read: core, given no title, would take the file's own all the same
        if ('' === $title && !$readFileData) {
            $title = sanitize_text_field(pathinfo($name, PATHINFO_FILENAME));
        }
        $description = $text('description', true);
        // given here, as plain text like the data of file_list.txt: media_handle_sideload() would keep the file's HTML
        $postData = array(
            'post_content' => '' !== $description ? $description : $fromFile['description'],
            'post_excerpt' => $text('caption', true),
        );

        $id = media_handle_sideload(array('name' => $name, 'tmp_name' => $tmp), $galleryId, '' !== $title ? $title : null, $postData);
        if (is_wp_error($id)) {
            @unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a temp file, may be gone already
            return self::error('robogallery_folder_import_failed', $id->get_error_message());
        }

        $alt = $text('alternative_text');
        if ('' !== $alt) {
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
        }
        // the per-image meta, by the rules of the media modal (media/MediaFields)
        $meta = array(
            'link'       => isset($item['link']) ? MediaFields::sanitizeUrl($item['link']) : '',
            'type_link'  => isset($item['type_link']) ? MediaFields::sanitizeTypeLink($item['type_link']) : 0,
            'video_link' => isset($item['video_link']) ? MediaFields::sanitizeUrl($item['video_link']) : '',
            'tags'       => '' !== $text('tags') ? $text('tags') : $fromFile['tags'],
        );
        foreach ($meta as $key => $value) {
            if ($value) {
                update_post_meta($id, ROBO_GALLERY_PREFIX . 'gallery_' . $key, $value);
            }
        }

        update_post_meta($id, self::SOURCE_META, $source);

        $deleted = false;
        if ($deleteOriginal) {
            $deleted = @unlink($real); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- reported to the user when it fails
        }

        return array('id' => (int) $id, 'deleted' => $deleted, 'reused' => false);
    }

    /**
     * The file as it is now: its place, size and time. Changed or replaced, it is a new file.
     */
    private static function sourceKey(string $real): string
    {
        return md5(wp_normalize_path($real) . '|' . filesize($real) . '|' . filemtime($real));
    }

    /**
     * The image of the library this file was added as, 0 for none.
     */
    private static function findImported(string $source): int
    {
        $ids = get_posts(array(
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'meta_key'    => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one lookup per added file
            'meta_value'  => $source, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- one lookup per added file
            'fields'      => 'ids',
            'numberposts' => 1,
        ));

        return $ids ? (int) $ids[0] : 0;
    }

    /**
     * file_list.txt of the folder: file name => its data; [] when there is none or it is not valid.
     */
    private static function listData(string $folder): array
    {
        $path = $folder . self::LIST_FILE;
        if (!is_file($path) || filesize($path) > self::MAX_LIST_BYTES) {
            return array();
        }

        $data = json_decode((string) file_get_contents($path), true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file of the folder
        if (!is_array($data)) {
            return array();
        }

        return array_filter($data, 'is_array');
    }

    /**
     * The data written into the image itself (a JPEG exported from Lightroom, say: its
     * IPTC Title, Caption and Keywords), cleaned; the same fields core takes on an upload.
     *
     * @return array { title, description, tags }
     */
    private static function fileData(string $path): array
    {
        $meta = wp_read_image_metadata($path);
        if (!is_array($meta)) {
            return array('title' => '', 'description' => '', 'tags' => '');
        }

        $title = isset($meta['title']) ? sanitize_text_field((string) $meta['title']) : '';
        // as core: cameras write mere numbers here
        if (is_numeric(sanitize_title($title))) {
            $title = '';
        }

        $keywords = isset($meta['keywords']) ? array_filter((array) $meta['keywords'], 'is_scalar') : array();

        return array(
            'title'       => $title,
            'description' => isset($meta['caption']) ? sanitize_textarea_field((string) $meta['caption']) : '',
            // the format of rsg_gallery_tags: "a, b"
            'tags'        => implode(', ', array_filter(array_map('sanitize_text_field', $keywords), 'strlen')),
        );
    }

    /**
     * The address of a file for its preview, when it is under the site's folder; '' otherwise.
     */
    private static function url(string $path): string
    {
        $site = self::normalize(ABSPATH);
        $path = wp_normalize_path($path);
        if (0 !== strpos($path, $site)) {
            return '';
        }
        $parts = array_map('rawurlencode', explode('/', substr($path, strlen($site))));

        return site_url('/' . implode('/', $parts));
    }

    private static function hasImageExtension(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    private static function normalize(string $path): string
    {
        return rtrim(wp_normalize_path($path), '/') . '/';
    }

    private static function error(string $code, string $message, int $status = 400): \WP_Error
    {
        return new \WP_Error($code, $message, array('status' => $status));
    }
}
