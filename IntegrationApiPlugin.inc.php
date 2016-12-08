<?php
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.submission.SubmissionDAO');

/**
 * Project OSCOSS
 * University of Bonn
 * User: afshin Sadeghi sadeghi@cs.uni-bonn.de
 * Date: 13/06/16
 * Time: 14:44
 */
class IntegrationApiPlugin extends GenericPlugin
{

    /** @var string authoring tool URL address */
    private $atURL;

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
        //error_log("loggingRegister:" . $reviewAssignment, 0);
        $email = $this->getUserEmail($row[1]);
        //error_log("email: " . $email, 0);
        //array_keys(submitionID" . $row[0], 0);
        $userName = $this->getUserName($row[1]);
        $documentId = $this->getDocumentIdFromSubmissonId($row[0]);
        //error_log("documentId ->>>>>>>>>>>>>" . $documentId, 0);
        $dataArray = ['email' => $email,
            'doc_id' => $documentId,
            'user_name' => $userName];
        $this->atURL = 'http://localhost:8100';
        $url = $this->atURL . '/document/reviewer/';
        //then send the email address of reviewer to AT.
        // Authoring tool must give review access to this article with the submission id
        $this->sendPostRequest($url, $dataArray);
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
        #error_log("loggingRemoveReviewer:" . $reviewAssignment, 0);
        #error_log($reviewId, 0);
        $email = $this->getUserEmailByReviewID($reviewId);
        $submissionId = $this->getSubmissionIdByReviewID($reviewId);
        $documentId = $this->getDocumentIdFromSubmissonId($submissionId);
        $userName = $this->getUserNameByReviewID($reviewId);
        //error_log("reviewer email: " . $email);
        //error_log("SubmissionId: " . $submissionId);
        //error_log("documentId: " . $documentId);
        $dataArray = ['email' => $email,
            'doc_id' => $documentId,
            'user_name' => $userName];
        //Then send the email address of reviewer to authoring tool.
        // AT must give review aceess to this article with the submission id
        $this->atURL = 'http://localhost:8100';
        $url = $this->atURL . '/document/delReviewer/';
        $this->sendPostRequest($url, $dataArray);
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
     * @param $userId
     * @return string
     */
    private function getUserName($userId)
    {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var User $user  */
        $user = $userDao->getById($userId);
        return $user->getUsername($userId);
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
     * @return mixed
     */
    private function getUserNameByReviewID($reviewId)
    {
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $RADao->getById($reviewId);
        /** @var ReviewAssignment $reviewAssignment */
        $userId = $reviewAssignment->getReviewerId();
        return $this->getUserName($userId);
    }

    /**
     * @param $reviewId
     * @return int
     */
    private function getSubmissionIdByReviewID($reviewId)
    {
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
        /**
        error_log("sending put request: ", 0);
        error_log($url, 0);
        foreach ($data_array as $a => $b) {
            error_log($a . '--->' . $b, 0);
        }*/
        $result = $this->sendRequest('PUT', $url, $data_array);
        return $result;
    }

    /**
     * @param $url
     * @param $data_array
     * @return string
     */
    private function sendPostRequest($url, $data_array)
    {
        /**
        error_log("sending post request: ", 0);
        error_log($url, 0);
        foreach ($data_array as $a => $b) {
            error_log($a . '--->' . $b, 0);
        }
         * */
        $result = $this->sendRequest('POST', $url, $data_array);
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

    /**
     * @param $submissionId
     * @return mixed
     */
    private function getDocumentIdFromSubmissonId($submissionId)
    {
        $submissionDao = Application::getSubmissionDAO();
        /** @var Submission */
        $submission = $submissionDao->getById($submissionId);
        $submissionTitle = $submission->getTitle(AppLocale::getLocale());
        $matches = explode('"', $submissionTitle);

        $matches = explode('document/', $matches[1]);
        $documentId = $matches[1];
        return $documentId;
    }

}