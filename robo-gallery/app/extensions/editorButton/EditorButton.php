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

namespace RoboGallery\app\extensions\editorButton;

defined('WPINC') || exit;

/**
 * "Add Robo Gallery" button next to "Add Media" above every classic editor
 * (wp_editor): a dialog to pick a gallery, inserts [robo-gallery id="..."]
 * into that editor. The block editor has the Robo Gallery block instead.
 * JS: js/admin/editor-button.js.
 */
class EditorButton
{
    const DIALOG_ID = 'robo-gallery-editor-dialog';

    // media_buttons runs once per editor on the page; the dialog is printed once
    private bool $dialogNeeded = false;

    public function register(): void
    {
        add_action('media_buttons', array($this, 'renderButton'), 15);
        // wp_editor() with media buttons can also be on the frontend
        add_action('admin_footer', array($this, 'renderDialog'));
        add_action('wp_footer', array($this, 'renderDialog'));
    }

    /**
     * @param string $editor_id
     */
    public function renderButton($editor_id = ''): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        if (!$this->dialogNeeded) {
            $this->dialogNeeded = true;
            $this->enqueueAssets();
        }

        echo '<button type="button" class="button robo-gallery-insert" data-editor="' . esc_attr($editor_id) . '">'
            . '<span class="dashicons dashicons-format-gallery" style="margin: 4px 5px 0 0;"></span>'
            . esc_html__('Add Robo Gallery', 'robo-gallery')
            . '</button>';
    }

    public function renderDialog(): void
    {
        if (!$this->dialogNeeded) {
            return;
        }

        $select = wp_dropdown_pages(array(
            'post_type'    => ROBO_GALLERY_TYPE_POST, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 'echo' => 0: the HTML is returned, printed below
            'child_of'     => 0,
            'sort_order'   => 'ASC',
            'sort_column'  => 'post_title',
            'hierarchical' => 1,
            'echo'         => 0,
            'name'         => 'robo-gallery-editor-id',
            'id'           => 'robo-gallery-editor-id',
        ));

        echo '<div id="' . esc_attr(self::DIALOG_ID) . '" style="display: none;">';
        if ($select) {
            echo '<label for="robo-gallery-editor-id">' . esc_html__('Select gallery', 'robo-gallery') . '</label> ';
            echo $select; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes the names and values
        } else {
            echo '<p>' . esc_html__('No galleries found.', 'robo-gallery') . '</p>';
        }
        echo '<p style="margin-bottom:0;">' . esc_html__('Configure it in', 'robo-gallery') . ' '
            . '<a href="' . esc_url(admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST)) . '" target="_blank" rel="noopener">'
            . esc_html__('Robo Gallery plugin', 'robo-gallery') . '</a></p>';
        echo '</div>';
    }

    private function enqueueAssets(): void
    {
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script(
            'rbs-robo-gallery-button',
            ROBO_GALLERY_URL . 'js/admin/editor-button.js',
            array('jquery', 'jquery-ui-dialog', 'media-upload'),
            ROBO_GALLERY_VERSION,
            true
        );
        wp_localize_script('rbs-robo-gallery-button', 'robo_gallery_trans', array(
            'dialogId'         => self::DIALOG_ID,
            'roboGalleryTitle' => __('Robo Gallery', 'robo-gallery'),
            'closeButton'      => __('Close', 'robo-gallery'),
            'insertButton'     => __('Insert', 'robo-gallery'),
        ));
    }
}
