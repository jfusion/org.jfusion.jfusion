#!/bin/bash

FULLPATH="$PWD"

if [ -f "/usr/bin/7z" ]; then
   USEZIPCMD="7z"
   ZIPCMD="/usr/bin/7z"
elif [ -f "/usr/bin/zip" ]; then
   USEZIPCMD="zip"
   ZIPCMD="/usr/bin/zip"
else
    echo "No zip program found!  Install p7zip-full or zip!"
    exit 0
fi

case $1 in
	clear_packages)
		echo "delete old package zip files"
		cd $FULLPATH
		rm administrator/components/com_jfusion/packages/*.zip
		
		;;
	clear_main)
		echo "delete old main zip files"
		cd $FULLPATH
		rm *.zip

		;;
	clear)
		$0 clear_main
		$0 clear_packages
		
		;;
	create_packages)
		$0 clear_packages

		echo "create the new packages for the plugins and module"
		
		#login module has folders thus has to be treated differently

        if [ "$USEZIPCMD" == "zip" ];
        then
		    cd $FULLPATH/modules/mod_jfusion_login
		    $ZIPCMD -r $FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_login.zip . -x *.svn*  > /dev/null
		    cd $FULLPATH

		    cd $FULLPATH/modules/mod_jfusion_activity
		    $ZIPCMD -r $FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_activity.zip . -x *.svn*  > /dev/null
		    cd $FULLPATH

		    cd $FULLPATH/modules/mod_jfusion_whosonline
		    $ZIPCMD -r $FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_whosonline.zip . -x *.svn*  > /dev/null
		    cd $FULLPATH

		    cd $FULLPATH/modules/mod_jfusion_user_activity
		    $ZIPCMD -r $FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_user_activity.zip . -x *.svn*  > /dev/null
		    cd $FULLPATH

		    $ZIPCMD -rj $FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_auth.zip plugins/authentication -x *.svn* > /dev/null
		    $ZIPCMD -rj $FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_user.zip plugins/user -x *.svn* > /dev/null
		    $ZIPCMD -rj $FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_search.zip plugins/search -x *.svn* > /dev/null

		    cd $FULLPATH/plugins/content
		    $ZIPCMD -r $FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_content.zip . -x *.svn*  > /dev/null
		    cd $FULLPATH

		    $ZIPCMD -j $FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_system.zip plugins/system/jfusion.php plugins/system/jfusion.xml -x *.svn* > /dev/null
		    $ZIPCMD -rj $FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_override.zip plugins/override -x *.svn* > /dev/null
		    
		    cd $FULLPATH/modules/mod_jfusion_magecart
		    $ZIPCMD -r $FULLPATH/side_projects/magento/jfusion_mod_magecart.zip . -x *.svn*  > /dev/null
		    
		    cd $FULLPATH/modules/mod_jfusion_mageselectblock
		    $ZIPCMD -r $FULLPATH/side_projects/magento/jfusion_mod_mageselectblock.zip . -x *.svn*  > /dev/null
		    
		    cd $FULLPATH/modules/mod_jfusion_magecustomblock
		    $ZIPCMD -r $FULLPATH/side_projects/magento/jfusion_mod_magecustomblock.zip . -x *.svn*  > /dev/null
		    
		    cd $FULLPATH/plugins/system
            $ZIPCMD -r $FULLPATH/side_projects/magento/jfusion_plugin_magelib.zip ./magelib.* -x *.svn*  > /dev/null

            cd $FULLPATH/components/com_jfusion/plugins/dokuwiki
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_dokuwiki.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/efront
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_efront.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/elgg
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_elgg.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/gallery2
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_gallery2.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/joomla_ext
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_joomla_ext.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/magento
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_magento.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/mediawiki
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_mediawiki.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/moodle
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_moodle.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/mybb
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_mybb.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/oscommerce
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_oscommerce.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/phpbb3
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_phpbb3.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/prestashop
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_prestashop.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/smf
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_smf.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/smf2
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_smf2.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/universal
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_universal.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/vbulletin
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_vbulletin.zip" -x *.svn* > /dev/null
            cd $FULLPATH/components/com_jfusion/plugins/wordpress
            $ZIPCMD -r "$FULLPATH/pluginpackages/jfusion_wordpress.zip" -x *.svn* > /dev/null
		    
        else
            cd $FULLPATH/modules/
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_activity.zip" ./mod_jfusion_activity/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_login.zip" ./mod_jfusion_login/* -r -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_whosonline.zip" ./mod_jfusion_whosonline/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_mod_user_activity.zip" ./mod_jfusion_user_activity/* -xr!*.svn* > /dev/null          

            cd $FULLPATH/plugins/
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_auth.zip" ./authentication/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_user.zip" ./user/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_search.zip" ./search/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_content.zip" ./content/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/administrator/components/com_jfusion/packages/jfusion_plugin_system.zip" ./system/* -xr!*.svn* > /dev/null

            echo "create the new packages for the Magento Integration"
            cd $FULLPATH/modules/
            $ZIPCMD a "$FULLPATH/side_projects/magento/jfusion_mod_magecart.zip" ./mod_jfusion_magecart/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/side_projects/magento/jfusion_mod_mageselectblock.zip" ./mod_jfusion_mageselectblock/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/side_projects/magento/jfusion_mod_magecustomblock.zip" ./mod_jfusion_magecustomblock/* -xr!*.svn* > /dev/null

            cd $FULLPATH/plugins/
            $ZIPCMD a "$FULLPATH/side_projects/magento/jfusion_plugin_magelib.zip" ./system/magelib.* -xr!*.svn* > /dev/null

            cd $FULLPATH/components/com_jfusion/plugins
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_dokuwiki.zip" ./dokuwiki/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_efront.zip" ./efront/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_elgg.zip" ./elgg/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_gallery2.zip" ./gallery2/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_joomla_ext.zip" ./joomla_ext/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_magento.zip" ./magento/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_mediawiki.zip" ./mediawiki/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_moodle.zip" ./moodle/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_mybb.zip" ./mybb/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_oscommerce.zip" ./oscommerce/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_phpbb3.zip" ./phpbb3/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_prestashop.zip" ./prestashop/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_smf.zip" ./smf/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_smf2.zip" ./smf2/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_vbulletin.zip" ./vbulletin/* -xr!*.svn* > /dev/null
            $ZIPCMD a "$FULLPATH/pluginpackages/jfusion_wordpress.zip" ./wordpress/* -xr!*.svn* > /dev/null

            cd $FULLPATH
        fi
		;;
	create_main)
		$0 clear_main

		echo "Prepare the files for packaging"
		mkdir tmp
		mkdir tmp/admin
		rsync -r --exclude=".*/" components/com_jfusion/* tmp/admin

        rsync -r --exclude=".*/" pluginpackages/* tmp/admin/packages

		rm tmp/admin/jfusion.xml
		
		mkdir tmp/admin/languages
		rsync -r  --exclude=".*/" administrator/language/* tmp/admin/languages

		mkdir tmp/front
		rsync -r  --exclude=".*/" --exclude="plugins" components/com_jfusion/* tmp/front
		
        mkdir tmp/front/plugins
        mkdir tmp/front/plugins/joomla_int
		rsync -r --exclude=".*/" components/com_jfusion/plugins/joomla_int/* tmp/front/plugins/joomla_int		

		mkdir tmp/front/languages
		rsync -r  --exclude=".*/" language/* tmp/front/languages/
		
		rsync administrator/components/com_jfusion/jfusion.xml administrator/components/com_jfusion/install.jfusion.php administrator/components/com_jfusion/uninstall.jfusion.php tmp/ 
		
		echo "Update the revision number"
		if which svnversion &> /dev/null; then
			mv tmp/jfusion.xml tmp/jfusion.tmp
    		x=$(svnversion)
    		VERSION=${x%%M*}
    		VERSION=${VERSION##*:}
    		sed "s/<revision>\$revision\$<\/revision>/<revision>$VERSION<\/revision>/g" tmp/jfusion.tmp > tmp/jfusion.xml
    		rm tmp/jfusion.tmp
		else
    		echo "svnversion is not available.  Install subversion command line client."
		fi	
		echo "Revision set to $VERSION"
		
		
		echo "Create the new master package"

        if [ "$USEZIPCMD" == "zip" ];
        then
            cd tmp
		    $ZIPCMD -r $FULLPATH/jfusion_package.zip . > /dev/null
        else
            cd $FULLPATH
            $ZIPCMD a "$FULLPATH/jfusion_package.zip" ./tmp/* -xr!*.svn* > /dev/null
        fi
	
		echo "Create a ZIP containing all files to allow for easy updates"

		cd $FULLPATH

        if [ "$USEZIPCMD" == "zip" ];
        then
	    	$ZIPCMD -r jfusion_files.zip administrator components language modules plugins -x *.svn* > /dev/null
        else
            $ZIPCMD a "$FULLPATH/jfusion_files.zip" administrator components language modules plugins -r -xr!*.svn* > /dev/null
        fi            

		echo "Remove temporary files"
		rm -r tmp
		
		;;
	create)
		$0 create_packages
		$0 create_main

		;;

	*)
		echo "Usage $FULLPATH/create_package.sh {clear_packages|clear_main|clear|create_main|create_packages|create}"
		;;
esac

exit 0
