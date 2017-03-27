<?php

/**
 * Copyright 2016-17, Afshin Sadeghi (sadeghi@cs.uni-bonn.de) of the OSCOSS
 * Project.
 * License: MIT. See LICENSE.md for details.
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class FidusWriterPlugin extends GenericPlugin {

    /**
     * @param $category
     * @param $path
     * @return bool
     */

    function register($category, $path) {
        if (parent::register($category, $path)) {
			/* Note: it looks counterintuitive that only the first listener checks
			   whether the plugin is enabled, but the way OJS is set up, if one
			   moves the other listeners inside the check, they stop working.
			*/
            if ($this->getEnabled()) {
                HookRegistry::register('PluginRegistry::loadCategory', array($this, 'callbackLoadCategory'));
            }
			HookRegistry::register('reviewassignmentdao::_insertobject', array($this, 'callbackAddReviewer'));
			HookRegistry::register('reviewassignmentdao::_deletebyid', array($this, 'callbackRemoveReviewer'));
			HookRegistry::register('reviewrounddao::_insertobject', array($this, 'newRevisionWebHook'));
            return true;
        }
        return false;
    }

    // BEGIN STANDARD PLUGIN FUNCTIONS

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
    function getTemplatePath($inCore = false) {
 		return parent::getTemplatePath($inCore) . 'templates/';
 	}

    /**
     * Get the display name for this plugin.
     *
     * @return string
     */
    function getDisplayName() {
        return __('plugins.generic.fidusWriter.displayName');

    }

    /**
     * Get a description of this plugin.
     *
     * @return string
     */
    function getDescription() {
        return __('plugins.generic.fidusWriter.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    function getActions($request, $verb) {
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        return array_merge(
            $this->getEnabled()?array(
                new LinkAction(
                    'settings',
                    new AjaxModal($router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ):array(),
            parent::getActions($request, $verb)
        );
    }


	/**
	 * @copydoc Plugin::manage()
	 */
    function manage($args, $request) {
        $this->import('FidusWriterSettingsForm');
 		switch ($request->getUserVar('verb')) {
 			case 'settings':
				$settingsForm = new FidusWriterSettingsForm($this);
				$settingsForm->initData();
				return new JSONMessage(true, $settingsForm->fetch($request));
                break;
			case 'save':
				$settingsForm = new FidusWriterSettingsForm($this);
				$settingsForm->readInputData();
				if ($settingsForm->validate()) {
					$settingsForm->execute();
					$notificationManager = new NotificationManager();
					$notificationManager->createTrivialNotification(
						$request->getUser()->getId(),
						NOTIFICATION_TYPE_SUCCESS,
						array('contents' => __('plugins.generic.fidusWriter.settings.saved'))
					);
					return new JSONMessage(true);
				}
				return new JSONMessage(true, $settingsForm->fetch($request));
                break;
 		}
 		return parent::manage($args, $request);
 	}

    /**
     * @see Plugin::isSitePlugin()
     */
    function isSitePlugin() {
        return true;
    }

    // END STANDARD PLUGIN FUNCTIONS


    function getApiKey() {
        return $this->getSetting(CONTEXT_ID_NONE, 'apiKey');
    }


    /**
	 * Sends information about a newly registered reviewer for a specific submission
	 * to Fidus Writer, if the submission is of a document in Fidus Writer.
     * @param $hookName
     * @param $args
     * @return bool
     */
    function callbackAddReviewer($hookName, $args) {
        $row =& $args[1];
        $submissionId = $row[0];
		$docData = $this->getFidusWriterLinkData($submissionId);
		if ($docData === false) {
			// The article was not connected with Fidus Writer, so we send no
			// notification.
			return false;
		}
		$reviewerId = $row[1];
        $reviewer = $this->getUser($reviewerId);
        $dataArray = [
			'email' => $reviewer->getEmail(),
			'username' => $reviewer->getUserName(),
			'user_id' => $reviewerId,
			'key' => $this->getApiKey()
		];
        $url = $docData['editor_url'] . '/ojs/add_reviewer/' . $docData['editor_revision_id'] . '/';

        // then send the email address of reviewer to AT.
        // Authoring tool must give review access to this article with the submission id
        $this->sendPostRequest($url, $dataArray);
        return false;
    }

    /**
	 * Sends information to Fidus Writer that a given reviewer has been removed
	 * from a submission so that Fidus Writer also removes the access the reviewer
	 * has had to the document in question.
     * @param $hookName
     * @param $args
     * @return bool
     */
    function callbackRemoveReviewer($hookName, $args) {
		$reviewId =& $args[1];
		$submissionId = $this->getSubmissionIdByReviewId($reviewId);
		$docData = $this->getFidusWriterLinkData($submissionId);
		if ($docData === false) {
			// The article was not connected with Fidus Writer, so we send no
			// notification.
			return false;
		}

        $docData = $this->getFidusWriterLinkData($submissionId);
        $userId = $this->getUserIdByReviewId($reviewId);
        $dataArray = [
			'user_id' => $userId,
			'key' => $this->getApiKey()
		];
        // Then send the email address of reviewer to Fidus Writer.
		$url = $docData['editor_url'] . '/ojs/remove_reviewer/' . $docData['editor_revision_id'] . '/';
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
            'key' => $this->getApiKey(), //shared key between OJS and Editor software
            'submission_id' => $submissionId,
            'round' => $round];  //editor user for logging in
        // Then send the email address of reviewer to authoring tool.
        // AT must give review access to this article with the submission id
        $docData = $this->getFidusWriterLinkData($submissionId);
        $url = $docData['base_url'] . '/ojs/newsubmissionrevision/';
        $result = $this->sendPostRequest($url, $dataArray);
    }

    /**
     * @param $hookName string
     * @param $args array
     * @return bool
     **/
    function callbackLoadCategory($hookName, $args) {
        $category = $args[0];
        $plugins =& $args[1];
        switch ($category) {
            case 'gateways':
                $this->import('FidusWriterGatewayPlugin');
                $gatewayPlugin = new FidusWriterGatewayPlugin($this->getName());
                $plugins[$gatewayPlugin->getSeq()][$gatewayPlugin->getPluginPath()] = $gatewayPlugin;
                break;
        }
        return false;
    }

	function getUser($userId) {
		$userDao = DAORegistry::getDAO('UserDAO');
		return $userDao->getById($userId);
	}


    /**
     * @param $userId
     * @return string
     */
    function getUserEmail($userId) {
        /** @var UserDAO $userDao */
        $userDao = DAORegistry::getDAO('UserDAO');
        return $userDao->getUserEmail($userId);
    }

    /**
     * @param $userId
     * @return string
     */
    function getUserName($userId) {
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
    function getUserIdByReviewId($reviewId) {
        $userDao = DAORegistry::getDAO('UserDAO');
        /** @var ReviewAssignmentDAO $RADao */
        $RADao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignmentArray = $RADao->getById($reviewId);
		// TODO: Find out if there are any problems here if this assignment
		// contains more than one reviewer.
        if (is_array($reviewAssignmentArray)) {
            $reviewAssignment = $reviewAssignmentArray[0];
        } else {
            $reviewAssignment = $reviewAssignmentArray;
        }
        /** @var ReviewAssignment $reviewAssignment */
        $userId = $reviewAssignment->getReviewerId();
        return $userId;
    }

    /**
     * @param $reviewId
     * @return int
     */
    function getSubmissionIdByReviewId($reviewId) {
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
     * @param $dataArray
     * @return string
     */
    function sendRequest($requestType, $url, $dataArray) {
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => $requestType,
                'content' => http_build_query($dataArray)
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === false) { /* Handle error */
            echo $result;
        }
        return $result;
    }


    /**
     * @param $url
     * @param $dataArray
     * @return string
     */
    function sendPostRequest($url, $dataArray) {
        $result = $this->sendRequest('POST', $url, $dataArray);
        return $result;
    }


    /**
	 * We split the title of a submission at all quotation marks and iterate
	 * over each part.
	 * If one of these parts is a link to documentReview on a Fidus Writer
	 * instance, we return the document data found in the link. Otherwise
	 * we return false.
     * @param $submissionId
     * @param $round
     * @return mixed
     */
    function getFidusWriterLinkData($submissionId) {
		$required = array('editor_url', 'editor_revision_id', 'submission_id', 'version');
        $submissionDao = Application::getSubmissionDAO();
        /** @var Submission */
        $submission = $submissionDao->getById($submissionId);
        $submissionTitle = $submission->getTitle();
		$result = false;
        $titleParts = explode('"', $submissionTitle);
		foreach ($titleParts as $part) {
			$position = strpos($part, "documentReview?editor_url");
            if ($position !== false) {
				// The string contains a link to what is most likely a Fidus
				// Writer instance. We now extract the other data from the link
				// and return it.
				$data = [];
				parse_str(parse_url($part, PHP_URL_QUERY), $data);
				if(count(array_intersect_key(array_flip($required), $data)) === count($required)) {
					// All the fields are here, we can safely return this.
					$result = $data;
				}
			}
		}
        return $result;
    }


    /**
     * @param $submissionId
     * @return mixed
     */
    function getAuthorEmailBySubmissionId($submissionId) {
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
    function getAuthorUserNameBySubmissionId($submissionId) {
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
