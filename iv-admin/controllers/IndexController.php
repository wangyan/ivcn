<?php

class IndexController extends ivController
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
		$path = ivPath::canonizeRelative($this->_getParam('path', $this->conf->get('/config/imagevue/settings/contentfolder'), 'path'));
		if (!ivAcl::isAllowedPath($path) && !ivAcl::isAllowedPath(ivPath::canonizeRelative($path))) {
			if (is_null(ivAcl::getAllowedPath())) {
				$this->_forward('login', 'cred');
				return;
			} else {
				$path = ivAcl::getAllowedPath();
			}
		}
		$fullPath = ROOT_DIR . $path;
		if (is_dir($fullPath)) {
			$path = ivPath::canonizeRelative($path);
		} elseif (is_file($fullPath)) {
			$this->_redirect('index.php?c=file&path=' . urlencode($path));
		} else {
			$this->_redirect('index.php?c=config');
		}
		$this->path = $path;
	}

	/**
	 * Default action
	 *
	 */
	function indexAction()
	{
		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path);
		if (!$folder) {
			$this->_redirect('index.php');
		}

		// Save folder data
		$newdata = $this->_getParam('newdata');
		
		if (is_array($newdata)) {

			if (isset($newdata['newWindow']) && isset ($newdata['pageContent'])) $newdata['pageContent'].='*_blank';

			foreach ($newdata as $name => $value) {
				$folder->$name = $value;
			}
			if (isset($_POST['folderPwd'])) {
				if (empty($_POST['folderPwd'])) {
					$folder->removePassword();
				} elseif ('******' != $_POST['folderPwd']) {
					$folder->setPassword((string) $_POST['folderPwd']);
				}
			}
			if ($folder->save()) {
				ivMessenger::add(ivMessenger::NOTICE, '文件夹数据保存成功');
			} else {
				$folder->refresh();
				ivMessenger::add(ivMessenger::ERROR, "文件夹数据保存失败");
			}
			$this->_redirect($_SERVER['REQUEST_URI']);
		}

		$contentFolder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . ivAcl::getAllowedPath());
		$iterator = new ivRecursiveFolderIterator($contentFolder);
		$this->view->assign('folderTreeIterator', new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST));

		$this->view->assign('path', $this->path);
		$this->placeholder->set('path', $this->path);

		$this->view->assign('folder', $folder);
		$this->view->assign('sorts', ivFolder::getSortTypes());
		$this->view->assign('contentPath', ivPath::canonizeRelative($this->conf->get('/config/imagevue/settings/contentfolder')));
		$this->view->assign('uploaderType', $this->conf->get('/config/imagevue/settings/uploader/type'));

		// FIXME: Refactoring, not needed?
		// $this->view->assign('allowRenaming', ivPath::canonizeRelative($this->path) !== ivPath::canonizeRelative($this->conf->get('/config/imagevue/settings/contentfolder')));
		$viewsNode = $this->conf->findByXPath('/config/imagevue/settings/defaultViewAs');
		$this->view->assign('views', $viewsNode->getValues());

		$_SESSION['imagevue']['lastManageLink'] = '?path=' . urlencode($this->path);
	}

	/**
	 * Create new folder
	 *
	 */
	function createAction()
	{
		$newDirName = $this->_getParam('name');
		if (!preg_match('/^[_\w\d\s]+$/i', $newDirName)) {
			ivMessenger::add(ivMessenger::ERROR, '文件夹名称只可使用数字、字母和"_"下划线');
			$this->_redirect('index.php?path=' . urlencode($this->path));
		}
		if (is_string($newDirName)) {
			$newDirPath = ivPath::canonizeRelative($this->path . $newDirName);
			if (file_exists(ROOT_DIR . $newDirPath)) {
				ivMessenger::add(ivMessenger::WARNING, "文件夹 '<strong>$newDirName</strong>' 已经存在");
			} else if (mkdirRecursive(ROOT_DIR . $newDirPath, octdec($this->conf->get('/config/imagevue/settings/chmod')))) {
				ivMessenger::add(ivMessenger::NOTICE, '文件夹创建成功');
			} else {
				ivMessenger::add(ivMessenger::ERROR, "文件夹创建失败");
				$newDirPath = null;
			}
		}
		$this->_redirect('index.php?path=' . urlencode(isset($newDirPath) ? $newDirPath : $this->path));
	}

	/**
	 * Rename folder
	 *
	 */
	function renameAction()
	{
		if (ivPath::canonizeRelative($this->path) === ivPath::canonizeRelative($this->conf->get('/config/imagevue/settings/contentfolder'))) {
			ivMessenger::add(ivMessenger::ERROR, '不能重命名 content 文件夹');
			$this->_redirect('index.php?path=' . urlencode($this->path));
		}
		$newDirName = $this->_getParam('name');
		if (!preg_match('/^[_\w\d\s]+$/i', $newDirName)) {
			ivMessenger::add(ivMessenger::ERROR, '文件夹名称只可使用数字、字母和"_"下划线');
			$this->_redirect('index.php?path=' . urlencode($this->path));
		}
		$newDirPath = ivPath::canonizeRelative(dirname($this->path)) . ivPath::canonizeRelative($newDirName);
		$result = @rename(ROOT_DIR . $this->path, ROOT_DIR . $newDirPath);
		if ($result) {
			ivMessenger::add(ivMessenger::NOTICE, '文件夹重命名成功');
			$this->_redirect('index.php?path=' . urlencode($newDirPath));
		} else {
			ivMessenger::add(ivMessenger::ERROR, '文件夹重命名失败');
			$this->_redirect('index.php?path=' . urlencode($this->path));
		}
	}

	/**
	 * Delete folder
	 *
	 */
	function deleteAction()
	{
		if ($this->conf->get('/config/imagevue/settings/contentfolder') == $this->path) {
			ivMessenger::add(ivMessenger::ERROR, 'Content folder cannot be deleted');
		} else {
			if ($folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path)) {
				$folder->delete();
				ivMessenger::add(ivMessenger::NOTICE, '文件夹删除成功');
			} else {
				ivMessenger::add(ivMessenger::ERROR, '文件夹删除失败');
			}
		}
		$this->_redirect('index.php');
	}

	/**
	 * Move files
	 *
	 */
	function moveFilesAction()
	{
		$targetPath = ivPath::canonizeRelative($this->_getParam('target', null, 'path'));
		$moved = 0;
		$skipped = 0;
		$mapper = ivMapperFactory::getMapper('file');
		$targetFolder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $targetPath);
		if ($targetFolder) {
			foreach ($this->_getParam('selected', array()) as $filename) {
				$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path . $filename);
				if ($file && $mapper->copyFile($file, $targetFolder) && $file->delete()) {
					$moved++;
				} else {
					$skipped++;
				}
			}
			ivMessenger::add(ivMessenger::NOTICE, $moved . ' 文件移动成功, ' . $skipped . ' 忽略该文件');
		}
		$this->_redirect('index.php?path=' . urlencode($this->path));
	}

	/**
	 * Copy files
	 *
	 */
	function copyFilesAction()
	{
		$targetPath = ivPath::canonizeRelative($this->_getParam('target', null, 'path'));
		$copied = 0;
		$skipped = 0;
		$mapper = ivMapperFactory::getMapper('file');
		$targetFolder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $targetPath);
		if ($targetFolder) {
			foreach ($this->_getParam('selected', array()) as $filename) {
				$file = $mapper->find(ROOT_DIR . $this->path . $filename);
				if ($file && $mapper->copyFile($file, $targetFolder)) {
					$copied++;
				} else {
					$skipped++;
				}
			}
			ivMessenger::add(ivMessenger::NOTICE, "文件 $copied 复制成功, $skipped 个文件被忽略");
		}
		$this->_redirect('index.php?path=' . urlencode($this->path));
	}

	/**
	 * Delete files
	 *
	 */
	function deleteFilesAction()
	{
		$deleted = 0;
		$skipped = 0;
		foreach ($this->_getParam('selected', array()) as $filename) {
			$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path . $filename);
			if ($file && $file->delete()) {
				$deleted++;
			} else {
				$skipped++;
			}
		}
		ivMessenger::add(ivMessenger::NOTICE, $deleted . ' 个文件删除成功, ' . $skipped . ' 个文件被忽略');
		$this->_redirect('index.php?path=' . urlencode($this->path));
	}

	/**
	 * Sets given file as preview image for given folder
	 *
	 */
	function setPreviewAction()
	{
		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path);
		$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path . decodeFilenameBase64($this->_getParam('id')));
		if ($folder && $file) {
			$folder->previewimage = $file->name;
			$folder->save();

			if ($this->isXmlHttpRequest()) {
				$this->_setNoRender();
				echo '文件 ' . $file->name . ' 已被设置为预览图片';
			} else {
				ivMessenger::add(ivMessenger::NOTICE, '文件 ' . $file->name . ' 已被设置为预览图片');
				$this->_redirect('index.php?path=' . urlencode($folder->getPrimary()));
			}
		} else {
			if ($this->isXmlHttpRequest()) {
				$this->_setNoRender();
				echo '文件未找到';
			} else {
				ivMessenger::add(ivMessenger::NOTICE, '文件未找到');
				$this->_redirect('index.php');
			}
		}
	}

	/**
	 * Sort files
	 *
	 */
	function sortFilesAction()
	{
		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path);
		if ($folder) {
			foreach ($_POST['sort'] as $order => $id) {
				$file = ivMapperFactory::getMapper('file')->find(ROOT_DIR . $this->path . decodeFilenameBase64($id, 5));
				if ($file) {
					$file->order = $order + 1;
					$file->save();
				}
			}
			if (ivFolder::SORT_ORDER_MANUAL != $folder->sort) {
				$folder->sort = ivFolder::SORT_ORDER_MANUAL;
				$folder->save();
			}
		}

		if ($this->isXmlHttpRequest()) {
			$this->_setNoRender();
			echo 'OK';
		} else {
			$this->_redirect($_SERVER['HTTP_REFERER']);
		}
	}

	/**
	 * Sort folders
	 *
	 */
	function sortFoldersAction()
	{
		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path);
		if ($folder) {
			foreach ($_POST['sort'] as $order => $id) {
				$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path . decodeFilenameBase64($id) . DS);
				if ($folder) {
					$folder->order = $order + 1;
					$folder->save();
				}
			}
		}

		if ($this->isXmlHttpRequest()) {
			$this->_setNoRender();
			echo 'OK';
		} else {
			$this->_redirect($_SERVER['HTTP_REFERER']);
		}
	}

	/**
	 * Hide folder
	 *
	 */
	function hideAction()
	{
		if ($this->conf->get('/config/imagevue/settings/contentfolder') == $this->path) {
			ivMessenger::add(ivMessenger::ERROR, 'Content folder cannot be hid');
		} else {
			if ($folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path)) {
				$folder->hidden = 'true';
				$folder->save();
			}
		}
		$this->_redirect($_SERVER['HTTP_REFERER']);
	}

	/**
	 * Unhide folder
	 *
	 */
	function unhideAction()
	{
		if ($folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path)) {
			$folder->hidden = 'false';
			$folder->save();
		}
		$this->_redirect($_SERVER['HTTP_REFERER']);
	}

	/**
	 * Upload file
	 *
	 */
	function uploadAction()
	{
		$this->_setNoRender();
		if (!isset($_FILES['Filedata'])) {
			header("HTTP/1.1 500 Internal Server Error");
			echo "错误，文件未找到！";
			return;
		}
		$imageData = $_FILES['Filedata'];
		$imageName = $imageData['name'];
		if (get_magic_quotes_gpc()) {
			$imageName = stripslashes($imageName);
		}
		if (!ivFilepath::matchSuffix($imageName, $this->conf->get('/config/imagevue/settings/allowedext'))) {
			header("HTTP/1.1 403 Forbidden");
			echo "错误， 文件扩展错误！";
		} else {
			$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path);
			if ($folder) {
				$imageName = ivMapperFactory::getMapper('folder')->sanityFileName($folder, $imageName);
				$fullpath = ROOT_DIR . $this->path . $imageName;
				$result = $this->moveUploadedFile($imageData['tmp_name'], $fullpath);
				if ($result) {
					iv_chmod($fullpath, 0777);
					$file = ivMapperFactory::getMapper('file')->find($fullpath);
					if ($file) {
						$file->generateThumbnail();
						if (!$file->title && $this->conf->get('/config/imagevue/settings/autoTitling')) {
							$file->title = ivFilepath::filename($imageData['name']);
						}
						$file->save();
						// REFACTORING How to avoid this legally?
						$folder->addFile($file);
						echo "文件 {$imageName} 上传成功";
					} else {
						header("HTTP/1.1 500 Internal Server Error");
						echo "错误，文件 {$imageName} 上传失败！";
					}
				} else {
					header("HTTP/1.1 500 Internal Server Error");
					echo "错误，文件 {$imageName} 上传失败！";
				}
			} else {
				header("HTTP/1.1 500 Internal Server Error");
				echo '错误，文件夹未找到！';
			}
		}
	}

	/**
	 * move_uploaded_file wrapper (for test purposes)
	 *
	 * @access protected
	 * @param  string    $filename
	 * @param  string    $destination
	 * @return boolean
	 */
	function moveUploadedFile($filename, $destination)
	{
		return @move_uploaded_file($filename, $destination);
	}

	function mceImageListAction()
	{
		$this->_setNoRender();

		header('Content-type: text/javascript');
		header('pragma: no-cache');
		header('expires: 0');

		echo "var tinyMCEImageList = [\n";

		$files = array();

		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path);
		if ($folder) {
			foreach ($folder->Files as $file) {
				if (is_a($file, 'ivFileImage')) {
					$files[] = "['" . $file->getTitle() . "', '" . $file->getPrimary() . "']";
				}
			}
		}

		echo implode(",\n", $files);
		echo "\n];";
	}

	function removePasswordAction()
	{
		$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->path);
		if ($folder) {
			$folder->removePassword();
		}
		$this->_redirect('index.php?path=' . urlencode($this->path));
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
					$path .= $key . '/';
					$folder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $path);
					if (!$folder) {
						continue;
					}
					$suffix='';
					if ($lastCrumbKey == $key) {
						$numOfFiles = $folder->fileCount;
						if ($totalNumOfFiles = $folder->totalFileCount) {
							$suffix =  '[' . (($numOfFiles == $totalNumOfFiles) ? $numOfFiles : $numOfFiles . '/' . $totalNumOfFiles . '') . ']';
						}
						$crumbs->push($folder->getTitle(), 'index.php?path=' . urlencode($path), $suffix, ($folder->isHidden() ? '隐藏' : ''));
					} else {
						$crumbs->push($folder->getTitle(), 'index.php?path=' . urlencode($path), '', ($folder->isHidden() ? '隐藏' : ''));
					}
				}
			}
		}
	}

}