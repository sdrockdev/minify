<?php

namespace Sdrockdev\Minify\Providers;

use Sdrockdev\Minify\Contracts\MinifyInterface;
use CssMinifier;

class StyleSheet extends BaseProvider implements MinifyInterface
{
    /**
     *  The extension of the outputted file.
     */
    const EXTENSION = '.css';

    /**
     * @return string
     */
    function minify() {
        $minified = new CssMinifier($this->appended);

        return $this->put($minified->getMinified());
    }

    /**
     * @param $file
     * @param array $attributes
     * @return string
     */
    function tag($file, array $attributes) {
        $attributes = [
            'href' => $file,
            'rel'  => 'stylesheet',
        ] + $attributes;

        return "<link {$this->attributes($attributes)}>" . PHP_EOL;
    }
}
