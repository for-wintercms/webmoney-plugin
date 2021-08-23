<?php

namespace DS\Webmoney;

use Backend;
use System\Classes\PluginBase;

/**
 * Webmoney Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Webmoney API',
            'description' => 'Webmoney API plugin',
            'author'      => 'DS',
            'icon'        => 'icon-leaf'
        ];
    }
}
