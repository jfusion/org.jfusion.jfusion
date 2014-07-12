#!/bin/bash
createxml(){
	FILE=$1
	mv ${FILE}.xml ${FILE}.tmp
	sed "s/<revision>\$revision\$<\/revision>/<revision>${REVISION}<\/revision>/g" ${FILE}.tmp > ${FILE}.xml
	mv ${FILE}.xml ${FILE}.tmp
	sed "s/<timestamp>\$timestamp\$<\/timestamp>/<timestamp>${TIMESTAMP}<\/timestamp>/g" ${FILE}.tmp > ${FILE}.xml
	rm ${FILE}.tmp
}
createpackage(){
	TARGETPATH=$1
	TARGETDEST=$2
	XMLFILE=$3

	if [ -z "$3" ]
	then
		XMLFILE=jfusion
	fi

		echo "Creating: " ${TARGETDEST}

		mkdir ${FULLPATH}/tmppackage

		rsync -r --exclude=".*/" ${FULLPATH}/${TARGETPATH} ${FULLPATH}/tmppackage/

		createxml ${FULLPATH}/tmppackage/${XMLFILE}

		if [ "$USEZIPCMD" == "zip" ];
		then
		  cd ${FULLPATH}/tmppackage
			${ZIPCMD} -r ${FULLPATH}/${TARGETDEST} . -x *.svn*  > /dev/null
		else
			${ZIPCMD} a "${REVISION}/${TARGETDEST}" ${FULLPATH}/tmppackage/* -xr!*.svn* > /dev/null
		fi
	rm -r ${FULLPATH}/tmppackage
	
	cd ${FULLPATH}
}

FULLPATH=$(dirname $(readlink -f $0))

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
		rm ${FULLPATH}/administrator/components/com_jfusion/packages/*.zip

		;;
	clear_main)
		echo "delete old main zip files"
       	rm ${FULLPATH}/*.zip

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
		createpackage "plugins/system/magelib.*" side_projects/magento/jfusion_plugin_magelib.zip magelib


		cd  ${FULLPATH}
		if [ -d "pluginpackages" ]; then
			rm  pluginpackages -R
        fi
		mkdir pluginpackages

		for i in components/com_jfusion/plugins/*
		do
        	if [ -d "$i" ]; then
        		if [ -e ${i}/jfusion.xml ]; then
                	createpackage ${i}"/" pluginpackages/jfusion_$(basename "$i").zip
               	else
               		echo Error: ${i}/jfusion.xml was not found
               	fi
        	fi
		done

		;;
	create_main)
		$0 clear_main

		echo "Prepare the files for packaging"
		cd ${FULLPATH}
		mkdir tmp
		mkdir tmp/admin
		rsync -r --exclude=".*/" administrator/components/com_jfusion/* tmp/admin

		rsync -r --exclude=".*/" pluginpackages/* tmp/admin/packages

		rm tmp/admin/jfusion.xml
		
		mkdir tmp/admin/language
		rsync -r  --exclude=".*/" administrator/language/en-GB/* tmp/admin/language/en-GB

		mkdir tmp/front
		rsync -r  --exclude=".*/" --exclude="plugins" components/com_jfusion/* tmp/front

		mkdir tmp/front/language
		rsync -r  --exclude=".*/" language/en-GB/* tmp/front/language/en-GB/
		
		rsync administrator/components/com_jfusion/jfusion.xml administrator/components/com_jfusion/script.php tmp/
		
		echo "Update the revision number"

		echo "Revision set to $REVISION"
		echo "Timestamp set to $TIMESTAMP"
		
		createxml tmp/jfusion
		
		echo "Create the new master package"

		if [ "$USEZIPCMD" == "zip" ];
		then
			cd tmp
			${ZIPCMD} -r ${FULLPATH}/jfusion_package.zip . > /dev/null
		else
			${ZIPCMD} a "${REVISION}/jfusion_package.zip" ${FULLPATH}/tmp/* -xr!*.svn* > /dev/null
		fi
	
		echo "Remove temporary files"
		cd ${FULLPATH}
		rm -r tmp
		
		;;
	create)
		$0 create_packages
		$0 create_main

		;;

	*)
		echo "Usage ${REVISION}/create_package.sh {clear_packages|clear_main|clear|create_main|create_packages|create}"
		;;
esac

exit 0