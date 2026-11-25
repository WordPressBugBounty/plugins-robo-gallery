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

/**
 * The tokens of the proofing clients, as the key's proofing module gives them to Proofing::provide().
 * Both answer only while the gallery's proofing is on.
 */
interface ClientTokens
{
    /**
     * @return array|null the client of the gallery with this token: at least { token, name }
     */
    public function findByToken(int $galleryId, string $token): ?array;

    /**
     * @return int the gallery of a client's token, 0 when unknown
     */
    public function findGallery(string $token): int;
}
