<?php
/*
 *                                                                                                                                                                                                                                                            *
 * Copyright (c) 2018 by Firegore (https://firegore.es) (git:firegore2).                                                                                                                                                                                      *
 * This file is part of clash-royale-php.                                                                                                                                                                                                                     *
 *                                                                                                                                                                                                                                                            *
 * clash-royale-php is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. *
 * clash-royale-php is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                                                                    *
 * See the GNU Affero General Public License for more details.                                                                                                                                                                                                *
 * You should have received a copy of the GNU General Public License along with clash-royale-php.                                                                                                                                                             *
 * If not, see <http://www.gnu.org/licenses/>.                                                                                                                                                                                                                *
 *                                                                                                                                                                                                                                                            *
 */

namespace CR;

class CRCache
{
    /**
     * [protected description].
     *
     * @var string
     */
    protected static $path;

    /**
     * Prefix directories size.
     *
     * For instance, if the file is helloworld.txt and the prefix size is
     * 5, the cache file will be: h/e/l/l/o/helloworld.txt
     *
     * This is useful to avoid reaching a too large number of files into the
     * cache system directories
     *
     * @var int
     */
    protected static $prefixSize = 5;

    /**
     * Directory mode.
     *
     * Allows setting of the access mode for the directories created.
     *
     * @var int
     */
    protected static $directoryMode = 0755;

    /**
     * @return string
     */
    public static function getPath(): string
    {
        if (is_null(self::$path)) {
            $pos = strpos(__DIR__, 'vendor') ?: strpos(__DIR__, 'src');
            self::$path = substr(__DIR__, 0, $pos).'cache'.DIRECTORY_SEPARATOR.'CR';
        }

        return self::$path;
    }

    /**
     * @param string $path
     *
     * @return static
     */
    public static function setPath(string $path)
    {
        self::$path = $path;

        return self;
    }

    /**
     * Gets the cache file name.
     *
     * @param string $filename the name of the cache file
     * @param bool   $actual   get the actual file or the public file
     * @param bool   $mkdir    a boolean to enable/disable the construction of the
     *                         cache file directory
     *
     * @return string
     */
    public static function getCacheFile($filename, $actual = false, $mkdir = false)
    {
        $path = [];

        // Getting the length of the filename before the extension
        $parts = explode('.', $filename);
        $len = strlen($parts[0]);

        for ($i = 0; $i < min($len, self::$prefixSize); ++$i) {
            $path[] = $filename[$i];
        }
        $path = implode(DIRECTORY_SEPARATOR, $path);

        if ($mkdir) {
            $actualDir = self::getPath().DIRECTORY_SEPARATOR.$path;
            self::mkdir($actualDir);
        }

        $path .= DIRECTORY_SEPARATOR.$filename;

        return self::getPath().DIRECTORY_SEPARATOR.$path;
    }

    /**
     * Checks if the target filename exists in the cache and if the conditions
     * are respected.
     *
     * @param string $filename   the filename
     * @param array  $conditions the conditions to respect
     *
     * @return bool
     */
    public static function exists($filename, array $conditions = [])
    {
        $cacheFile = self::getCacheFile($filename, true);

        return self::checkConditions($cacheFile, $conditions);
    }

    /**
     * Write data in the cache.
     *
     * @param string $filename the name of the cache file
     * @param string $contents the contents to store
     *
     * @return self
     */
    public static function set($filename, $contents = '')
    {
        $cacheFile = self::getCacheFile($filename, true, true);

        return false !== file_put_contents($cacheFile, $contents, \LOCK_EX);
    }

    /**
     * Alias for set().
     *
     * @param string $filename the name of the cache file
     * @param string $contents the contents to store
     *
     * @return self
     */
    public static function write($filename, $contents = '')
    {
        return self::set($filename, $contents);
    }

    /**
     * Get data from the cache.
     *
     * @param string $filename   the cache file name
     * @param array  $conditions
     *
     * @return null|string
     */
    public static function get($filename, array $conditions = [])
    {
        if (self::exists($filename, $conditions)) {
            return file_get_contents(self::getCacheFile($filename, true));
        }

        return null;
    }

    /**
     * Creates a directory.
     *
     * @param string $directory the target directory
     */
    protected static function mkdir($directory)
    {
        if (!is_dir($directory)) {
            @mkdir($directory, self::$directoryMode, true);
        }
    }

    /**
     * Is this URL remote?
     *
     * @param string $file
     *
     * @return bool
     */
    protected function isRemote($file)
    {
        if (preg_match('/^([a-z]+):\/\//', $file, $match)) {
            return 'file' != $match[1];
        }

        return false;
    }

    /**
     * Checks that the cache conditions are respected.
     *
     * @param string $cacheFile  the cache file
     * @param array  $conditions an array of conditions to check
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected static function checkConditions($cacheFile, array $conditions = [])
    {
        // Implicit condition: the cache file should exist
        if (!file_exists($cacheFile)) {
            return false;
        }

        foreach ($conditions as $type => $value) {
            switch ($type) {
                case 'maxage':
                case 'max-age':
                    // Return false if the file is older than $value
                    $age = time() - filemtime($cacheFile);
                    if ($age > $value) {
                        return false;
                    }

                    break;
                case 'younger-than':
                case 'youngerthan':
                    // Return false if the file is older than the file $value, or the files $value
                    $check = function ($filename) use ($cacheFile) {
                        return !file_exists($filename) || filemtime($cacheFile) < filemtime($filename);
                    };

                    if (!is_array($value)) {
                        if (!self::isRemote($value) && $check($value)) {
                            return false;
                        }
                    } else {
                        foreach ($value as $file) {
                            if (!self::isRemote($file) && $check($file)) {
                                return false;
                            }
                        }
                    }

                    break;
                default:
                    throw new \Exception('Cache condition '.$type.' not supported');
            }
        }

        return true;
    }
}
