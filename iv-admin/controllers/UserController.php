<?php

class UserController extends ivController
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
		ivPool::get('breadCrumbs')->push('用户', 'index.php?c=user');
		$login = $this->_getParam('login');
		$this->view->assign('user', ivAuth::getCurrentUserLogin());
		$this->view->assign('users', ivPool::get('userManager')->getUsers());
	}

	/**
	 * Add/edit user
	 *
	 */
	function editAction()
	{
		if (isset($_POST['save']) && is_array($_POST['user'])) {
			$newUser = $_POST['user'];
			if (($newUser['password'] && $newUser['password'] == $_POST['password_confirm']) || empty($newUser['password'])) {
				$result = ivPool::get('userManager')->saveUser($_POST['login'], $newUser);
				if ($result) {
					ivMessenger::add(ivMessenger::NOTICE, "用户数据保存成功");
				}
				$this->_redirect('index.php?c=user');
			} else {
				ivMessenger::add(ivMessenger::ERROR, "两次输入密码不相符");
			}
		}

		ivPool::get('breadCrumbs')->push('用户管理', 'index.php?c=user');
		$login = $this->_getParam('login');
		$user = ivPool::get('userManager')->getUser($login);
		if ($user) {
			$this->view->assign('user', $user);
			ivPool::get('breadCrumbs')->push($login, 'index.php?c=user&amp;a=edit&amp;login=' . $login);
		} elseif ($login) {
			ivMessenger::add(ivMessenger::ERROR, "用户 $login 未找到");
			$this->_redirect('index.php?c=user');
		} else {
			ivPool::get('breadCrumbs')->push('添加', 'index.php?c=user&amp;a=edit');
		}
		$contentFolder = ivMapperFactory::getMapper('folder')->find(ROOT_DIR . $this->conf->get('/config/imagevue/settings/contentfolder'));
		$iterator = new ivRecursiveFolderIterator($contentFolder);
		$this->view->assign('folderTreeIterator', new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST));
	}

	/**
	 * Default action
	 *
	 */
	function deleteAction()
	{
		$login = $this->_getParam('login');
		if (ivAcl::isAdmin($login)) {
			$adminCount = 0;
			foreach (ivPool::get('userManager')->getUsers() as $user) {
				$adminCount += (int) ivAcl::isAdmin($user->login);
			}
			if ($adminCount < 2) {
				ivMessenger::add(ivMessenger::ERROR, "用户 $login 是仅有的管理员用户，你不能删除它");
				$this->_redirect('index.php?c=user');
			}
		}
		$result = ivPool::get('userManager')->deleteUser($login);
		if ($result) {
			ivMessenger::add(ivMessenger::NOTICE, "用户 $login 删除成功");
		}
		$this->_redirect('index.php?c=user');
	}

}
?>