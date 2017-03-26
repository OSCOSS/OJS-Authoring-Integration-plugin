Bonn University - GESIS Institute
#####OSCOSS Project


#FidusWriterPlugin
This plugin allows the Open Journal Systems (OJS) to work with Fidus Writer instances as one integrated publishing system.
OJS is an open source journal management and publishing system that has been developed by the Public Knowledge Project. Fidus Writer is an open source academic collaborative and online text processor.


Source code of the plugin:
https://github.com/OSCOSS/OJSIntegrationRestAPIplugin

Open Journal Systems (OJS):
https://pkp.sfu.ca/ojs/

Fiduswriter:
https://www.fiduswriter.org/


####Use case: FidusWriter & beyond
This plugin has been programmed with the use case of integrating Fidus Writer, but we have written it as open so that in the future it will hopefully be possible to make it work with a range of different online text processors. For this reason, we have tried to incorporate as much of a REST API as possible.

##Installation:
#####1.First step
An installation of OJS is needed. To install OJS please follow the instructions at https://github.com/pkp/ojs/

To install the latest version, please check out the master branch.

#####2.Second step
Download and copy the plugin files from github into plugins/generic/fidusWriter inside your OJS folder.
Create the folder if it does not exist. You can achieve this by running these commands:

```
cd plugins/generic/
git clone https://github.com/OSCOSS/OJSIntegrationRestAPIplugin.git
mv OJSIntegrationRestAPIplugin fidusWriter
cd ../..
```

Then, run the upgrade script to register the plugin with the system by running:

```
php tools/upgrade.php upgrade
```

#####3. Activate the plugin in OJS:
Enable the plugin via the OJS website interface:

Make sure you have set up at least one journal on your site. Otherwise the settings menu does not show.

Open the OJS interface and select "ENABLE" under the settings "Fidus Writer Integration Plugin" under the following routes:

 in OJS 3.0 :

 setting > website > plugins

in OJS < 3.0:

Home > User > Journal Management > Plugin Management

#####4. Set the API KEY:
Come up with an API Key to allow secure communications between Fidus Writer and OJS. This is just a single long text string that you should not share with anyone that will need to be entered in the configurations of Fidus Writer and OJS. Be cautious: The key allows automatic login into Fidus Writer and OJS in various ways, so do not share it!

To set the key in OJS, go to the settings of the Fidus Writer integration plugin under the following routes:

in OJS 3.0 :

setting > website > plugins -> Fidus Writer Integration plugin (triangle to left) -> Settings -> Enter API key -> Save.


#####5.To Activate on the Editor end:

Set the OJS_URL to the base URL of your OJS installation (for example: "http://www.myojssite.com").

Set the OJS_KEY to the API Key chosen in step 4.

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
