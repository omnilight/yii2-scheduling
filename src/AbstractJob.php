<?php

namespace lexeo\yii2scheduling;

use Cron\CronExpression;
use DateTime;
use yii\base\ModelEvent;

/**
 * Class AbstractJob
 * @property-read string $id
 * @property-read bool $withoutOverlapping
 * @property-read bool $onOneServer
 */
abstract class AbstractJob extends \yii\base\Component
{
    const EVENT_BEFORE_RUN = 'beforeRun';
    const EVENT_AFTER_COMPLETE = 'afterComplete';

    /**
     * The cron expression representing the frequency of running.
     *
     * @var string
     */
    protected $expression = '* * * * *';
    /**
     * The filter callback.
     *
     * @var callable[]
     */
    protected $filters = [];
    /**
     * The reject callback.
     *
     * @var callable[]
     */
    protected $rejects = [];
    /**
     * The human readable description of the job.
     *
     * @var string
     */
    protected $description;
    /**
     * Indicates if the command should not overlap itself.
     *
     * @var bool
     */
    protected $withoutOverlapping = false;
    /**
     * Indicates if the command should only be allowed to run on one server for each cron expression.
     *
     * @var bool
     */
    protected $onOneServer = false;
    /**
     * Decide if errors will be displayed.
     *
     * @var bool
     */
    protected $omitErrors = false;

    /**
     * Run the given job.
     */
    abstract public function run();

    /**
     * Get the summary of the job for display.
     *
     * @return string
     */
    abstract public function getSummaryForDisplay();

    /**
     * Get the unique id of scheduled command.
     *
     * @return string
     */
    abstract public function getId();

    /**
     * @return bool
     */
    protected function beforeRun()
    {
        $event = new ModelEvent();
        $this->trigger(self::EVENT_BEFORE_RUN, $event);

        return $event->isValid;
    }

    /**
     * @return void
     */
    protected function afterComplete()
    {
        $this->trigger(self::EVENT_AFTER_COMPLETE);
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param callable|bool $callback
     * @return $this
     */
    public function when($callback)
    {
        $this->filters[] = is_callable($callback) ? $callback : static function () use ($callback) {
            return $callback;
        };

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param callable|bool $callback
     * @return $this
     */
    public function skip($callback)
    {
        $this->rejects[] = is_callable($callback) ? $callback : static function () use ($callback) {
            return $callback;
        };

        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param callable $callback
     * @param mixed $data
     * @return $this
     */
    public function then($callback, $data = null)
    {
        $this->on(self::EVENT_AFTER_COMPLETE, $callback, $data);
        return $this;
    }

    /**
     * Set the human-friendly description of the job.
     *
     * @param string $description
     * @return $this
     */
    public function description($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the Cron expression for the job.
     *
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * Set if errors should be displayed
     *
     * @param bool $omitErrors
     * @return $this
     */
    public function omitErrors($omitErrors = false)
    {
        $this->omitErrors = $omitErrors;
        return $this;
    }

    /**
     * Determine if the given job should run based on the Cron expression.
     *
     * @param DateTime|string $currentTime
     * @return bool
     */
    public function isDue($currentTime)
    {
        return CronExpression::factory($this->expression)->isDue($currentTime);
    }

    /**
     * Determine if the filters pass for the job.
     *
     * @return bool
     */
    public function filtersPass()
    {
        foreach ($this->rejects as $callback) {
            if (call_user_func($callback, $this)) {
                return false;
            }
        }
        foreach ($this->filters as $callback) {
            if (!call_user_func($callback, $this)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The Cron expression representing the frequency of running.
     *
     * @param string $expression
     * @return $this
     */
    public function cron($expression)
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Schedule the job to run hourly.
     *
     * @return $this
     */
    public function hourly()
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * Schedule the job to run hourly at a given offset in the hour.
     *
     * @param array|int|string $offset
     * @return $this
     */
    public function hourlyAt($offset)
    {
        $offset = is_array($offset) ? implode(',', $offset) : $offset;
        return $this->spliceIntoPosition(1, $offset);
    }

    /**
     * Schedule the job to run daily.
     *
     * @return $this
     */
    public function daily()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0);
    }

    /**
     * Schedule the command at a given time.
     *
     * @param string $time
     * @return $this
     */
    public function at($time)
    {
        return $this->dailyAt($time);
    }

    /**
     * Schedule the job to run daily at a given time (10:00, 19:30, etc).
     *
     * @param string $time
     * @return $this
     */
    public function dailyAt($time)
    {
        $segments = explode(':', $time);
        return $this->spliceIntoPosition(2, (int)$segments[0])
            ->spliceIntoPosition(1, count($segments) === 2 ? (int)$segments[1] : '0');
    }

    /**
     * Schedule the job to run twice daily.
     *
     * @param int $first
     * @param int $second
     * @return $this
     */
    public function twiceDaily($first = 1, $second = 13)
    {
        $hours = $first . ',' . $second;
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, $hours);
    }

    /**
     * Schedule the job to run only on weekdays.
     *
     * @return $this
     */
    public function weekdays()
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    /**
     * Schedule the job to run only on Mondays.
     *
     * @return $this
     */
    public function mondays()
    {
        return $this->days(1);
    }

    /**
     * Set the days of the week the command should run on.
     *
     * @param array|int $days
     * @return $this
     */
    public function days($days)
    {
        $days = is_array($days) ? $days : func_get_args();
        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * Schedule the job to run only on Tuesdays.
     *
     * @return $this
     */
    public function tuesdays()
    {
        return $this->days(2);
    }

    /**
     * Schedule the job to run only on Wednesdays.
     *
     * @return $this
     */
    public function wednesdays()
    {
        return $this->days(3);
    }

    /**
     * Schedule the job to run only on Thursdays.
     *
     * @return $this
     */
    public function thursdays()
    {
        return $this->days(4);
    }

    /**
     * Schedule the job to run only on Fridays.
     *
     * @return $this
     */
    public function fridays()
    {
        return $this->days(5);
    }

    /**
     * Schedule the job to run only on Saturdays.
     *
     * @return $this
     */
    public function saturdays()
    {
        return $this->days(6);
    }

    /**
     * Schedule the job to run only on Sundays.
     *
     * @return $this
     */
    public function sundays()
    {
        return $this->days(0);
    }

    /**
     * Schedule the job to run weekly.
     *
     * @return $this
     */
    public function weekly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(5, 0);
    }

    /**
     * Schedule the job to run weekly on a given day and time.
     *
     * @param int $day
     * @param string $time
     * @return $this
     */
    public function weeklyOn($day, $time = '0:0')
    {
        $this->dailyAt($time);
        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * Schedule the job to run monthly.
     *
     * @return $this
     */
    public function monthly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1);
    }

    /**
     * Schedule the job to run monthly on a given day and time.
     *
     * @param int $day
     * @param string $time
     * @return $this
     */
    public function monthlyOn($day = 1, $time = '0:0')
    {
        $this->dailyAt($time);
        return $this->spliceIntoPosition(3, $day);
    }

    /**
     * Schedule the job to run twice monthly.
     *
     * @param int $first
     * @param int $second
     * @return $this
     */
    public function twiceMonthly($first = 1, $second = 16)
    {
        $days = $first . ',' . $second;

        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, $days);
    }

    /**
     * Schedule the job to run quarterly.
     *
     * @return $this
     */
    public function quarterly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, '1-12/3');
    }

    /**
     * Schedule the job to run yearly.
     *
     * @return $this
     */
    public function yearly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, 1);
    }

    /**
     * Schedule the job to run every minute.
     *
     * @return $this
     */
    public function everyMinute()
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * Schedule the job to run every N minutes.
     *
     * @param int|string $minutes
     * @return $this
     */
    public function everyNMinutes($minutes)
    {
        return $this->spliceIntoPosition(1, '*/' . $minutes);
    }

    /**
     * Schedule the job to run every five minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->everyNMinutes(5);
    }

    /**
     * Schedule the job to run every ten minutes.
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->everyNMinutes(10);
    }

    /**
     * Schedule the job to run every thirty minutes.
     *
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->spliceIntoPosition(1, '0,30');
    }


    /**
     * Splice the given value into the given position of the expression.
     *
     * @param int $position
     * @param string $value
     * @return $this
     */
    protected function spliceIntoPosition($position, $value)
    {
        $segments = explode(' ', $this->expression);
        $segments[$position - 1] = $value;
        return $this->cron(implode(' ', $segments));
    }

    /**
     * Do not allow the job to overlap each other.
     *
     * @param bool $bool
     * @return $this
     */
    public function withoutOverlapping($bool = true)
    {
        $this->withoutOverlapping = $bool;
        return $this;
    }

    /**
     * @return bool
     */
    public function getWithoutOverlapping()
    {
        return $this->withoutOverlapping;
    }

    /**
     * Allow the job to only run on one server for each cron expression.
     *
     * @param bool $bool
     * @return $this
     */
    public function onOneServer($bool = true)
    {
        $this->onOneServer = $bool;
        if ($bool) {
            $this->getWithoutOverlapping();
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getOnOneServer()
    {
        return $this->onOneServer;
    }
}