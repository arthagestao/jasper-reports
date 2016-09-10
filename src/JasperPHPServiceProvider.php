<?php
namespace JasperPHP;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

/**
 * Classe JasperPHPServiceProvider
 *
 * @codeCoverageIgnore
 * @package JasperPHP
 */
class JasperPHPServiceProvider extends ServiceProvider
{
    const SESSION_HASH = '_JasperPHP';

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['jasperphp'] = $this->app->share(function () {
            return new JasperPHP;
        });

        /**
         * Register the alias.
         */
        $this->app->booting(function () {
            $loader = AliasLoader::getInstance();
            $loader->alias('JasperPHP', 'JasperPHP\Facades\JasperPHP');
        });
    }

}