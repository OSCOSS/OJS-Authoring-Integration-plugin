Bonn University - GESIS Institute
#####OSCOSS Project


#OJSIntegrationRestAPIplugin
Rest API Plugin for OJS to work with external web based authoring and text editing systems

##Use case : FidusWriter
Our focus of use case was the working with FidusWriter collaborative authoring tool(https://www.fiduswriter.org) while
the the code is written as open as possible for any software that can deploy Rest API to integrate with OJS.

Github source code of the plugin:
https://github.com/OSCOSS/OJSIntegrationRestAPIplugin


#Installation:
Copy the plugin files into plugins/generic/ojsIntegrationRestApi folder.
Create the folder if it does not exist.

####To Activate:
 Open the OJS interface and select "ENABLE" under Settings "OJS REST API Integration Plugin" in the routs below:

 in OJS 3.0 :
 setting > website > plugins
 in OJS < 3.0:
Home > User > Journal Management > Plugin Management

#Documentation

Provided calls:
GET: RestApiGatewayPlugin/Journals?userEmail="Afshin@test.com"

###OJS 3 usage manual:
https://pkp.gitbooks.io/ojs3/content/en

###Sample OJS plugin:
https://pkp.sfu.ca/ojs/docs/technicalreference/2.1/pluginsSamplePlugin.html

###OJS 3 code report:
https://pkp.sfu.ca/ojs/doxygen/master/html/modules.html

###Ojs plugins basics:
https://pkp.sfu.ca/ojs/docs/technicalreference/2.1/plugins.html

###About Gateway Plugins
https://pkp.sfu.ca/ojs/docs/userguide/2.3.3/journalManagementGatewayPlugins.html