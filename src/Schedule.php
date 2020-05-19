<?php

namespace lexeo\yii2scheduling;

use Yii;
use yii\base\Component;
use yii\base\Application;
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
     * @var Mutex
     */
    protected $mutex;

    /**
     * @var string The name of cli script
     */
    public $cliScriptName = 'yii';

    /**
     * Schedule constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->mutex = Yii::$app->has('mutex') ? Yii::$app->get('mutex') : (new FileMutex());

        parent::__construct($config);
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
        $this->events[] = $event = new CallbackEvent($this->mutex, $callback, $parameters);
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
        return $this->exec(PHP_BINARY . ' ' . $this->cliScriptName . ' ' . $command);
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param string $command
     * @return Event
     */
    public function exec($command)
    {
        $this->events[] = $event = new Event($this->mutex, $command);
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
     * @param Application $app
     * @return AbstractEvent[]
     */
    public function dueEvents(Application $app)
    {
        return array_filter($this->events, static function (AbstractEvent $event) use ($app) {
            return $event->isDue($app);
        });
    }
}
