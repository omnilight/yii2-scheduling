<?php

namespace lexeo\yii2scheduling;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
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
     * Schedule constructor.
     * @param array $config
     * @throws InvalidConfigException
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        if (null === $this->mutex) {
            $this->mutex = Yii::$app->has('mutex')
                ? Instance::ensure('mutex', Mutex::className())
                : new FileMutex(['autoRelease' => false]);
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
        $job->setMutex($this->mutex);

        $this->jobs[] = $job;
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
        $job->setMutex($this->mutex);

        $this->jobs[] = $job;
        return $job;
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
        return array_filter($this->jobs, static function (AbstractJob $job) {
            return $job->isDue();
        });
    }
}
