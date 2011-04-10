<?php

class CredController extends ivController
{
	/**
	 * Log in
	 *
	 */
	function loginAction()
	{
		$this->_disableLayout();
		$login = trim((string) $this->_getParam('login'));
		$password = trim((string) $this->_getParam('password'));
		if (!empty($login) && !empty($password)) {
			$rememberme = (boolean) $this->_getParam('rememberme');
			$result = ivAuth::authenticate($login, $password, $rememberme);
			if ($result) {
				ivMessenger::add(ivMessenger::NOTICE, "欢迎, $login");
			}
			$this->view->assign('wrongPassword', $result);
			$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?';
			if (ivAcl::isAdmin()) {
				$referer = $this->_getParam('redirect', $referer);
			}
			$this->_redirect($referer);
		}
		$userManager = ivPool::get('userManager');
		$admin = $userManager->getUser('admin');

		$defaultUser = ('d033e22ae348aeb5660fc2140aec35850c4da997' === $admin->passwordHash);
		$this->view->assign('defaultUser', $defaultUser);

		$guest = $userManager->getUser('guest');
		$this->view->assign('defaultGuest', ($guest && ('35675e68f4b5af7b995d9205ad0fc43842f16450' === $guest->passwordHash)));
	}

	/**
	 * Log out
	 *
	 */
	function logoutAction()
	{
		ivAuth::authenticate('', '', false);
		ivMessenger::add(ivMessenger::NOTICE, '再见！');
		$this->_redirect('?');
	}

}
?>