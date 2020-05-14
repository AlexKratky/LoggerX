<?php
/**
 * @name Logger.php
 * @link https://alexkratky.com                         Author website
 * @link https://panx.eu/docs/                          Documentation
 * @link https://github.com/AlexKratky/LoggerX/         Github Repository
 * @author Alex Kratky <alex@panx.dev>
 * @copyright Copyright (c) 2020 Alex Kratky
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @description Class to work with logs. Part of panx-framework.
 */

declare (strict_types = 1);

namespace AlexKratky;

use AlexKratky\Cache;
use AlexKratky\FileStream;
use AlexKratky\VariableToJson;

class Logger {

    /**
     * @var string|null The directory with logs. Absolute path.
     */
    private static $directory = null;

    private static $realTime = false;
    private static $realTimeServer;
    private static $realTimePort;
    private static $realTimePassword;
    private static $realTimeSecured = true;

    /**
     * Writes data to log file.
     * @param mixed $text The text to be written or mixed if realtime logger is used.
     * @param string $file The name of log file, default is main.log.
     * @param string|null $dir The base path, if sets to null: $_SERVER['DOCUMENT_ROOT'] . "/..".
     * @return false|int This function returns the number of bytes that were written to the log file, or FALSE on failure.
     */
    public static function log($text, string $file = "main.log", ?string $dir = null) {
        if(self::$realTime) {
            // need to change location, add date
            $var = VariableToJson::convert($text, false, true);
            $var['location'] = self::findLocation(is_callable($var));
            $var['date'] =  date("d-m-Y H:i:s");
            return self::sendToRealTimeLogger($var);
        }
        $sizeCheck = Cache::get("__Logger__sizeChecked__$file.info", 60);
        if($dir === null) {
            $dir = self::$directory ?? $_SERVER['DOCUMENT_ROOT'] . "/../logs/";
        }

        if($sizeCheck === false && file_exists($dir . $file)) {
            //Check if the log size if more then 100 MB, if yes, tar.gz it. 102400000
            if(filesize( $dir . $file) > 102400000) {
                $t = time();
                $a = new \PharData(realpath($dir) . "$file.$t.tar");

                $a->addFile(realpath("{$dir}{$file}"));

                $a->compress(\Phar::GZ);

                unlink("{$dir}{$file}.$t.tar");
                unlink("{$dir}{$file}");
            }
            Cache::save("__Logger__sizeChecked__$file.info", "t");
        }
        $text .= ( isset($GLOBALS["request"]) ? " | " . ($GLOBALS["request"]->getClientID() ?? null): '');
        return file_put_contents ('panx://'. $dir . $file , "[".date("d/m/Y H:i:s")."] ".$text . " -  ".debug_backtrace()[0]['file']."@" . (debug_backtrace()[1]['function'] ?? 'null') ."() \r\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Alias to log()
     */
    public static function write(string $text, string $file = "main.log", ?string $dir = null) {return self::log($text, $file, $dir);}

    /**
     * Sets logs' directory.
     */
    public static function setDirectory($directory) {
        self::$directory = $directory;
    }

    public static function setRealTime($realTime) {
        self::$realTime = $realTime;
    }

    public static function setRealTimeServer($realTimeServer) {
        self::$realTimeServer = $realTimeServer;
    }

    public static function setRealTimePort($realTimePort) {
        self::$realTimePort = $realTimePort;
    }

    public static function setRealTimePassword($realTimePassword) {
        self::$realTimePassword = $realTimePassword;
    }

    public static function setRealTimeSecure($realTimeSecured) {
        self::$realTimeSecured = $realTimeSecured;
    }

    public static function realTime($server, $port, $password) {
        self::$realTime = true;
        self::$realTimeServer = $server;
        self::$realTimePort = $port;
        self::$realTimePassword = $password;
    }

    public static function sendToRealTimeLogger($data) {
        $url = (self::$realTimeSecured ? 'https://' : 'http://') . self::$realTimeServer . ":" . self::$realTimePort;

        $ch = curl_init();

        $POST = ["password" => self::$realTimePassword, "data" => json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT)];

        $POST_string = null;

        foreach ($POST as $key => $value) {
            $POST_string .= $key . '=' . $value . '&';
        }
        rtrim($POST_string, '&');

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, count($POST));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POST_string);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response == "true";
    }

    private static function findLocation(): ?array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
            if (isset($item['class']) && $item['class'] === __CLASS__) {
                $location = $item;
                continue;
            } elseif (isset($item['function'])) {
                try {
                    $reflection = isset($item['class'])
                        ? new \ReflectionMethod($item['class'], $item['function'])
                        : new \ReflectionFunction($item['function']);
                    if ($reflection->isInternal()) {
                        $location = $item;
                        continue;
                    }
                } catch (\ReflectionException $e) {
                }
            }
            break;
        }

        if (isset($location['file'], $location['line']) && is_file($location['file'])) {
            $lines = file($location['file']);
            $line = $lines[$location['line'] - 1];
            return [
                $location['file'],
                $location['line'],
                trim(preg_match('/\w*VariableToJson::convert\((.*?)\)/i', $line, $m) ? $m[0] : $line),
                preg_match('/\w*VariableToJson::convert\((.*?)\)\)/i', $line, $m) ? trim(explode(",", $m[1])[0]) : null,
            ];
        }
        return null;
    }


}
