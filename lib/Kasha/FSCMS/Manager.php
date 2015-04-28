<?php

namespace Kasha\FSCMS;

class Manager
{
	private $rootFolder = '';
	private $currentUser = 1;
	private $currentLanguage = 'en';
	private $metadata = array();

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

	public function getMetadata()
	{
		return $this->metadata;
	}

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

	private function writePostsMetadata($posts)
	{
		$postsJSON = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		file_put_contents($this->rootFolder . '/metadata/posts.json', $postsJSON);
	}

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

	public function addPost($type, $post, $status = 'draft', $publish = null)
	{
		if ($this->rootFolder != '') {
			$id = $this->getNewID();
			if (!file_exists($this->rootFolder . '/contents')) {
				mkdir($this->rootFolder . '/contents');
			}
			if (!file_exists($this->rootFolder . '/contents/' . $type)) {
				mkdir($this->rootFolder . '/contents/' . $type);
			}
			// add more fields automatically
			$post['id'] = $id;
			$post['type'] = $type;
			$post['status'] = $status;
			$post['created'] = \Temple\DateTimeUtil::now()->format('Y-m-d H:i:s');
			$post['creator'] = $this->currentUser;
			if ($status == 'published') {
				$post['published'] = !is_null($publish) ? $publish : $post['created'];
			} else {
				$post['published'] = '';
			}
			// write out the JSON file
			$postFile = $this->rootFolder . '/contents/' . $type . '/' . $id . '.json';
			file_put_contents($postFile, json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
			if (!isset($this->metadata['posts']) || !is_array($this->metadata['posts'])) {
				$this->regeneratePostsMetadata();
			} else {
				$this->metadata['posts']["$id"] = array(
					'id' => $post['id'],
					'type' => $post['type'],
					'status' => $post['status'],
					'published' => $post['published'],
					'language' => $post['language']
				);
				$this->writePostsMetadata($this->metadata['posts']);
			}
		}
	}

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

	public function getPost($id)
	{
		// make sure we have the latest state of posts
		if (!isset($this->metadata['posts'])) {
			$this->regeneratePostsMetadata();
		}
		// get the file
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
	 * @return int
	 */
	private function getNewID()
	{
		$ids = array_keys($this->metadata['posts']);
		$lastId = (count($ids) > 0) ? max($ids) : 0;

		return $lastId + 1;
	}
}
