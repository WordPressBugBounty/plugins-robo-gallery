<?php
/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

use RoboGallery\app\extensions\validation\CssUnits;

if (! defined('WPINC')) {
    exit;
}

class roboGalleryModuleLayoutRoboGrid extends roboGalleryModuleAbstraction
{

    private $options = [  ];

    public function init()
    {
        $this->initScss();
        $this->core->addEvent('gallery.init', [ $this, 'initGrid' ]);
    }

    public function initGrid()
    {
        $this->initOptions();
        $this->initBlockSize();
        $this->core->addEvent('gallery.block.main', [ $this, 'renderMainBlock' ]);
        //  $this->core->addEvent('gallery.image.init.before', array($this, 'prepareImageData'));
    }

    /**
     * The gallery's settings through GalleryOptions (the only reader of the
     * robo-gallery-options meta): a setting that was never saved gets its default.
     */
    public function initOptions()
    {
        $this->options = [];
        foreach (\RoboGallery\app\GalleryOptions::getWithValues($this->id) as $optionId => $option) {
            $this->options[$optionId] = $option['value'];
        }
    }

    function getWidthStyleFromOptions()
    {
        if (isset($this->options[ 'widthAuto' ]) && $this->options[ 'widthAuto' ]) {
            return '100%';
        }

        if (isset($this->options[ 'widthValue' ]) && (int) $this->options[ 'widthValue' ]) {

            $widthType = "%";
            if (isset($this->options[ 'widthType' ]) && $this->options[ 'widthType' ]) {
                $widthType = CssUnits::getCorrectSizeUnits($this->options[ 'widthType' ]);
            }

            return (int) $this->options[ 'widthValue' ] . $widthType;
        }

        return '100%';
    }

    function getMaxWidthStyleFromOptions()
    {
        if (isset($this->options[ 'maxWidthValue' ]) && (int) $this->options[ 'maxWidthValue' ]) {

            $widthType = "%";
            if (isset($this->options[ 'maxWidthType' ]) && $this->options[ 'maxWidthType' ]) {
                $widthType = CssUnits::getCorrectSizeUnits($this->options[ 'maxWidthType' ]);
            }

            return (int) $this->options[ 'maxWidthValue' ] . $widthType;
        }

        return '';
    }

    function getAlignStyleFromOptions()
    {
        if ( isset($this->options[ 'align' ]) &&  $this->options[ 'align' ]) {
            switch ($this->options[ 'align' ]) {
                case 'right':
                    return '0 0 0 auto';
                    break;
                case 'left':
                    return '0 auto 0 0';
                    break;
                case 'center':
                    return '0 auto';
                    break;
            }
        }
        return '';
    }

    /**
     * Initializes the block size for the gallery layout.
     *
     * This method is responsible for setting up the dimensions
     * and related properties of the blocks used in the gallery grid.
     *
     * @return void
     */
    private function initBlockSize()
    {
        //
        $width = '100%';

        $widthAuto = isset($this->options[ 'widthAuto' ]) && $this->options[ 'widthAuto' ] ? true : false;
        if (! $widthAuto) {
            $width = $this->getWidthStyleFromOptions();

            $align = $this->getAlignStyleFromOptions();
            if ($align) {
                $this->element->addElementStyle('robogrid', 'margin', $align);
            }

            
        }

        $this->element->addElementStyle('robogrid', 'width', $width);
        $maxWidth = $this->getMaxWidthStyleFromOptions();
            if ($maxWidth) {
                $this->element->addElementStyle('robogrid', 'max-width', $maxWidth);
            }  
                
        

    }

    public function renderMainBlock()
    {
        return
        $this->core->getContent('Begin')

        . '<div '
        . ' robogallery_id="' . $this->id . '" '
        . ' class="RoboGalleryV5 RoboGallery_ID' . $this->id . '"  style="' . $this->core->element->getElementStyles('robogrid') . '"'
        . '>'
        . '</div>'

        . '<script>' . $this->getJS() . '</script>'

        . $this->core->getContent('End');
    }

    public function getJS()
    {
        // token-mode galleries: the script sends it back as rg_token in its REST
        // request; only a token the visitor already brought in the URL is echoed
        $accessManager = new \RoboGallery\app\extensions\access\GalleryAccessManager();
        $accessToken   = $accessManager->getValidUrlToken($this->id);

        return ' var robogallery_config_id_' . $this->id . ' = {
            "restUrl": "' . esc_attr(get_rest_url()) . '",
            "wp_rest": "' . esc_attr(wp_create_nonce('wp_rest')) . '",
            "errorImageUrl": "' . esc_url(ROBO_GALLERY_URL . 'images/') . '",
            "accessToken": ' . wp_json_encode($accessToken) . ',
            "debug": false
        };';
    }

}
