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
    /** string API version */
    private $APIVersion;

    public function RestApiGatewayPlugin($parentPluginName)
    {
        parent::GatewayPlugin();
        $this->parentPluginName = $parentPluginName;
        $this->APIVersion = "1.0";
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
     * @see Plugin::isSitePlugin()
     */
    function isSitePlugin()
    {
        return true;
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
                        "version" => $this->APIVersion
                    );
                    $this->sendJsonResponse($response);
                    break;
                case 'journals':
                    $response = $this->getJournals();
                    // possible extension  get journals by email of a author. Currently, it returns all jounals
                    // sample:
                    //$userEmail = $this->getParameter('userEmail');
                    // echo json_encode(['userId'=>$userId]);
                    //$response = $this->getUserJournals($userEmail);

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
                "version" => $this->APIVersion
            );
            $this->sendJsonResponse($response);
        }


        if ($restCallType === "PUT") {
            $response = array(
                "message" => "PUT response",
                "version" => $this->APIVersion
            );
            $this->sendJsonResponse($response);
        }

        if ($restCallType === "DELETE") {
            $response = array(
                "message" => "DELETE response",
                "version" => $this->APIVersion
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
     * @return string
     */
    public function sendErrorResponse($errorMessage)
    {
        header("HTTP/1.0 500 Internal Server Error");
        http_response_code(500);
        if ($errorMessage != null) echo $errorMessage . PHP_EOL;
        $response = [

            "error" => "internal server error",
            "errorMessage" => $errorMessage,
            "code" => "500"
        ];
        echo json_encode($response);
        return;
    }

    private function getJournals()
    {
        /** Journal $journal */
        //$journal =& $request->getJournal();
        $journalArray = [];
        $journalDao = DAORegistry::getDAO('JournalDAO');
        /* @var $journalDao JournalDAO */
        $journals = $journalDao->getAll();
        $journalsCount = $journals->getCount();
        $journal = null;
        if ($journalsCount === 1) {
            // Return the unique journal.
            $journal = $journals->next();
            $journals[] = $journal;
        } else {
            $journals = $journals->toAssociativeArray();
        }
        foreach ($journals as $journal) {
            $journalArray[] = [
                'id' => $journal->getId(),
                'name' => $journal->getLocalizedName(),
                'contactEmail' => $journal->_data['contactEmail'],
                'contactName' => $journal->_data['contactName'],
                //'autherInfo'=> $journal->_data['authorInformation'],
                'path' => $journal->getPath(),
                'description' => $journal->getLocalizedDescription(),
            ];
        }

        if (!isset($journal)) $this->sendErrorResponse("no journal is available");

        $response = array(
            "journals" => $journalArray,
            "version" => $this->APIVersion
        );
        return $response;
    }

}

?>