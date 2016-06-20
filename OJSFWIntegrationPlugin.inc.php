<?php
import('lib.pkp.classes.plugins.GenericPlugin');

/**
 * Created by PhpStorm.
 * User: afshin
 * Date: 13/06/16
 * Time: 14:44
 */
class OJSFWIntegrationPlugin extends GenericPlugin
{



    /**
     * Get the name of the settings file to be installed on new context
     * creation.
     * @return string
     */
    function getContextSpecificPluginSettingsFile() {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Override the builtin to get the correct template path.
     * @return string
     */
    function getTemplatePath() {
        return parent::getTemplatePath() . 'templates/';
    }

    /**
     * Get the display name for this plugin.
     *
     * @return string
     */
    function getDisplayName()
    {
        return __('plugins.generic.ojsfw.displayName');

    }

    /**
     * Get a description of this plugin.
     *
     * @return string
     */
    function getDescription()
    {
        return __('plugins.generic.ojsfw.description');

    }

    /**
     * @param $category
     * @param $path
     * @return bool
     */
    function register($category, $path) {
        if (parent::register($category, $path)) {
            if ($this->getEnabled()) {
                HookRegistry::register('PluginRegistry::loadCategory', array($this, 'callbackLoadCategory'));
            }
            return true;
        }
        return false;
    }

    /**
     * @param $hookName string
     * @param $args array
     * @return bool
     **/
    function callbackLoadCategory($hookName, $args) {
        $category =& $args[0];
        $plugins =& $args[1];
        switch ($category) {
            case 'gateways':
                $this->import('OJSFWGatewayPlugin');
                $gatewayPlugin = new OJSFWGatewayPlugin($this->getName());
                $plugins[$gatewayPlugin->getSeq()][$gatewayPlugin->getPluginPath()] = $gatewayPlugin;
                break;
        }
        return false;
    }
    

}