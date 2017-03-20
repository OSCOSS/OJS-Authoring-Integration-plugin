{**
 * plugins/generic/fidusWriter/settingsForm.tpl
 *
 * Copyright (c) 2013 Simon Fraser University Library
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * FidusWriter plugin settings
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.generic.fidusWriter.manager.fidusWriterSettings"}
{include file="common/header.tpl"}
{/strip}
<div id="fidusWriterSettings">
<div id="description">{translate key="plugins.generic.fidusWriter.manager.settings.description"}</div>

<div class="separator"></div>

<br />

<form method="post" action="{plugin_url path="settings"}">
{include file="common/formErrors.tpl"}

<table width="100%" class="data">
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="apiUrl" required="true" key="plugins.generic.fidusWriter.manager.settings.apiUrl"}</td>
		<td width="80%" class="value">
			<input type="text" name="apiUrl" id="apiUrl" value="{$apiUrl|escape}" size="60" maxlength="120" class="textField" />
			<br />
			<span class="instruct">{translate key="plugins.generic.fidusWriter.manager.settings.apiUrlInstructions"}</span>
		</td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="apiKey" required="true" key="plugins.generic.fidusWriter.manager.settings.apiKey"}</td>
		<td class="value"><input type="text" name="apiKey" id="apiKey" value="{$apiKey|escape}" size="60" maxlength="120" class="textField" /></td>
	</tr>
</table>

<br/>

<input type="submit" name="save" class="button defaultButton" value="{translate key="common.save"}"/><input type="button" class="button" value="{translate key="common.cancel"}" onclick="history.go(-1)"/>
</form>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</div>
{include file="common/footer.tpl"}
