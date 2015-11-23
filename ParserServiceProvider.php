<?php

namespace Medlib\MarcXML;

use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return \Medlib\MarcXML\Parser
     */
    public function register()
    {
        $this->app->singleton('marcxml', function () {
            $parser = new Parser();
            return $parser;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['marcxml'];
    }
}