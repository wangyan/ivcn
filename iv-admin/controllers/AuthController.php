<?php

class AuthController extends ivController
{

	/**
	 * Pre-dispatching
	 *
	 */
	function _preDispatch()
	{
		$crumbs = ivPool::get('breadCrumbs');
		$crumbs->push('授权', '?c=auth');

		if (!ivAcl::isAdmin()) {
			$this->_forward('login', 'cred');
			if (ivAuth::getCurrentUserLogin()) {
				ivMessenger::add(ivMessenger::ERROR, "You don't have access to this page");
			}
			return;
		}
	}

	/**
	 * Authorize
	 *
	 */
	function indexAction()
	{}

	/**
	 * Done
	 *
	 */
	function doneAction()
	{
		$this->_disableLayout();
		$this->_setNoRender();

		$this->conf->findByXPath('/config/imagevue/settings/authorized')->setValue('true');
		$this->conf->writeToFile();
	}

}
?>