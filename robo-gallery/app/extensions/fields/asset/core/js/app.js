/* 
*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

/*
 * Fields framework (gallery edit screen). jQuery on purpose: other scripts may
 * change a field and call jQuery(...).trigger('change') - native listeners would
 * not see that, the delegated jQuery handler below does.
 */
(function ($) {
    'use strict';

    $(function () {

        if ('function' === typeof $.fn.foundation) {
            $(document).foundation();
        }

        // data-dependents='{"<value>": {"show": ["#id"], "hide": [...]}}' - the keys are jQuery methods
        $(document).on('change', '[data-dependents]', function () {
            var $this = $(this),
                tag = this.nodeName.toLowerCase(),
                type = 'input' === tag ? $this.attr('type') : tag,
                dependents = {},
                value;

            try {
                dependents = JSON.parse($this.attr('data-dependents') || '{}') || {};
            } catch (e) {
                return;
            }

            switch (type) {
                case 'checkbox':
                    value = $this.prop('checked') ? 1 : 0;
                    break;
                case 'radio':
                case 'select':
                    value = $this.val();
                    break;
            }

            if (!dependents[value]) {
                return;
            }

            $.each(dependents[value], function (action, selectors) {
                $.each(selectors, function (i, selector) {
                    var $target = $(selector);
                    if ('function' === typeof $target[action]) {
                        $target[action]();
                    }
                });
            });
        });

        // the "New Feature" label is a <button> inside the post form: don't submit it
        $(document).on('click', '.twoj-gallery-option-new', function (event) {
            event.preventDefault();
        });

        // initial state: every checkbox, the checked radio of each group, every select
        $('input[type="checkbox"][data-dependents]').trigger('change');
        $('input[type="radio"][data-dependents]:checked').trigger('change');
        $('select[data-dependents]').trigger('change');
    });
})(jQuery);
