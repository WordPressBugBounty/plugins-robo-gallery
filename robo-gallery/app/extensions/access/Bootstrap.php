<?php
namespace RoboGallery\app\extensions\access;

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

/**
 * Class Bootstrap
 * Initializes and runs the AccessMode with its dependencies.
 */
class Bootstrap
{
    public function run(): void
    {
        // Create instances of the required classes
        $excludeManager    = new ExcludeListManager();
        $tokenGenerator    = new GalleryTokenGenerator();
        $permalinkModifier = new GalleryPermalinkModifier();
        $rewriteManager    = new GalleryRewriteRuleManager();
        $accessManager     = new GalleryAccessManager();
        $passwordManager   = new AccessPasswordManager();

        // Create AccessMetaManager with dependency injection
        $metaManager = new AccessMetaManager($tokenGenerator, $passwordManager, $excludeManager);

        // Create FilterPrivateGallery with dependency injection
        $filterPrivateGallery = new FilterPrivateGallery($excludeManager);

        // Create AccessPermalinkHandler with dependency injection
        $permalinkHandler = new AccessPermalinkHandler($permalinkModifier);

        // Create AccessMode with all dependencies injected
        new AccessMode(
            $accessManager,
            $permalinkHandler,
            $rewriteManager,
            $filterPrivateGallery,
            $metaManager
        );
    }
}
