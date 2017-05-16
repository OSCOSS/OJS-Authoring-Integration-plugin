{**
 * plugins/generic/fidusWriter/settingsForm.tpl
 *
 * Based on code of:
 * Copyright (c) 2013 Simon Fraser University Library
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see
 * https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 *
 * FidusWriter plugin settings
 *
 *}
<div id="fidusWriterSettings">
<div id="description">{translate key="plugins.generic.fidusWriter.description"}</div>
<h3>{translate key="plugins.generic.fidusWriter.settings"}</h3>


<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#fidusWriterSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="fidusWriterSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="common/formErrors.tpl"}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="fidusWriterSettingsFormNotification"}

    {fbvFormSection title="plugins.generic.fidusWriter.settings.apiKey" description="plugins.generic.fidusWriter.settings.apiKey.description"}
		{fbvElement type="text" id="apiKey" value=$apiKey}
	{/fbvFormSection}
    {fbvFormButtons id="fidusWriterSettingsFormSubmit" submitText="common.save" hideCancel=true}
</form>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</div>
