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

namespace RoboGallery\app;

use RoboGallery\app\extensions\classic\ClassicOptions;
use RoboGallery\app\extensions\optionThemes\GalleryTheme;
use RoboGallery\app\extensions\posts\PostsOptions;
use RoboGallery\app\extensions\slider\SliderOptions;
use RoboGallery\app\extensions\validation\CssColor;
use RoboGallery\app\extensions\youtube\YoutubeOptions;

defined('WPINC') || exit;

/**
 * Gallery settings stored in the robo-gallery-options meta (all of them for the
 * robogrid/v5 and slider galleries, access for every type): definitions and
 * defaults (getOptionConfig), reading and saving (getStored / getEffective /
 * getWithValues / save) and sanitizing (sanitizeValue).
 * The one place that reads/writes this meta: the REST options controller
 * (read/write), the robofields REST field and the access extension (read).
 * getEffective() adds the option theme the gallery follows (extensions/optionThemes).
 *
 * Each type has its set of options (setOf()): the robogrid its own, the slider
 * its own (extensions/slider/SliderOptions), a classic type the fields of the
 * old boxes (extensions/classic/ClassicOptions), the posts and YouTube
 * (youtubegrid) types the robogrid set and their "posts" / "youtube" group
 * (extensions/posts/PostsOptions, extensions/youtube/YoutubeOptions; the same
 * script draws them); the options of SHARED_GROUPS belong to every set.
 *
 * An option with 'meta' is kept in that meta of its own, not in the options
 * array: the text before / after the gallery stays where the old "Text Addons"
 * box saved it, and the frontend reads it from there (modules/class/addtexts.php);
 * customCss too (customCss()): the array is written unslashed, which would eat the
 * backslashes of CSS escapes. The classic options ('store') stay in the rsg_* meta of the old boxes, read and
 * written by ClassicOptions.
 */
class GalleryOptions
{
    const SET_ROBOGRID = 'robogrid';

    const SET_SLIDER = 'slider';

    const SET_CLASSIC = 'classic';

    const SET_POSTS = 'posts';

    const SET_YOUTUBE = 'youtube';

    // the options of every type: who may see the gallery and the text around it
    const SHARED_GROUPS = array('access', 'text');

    /*
     * The access options of the versions before accessDirectLinkOnly (in the same meta, true /
     * false): "Private" (accessModePrivate) - out of listings, by its link; "Token Mode"
     * (accessModeToken) - with "Private" only, the link must carry the gallery's token.
     * accessModePassword was a copy of the WordPress post password, which stays; accessMode
     * was never applied. Read as the current options by readLegacyAccess(); activation\Upgrade
     * moves them once and drops them, save() drops them too (not known options).
     */
    const LEGACY_ACCESS_KEYS = array('accessModePrivate', 'accessModeToken', 'accessModePassword', 'accessMode');

    /*
     * The Google Fonts a hover text may have (the 50 most popular of their catalog and five handwritten ones); '': the
     * gallery's own font. The grid script makes the font's address and CSS from the name, so only
     * these names are kept. The same list: react_parts/options data/formData.js hoverFontArray.
     */
    const HOVER_FONTS = array(
        '',
        'Abel',
        'Archivo',
        'Archivo Black',
        'Arimo',
        'Barlow',
        'Barlow Condensed',
        'Bebas Neue',
        'Bricolage Grotesque',
        'Caveat',
        'Dancing Script',
        'DM Sans',
        'Dosis',
        'EB Garamond',
        'Figtree',
        'Funnel Sans',
        'Google Sans',
        'Great Vibes',
        'IBM Plex Sans',
        'Inter',
        'Inter Tight',
        'JetBrains Mono',
        'Lato',
        'Libre Baskerville',
        'Lobster',
        'Lora',
        'Lusitana',
        'Manrope',
        'Merriweather',
        'Montserrat',
        'Montserrat Alternates',
        'Mulish',
        'Nunito',
        'Nunito Sans',
        'Open Sans',
        'Oswald',
        'Outfit',
        'Pacifico',
        'Playfair Display',
        'Plus Jakarta Sans',
        'PT Sans',
        'PT Serif',
        'Quattrocento Sans',
        'Quicksand',
        'Raleway',
        'Roboto',
        'Roboto Condensed',
        'Roboto Mono',
        'Roboto Slab',
        'Rubik',
        'Smooch Sans',
        'Sora',
        'Source Sans 3',
        'Titan One',
        'Ubuntu',
        'Work Sans',
    );

    /**
     * @param string $type gallery type
     * @return string the option set of the type
     */
    public static function setForType($type): string
    {
        if ('slider' === $type) {
            return self::SET_SLIDER;
        }

        if ('posts' === $type) {
            return self::SET_POSTS;
        }

        if ('youtubegrid' === $type) {
            return self::SET_YOUTUBE;
        }

        return 'robogrid' === $type ? self::SET_ROBOGRID : self::SET_CLASSIC;
    }

    /**
     * @param int $galleryId
     * @return string the option set of the gallery's stored type
     */
    public static function setOf($galleryId): string
    {
        return self::setForType(GalleryUtils::getTypeGallery((int) $galleryId));
    }

    /**
     * @param string $set SET_ROBOGRID / SET_SLIDER / SET_CLASSIC / SET_POSTS / SET_YOUTUBE
     * @return array option_id => definition (type, sanitize, default, group, options/params, meta)
     */
    public static function getOptionConfig($set = self::SET_ROBOGRID)
    {
        $robogrid = self::robogridOptionConfig();
        if (self::SET_ROBOGRID === $set) {
            return $robogrid;
        }

        if (self::SET_POSTS === $set) {
            return $robogrid + PostsOptions::getOptionConfig();
        }

        if (self::SET_YOUTUBE === $set) {
            return $robogrid + YoutubeOptions::getOptionConfig();
        }

        $shared = array_filter($robogrid, function ($option) {
            return in_array($option['group'], self::SHARED_GROUPS, true);
        });

        if (self::SET_SLIDER === $set) {
            return SliderOptions::getOptionConfig() + $shared;
        }

        // a classic gallery has no shadow root and its own custom CSS (classicCustomCss)
        unset($shared['customCss']);

        return ClassicOptions::getOptionConfig() + $shared;
    }

    /**
     * @return array option_id => definition of the robogrid set
     */
    private static function robogridOptionConfig()
    {

        return [

            'widthAuto'                       => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'align'                           => [
                'type'    => 'select',
                'options' => ['left', 'right', 'center', 'no'],
                'default' => 'center',
                'group'   => 'general',
            ],

            'widthValue'                      => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 100,
                'group'    => 'general',
            ],

            'widthType'                       => [
                'type'    => 'select',
                'options' => ['%', 'px', 'rem', 'em', 'vw'],
                'default' => '%',
                'group'   => 'general',
            ],

            'maxWidthValue'                   => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 100,
                'group'    => 'general',
            ],

            'maxWidthType'                    => [
                'type'    => 'select',
                'options' => ['%', 'px', 'rem', 'em', 'vw'],
                'default' => '%',
                'group'   => 'general',
            ],

            'orderby'                         => [
                'type'    => 'select',
                'options' => ['order', 'orderU', 'random', 'title', 'titleU', 'date', 'dateU'],
                'default' => 'order',
                'group'   => 'general',
            ],

            'layout'                          => [
                'type'    => 'select',
                'options' => ['grid', 'masonry', 'columns', 'rows', 'slider'],
                'default' => 'grid',
                'group'   => 'general',
            ],

            'layoutAdjustment'                => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'targetRowHeight'                 => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 500,
                'group'    => 'general',
                'params'   => ['min' => 50, 'max' => 1000],
            ],

            'columns'                         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 0,
                'group'    => 'general',
                'params'   => ['min' => 0, 'max' => 100],
            ],

            'spacing'                         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 10,
                'group'    => 'general',
                'params'   => ['min' => 0, 'max' => 20],
            ],

            'loadingColor'                    => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#686868',
                'group'    => 'thumbnails',
            ],

            'loadingSize'                     => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'group'    => 'thumbnails',
                'default'  => 5,
                'params'   => ['min' => 1, 'max' => 20],
            ],

            'shadow'                          => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 0,
                'group'    => 'general',
                'params'   => ['min' => 0, 'max' => 24],
            ],

            // the corners of the cards (the image with its polaroid panel), px; 0 the square cards before the option
            'imageRadius'                     => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 0,
                'group'    => 'general',
                'params'   => ['min' => 0, 'max' => 40],
            ],

            /* the slider layout (layout = slider): own ids, apart from the slider* options of the
               slider type; the card height is targetRowHeight, the gap spacing */

            'slidesPerView'                   => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 3,
                'group'    => 'general',
                'params'   => ['min' => 1, 'max' => 10],
            ],

            // the effects of Swiper; all but slide and coverflow show one card
            'slidesEffect'                    => [
                'type'    => 'select',
                'options' => ['slide', 'coverflow', 'fade', 'cube', 'flip', 'cards', 'creative'],
                'default' => 'slide',
                'group'   => 'general',
            ],

            // crop: cards of one width; auto: each card of its image's shape (slide / coverflow,
            // the others take contain); contain: the whole image, a blurred copy around it
            'slidesFit'                       => [
                'type'    => 'select',
                'options' => ['crop', 'auto', 'contain'],
                'default' => 'crop',
                'group'   => 'general',
            ],

            // the shadows of the 3D effects (coverflow, cube - also the one under it -, flip, cards, creative)
            'slidesShadows'                   => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'slidesLoop'                      => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            'slidesAutoplay'                  => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'group'    => 'general',
            ],

            'slidesDelay'                     => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 3000,
                'group'    => 'general',
                'params'   => ['min' => 500, 'max' => 60000],
            ],

            'slidesNavButtons'                => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'general',
            ],

            // a Swiper pagination type, or none
            'slidesPagination'                => [
                'type'    => 'select',
                'options' => ['bullets', 'fraction', 'progressbar', 'disable'],
                'default' => 'bullets',
                'group'   => 'general',
            ],

            // the arrows and the pagination, drawn over the cards
            'slidesNavColor'                  => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'general',
            ],

            /* thumbnails  */

            'hoverInvert'                     => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'thumbnails',
            ],

            'hoverHighlight'                  => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'legacy'   => true,
                'group'    => 'thumbnails',
            ],


            'hoverHighlightFullWidth'        => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'group'    => 'thumbnails',
            ],


             

            'hoverEffect'                     => [
                'type'    => 'select',
                'options' => ['zoe', 'lily', 'sadie', 'static', 'oscar', 'disable'],
                'default' => 'lily',
                'group'   => 'thumbnails',
            ],

            'hoverTitleColor'                 => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'thumbnails',
            ],
            'hoverTitleBackgroundColor'       => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(0, 0, 0, 0.71)',
                'group'    => 'thumbnails',
            ],

            'hoverDescriptionColor'           => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#000000',
                'group'    => 'thumbnails',
            ],

            'hoverDescriptionBackgroundColor' => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(255, 255, 255, 0.67)',
                'group'    => 'thumbnails',
            ],

            'hoverBackgroundColor'            => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(0, 0, 0, 0.71)',
                'group'    => 'thumbnails',
            ],

            'hoverColor'                      => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgba(255, 255, 255, 0.67)',
                'group'    => 'thumbnails',
            ],

            // the lines and frames of the Codrops effects (Oscar's frame)
            'hoverLineColor'                  => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'thumbnails',
            ],

            'hoverTitleFont'                  => [
                'type'    => 'select',
                'options' => self::HOVER_FONTS,
                'default' => '',
                'group'   => 'thumbnails',
            ],

            'hoverDescriptionFont'            => [
                'type'    => 'select',
                'options' => self::HOVER_FONTS,
                'default' => '',
                'group'   => 'thumbnails',
            ],


            'titleSource'                     => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'title',
                'group'   => 'thumbnails',
            ],

            'descriptionSource'               => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'description',
                'group'   => 'thumbnails',
            ],

            /* Polaroid panel */

            'polaroidMode'                    => [
                'type'    => 'select',
                'options' => ['top', 'left', 'bottom', 'right', 'disable'],
                'default' => 'disable',
                'group'   => 'polaroid',
            ],

            'polaroidTitleSource'             => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'title',
                'group'   => 'polaroid',
            ],

            'polaroidDescriptionSource'       => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'description',
                'group'   => 'polaroid',
            ],

            'polaroidTextColor'               => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#000000',
                'group'    => 'polaroid',
            ],

            'polaroidBackgroundColor'         => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'polaroid',
            ],

            'polaroidDescriptionSize'         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 50,
                'group'    => 'polaroid',
                'params'   => ['min' => 10, 'max' => 90],
            ],

            // the buttons of the panel (learnMore: also its icon of a narrow panel; link: only on a link image);
            // all by default - the panel had them all before the option
            'polaroidButtons'                 => [
                'type'     => 'multiselect',
                'sanitize' => 'string',
                // the like button is not one of them: likesIn
                'options'  => ['learnMore', 'share', 'link'],
                'default'  => ['learnMore', 'share', 'link'],
                'group'    => 'polaroid',
            ],

            /* Album */

            'albumHideCoverImage'             => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'album',
            ],

            'albumIconColor'                  => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '#ffffff',
                'group'    => 'album',
            ],

            'albumIcon'                       => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Folder',
                'group'    => 'album',
            ],


            /* the interface color: the navigation, the search and filters, the proofing bar and the dialogs (the
               grid script's MUI theme); shown on the Interface tab, the group stays as it was */
            'navigationInterfaceColor'        => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => 'rgb(25,118,210)',
                'group'    => 'navigation',
            ],

            /* the look of the same elements (the Interface tab); the defaults are the look before the options */
            'interfaceMode'                   => [
                'type'    => 'select',
                'options' => ['light', 'dark'],
                'default' => 'light',
                'group'   => 'interface',
            ],

            // the corners of the fields, lists, buttons, bars, menus and dialogs, px
            'interfaceRadius'                 => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 4,
                'params'   => ['min' => 0, 'max' => 24],
                'group'    => 'interface',
            ],

            // the background of the fields, lists, filter buttons, bars, menus and dialogs; '' by the scheme
            'interfaceBackground'             => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '',
                'group'    => 'interface',
            ],

            // the text on the filled buttons and items in the interface color; '' chosen for that color
            'interfaceContrastColor'          => [
                'type'     => 'text',
                'sanitize' => 'color',
                'default'  => '',
                'group'    => 'interface',
            ],

            'pagination'                      => [
                'type'    => 'select',
                'options' => ['loadmore', 'pagination', 'disable'],
                'default' => 'loadmore',
                'group'   => 'navigation',
            ],

            'imagesPerPage'                   => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 12,
                'group'    => 'navigation',
            ],

            // auto: the script shows them only when the gallery has a sub-gallery; a switch before (true / false)
            'breadcrumbs'                     => [
                'type'          => 'select',
                'options'       => ['on', 'off', 'auto'],
                'legacyBoolean' => ['on', 'off'],
                'default'       => 'auto',
                'legacy'        => 'on',
                'group'         => 'navigation',
            ],

            // auto: the script hides the menu when it has one item at most, else shows it like compact
            // and, when its items do not fit, the side menu instead
            'topMenuMode'                     => [
                'type'    => 'select',
                'options' => ['off', 'compact', 'wide', 'auto'],
                'default' => 'auto',
                'legacy'  => 'wide',
                'group'   => 'navigation',
            ],

            // auto: the script hides the menu when it has one item at most; a switch before (true / false)
            'sideMenu'                        => [
                'type'          => 'select',
                'options'       => ['on', 'off', 'auto'],
                'legacyBoolean' => ['on', 'off'],
                'default'       => 'auto',
                'legacy'        => 'on',
                'group'         => 'navigation',
            ],

            'rootGalleryInMenu'               => [
                'type'    => 'select',
                'options' => ['title', 'label', 'hide'],
                'default' => 'title',
                'group'   => 'navigation',
            ],

            'rootGalleryLabel'                => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Root Gallery',
                'group'    => 'navigation',
            ],

            'infiniteScroll'                  => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => true,
                'group'    => 'navigation',
            ],

            /* ======================================================= */

            'autoPlay'                        => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'group'    => 'lightbox',
            ],

            'timeout'                         => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 1500,
                'group'    => 'lightbox',
            ],

            'lightboxTitleSource'             => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'title',
                'group'   => 'lightbox',
            ],

            'lightboxDescriptionSource'       => [
                'type'    => 'select',
                'options' => ['title', 'caption', 'description', 'disable'],
                'default' => 'description',
                'group'   => 'lightbox',
            ],

            'lightboxButtons'                 => [
                'type'     => 'multiselect',
                'sanitize' => 'string',
                'options'  => ['fullscreen', 'zoom', 'slideshow', 'share', 'download'],
                'default'  => ['fullscreen', 'zoom'],
                'group'    => 'lightbox',
            ],

            /* Likes & Comments (extensions/reactions; robogrid only): who may like, where the like buttons
               are, who may comment, where the comment buttons are; 'all' may come later for comments */
            'likes'                           => [
                'type'    => 'select',
                'options' => ['off', 'users', 'all'],
                'default' => 'off',
                'group'   => 'reactions',
            ],

            'likesIn'                         => [
                'type'     => 'multiselect',
                'sanitize' => 'string',
                'options'  => ['lightbox', 'polaroid'],
                'default'  => ['lightbox', 'polaroid'],
                'group'    => 'reactions',
            ],

            'comments'                        => [
                'type'    => 'select',
                'options' => ['off', 'users'],
                'default' => 'off',
                'group'   => 'reactions',
            ],

            'commentsIn'                      => [
                'type'     => 'multiselect',
                'sanitize' => 'string',
                'options'  => ['lightbox', 'polaroid'],
                'default'  => ['lightbox', 'polaroid'],
                'group'    => 'reactions',
            ],

            /* Client proofing (Pro, robogrid only; the feature is in the key, extensions/proofing is what this plugin
               asks it): the clients of the Proofing tab open the gallery by their own links and choose images; read
               from the stored options only */
            'proofing'                        => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'group'    => 'proofing',
            ],

            /* Search & Filter (extensions/search; the grid script's bar over the gallery): a search field over the
               open gallery's items (titles, captions, descriptions, tags; a sub-gallery's card by its title, not
               the images in it), the filters by the images' tags, by sub-gallery (its images in place of the
               cards) and by kind (photos / videos / links); each filter with its look */
            'search'                          => [
                'type'     => 'checkbox',
                'sanitize' => 'boolean',
                'default'  => false,
                'group'    => 'search',
            ],

            /* the look of each filter (SearchIndex::MODES): off, buttons (chips; the kinds as a button group),
               a drop-down list, or suggested by the search field (shown then even without the search option) */
            'filterTags'                      => [
                'type'    => 'select',
                'options' => ['off', 'buttons', 'dropdown', 'search'],
                'default' => 'off',
                'group'   => 'search',
            ],

            'filterCategories'                => [
                'type'    => 'select',
                'options' => ['off', 'buttons', 'dropdown', 'search'],
                'default' => 'off',
                'group'   => 'search',
            ],

            'filterType'                      => [
                'type'    => 'select',
                'options' => ['off', 'buttons', 'dropdown', 'search'],
                'default' => 'off',
                'group'   => 'search',
            ],

            /* the side of the bar the search field keeps, and the side of the filters shown apart from it (buttons,
               drop-down lists; a filter in the field goes with the field); left is the bar before the options */
            'searchPosition'                  => [
                'type'    => 'select',
                'options' => ['left', 'right'],
                'default' => 'left',
                'group'   => 'search',
            ],

            'filterPosition'                  => [
                'type'    => 'select',
                'options' => ['left', 'right'],
                'default' => 'left',
                'group'   => 'search',
            ],



            'labelButtonLoadMore'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Load More',
                'group'    => 'labels',
            ],

            'labelButtonUp'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Up',
                'group'    => 'labels',
            ],

            'labelButtonMenu'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Menu',
                'group'    => 'labels',
            ],

            'labelSidebarMenuTitle'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Gallery Menu',
                'group'    => 'labels',
            ],

            // the button of the polaroid panel; the text the panel had before the option
            'labelButtonLearnMore'                 => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Learn More',
                'group'    => 'labels',
            ],

            // the search field's placeholder and the first item of the filters (Search & Filter)
            'labelSearch'                     => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Search',
                'group'    => 'labels',
            ],

            // the field's placeholder when it only offers the filters in it (the search option off)
            'labelFilterField'                => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Filter',
                'group'    => 'labels',
            ],

            // the field's placeholder when it searches and offers the filters in it too
            'labelSearchFilter'               => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Search and filter',
                'group'    => 'labels',
            ],

            'labelFilterAll'                  => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'All',
                'group'    => 'labels',
            ],

            // the items of the Type filter (filterType)
            'labelFilterPhotos'               => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Photos',
                'group'    => 'labels',
            ],

            'labelFilterVideos'               => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Videos',
                'group'    => 'labels',
            ],

            'labelFilterLinks'                => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Links',
                'group'    => 'labels',
            ],

            // the message when the search and the filters find nothing, and its button that clears them
            'labelFilterNothingFound'         => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Nothing found.',
                'group'    => 'labels',
            ],

            'labelFilterClear'                => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => 'Clear',
                'group'    => 'labels',
            ],


            'accessDirectLinkOnly'                   => [
                'type'    => 'checkbox',
                'sanitize' => 'boolean',
                'default' => false,
                'group'   => 'access',
            ],

            'accessRequireToken'                     => [
                'type'    => 'checkbox',
                'sanitize' => 'boolean',
                'default' => true,
                'group'   => 'access',
            ],


            // no plugin password option: password protection is WordPress's own
            // post_password (Publish box -> Visibility)

            // HTML shown before / after the gallery in every layout; the meta of the old box
            'textBefore'                      => [
                'type'     => 'textarea',
                'sanitize' => 'html',
                'default'  => '',
                'group'    => 'text',
                'meta'     => ROBO_GALLERY_PREFIX . 'pretext',
            ],

            'textAfter'                       => [
                'type'     => 'textarea',
                'sanitize' => 'html',
                'default'  => '',
                'group'    => 'text',
                'meta'     => ROBO_GALLERY_PREFIX . 'aftertext',
            ],

            // the user's styles, added to the gallery's shadow root by its script; in the text group:
            // what one gallery needs, so not in option themes
            'customCss'                       => [
                'type'     => 'textarea',
                'sanitize' => 'css',
                'default'  => '',
                'group'    => 'text',
                'meta'     => ROBO_GALLERY_PREFIX . 'customCss',
            ],

        ];

    }

    /**
     * Definitions with option_id and a default sanitize rule filled in.
     *
     * @param string $set SET_ROBOGRID / SET_SLIDER / SET_CLASSIC / SET_POSTS / SET_YOUTUBE
     * @return array option_id => definition
     */
    public static function getOptionsArray($set = self::SET_ROBOGRID)
    {
        $options = self::getOptionConfig($set);
        foreach ($options as $option_id => $option) {
            $options[$option_id]['option_id'] = $option_id;
            if (!isset($options[$option_id]['sanitize'])) {
                $options[$option_id]['sanitize'] = 'string';
            }
        }
        return $options;
    }

    /**
     * The gallery's stored settings as saved (no defaults), the access options of
     * the older versions read as the current ones (readLegacyAccess()).
     *
     * @param int $galleryId
     * @return array option_id => value
     */
    public static function getStored($galleryId)
    {
        $stored = get_post_meta((int) $galleryId, PluginConstants::OPTIONS_KEY, true);
        return is_array($stored) ? self::readLegacyAccess($stored) : array();
    }

    /**
     * A gallery not yet saved with the current access options keeps the access of its old
     * ones - until the upgrade has run, or if it could not: a private gallery must not open
     * to everyone. As the old access check read them (!empty()); the Token Mode only counted
     * with Private on. The current options, once there, win.
     *
     * @param array $stored option_id => value
     * @return array
     */
    public static function readLegacyAccess(array $stored): array
    {
        if (array_key_exists(PluginConstants::ACCESS_DIRECT_LINK_ONLY, $stored) || !array_key_exists('accessModePrivate', $stored)) {
            return $stored;
        }

        $private = !empty($stored['accessModePrivate']);

        $stored[PluginConstants::ACCESS_DIRECT_LINK_ONLY] = $private;
        // set either way: the current option's default is on, an old Private gallery without the token opened by its link
        $stored[PluginConstants::ACCESS_REQUIRE_TOKEN] = $private && !empty($stored['accessModeToken']);

        return $stored;
    }

    /**
     * The settings the gallery shows: its own, with the values of the option
     * theme it follows over the options it does not change itself
     * (optionThemes\GalleryTheme). No defaults. The access options are always
     * the gallery's own - access reads getStored().
     *
     * @param int $galleryId
     * @return array option_id => value
     */
    public static function getEffective($galleryId)
    {
        return GalleryTheme::apply((int) $galleryId, self::readLegacy(self::getStored($galleryId)));
    }

    /**
     * Values stored by an older version as their options read them now:
     * - a select that was a switch ('legacyBoolean' => [value for true, value for false]);
     * - an option whose default changed ('legacy' => the old default): a gallery saved by
     *   the robogrid settings of an older version without it keeps what that version showed.
     *   Those settings wrote every option they had, layout too (from 2024-06), and the
     *   current ones write all, so a robogrid gallery without the option is an old one.
     * A gallery keeps the old value until it is saved again.
     *
     * @param array $values option_id => value
     * @return array
     */
    public static function readLegacy(array $values): array
    {
        $savedByRobogrid = array_key_exists('layout', $values);

        foreach (self::robogridOptionConfig() as $optionId => $option) {
            if (isset($option['legacyBoolean']) && isset($values[$optionId]) && is_bool($values[$optionId])) {
                $values[$optionId] = $option['legacyBoolean'][$values[$optionId] ? 0 : 1];
            }
            if (isset($option['legacy']) && $savedByRobogrid && !array_key_exists($optionId, $values)) {
                $values[$optionId] = $option['legacy'];
            }
        }

        return $values;
    }

    /**
     * The custom CSS the Fusion Grid script gets (REST robofields), checked again: the meta can
     * be written around the settings panel. The slider reads it as its other options.
     *
     * @param int $galleryId
     * @return string
     */
    public static function customCss($galleryId): string
    {
        return self::sanitizeCss(get_post_meta((int) $galleryId, ROBO_GALLERY_PREFIX . 'customCss', true));
    }

    /**
     * Definitions with the gallery's value in 'value' (getEffective() or the
     * option's own meta, else the default). For a gallery that follows a theme,
     * 'default' is the theme's value: "reset" in the settings goes back to the
     * theme. A slider not yet saved in the settings panel has the values of its
     * old meta (SliderOptions::legacyValues()); the classic options are read from
     * their meta (ClassicOptions::storedValues()).
     *
     * @param int         $galleryId
     * @param string|null $set the option set, by the gallery's type when null
     * @return array option_id => definition + value
     */
    public static function getWithValues($galleryId, $set = null)
    {
        $set         = null === $set ? self::setOf($galleryId) : $set;
        $definitions = self::getOptionsArray($set);
        $values      = self::getEffective($galleryId);
        $themeValues = GalleryTheme::themeValues((int) $galleryId);
        $result      = array();

        foreach ($definitions as $option_id => $option) {
            if (isset($option['meta'])) {
                $meta               = get_post_meta((int) $galleryId, $option['meta'], true);
                $values[$option_id] = is_string($meta) ? $meta : $option['default'];
            }
        }

        if (self::SET_SLIDER === $set && array_diff_key($definitions, $values)) {
            $values += SliderOptions::legacyValues((int) $galleryId);
        }

        if (self::SET_CLASSIC === $set) {
            $values = ClassicOptions::storedValues((int) $galleryId) + $values;
        }

        foreach ($definitions as $option_id => $option) {
            if ($themeValues && array_key_exists($option_id, $themeValues)) {
                $option['default'] = $themeValues[$option_id];
            }
            $option['value']    = array_key_exists($option_id, $values) ? $values[$option_id] : $option['default'];
            $result[$option_id] = $option;
        }

        return $result;
    }

    /**
     * Saves values (already sanitized, see sanitizeValue()) over the gallery's
     * current settings. The whole array is written with only the known options,
     * so options removed from the config disappear on the next save. The stored
     * options of the other sets stay: they are back when the type is changed back.
     * An option with its own meta is written there, only when it is in $values,
     * and so are the classic options (ClassicOptions::save()).
     *
     * @param int         $galleryId
     * @param array       $values option_id => value
     * @param string|null $set the option set, by the gallery's type when null
     */
    public static function save($galleryId, array $values, $set = null)
    {
        $known   = self::getOptionConfig(self::SET_POSTS) + YoutubeOptions::getOptionConfig() + self::getOptionConfig(self::SET_SLIDER);
        $classic = ClassicOptions::getOptionConfig();
        $options = array_intersect_key(self::getStored($galleryId), $known);
        foreach (self::getWithValues($galleryId, $set) as $option_id => $option) {
            if (!isset($option['meta']) && !isset($option['store'])) {
                $options[$option_id] = $option['value'];
            }
        }

        $classicValues = array();
        foreach ($values as $option_id => $value) {
            if (isset($classic[$option_id])) {
                $classicValues[$option_id] = $value;
                continue;
            }
            if (isset($known[$option_id]['meta'])) {
                // update_post_meta() unslashes
                update_post_meta((int) $galleryId, $known[$option_id]['meta'], wp_slash($value));
                continue;
            }
            $options[$option_id] = $value;
        }

        if ($classicValues) {
            ClassicOptions::save((int) $galleryId, $classicValues);
        }

        // update_post_meta() also creates the meta when it's missing, and unlike
        // add_post_meta() it always runs the update_post_metadata filter - which
        // access\ExcludeListManager needs to put a direct-link-only gallery on the
        // exclude list on its very first settings save.
        update_post_meta((int) $galleryId, PluginConstants::OPTIONS_KEY, $options);
    }

    /**
     * Validates/sanitizes an incoming value by the option's definition.
     *
     * @param array $option definition (from getOptionsArray())
     * @param mixed $value
     * @return mixed|\WP_Error
     */
    public static function sanitizeValue(array $option, $value)
    {
        if (isset($option['sanitize']) && 'ids' === $option['sanitize']) {
            return self::sanitizeIds($value, $option);
        }

        if ('multiselect' === $option['type']) {
            return self::sanitizeMultiselect($value, $option);
        }

        return self::sanitizeText($value, $option);
    }

    /**
     * @param mixed $values
     * @param array $option
     * @return array|\WP_Error only the values listed in the option's 'options'
     */
    private static function sanitizeMultiselect($values, $option)
    {
        if (empty($values)) {
            return array();
        }

        if (!is_array($values)) {
            return new \WP_Error(
                'rest_options_value_invalid',
                __('An invalid setting value was passed.', 'robo-gallery'),
                array('status' => 400)
            );
        }

        $final_values = array();
        $allow_items  = $option['options'];
        foreach ($values as $value) {
            if (in_array($value, $allow_items, true)) {
                $final_values[] = $value;
            }
        }

        return $final_values;
    }

    /**
     * A list of ids ('sanitize' => 'ids', e.g. terms): positive unique integers,
     * at most params max; anything else is no ids.
     *
     * @param mixed $values
     * @param array $option
     * @return int[]
     */
    private static function sanitizeIds($values, array $option): array
    {
        $ids = array();
        foreach (is_array($values) ? $values : array() as $value) {
            $value = is_scalar($value) ? (int) $value : 0;
            if ($value > 0) {
                $ids[$value] = $value;
            }
        }

        $max = isset($option['params']['max']) ? (int) $option['params']['max'] : 0;
        return $max ? array_slice(array_values($ids), 0, $max) : array_values($ids);
    }

    /**
     * A color setting ('sanitize' => 'color'): a valid CSS color, else the
     * option's default. Used when saving and when handing stored values out
     * (restapi GalleryFields), so values saved before this check are covered too.
     *
     * @param mixed $value
     * @param array $option
     * @return string
     */
    public static function sanitizeColor($value, array $option): string
    {
        return CssColor::sanitize(is_string($value) ? $value : '', (string) $option['default']);
    }

    /**
     * CSS kept as written (selectors with ">", the backslashes of escapes), as text only: no "<",
     * which could close a <style> (includes/frontend/modules/class/customcss.php does the same).
     *
     * @param mixed $value
     * @return string
     */
    private static function sanitizeCss($value): string
    {
        return str_replace(array('<', "\0"), '', is_scalar($value) ? (string) $value : '');
    }

    /**
     * By the option's 'sanitize' rule (boolean / integer with min-max / color / html / css / string);
     * a value outside the option's 'options' list falls back to the default.
     *
     * @param mixed $value
     * @param array $option
     * @return mixed
     */
    private static function sanitizeText($value, $option)
    {
        if (!isset($option['sanitize'])) {
            $option['sanitize'] = 'string';
        }

        switch ($option['sanitize']) {
            case 'boolean':
                $value = $value ? true : false;
                break;

            case 'integer':
                $value = (int) $value;
                if (isset($option['params']['min']) && $value < $option['params']['min']) {
                    $value = $option['params']['min'];
                }
                if (isset($option['params']['max']) && $value > $option['params']['max']) {
                    $value = $option['params']['max'];
                }
                break;

            case 'color':
                $value = self::sanitizeColor($value, $option);
                break;

            // the HTML a post may have, as the old "Text Addons" box kept it
            case 'html':
                $value = wp_kses_post(is_scalar($value) ? (string) $value : '');
                break;

            case 'css':
                $value = self::sanitizeCss($value);
                break;

            case 'string':
            default:
                $value = sanitize_text_field($value);
        }

        if (isset($option['options']) && !in_array($value, $option['options'], true)) {
            $value = $option['default'];
        }

        return $value;
    }
}
