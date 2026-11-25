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

namespace RoboGallery\app\extensions\restapi\fields;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\GalleryUtils;
use RoboGallery\app\extensions\galleryType\GalleryTypeList;
use RoboGallery\app\extensions\posts\PostItem;
use RoboGallery\app\extensions\posts\PostsSource;
use RoboGallery\app\extensions\youtube\VideoItem;
use RoboGallery\app\extensions\youtube\YoutubeSource;
use RoboGallery\app\extensions\restapi\models\GalleryModel;
use RoboGallery\app\extensions\access\GalleryAccessManager;
use RoboGallery\app\extensions\reactions\ReactionSettings;
use RoboGallery\app\extensions\search\SearchIndex;

defined('WPINC') || exit;

/**
 * robofields on galleries (/wp/v2/robogallery/{id}): everything the robogrid
 * frontend needs to render a gallery - images, settings, album children/tree.
 * A gallery of posts or YouTube videos has their ids in images and their data in items.
 */
class GalleryFields
{
    /**
     * Settings the robogrid frontend receives (key order = response order).
     * Everything else in GalleryOptions stays admin-only (options controller).
     */
    const PUBLIC_OPTIONS = array(
        'layout', 'columns', 'spacing', 'shadow', 'imageRadius', 'layoutAdjustment', 'targetRowHeight',
        'slidesPerView', 'slidesEffect', 'slidesFit', 'slidesShadows', 'slidesLoop', 'slidesAutoplay', 'slidesDelay', 'slidesNavButtons', 'slidesPagination', 'slidesNavColor',
        'albumIcon', 'albumIconColor', 'albumHideCoverImage',
        'hoverEffect', 'titleSource', 'descriptionSource', 'hoverColor', 'hoverInvert', 'hoverHighlight', 'hoverHighlightFullWidth',
        'hoverTitleColor', 'hoverTitleBackgroundColor', 'hoverDescriptionColor', 'hoverDescriptionBackgroundColor', 'hoverBackgroundColor', 'hoverLineColor',
        'hoverTitleFont', 'hoverDescriptionFont',
        'loadingColor', 'loadingSize',
        'breadcrumbs', 'topMenuMode', 'sideMenu', 'rootGalleryInMenu', 'rootGalleryLabel', 'navigationInterfaceColor', 'infiniteScroll',
        'interfaceMode', 'interfaceRadius', 'interfaceBackground', 'interfaceContrastColor',
        'lightboxButtons', 'lightboxTitleSource', 'lightboxDescriptionSource', 'autoPlay', 'timeout',
        'likes', 'likesIn', 'comments', 'commentsIn',
        'search', 'filterTags', 'filterCategories', 'filterType', 'searchPosition', 'filterPosition',
        'polaroidMode', 'polaroidTextColor', 'polaroidBackgroundColor', 'polaroidTitleSource', 'polaroidDescriptionSource',
        'labelButtonLoadMore', 'labelButtonUp', 'labelButtonMenu', 'labelSidebarMenuTitle', 'labelButtonLearnMore',
        'polaroidDescriptionSize', 'polaroidButtons',
        'labelSearch', 'labelFilterField', 'labelSearchFilter', 'labelFilterAll', 'labelFilterPhotos', 'labelFilterVideos', 'labelFilterLinks',
        'labelFilterNothingFound', 'labelFilterClear',
    );

    public function register()
    {
        $this->add_gallery_fields();
        $this->addTypeField();
    }

    /**
     * gallery_type (context edit, read only): the stored type (rbsGalleryUtils::getTypeGallery()), for
     * the block's list of galleries - it previews only the types of the Fusion Grid script.
     */
    private function addTypeField()
    {
        register_rest_field(ROBO_GALLERY_TYPE_POST, 'gallery_type', array(
            'get_callback' => function ($object) {
                return isset($object['id']) ? GalleryUtils::getTypeGallery((int) $object['id']) : '';
            },
            'schema'       => array(
                'description' => __('Robo Gallery type.', 'robo-gallery'),
                'type'        => 'string',
                'context'     => array('edit'),
                'readonly'    => true,
            ),
        ));
    }

    private static function getFieldsPrefix()
    {
        return 'robofields';
    }

    public function add_gallery_fields()
    {

        register_rest_field(ROBO_GALLERY_TYPE_POST, self::getFieldsPrefix(), [

            'get_callback'    => function ($object, $field_name, $request) {

                $response = [];

                if (! isset($object['id']) || ! $object['id']) {
                    return $response;
                }

                $id = (int) $object['id'];

                // private/token/password-protected galleries must not leak their
                // images or settings through the REST API to visitors who
                // couldn't view them on the gallery's own page.
                // A REST request has no gallery URL, so the robogrid script passes
                // the visitor's token (from its config, see fusionGrid/FusionGrid.php) as rg_token.
                $rgToken       = $request->get_param('rg_token');
                $rgToken       = is_string($rgToken) ? sanitize_text_field($rgToken) : null;
                $accessManager = new GalleryAccessManager();
                if (! $accessManager->canView($id, $rgToken)) {
                    return $response;
                }

                if (GalleryTypeList::isLocked(GalleryUtils::getTypeGallery($id))) {
                    return $response;
                }

                // check root_gallery param
                $root_gallery_id = (int) $request->get_param('root_gallery');
                if (! $root_gallery_id) {
                    return $response;
                }
                
                if( $object['id'] == $root_gallery_id) {
                    $response['hierarchical_children'] = GalleryModel::get_gallery_hierarchical_children( $root_gallery_id);
                } else {
                    $response['hierarchical_children'] = array();
                } 
                
                $response['children'] = GalleryModel::get_gallery_children($id, $root_gallery_id);
                    

                // with the option theme the gallery follows
                $options      = GalleryOptions::getEffective($id);
                $optionConfig = GalleryOptions::getOptionConfig();

                $orderby = isset($options['orderby']) ? sanitize_text_field($options['orderby']) : 'order';

                $response['title']    = get_post_field('post_title', $id, 'raw');

                $items = self::items($id);
                if (null !== $items) {
                    // posts / videos with their data in the form of /wp/v2/media items: the
                    // script takes them as loaded images instead of requesting /wp/v2/media
                    $response['images'] = array_column($items, 'id');
                    $response['items']  = $items;
                } else {
                    $response['images'] = GalleryModel::get_gallery_images($id, $orderby);
                }
                $response['orderby']  = $orderby;

                // the search and the filters of the root gallery's bar (its settings are the sub-galleries'
                // too) work on an index of all the items, not on the loaded pages
                $rootOptions = SearchIndex::withoutLocked(
                    $root_gallery_id === $id ? $options : GalleryOptions::getEffective($root_gallery_id)
                );
                if (SearchIndex::isOn($rootOptions)) {
                    $response['filterIndex'] = SearchIndex::build($response['images'], $items, $response['children'], $rootOptions);
                }

                $publicOptions = SearchIndex::withoutLocked($options);
                foreach (self::PUBLIC_OPTIONS as $name) {
                    $response[$name] = self::publicValue($optionConfig[$name], $publicOptions, $name);
                }

                // as the REST routes of the likes and comments read them: off for the posts and YouTube sources
                $response['likes']      = ReactionSettings::likesMode($id);
                $response['likesIn']    = ReactionSettings::likesIn($id);
                $response['comments']   = ReactionSettings::commentsMode($id);
                $response['commentsIn'] = ReactionSettings::commentsIn($id);

                // in a meta of its own, not one of the options above
                $response['customCss'] = GalleryOptions::customCss($id);

                /* access */
                $response['accessDirectLinkOnly'] = $accessManager->isDirectLinkOnly($id);
                $response['accessRequireToken']   = $accessManager->requiresToken($id);

                $response['pagination']    = isset($options['pagination']) ? $options['pagination'] : 'disable';
                $response['imagesPerPage'] = isset($options['imagesPerPage']) ? (int) $options['imagesPerPage'] : 10;

                return $response;
            },
            // read-only (no update_callback); the keys are listed in get_callback above
            'schema'          => [
                'description' => 'Robo Gallery data for the robogrid frontend: images, settings, album children and tree.',
                'type'        => 'object',
                'readonly'    => true,
            ],
        ]);
    }

    /**
     * The items of a gallery of other things than its images: posts, YouTube videos.
     *
     * @param int $id
     * @return array[]|null /wp/v2/media items; null for a gallery of images
     */
    private static function items($id)
    {
        $items = array();
        switch (GalleryUtils::getTypeGallery($id)) {
            case 'posts':
                $options = PostsSource::options($id);
                foreach (PostsSource::items($id) as $item) {
                    $items[] = PostItem::forGrid($item, $options);
                }
                return $items;

            case YoutubeSource::TYPE:
                $options = YoutubeSource::options($id);
                foreach (YoutubeSource::items($id)['items'] as $item) {
                    $items[] = VideoItem::forGrid($item, $options);
                }
                return $items;
        }
        return null;
    }

    /**
     * A stored setting as the frontend gets it: cast by its definition
     * (integer / boolean / multiselect array / string), or the default when unset.
     *
     * @param array  $option  definition from GalleryOptions
     * @param array  $options stored settings
     * @param string $name
     * @return mixed
     */
    private static function publicValue(array $option, array $options, $name)
    {
        if (!isset($options[$name])) {
            return $option['default'];
        }

        $value    = $options[$name];
        $sanitize = isset($option['sanitize']) ? $option['sanitize'] : null;

        if ('integer' === $sanitize) {
            return (int) $value;
        }
        if ('boolean' === $sanitize) {
            return (bool) $value;
        }
        if ('color' === $sanitize) {
            return GalleryOptions::sanitizeColor($value, $option);
        }
        if ('multiselect' === $option['type']) {
            return $value;
        }

        return sanitize_text_field($value);
    }

}
