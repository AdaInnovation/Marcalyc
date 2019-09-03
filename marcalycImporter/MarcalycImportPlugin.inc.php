<?php

/**
 * @file plugins/importexport/marcalycImporter/MarcalycImportPlugin.inc.php
 *
 * Copyright (c) 2019 
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MarcalycImportPlugin
 * @ingroup plugins_importexport_marcalycImporter
 *
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');


class MarcalycImportPlugin extends ImportExportPlugin
{
	var $context;
	var $submission;
	var $request;


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
					$statusMsg .= __('plugins.importexport.marcalycImporter.errorDecompress');
					$templateMgr->assign('Resultados', $statusMsg);
					$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
					header('Content-Type: application/json');
					return $json->getString();
				}

				$templateMgr->assign('temporaryFolder', '->' . $processingFilePath);
				$dirs = scandir($processingFilePath);
				$dirs = $this->cleanFileList($dirs);
				$this->decompressZipFile($processingFilePath, $errorMsg, true);

				$artIdx = 0;
				$issueObj = null;
				$articleData = $this->getDataByPath($processingFilePath);

				if (empty($articleData)) {
					$statusMsg .= __('plugins.importexport.marcalycImporter.noContent');
					$templateMgr->assign('Resultados', $statusMsg);
					$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
					header('Content-Type: application/json');
					return $json->getString();
				}

				//$statusMsg = "<ul class='list-group'>";

				$numeroMsg="";
				$articulosMsg="";
				foreach ($articleData as $k => $ad) {
					if ($artIdx == 0) {
						$issueObj = $this->createNumber($ad, $request);
						if ($issueObj == null) {
							$errorMsg = "Ocurrio un error al importar el numero";
							$templateMgr->assign('Resultados', $errorMsg);
							$json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
							header('Content-Type: application/json');
							return $json->getString();
						}
						$numeroMsg .="<h3 style='text-align: center;'> = Resultados =</h3>";
						$numeroMsg .= "<h4>Vol. " . $issueObj->getVolume() . " Num " . $issueObj->getNumber() . "</h4>";
						$numeroMsg .= "<h4>Articulos importados:</h4>";
						$artIdx++;
					}

					$tituloArticulo = $this->createArticle($ad, $issueObj->getId());
					$article_id = $this->insert_url($ad, $request->getContext()->getId());
					$this->insert_files_pdf($processingFilePath, $article_id, $request->getContext()->getId());
					$this->insert_files_epub($processingFilePath, $article_id, $request->getContext()->getId());
					$this->insert_files_html($processingFilePath, $article_id, $request->getContext()->getId());
					$this->assignAuthorsArticle($ad);
					$articulosMsg .= 	"<li class='list-group-item'> " . $tituloArticulo . " </li>";
					
				}
				$statusMsg.=$numeroMsg;
				$statusMsg .="<ul class='list-group'>".$articulosMsg."</ul>";
				$templateMgr->assign('Resultados', $statusMsg);
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
		$journal_id_nodo = $articleData->getElementsByTagName("journal-id");
		$journal_id = "";
		foreach ($journal_id_nodo as $k => $tl) {
			$journal_id = $tl->nodeValue;
		}

		$article_id_nodo = $articleData->getElementsByTagName("article-id");
		$article_id = "";
		foreach ($article_id_nodo as $k => $tl) {
			$article_id = $tl->nodeValue;
		}

		//url del visor de redalyc
		$link_visor = "http://www.redalyc.org/jatsRepo/" . $journal_id . "/" . $article_id . "/index.html";
		$existe_url = $this->validarURL($link_visor);
		if ($existe_url) {
			$this->add_galley(null, "URL", null, $context_id, $this->submissionId, $link_visor);
		}
		return $article_id;
	}

	/**
	 * Permite la insercion de archivos PDF al galley del articulo
	 * 
	 * @param string $path ruta donde se encuentran los archivos del articulo
	 * @param string $article_id id con el cual el xml identifica al articulo
	 * @param int $context_id id de la revista
	 */
	private function insert_files_pdf($path, $article_id, $context_id)
	{
		$todas_las_concidencias = $this->findFilesByName($path, $article_id, "pdf");
		$archivos_upload = $this->quitar_archivos_basura($todas_las_concidencias);
		foreach ($archivos_upload as $file) {
			$this->add_galley($file, "PDF", null, $context_id, $this->submissionId, "");
		}
	}

	/**
	 * Permite la insercion de archivos EPUB al galley del articulo
	 * 
	 * @param string $path ruta donde se encuentran los archivos del articulo
	 * @param string $article_id id con el cual el xml identifica al articulo
	 * @param int $context_id id de la revista
	 */
	private function insert_files_epub($path, $article_id, $context_id)
	{
		$todas_las_concidencias = $this->findFilesByName($path, $article_id, "epub");
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
	 * @param string $article_id id con el cual el xml identifica al articulo
	 * @param int $context_id id de la revista
	 */
	private function insert_files_html($path, $article_id, $context_id)
	{
		$todas_las_concidencias = $this->findFilesByName($path, $article_id, "png");
		$coincidencias = $this->quitar_archivos_basura($todas_las_concidencias);
		$path_html_directory = $this->find_path_html($coincidencias);
		$archivo_index = $this->obtener_archivos($path_html_directory, true, "html");


		$file_index = $path_html_directory . DIRECTORY_SEPARATOR . $archivo_index;
		$assoc_id = $this->add_galley($file_index, "HTML", null, $context_id, $this->submissionId, "");

		$archivos_png = $this->obtener_archivos($path_html_directory, false, "png");
		//$archivos_jpg = $this->obtener_archivos($path_html_directory,false,"jpg");
		$archivos_css = $this->obtener_archivos($path_html_directory, false, "css");

		//Cargar los archivos complementarios PNG


		foreach ($archivos_png as $archivo) {
			$file_png = $path_html_directory . DIRECTORY_SEPARATOR . $archivo;
			$this->add_galley($file_png, "png", $assoc_id->getFileId(), $context_id, $this->submissionId, "");
		}

		//Cargar los archivos complementarios CSS
		foreach ($archivos_css as $archivo) {
			$file_css = $path_html_directory . DIRECTORY_SEPARATOR . $archivo;
			$this->add_galley($file_css, "css", $assoc_id->getFileId(), $context_id, $this->submissionId, "");
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
			$submissionFile = $submissionFileManager->_instantiateSubmissionFile($file, $fileStage, $revisedFileId, $genreId, $assocType, $assocId);

			if (is_null($submissionFile)) return null;

			$fileType = mime_content_type($file);
			assert($fileType !== false);
			//obtener el tipo de archivo
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

	private function createNumber($articleData, $request)
	{
		$journal = $request->getJournal();
		$issueMonth = $articleData->getElementsByTagName('season');
		$issueMonth = $issueMonth->item(0)->nodeValue;
		$issueYear = $articleData->getElementsByTagName('year');
		$issueYear = $issueYear->item(0)->nodeValue;
		$issueNumber = $articleData->getElementsByTagName('issue');
		$issueNumber = $issueNumber->item(0)->nodeValue;
		$issueVolume = $articleData->getElementsByTagName('volume');
		$volume = $issueVolume->length == 0 ? "0" : $issueVolume->item(0)->nodeValue;



		//	echo $issueMonth . ' - ' . $issueNumber . ' - ' . $issueYear;
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->newDataObject();
		$issue->setAccessStatus(ISSUE_ACCESS_OPEN);
		$issue->setTitle($issueMonth, null); // Localized
		$issue->setJournalId($journal->getId());
		$issue->setVolume($volume);
		$issue->setNumber(empty($issueNumber) ? 0 : $issueNumber);
		$issue->setYear(empty($issueYear) ? 0 : $issueYear);
		//	$issue->setDatePublished($this->getData('datePublished'));
		$issue->setPublished(0);
		$issue->setCurrent(0);
		$status_insert = $issueDao->insertObject($issue);
		if ($status_insert != null || $status_insert > 0) {
			return $issue;
		} else {
			return null;
		}
	}

	private function createArticle($articleData, $issueId)
	{
		$commonLangs = array(
			'es' => 'es_ES',
			'en' => 'en_US'
		);
		$date = Core::getCurrentDate();
		$locale = AppLocale::getLocale();
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$sectionOptions = $sectionDao->getTitlesByContextId($this->context->getId());
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->newDataObject();
		$submission->setContextId($this->context->getId());
		$submission->setStatus(STATUS_PUBLISHED);
		$submission->setSubmissionProgress(0);
		$submission->stampStatusModified();
		$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
		$submission->setSectionId(1);
		$submission->setLocale(AppLocale::getLocale());
		$submission->setDateSubmitted($date);
		$lang = $articleData->getElementsByTagName("article")[0]->getAttribute('xml:lang');
		$submission->setLanguage($lang);

		$titleLang = $articleData->getElementsByTagName("article-title");
		$titulo_articulo = "";
		foreach ($titleLang as $k => $tl) {
			$xmlLang = $tl->getAttribute('xml:lang');
			$titulo_articulo = $tl->nodeValue;
			$submission->setTitle($titulo_articulo, $commonLangs[$xmlLang]);
		}

		$abstractLang = $articleData->getElementsByTagName("abstract");
		foreach ($abstractLang as $k => $al) {
			$xmlLang = $al->getAttribute('xml:lang');
			$titleAbs = $al->getElementsByTagName('title')->item(0);
			$al->removeChild($titleAbs);
			$submission->setAbstract($al->nodeValue, $commonLangs[$xmlLang]);
		}


		$transAbstract = $articleData->getElementsByTagName("trans-abstract");
		foreach ($transAbstract as $k => $ta) {
			$xmlLang = $ta->getAttribute('xml:lang');
			$titleAbs = $ta->getElementsByTagName('title')->item(0);
			$ta->removeChild($titleAbs);
			$submission->setAbstract($ta->nodeValue, $commonLangs[$xmlLang]);
		}

		$this->submissionId = $submissionDao->insertObject($submission);
		//$this->setData('submissionId', $this->submissionId);
		//$this->_metadataFormImplem->initData($submission);
		// Add the user manager group (first that is found) to the stage_assignment for that submission
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

		return $titulo_articulo;
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

	private function assignAuthorsArticle($articleData)
	{
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$xpath = new DOMXPath($articleData);
		$authors = $xpath->query("//contrib-group/contrib[@contrib-type='author']");
		foreach ($authors as $k => $author) {
			$authorSurName = $xpath->query("//name/surname", $author);
			$authorSurName = $authorSurName[$k]->textContent;
			$authorGivenName = $xpath->query("//name/given-names", $author);
			$authorGivenName = $authorGivenName[$k]->textContent;
			$authorEmail = $xpath->query(".//email", $author);
			$authorEmail = $authorEmail[0]->textContent;

			$authorXref = $xpath->query(".//xref/@rid", $author);
			$authorXref = $authorXref[0]->textContent;
			$aff = $xpath->query(".//aff[@id='" . $authorXref . "']");
			$aff = $aff[0];
			$authorInstitution = $xpath->query(".//institution", $aff);
			$authorInstitution = $authorInstitution[0]->textContent;
			$authorCountry = $xpath->query(".//country/@country", $aff);
			$authorCountry = $authorCountry[0]->textContent;

			$givenName[AppLocale::getLocale()] = $authorGivenName;
			$surName[AppLocale::getLocale()] = $authorSurName;
			$author = $authorDao->newDataObject();
			$author->setGivenName($givenName, null);
			$author->setFamilyName($surName, null);
			$affilliation[AppLocale::getLocale()] = $authorInstitution;
			$author->setAffiliation($affilliation, null);
			$author->setCountry($authorCountry);
			$author->setEmail($authorEmail);
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

	/** */
	private function cleanFileList($dirs)
	{
		$dirs = array_diff($dirs, array('.', '..', '__MACOSX', '.DS_Store'));
		return $dirs;
	}

	private function getDataByPath($articleFolder)
	{
		$articleData = array();
		$dirs = scandir($articleFolder);
		$dir = array_values($this->cleanFileList($dirs));
		$articleDir = str_replace(' ', '\ ', $articleFolder . DIRECTORY_SEPARATOR . $dir[0]);
		$xmlFiles = $this->findFilesByExtension($articleDir, 'xml');
		foreach ($xmlFiles as $xf) {
			$xmlJats = file_get_contents($xf);
			$xmlJatsDom = new DOMDocument();
			if (!$xmlJatsDom->loadXML($xmlJats)) {
				//$this->logger->debugTranslate('marcalicImporter.erros.loadxml', $this->libxmlErrors());
				return false;
			}
			$articleData[$xf] = $xmlJatsDom;
		}
		return $articleData;
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
	 * 
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
