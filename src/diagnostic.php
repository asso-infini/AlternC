#!/usr/bin/php -q
<?php

/*
   $Id: diagnostic.php 2014/05/19 alban Exp $
   ----------------------------------------------------------------------
   AlternC - Web Hosting System
   Copyright (C) 2002 by the AlternC Development Team.
   http://alternc.org/
   ----------------------------------------------------------------------
   Based on:
   Valentin Lacambre's web hosting softwares: http://altern.org/
   ----------------------------------------------------------------------
   LICENSE

   This program is free software; you can redistribute it and/or
   modify it under the terms of the GNU General Public License (GPL)
   as published by the Free Software Foundation; either version 2
   of the License, or (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   To read the license please visit http://www.gnu.org/copyleft/gpl.html
   ----------------------------------------------------------------------
   Original Author of file: alban 
   Purpose of file: Provide a diagnostic of the server state 
   ----------------------------------------------------------------------
 */


/*

   Handles diagnostics of an alternc server.

   Notes 
   The diagnostic files are located by default in /var/lib/alternc/diagnostics

*/

/**
 * Attempts to load a class in multiple path, the PSR-0 or old style way
 * 
 * @staticvar array $srcPathList
 * @staticvar boolean $init
 * @param string $class_name
 * @return boolean
 */
function __autoload($class_name)
{
    // Contains (Namespace) => directory
    static $srcPathList                 = array();
    static $init;
    
    // Attempts to set include path and directories once
    if( is_null( $init )){
        
        // Sets init flag
        $init                           = true;
        
        // Sets a contextual directory
        $srcPathList["standard"]        = __DIR__."/../lib";

        // Updates include_path according to this list
        $includePathList                = explode(PATH_SEPARATOR, get_include_path()); 

        foreach($srcPathList as $path){
            if ( !in_array($path, $includePathList)){
                $includePathList[]      = $path;
            }
        }
        // Reverses the path for search efficiency
        $finalIncludePathList           = array_reverse($includePathList);
        
        // Sets the updated include_path
        set_include_path(implode(PATH_SEPARATOR, $finalIncludePathList));
        
    }
    
    // Accepts old Foo_Bar namespacing
    if(preg_match("/_/", $class_name)){
        $file_name                      = str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';
        
    // Accepts 5.3 Foo\Bar PSR-0 namespacing 
    } else if(preg_match("/\\/", $class_name)){
        $file_name                      = str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class_name,'\\')) . '.php';
        
    // Accepts non namespaced classes
    } else {
        $file_name                      = $class_name . '.php';        
    }
   
    // Attempts to find file in namespace
    foreach($srcPathList as $namespace => $path ){
        $file_path                      = $path.DIRECTORY_SEPARATOR.$file_name;
        if(is_file($file_path) && is_readable($file_path)){
            require $file_path;
            return true;
        }
    }
    
    // Failed to find file
    return false;
}


// ==================================================================
// ==================================================================
// alternc config
// ==================================================================
// ==================================================================

chdir(__DIR__);

$version				= "3.2";

// alternc 1.0
if(is_file("/usr/share/alternc/panel/class/config_nochk.php")){
    require_once("/usr/share/alternc/panel/class/config_nochk.php");
}else{
    $version				= "1.0";				
    require_once("/var/alternc/bureau/class/config_nochk.php");
    include "../bureau/class/class_system_bind.php";
}


$directoryInstance                      = new Alternc_Diagnostic_Directory("/var/lib/alternc/diagnostics");


// instanciation of the diagnosticManager service

$diagnosticManager                      = new Alternc_Diagnostic_Manager( array(
    "directoryInstance"         => $directoryInstance,
    "formatInstance"            => new Alternc_Diagnostic_Format_Json($directoryInstance),
    "version"			=> $version 
));


// ==================================================================
// ==================================================================
// Console parser configuration
// ==================================================================
// ==================================================================

$consoleParser                          = new Alternc_Diagnostic_Console();


$createCommmand                         = $consoleParser->addCommand('create', array('multiple'=>true,"alias"=>"c","description" => "Creates a new diagnostic"));

$createCommmand->addOption('services', array(
   'short_name'  => '-s',
   'long_name'   => '--services',
   'action'      => 'StoreString',
   'default'     => 'apache2,dns,mail,system,mailman,mysql,panel,ftp',
   'description' => 'Sets the services to use for diagnostics separated by comma
   ex: -d apache2,dns,mail',
    'help_name'   => 'services'
    ));

$createCommmand->addOption('format', array(
   'short_name'  => '-f',
   'long_name'   => '--format',
   'action'      => 'StoreString',
   'default'     => 'json',
   'description' => 'Sets the format of the diagnostic file : json (default)',
    'help_name'   => 'format'
    ));

$indexCommmand                          = $consoleParser->addCommand('index', array('multiple'=>false,"alias"=>"i","description" => "Shows all available diagnostics"));
$compareCommmand                        = $consoleParser->addCommand('compare', array('multiple'=>false,"alias"=>"x","description" => "Compares file_1 to file_2. Accepts names or number reference."));

$compareCommmand->addArgument('file1', array(
   'description' => 'File name or reference (1..x)',
    'help_name'   => 'file_1',
    "optional"  => true
    ));

$compareCommmand->addArgument('file2', array(
   'description' => 'File name or reference of file (1..x)',
    'help_name'   => 'file_2',
    "optional"  => true
    ));

$showCommmand                           = $consoleParser->addCommand('show', array('multiple'=>false,"alias"=>"s","description" => "Prints a diagnotic content"));
$showCommmand->addArgument('file', array(
   'description' => 'File name or reference (1..x)',
    'help_name'   => 'file',
    "optional"  => true
    ));
$showCommmand->addOption('format', array(
   'short_name'  => '-f',
   'long_name'   => '--format',
   'action'      => 'StoreString',
   'default'     => 'json',
   'description' => 'Sets the format of the output : 
*    json (default)
*    var_dump 
', 
    'help_name'   => 'format',
    'choices'   => array("jso","var_dump")
    ));

$deleteCommmand                         = $consoleParser->addCommand('delete', array('multiple'=>false,"alias"=>"d","description" => "Deletes diagnostic files"));

$deleteCommmand->addArgument('filesList', array(
   'description' => 'Single or multiple files names or references (1..x)',
    'help_name'   => 'file',
    "optional"  => true,
    "multiple"  => true
    ));


// Attempts to parse command line
try {
    $result                             = $consoleParser->parse();
    if ($result->command_name){
        $command_name                   = $result->command_name;
        $command                        = $result->command;
    }else{
        throw new \Exception("Command missing, use -h to learn about available commands.");
    }
    if( !method_exists($diagnosticManager, $command_name)){
        throw new \Exception("Invalid command : $command");
    }
    $response                           = $diagnosticManager->$command_name($command);
    if( is_a($response, "Alternc_Diagnostic_Report")){
        var_dump($response);
    }else if( is_array($response)){
        foreach ($response as $key => $value) {
            echo "$key : $value \n";
        }
    }else if( is_string($response)){
        echo $response;
    }else if (is_null($response)){
        echo ("Empty response");
    }
    echo ("\n");
} catch (\Exception $exc) {
    $consoleParser->displayError($exc->getMessage());
}