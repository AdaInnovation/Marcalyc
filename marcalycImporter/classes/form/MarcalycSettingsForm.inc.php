<?php

import('lib.pkp.classes.form.Form');

class MarcalycSettingsForm extends Form
{

	//
	// Private properties
	//
	/** @var integer */
	var $_contextId;

	/**
	 * Get the context ID.
	 * @return integer
	 */
	function _getContextId()
	{
		return $this->_contextId;
	}

	/** @var MarcalycImportPlugin */
	var $_plugin;

	/**
	 * Get the plugin.
	 * @return MarcalycImportPlugin
	 */
	function _getPlugin()
	{
		return $this->_plugin;
	}


	//
	// Constructor
	//
	/**
	 * Constructor
	 * @param $plugin MarcalycImportPlugin
	 * @param $contextId integer
	 */
	function __construct($plugin, $contextId)
	{
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		// Add form validation checks.
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::initData()
	 */
	function initData()
	{
		$contextId = $this->_getContextId();
		$plugin = $this->_getPlugin();
		foreach ($this->getFormFields() as $fieldName => $fieldType) {
			$this->setData($fieldName, $plugin->getSetting($contextId, $fieldName));
		}
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData()
	{
		$this->readUserVars(array_keys($this->getFormFields()));
	}

	/**
	 * Execute the form.
	 */
	function execute()
	{
		$plugin = $this->_getPlugin();
		$contextId = $this->_getContextId();
		$opcionActualizar = "";
		$url_new="";
		
		foreach ($this->getFormFields() as $fieldName => $fieldType) {
			$plugin->updateSetting($contextId, $fieldName, $this->getData($fieldName), $fieldType);
			
			if($fieldName == "url_visor"){
				$url_new = $this->getData($fieldName);
			}

			if ($fieldName == "updateOld") {
				$opcionActualizar = $this->getData($fieldName);
				if ($opcionActualizar == "on") {
					$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
					$param1 = "Visor";
					$result = $articleGalleyDao->retrieve(
						'SELECT i.* FROM submission_galleys i where label = ?',
						array($param1)
					);
					$data_update = new DAOResultFactory($result, $articleGalleyDao, '_fromRow');
					
					while ($row  = $data_update->next()) {
						$url_edit = $row->getRemoteURL();
						$array_url_old = explode('/', $url_edit);
						$array_url_old_size=count($array_url_old);

						$url_complemento="";
						for ($i = $array_url_old_size-3; $i < $array_url_old_size; $i++) {
							$url_complemento.=$array_url_old[$i];
							if($array_url_old[$i]!="index.html"){
								$url_complemento.="/";
							}
						}

						$url_new.=$url_complemento;

						$row->setRemoteURL($url_new);

						$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
						$galleyDao->updateObject($row);
						
					}
					$result->Close();
					
					$plugin->updateSetting($contextId, $fieldName, null, $fieldType);
					
				}
			}
			
		}
		
	}


	//
	// Public helper methods
	//
	/**
	 * Get form fields
	 * @return array (field name => field type)
	 */
	function getFormFields()
	{
		return array(
			'url_visor' => 'string',
			'updateOld' => 'bool',
			'validarURL' => 'bool'
		);
	}

	/**
	 * Is the form field optional
	 * @param $settingName string
	 * @return boolean
	 */
	function isOptional($settingName)
	{
		return in_array($settingName, array('url_visor', 'updateOld','validarURL'));
	}
}
