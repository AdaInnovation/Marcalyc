<?php

/**
 * @file plugins/importexport/marcalycImporter/MarcalycImportPlugin.inc.php
 *
 * Copyright (c) 2019 
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MarcalycImportPlugin
 * @ingroup plugins_importexport_marcalycImporter
 *
 */

//use PHPMailer\PHPMailer\Exception;

import('classes.plugins.PubObjectsExportPlugin');

class MarcalycImportPlugin extends PubObjectsExportPlugin
{
	var $context;
	var $submission;
	var $request;


	var $commonLangs = array(
		'ca' =>	'ca_ES',
		'es' => 'es_ES',
		'en' => 'en_US',
		'fr' => 'fr_FR',
		'pl' => 'pl_PL',
		'pt' => 'pt_PT',
		'tr' => 'tr_TT'
	);


	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null)
	{
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_APP_AUTHOR,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_SUBMISSION
		);

		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		$this->import('ModelIssue');
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName()
	{
		return 'MarcalycImportPlugin';
	}

	/**
	 * Get the display name.
	 * @return string
	 */
	function getDisplayName()
	{
		return __('plugins.importexport.marcalycImporter.displayName');
	}

	/**
	 * Get the display description.
	 * @return string
	 */
	function getDescription()
	{
		return __('plugins.importexport.marcalycImporter.description');
	}

	/**
	 * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
	 */
	function getPluginSettingsPrefix()
	{
		return 'marcalycImporter';
	}

	function getExportDeploymentClassName()
	{
		return '';
	}

	function getSettingsFormClassName()
	{
		return 'MarcalycSettingsForm';
	}

	function depositXML($objects, $context, $jsonString)
	{ }

	/**
	 * Display the plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request)
	{
		parent::display($args, $request);
		$templateMgr = TemplateManager::getManager($request);
		$this->context = $request->getContext();
		$this->request = $request;


		switch (array_shift($args)) {
			case 'index':
			case '':
				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;

			case 'importCompressedFile':
				$json = new JSONMessage(true);
				$json->setContent("Hola CArgando");
				$json->setEvent('addTab', array(
					'title' => __('plugins.importexport.marcalycImporter.results'),
					'url' => $request->url(null, null, null, array('plugin', $this->getName(), 'import'), array('temporaryFileId' => $request->getUserVar('temporaryFileId'))),
				));
				header('Content-Type: application/json');
				return $json->getString();
				break;

			case 'uploadCompressedFile':
				$user = $request->getUser();
				import('lib.pkp.classes.file.TemporaryFileManager');
				$temporaryFileManager = new TemporaryFileManager();
				$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
				if ($temporaryFile) {
					$json = new JSONMessage(true);
					$json->setAdditionalAttributes(array(
						'temporaryFileId' => $temporaryFile->getId()
					));
				} else {
					$json = new JSONMessage(false, __('common.uploadFailed'));
				}
				header('Content-Type: application/json');
				return $json->getString();
				break;

			case 'import':
				$statusMsg = "";
				$Errores = "";
				AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
				$temporaryFileId = $request->getUserVar('temporaryFileId');
				$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
				$user = $request->getUser();
				$temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $user->getId());

				if (!$temporaryFile) {
					$json = new JSONMessage(true, __('plugins.inportexport.native.uploadFile'));
					header('Content-Type: application/json');
					return $json->getString();
				}

				$temporaryFilePath = $temporaryFile->getFilePath();
				$templateMgr->assign('temporaryFilePath', $temporaryFilePath);
				$errorMsg = null;
				$processingFilePath = $this->decompressZipFile($temporaryFilePath, $errorMsg, false);

				if ($processingFilePath == false) {
					$Errores .= __('plugins.importexport.marcalycImporter.errorDecompress');
					$templateMgr->assign('Errores', $Errores);
					$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
					header('Content-Type: application/json');
					return $json->getString();
				}

				$templateMgr->assign('temporaryFolder', '->' . $processingFilePath);
				$dirs = scandir($processingFilePath);
				$dirs = $this->cleanFileList($dirs);
				$this->decompressZipFile($processingFilePath, $errorMsg, true);


				$articleData = $this->getDataByPath($processingFilePath, $request);


				if (empty($articleData)) {
					$Errores .= __('plugins.importexport.marcalycImporter.noContent');
					$templateMgr->assign('Errores', $Errores);
					$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
					header('Content-Type: application/json');
					return $json->getString();
				}

				if (!is_array($articleData)) {
					$templateMgr->assign('Errores', $articleData);
					$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
					header('Content-Type: application/json');
					return $json->getString();
				}


				$numeroMsg = "";
				$articulosMsg = "";

				//solo insertar datos
				$id_issue = "";
				$artIdx = 0;
				foreach ($articleData as $data) {
					if ($artIdx == 0) {
						$id_issue = $this->add_Number($data->getNumero());
						$artIdx++;
					}

					$id_article = $this->add_Article($data, $id_issue);
					$this->add_AuthorsArticle($data->Autores);
					$this->insert_url($data, $request->getContext()->getId());
					$this->insert_files_pdf($processingFilePath, $data->article_id_redalyc, $request->getContext()->getId());
					$this->insert_files_epub($processingFilePath, $data->article_id_redalyc, $request->getContext()->getId());
					$this->insert_files_html($processingFilePath, $data->article_id_redalyc, $request->getContext()->getId());
				}


				//imprimir resusltados
				$imprimirTitulo = true;
				$resultados = "";
				foreach ($articleData as $data) {
					if ($imprimirTitulo) {
						$resultados .= "<h3 style='text-align: center;'> = Resultados =</h3>";
						$resultados .= "<h4>Vol. " . $data->getNumero()->getVolume() . " Num " . $data->getNumero()->getNumber() . "</h4>";
						$imprimirTitulo = false;
					}
					$titulo = "";
					foreach ($data->title as $k => $tl) {
						$titulo = $tl;
						break;
					}
					$resultados .= "<div style='font-size:15px; color:gray'>[" . $data->categoria_articulo . "]</div>";
					$resultados .= "<div class='list-group-item' style='color:black'> " . $titulo . "</div>";
				}

				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$fileManager->deleteByPath($temporaryFilePath);
				$fileManager->rmtree($temporaryFilePath . "_1");

				$protocolo = $request->_protocol;
				$servidor = $request->_serverHost;
				$aplicacion = $request->_basePath;
				$nombre_revista = $request->getRequestedJournalPath();

				// url para el boton de ver numeros
				$ver_numeros_url = $protocolo . "://" . $servidor . $aplicacion . "/index.php/" . $nombre_revista . "/manageIssues#futureIssues";

				$templateMgr->assign('numeros_url', $ver_numeros_url);
				$templateMgr->assign('Resultados', $resultados);
				$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
				header('Content-Type: application/json');
				return $json->getString();
				break;

			default:
				$dispatcher = $request->getDispatcher();
				$dispatcher->handle404();
		}
	}

	/**
	 * Metodo para validacion de existencia del numero y la seccion
	 * 
	 * @param Articulo $articulo datos del xml
	 * @param bool $validarNum Opcion para evitar validar todos los numero si son iguales
	 * @return Articulo $articulo El mismo objeto con los datos validados
	 */
	function validarArticulo($articulo, $validarNum)
	{
		//validar numero
		if ($validarNum) {
			$numero = $articulo->getNumero()->getNumber();
			$volume = $articulo->getNumero()->getVolume();
			$anio = $articulo->getNumero()->getYear();
			$existe_numero = $this->validarNumero($volume, $numero, $anio);
			if ($existe_numero) {
				$articulo->getNumero()->setStatus(202);
			} else {
				$articulo->getNumero()->setStatus(404);
			}
		} else {
			$articulo->getNumero()->setStatus(404);
		}

		//validar seccion

		if ($articulo->categoria_articulo == "Sin sección" || $articulo->categoria_articulo == "") {
			$existe_seccion = $this->validarSeccion();
		} else {
			$existe_seccion = $this->validarSeccion($articulo->categoria_articulo);
		}

		if ($existe_seccion == null) {
			$existe_seccion = $this->add_section($articulo->categoria_articulo);
		}

		$articulo->categoria_id = $existe_seccion;

		return $articulo;
	}

	/**
	 * Permite la insercion del url remoto del galley del articulo
	 * 
	 * @param string $articleData datos del xml
	 * @param int @context_id id de la revista
	 * 
	 * @return article_id id con el cual el xml identifica al articulo
	 */
	private function insert_url($articleData, $context_id)
	{
		//Get Journal-id and article-id
		$journal_id = $articleData->journal_id_redalyc;
		$article_id = $articleData->article_id_redalyc;
		//url del visor de redalyc
		//$link_visor = "http://www.redalyc.org/jatsRepo/" . $journal_id . "/" . $article_id . "/index.html";

		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		$plugin_settings_exist = $pluginSettingsDao->settingExists($context_id, "marcalycimportplugin", "url_visor");
		$link_temp = "http://www.redalyc.org/jatsRepo/";
		$validar_url = false;
		if (!$plugin_settings_exist) {
			$pluginSettingsDao->updateSetting($context_id, "marcalycimportplugin", "url_visor", $link_temp, "string");
			$pluginSettingsDao->updateSetting($context_id, "marcalycimportplugin", "updateOld", 0, "bool");
			$pluginSettingsDao->updateSetting($context_id, "marcalycimportplugin", "validarURL", 1, "bool");
		} else {
			$plugin_settings = $pluginSettingsDao->getPluginSettings($context_id, "marcalycimportplugin");
			$link_temp = $plugin_settings["url_visor"];
			$validar_url = $plugin_settings["validarURL"];
		}

		$link_visor = $link_temp . $journal_id . "/" . $article_id . "/index.html";

		$existe_url = true;
		if ($validar_url) {
			$existe_url = $this->validarURL($link_visor);
		}

		if ($existe_url) {
			$this->add_galley(null, "URL", null, $context_id, $this->submissionId, $link_visor);
		}
	}

	/**
	 * Permite la insercion de archivos PDF al galley del articulo
	 * 
	 * @param string $path ruta donde se encuentran los archivos del articulo
	 * @param string $idArticleRedalyc id que se encuentra en el xml
	 * @param int $context_id id de la revista
	 */
	private function insert_files_pdf($path, $idArticleRedalyc, $context_id)
	{
		$todas_las_concidencias = $this->findFilesByName($path, $idArticleRedalyc, "pdf");
		$archivos_upload = $this->quitar_archivos_basura($todas_las_concidencias);
		foreach ($archivos_upload as $file) {
			$this->add_galley($file, "PDF", null, $context_id, $this->submissionId, "");
		}
	}

	/**
	 * Permite la insercion de archivos EPUB al galley del articulo
	 * 
	 * @param string $path ruta donde se encuentran los archivos del articulo
	 * @param string $idArticleRedalyc id que se encuentra en el xml
	 * @param int $context_id id de la revista
	 */
	private function insert_files_epub($path, $idArticleRedalyc, $context_id)
	{
		$todas_las_concidencias = $this->findFilesByName($path, $idArticleRedalyc, "epub");
		$archivos_upload = $this->quitar_archivos_basura($todas_las_concidencias);
		foreach ($archivos_upload as $file) {
			$this->add_galley($file, "EPUB", null, $context_id, $this->submissionId, "");
		}
	}

	/**
	 * Permite la insercion de archivos HTML al galley del articulo,
	 * asi como sus archivos complementos
	 * 
	 * @param string $path ruta donde se encuentran los archivos del articulo
	 * @param string $idArticleRedalyc id que se encuentra en el xml
	 * @param int $context_id id de la revista
	 */
	private function insert_files_html($path, $idArticleRedalyc, $context_id)
	{
		$todas_las_concidencias = $this->findFilesByName($path, $idArticleRedalyc, "png");
		if (empty($todas_las_concidencias)) {
			//buscar en los archivos txt
			$todas_las_concidencias = $this->findFilesByName($path, $idArticleRedalyc, "txt");

			if (empty($todas_las_concidencias)) {
				return null;
			}
		}



		$coincidencias = $this->quitar_archivos_basura($todas_las_concidencias);
		$path_html_directory = $this->find_path_html($coincidencias);
		$archivo_index = $this->obtener_archivos($path_html_directory, true, "html");


		$file_index = $path_html_directory . DIRECTORY_SEPARATOR . $archivo_index;
		$assoc_id = $this->add_galley($file_index, "HTML", null, $context_id, $this->submissionId, "");

		$archivos_png = $this->obtener_archivos($path_html_directory, false, "png");
		$archivos_jpg = $this->obtener_archivos($path_html_directory, false, "jpg");
		$archivos_gif = $this->obtener_archivos($path_html_directory, false, "gif");
		$archivos_css = $this->obtener_archivos($path_html_directory, false, "css");

		//Cargar los archivos complementarios PNG
		foreach ($archivos_png as $archivo) {
			$file_png = $path_html_directory . DIRECTORY_SEPARATOR . $archivo;
			$this->add_galley($file_png, "png", $assoc_id->getFileId(), $context_id, $this->submissionId, "");
		}

		//Cargar los archivos complementarios GIF
		foreach ($archivos_gif as $archivo) {
			$file_png = $path_html_directory . DIRECTORY_SEPARATOR . $archivo;
			$this->add_galley($file_png, "gif", $assoc_id->getFileId(), $context_id, $this->submissionId, "");
		}

		//Cargar los archivos complementarios jpg
		foreach ($archivos_jpg as $archivo) {
			$file_png = $path_html_directory . DIRECTORY_SEPARATOR . $archivo;
			$this->add_galley($file_png, "jpg", $assoc_id->getFileId(), $context_id, $this->submissionId, "");
		}

		//Cargar los archivos complementarios CSS
		foreach ($archivos_css as $archivo) {
			$file_css = $path_html_directory . DIRECTORY_SEPARATOR . $archivo;
			$this->add_galley($file_css, "css", $assoc_id->getFileId(), $context_id, $this->submissionId, "");
		}
	}

	/**
	 * Este metodo permite la insercion del numero a la base de datos
	 * 
	 * @param Numero $datos_autores Objeto con los datos del numero a insertar
	 * @return int $issueDao Id del Issue
	 */
	private function add_Number($datos_numero)
	{
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->newDataObject();
		$issue->setAccessStatus(ISSUE_ACCESS_OPEN);
		$issue->setTitle($datos_numero->getTitle(), null); // Localized
		$issue->setJournalId($datos_numero->getJournalId());
		$issue->setVolume($datos_numero->getVolume());
		$issue->setNumber($datos_numero->getNumber());
		$issue->setYear($datos_numero->getYear());
		$issue->setPublished(0);
		$issue->setCurrent(0);

		return $issueDao->insertObject($issue);
	}

	/**
	 * Este metodo permite la insercion de los articulos a la base de datos
	 * 
	 * @param obj $datos_articulo Objeto que contiene todos los datos del articulo
	 * @param int $issueId Id del numero al cual pertenece el articulo
	 * @return int $submissionId Regresa el id de submission
	 */
	private function add_Article($datos_articulo, $issueId)
	{

		$date = Core::getCurrentDate();
		/*
		$locale = AppLocale::getLocale();
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		*/
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->newDataObject();
		$submission->setContextId($this->context->getId());
		$submission->setStatus(STATUS_PUBLISHED);
		$submission->setSubmissionProgress(0);
		$submission->stampStatusModified();
		$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
		$submission->setSectionId($datos_articulo->categoria_id);
		$submission->setLocale(AppLocale::getLocale());
		$submission->setDateSubmitted($date);
		$lang = $datos_articulo->language;
		$submission->setLanguage($lang);

		foreach ($datos_articulo->title as $k => $tl) {
			$submission->setTitle($tl, $k);
		}

		if (isset($datos_articulo->abstract)) {
			foreach ($datos_articulo->abstract as $k => $al) {
				$submission->setAbstract($al, $k);
			}
		}


		$this->submissionId = $submissionDao->insertObject($submission);
		$user = $this->request->getUser();
		$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupId = null;
		$managerUserGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $this->context->getId(), ROLE_ID_MANAGER);
		if ($managerUserGroupAssignments) {
			while ($managerUserGroupAssignment = $managerUserGroupAssignments->next()) {
				$managerUserGroup = $userGroupDao->getById($managerUserGroupAssignment->getUserGroupId());
				$userGroupId = $managerUserGroup->getId();
				break;
			}
		}
		// Assign the user author to the stage
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignmentDao->build($this->submissionId, $userGroupId, $user->getId());

		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$publishedArticle = $publishedArticleDao->newDataObject();
		$publishedArticle->setId($this->submissionId);
		$publishedArticle->setIssueId($issueId);
		$publishedArticle->setDatePublished($date);
		$publishedArticle->setSequence(REALLY_BIG_NUMBER);
		$publishedArticle->setAccessStatus(0);
		$publishedArticleDao->insertObject($publishedArticle);

		return $this->submissionId;
	}

	/**
	 * Este metodo permite la insercion de los autores a la base de datos
	 * 
	 * @param array $datos_autores Datos de los autores
	 *   
	 */
	private function add_AuthorsArticle($datos_autores)
	{
		$authorDao = DAORegistry::getDAO('AuthorDAO');

		foreach ($datos_autores as $k => $autor) {

			$givenName[AppLocale::getLocale()] = $autor->nombre;
			$surName[AppLocale::getLocale()] = $autor->apellidos;
			$author = $authorDao->newDataObject();
			$author->setGivenName($givenName, null);
			$author->setFamilyName($surName, null);
			$affilliation[AppLocale::getLocale()] = $autor->institucion;
			$author->setAffiliation($affilliation, null);
			$author->setCountry($autor->pais);
			$author->setEmail($autor->email);
			$publicName[AppLocale::getLocale()] = "";
			$author->setPreferredPublicName($publicName, null);
			$biography[AppLocale::getLocale()] = "";
			$author->setBiography($biography, null);
			$author->setIncludeInBrowse(1);
			$author->setOrcid("");
			$author->setUserGroupId(14);
			$author->setSubmissionId($this->submissionId);

			$authorDao->insertObject($author);
		}
	}

	/**
	 * Permite insertar los archivos a su carpeta y insertarlos a la base de datos
	 * 
	 * @param string $file ruta donde se encuentra el archivo a subir
	 * @param string $type_file tipo de archivo a subir PDF EPUB CSS PNG
	 * @param string $complement id del archivo al que es complementario
	 * @param int $journal_id id de la revista
	 * @param int $submission_id id del articulo
	 * @param string $url si el galley que se va a cargar es un link, se pone la url
	 * 
	 * @return idSubmission o idGalley
	 */
	private function add_galley($file, $type_file, $complement, $journal_id, $submission_id, $url)
	{
		$fileStage = 10;
		$revisedFileId = null;
		$assocType = 521;
		$assocId = null;
		$genreId = null;
		if ($complement != null) {
			$assocType = 515;
			$assocId = $complement;
			$fileStage = 17;
		}

		switch ($type_file) {
			case "png":
				$genreId = 10;
				break;
			case "css":
				$genreId = 11;
				break;
			default:
				$genreId = 1;
				break;
		}

		if ($file != null) {

			//insertar a DB
			import('lib.pkp.classes.file.SubmissionFileManager');
			$submissionFileManager = new SubmissionFileManager($journal_id, $submission_id);
			try {
				$submissionFile = $submissionFileManager->_instantiateSubmissionFile($file, $fileStage, $revisedFileId, $genreId, $assocType, $assocId);
			} catch (Exception $e) {
				$error = $e;
				$er = "";
			}


			if (is_null($submissionFile)) return null;
			//obtener el tipo de archivo
			$fileType = mime_content_type($file);
			assert($fileType !== false);

			//validacion para que se vean los CSS
			$findCSS   = 'text/x-asm';
			$pos = strpos($fileType, $findCSS);
			if ($pos !== false) {
				$fileType = "text/css";
			}

			$submissionFile->setFileType($fileType);
			$originalFileName = basename($file);
			assert($originalFileName !== false);
			$submissionFile->setOriginalFileName($submissionFileManager->truncateFileName($originalFileName));

			// Set the uploader's user and user group id.
			$user = $this->request->getUser();
			$submissionFile->setUploaderUserId($user->getId());

			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$submision = $submissionFileDao->insertObject($submissionFile, $file, false);
		} else {
			$submision = null;
		}


		//si no es complementaria, no se inserta en galley
		if ($complement != null) {
			return $submision;
		} else {
			// agregar detalles al galley
			$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
			$galley_data = $galleyDao->newDataObject();
			$galley_data->setSubmissionId($submission_id);
			$galley_data->setlocale(AppLocale::getLocale());
			if ($submision != null) {
				$id_submission_file = $submision->getFileId();
				$galley_data->setFileId($id_submission_file);
			}

			switch ($type_file) {
				case "URL":
					$galley_data->setLabel("Visor");
					$galley_data->setSequence("0");
					$galley_data->setRemoteURL($url);
					break;
				case "PDF":
					$galley_data->setLabel("PDF");
					$galley_data->setSequence("0");
					break;
				case "EPUB":
					$galley_data->setLabel("EPUB");
					$galley_data->setSequence("1");
					break;
				case "HTML":
					$galley_data->setLabel("HTML");
					$galley_data->setSequence("2");
					break;
			}
			$resp = $galleyDao->insertObject($galley_data);

			return $submision;
		}
	}

	/**
	 * Este metodo te permite insertar una seccion en la base de datos
	 * 
	 * @param string $seccion_name Nombre de la seccion a agregar
	 * @return int $sectionId Id de la seccion insertada
	 */
	private function add_section($section_name)
	{
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$journal = Application::getRequest()->getJournal();

		$locale = AppLocale::getLocale();
		$valor_vacio = array($locale => "");
		$title = array($locale => $section_name);

		$abr = str_split($section_name, 2);
		$section = $sectionDao->newDataObject();
		$section->setJournalId($journal->getId());
		$section->setTitle($title, null);
		$section->setAbbrev($abr[0], null);
		$section->setReviewFormId(0);
		$section->setMetaIndexed(1);
		$section->setMetaReviewed(1);
		$section->setAbstractsNotRequired(0);
		$section->setIdentifyType($valor_vacio, null);
		$section->setEditorRestricted(0);
		$section->setHideTitle(0);
		$section->setHideAuthor(0);
		$section->setPolicy($valor_vacio, null);
		$section->setAbstractWordCount(0);

		$section->setSequence(REALLY_BIG_NUMBER);
		$sectionId = $sectionDao->insertObject($section);
		$sectionDao->resequenceSections($journal->getId());
		return $sectionId;
	}

	/**
	 * Esta funcion permite encontrar la ruta donde se encuentran los archivos html
	 * 
	 * @param array @coincidencias un array de todos los archivos que coinciden con la busqueda
	 * @return string ruta donde se encuentran los archivos html,css,png
	 *  */
	private function find_path_html($coincidencias)
	{
		$files = array();
		foreach ($coincidencias as $file) {
			if (strpos($file, 'html') !== false or strpos($file, 'HTML') !== false) {
				array_push($files, $file);
				$path_directory = dirname($file);
				return $path_directory;
				//return $files;
			}
		}
		//return $files;
		return null;
	}

	/**
	 * Devuelve el archivo index.html o un array como los archivos que coincidandan con los parametros
	 * 
	 * @param string $path_html_directory ruta donde se encuentran los archivos html
	 * @param bool $index_html permite saber si se esta buscando el archivo index
	 * @param string $extension tipo de extension que se busca devolver 
	 * @return index.html o array de archivos
	 */
	private function obtener_archivos($path_html_directory, $index_html = false, $extension)
	{
		$response = array();
		$files = array_diff(scandir($path_html_directory), array('.', '..'));
		foreach ($files as $file) {
			// Divides en dos el nombre de tu archivo utilizando el . 
			$data          = explode(".", $file);
			// Nombre del archivo
			$fileName      = $data[0];
			// Extensión del archivo 
			$fileExtension = $data[1];

			//Solo regresa el index
			if ($index_html) {
				if ($fileName == "index" && $fileExtension == $extension) {
					return $file;
				}
			}

			//valida que la extension
			if ($fileExtension == $extension) {
				array_push($response, $file);
			}
		}
		//regresa todos los archivos que tengan esa extension
		return $response;
	}

	/**
	 * Permite validar que la url es valida
	 * 
	 * @param string $url url donde se encuentra el visor del articulo
	 * @return bool $exists
	 */
	private function validarURL($url)
	{
		$file_headers = get_headers($url);

		if (!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
			$exists = false;
		} else {
			$exists = true;
		}
		return $exists;
	}

	/**
	 * Metodo para la validacion de la existencia de el volumen numero y año en la base de datos
	 * 
	 * @param string $volume	
	 * @param string $number
	 * @param string $year
	 * @return bool $returner Si existe alguna coincidencia con los parametros regresa true,false en otro caso
	 */
	private function validarNumero($volume, $number, $year)
	{
		$journal = $this->request->getJournal();

		$issueDao = DAORegistry::getDAO('IssueDAO');
		$result = $issueDao->retrieve(
			'SELECT i.* FROM issues i WHERE journal_id = ? AND volume = ? AND number = ? AND year = ?',
			array((int) $journal->getId(), $volume, $number, $year)
		);
		$returner = $result->RecordCount() != 0 ? true : false;
		$result->Close();
		return $returner;
	}

	/**
	 * Metodo para la validacion de la existencia de la seccion
	 * 
	 * @param string $seccion Nombre de la seccion a buscar, si no existe toma por default "Articulos"
	 * @return int $row["section_id"] Id de la Seccion encontrada o null en caso de no encontrar la seccion
	 * 
	 */
	private function validarSeccion($seccion = "Articulos")
	{
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$journal = Application::getRequest()->getJournal();

		$params = array($seccion);
		$sql = 'SELECT section_id FROM section_settings WHERE setting_value = ?';
		$result = $sectionDao->retrieve($sql, $params);
		if ($result->RecordCount() != 0) {
			$row = $result->GetRowAssoc(false);
			return $row["section_id"];
		}
		$result->Close();
		return null;
	}

	/**
	 * Metodo para obtener todos los datos del zip
	 * 
	 * @param string $articleFolder Ruta del Directorio descomprimido
	 * @return array $articleData Array con los datos del zip
	 * 
	 */
	private function getDataByPath($articleFolder)
	{
		$articleData = array();
		$dirs = scandir($articleFolder);
		//Se limpia la ruta de archivos basura
		$dir = array_values($this->cleanFileList($dirs));
		$dir = $this->cleanFileList($dir);
		$articleDir = str_replace(' ', '\ ', $articleFolder . DIRECTORY_SEPARATOR . $dir[0]);
		//Se buscan todos los archivos xml
		$xmlFiles = $this->findFilesByExtension($articleDir, 'xml');
		$contador_articulos = 0;

		$numero = "";
		$volumen = "";
		$error = "";
		foreach ($xmlFiles as $xf) {
			$xmlJats = file_get_contents($xf);
			$pos = strpos($xf, "__MACOSX");
			if (!$pos) {
				$xmlJatsDom = new DOMDocument();
				if (!$xmlJatsDom->loadXML($xmlJats)) {
					//Error al abrir el xml

					return "Error archivo: " . $xf;
				} else {
					$datos_articulo = $this->leerXML($xmlJatsDom, $this->request, $contador_articulos);

					$validar_existencia_numero = true;
					if ($contador_articulos == 0) {
						$numero = $datos_articulo->getNumero()->getNumber();
						$volumen = $datos_articulo->getNumero()->getVolume();
					} else {
						$numero_a = $datos_articulo->getNumero()->getNumber();
						$volume_a = $datos_articulo->getNumero()->getVolume();
						//Valida que el los articulos pertenescan al mismo volumen y numero
						if ($numero == $numero_a && $volumen == $volume_a) {
							$validar_existencia_numero = false;
						} else {
							$error .= "Error: No coinciden Volumen-Numero en el ID: " . $datos_articulo->article_id_redalyc . "</br>";
						}
					}

					//Se validan las datos que contiene este articulo
					$datos_articulo = $this->validarArticulo($datos_articulo, $validar_existencia_numero);

					//Se agrega un error en caso de existir el Numero en la base de datos
					if ($datos_articulo->getNumero()->Status == 202) {
						$error .= "Error: Ya existe el Volumen " . $volumen . " Numero " . $numero;
					}

					array_push($articleData, $datos_articulo);
				}
			}



			$contador_articulos++;
		}

		// Si contienen errores la lectura del archivo se regresan estos
		if ($error != "") {
			return $error;
		}

		// Si no contienen errores, se regresan los datos
		return $articleData;
	}

	/**
	 * Permite leer el xml y convertirlo en un objeto
	 * 
	 * @param DOMDocument $xml El DOM del xml
	 * @param int $index Identificador extra del arreglo
	 * @return Articulo $submission Objeto que contiene todos los datos del xml
	 */
	private function leerXML($xml, $index)
	{
		//objeto principal
		$submission = new Articulo($index, "", "");
		$journal = $this->request->getJournal();

		$issueMonth = $xml->getElementsByTagName('season');
		if (sizeof($issueMonth) == 0) {
			$issueMonth = "";
		} else {
			$issueMonth = $issueMonth->item(0)->nodeValue;
		}


		$issueYear = $xml->getElementsByTagName('year');
		$issueYear = $issueYear->item(0)->nodeValue;
		$issueNumber = $xml->getElementsByTagName('issue');
		$issueNumber = $issueNumber->item(0)->nodeValue;
		$issueVolume = $xml->getElementsByTagName('volume');
		$volume = $issueVolume->length == 0 ? 1 : $issueVolume->item(0)->nodeValue;
		$numero = empty($issueNumber) ? 0 : $issueNumber;
		$anio = empty($issueYear) ? 0 : $issueYear;

		//se guardan los datos del numero en el objeto Numero
		$mi_numero = new Numero($journal->getId(), $issueMonth, $volume, $numero, $anio);

		$lang = $xml->getElementsByTagName("article")[0]->getAttribute('xml:lang');
		$submission->setLanguage($lang);
		//obtiene el titulo del articulo
		$titleLang = $xml->getElementsByTagName("article-title");
		$titulo_articulo = "";
		foreach ($titleLang as $k => $tl) {
			$xmlLang = $tl->getAttribute('xml:lang');
			$titulo_articulo = $tl->nodeValue;
			if ($xmlLang != "") {
				$submission->setTitle($titulo_articulo, $this->commonLangs[$xmlLang]);
			}
		}
		//obtine el idioma principal
		$abstractLang = $xml->getElementsByTagName("abstract");
		foreach ($abstractLang as $k => $al) {
			$xmlLang = $al->getAttribute('xml:lang');
			$titleAbs = $al->getElementsByTagName('title')->item(0);
			$al->removeChild($titleAbs);
			$submission->setAbstract($al->nodeValue, $this->commonLangs[$xmlLang]);
		}

		//obtine los resumenes en y sus idiomas
		$transAbstract = $xml->getElementsByTagName("trans-abstract");
		foreach ($transAbstract as $k => $ta) {
			$xmlLang = $ta->getAttribute('xml:lang');
			$titleAbs = $ta->getElementsByTagName('title')->item(0);
			$ta->removeChild($titleAbs);
			$submission->setAbstract($ta->nodeValue, $this->commonLangs[$xmlLang]);
		}

		//obtener la seccion del articulo
		$articleCategories = $xml->getElementsByTagName("subject");
		foreach ($articleCategories as $k => $ac) {
			$categoria = $ac->nodeValue;
			$submission->setCategoria($categoria);
		}

		//obtener los ids de redalyc
		$journal_id_nodo = $xml->getElementsByTagName("journal-id");
		$journal_id = "";
		foreach ($journal_id_nodo as $k => $tl) {
			$journal_id = $tl->nodeValue;
		}
		$submission->setIdJournalRedalyc($journal_id);

		$article_id_nodo = $xml->getElementsByTagName("article-id");
		$article_id = "";
		foreach ($article_id_nodo as $k => $tl) {
			$pub_id_type = $tl->getAttribute('pub-id-type');
			if ($pub_id_type == "art-access-id") {
				$article_id = $tl->nodeValue;
			}
		}

		$submission->setIdArticleRedalyc($article_id);

		$submission->setNumero($mi_numero);

		//Datos de Autores
		$xpath = new DOMXPath($xml);
		$authors = $xpath->query("//contrib-group/contrib[@contrib-type='author']");
		$contador_autores = 0;
		foreach ($authors as $k => $author) {
			try {
				$authorSurName = $xpath->query("//name/surname", $author);
				$authorSurName = $authorSurName[$k]->textContent;
			} catch (Exception $e) {
				$authorSurName = "";
			}

			try {
				$authorGivenName = $xpath->query("//name/given-names", $author);
				$authorGivenName = $authorGivenName[$k]->textContent;
			} catch (Exception $e) {
				$authorGivenName = "";
			}

			try {
				$authorEmail = $xpath->query(".//email", $author);
				if (count($authorEmail) != 0) {
					$authorEmail = $authorEmail[0]->textContent;
				} else {
					$authorEmail = "";
				}
			} catch (Exception $e) {
				$authorEmail = "";
			}

			try {
				$authorXref = $xpath->query(".//xref/@rid", $author);
				$authorXref = $authorXref[0]->textContent;
				$aff = $xpath->query(".//aff[@id='" . $authorXref . "']");
				$aff = $aff[0];
				$authorInstitution = $xpath->query(".//institution", $aff);
				$authorInstitution = $authorInstitution[0]->textContent;
			} catch (Exception $e) {
				$authorInstitution = "";
			}

			try {
				$authorCountry = $xpath->query(".//country/@country", $aff);
				$authorCountry = $authorCountry[0]->textContent;
			} catch (Exception $e) {
				$authorCountry = "Vacio";
			}

			$autor = new Autor();
			$autor->setNombre($authorGivenName);
			$autor->setApellidos($authorSurName);
			$autor->setInstitucion($authorInstitution);
			$autor->setPais($authorCountry);
			$autor->setEmail($authorEmail);
			$submission->setAutor($autor, $contador_autores);
			$contador_autores++;
		}
		return $submission;
	}

	/**
	 * Permite la busqueda de archivos por la extension que contenga el nombre
	 * Utilizando el comando find
	 * 
	 * @param string $folderPath Ruta donde se va a buscar los archivos
	 * @param string $ext Extension que los archivos que se va a buscar
	 * @return array $output Array con los archivos encontrados
	 */
	private function findFilesByExtension($folderPath, $ext)
	{
		$unzipCmd = "";
		$unzipCmd .= ' find ' . $folderPath . ' -name "*.' . $ext . '" -print';
		$unzipCmd .= ' 2>&1';
		exec($unzipCmd, $output, $returnValue);
		return $output;
	}

	/**
	 * Permite la busqueda de archivos por alguna cadena que contenga el nombre
	 * Utilizando el comando find
	 * 
	 * @param string $folderPath Directorio donde se va a buscar
	 * @param string $id Caracteres que contenga el nombre del archivo
	 * @param string $ext Extension del archivo
	 * 
	 * @return $output Regresa un array con las coincidencias encontradas
	 */
	private function findFilesByName($folderPath, $nombre, $ext)
	{
		$unzipCmd = "";
		$unzipCmd .= ' find ' . $folderPath . ' -name "*' . $nombre . '*" -and  -name "*.' . $ext . '" -print';
		$unzipCmd .= ' 2>&1';
		exec($unzipCmd, $output, $returnValue);
		return $output;
	}

	/**
	 * Metodo para la descompresion de archivos
	 * @param string $filePath Ruta del zip
	 * @param bool $allInSubDirectories Permite manejar si se desea descomprimir todos los archivos
	 * @return string nombre del archivo descomprimido
	 */
	private function decompressZipFile($filePath, &$errorMsg, $allInSubDirectories)
	{
		PKPLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN);
		$unzipPath = Config::getVar('cli', 'unzip');
		if (!is_executable($unzipPath)) {
			$errorMsg = __('admin.error.executingUtil', array('utilPath' => $unzipPath, 'utilVar' => 'unzip'));
			return false;
		}
		$unzipCmd = "";
		if ($allInSubDirectories) {
			$unzipCmd .= ' find ' . $filePath . ' -name \'*.zip\' -exec sh -c \'unzip -d "${1%.*}" "$1"\' _ {} \;';
			$unzipCmd .= ' 2>&1';
			exec($unzipCmd, $output, $returnValue);
		} else {

			$unzipCmd = escapeshellarg($unzipPath);
			$unzipCmd .= ' -o';
			$unzipCmd .= ' -d ' . $filePath . '_1';
			$output = array($filePath);
			$returnValue = 0;
			$unzipCmd .= ' ' . $filePath;
			if (!Core::isWindows()) {
				$unzipCmd .= ' 2>&1';
			}

			exec($unzipCmd, $output, $returnValue);

			if ($returnValue > 0) {
				$errorMsg = __('admin.error.utilExecutionProblem', array('utilPath' => $unzipPath, 'output' => implode(PHP_EOL, $output)));
				return false;
			}

			return $filePath . '_1';
		}
	}

	/**
	 * Permite quitar archivos que se encuentran dentro de la carpeta MACOSX
	 * los cuales se consideran basura
	 * 
	 * @param array $archivos Es un array de archivos
	 * @return array $files Array con los archivos limpios
	 */
	private function quitar_archivos_basura($archivos)
	{
		$files = array();
		foreach ($archivos as $file) {
			if (strpos($file, '__MACOSX') === false) {
				array_push($files, $file);
			}
		}
		return $files;
	}

	/** */
	private function cleanFileList($dirs)
	{
		$dirs = array_diff($dirs, array('.', '..', '__MACOSX', '.DS_Store'));
		return $dirs;
	}

	/**
	 * @copydoc PKPImportExportPlugin::usage
	 */
	function usage($scriptName)
	{
		echo __('plugins.importexport.marcalycImporter.cliUsage', array(
			'scriptName' => $scriptName,
			'pluginName' => $this->getName()
		)) . "\n";
	}

	/**
	 * @see PKPImportExportPlugin::executeCLI()
	 */
	function executeCLI($scriptName, &$args)
	{
		$this->usage($scriptName);
	}
}
