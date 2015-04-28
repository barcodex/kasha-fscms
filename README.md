# kasha-fscms

This library provides dead easy backend for filesystem based CMS.
This CMS is expected to work on top of Kasha framework, but actually it does need only very basic dependencies and does not require fulls tack to run.

The library consists of only class, \Kasha\FSCMS\Manager, that is responsible for searching, adding, deleting and editing posts.

Every post is just a serialized JSON object, so it can easily be fed to JSON-based frontends like AngularJS.

