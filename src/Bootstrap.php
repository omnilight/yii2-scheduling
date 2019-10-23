<?php

namespace rubarbs\scheduling;

use yii\base\Application;
use yii\base\BootstrapInterface;

/**
 * Class Bootstrap
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param Application $app the application currently running
     */
    public function bootstrap($app)
    {
        if (($app instanceof \yii\console\Application) && ! isset($app->controllerMap['schedule'])) {
            $app->controllerMap['schedule'] = 'rubarbs\scheduling\ScheduleController';
        }
    }
}
