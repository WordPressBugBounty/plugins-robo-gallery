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

namespace RoboGallery\app\extensions\posts;

defined('WPINC') || exit;

/**
 * Settings of the posts type: which posts the gallery shows. They are the
 * "posts" group of the posts set of GalleryOptions (robo-gallery-options meta,
 * autosaved by the settings panel); the rest of the set is the Fusion Grid one,
 * the posts gallery is drawn by the same script. The hand-picked list and the
 * images chosen for posts are not options: PostsSource keeps them in meta of
 * their own.
 */
class PostsOptions
{
    // the most posts one gallery shows (the grid gets them in one REST answer)
    const MAX_POSTS = 100;

    // the most terms the query filters by
    const MAX_TERMS = 100;

    /**
     * @return array option_id => definition, as in GalleryOptions::getOptionConfig()
     */
    public static function getOptionConfig(): array
    {
        return [

            // query: the newest posts of a type (filtered by terms); manual: the hand-picked list
            'postsSource'        => [
                'type'    => 'select',
                'options' => ['query', 'manual'],
                'default' => 'query',
                'group'   => 'posts',
            ],

            'postsPostType'      => [
                'type'    => 'select',
                'options' => PostsSource::postTypes(),
                'default' => 'post',
                'group'   => 'posts',
            ],

            // term ids of the post type's taxonomies; a post with any of them is shown
            'postsTerms'         => [
                'type'     => 'multiselect',
                'sanitize' => 'ids',
                'default'  => [],
                'group'    => 'posts',
                'params'   => ['max' => self::MAX_TERMS],
            ],

            'postsCount'         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 12,
                'group'    => 'posts',
                'params'   => ['min' => 1, 'max' => self::MAX_POSTS],
            ],

            'postsOrderBy'       => [
                'type'    => 'select',
                'options' => ['date', 'modified', 'title', 'menu_order', 'comment_count', 'rand'],
                'default' => 'date',
                'group'   => 'posts',
            ],

            'postsOrder'         => [
                'type'    => 'select',
                'options' => ['DESC', 'ASC'],
                'default' => 'DESC',
                'group'   => 'posts',
            ],

            // a post without an image: a placeholder picture, or left out
            'postsNoImage'       => [
                'type'    => 'select',
                'options' => ['placeholder', 'skip'],
                'default' => 'placeholder',
                'group'   => 'posts',
            ],

            'postsLinkTarget'    => [
                'type'    => 'select',
                'options' => ['self', 'blank'],
                'default' => 'self',
                'group'   => 'posts',
            ],

            // words of the excerpt (the caption / description of the card); 0 = none
            'postsExcerptLength' => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 20,
                'group'    => 'posts',
                'params'   => ['min' => 0, 'max' => 100],
            ],

        ];
    }
}
