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
import('lib.pkp.classes.workflow.WorkflowStageDAO');
import('classes.user.UserDAO');
import('classes.user.User');
import('classes.security.RoleDAO');
import('lib.pkp.classes.security.Role');
import('lib.pkp.classes.security.UserGroupAssignment');
import('lib.pkp.classes.security.AuthSourceDAO');
import('lib.pkp.classes.submission.SubmissionDAO');
import('classes.article.AuthorDAO');
import('classes.article.Author');

class RestApiGatewayPlugin extends GatewayPlugin
{


    /** @var string Name of parent plugin */
    public $parentPluginName;
    /** string API version */
    private $APIVersion;
    /** @var string */
    private $defaultLocale;
    /** @var string authoring tool URL address */
    private $atURL;
    /** @var string shared key to send request to AT */
    private $sharedKey;
    /**  @var string current plugin URL */
    private $pluginURL;
    /** @var string */
    private $OJSURL;

    public function RestApiGatewayPlugin($parentPluginName)
    {
        parent::__construct();
        $this->parentPluginName = $parentPluginName;
        $this->APIVersion = "1.0";
        $this->defaultLocale = AppLocale::getLocale();
        //todo: this should be get from user in plugin installation time
        $this->atURL = 'http://localhost:8100';
        $this->OJSURL = 'http://localhost:8000';//todo:get it from session
        $this->pluginURL = $this->OJSURL . '/index.php/index/gateway/plugin/RestApiGatewayPlugin';
        $this->sharedKey = "d5PW586jwefjn!3fv";
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

        try {
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
                        //sample address:
                        // http://localhost:8000/index.php/index/gateway/plugin/RestApiGatewayPlugin/journals
                        $response = $this->getJournals();
                        // possible extension  get journals by email of a author. Currently, it returns all jounals
                        // sample:
                        //$userEmail = $this->getParameter('userEmail');
                        // echo json_encode(['userId'=>$userId]);
                        //$response = $this->getUserJournals($userEmail);

                        $this->sendJsonResponse($response);
                        break;
                    case 'documentReview':
                        // Here I convert Local command in form of GET from browser to Remote Host Command by Server in for of POST
                        $article_url = $_GET['article_url'];
                        $status = $this->loginAuthoringTool($article_url);
                        #echo $status;
                        /*if (!$status) {
                            $response = array(
                                "message" => "error recognizing the reviewer.",
                                "version" => $this->APIVersion
                            );
                            $this->sendJsonResponse($response);
                        }*/
                        //$this->redirect($redirect_url);
                        break;
                    default:
                        $error = " OJS Integration REST Plugin: Not a valid GET request";
                        $this->sendErrorResponse($error);
                }

            }

            if ($restCallType === "POST") {
                switch ($operator) {
                    case 'test': // Basic test
                        $response = array(
                            "message" => "POST test response",
                            "version" => $this->APIVersion
                        );
                        $this->sendJsonResponse($response);

                        break;
                    case 'articles':
                        // in case author submits and article
                        $resultArray = $this->saveArticleWithAuthor();
                        $response = array(
                            "submission_id" => $resultArray["submissionId"],
                            "journal_id" => $resultArray["journalId"],
                            "user_id" => $resultArray["userId"],
                            "version" => $this->APIVersion
                        );
                        $this->sendJsonResponse($response);
                        break;
                    case 'articleReviews':
                        // in case a reviewer submit the article review
                        $resultArray = $this->saveArticleReview();
                        $response = array(
                            "journal_id" => $resultArray["journalId"],
                            "user_id" => $resultArray["userId"],
                            "version" => $this->APIVersion
                        );
                        $this->sendJsonResponse($response);
                        break;
                    //  case 'authors':
                    //     $id = $this->saveAuthor();
                    //      $response = array(
                    //        "authorId" => "$id",
                    //         "version" => $this->APIVersion
                    //       );
                    //      $this->sendJsonResponse($response);
                    //     break;

                    default:
                        $error = " Not a valid request";
                        $this->sendErrorResponse($error);
                }
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
        } catch
        (Exception $e) {
            $this->sendErrorResponse($e->getMessage());
            return true;
        }
    }


    /**
     * @param $varName
     * @return string
     */
    private function getPOSTPayloadVariable($varName)
    {
        error_log("logging:" . implode($_SERVER, ","));
        //todo: find the cors_token from header , check of is in $_SERVER or other places.
        //   error_log("loggingCRF:". $_SERVER["csrf_token"],0);  //csrfToken
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
        $response = [

            "error" => "internal server error",
            "errorMessage" => $errorMessage,
            "code" => "500"
        ];
        echo json_encode($response);
        return;
    }

    /**
     * @return array
     */
    private function getJournals()
    {
        $journalArray = [];
        $journalDao = DAORegistry::getDAO('JournalDAO');
        /* @var $journalDao JournalDAO */
        $journalsObject = $journalDao->getAll();
        //$journalsCount = $journalsObject->getCount();
        /** Journal $journal */
        $journal = null;
        $journals = $journalsObject->toAssociativeArray();
        foreach ($journals as $journal) {
            $journalArray[] = [
                'id' => $journal->getId(),
                'name' => $journal->getLocalizedName(),
                'contact_email' => $journal->_data['contactEmail'],
                'contact_name' => $journal->_data['contactName'],
                'url_relative_path' => $journal->getPath(),
                'description' => $journal->getLocalizedDescription(),
            ];
        }

        if (!isset($journal)) $this->sendErrorResponse("No journal is available");
        $response = array(
            "journals" => $journalArray,
            "version" => $this->APIVersion
        );
        return $response;
    }

    /**
     * @param Submission $submission
     * @return Submission
     */
    private function setArticleSubmissionVariablesFromPOSTPayload($submission)
    {
        $contextId = $this->getPOSTPayloadVariable("journal_id");
        $filename = $this->getPOSTPayloadVariable("file_name");
        $title = $this->getPOSTPayloadVariable("title");

        //version numbers become clickable
        // update the link part is left
        //Fw side and test is left


        /** Submission */
        $submission->setContextId($contextId);
        $submission->setDateSubmitted(Core::getCurrentDate());

        $submission->setLocale($this->defaultLocale);
        $submission->setSubject($title, $this->defaultLocale);
        //$submission->setFileName($filename, $this->defaultLocale);
        // setting data as article_url did not work,
        // instead we use the file_name to store it and the title will keep it.
        //$submission->setData("article_url", $articleUrl);
        return $submission;
    }

    /**
     * Takes an article submission from authors
     * @return array
     */
    private function saveArticleWithAuthor()
    {

        $submission_id = $this->getPOSTPayloadVariable("submission_id");
        $version_id = $this->getPOSTPayloadVariable("version_id");
        //error_log("MOINMOIN4:" . var_export([$submission_id, $version_id], true), 0);
        $journalId = 0;
        $userId = 0;
        if ($submission_id !== "" && $version_id !== "") {

            $submissionId = $submission_id;
            $this->updateArticleSubmissionBySecondArticleSubmit($submissionId, $version_id);
        } else {
            $submissionId = $this->saveNewArticleSubmission();
            $emailAddress = $this->getPOSTPayloadVariable("email");
            $firstName = $this->getPOSTPayloadVariable("first_name");
            $lastName = $this->getPOSTPayloadVariable("last_name");
            $journalId = $this->getPOSTPayloadVariable("journal_id");

            $user = $this->getUserForAuthoring($emailAddress, $journalId, $firstName, $lastName);
            $userId = $user->getId();
            //check if author exist in db
            $authorId = $this->saveAuthor($submissionId, $journalId, $emailAddress, $firstName, $lastName);

            // Assign the user author to the stage
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $stageAssignmentDao->build($submissionId, $this->getAuthorUserGroupId($journalId), $user->getId());

        }


        $resultArray = array(
            "journalId" => $journalId,
            "submissionId" => $submissionId,
            "userId" => $userId,
        );
        return $resultArray;
    }

    /**
     * @param $articleUrl
     * @param $title
     * @return string
     */
    private function makeSingleSignOnURL($articleUrl, $title)
    {
        $single_sign_on_Url = $this->pluginURL . '/documentReview?article_url=' . $articleUrl;
        $linkToOJS = '<a href="' . $single_sign_on_Url . '">Open : ' . $title . '</a>';
        return $linkToOJS;
    }

    /**
     * @param $oldTitle
     * @param $version_num
     * @return string
     */
    private function makeSingleSignOnURLForRevision($oldTitle, $version_num)
    {
        //error_log("MOINMOIN1:" . var_export([$oldTitle,$version_num], true), 0);
        $articleUrl = $this->getPOSTPayloadVariable("article_url");
        $oldLinkToOJS = $oldTitle;
        $single_sign_on_Url = $this->pluginURL . '/documentReview?article_url=' . $articleUrl;
        //error_log("MOINMOIN2:" . $single_sign_on_Url, 0);
        $round = ($version_num +1 )/2;
        $linkToOJS = $oldLinkToOJS . ' &nbsp;<a href="' . $single_sign_on_Url . '">Round ' . $round . '</a>';
        //error_log("MOINMOIN3:" . $linkToOJS, 0);
        return $linkToOJS;
    }

    /**
     * @return mixed
     */
    private function saveNewArticleSubmission()
    {
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->newDataObject();
        $submission->setStatus(STATUS_QUEUED);
        $submission->setSubmissionProgress(0);

        $articleUrl = $this->getPOSTPayloadVariable("article_url");
        $title = $this->getPOSTPayloadVariable("title");

        $submission = $this->setArticleSubmissionVariablesFromPOSTPayload($submission);
        $linkToOJS = $this->makeSingleSignOnURL($articleUrl, $title);
        $submission->setTitle($linkToOJS, $this->defaultLocale);
        $submission->setCleanTitle($linkToOJS, $this->defaultLocale);
        $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO');
        //  $submission->setStageId(WorkflowStageDAO::getIdFromPath($node->getAttribute('stage')));
        $submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);  // WORKFLOW_STAGE_ID_SUBMISSION value is equal to 1 in our first journal test
        //$submission->setCopyrightNotice($this->context->getLocalizedSetting('copyrightNotice'), $this->getData('locale'));

        $journalId = $this->getPOSTPayloadVariable("journal_id");

        // Sections are different parts of a journal,
        // Later we can extend the api to select which section to submit, the default section is articles.
        // https://pkp.sfu.ca/ojs/docs/userguide/2.3.3/journalManagementJournalSections.html
        $sectionDao = Application::getSectionDAO();
        $section = $sectionDao->getByTitle("Articles", $journalId, $this->defaultLocale);
        if ($section !== NULL) {
            $sectionId = $section->getId();
        } else {
            $sectionId = 1;
        }
        $submission->setData("sectionId", $sectionId);
        // Insert the submission
        $submissionId = $submissionDao->insertObject($submission);
        return $submissionId;
    }


    /**
     * @param $submissionId
     * @param $version_num
     * @return Submission
     * @throws Exception
     */
    private function updateArticleSubmissionBySecondArticleSubmit($submissionId, $version_num)
    {
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($submissionId);
        //error_log("MOINMOIN0:" . var_export([$submissionId,$version_num, $submission], true), 0);

        if ($submission === NUll || $submission === "") {

            throw new Exception("Error: no submission with given submissionId $submissionId exists");
        }
        $singleSignOnURL = $this->makeSingleSignOnURLForRevision($submission->getTitle($this->defaultLocale), $version_num);
        /** @var ArticleDAO $submissionDao * */
        $submissionDao->updateSetting($submissionId, 'title', [$this->defaultLocale => $singleSignOnURL], 'string', True);
        $submissionDao->updateSetting($submissionId, 'cleanTitle', [$this->defaultLocale => $singleSignOnURL], 'string', True);
    }

    /**
     * Takes an article review submission from reviewers
     * @return array
     * @throws Exception
     */
    private function saveArticleReview()
    {
        $journalId = $this->getPOSTPayloadVariable("journal_id"); //same as $contextId
        if ($journalId === NULL || $journalId === "") {
            throw new Exception("Error: journal_id is not set or is empty in the header");
        }

        $submissionId = $this->getPOSTPayloadVariable("submission_id");
        if ($submissionId === NULL || $submissionId === "") {
            throw new Exception("Error: submissionId is not set or is empty in the header");
        }

        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($submissionId);
        if ($submission === NUll || $submission === "") {
            throw new Exception("Error: no submission with given submissionId $submissionId exists");
        }
        $submission->setStageId(WORKFLOW_STAGE_ID_INTERNAL_REVIEW);  // WORKFLOW_STAGE_ID_INTERNAL_REVIEW value is equal to 2 from interface iPKPApplicationInfoProvider

        $emailAddress = $this->getPOSTPayloadVariable("email");
        if ($emailAddress === NULL || $emailAddress === "") {
            throw new Exception("Error: $emailAddress is not set in the header");
        }


        /** @var User */
        $user = $this->getUserForReviewing($emailAddress, $journalId);
        $userId = $user->getId();

        if ($user === NULL || $user === "") {
            throw new Exception("Error: There is not user with this email and journal $emailAddress is not set in the header");
        }

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');

        $reviewAssignmentsArray = $reviewAssignmentDao->getBySubmissionId($submission->getId());

        $reviewAssignment = NULL;
        foreach ($reviewAssignmentsArray as $reviewAssignmentObject) {
            /** @var $reviewAssignmentObject  ReviewAssignment */
            if ($reviewAssignmentObject->getReviewerId() === $userId) {
                $reviewAssignment = $reviewAssignmentObject;
            }
        }
        # error_log(print_r( $reviewAssignmentsArray));

        if ($reviewAssignment === NULL) {
            throw new Exception('Error: user with email address ' . $emailAddress . ' has not right to review this article');
        }


        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
        /* @var $reviewerSubmissionDao ReviewerSubmissionDAO */
        $reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewAssignment->getId());

        $editorMessageCommentText = $this->getPOSTPayloadVariable("editor_message");
        $editorAndAuthorMessageCommentText = $this->getPOSTPayloadVariable("message_editor_author");
        $this->saveCommentForEditor($editorMessageCommentText, $reviewAssignment);
        $this->saveCommentForEditorAndAuthor($editorAndAuthorMessageCommentText, $reviewAssignment);


        $this->updateReviewStepAndSaveSubmission($reviewerSubmission);
        $resultArray = array(
            "journalId" => $journalId,
            "submissionId" => $submissionId,
            "userId" => $userId,
        );
        return $resultArray;

    }

    /**
     * Set the review step of the submission to the given
     * value if it is not already set to a higher value. Then
     * update the given reviewer submission.
     * @param $reviewerSubmission ReviewerSubmission
     */
    function updateReviewStepAndSaveSubmission(ReviewerSubmission &$reviewerSubmission)
    {
        //review step
        $submissionCompleteStep = 3;
        $nextStep = $submissionCompleteStep;
        if ($reviewerSubmission->getStep() < $nextStep) {
            $reviewerSubmission->setStep($nextStep);
        }
        $reviewerSubmission->setRecommendation("See Comments");
        // Save the reviewer submission.
        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
        /* @var $reviewerSubmissionDao ReviewerSubmissionDAO */
        $reviewerSubmissionDao->updateReviewerSubmission($reviewerSubmission);
    }


    /**
     * @param $articleId
     * @param $journalId
     * @param $emailAddress
     * @param $firstName
     * @param $lastName
     * @return null
     */
    private function saveAuthor($articleId, $journalId, $emailAddress, $firstName, $lastName)
    {
        // Set user to initial author

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var Author $author */
        $author = $authorDao->newDataObject();
        $author->setFirstName($firstName);
        $author->setMiddleName("");
        $author->setLastName($lastName);
        $author->setAffiliation($this->getPOSTPayloadVariable("affiliation"), $this->defaultLocale);
        $author->setCountry($this->getPOSTPayloadVariable("country"));
        $author->setEmail($emailAddress);
        $author->setUrl($this->getPOSTPayloadVariable("author_url"));
        $author->setBiography($this->getPOSTPayloadVariable("biography"), $this->defaultLocale);
        $author->setPrimaryContact(1);
        $author->setIncludeInBrowse(1);

        $authorUserGroup = $this->getAuthorUserGroupId($journalId);
        $author->setUserGroupId($authorUserGroup);
        $author->setSubmissionId($articleId);

        $authorId = $authorDao->insertObject($author);
        $author->setId($authorId);
        return $authorId;
    }


    /**
     * @param $journalId
     * @return mixed
     */
    private function getAuthorUserGroupId($journalId)
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        /** /classes/security/UserGroup  */
        $authorUserGroup = $userGroupDao->getDefaultByRoleId($journalId, ROLE_ID_AUTHOR);
        if ($authorUserGroup === FALSE) {
            return ROLE_ID_AUTHOR;
        }
        return $authorUserGroup->getId();
    }

    /**
     * @param $emailAddress
     * @param $journalId
     * @param $firstName
     * @param $lastName
     * @return PKPUser|User
     */
    private function getUserForAuthoring($emailAddress, $journalId, $firstName, $lastName)
    {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var RoleDAO $roleDao */
        $roleDao = DAORegistry::getDAO('RoleDAO');
        if ($userDao->userExistsByEmail($emailAddress)) {

            // User already has account, check if enrolled as author in journal
            /** @var User */
            $user = $userDao->getUserByEmail($emailAddress);
            $userId = $user->getId();

            if (!$roleDao->userHasRole($journalId, $userId, ROLE_ID_AUTHOR)) {
                //throw  new exception("Error: The author has already another role other than authoring in the same Journal");
            }
        } else {
            // User does not have an account. Create one and enroll as author.
            $username = Validation::suggestUsername($firstName, $lastName);
            $password = Validation::generatePassword();

            $user = $userDao->newDataObject();
            $user->setUsername($username);
            $user->setPassword(Validation::encryptCredentials($username, $password));
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setEmail($emailAddress);
            $user->setDateRegistered(Core::getCurrentDate());

            //this is to be added for authentication plugin in future, so that we will list it in auth_source table
            $authDao = DAORegistry::getDAO('AuthSourceDAO');
            $defaultAuth = $authDao->getDefaultPlugin();
            $user->setAuthId($defaultAuth->authId);

            $userDao->insertObject($user);
            $userId = $user->getId();

            //$role = new Role();
            //$role->setJournalId($journalId);
            //$role->setUserId($userId);
            //$role->setRoleId(ROLE_ID_AUTHOR);
            //$roleDao->insertRole($role);
        }
        return $user;
    }

    /**
     * @param $emailAddress
     * @param $journalId
     * @return PKPUser|User
     * @throws Exception
     * @internal param $firstName
     * @internal param $lastName
     */
    private function getUserForReviewing($emailAddress, $journalId)
    {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var RoleDAO $roleDao */
        $roleDao = DAORegistry::getDAO('RoleDAO');
        if ($userDao->userExistsByEmail($emailAddress)) {

            // User already has account, check if enrolled as author in journal
            /** @var User */
            $user = $userDao->getUserByEmail($emailAddress);
        } else {
            $user = null;
        }
        return $user;
    }


    // todo:
    private function updateArticle($authorId, $journalId)
    {

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        $author = $authorDao->getById($authorId);

        if ($author !== null && $userDao->userExistsByEmail($author->getEmail())) {

            return null;
        } else {
            //user has no
            return null;
        }
    }

    /**
     * @param User $user
     * @return int
     * @throws Exception
     */
    private function updateUser(User $user)
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new Exception("Error: Problem in updating User data");
        }
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        $userDao->updateObject($user);
        return $userId;
    }

    /**
     * @param $url
     * @param int $statusCode
     */
    private function redirect($url, $statusCode = 303)
    {
        header('Location: ' . $url, true, $statusCode);
        die();
    }

    /**
     * @param $revUrl
     * @return string
     */
    private function loginAuthoringTool($article_url)
    {
        $sharedKey = $this->sharedKey;
        $email = $this->getLoggedInUserEmailFromSession();
        if ($email == Null) {
            echo "Error: user is not logged in"; //todo make error handling
        }
        $userName = $this->getLoggedInUserNameFromSession();
        $data = array('key' => $sharedKey,
            'email' => $email,
            'user_name' => $userName);
        return $this->sendPostRequestAndJump($article_url, $data);
    }

    /**
     * @param $url
     * @param $data_array
     * @return string
     */
    private function sendPostRequestAndJump($url, $data_array)
    {

        $result = '
<html>
 <body onload="document.frm1.submit()">
   <form method="post" action="' . $url . '" name = "frm1" class="inline">
    <input type="hidden" name="key" value="' . $data_array['key'] . '">
    <input type="hidden" name="email" value="' . $data_array['email'] . '">
    <input type="hidden" name="doc_id" value="' . $data_array['doc_id'] . '">
    <input type="hidden" name="user_name" value="' . $data_array['user_name'] . '">
    <button type="submit" name="submit_param" value="submit_value" class="link-button">
    Jumping to the article ...
    </button>
  </form>
 </body >
</html >';
        echo $result;
    }


    /**
     * @return User/Null
     */
    private function getLoggedInUserEmailFromSession()
    {
        $email = Null;
        $sessionManager = SessionManager::getManager();
        $userSession = $sessionManager->getUserSession();
        /**
         * @var User
         */
        $user = $userSession->getUser();
        if (isset($user)) {
            $email = $user->getEmail();
        }
        return $email;
    }

    /**
     * @return User/Null
     */
    private function getLoggedInUserNameFromSession()
    {
        $email = Null;
        $sessionManager = SessionManager::getManager();
        $userSession = $sessionManager->getUserSession();
        /**
         * @var User
         */
        $user = $userSession->getUser();
        if (isset($user)) {
            $userName = $user->getUsername();
        }
        return $userName;
    }


    /**
     * @param $editorMessageCommentText
     * @param $reviewAssignment
     * @return bool
     */
    private function saveCommentForEditor($editorMessageCommentText, $reviewAssignment)
    {
        $hidden = true;
        return $this->saveComment($editorMessageCommentText, $hidden, $reviewAssignment);
    }

    /**
     * @param $editorAndAuthorMessageCommentText
     * @param $reviewAssignment
     * @return bool
     */
    private function saveCommentForEditorAndAuthor($editorAndAuthorMessageCommentText, $reviewAssignment)
    {
        $hidden = false;
        return $this->saveComment($editorAndAuthorMessageCommentText, $hidden, $reviewAssignment);
    }

    /**
     * @param $commentText
     * @param $hidden
     * @param $reviewAssignment
     * @return bool
     */
    private function saveComment($commentText, $hidden, $reviewAssignment)
    {
        if (strlen($commentText) === 0) {
            return false;
        }
        // Create a comment with the review.
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        $comment = $submissionCommentDao->newDataObject();
        $comment->setCommentType(COMMENT_TYPE_PEER_REVIEW);
        $comment->setRoleId(ROLE_ID_REVIEWER);
        $comment->setAssocId($reviewAssignment->getId());
        $comment->setSubmissionId($reviewAssignment->getSubmissionId());
        $comment->setAuthorId($reviewAssignment->getReviewerId());
        $comment->setComments($commentText);
        $comment->setCommentTitle('');
        $viewable = true;
        if ($hidden === true) {
            $viewable = false;
        }
        $comment->setViewable($viewable);
        $comment->setDatePosted(Core::getCurrentDate());
        // Persist.
        $submissionCommentDao->insertObject($comment);
        return true;
    }

}
