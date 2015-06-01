<?php

namespace Kasha\FSCMS;

class Manager
{
	private $rootFolder = '';
	private $currentUser = 1;
	private $currentLanguage = 'en';
	private $metadata = array();

	/**
	 * @param string $rootFolder
	 * @param int $currentUser
	 * @param string $currentLanguage
	 */
	public function __construct($rootFolder = '', $currentUser = 1, $currentLanguage = 'en')
	{
		if (!file_exists($rootFolder)) {
			mkdir($rootFolder);
		}

		if (file_exists($rootFolder)) {
			$this->rootFolder = $rootFolder;
			$this->currentUser = $currentUser;
			$this->currentLanguage = $currentLanguage;
			$this->loadMetadata();
		}
	}

	/**
	 *
	 */
	private function loadMetadata()
	{
		// get metadata keys and file names
		$metadataFiles = array();
		foreach (glob($this->rootFolder . '/metadata/*.json') as $fileName) {
			$metadataKey = str_replace('.json', '', basename($fileName));
			$metadataFiles[$metadataKey] = $fileName;
		}
		$metadataKeys = array_keys($metadataFiles);

		// check that all expected files exist
		if (!in_array('posts', $metadataKeys)) {
			$this->regeneratePostsMetadata();
		}

		foreach ($metadataFiles as $metadataKey => $metadataFile) {
			$this->metadata[$metadataKey] = json_decode(file_get_contents($metadataFile), true);
		}
	}

	/**
	 * @return array
	 */
	public function getMetadata()
	{
		return $this->metadata;
	}

	/**
	 *
	 */
	private function regeneratePostsMetadata()
	{
		$posts = array();
		foreach ($this->listPosts() as $postInfo) {
			$id = $postInfo['id'];
			$posts["$id"] = array(
				'id' => $postInfo['id'],
				'type' => $postInfo['type'],
				'status' => $postInfo['status'],
				'published' => $postInfo['published'],
				'language' => $postInfo['language']
			);
		}
		$this->metadata['posts'] = $posts;
		$this->writePostsMetadata($posts);
	}

	/**
	 * @param $posts
	 */
	private function writePostsMetadata($posts)
	{
		$postsJSON = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		file_put_contents($this->rootFolder . '/metadata/posts.json', $postsJSON);
	}

	/**
	 * @param string $type
	 *
	 * @return array
	 */
	public function listPostsByType($type = '')
	{
		// make sure that all posts are listed if $type was not set explicitly
		if ($type == '') {
			$type = '*';
		}

		// return the array of post arrays
		$output = array();
		if ($this->rootFolder != '') {
			foreach (glob($this->rootFolder . '/contents/' . $type . '/*.json') as $postFile) {
				$postInfo = json_decode(file_get_contents($postFile), true);
				$output[$postInfo['id']] = $postInfo;
			}
		}

		return $output;
	}

	/**
	 * @param array $searchParams
	 *
	 * @return array
	 */
	public function listPosts($searchParams = array())
	{
		// first, sort search parameters to metadata and full-data heaps
		$metadataParams = array();
		foreach (array_keys($searchParams) as $paramKey) {
			if (in_array($paramKey, ['type', 'status', 'language'])) {
				$metadataParams[$paramKey] = $searchParams[$paramKey];
				unset($searchParams[$paramKey]);
			}
		}

		// if metadata params are given, we can get ids of posts to search for more params
		$output = array();
		if (count($metadataParams) > 0) {
			// pre-filter using metadata
			foreach ($this->metadata['posts'] as $id => $postMeta) {
				$include = true;
				foreach ($metadataParams as $paramKey => $paramValue) {
					if ($postMeta[$paramKey] == $paramValue) {
						$include = false;
						break;
					}
				}
				if ($include) {
					$output[$id] = $this->getPost($id);
				}
			}
		} else {
			// search on all posts. ouch.
			$output = $this->listPostsByType();
		}

		// now we can filter the values based on post fields
		if (count($searchParams) > 0) {
			foreach (array_keys($output) as $id) {
				$postInfo = $output[$id];
				$exclude = false;
				foreach ($searchParams as $paramKey => $paramValue) {
					if (!isset($searchParams) || $postInfo[$paramKey] != $paramValue) {
						$exclude = true;
						break;
					}
				}
				if ($exclude) {
					unset($output[$id]);
				}
			}
		}

		return $output;
	}

	/**
     * Writes the post to the disk and updates the metadata store
     *  By default, posts stay in "draft" status.
     *  If $status is "published", and $publish is null, current datetime is used
     *
	 * @param $type
	 * @param $postInfo
	 * @param string $status
	 * @param null $publish
	 */
	public function addPost($type, $postInfo, $status = 'draft', $publish = null)
	{
		if ($this->rootFolder != '') {
            // make sure that required content folders exist
			if (!file_exists($this->rootFolder . '/contents')) {
				mkdir($this->rootFolder . '/contents');
			}
			if (!file_exists($this->rootFolder . '/contents/' . $type)) {
				mkdir($this->rootFolder . '/contents/' . $type);
			}

			// add more fields automatically
            $id = $this->getNewID();
			$postInfo['id'] = $id;
			$postInfo['type'] = $type;
			$postInfo['status'] = $status;
			$postInfo['created'] = \Temple\DateTimeUtil::now()->format('Y-m-d H:i:s');
			$postInfo['creator'] = $this->currentUser;
			if ($status == 'published') {
				$postInfo['published'] = !is_null($publish) ? $publish : $postInfo['created'];
			} else {
				$postInfo['published'] = '';
			}

			// write out the JSON file
			$postFilePath = $this->rootFolder . '/contents/' . $type . '/' . $id . '.json';
			file_put_contents($postFilePath, json_encode($postInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // refresh metadata on the disk
            $this->refreshMetadata($postInfo);
		}
	}

    /**
     * Persists current state of the post on the disk.
     *  Does not calculate any values automatically,
     *  so the caller code is responsible for what gets saved.
     *
     * @param $postInfo
     */
    public function updatePost($postInfo)
	{
        // write out the JSON file
        $postFilePath = $this->rootFolder . '/contents/' . $postInfo['type'] . '/' . $postInfo['id'] . '.json';
        file_put_contents($postFilePath, json_encode($postInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // refresh metadata on the disk
        $this->refreshMetadata($postInfo);
	}

	/**
     * Deletes post by its ID.
     *  If post is not mentioned in metadata, does nothing.
     *  However, metadata is regenerated before searching for the file,
     *  so this should not be the real case.
     *
	 * @param $id
	 */
	public function deletePost($id)
	{
		// make sure we have the latest state of posts
		if (!isset($this->metadata['posts'])) {
			$this->regeneratePostsMetadata();
		}
		// delete the file and reference from the metadata
		if (isset($this->metadata['posts'][$id])) {
			$postMeta = $this->metadata['posts'][$id];
			$postType = $postMeta['type'];
			unlink($this->rootFolder . '/contents/' . $postType . '/' . $id . '.json');
			unset($this->metadata['posts'][$id]);
			$this->writePostsMetadata($this->metadata['posts']);
		}
	}

	/**
     * Gets the post as associative array.
     *  Returns empty array if provided id was invalid
     *
	 * @param $id
	 *
	 * @return array|mixed
	 */
	public function getPost($id)
	{
		// make sure we have the latest state of posts
		if (!isset($this->metadata['posts'])) {
			$this->regeneratePostsMetadata();
		}
		// get the file: metadata for the post contains type => use it for constructing the path
		$output = array();
		if (isset($this->metadata['posts'][$id])) {
			$postMeta = $this->metadata['posts'][$id];
			$postType = $postMeta['type'];
			$postJSON = file_get_contents($this->rootFolder . '/contents/' . $postType . '/' . $id . '.json');
			if ($postJSON != '') {
				$output = json_decode($postJSON, true);
			}
		}

		return $output;
	}

    /**
     * Refreshes posts metadata with the latest information about given post
     *
     * @param $postInfo
     */
    private function refreshMetadata($postInfo)
    {
        if (!isset($this->metadata['posts']) || !is_array($this->metadata['posts'])) {
            $this->regeneratePostsMetadata();
        } else {
            $id = $postInfo['id'];
            $this->metadata['posts']["$id"] = array(
                'id' => $id,
                'type' => $postInfo['type'],
                'status' => $postInfo['status'],
                'published' => $postInfo['published'],
                'language' => $postInfo['language']
            );
            $this->writePostsMetadata($this->metadata['posts']);
        }
    }

    /**
     * Calculates the next ID for the post.
     *  This method is not thread safe, but that's ok
     *  because we assume that there are no concurrent admin sessions
     *
	 * @return int
	 */
	private function getNewID()
	{
		$ids = array_keys($this->metadata['posts']);
		$lastId = (count($ids) > 0) ? max($ids) : 0;

		return $lastId + 1;
	}
}
