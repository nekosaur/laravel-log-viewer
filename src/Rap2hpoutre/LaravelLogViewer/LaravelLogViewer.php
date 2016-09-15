<?php
namespace Rap2hpoutre\LaravelLogViewer;

use Illuminate\Support\Facades\File;
use Psr\Log\LogLevel;
use ReflectionClass;

/**
 * Class LaravelLogViewer
 * @package Rap2hpoutre\LaravelLogViewer
 */
class LaravelLogViewer
{

    /**
     * @var string file
     */
    private static $file;

    /**
     * @var string date
     */
    private static $date;

    private static $levels_classes = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'danger',
        'critical' => 'danger',
        'alert' => 'danger',
        'emergency' => 'danger',
    ];

    private static $levels_imgs = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'warning',
        'critical' => 'warning',
        'alert' => 'warning',
        'emergency' => 'warning',
    ];

    const MAX_FILE_SIZE = 52428800; // Why? Uh... Sorry

    /**
     * @param string $file
     */
    public static function setFile($file)
    {
        $file = self::pathToLogFile($file);

        if (File::exists($file)) {
            self::$file = $file;
        }
    }

    public static function pathToLogFile($file)
    {
        $logsPath = storage_path('logs');

        if (File::exists($file)) { // try the absolute path
            return $file;
        }

        $file = $logsPath . '/' . $file;

        // check if requested file is really in the logs directory
        if (dirname($file) !== $logsPath) {
            throw new \Exception('No such log file');
        }

        return $file;
    }

    /**
     * @return string
     */
    public static function getFileName()
    {
        return basename(self::$file);
    }

    /**
     * @return array
     */
    public static function all($pattern = null)
    {
        $log = array();

        $log_levels = self::getLogLevels();

        $date_pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*/';

        if (!isset($pattern)) {
            $pattern = $date_pattern;
        }

        $log_files = self::getFiles(false);

        if (!count($log_files)) {
            return [];
        }

        for ($f = 0; $f < count($log_files); $f++) {
            $file = File::get($log_files[$f]);

            preg_match_all($pattern, $file, $headings);

            if (is_array($headings)) {
                $log_data = preg_split($date_pattern, $file);

                if ($log_data[0] < 1) {
                    array_shift($log_data);
                }

                foreach ($headings as $h) {
                    for ($i=0, $j = count($h); $i < $j; $i++) {
                        foreach ($log_levels as $level_key => $level_value) {
                            if (strpos(strtolower($h[$i]), '.' . $level_value)) {
                                preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?(\w+)\.' . $level_key . ': (.*?)( in .*?:[0-9]+)?$/', $h[$i], $current);

                                if (!isset($current[3])) continue;

                                $log[] = array(
                                    'context' => $current[2],
                                    'level' => $level_value,
                                    'level_class' => self::$levels_classes[$level_value],
                                    'level_img' => self::$levels_imgs[$level_value],
                                    'date' => $current[1],
                                    'text' => $current[3],
                                    'in_file' => isset($current[4]) ? $current[4] : null,
                                    'stack' => preg_replace("/^\n*/", '', $log_data[$i])
                                );
                            }
                        }
                    }
                }
            }
        }

        return array_reverse($log);
    }

    /**
     * @return array
     */
    public static function daily()
    {
        $log = array();

        $log_levels = self::getLogLevels();

        $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*/';

        if (!self::$file) {
            $log_file = self::getFiles(false, self::$date);
            if (!count($log_file)) {
                return [];
            }
            self::$file = $log_file[0];
            // dd(self::$file);
        }

        if (File::size(self::$file) > self::MAX_FILE_SIZE) {
            return null;
        }

        $file = File::get(self::$file);

        preg_match_all($pattern, $file, $headings);

        if (!is_array($headings)) {
            return $log;
        }

        $log_data = preg_split($pattern, $file);

        if ($log_data[0] < 1) {
            array_shift($log_data);
        }

        foreach ($headings as $h) {
            for ($i=0, $j = count($h); $i < $j; $i++) {
                foreach ($log_levels as $level_key => $level_value) {
                    if (strpos(strtolower($h[$i]), '.' . $level_value)) {
                        preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?(\w+)\.' . $level_key . ': (.*?)( in .*?:[0-9]+)?$/', $h[$i], $current);

                        if (!isset($current[3])) continue;

                        $log[] = array(
                            'context' => $current[2],
                            'level' => $level_value,
                            'level_class' => self::$levels_classes[$level_value],
                            'level_img' => self::$levels_imgs[$level_value],
                            'date' => $current[1],
                            'text' => $current[3],
                            'in_file' => isset($current[4]) ? $current[4] : null,
                            'stack' => preg_replace("/^\n*/", '', $log_data[$i])
                        );
                    }
                }
            }
        }

        return array_reverse($log);
    }

    /**
     * @param bool $basename
     * @return array
     */
    public static function getFiles($basename = false, $date = false)
    {
        if ($date) {
            self::$date = $date;
        }

        $files = glob(storage_path() . '/logs/*');
        $files = array_reverse($files);
        $files = array_filter($files, 'is_file');
        if (is_array($files)) {
            foreach ($files as $k => $file) {
                if (!empty($date) && strpos($file, $date) === false) {
                    unset($files[$k]);
                } else if ($basename) {
                    $files[$k] = basename($file);
                } else {
                    $files[$k] = $file;
                }
            }
        }
        return array_values($files);
    }

    /**
     * @return array
     */
    private static function getLogLevels()
    {
        $class = new ReflectionClass(new LogLevel);
        return $class->getConstants();
    }
}
