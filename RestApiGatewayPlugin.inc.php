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

class RestApiGatewayPlugin extends GatewayPlugin {

    /** @var string Name of parent plugin */
    public $parentPluginName;
    /** string API version */
    private $APIVersion;
    /** @var string */
    private $defaultLocale;
    /** @var string shared key to send request to AT */
    private $sharedKey;
    /**  @var string current plugin URL */
    private $pluginURL;

    public function RestApiGatewayPlugin($parentPluginName) {
        parent::__construct();
        $this->parentPluginName = $parentPluginName;
        $this->APIVersion = "1.0";
        $this->defaultLocale = AppLocale::getLocale();
        $OJSURL = $this->getSiteUrl();
        $this->pluginURL = $OJSURL . '/index.php/index/gateway/plugin/RestApiGatewayPlugin';
        //todo: this should be get from user in plugin installation time
        $this->sharedKey = "d5PW586jwefjn!3fv";
    }

    /***
     * Get the url of the current site.
     */
    public function getSiteUrl() {
        $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
        $domain = $_SERVER['SERVER_NAME'];
        $port = $_SERVER['SERVER_PORT'];
        $portStr = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";

        return "${protocol}://${domain}${portStr}";
    }

    /**
     * Hide this plugin from the management interface (it's subsidiary)
     */
    public function getHideManagement() {
        return true;
    }


    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     * @return String name of plugin
     */
    public function getName() {
        return 'RestApiGatewayPlugin';
    }

    public function getDisplayName() {
        return __('plugins.generic.ojsIntegrationRestApi.displayName');
    }

    public function getDescription() {
        return __('plugins.generic.ojsIntegrationRestApi.description');
    }

    /**
     * @see Plugin::isSitePlugin()
     */
    function isSitePlugin() {
        return true;
    }

    /**
     * Get the IntegrationApiPlugin plugin
     * @return IntegrationApiPlugin
     */
    public function getIntegrationApiPlugin() {
        return PluginRegistry::getPlugin('generic', $this->parentPluginName);
    }

    /**
     * Override the builtin to get the correct plugin path.
     */
    public function getPluginPath() {
        return $this->getIntegrationApiPlugin()->getPluginPath();
    }

    /**
     * Override the builtin to get the correct template path.
     * @return string
     */
    public function getTemplatePath() {
        return $this->getIntegrationApiPlugin()->getTemplatePath();
    }

    /**
     * Get whether or not this plugin is enabled. (Should always return true, as the
     * parent plugin will take care of loading this one when needed)
     * @return boolean
     */
    public function getEnabled() {
        return $this->getIntegrationApiPlugin()->getEnabled();
    }


    /**
     * Handle all requests for this plugin.
     * @param $args array
     * @param $request PKPRequest Request object
     * @return bool
     */
    public function fetch($args, $request) {

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
                        // Get all journals setup on this server.
                        $response = $this->getJournals();
                        // possible extension  get journals by email of a author.
                        // Currently, it returns all jounals
                        // sample:
                        //$userEmail = $this->getParameter('userEmail');
                        // echo json_encode(['userId'=>$userId]);
                        //$response = $this->getUserJournals($userEmail);

                        $this->sendJsonResponse($response);
                        break;
                    case 'documentReview':
                        // Forward the user to the editor logged in with
                        // appropriate rights.
                        $editorUrl = $_GET['editor_url'];
                        $editorRevisionId = $_GET['editor_revision_id'];
                        $submissionId = intval($_GET['submission_id']);
                        $version = $_GET['version'];
                        $this->loginEditor($editorUrl, $editorRevisionId, $submissionId, $version);
                        break;
                    default:
                        $error = "OJS Integration REST Plugin: Not a valid GET request";
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
                    case 'submit':
                        // in case author submits an article
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
    private function getPOSTPayloadVariable($varName) {
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
    private function getRESTRequestType() {
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
    private function getParameters() {
        return $this->request->_requestVars;
    }

    /**
     * @param $parameter
     * @return string
     */
    private function getParameter($parameter) {
        return $this->request->_requestVars[$parameter];
    }

    /**
     * @param array $response
     */
    private function sendJsonResponse($response) {
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
    public function sendErrorResponse($errorMessage) {
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
     * Return a list of journals hosted at this installation.
     * @return array
     */
    private function getJournals() {
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
    private function setArticleSubmissionVariables($submission, $journalId, $filename, $title) {
        //version numbers become clickable
        // update the link part is left
        //Fw side and test is left


        /** Submission */
        // $journalId in OJS is same as $contextId in PKP lib.
        $submission->setContextId($journalId);
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
     * Takes an article submission from author and either updates an existing
     * submission or creates a new one.
     * @return array
     */
    private function saveArticleWithAuthor() {
        // Get all the variables used both when saving and updating submissions.
        $editorUrl = $this->getPOSTPayloadVariable("editor_url");
        $submissionId = $this->getPOSTPayloadVariable("submission_id");
        $editorRevisionId = $this->getPOSTPayloadVariable("editor_revision_id");
        $version = $this->getPOSTPayloadVariable("version");
        $title = $this->getPOSTPayloadVariable("title");
        $journalId = 0;
        $userId = 0;
        if ($submissionId !== "") {
            $this->updateArticleSubmission($editorUrl, $title, $editorRevisionId, $submissionId, $version);
        } else {
            $journalId = $this->getPOSTPayloadVariable("journal_id");
            $filename = $this->getPOSTPayloadVariable("file_name");
            $submissionId = $this->saveNewArticleSubmission($editorUrl, $title, $editorRevisionId, $journalId, $filename);

            $emailAddress = $this->getPOSTPayloadVariable("email");
            $firstName = $this->getPOSTPayloadVariable("first_name");
            $lastName = $this->getPOSTPayloadVariable("last_name");
            $user = $this->getOrCreateUser($emailAddress, $firstName, $lastName);
            $userId = $user->getId();

            //check if author exist in db
            $affiliation = $this->getPOSTPayloadVariable("affiliation");
            $country = $this->getPOSTPayloadVariable("country");
            $authorUrl = $this->getPOSTPayloadVariable("author_url");
            $biography = $this->getPOSTPayloadVariable("biography");
            $authorId = $this->saveAuthor($submissionId, $journalId, $emailAddress, $firstName, $lastName, $affiliation, $country, $authorUrl, $biography);

            // Assign the user author to the stage
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $authorUserGroupId =  $this->getAuthorUserGroupId($journalId);
            if ($authorUserGroupId) {
                $stageAssignmentDao->build($submissionId, $authorUserGroupId, $userId);
            }
        }

        $resultArray = array(
            "journalId" => $journalId,
            "submissionId" => $submissionId,
            "userId" => $userId,
        );
        return $resultArray;
    }

    /**
     * Creates a link that is set on the title of the submission. Clicking the
     * link will make the user be sent to the editor and be logged in there.
     * @param $editorUrl
     * @param $title
     * @param $submissionId
     * @param $editorRevisionId
     * @param $version
     * @return string
     */
    private function makeSignInURL($editorUrl, $title, $submissionId, $editorRevisionId, $version) {
        $signInUrl = $this->pluginURL . '/documentReview?editor_url=' . $editorUrl . '&submission_id=' . $submissionId . '&editor_revision_id=' . $editorRevisionId . '&version=' . $version;
        $round = $version + 1;
        $linkToOJS = $title . '<a href="' . $signInUrl . '"> Round ' . $round .'</a>';
        return $linkToOJS;
    }

    /**
     * @return mixed
     */
    private function saveNewArticleSubmission($editorUrl, $title, $editorRevisionId, $journalId, $filename) {
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->newDataObject();
        $submission->setStatus(STATUS_QUEUED);
        $submission->setSubmissionProgress(0);
        $submission = $this->setArticleSubmissionVariables($submission, $journalId, $filename, $title);
        //$workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO');
        //  $submission->setStageId(WorkflowStageDAO::getIdFromPath($node->getAttribute('stage')));
        // WORKFLOW_STAGE_ID_SUBMISSION is the stage a submission is in right
        // when it is first submitted (== 1 in database).
        $submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
        //$submission->setCopyrightNotice($this->context->getLocalizedSetting('copyrightNotice'), $this->getData('locale'));

        // Sections are different parts of a journal, we only allow submission
        // to the default section ('Articles').
        // TODO: Extend the api to select which section to submit to.
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

        // We first save the submission so that we get an ID for it. We need
        // this ID for the link in the title, so after saving we update the title
        // (which will cause another save).
        $signInURL = $this->makeSignInURL($editorUrl, $title, $submissionId, $editorRevisionId, 0);

        $submissionDao->updateSetting($submissionId, 'title', [$this->defaultLocale => $signInURL], 'string', True);
        $submissionDao->updateSetting($submissionId, 'cleanTitle', [$this->defaultLocale => $sigInURL], 'string', True);

        //$submission->setTitle($linkToEditor, $this->defaultLocale);
        //$submission->setCleanTitle($linkToEditor, $this->defaultLocale);

        return $submissionId;
    }


    /**
     * @param $submissionId
     * @param $versionNum
     * @return Submission
     * @throws Exception
     */
    private function updateArticleSubmission($editorUrl, $title, $editorRevisionId, $submissionId, $version) {
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($submissionId);
        //error_log("MOINMOIN0:" . var_export([$submissionId,$versionNum, $submission], true), 0);

        if ($submission === NUll || $submission === "") {
            throw new Exception("Error: no submission with given submissionId $submissionId exists");
        }

        $signInURL = $this->makeSignInURL($editorUrl, $title, $submissionId, $editorRevisionId, $version);

        /** @var ArticleDAO $submissionDao * */
        $submissionDao->updateSetting($submissionId, 'title', [$this->defaultLocale => $signInURL], 'string', True);
        $submissionDao->updateSetting($submissionId, 'cleanTitle', [$this->defaultLocale => $sigInURL], 'string', True);
    }

    /**
     * Takes an article review submission from reviewers
     * @return array
     * @throws Exception
     */
    private function saveArticleReview() {
        $journalId = $this->getPOSTPayloadVariable("journal_id");
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
        // WORKFLOW_STAGE_ID_INTERNAL_REVIEW is the stage a submission is in
        // during the internal peer review process (== 2 in database).
        $submission->setStageId(WORKFLOW_STAGE_ID_INTERNAL_REVIEW);

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
    function updateReviewStepAndSaveSubmission(ReviewerSubmission &$reviewerSubmission) {
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
    private function saveAuthor($articleId, $journalId, $emailAddress, $firstName, $lastName, $affiliation, $country, $authorUrl, $biography) {
        // Set user to initial author

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var Author $author */
        $author = $authorDao->newDataObject();
        $author->setFirstName($firstName);
        $author->setMiddleName("");
        $author->setLastName($lastName);
        $author->setAffiliation($affiliation, $this->defaultLocale);
        $author->setCountry($country);
        $author->setEmail($emailAddress);
        $author->setUrl($authorUrl);
        $author->setBiography($biography, $this->defaultLocale);
        $author->setPrimaryContact(true);
        $author->setIncludeInBrowse(true);

        $authorUserGroup = $this->getAuthorUserGroupId($journalId);
        if ($authorUserGroup) {
            $author->setUserGroupId($authorUserGroup);
        }
        $author->setSubmissionId($articleId);

        $authorId = $authorDao->insertObject($author);
        $author->setId($authorId);
        return $authorId;
    }


    /**
     * @param $journalId
     * @return mixed
     */
    private function getAuthorUserGroupId($journalId) {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        /** /classes/security/UserGroup  */
        $authorUserGroup = $userGroupDao->getDefaultByRoleId($journalId, ROLE_ID_AUTHOR);
        if ($authorUserGroup === false) {
            return false;
        }
        return $authorUserGroup->getId();
    }

    /**
     * Returns a user with the given $emailAddress or creates and returns a new
     * user if this is not the case.
     *
     * @param $emailAddress
     * @param $firstName
     * @param $lastName
     * @return PKPUser|User
     */
    private function getOrCreateUser($emailAddress, $firstName, $lastName) {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        if ($userDao->userExistsByEmail($emailAddress)) {

            // User already has account, check if enrolled as author in journal
            /** @var User */
            $user = $userDao->getUserByEmail($emailAddress);
            $userId = $user->getId();

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
    private function getUserForReviewing($emailAddress, $journalId) {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
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
    /*private function updateArticle($authorId, $journalId) {

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var UserDAO $userDao */
    /*    $userDao = DAORegistry::getDAO('UserDAO');
        $author = $authorDao->getById($authorId);

        if ($author !== null && $userDao->userExistsByEmail($author->getEmail())) {

            return null;
        } else {
            //user has no
            return null;
        }
    }*/

    /**
     * @param User $user
     * @return int
     * @throws Exception
     */
    /*private function updateUser(User $user) {
        $userId = $user->getId();
        if ($userId === null) {
            throw new Exception("Error: Problem in updating User data");
        }
        /** @var UserDAO $userDao */
    /*    $userDao = DAORegistry::getDAO('UserDAO');
        $userDao->updateObject($user);
        return $userId;
    }*/

    /**
     * @param $url
     * @param int $statusCode
     */
    /*private function redirect($url, $statusCode = 303) {
        header('Location: ' . $url, true, $statusCode);
        die();
    }*/

    /**
     * Find the access rights the current user should have on the editor.
     * Given that Fidus Writer and OJS have different access rights models,
     * we translate the access rights to one of three: 1. author, 2. reviewer
     * or 3. editor, in this order. If a user has multiple types of access
     * rights, only the first of these will be considered. If a user cannot be
     * considered to be any of these, false is returned.
     *
     * For example:
     * 1. User A is an administrator of the OJS site and therefore
     * has editor rights in all journals. User A has sent in a submission to
     * OJS via Fidus Writer as an author. Because User A is both an editor and
     * an author, the first one of these will be chosen: The access rights of
     * user A 'author'.
     * 2. User B is both a reviewer and an editor in OJS in relation to one
     * submission. The access rights of user B will be 'reviewer'
     * 3. User C is registered on the OJS site, but only as a reader without
     * editing rights. The access rights of user C will be false.
     */
    private function getAccessRights($user, $submissionId) {
        $authorDao = DAORegistry::getDAO('AuthorDAO');
        $author = $authorDao->getPrimaryContact($submissionId);

        $userId = $user->getId();

        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($submissionId);
        $journalId = $submission->getContextId();

        $roleDao = DAORegistry::getDAO('RoleDAO');

        // There seems to be no direct connection between authors and users,
        // so we make a guess.
        // If the user is in the author group for the journal AND
        // the email is the same as that of the author who si set as the
        // primary contact, we assume this is the author.
        // OBS! This means an author who changes the email address of his OJS
        // user account will run into problems.
        if (
            $roleDao->userHasRole($journalId, $userId, ROLE_ID_AUTHOR) &&
            $user->getEmail() === $author->getEmail()
        ) {
            return array('author', $jounalId, $userId);
        }

        // Check all registered reviewers if one of them is our user. If so, return 'reviewer'
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignmentsArray = $reviewAssignmentDao->getBySubmissionId($submissionId);
        foreach ($reviewAssignmentsArray as $reviewAssignmentObject) {
            if ($reviewAssignmentObject->getReviewerId() === $userId) {
                return array('reviewer', $journalId, $userId);
            }
        }

        // Check various roles that all could be counted as editors.
        if ($roleDao->userHasRole($journalId, $userId, ROLE_ID_MANAGER)) {
            return array('editor', $journalId, $userId);
        } elseif ($roleDao->userHasRole($journalId, $userId, ROLE_ID_SUB_EDITOR)) {
            return array('editor', $journalId, $userId);
        } elseif ($roleDao->userHasRole(none, $userId, ROLE_ID_SITE_ADMIN)) {
            return array('editor', $journalId, $userId);
        } elseif ($roleDao->userHasRole($journalId, $userId, ROLE_ID_GUEST_EDITOR)) {
            return array('editor', $journalId, $userId);
        } elseif ($roleDao->userHasRole($journalId, $userId, ROLE_ID_ASSISTANT)) {
            return array('editor', $journalId, $userId);
        }

        return array(false, $journalId);
    }

    /**
     * Gets a temporary access token from the Fidus Writer server to log the
     * given user in. This way we avoid exposing the api key in the client.
     * @param $userId
     * @param $accessRights
     */
    private function getLoginToken($editorUrl, $userRole, $journalId, $userId) {

        $dataArray = array(
            'user_id' => $userId,
            'user_role' => $userRole,
            'journal_id' => $journalId,
            'key' => $this->sharedKey
        );

        $request = curl_init($editorUrl . '/ojs/get_login_token/?' . http_build_query($dataArray));
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $result = json_decode(curl_exec($request), true);
        return $result['token'];
    }


    /**
     * Forwards user to the editor after checking access rights.
     * @param $editorUrl
     * @param $editorRevisionId
     * @param $submissionId
     * @param $version
     * @return string
     */
    private function loginEditor($editorUrl, $editorRevisionId, $submissionId, $version) {

        $user = $this->getUserFromSession();

        list ($userRole, $journalId) = $this->getAccessRights($user, $submissionId);

        if ($userRole === false) {
            $this->sendErrorResponse("Error: insufficient access rights");
            return;
        }

        $userId = $user->getId();
        $loginToken = $this->getLoginToken($editorUrl, $userRole, $journalId, $userId);

        echo '
<html>
 <body onload="document.frm1.submit()">
   <form method="post" action="' . $editorUrl . '/ojs/revision/' . $editorRevisionId . '/" name = "frm1" class="inline">
    <input type="hidden" name="token" value="' . $loginToken . '">
    <button type="submit" name="submit_param" value="submit_value" class="link-button">
    Jumping to the editor ...
    </button>
  </form>
 </body >
</html >';

        return;
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
     * @return User/Null
     */
    /*private function getLoggedInUserEmailFromSession() {
        $email = Null;
        $user = $this->getUserFromSession();
        if (isset($user)) {
            $email = $user->getEmail();
        }
        return $email;
    }

    /**
     * @return User/Null
     */
    /*private function getLoggedInUserNameFromSession() {
        $email = Null;
        $user = $this->getUserFromSession();
        if (isset($user)) {
            $userName = $user->getUsername();
        }
        return $userName;
    }*/


    /**
     * @param $editorMessageCommentText
     * @param $reviewAssignment
     * @return bool
     */
    private function saveCommentForEditor($editorMessageCommentText, $reviewAssignment) {
        $hidden = true;
        return $this->saveComment($editorMessageCommentText, $hidden, $reviewAssignment);
    }

    /**
     * @param $editorAndAuthorMessageCommentText
     * @param $reviewAssignment
     * @return bool
     */
    private function saveCommentForEditorAndAuthor($editorAndAuthorMessageCommentText, $reviewAssignment) {
        $hidden = false;
        return $this->saveComment($editorAndAuthorMessageCommentText, $hidden, $reviewAssignment);
    }

    /**
     * @param $commentText
     * @param $hidden
     * @param $reviewAssignment
     * @return bool
     */
    private function saveComment($commentText, $hidden, $reviewAssignment) {
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
