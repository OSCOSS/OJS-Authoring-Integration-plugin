<?php
/**
 * University of Bonn
 * User: Afshin Sadeghi
 * @ingroup plugins_generic_osjfw
 * @brief Wrapper for Web Feeds plugin.
 * Date: 13/06/16
 * Time: 14:59
 */

/**
 * @defgroup plugins_generic_ojsfw ojs fw integration Plugin
 */

if (!class_exists('OJSFWIntegrationPlugin')) {

    require_once('OJSFWIntegrationPlugin.inc.php');

    return new OJSFWIntegrationPlugin();
}
?>
