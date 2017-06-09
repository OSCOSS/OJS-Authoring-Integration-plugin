# OJS-FidusWriter

OJS-FidusWriter is an Open Journal Systems (OJS) plugin to connect an OJS instance with Fidus Writer to from an integrated
publishing system. 
This plugin has to be combined with the [FidusWriter-OJS plugin](https://github.com/fiduswriter/fiduswriter-ojs) for Fidus Writer.

Project page:
https://www.fiduswriter.org/ojs-integration/


## Installation:

1. Follow the instructions for the FidusWriter-OJS plugin to install Fidus Writer and the connector on the 
   Fidus Writer side: https://github.com/fiduswriter/fiduswriter-ojs

2. Install OJS

An installation of OJS is needed. To install OJS please follow the instructions at https://github.com/pkp/ojs/

To install the latest version, please check out the master branch.

3. Setup at least two journals on the OJS instance

This step is required to make the global settings show in the OJS menus.

4. Download plugin files

Download and copy the plugin files from github into plugins/generic/fidusWriter inside your OJS folder.
Create the folder if it does not exist. You can achieve this by running these commands:

```
cd plugins/generic/
git clone https://github.com/fiduswriter/ojs-fiduswriter.git fidusWriter
cd ../..
```
5. Register plugin with OJS

Run the upgrade script to register the plugin with the system by running:

```
php tools/upgrade.php upgrade
```

6. Activate plugin in OJS

Enable the plugin via the OJS website interface:

Make sure you have set up at least one journal on your site. Otherwise the settings menu does not show.

Open the OJS interface and select "ENABLE" under the settings "Fidus Writer Integration Plugin" under the following routes:

 setting > website > plugins


7. Configure the API key in OJS

Come up with an API Key to allow secure communications between Fidus Writer and OJS. This is just a single long text string that you should not share with anyone that will need to be entered in the configurations of Fidus Writer and OJS. Be cautious: The key allows automatic login into Fidus Writer and OJS in various ways, so do not share it!

To set the key in OJS, go to the settings of the Fidus Writer integration plugin under the following routes:

setting > website > plugins -> Fidus Writer Integration plugin (triangle to left) -> Settings -> Enter API key -> Save.


8. Activate connection on Fidus Writer side

Enter the administration interface at your Fidus Writer installation (http://myserver.com/admin).

In the section "Custom views" click on "Register journal". Enter the URL and API Key (from step 4) pf your OJS installation.


## Credits

This plugin has been developed by the [Opening Scolarly Communications in the Social Sciences (OSCOSS)](http://www.gesis.org/?id=10714) project, financed by the German Research Foundation (DFG) and executed by the University of Bonn and GESIS – Leibniz Institute for the Social Sciences. 

Lead Developer: [Afshin Sadeghi](https://github.com/sadeghiafshin)

## License

This software is released under the the GNU General Public License v2.0.

See the file License.md included with this distribution for the terms of this license.

Third parties are welcome to modify and redistribute the plugin in entirety or parts according to the terms of this license. We also welcome patches for improvements or bug fixes to the software.
