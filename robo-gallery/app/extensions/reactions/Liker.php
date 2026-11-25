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

namespace RoboGallery\app\extensions\reactions;

defined('WPINC') || exit;

/**
 * Who likes: a logged-in user by the user id ("u<id>"), a guest (likes "for all") by
 * a random id in the cookie COOKIE, kept in the likes only as its hash ("g<hash>").
 * A guest can like again with a new cookie, so a guest's likes are also limited by IP
 * (RATE_LIMIT per RATE_WINDOW seconds): the likes of guests are an estimate, not a vote.
 */
class Liker
{
    const COOKIE = 'robo_gallery_liker';

    const RATE_LIMIT  = 60;
    const RATE_WINDOW = 600;

    /**
     * @param bool $create give a guest a new id (and its cookie) when there is none
     * @return string the id stored in the likes, '' for a guest without a cookie
     */
    public static function current(bool $create = false): string
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            return 'u' . $userId;
        }

        $cookie = isset($_COOKIE[self::COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE])) : '';
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $cookie)) {
            if (!$create) {
                return '';
            }
            $cookie = bin2hex(random_bytes(16));
            self::sendCookie($cookie);
        }

        // keyed: the stored ids cannot be turned back into the cookies that like as those guests
        return 'g' . substr(hash_hmac('sha256', $cookie, wp_salt('auth')), 0, 24);
    }

    /**
     * One more like or unlike of a guest from this IP.
     *
     * @return bool false when the IP has used up its likes of the window
     */
    public static function takeGuestTurn(): bool
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $key = 'rsg_like_rate_' . md5($ip);

        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT) {
            return false;
        }
        set_transient($key, $count + 1, self::RATE_WINDOW);

        return true;
    }

    private static function sendCookie(string $value): void
    {
        // the rest of this request knows the guest by it, even when the cookie cannot be sent
        $_COOKIE[self::COOKIE] = $value;
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $value, array(
            'expires'  => time() + YEAR_IN_SECONDS,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }
}
