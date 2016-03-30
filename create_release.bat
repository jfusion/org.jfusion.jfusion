@echo off
SET FULLPATH=%~dp0
SET PLUGIN_DIR="%FULLPATH%components\com_jfusion\plugins"
setlocal enableextensions

:START
IF NOT EXIST administrator echo JFusion files not found. goto end

echo Looking for required commands...
IF NOT EXIST "C:\Windows\System32\7za.exe" (
	echo "7za.exe does not exist!  Please see create_release_readme.txt".
	goto end
)
IF NOT EXIST "C:\Program Files (x86)\GnuWin32\bin\sed.exe" (
	echo "sed.exe does not exist! Please see create_release_readme.txt". 
	goto end
)
IF NOT EXIST "C:\Program Files\Git\bin\git.exe"  (
	IF NOT EXIST "C:\Program Files (x86)\Git\bin\git.exe"  (
		echo "Git client not installed!  Please see create_release_readme.txt". 
		goto end
	)
)

SET REVISION=Unknown
SET TIMESTAMP=Unknown

for /f "tokens=*" %%a in ( 'git rev-parse HEAD' ) do ( set REVISION=%%a )
call :GetTimeStamp TIMESTAMP

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
	call :clearMain0
	call :clearPackages
goto end

:CLEAR_MAIN
	call :clearMain
goto end

:CREATE_ALL
	call :createPackages
	call :createMain
goto end

:CREATE_PACKAGES
	call :createPackages
goto end

:CREATE_MAIN
	call :createMain
goto end

:createPackages
	call :clearPackages

	echo Create the new packages for the plugins and module

	call :CreatePackage modules\mod_jfusion_activity\* administrator\components\com_jfusion\packages\jfusion_mod_activity.zip mod_jfusion_activity
	call :CreatePackage modules\mod_jfusion_login\* administrator\components\com_jfusion\packages\jfusion_mod_login.zip mod_jfusion_login
	call :CreatePackage modules\mod_jfusion_whosonline\* administrator\components\com_jfusion\packages\jfusion_mod_whosonline.zip mod_jfusion_whosonline
	call :CreatePackage modules\mod_jfusion_user_activity\* administrator\components\com_jfusion\packages\jfusion_mod_user_activity.zip mod_jfusion_user_activity

	call :CreatePackage plugins\authentication\* administrator\components\com_jfusion\packages\jfusion_plugin_auth.zip
	call :CreatePackage plugins\user\* administrator\components\com_jfusion\packages\jfusion_plugin_user.zip
	call :CreatePackage plugins\search\* administrator\components\com_jfusion\packages\jfusion_plugin_search.zip
	call :CreatePackage plugins\content\* administrator\components\com_jfusion\packages\jfusion_plugin_content.zip
	call :CreatePackage plugins\system\jfusion.* administrator\components\com_jfusion\packages\jfusion_plugin_system.zip


	echo Create the jfusion plugin packages

	FOR /f "tokens=*" %%G IN ('dir /d /b /a:d components\com_jfusion\plugins\') DO (
		if exist %FULLPATH%components\com_jfusion\plugins\%%G\jfusion.xml (
			call :CreatePackage components\com_jfusion\plugins\%%G pluginpackages\jfusion_%%G.zip
		) else (
			echo Error: %FULLPATH%components\com_jfusion\plugins\%%G\jfusion.xml was not found
		)
	)

	echo "create the new packages for the Magento Integration"

	call :CreatePackage modules\mod_jfusion_magecart\* side_projects\magento\jfusion_mod_magecart.zip mod_jfusion_magecart
	call :CreatePackage modules\mod_jfusion_mageselectblock\* side_projects\magento\jfusion_mod_mageselectblock.zip mod_jfusion_mageselectblock
	call :CreatePackage modules\mod_jfusion_magecustomblock\* side_projects\magento\jfusion_mod_magecustomblock.zip mod_jfusion_magecustomblock
	call :CreatePackage plugins\system\magelib.* side_projects\magento\jfusion_plugin_magelib.zip magelib
endlocal & goto :EOF

:createMain
	echo Prepare the files for packaging
	md tmp
	md tmp\admin
	c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%administrator\components\com_jfusion\*.*" "%FULLPATH%\tmp\admin" > NUL
	c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%pluginpackages\*.*" "%FULLPATH%tmp\admin\packages\" > NUL
	del "%FULLPATH%tmp\admin\jfusion.xml"

	md tmp\admin\language
	c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%administrator\language\en-GB\*.*" "%FULLPATH%tmp\admin\language\en-GB\" > NUL

	md tmp\front
	c:\windows\system32\xcopy /E /C /V /Y /EXCLUDE:%FULLPATH%exclude.txt "%FULLPATH%components\com_jfusion\*.*" "%FULLPATH%tmp\front" > NUL

	md tmp\front\language
	c:\windows\system32\xcopy /E /C /V /Y "%FULLPATH%language\en-GB\*.*" "%FULLPATH%tmp\front\language\en-GB\" > NUL

	copy "%FULLPATH%administrator\components\com_jfusion\jfusion.xml" "%FULLPATH%tmp" /V /Y > NUL
	copy "%FULLPATH%administrator\components\com_jfusion\script.php" "%FULLPATH%\tmp" /V /Y > NUL

	echo Update the revision number

	echo Revision set to %REVISION%
	echo Timestamp set to %TIMESTAMP%
	call :CreateXml %FULLPATH%tmp\jfusion

	echo Create the new master package

	call :clearMain

	7za a "%FULLPATH%jfusion_package.zip" .\tmp\* -xr!*.svn* > NUL

	RMDIR "%FULLPATH%tmp" /S /Q

endlocal & goto :EOF

:GetTimeStamp
	setlocal enableextensions
	for /f %%x in ('wmic path win32_utctime get /format:list ^| findstr "="') do (
		set %%x)
	set /a z=(14-100%Month%%%100)/12, y=10000%Year%%%10000-z
	set /a ut=y*365+y/4-y/100+y/400+(153*(100%Month%%%100+12*z-3)+2)/5+Day-719469
	set /a ut=ut*86400+100%Hour%%%100*3600+100%Minute%%%100*60+100%Second%%%100
endlocal & set "%1=%ut%" & goto :EOF

:CreatePackage
	setlocal enableextensions

	SET TARGETPATH=%1
	SET TARGETDEST=%2
	SET XMLFILE=%3

	IF "%3" == "" SET XMLFILE=jfusion

	echo Creating: %TARGETDEST%

	md %FULLPATH%tmppackage

	c:\windows\system32\xcopy /S /E /C /V /Y "%FULLPATH%%TARGETPATH%" "%FULLPATH%tmppackage" > NUL

	call :CreateXml %FULLPATH%tmppackage\%XMLFILE%

	7za a "%FULLPATH%%TARGETDEST%" %FULLPATH%tmppackage\* -xr!*.svn* > NUL

	RMDIR "%FULLPATH%tmppackage" /S /Q
endlocal & goto :EOF

:CreateXml
	setlocal enableextensions
	SET FILE=%1

	move "%FILE%.xml" "%FILE%.tmp" >nul
	"C:\Program Files (x86)\GnuWin32\bin\sed.exe" "s/<revision>\$revision\$<\/revision>/<revision>%REVISION%<\/revision>/g" "%FILE%.tmp" > "%FILE%.xml"
	move "%FILE%.xml" "%FILE%.tmp" >nul
	"C:\Program Files (x86)\GnuWin32\bin\sed.exe" "s/<timestamp>\$timestamp\$<\/timestamp>/<timestamp>%TIMESTAMP%<\/timestamp>/g" "%FILE%.tmp" > "%FILE%.xml"

	del %FILE%.tmp
endlocal & goto :EOF

:clearPackages
	echo Remove module and plugin packages
	if exist %FULLPATH%administrator\components\com_jfusion\packages\*.zip (
    	del "%FULLPATH%administrator\components\com_jfusion\packages\*.zip"
	)
		if exist %FULLPATH%pluginpackages\*.zip (
    		del "%FULLPATH%pluginpackages\*.zip"
    	)
endlocal & goto :EOF

:clearMain
	if exist %FULLPATH%*.zip (
    	del "%FULLPATH%*.zip"
	)
endlocal & goto :EOF

:end
echo Complete