<?php

/**
 * @file plugins/generic/fidusWriter/SettingsForm.inc.php
 *
 * Based on code of:
 * Copyright (c) 2013 Simon Fraser University Library
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see
 * https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html .
 *
 * @class SettingsForm
 * @ingroup plugins_generic_fidusWriter
 *
 * @brief Form for journal managers to modify FidusWriter plugin settings
 */


import('lib.pkp.classes.form.Form');

class FidusWriterSettingsForm extends Form {

	/** @var $plugin object */
	private $_plugin;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $journalId int
	 */
	function __construct($plugin) {
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplatePath() . 'settingsForm.tpl');
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$plugin = $this->_plugin;
		$this->setData('apiKey', $plugin->getSetting(CONTEXT_ID_NONE, 'apiKey'));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('apiKey',));
		$this->addCheck(new FormValidator($this, 'apiKey', 'required', 'plugins.generic.fidusWriter.manager.settings.apiKeyRequired'));
	}

	/**
	 * Save settings.
	 */
	function execute($object = NULL) {
		$plugin = $this->_plugin;
		$plugin->updateSetting(CONTEXT_ID_NONE, 'apiKey', trim($this->getData('apiKey'),"\"\';"), 'string');
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = NULL, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

}

?>
