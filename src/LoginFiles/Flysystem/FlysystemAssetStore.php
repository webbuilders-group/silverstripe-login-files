<?php
namespace WebbuildersGroup\LoginFiles\Flysystem;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore as SS_FlysystemAssetStore;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

class FlysystemAssetStore extends SS_FlysystemAssetStore
{
    /**
     * Whether to redirect protected urls or not when the url would normally 404
     * @config WebbuildersGroup\LoginFiles\Flysystem\FlysystemAssetStore.redirect_protected
     * @var bool
     * @default true
     */
    private static $redirect_protected = true;
    
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
        
        // If we found a URL to redirect to that is protected
        if ($this->config()->redirect_protected && !$public->has($asset) && !$protected->has($asset) && $redirectUrl = $this->_searchForEquivalentFileID($asset)) {
            if ($redirectUrl != $asset && ($public->has($redirectUrl) || $protected->has($redirectUrl))) {
                $response = new HTTPResponse(null, $this->config()->get('redirect_response_code'));
                /** @var PublicAdapter $adapter */
                $adapter = $this->getPublicFilesystem()->getAdapter();
                $response->addHeader('Location', $adapter->getPublicUrl($redirectUrl));
                
                return $response;
            } else {
                // Something weird is going on e.g. a publish file without a physical file
                return $this->createMissingResponse();
            }
        }
        
        return parent::getResponseFor($asset);
    }

    /**
     * Given a FileID, try to find an equivalent file ID for a more recent file using the latest format.
     * @param string $asset
     * @return string
     */
    private function _searchForEquivalentFileID($asset)
    {
        // If File is not versionable, let's bail
        if (!class_exists(Versioned::class) || !File::has_extension(Versioned::class)) {
            return '';
        }

        $parsedFileID = $this->parseFileID($asset);
        if ($parsedFileID && $parsedFileID['Hash']) {
            // Try to find a live version of this file
            $stage = Versioned::get_stage();
            Versioned::set_stage(Versioned::LIVE);
            $file = File::get()->filter(['FileFilename' => $parsedFileID['Filename']])->first();
            Versioned::set_stage($stage);

            // If we found a matching live file, let's see if our hash was publish at any point
            if ($file) {
                $oldVersionCount = $file->allVersions(
                    [
                        ['"FileHash" like ?' => DB::get_conn()->escapeString($parsedFileID['Hash']) . '%'],
                        ['not "FileHash" like ?' => DB::get_conn()->escapeString($file->getHash())],
                        '"WasPublished"' => true
                    ],
                    "",
                    1
                )->count();
                // Our hash was published at some other stage
                if ($oldVersionCount > 0) {
                    return $this->getFileID($file->getFilename(), $file->getHash(), $parsedFileID['Variant']);
                }
            }
        }

        // Let's see if $asset is a legacy URL that can be map to a current file
        $parsedFileID = $this->_parseLegacyFileID($asset);
        if ($parsedFileID) {
            $filename = $parsedFileID['Filename'];
            $variant = $parsedFileID['Variant'];
            // Let's try to match the plain file name
            $stage = Versioned::get_stage();
            Versioned::set_stage(Versioned::LIVE);
            $file = File::get()->filter(['FileFilename' => $filename])->first();
            Versioned::set_stage($stage);

            if ($file) {
                return $this->getFileID($filename, $file->getHash(), $variant);
            }
        }

        return '';
    }
    
    /**
     * Try to parse a file ID using the old SilverStripe 3 format legacy or the SS4 legacy filename format.
     *
     * @param string $fileID
     * @return array
     */
    private function _parseLegacyFileID($fileID)
    {
        // assets/folder/_resampled/ResizedImageWzEwMCwxMzNd/basename.extension
        $ss3Pattern = '#^(?<folder>([^/]+/)*?)(_resampled/(?<variant>([^/.]+))/)?((?<basename>((?<!__)[^/.])+))(?<extension>(\..+)*)$#';
        // assets/folder/basename__ResizedImageWzEwMCwxMzNd.extension
        $ss4LegacyPattern = '#^(?<folder>([^/]+/)*)(?<basename>((?<!__)[^/.])+)(__(?<variant>[^.]+))?(?<extension>(\..+)*)$#';
        
        // not a valid file (or not a part of the filesystem)
        $matches = [];
        if (!preg_match($ss3Pattern, $fileID, $matches) && !preg_match($ss4LegacyPattern, $fileID, $matches)) {
            return null;
        }
        
        $filename = $matches['folder'] . $matches['basename'] . $matches['extension'];
        $variant = isset($matches['variant']) ? $matches['variant'] : null;
        return [
            'Filename' => $filename,
            'Variant' => $variant
        ];
    }
}
