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

    public function OJSFWGatewayPlugin($parentPluginName)
    {
        parent::GatewayPlugin();
        $this->parentPluginName = $parentPluginName;
    }

    /**
     * Hide this plugin from the management interface (it's subsidiary)
     */
    public function getHideManagement()
    {
        return true;
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     * @return String name of plugin
     */
    public function getName()
    {
        return 'OJSFWGatewayPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.generic.ojsfw.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.ojsfw.description');
    }

    /**
     * Get the OJSFWIntegrationPlugin plugin
     * @return OJSFWIntegrationPlugin
     */
    public function getOJSFWIntegrationPlugin()
    {
        return PluginRegistry::getPlugin('generic', $this->parentPluginName);
    }

    /**
     * Override the builtin to get the correct plugin path.
     */
    public function getPluginPath()
    {
        return $this->getOJSFWIntegrationPlugin()->getPluginPath();
    }

    /**
     * Override the builtin to get the correct template path.
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->getOJSFWIntegrationPlugin()->getTemplatePath();
    }

    /**
     * Get whether or not this plugin is enabled. (Should always return true, as the
     * parent plugin will take care of loading this one when needed)
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->getOJSFWIntegrationPlugin()->getEnabled();
    }


    /**
     * Handle fetch requests for this plugin.
     * @param $args array
     * @param $request PKPRequest Request object
     * @return bool
     */
    public function fetch($args, $request)
    {
        // Put and post requests are also routed here. Testing that by:
        //$postVariableArray= $this->getPOSTPayloadVariable('afshinpayloadvariable');
        //var_dump($postVariableArray);

        if (!$this->getEnabled()) {
            return false;
        }

        $restCallType = $this->getRESTRequestType();
        $operator = array_shift($args);
        switch ($operator) {
            case 'test': // Basic test

                if($restCallType === "GET")
                $response = array(
                    "test message" => "GET rest  is testing",
                    "test version" => "1.0"
            );
                if($restCallType === "POST")
                    $response = array(
                        "test message" => "POST rest is testing",
                        "test version" => "1.0"
                    );

                if($restCallType === "PUT")
                    $response = array(
                        "test message" => "PUT rest is testing",
                        "test version" => "1.0"
                    );
                if($restCallType === "DELETE")
                    $response = array(
                        "test message" => "DELETE rest is testing",
                        "test version" => "1.0"
                    );
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
    public function showError()
    {
        header("HTTP/1.0 500 Internal Server Error");
        echo "internal server error";
        //todo extend with : echo Locale::translate('plugins.gateways.OJSFWGatewayPlugin.errors.errorMessage');
        exit;
    }


    /**
     * @param $varName
     * @return string
     */
    private function getPOSTPayloadVariable($varName){
        
        if(isset($_POST[$varName])){
            return $_POST[$varName];
        }
        return "";
    }

    /**
     * @return string
     */
    private function getRESTRequestType(){
        $callType = $_SERVER['REQUEST_METHOD'];
        switch ($callType){
            case 'PUT':
            case 'DELETE':
            case 'GET':
            case 'POST':
                $result =  $callType;
            break;
            default:
                $result = "";
        }
        return $result;
    }

}

?>