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

namespace RoboGallery\app\extensions\proofing;

defined('WPINC') || exit;

use RoboGallery\app\extensions\key\KeyPlugin;

/**
 * What this plugin knows of client proofing (Pro). The feature itself - the clients, their choice,
 * the e-mails, the REST routes /<id>/proofing... - is in the key plugin (robogallerykey, its
 * proofing/), which gives the clients' tokens here (provide()). Without it no token is a client's:
 * the clients' links open nothing (access\GalleryAccessManager, access\ShareLinks) and the page
 * config has no "proofing" (fusionGrid\FusionGrid). The switch "proofing" stays an option of
 * GalleryOptions, so a gallery keeps it while the key is away.
 *
 * Loaded by init.php, not autoloaded: the key checks it with class_exists(..., false), and this
 * plugin's autoloader throws for a class it has no file of.
 */
final class Proofing
{
    /* the gallery meta of the key's proofing, never copied with the gallery (duplicate\GalleryDuplicate) */
    const META_KEYS = array(
        ROBO_GALLERY_PREFIX . 'proofClients',
        ROBO_GALLERY_PREFIX . 'proofPick',
        ROBO_GALLERY_PREFIX . 'proofNote',
    );

    /** @var ClientTokens|null */
    private static $tokens = null;

    /* the key's proofing module */
    public static function provide(ClientTokens $tokens): void
    {
        self::$tokens = $tokens;
    }

    /**
     * A Pro key is loaded but has no client proofing (older than 4.0.0, or without its proofing/): the
     * settings panel asks to update it (config keyUpdate) instead of requesting the key's routes, which
     * are not there. Null without a key (proofing is locked then) and with a key that has it.
     *
     * @return array|null { version - '' when the key does not tell it, required, uploadUrl - the screen
     *                    that takes the new key's zip, '' for who may not install plugins }
     */
    public static function keyUpdate(): ?array
    {
        if (!defined('ROBO_GALLERY_TYR') || !ROBO_GALLERY_TYR || self::$tokens) {
            return null;
        }

        return array(
            'version'   => KeyPlugin::version(),
            'required'  => KeyPlugin::REQUIRED_VERSION,
            // as KeyNotice: on a multisite the upload screen is only in the network admin
            'uploadUrl' => current_user_can('install_plugins') ? esc_url_raw(network_admin_url('plugin-install.php?tab=upload')) : '',
        );
    }

    /**
     * The proofing client of the gallery with this token, null without the key or while the gallery's
     * proofing is off.
     *
     * @return array|null { token, name, ... }
     */
    public static function clientByToken(int $galleryId, string $token): ?array
    {
        if (!self::$tokens || $galleryId <= 0 || '' === $token) {
            return null;
        }

        $client = self::$tokens->findByToken($galleryId, $token);

        // the page config and the access check rely on these: anything else is no client
        return is_array($client) && isset($client['token'], $client['name'])
            && is_string($client['token']) && is_string($client['name']) && hash_equals($client['token'], $token)
            ? $client
            : null;
    }

    /**
     * @return int the gallery of a client's token (its share link carries only the token), 0 when none
     */
    public static function galleryByToken(string $token): int
    {
        return self::$tokens && '' !== $token ? max(0, self::$tokens->findGallery($token)) : 0;
    }
}
