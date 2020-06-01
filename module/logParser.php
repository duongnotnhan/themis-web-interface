<?php
	//? |-----------------------------------------------------------------------------------------------|
	//? |  /module/logParser.php                                                                        |
	//? |                                                                                               |
	//? |  Copyright (c) 2018-2020 Belikhun. All right reserved                                         |
	//? |  Licensed under the MIT License. See LICENSE in the project root for license information.     |
	//? |-----------------------------------------------------------------------------------------------|

	require_once $_SERVER["DOCUMENT_ROOT"] ."/lib/belibrary.php";
	require_once $_SERVER["DOCUMENT_ROOT"] ."/data/problems/problem.php";

	/**
	 * Parse all the log file
	 */
	define("LOGPARSER_MODE_FULL", "f");

	/**
	 * Parse the log filename and header only
	 */
	define("LOGPARSER_MODE_MINIMAL", "m");

	/**
	 * A module to parse Themis generated log files
	 * 
	 * @package logParser
	 */
	class logParser {

		/**
		 *
		 * Parse the log file generated by Themis
		 *
		 * @param    logPath    Path to log file
		 * @param    mode       Specify parser mode
		 * @return   Array      Contains parsed data
		 *
		 */
		public function __construct(String $logPath, String $mode = LOGPARSER_MODE_MINIMAL) {
			if (!file_exists($logPath))
				throw new Error("File not exist", 44);

			$this -> logPath = $logPath;
			$this -> mode = $mode;
			$this -> logIsFailed = false;
			$this -> passed = 0;
			$this -> failed = 0;
			$this -> blankLinePos = 3;
		}

		public function parse() {
			$file = file($this -> logPath, FILE_IGNORE_NEW_LINES);
			$header = $this -> __parseHeader($file);

			if ($this -> mode === LOGPARSER_MODE_FULL) {
				$testResult = $this -> __parseTestResult($file);
				$header["testPassed"] = $this -> passed;
				$header["testFailed"] = $this -> failed;
			} else
				$testResult = Array();

			return Array(
				"header" => $header,
				"test" => $testResult
			);
		}

		private function __parseHeader($file) {
			$data = Array(
				"status" => null,
				"user" => null,
				"problem" => null,
				"point" => 0,
				"testPassed" => 0,
				"testFailed" => 0,
				"description" => Array(),
				"error" => Array(),
				"file" => Array(
					"base" => null,
					"name" => null,
					"extension" => null,
					"logFilename" => pathinfo($this -> logPath, PATHINFO_FILENAME),
					"lastModify" => filemtime($this -> logPath)
				)
			);

			$firstLine = $file[0];
			$l1matches = [];

			//? check with error regex template
			if (preg_match_all("/(.+)‣(.+): ℱ (.+\w)/m", $firstLine, $l1matches, PREG_SET_ORDER, 0)) {
				//* test match failed template
				$data["status"] = "failed";
				$data["point"] = 0;
				$data["description"] = [$l1matches[0][3]];
				$this -> logIsFailed = true;

				//? error detail start from line 3
				for ($i = 2; $i < count($file); $i++)
					array_push($data["error"], $file[$i]);
				
			} else if (preg_match_all("/(.+)‣(.+): Chưa chấm/m", $firstLine, $l1matches, PREG_SET_ORDER, 0)) {
				//* test match skipped template
				$data["status"] = "skipped";
				$data["point"] = 0;
				$data["description"] = ["Chưa chấm"];
				$this -> logIsFailed = true;
			} else {
				//* test match pass template
				preg_match_all("/(.+)‣(.+): (.+\w)/m", $firstLine, $l1matches, PREG_SET_ORDER, 0);
				$data["point"] = $this -> __f($l1matches[0][3]);
				$data["status"] = ($data["point"] == 0) ? "accepted" : "passed";
				
				for ($i = 2; $i < count($file); $i++) {
					//? Break on blank line
					if (empty($file[$i])) {
						$this -> blankLinePos = $i;
						break;
					}

					array_push($data["description"],  $file[$i]);
				}
			}

			//! this is weird. soo weird
			$data["user"] = trim($l1matches[0][1], " \t\n\r\0\x0B﻿");
			$data["problem"] = trim($l1matches[0][2], " \t\n\r\0\x0B﻿");

			if (isset($file[1])) {
				$problemFileInfo = pathinfo($file[1]);
				$data["file"]["base"] = $problemFileInfo["filename"];
				$data["file"]["name"] = $problemFileInfo["basename"];
				$data["file"]["extension"] = $problemFileInfo["extension"];
			} else {
				$problemFileInfo = parseLogName($this -> logPath);

				if ($problemFileInfo) {
					$data["file"]["base"] = $problemFileInfo["problem"];
					$data["file"]["name"] = $problemFileInfo["name"];
					$data["file"]["extension"] = $problemFileInfo["extension"];
				}
			}

			return $data;
		}

		private function __parseTestResult($file) {
			if ($this -> logIsFailed === true)
				return Array();

			$this -> passed = 0;
			$this -> failed = 0;
			$data = Array();
			$lineData = null;
			$lineInitTemplate = Array(
				"status" => "passed",
				"test" => null,
				"point" => 0,
				"runtime" => 0,
				"detail" => Array(),
				"other" => Array(
					"output" => null,
					"answer" => null,
					"error" => null,
				)
			);

			# test result start after blank line
			for ($i = $this -> blankLinePos; $i < count($file); $i++) {
				$line = $file[$i];
				if (empty($line))
					continue;

				$lineParsed = [];
				if (preg_match_all("/.+‣.+‣(.+): (.+|\d+)/m", $line, $lineParsed, PREG_SET_ORDER, 0)) {
					# line match begin of test data format
					if (!empty($lineData))
						array_push($data, $lineData);

					$lineData = $lineInitTemplate;
					$lineData["test"] = $lineParsed[0][1];
					$lineData["point"] = $this -> __f($lineParsed[0][2]);

					if ($lineData["point"] == 0) {
						$lineData["status"] = "accepted";
						$this -> failed++;
					} else {
						$lineData["status"] = "passed";
						$this -> passed++;
					}

				} else if (preg_match_all("/.+ ≈ (.+) .+/m", $line, $lineParsed, PREG_SET_ORDER, 0))
					# line match runtime format
					$lineData["runtime"] = $this -> __f($lineParsed[0][1]);

				else if (preg_match_all("/.*Output.*: ((.+)(?=\.)|(.+))/m", $line, $lineParsed, PREG_SET_ORDER, 0))
					# line match output data format
					$lineData["other"]["output"] = $lineParsed[0][1];

				else if (preg_match_all("/.*Answer.*: ((.+)(?=\.)|(.+))/m", $line, $lineParsed, PREG_SET_ORDER, 0))
					# line match answer data format
					$lineData["other"]["answer"] = $lineParsed[0][1];

				else if (preg_match_all("/(Command: .+)/m", $line, $lineParsed, PREG_SET_ORDER, 0)) {
					# line match error detail format
					$lineData["other"]["error"] = $lineParsed[0][1];
					$lineData["status"] = "failed";
				}

				else
					# else is detail, cuz detail have no specific format
					array_push($lineData["detail"], $line);
			}

			if (!empty($lineData))
				array_push($data, $lineData);

			return $data;
		}

		private function __f($str) {
			if (preg_match("/^(?:.*\.|)\d+\,\d+$/m", $str))
				//? FORMAT: 000.000,000
				return round((float) str_replace(",", ".", str_replace(".", "", $str)), 3);

			if (preg_match("/^(?:.*\,|)\d+\.\d+$/m", $str))
				//? FORMAT: 000,000.000
				return round((float) str_replace(",", "", $str), 3);

			//! UNKNOWN
			return round((float) $str, 3);
		}
	}

	function parseLogName(String $path) {
		$name = basename($path);

		$parse = [];
		if (preg_match_all("/(.+)\[(.+)\]\[(.+)\]\.([^\.]+)\.?(log)?/m", $name, $parse, PREG_SET_ORDER, 0)) {
			$problemData = problemGet($parse[0][3], $_SESSION["id"] === "admin");
			$problemName = null;
			$problemPoint = null;
			
			if ($problemData !== PROBLEM_ERROR_IDREJECT && $problemData !== PROBLEM_ERROR_DISABLED) {
				$problemName = $problemData["name"];
				$problemPoint = $problemData["point"];
			}

			return Array(
				"id" => $parse[0][1],
				"user" => $parse[0][2],
				"problem" => $parse[0][3],
				"problemName" => $problemName,
				"problemPoint" => $problemPoint,
				"extension" => $parse[0][4],
				"name" => $parse[0][3] .".". $parse[0][4],
				"isLogFile" => isset($parse[0][5])
			);
		}

		return null;
	}