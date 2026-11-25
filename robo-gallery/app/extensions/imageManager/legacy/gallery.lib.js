/*

*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html

*/
(function($, _) {

    if (typeof wp.media.view.emWpGallery != "undefined") {
        return false;
    }

    var Select = wp.media.view.MediaFrame.Select,
        Library = wp.media.controller.Library,
        l10n = wp.media.view.l10n,
        Post;

    wp.media.view.emWpGallery = Select.extend( /** @lends wp.media.view.MediaFrame.Post.prototype */ {
        initialize: function() {
            this.counts = {
                audio: {
                    count: wp.media.view.settings.attachmentCounts.audio,
                    state: 'playlist'
                },
                video: {
                    count: wp.media.view.settings.attachmentCounts.video,
                    state: 'video-playlist'
                }
            };

            _.defaults(this.options, {
                multiple: true,
                editing: true,
                state: 'insert',
                metadata: {}
            });

            // Call 'initialize' directly on the parent class.
            Select.prototype.initialize.apply(this, arguments);
            this.createIframeStates();

        },

        /**
         * Create the default states.
         */
        createStates: function() {
            var options = this.options;

            this.states.add([
                // Main states.
                new Library({
                    id: 'insert',
                    title: l10n.insertMediaTitle,
                    priority: 20,
                    toolbar: 'main-insert',
                    filterable: 'all',
                    library: wp.media.query(options.library),
                    multiple: options.multiple ? 'reset' : false,
                    sortable: true,
                    editable: false,
                    /*
                                    // If the user isn't allowed to edit fields,
                                    // can they still edit it locally?
                                    allowLocalEdits: true,
                    				displaySettings: true,
                    				displayUserSettings: true,

                                    // Show the attachment display settings.
                                    displaySettings: true,
                                    // Update user settings when users adjust the
                                    // attachment display settings.
                                    displayUserSettings: true,
                                    */
                }),

                new Library({
                    id: 'gallery',
                    title: l10n.createGalleryTitle,
                    priority: 40,
                    toolbar: 'main-gallery',
                    filterable: 'uploaded',
                    multiple: 'add',
                    editable: true,
                    sortable: true,
                    library: wp.media.query(_.defaults({
                        type: 'image',
                    }, options.library))
                }),

                // Embed states.
                //			new wp.media.controller.Embed( { metadata: options.metadata } ),

                new wp.media.controller.EditImage({ model: options.editImage }),

                // Gallery states.
                new wp.media.controller.GalleryEdit({
                    library: options.selection,
                    editing: options.editing,
                    menu: 'gallery',
                    multiple: true,
                    displaySettings: false,
                    displayUserSettings: false,
                }),

                new wp.media.controller.GalleryAdd({
                    multiple: 'add',
                    editable: true,
                    sortable: true,
                    //toolbar:    'main-insert',
                }),
            ]);


        },

        bindHandlers: function() {
            var handlers, checkCounts;

            Select.prototype.bindHandlers.apply(this, arguments);

            this.on('activate', this.activate, this);

            // Only bother checking media type counts if one of the counts is zero
            checkCounts = _.find(this.counts, function(type) {
                return type.count === 0;
            });

            if (typeof checkCounts !== 'undefined') {
                this.listenTo(wp.media.model.Attachments.all, 'change:type', this.mediaTypeCounts);
            }

            this.on('menu:create:gallery', this.createMenu, this);

            this.on('toolbar:create:main-insert', this.createToolbar, this);
            this.on('toolbar:create:gallery-add', this.createToolbar, this);
            this.on('toolbar:create:main-gallery', this.createToolbar, this);

            this.on('toolbar:create:main-embed', this.mainEmbedToolbar, this);

            handlers = {
                menu: {
                    'default': 'mainMenu',
                    'gallery': 'galleryMenu',

                },

                content: {
                    'embed': 'embedContent',
                    'edit-image': 'editImageContent',
                    'edit-selection': 'editSelectionContent'
                },

                toolbar: {
                    'main-insert': 'mainInsertToolbar',
                    'main-gallery': 'mainGalleryToolbar',
                    'gallery-edit': 'galleryEditToolbar',
                    'gallery-add': 'galleryAddToolbar',
                }
            };

            _.each(handlers, function(regionHandlers, region) {
                _.each(regionHandlers, function(callback, handler) {
                    this.on(region + ':render:' + handler, this[callback], this);
                }, this);
            }, this);
        },

        activate: function() {
            // Hide menu items for states tied to particular media types if there are no items
            _.each(this.counts, function(type) {
                if (type.count < 1) {
                    this.menuItemVisibility(type.state, 'hide');
                }
            }, this);
        },

        mediaTypeCounts: function(model, attr) {
            if (typeof this.counts[attr] !== 'undefined' && this.counts[attr].count < 1) {
                this.counts[attr].count++;
                this.menuItemVisibility(this.counts[attr].state, 'show');
            }
        },

        // Menus
        /**
         * @param {wp.Backbone.View} view
         */
        mainMenu: function(view) {
            /*view.set({
            	'library-separator': new wp.media.View({
            		className:  'separator',
            		priority:   100,
            		attributes: {
            			role: 'presentation'
            		}
            	})
            });*/
        },

        menuItemVisibility: function(state, visibility) {
            var menu = this.menu.get();
            if (visibility === 'hide') {
                menu.hide(state);
            } else if (visibility === 'show') {
                menu.show(state);
            }
        },
        /**
         * @param {wp.Backbone.View} view
         */
        galleryMenu: function(view) {
            var lastState = this.lastState(),
                previous = lastState && lastState.id,
                frame = this;

            view.set({
                cancel: {
                    text: l10n.cancelGalleryTitle,
                    priority: 20,
                    click: function() {
                        if (previous) {
                            frame.setState(previous);
                        } else {
                            frame.close();
                        }

                        // Move focus to the modal after canceling a Gallery.
                        this.controller.modal.focusManager.focus();
                    }
                },
                separateCancel: new wp.media.View({
                    className: 'separator',
                    priority: 40
                })
            });
        },

        // Content
        embedContent: function() {
            var view = new wp.media.view.Embed({
                controller: this,
                model: this.state()
            }).render();

            this.content.set(view);
        },

        editSelectionContent: function() {
            var state = this.state(),
                selection = state.get('selection'),
                view;

            view = new wp.media.view.AttachmentsBrowser({
                controller: this,
                collection: selection,
                selection: selection,
                model: state,
                sortable: true,
                search: false,
                date: false,
                dragInfo: true,

                AttachmentView: wp.media.view.Attachments.EditSelection
            }).render();

            view.toolbar.set('backToLibrary', {
                text: l10n.returnToLibrary,
                priority: -100,

                click: function() {
                    this.controller.content.mode('browse');
                    // Move focus to the modal when jumping back from Edit Selection to Add Media view.
                    this.controller.modal.focusManager.focus();
                }
            });

            // Browse our library of attachments.
            this.content.set(view);

            // Trigger the controller to set focus
            this.trigger('edit:selection', this);
        },

        editImageContent: function() {
            var image = this.state().get('image'),
                view = new wp.media.view.EditImage({ model: image, controller: this }).render();

            this.content.set(view);

            // after creating the wrapper view, load the actual editor via an ajax call
            view.loadEditor();

        },

        // Toolbars

        /**
         * @param {wp.Backbone.View} view
         */
        selectionStatusToolbar: function(view) {
            var editable = this.state().get('editable');

            view.set('selection', new wp.media.view.Selection({
                controller: this,
                collection: this.state().get('selection'),
                priority: -40,

                // If the selection is editable, pass the callback to
                // switch the content mode.
                editable: editable && function() {
                    this.controller.content.mode('edit-selection');
                }
            }).render());
        },

        /**
         * @param {wp.Backbone.View} view
         */
        mainInsertToolbar: function(view) {
            var controller = this;

            this.selectionStatusToolbar(view);

            view.set('insert', {
                style: 'primary',
                priority: 80,
                text: l10n.insertIntoPost,
                requires: { selection: true },

                /**
                 * @ignore
                 *
                 * @fires wp.media.controller.State#insert
                 */
                click: function() {
                    var state = controller.state(),
                        selection = state.get('selection');

                    controller.close();
                    state.trigger('insert', selection).reset();
                }
            });
        },

        /**
         * @param {wp.Backbone.View} view
         */
        mainGalleryToolbar: function(view) {
            var controller = this;

            this.selectionStatusToolbar(view);

            view.set('gallery', {
                style: 'primary',
                text: l10n.createNewGallery,
                priority: 60,
                requires: { selection: true },

                click: function() {
                    var selection = controller.state().get('selection'),
                        edit = controller.state('gallery-edit'),
                        models = selection.where({ type: 'image' });

                    edit.set('library', new wp.media.model.Selection(models, {
                        props: selection.props.toJSON(),
                        multiple: true
                    }));

                    // Jump to Edit Gallery view.
                    this.controller.setState('gallery-edit');

                    // Move focus to the modal after jumping to Edit Gallery view.
                    this.controller.modal.focusManager.focus();
                }
            });
        },



        galleryEditToolbar: function() {
            var editing = this.state().get('editing');
            this.toolbar.set(new wp.media.view.Toolbar({
                controller: this,
                items: {
                    insert: {
                        style: 'primary',
                        text: editing ? l10n.updateGallery : l10n.insertGallery,
                        priority: 80,
                        requires: { library: true },

                        /**
                         * @fires wp.media.controller.State#update
                         */
                        click: function() {
                            var controller = this.controller,
                                state = controller.state();

                            controller.close();
                            state.trigger('update', state.get('library'));

                            // Restore and reset the default state.
                            controller.setState(controller.options.state);
                            controller.reset();
                        }
                    }
                }
            }));
        },

        galleryAddToolbar: function(view) {
            var controller = this;
            this.selectionStatusToolbar(view);

            view.set('insert', {
                style: 'primary',
                text: l10n.addToGallery,
                priority: 80,
                requires: { selection: true },

                /**
                 * @fires wp.media.controller.State#reset
                 */
                click: function() {
                    var controller = this.controller,
                        state = controller.state(),
                        edit = controller.state('gallery-edit');

                    edit.get('library').add(state.get('selection').models);
                    state.trigger('reset');
                    controller.setState('gallery-edit');
                    // Move focus to the modal when jumping back from Add to Gallery to Edit Gallery view.
                    this.controller.modal.focusManager.focus();
                }
            });
        },


    });

}(jQuery, _));