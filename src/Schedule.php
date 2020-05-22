<?php

namespace lexeo\yii2scheduling;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\console\Application;
use yii\mutex\FileMutex;
use yii\mutex\Mutex;


/**
 * Class Schedule
 */
class Schedule extends Component
{
    /**
     * All of the events on the schedule.
     *
     * @var AbstractEvent[]
     */
    protected $events = [];

    /**
     * The mutex implementation.
     *
     * @var Mutex|null
     */
    protected $mutex;

    /**
     * @var string The name of cli script
     */
    public $yiiCliEntryPoint = 'yii';

    /**
     * Schedule constructor.
     * @param array $config
     * @throws InvalidConfigException
     */
    public function __construct(array $config = [])
    {
        $this->mutex = Yii::$app->has('mutex') ? Yii::$app->get('mutex') : new FileMutex();
        parent::__construct($config);

        $absoluteYiiPath = realpath($this->yiiCliEntryPoint);
        if (false === $absoluteYiiPath) {
            if (!Yii::$app instanceof Application) {
                throw new InvalidConfigException(
                    'Unable to locate Yii CLI entry point. Use "yiiCliEntryPoint" to provide a valid path.'
                 );
            }
            $absoluteYiiPath = Yii::$app->request->scriptFile;
        }
        $this->yiiCliEntryPoint = $absoluteYiiPath;
    }

    /**
     * Add a new callback event to the schedule.
     *
     * @param string $callback
     * @param array $parameters
     * @return CallbackEvent
     */
    public function call($callback, array $parameters = [])
    {
        $event = new CallbackEvent($callback, $parameters);
        $event->setMutex($this->mutex);

        $this->events[] = $event;
        return $event;
    }

    /**
     * Add a new cli command event to the schedule.
     *
     * @param string $command
     * @return Event
     */
    public function command($command)
    {
        return $this->exec(PHP_BINARY . ' ' . $this->yiiCliEntryPoint . ' ' . $command);
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param string $command
     * @return Event
     */
    public function exec($command)
    {
        $event = new Event($command);
        $event->setMutex($this->mutex);

        $this->events[] = $event;
        return $event;
    }

    /**
     * @return AbstractEvent[]
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Get all of the events on the schedule that are due.
     *
     * @return AbstractEvent[]
     */
    public function dueEvents()
    {
        return array_filter($this->events, static function (AbstractEvent $event) {
            return $event->isDue();
        });
    }
}
