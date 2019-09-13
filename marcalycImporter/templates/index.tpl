{strip}
{assign var="pageTitle" value="plugins.importexport.marcalycImporter.displayName"}
{include file="common/header.tpl"}
{/strip}

<script type="text/javascript">
	// Attach the JS file tab handler.
	$(function() {ldelim}
		$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
		$('#importExportTabs').tabs('option', 'cache', true);
	{rdelim});
</script>
<div id="importExportTabs">
	<ul>
		<li><a href="#import-tab">{translate key="plugins.importexport.marcalycImporter.import"}</a></li>
		<li><a href="#settings-tab">{translate key="plugins.importexport.marcalycImporter.settings"}</a></li>
	</ul>
	<div id="import-tab">
		<script type="text/javascript">
			$(function() {ldelim}
				// Attach the form handler.
				$('#importXmlForm').pkpHandler('$.pkp.controllers.form.FileUploadFormHandler',
					{ldelim}
						$uploader: $('#plupload'),
							uploaderOptions: {ldelim}
								uploadUrl: {plugin_url|json_encode path="uploadCompressedFile" escape=false},
								baseUrl: {$baseUrl|json_encode}
							{rdelim}
					{rdelim}
				);
			{rdelim});
		</script>
		<form id="importXmlForm" class="pkp_form" action="{plugin_url path="import"}" method="post">
			{csrf}
			{fbvFormArea id="importForm"}
				{* Container for uploaded file *}
				<input type="hidden" name="temporaryFileId" id="temporaryFileId" value="" />

				{fbvFormArea id="file"}
					{fbvFormSection title="plugins.importexport.marcalycImporter.import.instructions"}
						{include file="controllers/fileUploadContainer.tpl" id="plupload"}
					{/fbvFormSection}
				{/fbvFormArea}

				{fbvFormButtons submitText="plugins.importexport.marcalycImporter.import" hideCancel="true"}
			{/fbvFormArea}
		</form>
	</div>

	<!--Tab setting  DOAJExportPlugin   MarcalycImportPlugin-->
	<div id="settings-tab">

		<!-- DOAJExportPlugin   MarcalycImportPlugin -->
		
		{capture assign=marcalycSettingsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="MarcalycImportPlugin" category="importexport" verb="index" escape=false}{/capture}
		{load_url_in_div id="marcalycSettingsGridContainer" url=$marcalycSettingsGridUrl}
		
	</div>

</div>

{include file="common/footer.tpl"}
