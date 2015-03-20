# phpBB 3.1 Extension - JFusion phpBB Auth

## Installation

Clone into phpBB/ext/jfusion/phpbbext:

    git clone https://github.com/jfusion/phpbb-ext.git phpBB/ext/jfusion/phpbbext

Go to "ACP" > "Customise" > "Extensions" and enable the "JFusion phpBB Auth Extension" extension.

## Tests and Continuous Integration

We use Travis-CI as a continous integration server and phpunit for our unit testing. See more information on the [phpBB development wiki](https://wiki.phpbb.com/Unit_Tests).
To run the tests locally, you need to install phpBB from its Git repository. Afterwards run the following command from the phpBB Git repository's root:

Windows:

    phpBB\vendor\bin\phpunit.bat -c phpBB\ext\jfusion\phpbbext\phpunit.xml.dist

others:

    phpBB/vendor/bin/phpunit -c phpBB/ext/jfusion/phpbbext/phpunit.xml.dist

[![Build Status](https://travis-ci.org/jfusion/phpbb-ext.png?branch=master)](https://travis-ci.org/jfusion/phpbb-ext)

## License

[GPLv2](license.txt)
