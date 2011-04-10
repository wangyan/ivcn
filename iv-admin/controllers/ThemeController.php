<?php

class ThemeController extends ivController
{

	/**
	 * Pre-dispatching
	 *
	 */
	function _preDispatch()
	{
		if (!ivAcl::isAdmin()) {
			$this->_forward('login', 'cred');
			if (ivAuth::getCurrentUserLogin()) {
				ivMessenger::add(ivMessenger::ERROR, "你没有权限访问该页面");
			}
			return;
		}
	}

	/**
	 * Default action
	 *
	 */
	function indexAction()
	{
		$crumbs = ivPool::get('breadCrumbs');
		$crumbs->push('主题', 'index.php?c=theme');

		$selectedTheme = $this->conf->get('/config/imagevue/settings/theme');
		$defaultThemes = ivTheme::getDefaultThemes();
		$themes = array_diff(ivTheme::getAllThemes(), array($selectedTheme), $defaultThemes);
		sort($themes);
		array_unshift($themes, $selectedTheme);
		foreach (array_diff($defaultThemes, array($selectedTheme)) as $name) {
			$themes[] = $name;
		}

		$this->view->assign('theme', $selectedTheme);
		$this->view->assign('themes', $themes);
		$this->view->assign('defaultThemes', $defaultThemes);
	}

	/**
	 * Edit theme cascade stylesheet
	 *
	 */
	function editcssAction()
	{
		$themeName = $this->_getParam('name', 'default', 'alnum');
		if (in_array($themeName, ivTheme::getDefaultThemes())) {
			$this->_redirect('?c=theme');
		}

		$theme = ivTheme::get($themeName);
		if (!$theme) {
			ivMessenger::add(ivMessenger::ERROR, "主题 '$themeName' 未找到");
			$this->_redirect('?c=theme');
		}

		if (isset($_POST['css'])) {
			$css = (string) $_POST['css'];
			if ($theme->setStyle($css)) {
				ivMessenger::add(ivMessenger::NOTICE, 'CSS 文件保存成功');
				$this->_redirect($_SERVER['REQUEST_URI']);
			}
		}

		$crumbs = ivPool::get('breadCrumbs');
		$crumbs->push('主题', 'index.php?c=theme');
		$crumbs->push(ucfirst($themeName), 'index.php?c=theme&amp;a=editconfig&amp;name=' . $themeName);
		$crumbs->push('样式表', 'index.php?c=theme&amp;a=editcss&amp;name=' . $themeName);

		$this->view->assign('theme', $theme);
		$this->view->assign('themes', ivTheme::getAllThemes());
	}

	/**
	 * Edit theme configuration
	 *
	 */
	function editconfigAction()
	{
		$crumbs = ivPool::get('breadCrumbs');
		$crumbs->push('主题', 'index.php?c=theme');

		$themeName = $this->_getParam('name', 'default', 'alnum');
		if (in_array($themeName, ivTheme::getDefaultThemes())) {
			$this->_redirect('?c=theme');
		}

		$crumbs->push(ucfirst($themeName), 'index.php?c=theme&amp;a=editconfig&amp;name=' . $themeName);

		$theme = ivTheme::get($themeName);
		if (!$theme) {
			ivMessenger::add(ivMessenger::ERROR, "主题 '$themeName' 未找到");
			$this->_redirect('?c=theme');
		}

		if (isset($_POST['save']) && isset($_POST['config'])) {
			$xml = $theme->getConfig();
			foreach ($_POST['config'] as $path => $value) {
				$node = $xml->findByXPath($path);
				if ($node) {
					$node->setValue(is_array($value) ? implode(',', $value): (string) $value);
				}
			}
			$result = $xml->writeToFile();
			if ($result) {
				ivMessenger::add(ivMessenger::NOTICE, '主题配置文件保存成功');
			}
		}

		$xml = $theme->getConfig();

		$bgImageUrlNode = $xml->findByXPath('/config/imagevue/style/background_image/url');
		$newBgImageUrlNode = ivXmlNode::create('url', array('options' => implode(',', $theme->getBackgroundsList()), 'type' => 'enum'));
		$newBgImageUrlNode->setValue($bgImageUrlNode->getValue());
		$xml->replace($bgImageUrlNode, $newBgImageUrlNode);

		$sections = array();
		$rootNode = $xml->findByXPath('/config/imagevue');
		if ($rootNode) {
			foreach ($rootNode->getChildren() as $k => $child) {
				$sections[$child->getName()] = $child->toFlatTree();
			}
		}

		$this->view->assign('sections', $sections);

		$this->view->assign('themes', ivTheme::getAllThemes());
		$this->view->assign('themeName', $themeName);

		$openedPanels = array();
		if (isset($_COOKIE['ivconf'])) {
			$openedPanels = array_unique(array_explode_trim(',', $_COOKIE['ivconf']));
		}
		$this->view->assign('openedPanels', $openedPanels);
	}

	/**
	 * Set default theme
	 *
	 */
	function useAction()
	{
		$value = $this->_getParam('name', 'default', 'alnum');
		if (!is_null($value)) {
			$xml = ivXml::readFromFile(CONFIG_FILE, DEFAULT_CONFIG_FILE);
			$node = $xml->findByXPath('/config/imagevue/settings/theme');
			if ($node) {
				$node->setValue((string) $value);
			}
			$result = $xml->writeToFile();
			if ($result) {
				ivMessenger::add(ivMessenger::NOTICE, '配置保存成功');
			} else {
				ivMessenger::add(ivMessenger::ERROR, "配置保存失败 " . substr(CONFIG_FILE, strlen(ROOT_DIR)));
			}
		}
		$this->_redirect('index.php?c=theme');
	}

	/**
	 * Copy theme
	 *
	 */
	function copyAction()
	{
		$themeName = $this->_getParam('name', 'default', 'alnum');
		$newThemeName = $this->_getParam('new');
		if (!ctype_alnum($newThemeName)) {
			ivMessenger::add(ivMessenger::ERROR, '仅支持使用字母作为主题名称');
			$this->_redirect('index.php?c=theme');
		}
		if (!$themeName || !$newThemeName) {
			$this->_redirect('index.php?c=theme&a=editconfig&name=' . $themeName);
		}
		if (in_array($newThemeName, ivTheme::getAllThemes())) {
			ivMessenger::add(ivMessenger::ERROR, '主题名称 ' . $newThemeName . ' 已经存在');
			$this->_redirect('index.php?c=theme&a=editconfig&name=' . $themeName);
		}

		$theme = ivTheme::get($themeName);
		if (!$theme) {
			ivMessenger::add(ivMessenger::ERROR, "主题 '$themeName' 未找到");
			$this->_redirect('?c=theme');
		}

		if ($theme->copyTo($newThemeName)) {
			ivMessenger::add(ivMessenger::NOTICE, "主题 $newThemeName 创建成功");
		} else {
			ivMessenger::add(ivMessenger::ERROR, "主题 $newThemeName 创建失败");
		}
		$this->_redirect('index.php?c=theme');
	}

	/**
	 * Delete theme
	 *
	 */
	function deleteAction()
	{
		$themeName = $this->_getParam('name', null, 'alnum');
		if (in_array($themeName, ivTheme::getDefaultThemes())) {
			$this->_redirect('?c=theme');
		}

		$theme = ivTheme::get($themeName);
		if (!$theme) {
			ivMessenger::add(ivMessenger::ERROR, "主题 '$themeName' 未找到");
			$this->_redirect('?c=theme');
		}

		if ($theme->delete()) {
			ivMessenger::add(ivMessenger::NOTICE, "主题 $themeName 删除成功");
		} else {
			ivMessenger::add(ivMessenger::ERROR, "主题 $themeName 删除失败");
		}
		$this->_redirect('index.php?c=theme');
	}

	/**
	 * Upload background file
	 *
	 */
	function uploadAction()
	{
		$themeName = $this->_getParam('name', null, 'alnum');
		if (in_array($themeName, ivTheme::getDefaultThemes())) {
			$this->_redirect($_SERVER['HTTP_REFERER']);
		}

		if (!$themeName) {
			$this->_redirect($_SERVER['HTTP_REFERER']);
		}
		$this->_setNoRender();
		if (!isset($_FILES['Filedata'])) {
			ivMessenger::add(ivMessenger::ERROR, '文件未找到');
			$this->_redirect($_SERVER['HTTP_REFERER']);
		}
		$imageData = $_FILES['Filedata'];
		if (!@getimagesize($imageData['tmp_name'])) {
			ivMessenger::add(ivMessenger::ERROR, '文件不兼容');
			$this->_redirect($_SERVER['HTTP_REFERER']);
		}

		$theme = ivTheme::get($themeName);
		if (!$theme) {
			ivMessenger::add(ivMessenger::ERROR, "主题 '$themeName' 未找到");
			$this->_redirect('?c=theme');
		}

		$fullpath = $theme->getUploadDirectory() . $imageData['name'];
		$result = @move_uploaded_file($imageData['tmp_name'], $fullpath);
		if ($result) {
			iv_chmod($fullpath, 0777);
			ivMessenger::add(ivMessenger::NOTICE, "文件 {$imageData['name']} 上传成功");
		} else {
			ivMessenger::add(ivMessenger::NOTICE, "文件 {$imageData['name']} 上传失败");
		}
		$this->_redirect($_SERVER['HTTP_REFERER']);
	}

}
?>