#!/bin/bash
createxml(){
		FILE=$1
		mv $FILE.xml $FILE.tmp
		sed "s/<revision>\$revision\$<\/revision>/<revision>$REVISION<\/revision>/g" $FILE.tmp > $FILE.xml
		mv $FILE.xml $FILE.tmp
		TIMESTAMP=$(date +%s)
		sed "s/<timestamp>\$timestamp\$<\/timestamp>/<timestamp>$TIMESTAMP<\/timestamp>/g" $FILE.tmp > $FILE.xml
		rm $FILE.tmp
}
createpackage(){
	TARGETPATH=$1
	TARGETDEST=$2
	XMLFILE=$3

	if [ -z "$3" ]
	then
		XMLFILE=jfusion
	fi

	mkdir $FULLPATH/tmppackage

	rsync -r --exclude=".*/" $FULLPATH/$TARGETPATH $FULLPATH/tmppackage/

	createxml $FULLPATH/tmppackage/$XMLFILE

	if [ "$USEZIPCMD" == "zip" ];
	then
	  cd $FULLPATH/tmppackage
    $ZIPCMD -r $FULLPATH/$TARGETDEST . -x *.svn*  > /dev/null
	else
		$ZIPCMD a "$FULLPATH/$TARGETDEST" $FULLPATH/tmppackage/* -xr!*.svn* > /dev/null
	fi

	rm -r $FULLPATH/tmppackage
	
	cd $FULLPATH
}

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

REVISION=Unknown
if which git &> /dev/null; then
		REVISION=$(git rev-parse HEAD)
else
		echo "git is not available.  Install git command line client."
fi
TIMESTAMP=$(date +%s)

case $1 in
	clear_packages)
		echo "delete old package zip files"
		rm $FULLPATH/administrator/components/com_jfusion/packages/*.zip
		
		;;
	clear_main)
		echo "delete old main zip files"
		rm $FULLPATH/*.zip

		;;
	clear)
		$0 clear_main
		$0 clear_packages
		
		;;
	create_packages)
		$0 clear_packages

		echo "create the new packages for the plugins and module"
		
		#login module has folders thus has to be treated differently

				createpackage modules/mod_jfusion_login/ administrator/components/com_jfusion/packages/jfusion_mod_login.zip mod_jfusion_login
				createpackage modules/mod_jfusion_activity/ administrator/components/com_jfusion/packages/jfusion_mod_activity.zip mod_jfusion_activity
				createpackage modules/mod_jfusion_whosonline/ administrator/components/com_jfusion/packages/jfusion_mod_whosonline.zip mod_jfusion_whosonline
				createpackage modules/mod_jfusion_user_activity/ administrator/components/com_jfusion/packages/jfusion_mod_user_activity.zip mod_jfusion_user_activity

				createpackage plugins/authentication/ administrator/components/com_jfusion/packages/jfusion_plugin_auth.zip
				createpackage plugins/user/ administrator/components/com_jfusion/packages/jfusion_plugin_user.zip
				createpackage plugins/search/ administrator/components/com_jfusion/packages/jfusion_plugin_search.zip
				createpackage plugins/content/ administrator/components/com_jfusion/packages/jfusion_plugin_content.zip
				createpackage "plugins/system/jfusion.*" administrator/components/com_jfusion/packages/jfusion_plugin_system.zip
				
				
				createpackage modules/mod_jfusion_magecart/ side_projects/magento/jfusion_mod_magecart.zip mod_jfusion_magecart
				createpackage modules/mod_jfusion_mageselectblock/ side_projects/magento/jfusion_mod_mageselectblock.zip mod_jfusion_mageselectblock
				createpackage modules/mod_jfusion_magecustomblock/ side_projects/magento/jfusion_mod_magecustomblock.zip mod_jfusion_magecustomblock
				createpackage "plugins/system/magelib.*" administrator/components/com_jfusion/packages/jfusion_plugin_magelib.zip magelib
				
				
				createpackage components/com_jfusion/plugins/dokuwiki/ pluginpackages/jfusion_dokuwiki.zip
				createpackage components/com_jfusion/plugins/efront/ pluginpackages/jfusion_efront.zip
				createpackage components/com_jfusion/plugins/elgg/ pluginpackages/jfusion_elgg.zip
				createpackage components/com_jfusion/plugins/gallery2/ pluginpackages/jfusion_gallery2.zip
				createpackage components/com_jfusion/plugins/joomla_ext/ pluginpackages/joomla_ext.zip
				createpackage components/com_jfusion/plugins/joomla_int/ pluginpackages/jfusion_joomla_int.zip
				createpackage components/com_jfusion/plugins/magento/ pluginpackages/jfusion_magento.zip
				
				createpackage components/com_jfusion/plugins/mediawiki/ pluginpackages/jfusion_mediawiki.zip
				
				createpackage components/com_jfusion/plugins/moodle/ pluginpackages/jfusion_moodle.zip
				
				
				createpackage components/com_jfusion/plugins/mybb/ pluginpackages/jfusion_mybb.zip
				createpackage components/com_jfusion/plugins/oscommerce/ pluginpackages/jfusion_oscommerce.zip
				createpackage components/com_jfusion/plugins/phpbb3/ pluginpackages/jfusion_phpbb3.zip
				createpackage components/com_jfusion/plugins/prestashop/ pluginpackages/jfusion_prestashop.zip
				createpackage components/com_jfusion/plugins/smf/ pluginpackages/jfusion_smf.zip
				createpackage components/com_jfusion/plugins/smf2/ pluginpackages/jfusion_smf2.zip
				
				createpackage components/com_jfusion/plugins/universal/ pluginpackages/jfusion_universal.zip
				createpackage components/com_jfusion/plugins/vbulletin/ pluginpackages/jfusion_vbulletin.zip
				createpackage components/com_jfusion/plugins/wordpress/ pluginpackages/jfusion_wordpress.zip
		;;
	create_main)
		$0 clear_main

		echo "Prepare the files for packaging"
		cd $FULLPATH
		mkdir tmp
		mkdir tmp/admin
		rsync -r --exclude=".*/" administrator/components/com_jfusion/* tmp/admin

#  rsync -r --exclude=".*/" pluginpackages/* tmp/admin/packages

		rm tmp/admin/jfusion.xml
		
		mkdir tmp/admin/languages
		rsync -r  --exclude=".*/" administrator/language/en-GB/* tmp/admin/languages/en-GB

		mkdir tmp/front
		rsync -r  --exclude=".*/" --exclude="plugins" components/com_jfusion/* tmp/front

		mkdir tmp/front/languages
		rsync -r  --exclude=".*/" language/en-GB/* tmp/front/languages/en-GB/
		
		rsync administrator/components/com_jfusion/jfusion.xml administrator/components/com_jfusion/install.jfusion.php administrator/components/com_jfusion/uninstall.jfusion.php tmp/ 
		
		echo "Update the revision number"

		echo "Revision set to $REVISION"
		echo "Timestamp set to $TIMESTAMP"
		
		createxml tmp/jfusion
		
		echo "Create the new master package"

    if [ "$USEZIPCMD" == "zip" ];
    then
        cd tmp
    		$ZIPCMD -r $FULLPATH/jfusion_package.zip . > /dev/null
    else
        $ZIPCMD a "$FULLPATH/jfusion_package.zip" $FULLPATH/tmp/* -xr!*.svn* > /dev/null
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