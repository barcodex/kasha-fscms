# kasha-fscms

This library provides dead easy backend for filesystem based CMS.
This CMS is expected to work on top of Kasha framework, but actually it does need only very basic dependencies and does not require fulls tack to run.

As it can be supposed from the name, this library has no database dependencies,
so it can be used on most restrictive hostings and/or can be easily moved between different servers/OSs
without changing anything at all, all content is just copied with OS file management commands.

Surprising possible benefit of storing content in text files instead of the database is possibility to store it under version control.

Every post is just a serialized JSON object, so it can easily be fed to JSON-based front-ends like AngularJS.

The library consists of only one class, \Kasha\FSCMS\Manager, that is responsible for searching, adding, deleting and editing posts (see section API below for more details).

*Do not use this library for complex fast-growing systems.*

## Installation

Install FSCMS library with Composer by adding a requirement into composer.json of your project:

```json
{
   "require": {
        "barcodex/kasha-fscms": "*"
   }
}
```

or requiring it from the command line:

```bash
composer require barcodex/kasha-fscms:*
```

## API

The main thing to remember when using FSCMS library is that we need to specify the root folder for the content.
We do it when creating an instance of Manager class (in this example, let's imaging that controller script wants to store the posts in cms folder of its containing directory:

```php
<?php

require_once "vendor/autoload.php";

$manager = new \Kasha\FSCMS\Manager(__DIR__ . '/cms', 1, 'en');
```

Second parameter here is ID of the current user, and second is current language (we could omit these parameters and values 1 and 'en' respectively would still be used by default).

After class instance is created, we can run just a handful of public methods to manage the posts:

|method|description|
|------|-----------|
|getPost($id)|retrieves the array with post data (empty if id is not valid)|
|deletePost($id)|deletes the specified post from the disk|
|listPostsByType($type)|retrives array of post objects filtered by type|
|listPosts($searchParams)|filters posts by their metadata or field values|
|getMetadata()|returns all known metadata about the content|

Search is also pretty basic - currently, there's support only for exact match. No ranges, greater-than/less-than, etc.

Metadata is used for speeding up the searches.
Without metadata, the search would always be a full scan of the files, which is not exactly the most efficient way of searching.
Anyway, this library is only good for really small sites with up to 100 posts - in this case, combination of full scans with handful of metadata and index files provide quite good performance.

