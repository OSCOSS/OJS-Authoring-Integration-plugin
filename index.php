<?php
/**
 * Project OSCOSS
 * University of Bonn
 * User: Afshin Sadeghi sadeghi@cs.uni-bonn.de
 * @ingroup plugins_generic_ojsIntegrationRestApi
 * Date: 13/06/16
 * Time: 14:59
 */

/**
 * @defgroup plugins_generic_ojsIntegrationRestApi ojs anf online authoring and editoring systems integration Plugin
 * @brief Wrapper for ojsIntegrationRestApi plugin.
 */


if (!class_exists('IntegrationApiPlugin')) {

    require_once('IntegrationApiPlugin.inc.php');
    return new IntegrationApiPlugin();
}
?>
