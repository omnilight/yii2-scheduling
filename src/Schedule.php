<?php

namespace lexeo\yii2scheduling;

use DateTime;
use DateTimeZone;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\ModelEvent;
use yii\console\Application;
use yii\di\Instance;
use yii\mutex\FileMutex;
use yii\mutex\Mutex;


/**
 * Class Schedule
 */
class Schedule extends Component
{
    /**
     * All of the jobs on the schedule.
     *
     * @var AbstractJob[]
     */
    protected $jobs = [];

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
     * @var
     */
    protected $timezone;

    /**
     * Schedule constructor.
     * @param array $config
     * @throws InvalidConfigException
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (null === $this->mutex) {
            $this->mutex = new FileMutex(['autoRelease' => false]);
        }

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
     * Add a new callback job to the schedule.
     *
     * @param string $callback
     * @param array $parameters
     * @return CallbackJob
     */
    public function call($callback, array $parameters = [])
    {
        $job = new CallbackJob($callback, $parameters);
        $this->add($job);

        return $job;
    }

    /**
     * Add a new cli command job to the schedule.
     *
     * @param string $command
     * @return ShellJob
     */
    public function command($command)
    {
        return $this->exec(PHP_BINARY . ' ' . $this->yiiCliEntryPoint . ' ' . $command);
    }

    /**
     * Add a new command job to the schedule.
     *
     * @param string $command
     * @return ShellJob
     */
    public function exec($command)
    {
        $job = new ShellJob($command);
        $this->add($job);

        return $job;
    }

    /**
     * @param AbstractJob $job
     */
    public function add(AbstractJob $job)
    {
        $this->attachHandlerPreventingOverlapping($job);
        $this->jobs[] = $job;
    }

    /**
     * @return AbstractJob[]
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * Get all of the jobs on the schedule that are due.
     *
     * @return AbstractJob[]
     */
    public function dueJobs()
    {
        $currentTime = new DateTime('now', $this->timezone);
        return array_filter($this->jobs, static function (AbstractJob $job) use ($currentTime) {
            return $job->isDue($currentTime);
        });
    }

    /**
     * @param Mutex|string $mutex
     * @return $this
     * @throws InvalidConfigException
     */
    public function setMutex($mutex)
    {
        $this->mutex = Instance::ensure($mutex, Mutex::className());
        return $this;
    }

    /**
     * Set the timezone the date should be evaluated on.
     *
     * @param DateTimeZone|string $timezone
     * @return $this
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        return $this;
    }

    /**
     * @param AbstractJob $job
     * @return void
     */
    protected function attachHandlerPreventingOverlapping(AbstractJob $job)
    {
        $jobUniqId = $job->mutexName();
        $job->on($job::EVENT_BEFORE_RUN, function(ModelEvent $e) use ($jobUniqId) {
            /** @var AbstractJob $job */
            $job = $e->sender;
            if ($job->getWithoutOverlapping() && !$this->mutex->acquire($jobUniqId)) {
                $e->isValid = false;
            }
        });
        $job->then(function () use ($jobUniqId) {
            $this->mutex->release($jobUniqId);
        });
        //TODO skip if lock exists. Unfortunately Mutex class doesn't allow to check if lock exists
    }
}
