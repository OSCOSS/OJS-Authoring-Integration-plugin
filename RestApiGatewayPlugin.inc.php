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

class RestApiGatewayPlugin extends GatewayPlugin
{


    /** @var string Name of parent plugin */
    public $parentPluginName;
    /** string API version */
    private $APIVersion;
    /** string */
    private $defaultLocale;

    public function RestApiGatewayPlugin($parentPluginName)
    {
        parent::GatewayPlugin();
        $this->parentPluginName = $parentPluginName;
        $this->APIVersion = "1.0";
        $this->defaultLocale = AppLocale::getLocale();
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
                        $response = $this->getJournals();
                        // possible extension  get journals by email of a author. Currently, it returns all jounals
                        // sample:
                        //$userEmail = $this->getParameter('userEmail');
                        // echo json_encode(['userId'=>$userId]);
                        //$response = $this->getUserJournals($userEmail);

                        $this->sendJsonResponse($response);
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
                    //todo:check why submissions are not listed in my journal as submitted papers
                    case 'articles':
                        $resultArray = $this->saveArticleWithAuthor();
                        $response = array(
                            "submission_id" => $resultArray["submissionId"],
                            "journal_id" => $resultArray["journalId"],
                            "user_Id" => $resultArray["userId"],
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
        } catch (Exception $e) {
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
        error_log("logging:". implode($_SERVER, "," ));
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
    private function setArticleVariablesFromPOSTPayload($submission)
    {
        $contextId = $this->getPOSTPayloadVariable("journal_id");
        $filename = $this->getPOSTPayloadVariable("file_name");
        $articleUrl = $this->getPOSTPayloadVariable("article_url");
        $title = $this->getPOSTPayloadVariable("title");

        /** Submission */
        $submission->setContextId($contextId);
        $submission->setDateSubmitted(Core::getCurrentDate());
        $linkToOJS = '<a href="'.$articleUrl.'">Click here to open in Fidus Writer: '.$title. '</a>';

        $submission->setLocale($this->defaultLocale);
        $submission->setSubject($title, $this->defaultLocale);
        //$submission->setFileName($filename, $this->defaultLocale);
        $submission->setTitle($linkToOJS, $this->defaultLocale);
        $submission->setCleanTitle($linkToOJS, $this->defaultLocale);
        // setting data as article_url did not work,
        // instead we use the file_name to store it and the title will keep it.
        //$submission->setData("article_url", $articleUrl);
        return $submission;
    }

    /**
     * @return array
     */
    private function saveArticleWithAuthor()
    {
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->newDataObject();
        $submission->setStatus(STATUS_QUEUED);
        $submission->setSubmissionProgress(0);
        $this->setArticleVariablesFromPOSTPayload($submission);

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

        $emailAddress = $this->getPOSTPayloadVariable("email");
        $firstName = $this->getPOSTPayloadVariable("first_name");
        $lastName = $this->getPOSTPayloadVariable("last_name");
        $user = $this->getUser($emailAddress, $journalId, $firstName, $lastName);

        //check if author exist in db
        $authorId = $this->saveAuthor($submissionId, $journalId, $emailAddress, $firstName, $lastName);

        // Assign the user author to the stage
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignmentDao->build($submissionId, $this->getAuthorUserGroupId($journalId), $user->getId());

        $resultArray = array(
            "journalId" => $journalId,
            "submissionId" => $submissionId,
            "userId" => $user->getId(),
        );
        return $resultArray;
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
        if($authorUserGroup === FALSE){
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
    private function getUser($emailAddress, $journalId, $firstName, $lastName)
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
                //todo:
                // ask alec and christop and philip about a legitimate logic here,
                // maybe if a user is already registered, we better not to change his role type

                // User not enrolled as author enroll as author

                /** @var Role $role */
                //$role = new Role();
                //todo: ask alec why this gives error
                // $role->setJournalId($journalId);
                //$role->setUserId($userId);

                // $role->setData("context_id",$journalId);
                //$role->setData("user_id",$journalId);
                //$role->setRoleId(ROLE_ID_AUTHOR);
                // $roleDao->insertRole($role);
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
}

?>