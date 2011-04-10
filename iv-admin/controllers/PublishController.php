<?php

class PublishController extends ivController
{

	/**
	 * Publish gallery
	 *
	 */
	function indexAction()
	{
		$crumbs = ivPool::get('breadCrumbs');
		$crumbs->push('缩略图', 'index.php?c=publish');

		$path = ivPath::canonizeRelative($this->_getParam('path', $this->conf->get('/config/imagevue/settings/contentfolder'), 'path'));
		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $path);
		if (!$folder) {
			$this->_redirect('index.php');
		}

		$this->view->assign('path', $path);

		$contentFolder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . ivPath::canonizeRelative($this->conf->get('/config/imagevue/settings/contentfolder')));
		$iterator = new ivRecursiveFolderIterator($contentFolder);
		$this->view->assign('folderTreeIterator', new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST));

		$publishData = $this->_getParam('publishData');
		if (is_array($publishData)) {
			$this->_forward('done', 'publish');
		}
	}

	/**
	 * End of gallery publishing
	 *
	 */
	function doneAction()
	{
		$publishData = $this->_getParam('publishData');

		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . ivPath::canonizeRelative($publishData['path']));
		if (!$folder) {
			$this->_redirect('index.php');
		}

		if (isset($publishData['width']) && ((integer) $publishData['width'] > 0)) {
			$this->view->assign('width', (integer) $publishData['width']);
		}

		if (isset($publishData['height']) && ((integer) $publishData['height'] > 0)) {
			$this->view->assign('height', (integer) $publishData['height']);
		}

		if (isset($publishData['resizetype']) && in_array($publishData['resizetype'], array(ivImage::IMAGE_RESIZETYPE_RESIZETOBOX, ivImage::IMAGE_RESIZETYPE_CROPTOBOX))) {
			$this->view->assign('resizetype', $publishData['resizetype']);
		}

		$iterator = new ivRecursiveFolderIterator($folder);
		$this->view->assign('folderTreeIterator', new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST));
		$this->view->assign('missingOnly', (($publishData['thumbnails']=='create') ? '1' : '0'));
		$this->view->assign('contentPath', ivPath::canonizeRelative($this->conf->get('/config/imagevue/settings/contentfolder')));
	}

}