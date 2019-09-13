{**
 * plugins/importexport/marcalyc/templates/settingsForm.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * marcalyc plugin settings
 *
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#marcalycSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="marcalycSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" plugin="MarcalycImportPlugin" category="importexport" verb="save"}">
	{csrf}
	{fbvFormArea id="marcalycSettingsFormArea"}
		{fbvFormSection}
			<p class="pkp_help">{translate key="plugins.importexport.marcalycImporter.registrationIntro"}</p>
			{fbvElement type="text" id="url_visor" value=$url_visor label="plugins.importexport.marcalycImporter.url_visor" maxlength="50" size=$fbvStyles.size.MEDIUM}
			<span class="instruct">{translate key="plugins.importexport.marcalycImporter.settingsDescription"}</span><br/>
		{/fbvFormSection}
		{fbvFormSection list="true"}
			{fbvElement type="checkbox" id="validarURL" label="plugins.importexport.marcalycImporter.validarURL" checked=$validarURL|compare:true}
		{/fbvFormSection}
		{fbvFormSection list="true"}
			{fbvElement type="checkbox" id="updateOld" label="plugins.importexport.marcalycImporter.updateOld" checked=$updateOld|compare:true}
		{/fbvFormSection}
		

	{/fbvFormArea}
	
	{fbvFormButtons submitText="common.save"}
	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>

