<?php

namespace Sdrockdev\Minify\Providers;

use Sdrockdev\Minify\Exceptions\CannotRemoveFileException;
use Sdrockdev\Minify\Exceptions\CannotSaveFileException;
use Sdrockdev\Minify\Exceptions\DirNotExistException;
use Sdrockdev\Minify\Exceptions\DirNotWritableException;
use Sdrockdev\Minify\Exceptions\FileNotExistException;
use Illuminate\Filesystem\Filesystem;
use Countable;

abstract class BaseProvider implements Countable
{
    /**
     * @var string
     */
    protected $outputDir;

    /**
     * @var string
     */
    protected $appended = '';

    /**
     * @var string
     */
    protected $filename = '';

    /**
     * @var array
     */
    protected $files = [];

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var string
     */
    private $publicPath;

    /**
     * @var Illuminate\Foundation\Filesystem
     */
    protected $file;

    /**
     * @var boolean
     */
    private $disable_mtime;

    /**
     * @var string
     */
    private $hash_salt;

    /**
     * @var string
     */
    private $remove_old_files;

    /**
     * @param null $publicPath
     */
    function __construct($publicPath=null, $config=null, Filesystem $file=null) {
        $this->file             = $file ?: new Filesystem;
        $this->publicPath       = $publicPath ?: $_SERVER['DOCUMENT_ROOT'];
        $this->disable_mtime    = $config['disable_mtime'];
        $this->hash_salt        = $config['hash_salt'];
        $this->remove_old_files = $config['remove_old_files'];
    }

    /**
     * @param $outputDir
     * @return bool
     */
    function make($outputDir) {
        $this->outputDir = $this->publicPath . $outputDir;

        $this->checkDirectory();

        if ( $this->checkExistingFiles() ) {
            return false;
        }

        if ( $this->remove_old_files ) {
            $this->removeOldFiles();
        }

        $this->appendFiles();

        return true;
    }

    /**
     * @param  $files
     * @return void
     * @throws \Sdrockdev\Minify\Exceptions\FileNotExistException
     */
    function add($files) {
        foreach ( $files as $file ) {
            $file = $this->publicPath . $file;
            if ( ! file_exists($file) ) {
                throw new FileNotExistException("File '{$file}' does not exist");
            }

            $this->files[] = $file;
        }
    }

    /**
     * @param $baseUrl
     * @param $attributes
     *
     * @return string
     */
    function tags($baseUrl, $attributes) {
        $html = '';
        foreach( $this->files as $file ) {
            $file = $baseUrl . str_replace($this->publicPath, '', $file);
            $html .= $this->tag($file, $attributes);
        }

        return $html;
    }

    /**
     * @return int
     */
    function count() {
        return count($this->files);
    }

    /**
     * @throws \Sdrockdev\Minify\Exceptions\FileNotExistException
     */
    protected function appendFiles() {
        foreach ( $this->files as $file ) {
            $contents = file_get_contents($file);
            $this->appended .= $contents . "\n";
        }
    }

    /**
     * @return bool
     */
    protected function checkExistingFiles() {
        $this->buildMinifiedFilename();

        return file_exists($this->outputDir . $this->filename);
    }

    /**
     * @throws \Sdrockdev\Minify\Exceptions\DirNotWritableException
     * @throws \Sdrockdev\Minify\Exceptions\DirNotExistException
     */
    protected function checkDirectory() {
        if ( ! file_exists($this->outputDir) ) {
            // Try to create the directory
            if ( ! $this->file->makeDirectory($this->outputDir, 0775, true) ) {
                throw new DirNotExistException("Buildpath '{$this->outputDir}' does not exist");
            }
        }

        if ( ! is_writable($this->outputDir) ) {
            throw new DirNotWritableException("Buildpath '{$this->outputDir}' is not writable");
        }
    }

    /**
     * @return string
     */
    protected function buildMinifiedFilename() {
        $this->filename = $this->getHashedFilename() . (($this->disable_mtime) ? '' : $this->getCumulativeModTimeOfFiles()) . static::EXTENSION;
    }

    /**
     * Build an HTML attribute string from an array.
     *
     * @param  array  $attributes
     * @return string
     */
    protected function attributes(array $attributes) {
        $html = [];
        foreach ( $attributes as $key => $value ) {
            $element = $this->attributeElement($key, $value);

            if ( ! is_null($element) ) {
                $html[] = $element;
            }
        }

        $output = ( count($html) > 0 ) ?
            ' ' . implode(' ', $html) :
            '';

        return trim($output);
    }

    /**
     * Build a single attribute element.
     *
     * @param  string|integer $key
     * @param  string|boolean $value
     * @return string|null
     */
    protected function attributeElement($key, $value) {
        if ( is_numeric($key) ) {
            $key = $value;
        }

        if ( is_bool($value) ) {
            return $key;
        }

        if ( ! is_null($value) ) {
            return $key.'="'.htmlentities($value, ENT_QUOTES, 'UTF-8', false).'"';
        }

        return null;
    }

    /**
     * @return string
     */
    protected function getHashedFilename() {
        $publicPath = $this->publicPath;
        return md5(implode('-', array_map(function($file) use ($publicPath) { return str_replace($publicPath, '', $file); }, $this->files)) . $this->hash_salt);
    }

    /**
     * @return int
     */
    protected function getCumulativeModTimeOfFiles() {
        $result = 0;

        foreach ( $this->files as $file ) {
            $result += filemtime($file);
        }

        return $result;
    }

    /**
     * @throws \Sdrockdev\Minify\Exceptions\CannotRemoveFileException
     */
    protected function removeOldFiles() {
        $pattern = $this->outputDir . $this->getHashedFilename() . '*';
        $find    = glob($pattern);

        if ( is_array($find) && count($find) ) {
            foreach ( $find as $file ) {
                if ( ! unlink($file) ) {
                    throw new CannotRemoveFileException("File '{$file}' cannot be removed");
                }
            }
        }
    }

    /**
     * @param $minified
     * @return string
     * @throws \Sdrockdev\Minify\Exceptions\CannotSaveFileException
     */
    protected function put($minified) {
        if ( file_put_contents($this->outputDir . $this->filename, $minified) === false ) {
            throw new CannotSaveFileException("File '{$this->outputDir}{$this->filename}' cannot be saved");
        }
        return $this->filename;
    }

    /**
     * @return string
     */
    function getAppended() {
        return $this->appended;
    }

    /**
     * @return string
     */
    function getFilename() {
        return $this->filename;
    }
}
