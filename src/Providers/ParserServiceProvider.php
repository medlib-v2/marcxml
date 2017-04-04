<?php

namespace Medlib\MarcXML\Providers;

use Medlib\MarcXML\Parser\Parser;
use Medlib\MarcXML\Facades\MarcXML;
use Illuminate\Support\ServiceProvider;

class ParserServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return \Medlib\MarcXML\Parser\Parser
     */
    public function register()
    {
        $this->registerFacades();
        $this->registerParserProvider();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['medlib.marcxml'];
    }


    /**
     * Bind some facades.
     *
     * @return void
     */
    protected function registerFacades()
    {
        $this->app->provideFacades(MarcXML::class);
    }

    /**
     * Register the bindings for the Parser provider.
     *
     * @return void
     */
    protected function registerParserProvider()
    {
        $this->app->singleton('medlib.marcxml', function ($app) {
            $parser = new Parser;
            return $parser;
        });
    }
}