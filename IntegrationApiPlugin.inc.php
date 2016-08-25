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

        error_log("loggingRegister:" . $reviewAssignment, 0);


        $email = $this->getUserEmail($row[1]);
        error_log("email: " . $email, 0);
        error_log("submitionID" . $row[0], 0);
        //then send the email address of reviewer to FW. FW must give review aceess to this article with the submisttion id
        //todo: use $this->sendRequest()
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
        error_log("reviewer email: ". $this->getUserEmailByReviewID($reviewId));
        error_log("SubmissionId: ". $this->getSubmissionIdByReviewID($reviewId));
        //then send the email address of reviewer to FW. FW must give review aceess to this article with the submisttion id
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

    // todo: update it
    private function sendRequest(){
        $url = 'http://server.com/path';
        $data = array('key1' => 'value1', 'key2' => 'value2');

        // use key 'http' even if you send the request to https://...
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === FALSE) { /* Handle error */ }

        var_dump($result);
    }
}