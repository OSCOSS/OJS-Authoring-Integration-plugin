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
            if ($this->getEnabled()) {
                HookRegistry::register('PluginRegistry::loadCategory', array($this, 'callbackLoadCategory'));
                HookRegistry::register('reviewassignmentdao::_insertobject', array($this, 'registerReviewerWebHook'));
                HookRegistry::register('reviewassignmentdao::_deletebyid', array($this, 'removeReviewerWebHook'));
                HookRegistry::register('reviewrounddao::_insertobject', array($this, 'newRevisionWebHook'));
            }
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
        $context = Request::getContext();
        $contextId = ($context == null) ? 0 : $context->getId();
        return $this->getSetting($contextId, 'apiKey');
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
    function getUserEmailByReviewID($reviewId) {
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
    function getUserNameByReviewID($reviewId) {
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
    function getSubmissionIdByReviewID($reviewId) {
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
    function sendPutRequest($url, $dataArray) {
        $result = $this->sendRequest('PUT', $url, $dataArray);
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
     * @return User/Null
     */
    function getUserFromSession() {
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
    function getDocData($submissionId, $round) {
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
            if ($position !== false) {
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
    function getReviewerEmailBySubmissionId($submissionId) {
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
    function getReviewerUserNameBySubmissionId($submissionId) {
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
