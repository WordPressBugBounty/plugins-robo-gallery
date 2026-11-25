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

namespace RoboGallery\app\extensions\classic;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\GalleryUtils;
use RoboGallery\app\extensions\cssCache\CssCache;
use RoboGallery\app\extensions\galleryType\ThemePresets;

defined('WPINC') || exit;

/**
 * Settings of the classic types (grid, masonry, mosaic, polaroid, youtube, custom
 * and their Pro types), the classic set of GalleryOptions: the fields of the old
 * CMB2 boxes (Free and the Pro files of the key plugin), edited in the settings panel.
 *
 * They stay where the old boxes saved them, in the rsg_* meta, in the same format:
 * the frontend (includes/frontend/modules/base-grid), "Clone Gallery", the new-gallery
 * presets and the key plugin read those meta. An option names its meta in 'store'
 * (the meta without the prefix, plus the key in an array meta) and how its value is
 * stored:
 * - checkbox: 'on' (default '1') when on, no meta / key when off - an unchecked box
 *   was not posted; 'inverted': '1' when off, '0' when on;
 * - select: 'stored' maps an option value to a stored one (an empty one is no meta);
 * - an empty text is no meta, and so is an array meta whose values are all empty
 *   (CMB2 deleted them).
 * 'legacy' is the value of a gallery saved by the old boxes without this meta: their
 * default was for new galleries only (their removed rbs_gallery_set_checkbox_default_for_new_post).
 */
class ClassicOptions
{
    // the meta every save of the old boxes wrote (the size fields always post a value): a
    // gallery without them has no old settings - activation\Upgrade adds a few other meta
    // (lightboxCounter, menuSelfImages...) to every gallery
    const SAVED_META = array('thumb-size-options', 'width-size');

    public function register(): void
    {
        add_action('add_meta_boxes_' . ROBO_GALLERY_TYPE_POST, array($this, 'storeDefaults'));
    }

    /**
     * Writes the defaults (with the preset of a new gallery's ?rsg_gallery_type=) of a
     * classic gallery without settings when its editor opens - a new one or a type
     * change - so the gallery renders with what the settings panel shows even if the
     * panel is never touched. The old boxes did this on the first save.
     *
     * @param \WP_Post $post
     */
    public function storeDefaults($post): void
    {
        $galleryId = isset($post->ID) ? (int) $post->ID : 0;
        if (!$galleryId || !current_user_can('edit_post', $galleryId)) {
            return;
        }

        // the edited gallery: a new one has no type meta yet, its type is ?rsg_gallery_type=
        if (GalleryOptions::SET_CLASSIC !== GalleryOptions::setForType(GalleryUtils::getTypeGallery())) {
            return;
        }

        if (self::hasStored($galleryId)) {
            return;
        }

        self::save($galleryId, self::defaults(GalleryUtils::getSourceGallery()));
    }

    /**
     * @return array option_id => definition, as in GalleryOptions::getOptionConfig()
     */
    public static function getOptionConfig(): array
    {
        return array_merge(
            self::sizeOptions(),
            self::columnsOptions(),
            self::viewOptions(),
            self::hoverOptions(),
            self::menuOptions(),
            self::loadingOptions(),
            self::polaroidOptions(),
            self::lightboxOptions(),
            self::cssOptions()
        );
    }

    /**
     * The settings of a gallery saved by the old boxes (or by the settings panel),
     * as option_id => value; empty when it has none (it gets the defaults). A
     * missing meta gets the value the old box showed for it ('legacy' or the default).
     *
     * @param int $galleryId
     * @return array
     */
    public static function storedValues(int $galleryId): array
    {
        if (!self::hasStored($galleryId)) {
            return array();
        }

        return self::decode(self::readMeta($galleryId), true);
    }

    /**
     * New-gallery values: the defaults with the preset of the source ("gridpro-3";
     * a type alone is its preset 1, galleryType\ThemePresets).
     *
     * @param string $source
     * @return array option_id => value
     */
    public static function defaults(string $source): array
    {
        $preset = '' === $source ? array() : (new ThemePresets())->forSource($source);

        return self::decode($preset, false);
    }

    /**
     * Writes values (sanitized by GalleryOptions::sanitizeValue()) to the meta, in
     * the format of the old boxes. A value equal to what the gallery has is not
     * written: a stored value the panel cannot show exactly (an old format, a value
     * set by hand) stays as it is until the option is changed. The other keys of an
     * array meta are kept.
     *
     * @param int   $galleryId
     * @param array $values option_id => value; options of other sets are skipped
     */
    public static function save(int $galleryId, array $values): void
    {
        $definitions = GalleryOptions::getOptionsArray(GalleryOptions::SET_CLASSIC);
        $current     = self::storedValues($galleryId);
        $changes     = array();

        foreach ($values as $optionId => $value) {
            if (!isset($definitions[$optionId]['store'])) {
                continue;
            }
            if (array_key_exists($optionId, $current) && $current[$optionId] === $value) {
                continue;
            }

            $option = $definitions[$optionId];
            $name   = $option['store'][0];
            $key    = isset($option['store'][1]) ? $option['store'][1] : null;
            $raw    = self::encodeValue($option, $value);

            if (null === $key) {
                $changes[$name] = $raw;
                continue;
            }

            if (!isset($changes[$name])) {
                $changes[$name] = get_post_meta($galleryId, ROBO_GALLERY_PREFIX . $name, true);
                if (!is_array($changes[$name])) {
                    $changes[$name] = array();
                }
            }
            if (null === $raw) {
                unset($changes[$name][$key]);
            } else {
                $changes[$name][$key] = $raw;
            }
        }

        foreach ($changes as $name => $raw) {
            // CMB2 deleted an empty value, and an array of empty values ("0" too)
            if (null === $raw || (is_array($raw) && !array_filter($raw))) {
                delete_post_meta($galleryId, ROBO_GALLERY_PREFIX . $name);
                continue;
            }

            // update_post_meta() unslashes
            update_post_meta($galleryId, ROBO_GALLERY_PREFIX . $name, wp_slash($raw));
        }

        // the frontend compiles SCSS from these options; the panel saves over REST, without save_post
        if ($changes) {
            CssCache::renew($galleryId);
        }
    }

    /**
     * @param int $galleryId
     * @return bool the gallery has the settings of the old boxes (or of the settings panel)
     */
    private static function hasStored(int $galleryId): bool
    {
        foreach (self::SAVED_META as $name) {
            if (metadata_exists('post', $galleryId, ROBO_GALLERY_PREFIX . $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $galleryId
     * @return array meta name without the prefix => value, the stored meta of the options
     */
    private static function readMeta(int $galleryId): array
    {
        $meta = array();
        foreach (self::getOptionConfig() as $option) {
            $name = $option['store'][0];
            if (!array_key_exists($name, $meta) && metadata_exists('post', $galleryId, ROBO_GALLERY_PREFIX . $name)) {
                $meta[$name] = get_post_meta($galleryId, ROBO_GALLERY_PREFIX . $name, true);
            }
        }

        return $meta;
    }

    /**
     * The values of the options from their meta, each checked by its definition (a
     * value outside an option's list becomes its default, as on a save).
     *
     * @param array $meta   meta name without the prefix => value (stored meta or a preset)
     * @param bool  $legacy a stored gallery: a missing meta has the 'legacy' value, not the default
     * @return array option_id => value
     */
    private static function decode(array $meta, bool $legacy): array
    {
        $values = array();
        foreach (GalleryOptions::getOptionsArray(GalleryOptions::SET_CLASSIC) as $optionId => $option) {
            if (!isset($option['store'])) {
                continue;
            }

            $value              = GalleryOptions::sanitizeValue($option, self::decodeValue($option, $meta, $legacy));
            $values[$optionId] = is_wp_error($value) ? $option['default'] : $value;
        }

        return $values;
    }

    /**
     * @param array $option
     * @param array $meta
     * @param bool  $legacy
     * @return mixed the value of the option, not sanitized yet
     */
    private static function decodeValue(array $option, array $meta, bool $legacy)
    {
        $name = $option['store'][0];
        $key  = isset($option['store'][1]) ? $option['store'][1] : null;

        if (!array_key_exists($name, $meta)) {
            return $legacy && array_key_exists('legacy', $option) ? $option['legacy'] : $option['default'];
        }

        $raw = $meta[$name];
        if (null !== $key) {
            // a key the array does not have: an unchecked box was not posted, the other fields
            // showed their default (and so does an array meta saved as something else)
            if (!is_array($raw) || !array_key_exists($key, $raw)) {
                return 'checkbox' === $option['type'] ? false : $option['default'];
            }
            $raw = $raw[$key];
        }

        if (is_array($raw)) {
            return $option['default'];
        }

        if ('checkbox' === $option['type']) {
            // as the frontend reads it: any non-empty value is on
            return empty($option['inverted']) ? !empty($raw) : empty($raw);
        }

        if (isset($option['stored'])) {
            $value = array_search((string) $raw, $option['stored'], true);
            return false === $value ? $raw : $value;
        }

        return $raw;
    }

    /**
     * @param array $option
     * @param mixed $value sanitized
     * @return string|null the stored value, null for no meta / key
     */
    private static function encodeValue(array $option, $value)
    {
        if ('checkbox' === $option['type']) {
            if (!empty($option['inverted'])) {
                return $value ? '0' : '1';
            }
            return $value ? (isset($option['on']) ? $option['on'] : '1') : null;
        }

        if (isset($option['stored'][$value])) {
            $value = $option['stored'][$value];
        }

        $value = (string) $value;

        return '' === $value ? null : $value;
    }

    /* the old boxes */

    /**
     * "Gallery Size Options": the gallery and its thumbnails.
     */
    private static function sizeOptions(): array
    {
        return [
            // 0: the whole width (100%)
            'classicWidth'       => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 100,
                'group'    => 'size',
                'params'   => ['min' => 0],
                'store'    => ['width-size', 'width'],
            ],

            'classicWidthType'   => [
                'type'    => 'select',
                'options' => ['%', 'px'],
                'default' => '%',
                'group'   => 'size',
                'store'   => ['width-size', 'widthType'],
                'stored'  => ['%' => '', 'px' => '1'],
            ],

            'classicAlign'       => [
                'type'    => 'select',
                'options' => ['disable', 'left', 'center', 'right'],
                'default' => 'disable',
                'group'   => 'size',
                'store'   => ['align'],
                'stored'  => ['disable' => ''],
            ],

            'classicPaddingTop'    => self::padding('top'),
            'classicPaddingRight'  => self::padding('right'),
            'classicPaddingBottom' => self::padding('bottom'),
            'classicPaddingLeft'   => self::padding('left'),

            // "category": the images as added, the gallery's own first, then those of its child galleries
            'classicOrderby'     => [
                'type'    => 'select',
                'options' => ['categoryD', 'categoryU', 'titleD', 'titleU', 'dateD', 'dateU', 'random'],
                'default' => 'categoryD',
                'group'   => 'size',
                'store'   => ['thumb-size-options', 'orderby'],
            ],

            'classicImageSize'   => [
                'type'    => 'select',
                'options' => ['thumbnail', 'medium', 'large', 'full'],
                'default' => 'medium',
                'group'   => 'size',
                'store'   => ['thumb-size-options', 'source'],
            ],

            'classicThumbWidth'  => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 240,
                'group'    => 'size',
                'params'   => ['min' => 20, 'max' => 500],
                'store'    => ['thumb-size-options', 'width'],
            ],

            'classicThumbHeight' => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 140,
                'group'    => 'size',
                'params'   => ['min' => 20, 'max' => 500],
                'store'    => ['thumb-size-options', 'height'],
            ],

            // the thumbnails get the ratio of the width and height above
            'classicCustomRatio' => self::checkbox('size', 'sizeType', false),
        ];
    }

    /**
     * The "Size Options" tab of the size box (Pro): one row per screen width.
     */
    private static function columnsOptions(): array
    {
        $options = array();
        // the row of the meta keys ('' the default one, 1-3 the narrow screens) => the option suffix
        $rows = array('' => '', '1' => '960', '2' => '650', '3' => '450');
        $columns = array('' => 3, '1' => 3, '2' => 2, '3' => 1);

        foreach ($rows as $row => $suffix) {
            // auto: as many columns as set, else columns of the width
            $options['classicColumnsAuto' . $suffix] = self::checkbox('columns', 'colums', true, 'autowidth' . $row, 'auto');

            $options['classicColumns' . $suffix] = [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => $columns[$row],
                'group'    => 'columns',
                'params'   => ['min' => 0, 'max' => 100],
                'store'    => ['colums', 'colums' . $row],
            ];

            $options['classicColumnWidth' . $suffix] = [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 300,
                'group'    => 'columns',
                'params'   => ['min' => 0, 'max' => 2000],
                'store'    => ['colums', 'width' . $row],
            ];
        }

        return $options;
    }

    /**
     * "Thumbs View Options".
     */
    private static function viewOptions(): array
    {
        return [
            'classicRadius'           => self::number('view', 'radius', 5, 0, 50),
            'classicSpaceHorizontal'  => self::number('view', 'horizontalSpaceBetweenBoxes', 15, 0, 100),
            'classicSpaceVertical'    => self::number('view', 'verticalSpaceBetweenBoxes', 15, 0, 100),

            'classicShadow'           => self::checkbox('view', 'shadow', true),
            'classicShadowX'          => self::shadow('shadow-options', 'hshadow', 0),
            'classicShadowY'          => self::shadow('shadow-options', 'vshadow', 5),
            'classicShadowBlur'       => self::shadow('shadow-options', 'bshadow', 7),
            'classicShadowColor'      => self::color('view', 'rgba(34, 25, 25, 0.4)', 'shadow-options', 'color'),

            'classicHoverShadow'      => self::checkbox('view', 'hover-shadow', false),
            'classicHoverShadowX'     => self::shadow('hover-shadow-options', 'hshadow', 1),
            'classicHoverShadowY'     => self::shadow('hover-shadow-options', 'vshadow', 3),
            'classicHoverShadowBlur'  => self::shadow('hover-shadow-options', 'bshadow', 3),
            'classicHoverShadowColor' => self::color('view', 'rgba(34, 25, 25, 0.4)', 'hover-shadow-options', 'color'),

            'classicBorder'           => self::checkbox('view', 'border', false),
            'classicBorderWidth'      => self::borderWidth('border-options'),
            'classicBorderStyle'      => self::borderStyle('border-options'),
            'classicBorderColor'      => self::color('view', 'rgb(229, 64, 40)', 'border-options', 'color'),

            'classicHoverBorder'      => self::checkbox('view', 'hover-border', false),
            'classicHoverBorderWidth' => self::borderWidth('hover-border-options'),
            'classicHoverBorderStyle' => self::borderStyle('hover-border-options'),
            'classicHoverBorderColor' => self::color('view', 'rgb(229, 64, 40)', 'hover-border-options', 'color'),
        ];
    }

    /**
     * "Hover Options".
     */
    private static function hoverOptions(): array
    {
        return array_merge(
            [
                // the whole thumbnail opens the lightbox, not only the zoom button
                'classicThumbClick'      => [
                    'type'     => 'checkbox',
                    'sanitize' => 'boolean',
                    'default'  => true,
                    'group'    => 'hover',
                    'store'    => ['thumbClick'],
                    'inverted' => true,
                ],

                // icons: the title, the buttons and the description below; template: classicHoverTemplate
                'classicHoverMode'       => [
                    'type'    => 'select',
                    'options' => ['off', 'icons', 'template'],
                    'default' => 'icons',
                    'legacy'  => 'off',
                    'group'   => 'hover',
                    'store'   => ['hover'],
                    'stored'  => ['off' => '0', 'icons' => '1', 'template' => '2'],
                ],

                'classicNoHoverOnMobile' => self::checkbox('hover', 'noHoverOnMobile', false),
                'classicHoverBackground' => self::color('hover', 'rgba(7, 7, 7, 0.5)', 'background'),

                'classicOverlayEffect'   => [
                    'type'    => 'select',
                    'options' => [
                        'push-up', 'push-down', 'push-up-100%', 'push-down-100%',
                        'reveal-top', 'reveal-bottom', 'reveal-top-100%', 'reveal-bottom-100%',
                        'direction-aware', 'direction-aware-fade', 'direction-right', 'direction-left',
                        'direction-top', 'direction-bottom', 'fade',
                    ],
                    'default' => 'direction-aware-fade',
                    'group'   => 'hover',
                    'store'   => ['overlayEffect'],
                ],
            ],
            self::font('classicTitle', 'showTitle', true, 12, 'rgb(255, 255, 255)', 'rgb(255, 255, 255)', true),
            self::icon('classicLinkIcon', 'linkIcon', false, 'fa-link', 20, array('#ffffff', '#ffffff', '#e54028', '#b73725')),
            self::icon(
                'classicZoomIcon',
                'zoomIcon',
                true,
                'fa-search-plus',
                30,
                array('#ffffff', 'rgb(174, 174, 174)', 'rgba(13, 130, 241, 0)', 'rgba(6, 70, 130, 0)')
            ),
            self::font('classicDesc', 'showDesc', false, 24, '#000000', '#000000', false),
            [
                // @TITLE@ @CAPTION@ @DESC@ @LINK@ @VIDEOLINK@ are replaced with the image's data
                'classicHoverTemplate' => [
                    'type'     => 'textarea',
                    'sanitize' => 'html',
                    'default'  => '<div class="rbs-hover-title">@TITLE@</div>' . "\n"
                        . '<div class="rbs-hover-caption">@CAPTION@</div>' . "\n"
                        . '<div class="rbs-hover-text">@DESC@</div>' . "\n"
                        . '<div class="rbs-hover-more"><a href="@LINK@">Read more</a></div>' . "\n",
                    'group'    => 'hover',
                    'store'    => ['desc_template'],
                ],
            ]
        );
    }

    /**
     * "Menu Options": the filter buttons above the gallery and the search.
     */
    private static function menuOptions(): array
    {
        return [
            // off: only the images of the child galleries
            'classicMenuSelfImages'      => self::checkbox('menu', 'menuSelfImages', true),

            'classicMenu'                => self::checkbox('menu', 'menu', true),

            // on: a button per image tag, off: per child gallery
            'classicMenuTags'            => self::checkbox('menu', 'menuTag', false),

            'classicMenuTagsOrder'       => [
                'type'    => 'select',
                'options' => ['none', 'asc', 'desc'],
                'default' => 'none',
                'group'   => 'menu',
                'store'   => ['menuTagSort'],
                'stored'  => ['none' => ''],
            ],

            'classicMenuRoot'            => self::checkbox('menu', 'menuRoot', true),
            'classicMenuRootLabel'       => self::text('menu', 'menuRootLabel', __('All', 'robo-gallery')),
            'classicMenuSelf'            => self::checkbox('menu', 'menuSelf', true),

            'classicButtonFill'          => self::select('menu', 'buttonFill', ['normal', 'flat', '3d', 'border'], 'flat'),
            'classicButtonColor'         => self::select('menu', 'buttonColor', ['gray', 'blue', 'green', 'orange', 'red', 'purple'], 'blue'),
            'classicButtonType'          => self::select('menu', 'buttonType', ['normal', 'rounded', 'pill', 'circle'], 'normal'),
            'classicButtonSize'          => self::select('menu', 'buttonSize', ['jumbo', 'large', 'normal', 'small', 'tiny'], 'large'),
            'classicButtonAlign'         => self::select('menu', 'buttonAlign', ['left', 'center', 'right'], 'left'),
            'classicButtonPaddingLeft'   => self::number('menu', 'paddingLeft', 5, 0, 100),
            'classicButtonPaddingBottom' => self::number('menu', 'paddingBottom', 10, 0, 100),

            'classicSearch'              => self::checkbox('menu', 'searchEnable', false),
            'classicSearchColor'         => self::color('menu', 'rgba(0, 0, 0)', 'searchColor'),
            'classicSearchLabel'         => self::text('menu', 'searchLabel', __('search', 'robo-gallery')),
        ];
    }

    /**
     * "Loading Options".
     */
    private static function loadingOptions(): array
    {
        return [
            'classicLazyLoad'          => self::checkbox('loading', 'lazyLoad', true),

            'classicImagesOnStart'     => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 12,
                'group'    => 'loading',
                'params'   => ['min' => 1, 'max' => 50],
                'store'    => ['boxesToLoadStart'],
            ],

            'classicImagesOnLoadMore'  => [
                'type'     => 'text',
                'sanitize' => 'integer',
                'default'  => 8,
                'group'    => 'loading',
                'params'   => ['min' => 1, 'max' => 50],
                'store'    => ['boxesToLoad'],
            ],

            'classicWaitThumbs'        => self::checkbox('loading', 'waitUntilThumbLoads', true),
            'classicWaitAllThumbs'     => self::checkbox('loading', 'waitForAllThumbsNoMatterWhat', false),
            'classicLoadingBackground' => self::color('loading', '#ffffff', 'loadingBgColor'),
            'classicLoadingLabel'      => self::text('loading', 'LoadingWord', __('Loading...', 'robo-gallery')),
            'classicLoadMoreLabel'     => self::text('loading', 'loadMoreWord', __('Load More', 'robo-gallery')),
            'classicNoMoreLabel'       => self::text('loading', 'noMoreEntriesWord', __('No More Entries', 'robo-gallery')),
        ];
    }

    /**
     * "Polaroid Style Options": a text under every thumbnail.
     */
    private static function polaroidOptions(): array
    {
        return [
            'classicPolaroid'           => self::checkbox('polaroid', 'polaroidOn', false),
            'classicPolaroidSource'     => self::select('polaroid', 'polaroidSource', ['title', 'desc', 'caption'], 'desc'),
            'classicPolaroidBackground' => self::color('polaroid', '#ffffff', 'polaroidBackground'),
            'classicPolaroidAlign'      => self::select('polaroid', 'polaroidAlign', ['left', 'right', 'center'], 'center'),
        ];
    }

    /**
     * "Lightbox Options".
     */
    private static function lightboxOptions(): array
    {
        return [
            'classicLightboxText'         => self::checkbox('lightbox', 'lightboxTitle', true),
            'classicLightboxTextSource'   => self::select('lightbox', 'lightboxSource', ['title', 'desc', 'caption'], 'title'),
            'classicLightboxTextColor'    => self::color('lightbox', '#F3F3F3', 'lightboxColor'),
            'classicLightboxBackground'   => self::color('lightbox', 'rgba(11, 11, 11, 0.8)', 'lightboxBackground'),
            // a link of its own (#...) for every image
            'classicDeepLinking'          => self::checkbox('lightbox', 'deepLinking', false),
            'classicLightboxCounter'      => self::checkbox('lightbox', 'lightboxCounter', true),
            // between the number of the image and the count: "3 of 10"
            'classicLightboxCounterText'  => self::text('lightbox', 'lightboxCounterText', __('of', 'robo-gallery')),
            'classicLightboxMobile'       => self::checkbox('lightbox', 'lightboxMobile', false),
            'classicLightboxClose'        => self::checkbox('lightbox', 'lightboxClose', true),
            'classicLightboxArrows'       => self::checkbox('lightbox', 'lightboxArrow', true),
            'classicLightboxSourceButton' => self::checkbox('lightbox', 'lightboxSourceButton', false),
            'classicLightboxPanel'        => self::checkbox('lightbox', 'lightboxDescPanel', false),
            'classicLightboxPanelSource'  => self::select('lightbox', 'lightboxDescSource', ['title', 'desc', 'caption'], 'title'),
            'classicLightboxPanelStyle'   => self::select('lightbox', 'lightboxDescClass', ['light', 'dark', 'red', 'blue', 'green', 'pink'], 'light'),
            // the Google+ switch of the old box is left out: the frontend never read it; the VK one
            // was removed (its meta lightboxSocialVK stays in old galleries, the frontend no longer reads it)
            'classicLightboxSocial'       => self::checkbox('lightbox', 'lightboxSocial', false),
            'classicLightboxFacebook'     => self::checkbox('lightbox', 'lightboxSocialFacebook', true),
            'classicLightboxTwitter'      => self::checkbox('lightbox', 'lightboxSocialTwitter', true),
            'classicLightboxPinterest'    => self::checkbox('lightbox', 'lightboxSocialPinterest', true),
        ];
    }

    /**
     * "Custom CSS": kept as that box saved it (modules/class/customcss.php reads it).
     */
    private static function cssOptions(): array
    {
        return [
            'classicCustomCss' => [
                'type'     => 'textarea',
                'sanitize' => 'html',
                'default'  => '',
                'group'    => 'css',
                'store'    => ['cssStyle'],
            ],
        ];
    }

    /* definitions repeated by many fields */

    /**
     * A switch on by default was on for new galleries only: off on a gallery saved without it.
     */
    private static function checkbox(string $group, string $meta, bool $default, ?string $key = null, string $on = '1'): array
    {
        $option = [
            'type'     => 'checkbox',
            'sanitize' => 'boolean',
            'default'  => $default,
            'group'    => $group,
            'store'    => null === $key ? [$meta] : [$meta, $key],
        ];

        if ($default) {
            $option['legacy'] = false;
        }
        if ('1' !== $on) {
            $option['on'] = $on;
        }

        return $option;
    }

    private static function color(string $group, string $default, string $meta, ?string $key = null): array
    {
        return [
            'type'     => 'text',
            'sanitize' => 'color',
            'default'  => $default,
            'group'    => $group,
            'store'    => null === $key ? [$meta] : [$meta, $key],
        ];
    }

    private static function text(string $group, string $meta, string $default): array
    {
        return [
            'type'     => 'text',
            'sanitize' => 'string',
            'default'  => $default,
            'group'    => $group,
            'store'    => [$meta],
        ];
    }

    private static function select(string $group, string $meta, array $options, string $default): array
    {
        return [
            'type'    => 'select',
            'options' => $options,
            'default' => $default,
            'group'   => $group,
            'store'   => [$meta],
        ];
    }

    /**
     * A number whose old default was for new galleries only: 0 on a gallery saved without it.
     */
    private static function number(string $group, string $meta, int $default, int $min, int $max): array
    {
        return [
            'type'     => 'text',
            'sanitize' => 'integer',
            'default'  => $default,
            'legacy'   => 0,
            'group'    => $group,
            'params'   => ['min' => $min, 'max' => $max],
            'store'    => [$meta],
        ];
    }

    private static function padding(string $side): array
    {
        return [
            'type'     => 'text',
            'sanitize' => 'integer',
            'default'  => 0,
            'group'    => 'size',
            'params'   => ['min' => 0],
            'store'    => ['paddingCustom', $side],
        ];
    }

    private static function shadow(string $meta, string $key, int $default): array
    {
        return [
            'type'     => 'text',
            'sanitize' => 'integer',
            'default'  => $default,
            'group'    => 'view',
            'params'   => 'bshadow' === $key ? ['min' => 0, 'max' => 50] : ['min' => -50, 'max' => 50],
            'store'    => [$meta, $key],
        ];
    }

    private static function borderWidth(string $meta): array
    {
        return [
            'type'     => 'text',
            'sanitize' => 'integer',
            'default'  => 5,
            'group'    => 'view',
            'params'   => ['min' => 0, 'max' => 50],
            'store'    => [$meta, 'width'],
        ];
    }

    private static function borderStyle(string $meta): array
    {
        return [
            'type'    => 'select',
            'options' => ['none', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset', 'hidden'],
            'default' => 'solid',
            'group'   => 'view',
            'store'   => [$meta, 'style'],
        ];
    }

    /**
     * A text of the hover (the old "font" field without an icon): <prefix> shows it,
     * <prefix>Size, LineHeight, Bold, Italic, Underline, Color, ColorHover.
     */
    private static function font(string $prefix, string $meta, bool $enabled, int $size, string $color, string $colorHover, bool $bold): array
    {
        $boldOption = self::checkbox('hover', $meta, $bold, 'fontBold', 'bold');
        // the style buttons' default was not for new galleries only
        unset($boldOption['legacy']);

        return [
            $prefix                => self::checkbox('hover', $meta, $enabled, 'enabled'),
            $prefix . 'Size'       => self::fontNumber($meta, 'fontSize', $size, 5, 50),
            $prefix . 'LineHeight' => self::fontNumber($meta, 'fontLineHeight', 100, 50, 300),
            $prefix . 'Bold'       => $boldOption,
            $prefix . 'Italic'     => self::checkbox('hover', $meta, false, 'fontItalic', 'italic'),
            $prefix . 'Underline'  => self::checkbox('hover', $meta, false, 'fontUnderline', 'underline'),
            $prefix . 'Color'      => self::color('hover', $color, $meta, 'color'),
            $prefix . 'ColorHover' => self::color('hover', $colorHover, $meta, 'colorHover'),
        ];
    }

    /**
     * A button of the hover (the old "font" field with an icon): <prefix> shows it,
     * <prefix>Name (a Font Awesome 4.7 class), Size, LineHeight, Border, Color,
     * ColorHover, Background, BackgroundHover.
     *
     * @param array $colors color, hover color, background, hover background
     */
    private static function icon(string $prefix, string $meta, bool $enabled, string $icon, int $size, array $colors): array
    {
        return [
            $prefix                     => self::checkbox('hover', $meta, $enabled, 'enabled'),
            // the frontend escapes it into the class attribute of the icon
            $prefix . 'Name'            => [
                'type'     => 'text',
                'sanitize' => 'string',
                'default'  => $icon,
                'group'    => 'hover',
                'store'    => [$meta, 'iconSelect'],
            ],
            $prefix . 'Size'            => self::fontNumber($meta, 'fontSize', $size, 5, 50),
            $prefix . 'LineHeight'      => self::fontNumber($meta, 'fontLineHeight', 100, 50, 300),
            $prefix . 'Border'          => self::fontNumber($meta, 'borderSize', 0, 0, 30),
            $prefix . 'Color'           => self::color('hover', $colors[0], $meta, 'color'),
            $prefix . 'ColorHover'      => self::color('hover', $colors[1], $meta, 'colorHover'),
            $prefix . 'Background'      => self::color('hover', $colors[2], $meta, 'colorBg'),
            $prefix . 'BackgroundHover' => self::color('hover', $colors[3], $meta, 'colorBgHover'),
        ];
    }

    private static function fontNumber(string $meta, string $key, int $default, int $min, int $max): array
    {
        return [
            'type'     => 'text',
            'sanitize' => 'integer',
            'default'  => $default,
            'group'    => 'hover',
            'params'   => ['min' => $min, 'max' => $max],
            'store'    => [$meta, $key],
        ];
    }
}
