<?php
namespace WebbuildersGroup\LoginFiles\Flysystem;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore as SS_FlysystemAssetStore;
use SilverStripe\Control\Controller;
use SilverStripe\Security\Security;

class FlysystemAssetStore extends SS_FlysystemAssetStore
{
    public function getResponseFor($asset)
    {
        $public = $this->getPublicFilesystem();
        $protected = $this->getProtectedFilesystem();
        
        // If the file exists in the protected store and the user has been explicitely granted access to it
        if (!$public->has($asset) && $protected->has($asset) && !$this->isGranted($asset)) {
            $parsedFileID = $this->parseFileID($asset);
            if ($parsedFileID && ($file = File::get()->filter(['FileFilename' => $parsedFileID['Filename']])->first()) && $file->isPublished()) {
                if ($file->canView()) {
                    //There was no grant present but the user can view the published file
                    $this->grant($parsedFileID['Filename'], $parsedFileID['Hash']);
                } else {
                    return Security::permissionFailure(Controller::curr());
                }
            }
        }
        
        return parent::getResponseFor($asset);
    }
}
