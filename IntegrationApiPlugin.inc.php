<?php
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.submission.SubmissionDAO');
import('classes.user.UserDAO');
import('classes.user.User');
import('lib.pkp.classes.security.AuthSourceDAO');
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
    protected $sharedKey;

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
                HookRegistry::register('reviewrounddao::_insertobject', array($this, 'newRevisionWeBHook'));

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
        //error_log("MOINMOINloggingRegister:" . $reviewAssignment, 0);
        $submissionId = $row[0];
        $reviewerId = $row[1];
        $email = $this->getUserEmail($reviewerId);
        //error_log("MOINMOINemail: " . $email, 0);
        //array_keys(submitionID" . $row[0], 0);
        $userName = $this->getUserName($reviewerId);
        $round = ($row[4]);
        $documentId = $this->getDocumentIdFromSubmissionId($submissionId, $round);
        //error_log("MOINMOINdocumentId ->>>>>>>>>>>>>" . $documentId, 0);
        $dataArray = ['email' => $email,
            'doc_id' => $documentId,
            'user_name' => $userName];
        $this->atURL = 'http://localhost:8100';
        $url = $this->atURL . '/document/reviewer/';
        // then send the email address of reviewer to AT.
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

        //$reviewAssignment =& $args[0];
        $reviewId =& $args[1];
        #error_log("loggingRemoveReviewer:" . $reviewAssignment, 0);
        #error_log($reviewId, 0);
        $email = $this->getUserEmailByReviewID($reviewId);
        $submissionId = $this->getSubmissionIdByReviewID($reviewId);
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        /** @var ReviewAssignment $reviewAssignmentObject */
        $reviewAssignmentObject = $RADao->getById($reviewId);
        $round = $reviewAssignmentObject->getRound();
        $documentId = $this->getDocumentIdFromSubmissionId($submissionId, $round);
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
     * Creates New In Editor Article Revision By OJS Editor User
     * @param $hookname
     * @param $args
     */
    function newRevisionWeBHook($hookname, $args)
    {


        $revisionReqArr =& $args[1];
        $submissionId = $revisionReqArr[0];
        //$stage_id = $revisionReqArr[1];
        $round = $revisionReqArr[2];
        //$status = $revisionReqArr[3];
        //error_log("newRevisionWeBHook1" . $submissionId, 0);  //example: 74, 3, 5, 6
        //error_log("newRevisionWeBHook2". $stage_id,0);
        //error_log("newRevisionWeBHook3". $round,0);
        //error_log("newRevisionWeBHook4". $status,0);

        $this->sharedKey = "d5PW586jwefjn!3fv";
        error_log("MOINMOIN:" . var_export($revisionReqArr, true), 0);

        if (is_null($submissionId)) return;   //it means its round 0 and no reviewer is assigned yet
        $authorEmail = $this->getAuthorEmailBySubmissionId($submissionId);
        if (is_null($authorEmail)) return;   //it means its round 0 and no reviewer is assigned yet
        $userName = $this->getAuthorUserNameBySubmissionId($submissionId);


        $dataArray = [
            'reviewer_email' => $authorEmail,
            'user_name' => $userName,
            'key' => $this->sharedKey, //shared key between OJS and Editor software
            'submission_id' => $submissionId,
            'round' => $round];  //editor user for logging in
        //Then send the email address of reviewer to authoring tool.
        // AT must give review aceess to this article with the submission id
        $this->atURL = 'http://localhost:8100';
        $url = $this->atURL . '/document/newsubmissionrevision/';
        $result = $this->sendPostRequest($url, $dataArray);
        //error_log("MOINMOIN:" . var_export($dataArray, true), 0);
        //error_log("newRevisionWeBHook_result" . $result, 0);

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
        /** @var ReviewAssignment $reviewAssignment */
        $user = $userDao->getById($userId);
        /** @var User $user */
        return $user->getUsername($userId);
    }

    /**
     * @param $reviewId
     * @return mixed
     */
    private function getUserEmailByReviewID($reviewId)
    {
        /** @var UserDAO $userDao **/
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
        $reviewAssignmentArray = $RADao->getById($reviewId);
        if (is_array($reviewAssignmentArray)) {
            $reviewAssignment = $reviewAssignmentArray[0];
        } else {
            $reviewAssignment = $reviewAssignmentArray;
        }
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
        $reviewAssignmentArray = $RADao->getById($reviewId);
        if (is_array($reviewAssignmentArray)) {
            $reviewAssignment = $reviewAssignmentArray[0];
        } else {
            $reviewAssignment = $reviewAssignmentArray;
        }
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
         * error_log("sending put request: ", 0);
         * error_log($url, 0);
         * foreach ($data_array as $a => $b) {
         * error_log($a . '--->' . $b, 0);
         * }*/
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
         * error_log("sending post request: ", 0);
         * error_log($url, 0);
         * foreach ($data_array as $a => $b) {
         * error_log($a . '--->' . $b, 0);
         * }
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
     * @param $round
     * @return mixed
     */
    private function getDocumentIdFromSubmissionId($submissionId, $round)
    {
        $submissionDao = Application::getSubmissionDAO();
        /** @var Submission */
        $submission = $submissionDao->getById($submissionId);
        $submissionTitle = $submission->getTitle(AppLocale::getLocale());
        $documentId = 0;
        $submissionArrayInString= [];
        $submissionInString= [];
        $matches = explode('"', $submissionTitle);
        $count = count($matches);
        for ($counter = 0; $counter < $count -1 ; $counter++ ) {
            $position = strpos($matches[$counter], "document/");
            if ($position !== FALSE) {
                $match1 = explode('document/', $matches[$counter]);
                $match1 = $match1[1];
                $match2 = explode('>Round', $matches[$counter + 1]);
                if(is_array($match2)){
                    if(count($match2) ===2){
                        $match2 = explode('</a>', $match2[1]);
                        $match2 = $match2[0];
                        $match2 = str_replace(' ', '', $match2);

                    }else{
                        $match2 = "1";
                    }
                }
                $submissionInString['doc_id']= $match1;
                $submissionInString['round']= $match2;
                $submissionArrayInString[]= $submissionInString;
            }
        }
        foreach ($submissionArrayInString as $subInString){
            if($subInString['round'] === $round){
                $documentId =  $subInString['doc_id'];
            }
        }
        return $documentId;
    }

    /**
     * @param $submissionId
     * @return mixed
     */
    private function getReviewerEmailBySubmissionId($submissionId)
    {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignmentArray = $RADao->getBySubmissionId($submissionId);
        $reviewAssignment = NULL;
        if (is_array($reviewAssignmentArray)) {
            foreach ($reviewAssignmentArray as $reviewAssignmentElement) {
                $reviewAssignment = $reviewAssignmentElement;
            }
        } else {
            $reviewAssignment = $reviewAssignmentArray;
        }

        if (is_null($reviewAssignment)) { //it means its round 0 and no reviewer is assigned yet
            return NULL;
        }
        /** @var ReviewAssignment $reviewAssignment */
        $userId = $reviewAssignment->getReviewerId();
        return $userDao->getUserEmail($userId);
    }

    /**
     * @param $submissionId
     * @return string
     */
    private function getReviewerUserNameBySubmissionId($submissionId)
    {
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignmentArray = $RADao->getBySubmissionId($submissionId);
        $reviewAssignment = NULL;
        if (is_array($reviewAssignmentArray)) {
            foreach ($reviewAssignmentArray as $reviewAssignmentElement)
                $reviewAssignment = $reviewAssignmentElement;
        } else {
            $reviewAssignment = $reviewAssignmentArray;
        }
        if (is_null($reviewAssignment)) { //it means its round 0 and no reivewer is assigned yet
            return NULL;
        }
        /** @var ReviewAssignment $reviewAssignment */
        $userId = $reviewAssignment->getReviewerId();
        return $this->getUserName($userId);
    }


    /**
     * @param $submissionId
     * @return mixed
     */
    private function getAuthorEmailBySubmissionId($submissionId)
    {
        error_log("submissionId: ". $submissionId,0);

        /** @var AuthorDao $authorDao */
        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var Author $author */
        $author = $authorDao->getBySubmissionId($submissionId);
        error_log("author: ". var_dump($author),0);
        $email = $author->getEmail();
        return $email;
    }

    /**
     * @param $submissionId
     * @return string
     */
    private function getAuthorUserNameBySubmissionId($submissionId)
    {
        /** @var AuthorDao $authorDao */
        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var Author $author */
        $author = $authorDao->getBySubmissionId($submissionId);
        $userId = $author->getId();
        return $userId;
    }

}