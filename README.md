OJS-Authoring-Integration-plugin
===========

Bonn University - GESIS Institute
----
OSCOSS Project
----

#OJS-Authoring-Integration-plugin
Rest API Plugin for OJS to work with external web-based authoring and text editing and publishing systems. Open joural sysems is an opensource journal management and publishing system that has been developed by the Public Knowledge Project. This Plugin for OJS creates a unified authoring, reviewing and publishing system by integrating the matching services of Fiduswriter online shared authoring tool and the OJS system.


Github source code of the plugin:
https://github.com/OSCOSS/OJS-Authoring-Integration-plugin

Open journal system:
https://pkp.sfu.ca/ojs/

Fiduswriter:
https://www.fiduswriter.org/


####Use case : FidusWriter & OJS
Our focus of use case was the working with FidusWriter collaborative authoring tool(https://www.fiduswriter.org) and OJS while the the code is written as open as possible for any system that can deploy Rest API that provides online authoring and  intends to benefit from a workflow managment system.

##Installation:
#####1.First step
An installation of OJS is needed. To install OJS please followup its readme in https://github.com/pkp/ojs/

To have its latest version please check out the master branch.

#####2.Second step
Download and copy the plugin files from github into plugins/generic/ojsAuthoringIntegration folder inside your OJS folder.
Create the folder if it does not exist. You can achieve this by running these commands:

```
cd plugins/generic/
git clone https://github.com/OSCOSS/OJS-Authoring-Integration-plugin.git
mv OJS-Authoring-Integration-plugin ojsAuthoringIntegration
cd ../..
```

Then, run the upgrade script to register the plugin with the system by running:

```
php tools/upgrade.php upgrade
```

#####3.To Activate on the OJS end:
Enable the plugin via the OJS website interface:

Make sure you have set up at least one journal on your site. Otherwise the settings menu does not show.

Open the OJS interface and select "ENABLE" under Settings "OJS REST API Integration Plugin" under the following routes:

 in OJS 3.0 :
 
 setting > website > plugins

in OJS < 3.0:
 
Home > User > Journal Management > Plugin Management

#####4.To Activate on the Editor end:

Set the OJS_URL to the base URL of your OJS installation (for example: "http://www.myojssite.com").

Set the OJS_KEY to "d5PW586jwefjn!3fv".

In the case of Fidus Writer, these settings are in the configuration.php file in the section SERVER_INFO.

##Documentation

Provided calls:
GET: RestApiGatewayPlugin/Journals?userEmail="Afshin@test.com"

####OJS 3 usage manual:
https://pkp.gitbooks.io/ojs3/content/en

####Sample OJS plugin:
https://pkp.sfu.ca/ojs/docs/technicalreference/2.1/pluginsSamplePlugin.html

####OJS 3 code report:
https://pkp.sfu.ca/ojs/doxygen/master/html/modules.html

####Ojs plugins basics:
https://pkp.sfu.ca/ojs/docs/technicalreference/2.1/plugins.html

####About Gateway Plugins
https://pkp.sfu.ca/ojs/docs/userguide/2.3.3/journalManagementGatewayPlugins.html
