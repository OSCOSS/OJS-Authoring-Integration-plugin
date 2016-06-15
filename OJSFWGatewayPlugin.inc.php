<?php
/**
 * University of Bonn
 * User: Afhin Sadeghi
 * Date: 15/06/16
 * Time: 13:22
 */
import('lib.pkp.classes.plugins.GatewayPlugin');

class OJSFWGatewayPlugin extends GatewayPlugin
{


    /** @var string Name of parent plugin */
    var $parentPluginName;

    function OJSFWGatewayPlugin($parentPluginName)
    {
        parent::GatewayPlugin();
        $this->parentPluginName = $parentPluginName;
    }

    /**
     * Hide this plugin from the management interface (it's subsidiary)
     */
    function getHideManagement()
    {
        return true;
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     * @return String name of plugin
     */
    function getName()
    {
        return 'OJSFWGatewayPlugin';
    }

    function getDisplayName()
    {
        return __('plugins.generic.ojsfw.displayName');
    }

    function getDescription()
    {
        return __('plugins.generic.ojsfw.description');
    }

    /**
     * Get the OJSFWIntegrationPlugin plugin
     * @return OJSFWIntegrationPlugin
     */
    function getOJSFWIntegrationPlugin()
    {
        return PluginRegistry::getPlugin('generic', $this->parentPluginName);
    }

    /**
     * Override the builtin to get the correct plugin path.
     */
    function getPluginPath()
    {
        return $this->getOJSFWIntegrationPlugin()->getPluginPath();
    }

    /**
     * Override the builtin to get the correct template path.
     * @return string
     */
    function getTemplatePath()
    {
        return $this->getOJSFWIntegrationPlugin()->getTemplatePath();
    }

    /**
     * Get whether or not this plugin is enabled. (Should always return true, as the
     * parent plugin will take care of loading this one when needed)
     * @return boolean
     */
    function getEnabled()
    {
        return $this->getOJSFWIntegrationPlugin()->getEnabled();
    }


    /**
     * Handle fetch requests for this plugin.
     * @param $args array
     * @param $request PKPRequest Request object
     * @return bool
     */
    function fetch($args, $request)
    {
        if (!$this->getEnabled()) {
            return false;
        }

        $operator = array_shift($args);
        switch ($operator) {
            case 'test': // Basic test  
                $response = array("test message" => "rest is testing",
                    "test version" => "1.0");
                echo json_encode($response);
                break;

            default:
                // Not a valid request
                $this->showError();
        }
        return true;
    }

    /**
     * Display an error message and exit
     */
    function showError()
    {
        header("HTTP/1.0 500 Internal Server Error");
        echo "internal server error";
        //todo extend with : echo Locale::translate('plugins.gateways.OJSFWGatewayPlugin.errors.errorMessage');
        exit;
    }

}

?>