<?php

/**
 * Copyright 2016-17, Afshin Sadeghi (sadeghi@cs.uni-bonn.de) of the OSCOSS
 * Project.
 * License: GNU GPL v2. See LICENSE.md for details.
 */

class MockObject extends stdClass {
	// Used to create request mock object, to emulate real request. See below.
    public function __call($closure, $args) {
        return call_user_func_array($this->{$closure}->bindTo($this),$args);
    }

    public function __toString() {
        return call_user_func($this->{"__toString"}->bindTo($this));
    }
}

import('lib.pkp.classes.plugins.GatewayPlugin');

class FidusWriterGatewayPlugin extends GatewayPlugin {

    // BEGIN STANDARD PLUGIN FUNCTIONS

    /** @var string Name of parent plugin */
	public $parentPluginName;

	function __construct($parentPluginName) {
		parent::__construct();
		$this->parentPluginName = $parentPluginName;
	}

    /**
     * Hide this plugin from the management interface (it's subsidiary)
     */
    public function getHideManagement() {
        return true;
    }

    /**
     * Get the name of this plugin.
     * @return String name of plugin
     */
    public function getName() {
        return 'FidusWriterGatewayPlugin';
    }

    public function getDisplayName() {
        return __('plugins.generic.fidusWriter.displayName');
    }

    public function getDescription() {
        return __('plugins.generic.fidusWriter.description');
    }

    /**
	 * Get the Fidus Writer plugin
	 * @return FidusWriterPlugin
	 */
	function getFidusWriterPlugin() {
		return PluginRegistry::getPlugin('generic', $this->parentPluginName);
	}

    /**
     * Override the builtin to get the correct plugin path.
     */
    public function getPluginPath() {
        return $this->getFidusWriterPlugin()->getPluginPath();
    }

    /**
     * Store the path value iin the parent plugin so that it is accessible from
     * both.
     */
    public function getPluginUrl() {
        return $this->getFidusWriterPlugin()->getGatewayPluginUrl();
    }

    /**
     * Override the builtin to get the correct template path.
     * @return string
     */
    public function getTemplatePath($inCore = false) {
        return $this->getFidusWriterPlugin()->getTemplatePath($inCore);
    }

    /**
     * Get whether or not this plugin is enabled. (Should always return true, as the
     * parent plugin will take care of loading this one when needed)
     * @return boolean
     */
    public function getEnabled() {
        return $this->getFidusWriterPlugin()->getEnabled();
    }

    /**
     * @see Plugin::isSitePlugin()
     */
    function isSitePlugin() {
        return true;
    }

    // END STANDARD PLUGIN FUNCTIONS

    public function getApiKey() {
        return $this->getFidusWriterPlugin()->getApiKey();
    }


    public function getApiVersion() {
        return "1.0";
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
                            "version" => $this->getApiVersion()
                        );
                        $this->sendJsonResponse($response);
                        break;
                    case 'journals':
                        // Get all journals setup on this server.
                        $key = $_GET['key'];
                        if ($this->getApiKey() !== $key) {
                            // Not correct api key.
                            $error = "Incorrect API Key";
                            $this->sendErrorResponse($error);
                            break;
                        }

                        $response = $this->getJournals();
                        $this->sendJsonResponse($response);
                        break;
                    case 'documentReview':
                        // Forward the user to the editor logged in with
                        // appropriate rights.
                        $submissionId = intval($_GET['submissionId']);
                        $versionString = $_GET['version'];
                        $this->loginFidusWriter($submissionId, $versionString);
                        break;
                    default:
                        $error = "OJS Integration REST Plugin: Not a valid GET request";
                        $this->sendErrorResponse($error);
                }

            }

            if ($restCallType === "POST") {

                $key = $_GET['key'];
                if ($this->getApiKey() !== $key) {
                    // Not correct api key.
                    $error = "Incorrect API Key";
                    $this->sendErrorResponse($error);
                }

                switch ($operator) {
                    case 'test': // Basic test
                        $response = array(
                            "message" => "POST test response",
                            "version" => $this->getApiVersion()
                        );
                        $this->sendJsonResponse($response);
                        break;
                    case 'authorSubmit':
                        // in case author submits an article
                        $resultArray = $this->authorSubmit();
                        $response = array(
                            "submission_id" => $resultArray["submissionId"],
                            "user_id" => $resultArray["userId"],
                            "version" => $this->getApiVersion()
                        );
                        $this->sendJsonResponse($response);
                        break;
                    case 'reviewerSubmit':
                        // in case a reviewer submits the article review
                        $this->reviewerSubmit($request);
                        $response = array(
                            "version" => $this->getApiVersion()
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
                    "version" => $this->getApiVersion()
                );
                $this->sendJsonResponse($response);
            }

            if ($restCallType === "DELETE") {
                $response = array(
                    "message" => "DELETE response",
                    "version" => $this->getApiVersion()
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
    function getPOSTPayloadVariable($varName) {
        if (isset($_POST[$varName])) {
            return $_POST[$varName];
        }
        return "";
    }

    /**
     * @return string
     */
    function getRESTRequestType() {
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
     * @param array $response
     */
    function sendJsonResponse($response) {
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
    function getJournals() {
        $journalArray = [];
        $journalDao = DAORegistry::getDAO('JournalDAO');
        /* @var $journalDao JournalDAO */
        $journalsObject = $journalDao->getAll();
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
            "version" => $this->getApiVersion()
        );
        return $response;
    }

    /**
     * Takes an article submission from author and either updates an existing
     * submission or creates a new one.
     * @return array
     */
    function authorSubmit() {
        // Get all the variables used both when saving and updating submissions.
        $submissionId = $this->getPOSTPayloadVariable("submission_id");
        // The revision Id will be updated with every update from Fidus Writer.
        // It represents the ID used in the Fidus Writer database.
        $submissionDao = Application::getSubmissionDAO();
        $locale = AppLocale::getLocale();
        if ($submissionId !== "") {
            // This is an update to an existing submission. We check that it exists,
            // thereafter we update the revision id.
            $submission = $submissionDao->getById($submissionId);

            if ($submission === NUll || $submission === "") {
                throw new Exception("Error: no submission with given submissionId $submissionId exists");
            }
            // Given that this is a resubmission, we need to set the status of
            // the stage to REVIEW_ROUND_STATUS_RESUBMITTED.
            $versionString = $this->getPOSTPayloadVariable("version");
            $versionInfo = $this->versionToStage($versionString);
            $stageId = $versionInfo['stageId'];
            $round = $versionInfo['round'];
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
            $reviewRound = $reviewRoundDao->getReviewRound($submissionId, $stageId, $round);
            $reviewRound->setStatus(REVIEW_ROUND_STATUS_RESUBMITTED);
            $reviewRoundDao->updateObject($reviewRound);

        } else {
            // This is a new submission so we create it in the database
            $title = $this->getPOSTPayloadVariable("title");
            $journalId = $this->getPOSTPayloadVariable("journal_id");
            $fidusId = $this->getPOSTPayloadVariable("fidus_id");
            // Add the fidusUrl to the db entry of the submission.
            // Together with the 'fidusId', OJS will be able to create
            // a link to send the user to FW to edit the file.
            $fidusUrl = $this->getPOSTPayloadVariable("fidus_url");
            $submission = $this->createNewSubmission($title, $journalId, $fidusUrl, $fidusId);

			$submissionId = $submission->getId();

            // We also create a user for the author
            $emailAddress = $this->getPOSTPayloadVariable("email");
            $firstName = $this->getPOSTPayloadVariable("first_name");
            $lastName = $this->getPOSTPayloadVariable("last_name");
            $user = $this->getOrCreateUser($emailAddress, $firstName, $lastName);
            $userId = $user->getId();

            // And we create an author for the user.
            // Notice: authors are apparently not connected to users in OJS.
            $affiliation = $this->getPOSTPayloadVariable("affiliation");
            $country = $this->getPOSTPayloadVariable("country");
            $authorUrl = $this->getPOSTPayloadVariable("author_url");
            $biography = $this->getPOSTPayloadVariable("biography");
            $authorId = $this->saveAuthor($submissionId, $journalId, $emailAddress, $firstName, $lastName, $affiliation, $country, $authorUrl, $biography);

			// Create a fake request object as the real request does not contain the required data.
			// $request is required in the following code which comes from different parts of OJS.

			$request = new MockObject();
			$request->journalId = $journalId;
			$request->user = $user;
			$application = PKPApplication::getApplication();
			$request->origRequest = $application->getRequest();

			$request->getContext = function() {
				$contextDao = Application::getContextDAO();
				return $contextDao->getById($this->journalId);
			};

			$request->getUser = function() {
				return $this->user;
			};

			$request->getRouter = function() {
				return $this->origRequest->getRouter();
			};

			$request->isPathInfoEnabled = function() {
				return $this->origRequest->isPathInfoEnabled();
			};

			$request->isRestfulUrlsEnabled = function() {
				return $this->origRequest->isRestfulUrlsEnabled();
			};

			$request->getBaseUrl = function() {
				return $this->origRequest->getBaseUrl();
			};

			$request->getRemoteAddr = function() {
				return $this->origRequest->getRemoteAddr();
			};


			// The following has been adapted from PKPSubmissionSubmitStep4Form

			// Manager and assistant roles -- for each assigned to this
			//  stage in setup, iff there is only one user for the group,
			//  automatically assign the user to the stage.
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$submissionStageGroups = $userGroupDao->getUserGroupsByStage($journalId, WORKFLOW_STAGE_ID_SUBMISSION);
			$managerFound = false;
			while ($userGroup = $submissionStageGroups->next()) {
				// Only handle manager and assistant roles
				if (!in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT))) continue;

				$users = $userGroupDao->getUsersById($userGroup->getId(), $journalId);
				if($users->getCount() == 1) {
					$user = $users->next();
					$stageAssignmentDao->build($submissionId, $userGroup->getId(), $user->getId(), $userGroup->getRecommendOnly());
					if ($userGroup->getRoleId() == ROLE_ID_MANAGER) $managerFound = true;
				}
			}

			// Assign the user author to the stage
			$authorUserGroupId =  $this->getAuthorUserGroupId($journalId);
            if ($authorUserGroupId) {
                $stageAssignmentDao->build($submissionId, $authorUserGroupId, $userId);
            }



			// Assign sub editors for that section
			$submissionSubEditorFound = false;
			$subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
			$subEditors = $subEditorsDao->getBySectionId($submission->getSectionId(), $journalId);
			foreach ($subEditors as $subEditor) {
				$userGroups = $userGroupDao->getByUserId($subEditor->getId(), $journalId);
				while ($userGroup = $userGroups->next()) {
					if ($userGroup->getRoleId() != ROLE_ID_SUB_EDITOR) continue;
					$stageAssignmentDao->build($submissionId, $userGroup->getId(), $subEditor->getId(), $userGroup->getRecommendOnly());
					// If we assign a stage assignment in the Submission stage to a sub editor, make note.
					if ($userGroupDao->userGroupAssignedToStage($userGroup->getId(), WORKFLOW_STAGE_ID_SUBMISSION)) {
						$submissionSubEditorFound = true;
					}
				}
			}

			// Update assignment notifications
			import('classes.workflow.EditorDecisionActionsManager');
			$notificationManager = new NotificationManager();
			$notificationManager->updateNotification(
				$request,
				EditorDecisionActionsManager::getStageNotifications(),
				null,
				ASSOC_TYPE_SUBMISSION,
				$journalId
			);

			// Send a notification to associated users if an editor needs assigning
			if (!$managerFound && !$submissionSubEditorFound) {
				$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */

				// Get the managers.
				$managers = $roleDao->getUsersByRoleId(ROLE_ID_MANAGER, $journalId);

				$managersArray = $managers->toAssociativeArray();

				$allUserIds = array_keys($managersArray);
				foreach ($allUserIds as $userId) {
					$notificationManager->createNotification(
						$request, $userId, NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
						$journalId, ASSOC_TYPE_SUBMISSION, $submissionId
					);

					// Add TASK notification indicating that a submission is unassigned
					$notificationManager->createNotification(
						$request,
						$userId,
						NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
						$journalId,
						ASSOC_TYPE_SUBMISSION,
						$submissionId,
						NOTIFICATION_LEVEL_TASK
					);
				}
			}

			$notificationManager->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_APPROVE_SUBMISSION),
				null,
				ASSOC_TYPE_SUBMISSION,
				$submissionId
			);

			// End adaption from PKPSubmissionSubmitStep4Form

			// The following has been adapted from SubmissionSubmitStep4Form

			// Send author notification email
			import('classes.mail.ArticleMailTemplate');
			$context = $request->getContext();
			$router = $request->getRouter();
			$mail = new ArticleMailTemplate($submission, 'SUBMISSION_ACK', null, null, false);
			$mail->setContext($context);
			$authorMail = new ArticleMailTemplate($submission, 'SUBMISSION_ACK_NOT_USER', null, null, false);
			$authorMail->setContext($context);

			if ($mail->isEnabled()) {
				// submission ack emails should be from the contact.
				$mail->setFrom($context->getSetting('contactEmail'), $context->getSetting('contactName'));
				$authorMail->setFrom($context->getSetting('contactEmail'), $context->getSetting('contactName'));

				$user = $request->getUser();
				$primaryAuthor = $submission->getPrimaryAuthor();
				if (!isset($primaryAuthor)) {
					$authors = $submission->getAuthors();
					$primaryAuthor = $authors[0];
				}
				$mail->addRecipient($user->getEmail(), $user->getFullName());
				// Add primary contact and e-mail address as specified in the journal submission settings
				if ($context->getSetting('copySubmissionAckPrimaryContact')) {
					$mail->addBcc(
						$context->getSetting('contactEmail'),
						$context->getSetting('contactName')
					);
				}
				if ($copyAddress = $context->getSetting('copySubmissionAckAddress')) {
					$mail->addBcc($copyAddress);
				}

				if ($user->getEmail() != $primaryAuthor->getEmail()) {
					$authorMail->addRecipient($primaryAuthor->getEmail(), $primaryAuthor->getFullName());
				}

				$assignedAuthors = $submission->getAuthors();

				foreach ($assignedAuthors as $author) {
					$authorEmail = $author->getEmail();
					// only add the author email if they have not already been added as the primary author
					// or user creating the submission.
					if ($authorEmail != $primaryAuthor->getEmail() && $authorEmail != $user->getEmail()) {
						$authorMail->addRecipient($author->getEmail(), $author->getFullName());
					}
				}
				$mail->bccAssignedSubEditors($submission->getId(), WORKFLOW_STAGE_ID_SUBMISSION);

				$mail->assignParams(array(
					'authorName' => $user->getFullName(),
					'authorUsername' => $user->getUsername(),
					'editorialContactSignature' => $context->getSetting('contactName'),
					'submissionUrl' => $router->url($request, null, 'authorDashboard', 'submission', $submission->getId()),
				));

				$authorMail->assignParams(array(
					'submitterName' => $user->getFullName(),
					'editorialContactSignature' => $context->getSetting('contactName'),
				));

				$mail->send($request);

				$recipients = $authorMail->getRecipients();
				if (!empty($recipients)) {
					$authorMail->send($request);
				}
			}

			// Log submission.
			import('classes.log.SubmissionEventLogEntry'); // Constants
			import('lib.pkp.classes.log.SubmissionLog');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_SUBMISSION_SUBMIT, 'submission.event.submissionSubmitted');

			// End adaption from SubmissionSubmitStep4Form

        }

        $resultArray = array(
            "journalId" => $journalId,
            "submissionId" => $submissionId,
            "userId" => $userId
        );
        return $resultArray;
    }

    /**
     * @return mixed
     */
    function createNewSubmission($title, $journalId, $fidusUrl, $fidusId) {
        $locale = AppLocale::getLocale();

        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->newDataObject();
        $submission->setStatus(STATUS_QUEUED);
		$submission->stampStatusModified();
        $submission->setSubmissionProgress(0);
        // $journalId in OJS is same as $contextId in PKP lib.
        $submission->setContextId($journalId);
        $submission->setDateSubmitted(Core::getCurrentDate());
        $submission->setLocale($locale);
        $submission->setSubject($title, $locale);
        // WORKFLOW_STAGE_ID_SUBMISSION is the stage a submission is in right
        // when it is first submitted (== 1 in database).
        $submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
        $sectionDao = Application::getSectionDAO();
        // Sections are different parts of a journal, we only allow submission
        // to the default section ('Articles').
        // TODO: Extend the api to select which section to submit to.
        // https://pkp.sfu.ca/ojs/docs/userguide/2.3.3/journalManagementJournalSections.html
        $section = $sectionDao->getByTitle("Articles", $journalId, $locale);
        if ($section !== NULL) {
            $sectionId = $section->getId();
        } else {
            $sectionId = 1;
        }
        $submission->setData("sectionId", $sectionId);
        $submission->setTitle($title, $locale);
        $submission->setCleanTitle($title, $locale);

        // Set fidus writer related fields.
        $submission->setData("fidusUrl", $fidusUrl);
        $submission->setData("fidusId", $fidusId);
        // Insert the submission
        $submissionDao->insertObject($submission);

        return $submission;
    }

    /**
     *Takes a FW versionString and returns a stageId and round number.
     * Does the opposite of stageToVersion(...) in the parent plugin.
     */
    function versionToStage($versionString) {
        $parts = explode('.', $versionString);
        $stageId = intval($parts[0]);
        $round = intval($parts[1]);
        if ($parts[2]=='5') {
            $revisionType = 'Author';
        } else {
            $revisionType = 'Reviewer';
        }

        $returnArray = array();

        $returnArray['stageId'] = $stageId;
        $returnArray['round'] = $round;
        $returnArray['revisionType'] = $revisionType;

        return $returnArray;
    }


    /**
     * Takes an article review submission from reviewers
     * @return array
     * @throws Exception
     */
    function reviewerSubmit($request) {

        $submissionId = $this->getPOSTPayloadVariable("submission_id");
        $versionString = $this->getPOSTPayloadVariable("version");
        $reviewerId = $this->getPOSTPayloadVariable("user_id");

        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($submissionId);
        if ($submission ===null || $submission === "") {
            throw new Exception("Error: no submission with given submissionId $submissionId exists.");
        }

        $versionInfo = $this->versionToStage($versionString);
        $stageId = $versionInfo['stageId'];
        $round = $versionInfo['round'];

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDao->getReviewRound($submissionId, $stageId, $round);

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $reviewAssignmentDao->getReviewAssignment($reviewRound->getId(), $reviewerId);

        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
        $reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewAssignment->getId());

        $editorMessageCommentText = $this->getPOSTPayloadVariable("editor_message");
        $editorAndAuthorMessageCommentText = $this->getPOSTPayloadVariable("editor_author_message");
        $this->saveCommentForEditor($editorMessageCommentText, $reviewAssignment);
        $this->saveCommentForEditorAndAuthor($editorAndAuthorMessageCommentText, $reviewAssignment);

        // Set review step to last step
        $this->updateReviewStepAndSaveSubmission($reviewerSubmission);

        // Mark the review assignment as completed.
		$reviewAssignment->setDateCompleted(Core::getCurrentDate());
		$reviewAssignment->stampModified();

        // Set the recommendation
        $recommendation = intval($this->getPOSTPayloadVariable("recommendation"));
        $reviewAssignment->setRecommendation($recommendation);
		$reviewAssignmentDao->updateObject($reviewAssignment);

        // Send notifications to everyone who should be informed.
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submissionId, $stageId);
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$receivedList = array(); // Avoid sending twice to the same user.
        $notificationMgr = new NotificationManager();

		while ($stageAssignment = $stageAssignments->next()) {
			$userId = $stageAssignment->getUserId();
			$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $submission->getContextId());

			// Only send notifications about reviewer comment notification to managers and editors
            // and only send to usrs who have not received a notificcation already.
			if (!in_array(
                $userGroup->getRoleId(),
                array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR)) || in_array($userId, $receivedList)
            ) continue;

			$notificationMgr->createNotification(
				$request, $userId, NOTIFICATION_TYPE_REVIEWER_COMMENT,
				$submission->getContextId(), ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId()
			);

			$receivedList[] = $userId;
		}

        // Update the review round status.
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		$reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
		$reviewAssignments = $reviewAssignmentDao->getByReviewRoundId($reviewRound->getId());
		$reviewRoundDao->updateStatus($reviewRound, $reviewAssignments);

        $contextId = $submission->getContextId();


		// Update the notification on whether all reviews are in.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submissionId, $stageId);

		$notificationDao = DAORegistry::getDAO('NotificationDAO');

		foreach ($stageAssignments as $stageAssignment) {
			$userId = $stageAssignment->getUserId();

			// Get any existing notification.
			$notificationFactory = $notificationDao->getByAssoc(
				ASSOC_TYPE_REVIEW_ROUND,
				$reviewRound->getId(), $userId,
				NOTIFICATION_TYPE_ALL_REVIEWS_IN,
				$contextId
			);

			$currentStatus = $reviewRound->getStatus();
			if (in_array($currentStatus, $reviewRoundDao->getEditorDecisionRoundStatus()) ||
			in_array($currentStatus, array(REVIEW_ROUND_STATUS_PENDING_REVIEWERS, REVIEW_ROUND_STATUS_PENDING_REVIEWS))) {
				// Editor has taken a decision in round or there are pending
				// reviews or no reviews. Delete any existing notification.
				if (!$notificationFactory->wasEmpty()) {
					$notification = $notificationFactory->next();
					$notificationDao->deleteObject($notification);
				}
			} else {
				// There is no currently decision in round. Also there is reviews,
				// but no pending reviews. Insert notification, if not already present.
				if ($notificationFactory->wasEmpty()) {
					$notificationMgr->createNotification($request, $userId, NOTIFICATION_TYPE_ALL_REVIEWS_IN, $contextId,
						ASSOC_TYPE_REVIEW_ROUND, $reviewRound->getId(), NOTIFICATION_LEVEL_TASK);
				}
			}
		}

		// Remove the review task
		$notificationDao = DAORegistry::getDAO('NotificationDAO');
		$notificationDao->deleteByAssoc(
			ASSOC_TYPE_REVIEW_ASSIGNMENT,
			$reviewAssignment->getId(),
			$reviewAssignment->getReviewerId(),
			NOTIFICATION_TYPE_REVIEW_ASSIGNMENT
		);

        return;
    }

    /**
     * Set the review step of the submission to the given
     * value if it is not already set to a higher value. Then
     * update the given reviewer submission.
     * @param $reviewerSubmission ReviewerSubmission
     */
    function updateReviewStepAndSaveSubmission(ReviewerSubmission &$reviewerSubmission) {
        //review step
        $submissionCompleteStep = 4;
        $nextStep = $submissionCompleteStep;
        if ($reviewerSubmission->getStep() < $nextStep) {
            $reviewerSubmission->setStep($nextStep);
        }
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
    function saveAuthor($articleId, $journalId, $emailAddress, $firstName, $lastName, $affiliation, $country, $authorUrl, $biography) {
        // Set user to initial author
        $locale = AppLocale::getLocale();

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        /** @var Author $author */
        $author = $authorDao->newDataObject();
        $author->setFirstName($firstName);
        $author->setMiddleName("");
        $author->setLastName($lastName);
        $author->setAffiliation($affiliation, $locale);
        $author->setCountry($country);
        $author->setEmail($emailAddress);
        $author->setUrl($authorUrl);
        $author->setBiography($biography, $locale);
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
    function getAuthorUserGroupId($journalId) {
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
    function getOrCreateUser($emailAddress, $firstName, $lastName) {
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
     * Whether or not the user can be counted as an editor..
     * @param $user
     * @param $journalId
     * @return bool
     */
    function isEditor($userId, $journalId) {

        $roleDao = DAORegistry::getDAO('RoleDAO');

        // Check various roles that all could be counted as editors.
        if ($roleDao->userHasRole($journalId, $userId, ROLE_ID_MANAGER)) {
            return true;
        } elseif ($roleDao->userHasRole($journalId, $userId, ROLE_ID_SUB_EDITOR)) {
            return true;
        } elseif ($roleDao->userHasRole(CONTEXT_ID_NONE, $userId, ROLE_ID_SITE_ADMIN)) {
            return true;
        } elseif ($roleDao->userHasRole($journalId, $userId, ROLE_ID_ASSISTANT)) {
            return true;
        }

        return false;
    }

    /**
     * Gets a temporary access token from the Fidus Writer server to log the
     * given user in. This way we avoid exposing the api key in the client.
     * @param $userId
     * @param $accessRights
     */
    function getLoginToken($fidusUrl, $fidusId, $versionString, $userId, $isEditor) {

        $dataArray = array(
            'fidus_id' => $fidusId,
            'version' => $versionString,
            'user_id' => $userId,
            'is_editor' => $isEditor,
            'key' => $this->getApiKey()
        );

        $request = curl_init($fidusUrl . '/ojs/get_login_token/?' . http_build_query($dataArray));
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $result = json_decode(curl_exec($request), true);
        return $result['token'];
    }


    /**
     * Forwards user to Fidus Writer after checking access rights.
     * @param $fidusUrl
     * @param $fidusId
     * @param $submissionId
     * @param $version
     * @return string
     */
    function loginFidusWriter($submissionId, $versionString) {
        $fwPlugin = $this->getFidusWriterPlugin();
        $fidusId = $fwPlugin->getSubmissionSetting($submissionId, 'fidusId');
        $fidusUrl = $fwPlugin->getSubmissionSetting($submissionId, 'fidusUrl');
        $user = $this->getUserFromSession();
        $submissionDao = Application::getSubmissionDAO();
        $submission = $submissionDao->getById($submissionId);
        $journalId = $submission->getContextId();
        // Editor users will fallback to being logged in as the editor user on the
        // backend if they are not registered as either reviewers or authors of
        // the revision they are trying to look at.
        $isEditor = $this->isEditor($user->getId(), $journalId, $submission);

        $userId = $user->getId();
        $loginToken = $this->getLoginToken($fidusUrl, $fidusId, $versionString, $userId, $isEditor);
        echo '
<html>
 <body onload="document.frm1.submit()">
   <form method="post" action="' . $fidusUrl . '/ojs/revision/' . $fidusId . '/' . $versionString . '/" name = "frm1" class="inline">
    <input type="hidden" name="token" value="' . $loginToken . '">
    <button type="submit" name="submit_param" style="display=none;" value="submit_value" class="link-button"></button>
  </form>
 </body >
</html >';

        return;
    }


    /**
     * @return User/Null
     */
    function getUserFromSession() {
        $sessionManager = SessionManager::getManager();
        $userSession = $sessionManager->getUserSession();
        $user = $userSession->getUser();
        return $user;
    }


    /**
     * @param $editorMessageCommentText
     * @param $reviewAssignment
     * @return bool
     */
    function saveCommentForEditor($editorMessageCommentText, $reviewAssignment) {
        $hidden = true;
        return $this->saveComment($editorMessageCommentText, $hidden, $reviewAssignment);
    }

    /**
     * @param $editorAndAuthorMessageCommentText
     * @param $reviewAssignment
     * @return bool
     */
    function saveCommentForEditorAndAuthor($editorAndAuthorMessageCommentText, $reviewAssignment) {
        $hidden = false;
        return $this->saveComment($editorAndAuthorMessageCommentText, $hidden, $reviewAssignment);
    }

    /**
     * @param $commentText
     * @param $hidden
     * @param $reviewAssignment
     * @return bool
     */
    function saveComment($commentText, $hidden, $reviewAssignment) {
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
