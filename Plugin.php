<?php

namespace DS\WebMoney;

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
            'name'        => 'WebMoney API',
            'description' => 'WebMoney API plugin',
            'author'      => 'DS',
            'icon'        => 'icon-leaf'
        ];
    }
}
