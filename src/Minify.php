<?php

namespace Sdrockdev\Minify;

use Sdrockdev\Minify\Exceptions\InvalidArgumentException;
use Sdrockdev\Minify\Providers\JavaScript;
use Sdrockdev\Minify\Providers\StyleSheet;
use Request;

class Minify
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var
     */
    protected $provider;

    /**
     * @var
     */
    protected $buildPath;

    /**
     * @var bool
     */
    protected $fullUrl = false;

    /**
     * @var bool
     */
    protected $onlyUrl = false;

    /**
     * @var bool
     */
    protected $buildExtension;


    /**
     * @param array $config
     * @param string $environment
     */
    function __construct(array $config, $environment) {
        $this->checkConfiguration($config);

        $this->config      = $config;
        $this->environment = $environment;
    }

    /**
     * @param $files
     * @param array $attributes
     * @return string
     */
    function javascript(array $files, $attributes=[]) {
        $this->provider = new JavaScript(public_path(), [
            'hash_salt'        => $this->config['hash_salt'],
            'disable_mtime'    => $this->config['disable_mtime'],
            'remove_old_files' => $this->config['remove_old_files'],
        ]);
        $this->buildPath      = $this->config['js_build_path'];
        $this->attributes     = $attributes;
        $this->buildExtension = 'js';

        $this->process($files);

        return $this;
    }

    /**
     * @param $files
     * @param array $attributes
     * @return string
     */
    function stylesheet(array $files, $attributes=[]) {
        $this->provider = new StyleSheet(public_path(), [
            'hash_salt'        => $this->config['hash_salt'],
            'disable_mtime'    => $this->config['disable_mtime'],
            'remove_old_files' => $this->config['remove_old_files'],
        ]);
        $this->buildPath      = $this->config['css_build_path'];
        $this->attributes     = $attributes;
        $this->buildExtension = 'css';

        $this->process($files);

        return $this;
    }


    /**
     * @param $files
     */
    protected function process($files) {
        $this->provider->add($files);

        if ( $this->minifyForCurrentEnvironment() && $this->provider->make($this->buildPath) ) {
            $this->provider->minify();
        }

        $this->fullUrl = false;
    }

    /**
     * @return mixed
     */
    protected function render() {
        $baseUrl = $this->fullUrl ?
            $this->getBaseUrl() : '';

        if ( ! $this->minifyForCurrentEnvironment() ) {
            return $this->provider->tags($baseUrl, $this->attributes);
        }

        $filename = $baseUrl . $this->_makeBuildPath() . $this->provider->getFilename();

        if ( $this->onlyUrl ) {
            return $filename;
        }

        return $this->provider->tag($filename, $this->attributes);
    }

    protected function _makeBuildPath() {
        if ( $this->buildExtension == 'js' ) {
            return $this->config['js_url_path'] ?? $this->buildPath;
        }

        if ( $this->buildExtension == 'css' ) {
            return $this->config['css_url_path'] ?? $this->buildPath;
        }
    }

    /**
     * @return bool
     */
    protected function minifyForCurrentEnvironment() {
        return ! in_array($this->environment, $this->config['ignore_environments']);
    }

    /**
     * @return string
     */
    function withFullUrl() {
        $this->fullUrl = true;

        return $this;
    }

    /**
     * @return mixed
     */
    function onlyUrl() {
        $this->onlyUrl = true;

        return $this;
    }

    /**
     * @return string
     */
    function __toString() {
        return $this->render();
    }

    /**
     * @param array $config
     * @throws Exceptions\InvalidArgumentException
     * @return array
     */
    protected function checkConfiguration(array $config) {
        if ( ! isset($config['css_build_path']) || ! is_string($config['css_build_path']) ) {
            throw new InvalidArgumentException("Missing css_build_path field");
        }
        if ( ! isset($config['js_build_path']) || ! is_string($config['js_build_path']) ) {
            throw new InvalidArgumentException("Missing js_build_path field");
        }
        if ( ! isset($config['ignore_environments']) || ! is_array($config['ignore_environments']) ) {
            throw new InvalidArgumentException("Missing ignore_environments field");
        }
    }

    /**
     * @return string
     */
    protected function getBaseUrl() {
        if ( is_null($this->config['base_url']) || (trim($this->config['base_url']) == '') ) {
            return Request::root();
        }
        return $this->config['base_url'];
    }
}
