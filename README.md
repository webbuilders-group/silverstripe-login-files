Silverstripe Login Files
=================
Changes how assets in Silverstripe that require a logged in user are handled by redirecting them to login rather than returning a page not found while keeping draft assets as a page not found. Restoring similar behavior to that of [silverstripe/secureassets](https://github.com/silverstripe/silverstripe-secureassets).

## Maintainer Contact
* Ed Chipman ([UndefinedOffset](https://github.com/UndefinedOffset))


## Requirements
* SilverStripe Assets ~1.4|~2.0|~3.0


## Installation
```
composer require webbuilders-group/silverstripe-login-files
```

## Configuration
By default this module will also redirect protected files when they are either missing their hash (for example a legacy url) or when the hash is out of date, this can be turned off by adding the following to your yaml configuration:

```yml
WebbuildersGroup\LoginFiles\Flysystem\FlysystemAssetStore:
  redirect_protected: false
```
