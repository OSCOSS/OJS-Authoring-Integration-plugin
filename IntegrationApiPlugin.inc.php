<?php
import('lib.pkp.classes.plugins.GenericPlugin');

/**
 * Project OSCOSS
 * University of Bonn
 * User: afshin Sadeghi sadeghi@cs.uni-bonn.de
 * Date: 13/06/16
 * Time: 14:44
 */
class IntegrationApiPlugin extends GenericPlugin
{

    private $fwURL;
    /**
     * Get the name of the settings file to be installed on new context
     * creation.
     * @return string
     */
    function getContextSpecificPluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Override the builtin to get the correct template path.
     * @return string
     */
    function getTemplatePath()
    {
        return parent::getTemplatePath() . 'templates/';
    }

    /**
     * Get the display name for this plugin.
     *
     * @return string
     */
    function getDisplayName()
    {
        return __('plugins.generic.ojsIntegrationRestApi.displayName');

    }

    /**
     * Get a description of this plugin.
     *
     * @return string
     */
    function getDescription()
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
     * @param $category
     * @param $path
     * @return bool
     */
    function register($category, $path)
    {
        $this->fwURL = 'http://localhost:8100';

        if (parent::register($category, $path)) {
            if ($this->getEnabled()) {
                HookRegistry::register('PluginRegistry::loadCategory', array($this, 'callbackLoadCategory'));
                HookRegistry::register('reviewassignmentdao::_insertobject', array($this, 'registerReviewerWeBHook'));
                HookRegistry::register('reviewassignmentdao::_deletebyid', array($this, 'removeReviewerWeBHook'));
                // HookRegistry::register ('TemplateManager::display', array($this, 'editReviewerTitle'));
            }
            return true;
        }
        return false;
    }


    /**
     * @param $hookName
     * @param $args
     * @return bool
     */
    function registerReviewerWeBHook($hookName, $args)
    {

        $reviewAssignment =& $args[0];
        $row =& $args[1];

        error_log("loggingRegister:" . $reviewAssignment, 0);
        $email = $this->getUserEmail($row[1]);
        error_log("email: " . $email, 0);
        error_log("submitionID" . $row[0], 0);
        
        $dataArray = ['email' => $email,
        'doc_submit_id' =>$row[0] ];

        $url = $this->fwURL.'/reviewer' ;
        //then send the email address of reviewer to FW.
        // FW must give review aceess to this article with the submission id
        $this->sendPostRequest($url, $dataArray );
        return false;

    }

    /**
     * @param $hookName
     * @param $args
     * @return bool
     */
    function removeReviewerWeBHook($hookName, $args)
    {

        $reviewAssignment =& $args[0];
        $reviewId =& $args[1];

        error_log("loggingRemoveReviewer:" . $reviewAssignment, 0);
        error_log($reviewId, 0);
        $email = $this->getUserEmailByReviewID($reviewId);
        $submissionId = $this->getSubmissionIdByReviewID($reviewId);

        error_log("reviewer email: " . $email);
        error_log("SubmissionId: " . $submissionId);


        $dataArray = ['email' => $email,
            'doc_submit_id' =>$submissionId];
        //Then send the email address of reviewer to FW.
        // FW must give review aceess to this article with the submission id
        $url = $this->fwURL.'/reviewer' ;
        $this->sendPutRequest($url,$dataArray );
        return false;
    }

    /**
     * @param $hookName string
     * @param $args array
     * @return bool
     **/
    function callbackLoadCategory($hookName, $args)
    {
        $category =& $args[0];
        $plugins =& $args[1];
        switch ($category) {
            case 'gateways':
                $this->import('RestApiGatewayPlugin');
                $gatewayPlugin = new RestApiGatewayPlugin($this->getName());
                $plugins[$gatewayPlugin->getSeq()][$gatewayPlugin->getPluginPath()] = $gatewayPlugin;
                break;
        }
        return false;
    }


    /**
     * @param $userId
     * @return string
     */
    private function getUserEmail($userId)
    {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        return $userDao->getUserEmail($userId);
    }

    /**
     * @param $reviewId
     * @return mixed
     */
    private function getUserEmailByReviewID($reviewId)
    {
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $RADao->getById($reviewId);
        /** @var ReviewAssignment $reviewAssignment */
        $userId = $reviewAssignment->getReviewerId();
        return $userDao->getUserEmail($userId);
    }

    /**
     * @param $reviewId
     * @return int
     */
    private function getSubmissionIdByReviewID($reviewId)
    {
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $RADao->getById($reviewId);
        /** @var ReviewAssignment $reviewAssignment */
        $submissionId = $reviewAssignment->getSubmissionId();
        return $submissionId;
    }

    /**
     * @param $requestType
     * @param $url
     * @param $data_array
     * @return string
     */
    private function sendRequest($requestType, $url, $data_array)
    {
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => $requestType,
                'content' => http_build_query($data_array)
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) { /* Handle error */
            echo $result;
        }
        return $result;
    }


    /**
     * @param $url
     * @param $data_array
     * @return string
     */
    private function sendPutRequest($url, $data_array)
    {
        $result = $this->sendRequest('PUT',$url, $data_array );
        return $result;
    }

    /**
     * @param $url
     * @param $data_array
     * @return string
     */
    private function sendPostRequest($url, $data_array)
    {
       $result = $this->sendRequest('POST',$url, $data_array );
        return $result;
    }

    /**
     * @return User/Null
     */
    private function getUserFromSession()
    {
        $sessionManager = SessionManager::getManager();
        $userSession = $sessionManager->getUserSession();
        $user = $userSession->getUser();
        return $user;
    }

}