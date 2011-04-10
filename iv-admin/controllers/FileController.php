<?php

class FileController extends ivController
{
	/**
	 * Path
	 * @var string
	 */
	var $path;

	/**
	 * Pre-dispatching
	 *
	 */
	function _preDispatch()
	{
		$path = ivPath::canonizeRelative($this->_getParam('path', $this->conf->get('/config/imagevue/settings/contentfolder'), 'path'), true);
		if (!ivAcl::isAllowedPath($path)) {
			if (is_null(ivAcl::getAllowedPath())) {
				$this->_forward('login', 'cred');
				return;
			} else {
				$this->_redirect('index.php');
			}
		}
		$fullPath = ROOT_DIR . $path;
		$include = $this->conf->get('/config/imagevue/settings/allowedext');
		if (is_dir($fullPath)) {
			$this->_redirect('index.php?path=' . urlencode($path));
		} elseif (!is_file($fullPath)) {
			$this->_redirect('index.php?c=config');
		} elseif (!in_array(strtolower(ivFilepath::suffix($fullPath)), $include)) {
			$this->_redirect('index.php?path=' . urlencode(ivFilepath::directory($path)));
		}
		$this->path = $path;
	}

	/**
	 * Default action
	 *
	 */
	function indexAction()
	{
		$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path);
		if (!$file) {
			$this->_redirect('index.php');
		}

		$siblings = $file->getSiblings();

		// Save file data
		$newdata = $this->_getParam('newdata');
		if (is_string($this->_getParam('save')) && is_array($newdata)) {
			foreach ($newdata as $name => $value) {
				$file->$name = $value;
			}
			if ($file->save()) {
				ivMessenger::add(ivMessenger::NOTICE, '文件数据保存成功');
			} else {
				ivMessenger::add(ivMessenger::ERROR, "文件数据保存失败");
			}
			if ($this->_getParam('editNext', false)) {
				$this->_redirect('index.php?c=file&path=' . urlencode($siblings->next->getPrimary()));
			} else {
				$this->_redirect($_SERVER['REQUEST_URI']);
			}
		}

		$contentFolder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . ivAcl::getAllowedPath());
		$iterator = new ivRecursiveFolderIterator($contentFolder);
		$this->view->assign('folderTreeIterator', new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST));

		$this->view->assign('path', $this->path);
		$this->placeholder->set('path', $this->path);

		$this->view->assign('file', $file);
		$this->view->assign('nextFile', $siblings->next);
		$this->view->assign('prevFile', $siblings->previous);
		$this->view->assign('current', $siblings->current);
		$this->view->assign('count', $siblings->count);

		$_SESSION['imagevue']['lastManageLink'] = '?c=file&amp;path=' . urlencode($this->path);
	}

	/**
	 * Copy/move file
	 *
	 */
	function moveAction()
	{
		$isMove = !$this->_getParam('copy', false);
		$targetPath = ivPath::canonizeRelative($this->_getParam('target', null, 'path'));
		$result = false;
		if (!empty($targetPath) && ($folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $targetPath)) && ($file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path))) {
			$result = ivMapperFactory::getMapper('file')->copyFile($file, $folder);
			if ($isMove) {
				$result &= $file->delete();
			}
		}
		if ($isMove) {
			ivMessenger::add(ivMessenger::NOTICE, ($result ? '文件移动成功' : "文件移动失败"));
		} else {
			ivMessenger::add(ivMessenger::NOTICE, ($result ? '文件复制成功' : "文件复制失败"));
		}
		$this->_redirect('index.php?path=' . urlencode($targetPath));
	}

	/**
	 * Delete file
	 *
	 */
	function deleteAction()
	{
		$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path);
		if ($file) {
			$result = $file->delete();
			ivMessenger::add(ivMessenger::NOTICE, ($result ? '文件删除成功' : "文件删除失败"));
		} else {
			ivMessenger::add(ivMessenger::NOTICE, '文件未找到');
		}
		$this->_redirect('index.php?path=' . urlencode(ivFilepath::directory($this->path)));
	}

	/**
	 * Rotate image
	 *
	 */
	function rotateAction()
	{
		$direction = $this->_getParam('dir', ivImage::IMAGE_ROTATE_CW, 'alnum');
		if (!in_array($direction, array(ivImage::IMAGE_ROTATE_CW, ivImage::IMAGE_ROTATE_CCW))) {
			$direction = ivImage::IMAGE_ROTATE_CW;
		}
		$image = new ivImage(ROOT_DIR . $this->path);
		$image->rotate($direction);
		$image->write();
		ivMessenger::add(ivMessenger::NOTICE, '文件旋转成功');
		$this->_redirect('index.php?c=file&path=' . urlencode($this->path));
	}

	function getthumbAction()
	{
		$errorReporting = error_reporting(0);
		$this->_setNoRender();
		if ($file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path)) {
			$file->generateThumbnail($this->_getParam('width'), $this->_getParam('height'), $this->_getParam('resizetype'));

			$thumbPath = ROOT_DIR . $file->thumbnail;
			$data = @getimagesize($thumbPath);
			if (isset($data['mime'])) {
				// FIXME Debug data
				xFireDebug('Generation Time ' . getGenTime() . ' sec');
				header('Cache-Control: public');
				header('Expires: Fri, 30 Dec 1999 19:30:56 GMT');
				header('Content-Type: ' . $data['mime']);
				readfile($thumbPath);
			}
			error_reporting($errorReporting);
		}
	}

	function thumbareaAction()
	{
		$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path);
		if (!$file) {
			ivMessenger::add(ivMessenger::NOTICE, '文件未找到');
			$this->_redirect('index.php');
		}

		if (!is_a($file, 'ivFileImage')) {
			ivMessenger::add(ivMessenger::NOTICE, '文件类型并非图片');
			$this->_redirect('index.php');
		}

		$this->_disableLayout();

		if (!empty($_POST)) {
			$errorReporting = error_reporting(0);

			$file->cropThumbnail($this->_getParam('x'), $this->_getParam('y'), $this->_getParam('width'), $this->_getParam('height'));
			$this->view->assign('done', true);
		}

		$this->view->assign('file', $file);
	}

	/**
	 * Post-dispatching
	 *
	 */
	function _postDispatch()
	{
		if ($this->needRender()) {
			$crumbs = ivPool::get('breadCrumbs');
			$brCrumbsKeys = array_explode_trim('/', $this->path);
			if ($brCrumbsKeys !== false) {
				$lastCrumbKey = end($brCrumbsKeys);
				$path = '';
				foreach ($brCrumbsKeys as $key) {
					if ($lastCrumbKey == $key) {
						$path .= $key;
						$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $path);
						if (!$file) {
							continue;
						}
						$crumbs->push($file->getTitle(), 'index.php?c=file&amp;path=' . urlencode($path));
					} else {
						$path .= $key . '/';
						$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $path);
						if (!$folder) {
							continue;
						}
						$crumbs->push($folder->getTitle(), 'index.php?path=' . urlencode($path), '', ($folder->isHidden() ? '隐藏' : ''));
					}
				}
			}
		}
	}

}
?>
