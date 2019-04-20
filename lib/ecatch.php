<?php
    //? |-----------------------------------------------------------------------------------------------|
    //? |  /lib/ecatch.php                                                                              |
    //? |                                                                                               |
    //? |  Copyright (c) 2018-2019 Belikhun. All right reserved                                         |
    //? |  Licensed under the MIT License. See LICENSE in the project root for license information.     |
    //? |-----------------------------------------------------------------------------------------------|

    require_once $_SERVER["DOCUMENT_ROOT"] ."/lib/logs.php";

    if (session_status() === PHP_SESSION_NONE)
        session_start();

    if (!defined("ERROR_HANDLING"))
        define("ERROR_HANDLING", "NORMAL");

    if (ERROR_HANDLING === "NORMAL") {
        function errorthrow($errnum, $errstr, $errfile, $errline, $errcode = 500) {
            $iframe = true;

            if (!is_numeric($errcode)) {
                $iframe = false;
                $errcode = 500;
            }

            writeLog("ERRR", "[$errnum] $errstr tại ". basename($errfile) .":$errline");

            $err = Array(
                "num" => $errnum,
                "str" => $errstr,
                "file" => basename($errfile),
                "line" => $errline,
                "errcode" => $errcode
            );

            http_response_code($errcode);
            printErrorPage($err, $iframe);

            die();
        }

        function printErrorPage(Array $data, Bool $useIframe = false) {
            $_SESSION["lastError"] = $data;
            print "<!-- Output Stopped here. Begin Error Page Element -->";
            
            if ($useIframe)
                print "<iframe src=\"/lib/error.php\" style=\"position: fixed; top: 0; left: 0; width: 100%; height: 100%; border: unset; overflow: auto;\"></iframe>";
            else
                require $_SERVER["DOCUMENT_ROOT"]. "/lib/error.php";
        }

        set_error_handler("errorthrow", E_ALL);
    }
    
?>