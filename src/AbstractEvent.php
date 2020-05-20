<?php

namespace lexeo\yii2scheduling;

use Closure;
use Cron\CronExpression;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client as HttpClient;
use yii\base\InvalidConfigException;
use yii\mutex\FileMutex;
use yii\mutex\Mutex;

abstract class AbstractEvent extends \yii\base\Component
{
    const EVENT_BEFORE_RUN = 'beforeRun';
    const EVENT_AFTER_RUN = 'afterRun';

    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    protected $expression = '* * * * *';
    /**
     * The timezone the date should be evaluated on.
     *
     * @var DateTimeZone|null
     */
    protected $timezone;
    /**
     * The filter callback.
     *
     * @var Closure
     */
    protected $filter;
    /**
     * The reject callback.
     *
     * @var Closure
     */
    protected $reject;
    /**
     * The array of callbacks to be run after the event is finished.
     *
     * @var array
     */
    protected $afterCallbacks = [];
    /**
     * The human readable description of the event.
     *
     * @var string
     */
    protected $description;
    /**
     * The mutex implementation.
     *
     * @var Mutex|null
     */
    protected $mutex;
    /**
     * Decide if errors will be displayed.
     *
     * @var bool
     */
    protected $omitErrors = false;

    /**
     * Run the given event.
     */
    abstract public function run();

    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    abstract public function getSummaryForDisplay();

    /**
     * Get the mutex name for the scheduled command.
     *
     * @return string
     */
    abstract protected function mutexName();

    /**
     * Register a callback to further filter the schedule.
     *
     * @param Closure $callback
     * @return $this
     */
    public function when(Closure $callback)
    {
        $this->filter = $callback;
        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param Closure $callback
     * @return $this
     */
    public function then(Closure $callback)
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback to the ping a given URL after the job runs.
     *
     * @param string $url
     * @return $this
     */
    public function thenPing($url)
    {
        return $this->then(static function () use ($url) {
            (new HttpClient)->get($url);
        });
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param Closure $callback
     * @return $this
     */
    public function skip(Closure $callback)
    {
        $this->reject = $callback;
        return $this;
    }

    /**
     * Call all of the "after" callbacks for the event.
     */
    protected function callAfterCallbacks()
    {
        foreach ($this->afterCallbacks as $callback) {
            call_user_func($callback);
        }
    }

    /**
     * Set the human-friendly description of the event.
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
     * Get the Cron expression for the event.
     *
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * Set the timezone the date should be evaluated on.
     *
     * @param DateTimeZone|string $timezone
     * @return $this
     */
    public function timezone($timezone)
    {
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        return $this;
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
     * Determine if the given event should run based on the Cron expression.
     *
     * @return bool
     */
    public function isDue()
    {
        return $this->expressionPasses() && $this->filtersPass();
    }

    /**
     * Determine if the Cron expression passes.
     *
     * @return bool
     */
    protected function expressionPasses()
    {
        $date = new DateTime('now', $this->timezone);
        return CronExpression::factory($this->expression)->isDue($date);
    }

    /**
     * Determine if the filters pass for the event.
     *
     * @return bool
     */
    protected function filtersPass()
    {
        if (($this->filter && !call_user_func($this->filter))
            || ($this->reject && call_user_func($this->reject))
        ) {
            return false;
        }
        return true;
    }

    /**
     * The Cron expression representing the event's frequency.
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
     * Schedule the event to run hourly.
     *
     * @return $this
     */
    public function hourly()
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * Schedule the event to run hourly at a given offset in the hour.
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
     * Schedule the event to run daily.
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
     * Schedule the event to run daily at a given time (10:00, 19:30, etc).
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
     * Schedule the event to run twice daily.
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
     * Schedule the event to run only on weekdays.
     *
     * @return $this
     */
    public function weekdays()
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    /**
     * Schedule the event to run only on Mondays.
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
     * Schedule the event to run only on Tuesdays.
     *
     * @return $this
     */
    public function tuesdays()
    {
        return $this->days(2);
    }

    /**
     * Schedule the event to run only on Wednesdays.
     *
     * @return $this
     */
    public function wednesdays()
    {
        return $this->days(3);
    }

    /**
     * Schedule the event to run only on Thursdays.
     *
     * @return $this
     */
    public function thursdays()
    {
        return $this->days(4);
    }

    /**
     * Schedule the event to run only on Fridays.
     *
     * @return $this
     */
    public function fridays()
    {
        return $this->days(5);
    }

    /**
     * Schedule the event to run only on Saturdays.
     *
     * @return $this
     */
    public function saturdays()
    {
        return $this->days(6);
    }

    /**
     * Schedule the event to run only on Sundays.
     *
     * @return $this
     */
    public function sundays()
    {
        return $this->days(0);
    }

    /**
     * Schedule the event to run weekly.
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
     * Schedule the event to run weekly on a given day and time.
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
     * Schedule the event to run monthly.
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
     * Schedule the event to run monthly on a given day and time.
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
     * Schedule the event to run twice monthly.
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
     * Schedule the event to run quarterly.
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
     * Schedule the event to run yearly.
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
     * Schedule the event to run every minute.
     *
     * @return $this
     */
    public function everyMinute()
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * Schedule the event to run every N minutes.
     *
     * @param int|string $minutes
     * @return $this
     */
    public function everyNMinutes($minutes)
    {
        return $this->spliceIntoPosition(1, '*/' . $minutes);
    }

    /**
     * Schedule the event to run every five minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->everyNMinutes(5);
    }

    /**
     * Schedule the event to run every ten minutes.
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->everyNMinutes(10);
    }

    /**
     * Schedule the event to run every thirty minutes.
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
     * @param Mutex|null $mutex
     * @return $this
     */
    public function setMutex($mutex)
    {
        if (null !== $mutex && !$mutex instanceof Mutex) {
            throw new \InvalidArgumentException(sprintf(
                'Instance of "%s" expected, "%s" provided.',
                Mutex::className(),
                is_object($mutex) ? get_class($mutex) : gettype($mutex)
            ));
        }
        $this->mutex = $mutex;
        return $this;
    }

    /**
     * Do not allow the event to overlap each other.
     *
     * @return $this
     */
    public function withoutOverlapping()
    {
        $this->ensureMutexDefined();
        return $this->then(function () {
            $this->mutex->release($this->mutexName());
        })->skip(function () {
            return !$this->mutex->acquire($this->mutexName());
        });
    }

    /**
     * Allow the event to only run on one server for each cron expression.
     *
     * @return $this
     */
    public function onOneServer()
    {
        $this->ensureMutexDefined();
        if ($this->mutex instanceof FileMutex) {
            throw new InvalidConfigException(
                'You must config mutex in the application component, except the FileMutex.'
            );
        }

        return $this->withoutOverlapping();
    }

    /**
     * @throws InvalidConfigException
     */
    private function ensureMutexDefined()
    {
        if (null === $this->mutex) {
            throw new InvalidConfigException('For preventing overlapping a Mutex component is required.');
        }
    }
}