<?php

namespace Sdrockdev\Minify\Contracts;

interface MinifyInterface
{

    /**
     * @return mixed
     */
    function minify();

    /**
     * @param  string $file
     * @param  array  $attributes
     * @return mixed
     */
    function tag($file, array $attributes);
}
