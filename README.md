# kasha-fscms

This library provides dead easy backend for filesystem based CMS.
This CMS is expected to work on top of Kasha framework,
but actually it does need only very basic dependencies and does not require full Kasha stack to run.

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

## Folder structure

Since CMS is based on the file system, it is very important to understand the folder structure.

When Manager object is instantiated, its constructor gets an address of a CMS root folder as a parameter.
Under this root folder, all data is contained - the main reason is, of course, the "contents" folder. There all the posts themselves are stored.
Since CMS supports different types of posts, there are as many subfolders under "contents", as there are different types.
In each type folder, posts are stored one per .json file.

It is up to framework integrator to decide the structure of the post objects, but it is important to know about some reserved names for the fields, used internally by the framework and thus, unavailable to describe custom fields:

|field    |description|
|---------|-----------|
|id       |id of the post is unique throughout all types|
|type     |type of the post, can be any string
|status   |either 'draft' or 'published'
|created  |date of creation, as all other dates, is given in 'Y-m-d H:i:s' format|
|published|in case of status being 'published', this contains date of publishing in 'Y-m-d H:i:s' format, otherwise empty string|
|creator  |id of the creator|

Every post has its set of metadata, which is stored separately.
To speed up the searches, metadata of all posts is stored in the same posts.json file.
This file is stored in "metadata" folder inside of the CMS root folder, to make sure it is not confused with the contents.

Thus, the entire folder structure looks like this:

```
\contents
  \type1
    \id1.json
     ...
  ...
\metadata
 \posts.json

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
|addPost($type, $postInfo, $status = 'draft', $publish = null)|adds a new post|
|updatePost($postInfo)|updates the post|
|deletePost($id)|deletes the specified post from the disk|
|listPostsByType($type)|retrieves array of post objects filtered by type|
|listPosts($searchParams)|filters posts by their metadata or field values|
|getMetadata()|returns all known metadata about the content|

Search is also pretty basic - currently, there's support only for exact match. No ranges, greater-than/less-than, etc.

Metadata is used for speeding up the searches.
Without metadata, the search would always be a full scan of the files, which is not exactly the most efficient way of searching.
Anyway, this library is only good for really small sites with up to 100 posts - in this case, combination of full scans with handful of metadata and index files provide quite good performance.

## Further integrations

Anyone is welcome to fork the library and extend it with support for different post types.
Since every post is just a JSON object, it is totally possible to have whatever fields in the object, granted that they do not conflict with reserved fields used in the metadata (See the section Folder Structure above).

API is sufficient to build a RESTful application with any desired frontend.