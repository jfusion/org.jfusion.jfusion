HOW TO USE THE CREATE RELEASE SCRIPTS

Windows:
1) The following tools must be installed in which all can be obtained from the create_release_tools:
	7za.exe -> copy to c:\WINDOWS\system32
	sed.exe -> copy to C:\Program Files (x86)\GnuWin32\bin
	You must have Git, you can get it here http://git-scm.com/downloads you must install it so that you can use it from command line
2) Make sure that your local copy is updated to the latest GIT revision (otherwise the revision number will be set to a range of revisions rather the latest)
3) Run the bat file by either
	a) Open a command prompt (Start -> Run -> cmd)
	b) cd into your local copy of the GIT repository
	c) Type create_release.bat

	OR

	a) Browsing to the local copy of the GIT repository and double clicking the create_release.bat file
4) Input the number corresponding to the action you want to take and hit enter.


Linux:
1) You must have Git, you can get it here http://git-scm.com/downloads you must install it so that you can use it from command line
2) cd into your local copy of the GIT repository
3) Type "./create_release.sh ACTION"
Action can be one of the following
	clear_packages - deletes the module and plugin packages in administrator/components/com_jfusion/packages
	clear_main - deletes the jfusion_package.zip and jfusion_files.zip
	clear - deletes all of the above

	create_main - creates the jfusion_package.zip and jfusion_files.zip
	create_packages - creates the zips for the modules and plugins in administrator/components/com_jfusion/packages
	create - creates all of the above

IMPORTANT NOTICE ABOUT GIT:
The script will attempt to automatically update the revision variable in the component's jfusion.xml.  It uses the command git rev-parse HEAD
to accomplish this. In the jfusion.xml file, there is a placeholder $revision$ that must
remain in order for the script to work correctly. Before building a package to be committed, make sure you have updated your working copy
to the latest revision.  Then run the create_release.sh script.


If you prefer a Linux GUI, create a file (with either .sh or no extension) and add the following to it (note that you need zenity installed):

#!/bin/sh

DIR=/path/to/my/local/GIT/repository

cd $DIR;

ACTION=$(zenity  --list  --text "JFusion Packager" --radiolist  \
	--column "Choose" \
	--column "Action" \
		TRUE "Create Main" \
		FALSE "Create Packages" \
		FALSE "Create Main and Packages" \
		FALSE "Delete Main" \
		FALSE "Delete Packages" \
		FALSE "Delete Main and Packages"); 

case $ACTION in
	"Create Main") 
		./create_release.sh create_main
		;;
	"Create Packages")
		./create_release.sh create_packages
		;;
	"Create Main and Packages")
		./create_release.sh create
		;;
	"Delete Main")
		./create_release.sh clear_main
		;;
	"Delete Packages")
		./create_release.sh clear_packages
		;;
	"Delete Main and Packages")
		./create_release.sh clear
		;;
esac

exit 0
