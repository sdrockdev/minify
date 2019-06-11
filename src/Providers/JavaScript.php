<?php

namespace Sdrockdev\Minify\Providers;

use Sdrockdev\Minify\Contracts\MinifyInterface;
use JShrink\Minifier;

class JavaScript extends BaseProvider implements MinifyInterface
{
    /**
     *  The extension of the outputted file.
     */
    const EXTENSION = '.js';

    /**
     * @return string
     */
    function minify() {
        $minified = Minifier::minify($this->appended);

        return $this->put($minified);
    }

    /**
     * @param $file
     * @param array $attributes
     * @return string
     */
    function tag($file, array $attributes) {
        $attributes = [
            'src' => $file,
        ] + $attributes;

        return "<script {$this->attributes($attributes)}></script>" . PHP_EOL;
    }
}
