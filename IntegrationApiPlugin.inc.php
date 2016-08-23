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

                // HookRegistry::register('pkpsubmissionfiledao::__getinternally', array($this, 'editSubmittedFiles'));
               // HookRegistry::register('reviewAssignmentdao::_returnReviewAssignmentFromRow', array($this, 'registerReviewer'));
                // HookRegistry::register ('TemplateManager::display', array($this, 'editReviewerTitle'));

            }
            return true;
        }
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
     * @param $hookName
     * @param $args
     * @return bool
     */
    function registerReviewer($hookName, $args)
    {
        /*
        $reviewAssignment =& $args[0];
        $row =& $args[1];

        error_log("logging:".$reviewAssignment,0);
        error_log("reviewerID:".$row['reviewer_id']. "submittionID" .$row['submission_id']."reviewID:".$row['review_id'] ,0);
        //then send the email address of reviewer to FW. FW must give review aceess to this article with the submisttion id
        */
        return false;

    }

    /**
     * Show submitted files in in FW instead of uploaded files
     * @param $hookName
     * @param $args
     */
    function editSubmittedFiles($hookName, $args)
    {

        //     error_log( "logging:". implode($args[2][1]),0);

        /*
            $sql =  'SELECT DISTINCT
                    sf.file_id AS submission_file_id, sf.revision AS submission_revision,
                    af.file_id AS artwork_file_id, af.revision AS artwork_revision,
                    suf.file_id AS supplementary_file_id, suf.revision AS supplementary_revision,
                    sf.*, af.*, suf.*
                FROM	submission_files sf
                    LEFT JOIN submission_artwork_files af ON sf.file_id = af.file_id AND sf.revision = af.revision
                    LEFT JOIN submission_supplementary_files suf ON sf.file_id = suf.file_id AND sf.revision = suf.revision LEFT JOIN submission_files sf2 ON sf.file_id = sf2.file_id AND sf.revision < sf2.revision
                    WHERE sf2.revision IS NULL AND  sf.submission_id = ? AND sf.file_stage = ? ORDER BY sf.submission_id ASC, sf.file_stage ASC, sf.file_id ASC, sf.revision DESCArray';
        */
        //$params = 192;

        //if(strtolower_codesafe($trace[1]['class'] . '::_' . $trace[1]['function']) === "PKPSubmissionFileDAO::__getInternally") {
        //    echo ($trace[1]['class'] . '::_' . $trace[1]['function']) ;
        //die();
//vars:        &$sql, &$params, &$value

        return false;
    }

    /**
     * Show submitted files in in FW instead of range of uploaded files
     * @param $hookName
     * @param $args
     */
    function editRangeSubmittedFiles($hookName, $args)
    {

        //vars:       &$sql, &$params, &$dbResultRange, &$value
        return false;
    }


    /**
     * Edit the reviewer page.
     * This follows a convoluted execution path in order to obtain the
     * page title *after* the template has been displayed, even though
     * the hook is called before execution.
     */
    function editReviewerTitle($hookName, $args)
    {
        /*
        $templateManager = $args[0];
        $template =& $args[1];
        $request = $this->getRequest();

        $site = $request->getSite();
        $journal = $request->getJournal();
        $session =& $request->getSession();

        if (!$journal) return false;
        if ($request->isBot()) return false;

        assert(false); // Template names no longer apply (e.g. interstitial)

        switch ($template) {
            // lib/pkp/templates/reviewer/review/reviewStepHeader.tpl
            //  lib/pkp/templates/reviewer/review/step3.tpl
            case 'reviewer/review/reviewStepHeader.tpl':
            case 'reviewer/review/step3.tpl':
                // Log the request as an article view.
                $stepTitle = $templateManager->get_template_vars('reviewStepHeader');
                $article = $templateManager->get_template_vars('step3');
                print_r($stepTitle);
                print_r($article);
            //exit();
        }
*/
        return false;
    }


    function handleRequest($hookName, $args)
    {
        $page =& $args[0];
        $op =& $args[1];
        $sourceFile =& $args[2];

        // If the request is for the log analyzer itself, handle it.
        if ($page === 'counter' && in_array($op, array('index', 'reportXML', 'sushiXML', 'report'))) {
            $this->import('CounterHandler');
            Registry::set('plugin', $this);
            define('HANDLER_CLASS', 'CounterHandler');
            return true;
        }

        return false;
    }

}