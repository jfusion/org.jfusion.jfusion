@echo off
SET FULLPATH=%CD%
SET PLUGIN_DIR="%FULLPATH%\components\com_jfusion\plugins"
setlocal enableextensions


:START
IF NOT EXIST administrator echo JFusion files not found. goto end

echo Looking for required commands...
IF NOT EXIST c:\WINDOWS\system32\7za.exe (
	echo "7za.exe does not exist!  Please see create_release_readme.txt".
	goto end
)
IF NOT EXIST c:\WINDOWS\system32\sed.exe (
	echo "sed.exe does not exist! Please see create_release_readme.txt". 
	goto end
)
IF NOT EXIST "C:\Program Files\Git\bin\git.exe"  (
	IF NOT EXIST "C:\Program Files (x86)\Git\bin\git.exe"  (
		echo "Git client not installed!  Please see create_release_readme.txt". 
		goto end
	)
)

cls
echo Choices:
echo 1 - Create Main Packages
echo 2 - Create Plugin and Module Packages
echo 3 - Create All Packages
echo 4 - Delete Main Packages
echo 5 - Delete Plugin and Module Packages
echo 6 - Delete All Packages
set /p useraction=Choose a number(1-6):
set action=%useraction:~0,1%


IF "%action%"=="6" goto CLEAR_ALL
IF "%action%"=="5" goto CLEAR_PACKAGES
IF "%action%"=="4" goto CLEAR_MAIN
IF "%action%"=="3" goto CREATE_ALL
IF "%action%"=="2" goto CREATE_PACKAGES
IF "%action%"=="1" goto CREATE_MAIN
echo Invalid Choice
goto start

:CLEAR_ALL
echo Clearing All Packages
goto CLEAR_MAIN
goto CLEAR_PACKAGES
goto end

:CLEAR_PACKAGES
echo Remove module and plugin packages
del "%FULLPATH%\administrator\components\com_jfusion\packages\*.zip"
IF "%action%"=="5" goto end

:CLEAR_MAIN
echo Remove main packages
del *.zip
IF "%action%"=="4" goto end

:CREATE_ALL
goto create_packages
goto create_main
goto end

:CREATE_PACKAGES
del "%FULLPATH%\administrator\components\com_jfusion\packages\*.zip"

echo Create the new packages for the plugins and module
chdir %FULLPATH%\modules\
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_mod_activity.zip" .\mod_jfusion_activity\* -xr!*.svn* > NUL
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_mod_login.zip" .\mod_jfusion_login\* -r -xr!*.svn* > NUL
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_mod_whosonline.zip" .\mod_jfusion_whosonline\* -xr!*.svn* > NUL
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_mod_user_activity.zip" .\mod_jfusion_user_activity\* -xr!*.svn* > NUL

chdir %FULLPATH%\plugins\
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_plugin_auth.zip" .\authentication\* -xr!*.svn* > NUL
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_plugin_user.zip" .\user\* -xr!*.svn* > NUL
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_plugin_search.zip" .\search\* -xr!*.svn* > NUL
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_plugin_content.zip" .\content\* -xr!*.svn* > NUL
7za a "%FULLPATH%\administrator\components\com_jfusion\packages\jfusion_plugin_system.zip" .\system\jfusion.* -xr!*.svn* > NUL


echo "create the jfusion plugin packages"
chdir %FULLPATH%\components\com_jfusion\plugins
7za a "%FULLPATH%\pluginpackages\jfusion_dokuwiki.zip" .\dokuwiki\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_efront.zip" .\efront\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_elgg.zip" .\elgg\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_gallery2.zip" .\gallery2\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_joomla_ext.zip" .\joomla_ext\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_joomla_int.zip" .\joomla_int\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_magento.zip" .\magento\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_mediawiki.zip" .\mediawiki\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_moodle.zip" .\moodle\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_mybb.zip" .\mybb\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_oscommerce.zip" .\oscommerce\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_phpbb3.zip" .\phpbb3\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_prestashop.zip" .\prestashop\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_smf.zip" .\smf\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_smf2.zip" .\smf2\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_universal.zip" .\universal\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_vbulletin.zip" .\vbulletin\* -xr!*.svn* > NUL
7za a "%FULLPATH%\pluginpackages\jfusion_wordpress.zip" .\wordpress\* -xr!*.svn* > NUL

echo "create the new packages for the Magento Integration"
chdir %FULLPATH%\modules\
7za a "%FULLPATH%\side_projects\magento\jfusion_mod_magecart.zip" .\mod_jfusion_magecart\* -xr!*.svn* > NUL
7za a "%FULLPATH%\side_projects\magento\jfusion_mod_mageselectblock.zip" .\mod_jfusion_mageselectblock\* -xr!*.svn* > NUL
7za a "%FULLPATH%\side_projects\magento\jfusion_mod_magecustomblock.zip" .\mod_jfusion_magecustomblock\* -xr!*.svn* > NUL

chdir %FULLPATH%\plugins\
7za a "%FULLPATH%\side_projects\magento\jfusion_plugin_magelib.zip" .\system\magelib.* -xr!*.svn* > NUL

chdir %FULLPATH%

IF "%action%"=="2" goto end


:CREATE_MAIN
chdir %FULLPATH%

echo Prepare the files for packaging
md tmp
md tmp\admin
c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%\administrator\components\com_jfusion\*.*" "%FULLPATH%\tmp\admin" > NUL
c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%\pluginpackages\*.*" "%FULLPATH%\tmp\admin\packages\" > NUL
del "%FULLPATH%\tmp\admin\jfusion.xml"

md tmp\admin\languages
c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%\administrator\language\en-GB\*.*" "%FULLPATH%\tmp\admin\languages\en-GB\" > NUL

md tmp\front
c:\windows\system32\xcopy /E /C /V /Y /EXCLUDE:%FULLPATH%\exclude.txt "%FULLPATH%\components\com_jfusion\*.*" "%FULLPATH%\tmp\front" > NUL

md tmp\front\languages
c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%\language\en-GB\*.*" "%FULLPATH%\tmp\front\languages\en-GB\" > NUL

copy "%FULLPATH%\administrator\components\com_jfusion\jfusion.xml" "%FULLPATH%\tmp" /V /Y > NUL
copy "%FULLPATH%\administrator\components\com_jfusion\install.jfusion.php" "%FULLPATH%\tmp" /V /Y > NUL
copy "%FULLPATH%\administrator\components\com_jfusion\uninstall.jfusion.php" "%FULLPATH%\tmp" /V /Y > NUL

echo Update the revision number

move "%FULLPATH%\tmp\jfusion.xml" "%FULLPATH%\tmp\jfusion.tmp"

SET REVISION=Unknown

for /f "tokens=*" %%a in ( 'git rev-parse HEAD' ) do ( set REVISION=%%a )

echo Revision set to %REVISION%
c:\WINDOWS\system32\sed.exe "s/<revision>\$revision\$<\/revision>/<revision>%REVISION%<\/revision>/g" "%FULLPATH%\tmp\jfusion.tmp" > "%FULLPATH%\tmp\jfusion.xml"
del "%FULLPATH%\tmp\jfusion.tmp"

echo Create the new master package
chdir %FULLPATH%
del *.zip

7za a "%FULLPATH%\jfusion_package.zip" .\tmp\* -xr!*.svn* > NUL

RMDIR "%FULLPATH%\tmp" /S /Q

echo Create a ZIP containing all files to allow for easy updates
chdir %FULLPATH%
7za a "%FULLPATH%\jfusion_files.zip" administrator components language modules plugins -r -xr!*.svn* > NUL
IF "%action%"=="1" goto end

:end
echo Complete
pause>nul