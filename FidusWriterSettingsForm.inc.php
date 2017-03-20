<?php

/**
 * @file plugins/generic/fidusWriter/FidusWriterSettingsForm.inc.php
 *
 * Copyright (c) 2013 Simon Fraser University Library
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FidusWriterSettingsForm
 * @ingroup plugins_generic_fidusWriter
 *
 * @brief Form for journal managers to modify FidusWriter plugin settings
 */


import('lib.pkp.classes.form.Form');

class FidusWriterSettingsForm extends Form {

	/** @var $journalId int */
	var $journalId;

	/** @var $plugin object */
	var $plugin;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $journalId int
	 */
	function FidusWriterSettingsForm(&$plugin, $journalId) {
		$this->journalId = $journalId;
		$this->plugin =& $plugin;

		parent::__construct($plugin->getTemplatePath() . 'settingsForm.tpl');

		$this->addCheck(new FormValidatorURL($this, 'apiUrl', 'required', 'plugins.generic.fidusWriter.manager.settings.apiUrlRequired'));
		$this->addCheck(new FormValidator($this, 'apiKey', 'required', 'plugins.generic.fidusWriter.manager.settings.apiKeyRequired'));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$journalId = $this->journalId;
		$plugin =& $this->plugin;

		$this->_data = array(
			'apiUrl' => $plugin->getSetting($journalId, 'apiUrl'),
			'apiKey' => $plugin->getSetting($journalId, 'apiKey'),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('apiUrl', 'apiKey'));
	}

	/**
	 * Save settings.
	 */
	function execute() {
		$plugin =& $this->plugin;
		$journalId = $this->journalId;

		$plugin->updateSetting($journalId, 'apiUrl',  trim($this->getData('apiUrl'),"\"\';"), 'string');
		$plugin->updateSetting($journalId, 'apiKey', trim($this->getData('apiKey'),"\"\';"), 'string');
	}
	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * Validate form data.
	 */
	function validate() {
		if (!parent::validate()) return false;

	//	$plugin =& $this->plugin;
	//	$fidusWriterConnection = $plugin->getConnection($this->getData('apiUrl'), $this->getData('apiKey'));
	//	if (!$fidusWriterConnection->verifyKey()) {
	//		$this->addError('apiKey', __('plugins.generic.fidusWriter.manager.settings.apiKeyNotValidated'));
	//		return false;
	//	}

		return true;
	}
}

?>
