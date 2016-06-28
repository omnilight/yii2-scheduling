<?php

namespace omnilight\scheduling;
use yii\base\Component;
use yii\base\Application;


/**
 * Class Schedule
 */
class Schedule extends Component
{
    /**
     * All of the events on the schedule.
     *
     * @var Event[]
     */
    protected $_events = [];

    /**
     * @var string The name of cli script
     */
    public $cliScriptName = 'yii';

    /**
     * Add a new callback event to the schedule.
     *
     * @param  string  $callback
     * @param  array   $parameters
     * @return Event
     */
    public function call($callback, array $parameters = array())
    {
        $this->_events[] = $event = new CallbackEvent($callback, $parameters);
        return $event;
    }
    /**
     * Add a new cli command event to the schedule.
     *
     * @param  string  $command
     * @return Event
     */
    public function command($command)
    {
        return $this->exec(PHP_BINARY . ' ' . $this->cliScriptName . ' ' . $command);
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param  string  $command
     * @return Event
     */
    public function exec($command)
    {
        $this->_events[] = $event = new Event($command);
        return $event;
    }

    public function getEvents()
    {
        return $this->_events;
    }

    /**
     * Get all of the events on the schedule that are due.
     *
     * @param \yii\base\Application $app
     * @return Event[]
     */
    public function dueEvents(Application $app)
    {
        return array_filter($this->_events, function(Event $event) use ($app)
        {
            return $event->isDue($app);
        });
    }
}
