<?php

class ConfigController extends ivController
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
	 * Default action (edit main config)
	 *
	 */
	function indexAction()
	{
		$crumbs = ivPool::get('breadCrumbs');
		$crumbs->push('设置', 'index.php?c=config');
		$configFile = CONFIG_FILE;

		if (isset($_POST['save']) && isset($_POST['config'])) {
			$xml = ivXml::readFromFile($configFile, DEFAULT_CONFIG_FILE);
			foreach ($_POST['config'] as $path => $value) {
				$node = $xml->findByXPath($path);
				if ($node) {
					$node->setValue(is_array($value) ? implode(',', $value): (string) $value);
				}
// SMTP dirty hack goes here
				$smtp = array();
				if (isset($_POST['config']['/config/imagevue/settings/email/smtp/enabled'])) {
					$smtp['enabled'] = 'true' == $_POST['config']['/config/imagevue/settings/email/smtp/enabled'];
				}
				if (isset($_POST['config']['/config/imagevue/settings/email/smtp/host'])) {
					$smtp['host'] = $_POST['config']['/config/imagevue/settings/email/smtp/host'];
				}
				if (isset($_POST['config']['/config/imagevue/settings/email/smtp/port'])) {
					$smtp['port'] = $_POST['config']['/config/imagevue/settings/email/smtp/port'];
				}
				if (isset($_POST['config']['/config/imagevue/settings/email/smtp/auth'])) {
					$smtp['auth'] = 'true' == $_POST['config']['/config/imagevue/settings/email/smtp/auth'];
				}
				if (isset($_POST['config']['/config/imagevue/settings/email/smtp/username'])) {
					$smtp['username'] = $_POST['config']['/config/imagevue/settings/email/smtp/username'];
				}
				if (isset($_POST['config']['/config/imagevue/settings/email/smtp/password'])) {
					$smtp['password'] = $_POST['config']['/config/imagevue/settings/email/smtp/password'];
				}
				if (isset($_POST['config']['/config/imagevue/settings/email/smtp/secure'])) {
					$smtp['secure'] = ('none' == $_POST['config']['/config/imagevue/settings/email/smtp/secure'] ? '' : $_POST['config']['/config/imagevue/settings/email/smtp/secure']);
				}
				ivMail::saveSmtpConfig($smtp);
// --
			}
			// Check for valid content folder
			$path = ROOT_DIR . ivPath::canonizeRelative($xml->get('/config/imagevue/settings/contentfolder'));
			if (!file_exists($path) || !is_dir($path)) {
				ivMessenger::add(ivMessenger::ERROR, "错误的值应用于内容文件夹：文件夹 " . $xml->get('/config/imagevue/settings/contentfolder') . " 不存在");
			} else {
				$result = $xml->writeToFile();
				if ($result) {
					ivMessenger::add(ivMessenger::NOTICE, '配置文件保存成功');
				} else {
					ivMessenger::add(ivMessenger::ERROR, "无法保存配置文件 " . substr(CONFIG_FILE, strlen(ROOT_DIR)));
				}
				$this->_redirect($_SERVER['REQUEST_URI']);
			}
		}

		$xml = ivXml::readFromFile($configFile, DEFAULT_CONFIG_FILE);

// SMTP dirty hack goes here
		$smtpConfig = ivMail::getSmtpConfig();

		$emailNode = $xml->findByXPath('/config/imagevue/settings/email');
		if ($emailNode) {
			$smtpNode = new ivXmlNode('smtp');

			$emailNode->addChild($smtpNode);

			$enabledNode = new ivXmlNodeBoolean('enabled', array('description' => $smtpConfig['enabled']['description']));
			$enabledNode->setValue($smtpConfig['enabled']['value']);
			$smtpNode->addChild($enabledNode);

			$hostNode = new ivXmlNodeString('host', array('description' => $smtpConfig['host']['description']));
			$hostNode->setValue($smtpConfig['host']['value']);
			$smtpNode->addChild($hostNode);

			$portNode = new ivXmlNodeInteger('port', array('description' => $smtpConfig['port']['description']));
			$portNode->setValue($smtpConfig['port']['value']);
			$smtpNode->addChild($portNode);

			$authNode = new ivXmlNodeBoolean('auth', array('description' => $smtpConfig['auth']['description']));
			$authNode->setValue($smtpConfig['auth']['value']);
			$smtpNode->addChild($authNode);

			$usernameNode = new ivXmlNodeString('username', array('description' => $smtpConfig['username']['description']));
			$usernameNode->setValue($smtpConfig['username']['value']);
			$smtpNode->addChild($usernameNode);

			$passwordNode = new ivXmlNodePassword('password', array('description' => $smtpConfig['password']['description']));
			$passwordNode->setValue($smtpConfig['password']['value']);
			$smtpNode->addChild($passwordNode);

			$secureNode = new ivXmlNodeEnum('secure', array('description' => $smtpConfig['secure']['description'], 'options' => 'none,tls,ssl'));
			$secureNode->setValue($smtpConfig['secure']['value']);
			$smtpNode->addChild($secureNode);
		}
// --

		if (!$xml->get('/config/imagevue/settings/authorized')) {
			$xml->remove('/config/imagevue/settings/authorized');
		}

		$sections = array();
		$rootNode = $xml->findByXPath('/config/imagevue');
		if ($rootNode) {
			foreach ($rootNode->getChildren() as $k => $child) {
				$sections[$child->getName()] = $child->toFlatTree();
			}
		}

		$this->view->assign('sections', $sections);

		$openedPanels = array();
		if (isset($_COOKIE['ivconf'])) {
			$openedPanels = array_unique(array_explode_trim(',', $_COOKIE['ivconf']));
		}
		$this->view->assign('openedPanels', $openedPanels);
	}

}
?>