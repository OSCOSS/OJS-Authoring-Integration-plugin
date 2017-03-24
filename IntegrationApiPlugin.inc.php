<?php
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.submission.SubmissionDAO');
import('classes.user.UserDAO');
import('classes.user.User');
import('classes.article.AuthorDAO');
import('classes.article.Author');
import('lib.pkp.classes.security.UserGroupAssignment');
import('lib.pkp.classes.security.AuthSourceDAO');
import('lib.pkp.classes.submission.SubmissionDAO');
/**
 * Project OSCOSS
 * University of Bonn
 * User: afshin Sadeghi sadeghi@cs.uni-bonn.de
 * Date: 13/06/16
 * Time: 14:44
 */
class IntegrationApiPlugin extends GenericPlugin {

    /** @var string authoring tool URL address */
    protected $sharedKey;

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
    function getDisplayName() {
        return __('plugins.generic.ojsIntegrationRestApi.displayName');

    }

    /**
     * Get a description of this plugin.
     *
     * @return string
     */
    function getDescription() {
        return __('plugins.generic.ojsIntegrationRestApi.description');

    }


    /**
     * @see Plugin::isSitePlugin()
     */
    function isSitePlugin() {
        return true;
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
                HookRegistry::register('reviewassignmentdao::_insertobject', array($this, 'registerReviewerWebHook'));
                HookRegistry::register('reviewassignmentdao::_deletebyid', array($this, 'removeReviewerWebHook'));
                HookRegistry::register('reviewrounddao::_insertobject', array($this, 'newRevisionWebHook'));

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
    function registerReviewerWebHook($hookName, $args) {

        $reviewAssignment =& $args[0];
        $row =& $args[1];
        $submissionId = $row[0];
        $reviewerId = $row[1];
        $email = $this->getUserEmail($reviewerId);
        $userName = $this->getUserName($reviewerId);
        $round = ($row[4]);
        $docData = $this->getDocData($submissionId, $round);
        $dataArray = ['email' => $email,
            'rev_id' => $docData['rev_id'],
            'user_name' => $userName];
        $url = $docData['base_url'] . '/ojs/reviewer/';

        error_log("MOINMOINAddreviewer: " . $documentId."---". $email, 0);

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
    function removeReviewerWebHook($hookName, $args) {

        $reviewId =& $args[1];
        $email = $this->getUserEmailByReviewID($reviewId);
        $submissionId = $this->getSubmissionIdByReviewID($reviewId);
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        /** @var ReviewAssignment $reviewAssignmentObject */
        $reviewAssignmentObject = $RADao->getById($reviewId);
        $round = $reviewAssignmentObject->getRound();
        $docData = $this->getDocData($submissionId, $round);
        $userName = $this->getUserNameByReviewID($reviewId);
        $dataArray = ['email' => $email,
            'rev_id' => $docData['rev_id'],
            'user_name' => $userName];
        // Then send the email address of reviewer to authoring tool.
        // AT must give review aceess to this article with the submission id.
        $url = $docData['base_url'] . '/ojs/reviewer/del/';
        $this->sendPostRequest($url, $dataArray);
        return false;
    }


    /**
     * Creates New In Editor Article Revision By OJS Editor User
     * @param $hookname
     * @param $args
     */
    function newRevisionWebHook($hookname, $args) {

        $revisionReqArr =& $args[1];
        $submissionId = $revisionReqArr[0];
        $round = $revisionReqArr[2];

        $this->sharedKey = "d5PW586jwefjn!3fv";
        if($round == "1") return;
        // If $submissionId is 0, it is round 0 and no reviewer is assigned yet
        if (is_null($submissionId)) return;
        $authorEmail = $this->getAuthorEmailBySubmissionId($submissionId);
        // If $submissionId is 0, it is round 0 and no reviewer is assigned yet
        if (is_null($authorEmail)) return;
        $userName = $this->getAuthorUserNameBySubmissionId($submissionId);


        $dataArray = [
            'author_email' => $authorEmail,
            'author_user_name' => $userName,
            'key' => $this->sharedKey, //shared key between OJS and Editor software
            'submission_id' => $submissionId,
            'round' => $round];  //editor user for logging in
        // Then send the email address of reviewer to authoring tool.
        // AT must give review access to this article with the submission id
        $docData = $this->getDocData($submissionId, $round-1);
        $url = $docData['base_url'] . '/ojs/newsubmissionrevision/';
        $result = $this->sendPostRequest($url, $dataArray);
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
    private function getUserEmail($userId) {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        return $userDao->getUserEmail($userId);
    }

    /**
     * @param $userId
     * @return string
     */
    private function getUserName($userId) {
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
    private function getUserEmailByReviewID($reviewId) {
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
    private function getUserNameByReviewID($reviewId) {
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
    private function getSubmissionIdByReviewID($reviewId) {
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
    private function sendRequest($requestType, $url, $dataArray) {
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => $requestType,
                'content' => http_build_query($dataArray)
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
     * @param $dataArray
     * @return string
     */
    private function sendPutRequest($url, $dataArray) {
        $result = $this->sendRequest('PUT', $url, $dataArray);
        return $result;
    }

    /**
     * @param $url
     * @param $dataArray
     * @return string
     */
    private function sendPostRequest($url, $dataArray) {
        $result = $this->sendRequest('POST', $url, $dataArray);
        return $result;
    }

    /**
     * @return User/Null
     */
    private function getUserFromSession() {
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
    private function getDocData($submissionId, $round) {
        $submissionDao = Application::getSubmissionDAO();
        /** @var Submission */
        $submission = $submissionDao->getById($submissionId);
        $submissionTitle = $submission->getTitle(AppLocale::getLocale());
        $submissionArrayInString= [];
        $submissionInString = [];
        $matches = explode('"', $submissionTitle);
        $count = count($matches);
        for ($counter = 0; $counter < $count -1 ; $counter++ ) {
            $position = strpos($matches[$counter], "/ojs/revision/");
            if ($position !== FALSE) {
                $urlMatch = explode('/ojs/revision/', $matches[$counter]);
                $baseUrl = $urlMatch[0];
                $revId = $urlMatch[1];
                $afterUrlMatch = explode('>Round', $matches[$counter + 1]);
                if(is_array($afterUrlMatch)){
                    if(count($afterUrlMatch) === 2){
                        $roundMatch = explode('</a>', $afterUrlMatch[1]);
                        $round = $roundMatch[0];
                        $round = str_replace(' ', '', $round);

                    } else {
                        $round = "1";
                    }
                }
                $submissionInString['base_url']= $baseUrl;
                $submissionInString['rev_id']= $revId;
                $submissionInString['round']= $round;
                $submissionArrayInString[]= $submissionInString;
            }
        }
        foreach ($submissionArrayInString as $subInString){
            if($subInString['round'] == $round){
                $docData = $subInString;
            }
        }
        return $docData;
    }

    /**
     * @param $submissionId
     * @return mixed
     */
    private function getReviewerEmailBySubmissionId($submissionId) {
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
    private function getReviewerUserNameBySubmissionId($submissionId) {
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
    private function getAuthorEmailBySubmissionId($submissionId) {
        error_log("submissionId: ". $submissionId,0);

        /** @var AuthorDao $authorDao */
        $authorDao = DAORegistry::getDAO('AuthorDAO');
        error_log("AuthorDao: ". var_dump($authorDao),0);
        $authors = $authorDao->getBySubmissionId($submissionId);
        $email = "";
        foreach ($authors as $author){
            /** @var Author $author */
            $email = $author->getEmail(); //get the first author
            break;
        }
        error_log("author_email: ". $email,0);
        //$email = $author->getEmail();
        return $email;
    }

    /**
     * @param $submissionId
     * @return string
     */
    private function getAuthorUserNameBySubmissionId($submissionId) {
        /** @var AuthorDao $authorDao */
        $authorDao = DAORegistry::getDAO('AuthorDAO');
        $authors = $authorDao->getBySubmissionId($submissionId);
        $userName = "";
        foreach ($authors as $author){
            /** @var Author $author */
            $userName = $author->getFullName();
            break;
        }
        return $userName;
    }

}
