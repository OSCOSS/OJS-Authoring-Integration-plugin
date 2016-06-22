<?php
/**
 * Project OSCOSS
 * University of Bonn
 * User: Afhin Sadeghi sadeghi@cs.uni-bonn.de
 * Date: 15/06/16
 * Time: 13:22
 */
import('lib.pkp.classes.plugins.GatewayPlugin');
import('lib.pkp.classes.context.Context');
import('classes.journal.Journal');

class RestApiGatewayPlugin extends GatewayPlugin
{


    /** @var string Name of parent plugin */
    public $parentPluginName;

    /** PKPRequest */
   // public $request; //have to be public due to plugin class

    public function RestApiGatewayPlugin($parentPluginName)
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
        return 'RestApiGatewayPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.generic.ojsIntegrationRestApi.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.ojsIntegrationRestApi.description');
    }

    /**
     * Get the IntegrationApiPlugin plugin
     * @return IntegrationApiPlugin
     */
    public function getIntegrationApiPlugin()
    {
        return PluginRegistry::getPlugin('generic', $this->parentPluginName);
    }

    /**
     * Override the builtin to get the correct plugin path.
     */
    public function getPluginPath()
    {
        return $this->getIntegrationApiPlugin()->getPluginPath();
    }

    /**
     * Override the builtin to get the correct template path.
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->getIntegrationApiPlugin()->getTemplatePath();
    }

    /**
     * Get whether or not this plugin is enabled. (Should always return true, as the
     * parent plugin will take care of loading this one when needed)
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->getIntegrationApiPlugin()->getEnabled();
    }


    /**
     * Handle fetch requests for this plugin.
     * @param $args array
     * @param $request PKPRequest Request object
     * @return bool
     */
    public function fetch($args, $request)
    {



       // $this->request = $request;
        // Put and post requests are also routed here. Testing that by:
        //$postVariableArray= $this->getPOSTPayloadVariable('afshinpayloadvariable');
   //     var_dump($request);
//exit;
        $userEmail = $this->getParameter('userEmail');
        //  echo json_encode(['userId'=>$userId]);
        //echo json_encode($request);


        if (!$this->getEnabled()) {
            return false;
        }

        $restCallType = $this->getRESTRequestType();
        $operator = array_shift($args);

        if ($restCallType === "GET") {
            switch ($operator) {
                case 'test': // Basic test
                    $response = array(
                        "message" => "GET response",
                        "version" => "1.0"
                    );
                    $this->sendJsonResponse($response);
                    break;
                case 'journals':
                    $response =$this->getUserJournals($userEmail, $request);
                    $this->sendJsonResponse($response);
                    break;

                default:
                    $error = " Not a valid request";
                    $this->sendErrorResponse($error);
            }

        }

        if ($restCallType === "POST") {
            $response = array(
                "message" => "POST response",
                "version" => "1.0"
            );
            $this->sendJsonResponse($response);
        }


        if ($restCallType === "PUT") {
            $response = array(
                "message" => "PUT response",
                "version" => "1.0"
            );
            $this->sendJsonResponse($response);
        }

        if ($restCallType === "DELETE") {
            $response = array(
                "message" => "DELETE response",
                "version" => "1.0"
            );
            $this->sendJsonResponse($response);
        }


        return true;
    }


    /**
     * @param $varName
     * @return string
     */
    private function getPOSTPayloadVariable($varName)
    {

        if (isset($_POST[$varName])) {
            return $_POST[$varName];
        }
        return "";
    }

    /**
     * @return string
     */
    private function getRESTRequestType()
    {
        $callType = $_SERVER['REQUEST_METHOD'];
        switch ($callType) {
            case 'PUT':
            case 'DELETE':
            case 'GET':
            case 'POST':
                $result = $callType;
                break;
            default:
                $result = "";
        }
        return $result;
    }

    /**
     * @return array
     */
    private function getParameters()
    {
        return $this->request->_requestVars;
    }

    /**
     * @param $parameter
     * @return string
     */
    private function getParameter($parameter)
    {
        return $this->request->_requestVars[$parameter];
    }

    /**
     * @param array $response
     */
    private function sendJsonResponse($response)
    {
        header("Content-Type: application/json;charset=utf-8");
        http_response_code(200);
        echo json_encode($response);
        return;
    }

    /**
     * Display an error message and exit
     * @param $errorMessage
     */
    public function sendErrorResponse($errorMessage)
    {
        header("HTTP/1.0 500 Internal Server Error");
        http_response_code(500);
        if ($errorMessage != null) echo $errorMessage . PHP_EOL;
        echo "internal server error";
        exit;
    }

    private function getUserJournals($userEmail,$request)
    {
        /** Journal $journal */
        $journal =& $request->getJournal();
        if (!isset($journal)) $this->sendErrorResponse("no journal is available");
        $issueDao =& DAORegistry::getDAO('IssueDAO');
        $journalArray= [
                                'id' => $journal->getId(),
								'name' => $journal->getLocalizedName(),
								'path' => $journal->getPath(),
								'description' => $journal->getLocalizedDescription(),
        ];
        $response = array(
            "journal" => $journalArray,
            "version" => "1.0"
        );
        return $response;
    }

}

?>