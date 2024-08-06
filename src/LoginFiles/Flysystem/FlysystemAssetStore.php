<?php
namespace WebbuildersGroup\LoginFiles\Flysystem;

use SilverStripe\Assets\Flysystem\FlysystemAssetStore as SS_FlysystemAssetStore;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\LoginForms\EnablerExtension;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;

class FlysystemAssetStore extends SS_FlysystemAssetStore
{
    /**
     * Whether to redirect protected urls or not when the url would normally 404
     * @config WebbuildersGroup\LoginFiles\Flysystem\FlysystemAssetStore.redirect_protected
     * @var bool
     * @default true
     */
    private static $redirect_protected = true;

    /**
     * {@inheritDoc}
     */
    public function createDeniedResponse()
    {
        // If silverstripe/login-forms is installed ensure we bootstrap it's theme
        if (class_exists(EnablerExtension::class)) {
            SSViewer::set_themes(Config::inst()->get(EnablerExtension::class, 'login_themes'));
        }

        return Security::permissionFailure(Controller::curr());
    }

    /**
     * {@inheritDoc}
     */
    public function getResponseFor($asset)
    {
        $this->afterExtending(
            'updateResponse',
            function (HTTPResponse $response, $asset, $context) {
                if ($this->config()->redirect_protected && $response->getStatusCode() == $this->config()->missing_response_code && empty($context)) {
                    $protected = $this->getProtectedFilesystem();
                    $protectedStrategy = $this->getPublicResolutionStrategy();

                    // Check if we can find a URL to redirect to
                    if ($parsedFileID = $protectedStrategy->softResolveFileID($asset, $protected)) {
                        $redirectFileID = $parsedFileID->getFileID();
                        $permanentFileID = $protectedStrategy->buildFileID($parsedFileID);

                        // If our redirect FileID is equal to the permanent file ID, this URL will never change
                        $code = ($redirectFileID === $permanentFileID ? $this->config()->get('permanent_redirect_response_code') : $this->config()->get('redirect_response_code'));

                        /** @var \SilverStripe\Assets\Flysystem\ProtectedAdapter $adapter */
                        $adapter = $protected->getAdapter();
                        $response->addHeader('Location', $adapter->getProtectedUrl($redirectFileID));
                        $response->setStatusCode($code);
                    }
                }
            },
        );

        return parent::getResponseFor($asset);
    }
}
