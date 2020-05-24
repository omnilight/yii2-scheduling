<?php

namespace lexeo\yii2scheduling;

use Yii;
use yii\base\InvalidParamException;
use yii\console\Controller;
use yii\di\Instance;


/**
 * Run the scheduled commands
 */
class ScheduleController extends Controller
{
    /**
     * @var Schedule
     */
    public $schedule = 'schedule';
    /**
     * @var string Schedule file that will be used to run schedule
     */
    public $scheduleFile;
    /**
     * @var bool|null set to true to avoid error output.
     * Note: if not null, the specified value will be applied globally for all commands
     */
    public $omitErrors;

    /**
     * @inheritDoc
     */
    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID),
            'run' === $actionID ? ['scheduleFile', 'omitErrors'] : []
        );
    }

    /**
     * @inheritDoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if (Yii::$app->has($this->schedule)) {
            $this->schedule = Instance::ensure($this->schedule, Schedule::className());
        } else {
            $this->schedule = Yii::createObject(Schedule::className());
        }
        parent::init();
    }


    /**
     * @return void
     * @throws InvalidParamException
     */
    public function actionRun()
    {
        $this->importScheduleFile();

        $jobsRan = false;
        foreach ($this->schedule->dueJobs() as $job) {
            if (!$job->filtersPass()) {
                continue;
            }
            if ($this->omitErrors !== null) {
                $job->omitErrors($this->omitErrors);
            }
            $this->stdout('Running scheduled command: ' . $job->getSummaryForDisplay() . "\n");
            $job->run();
            $jobsRan = true;
        }

        if (false === $jobsRan) {
            $this->stdout("No scheduled commands are ready to run.\n");
        }
    }

    /**
     * @param string $id The unique Job id
     * @param int $exitCode
     * @return void
     * @throws InvalidParamException
     */
    public function actionFinish($id, $exitCode = 0)
    {
        $this->importScheduleFile();
        foreach ($this->schedule->getJobs() as $job) {
            if ($job instanceof ShellJob && $id === $job->getId()) {
                $job->finish($exitCode);
                break;
            }
        }
    }

    /**
     * @return void
     * @throws InvalidParamException
     */
    protected function importScheduleFile()
    {
        if ($this->scheduleFile === null) {
            return;
        }

        $scheduleFile = Yii::getAlias($this->scheduleFile);
        if (!file_exists($scheduleFile)) {
            $this->stderr('Can not load schedule file ' . $this->scheduleFile . "\n");
            return;
        }

        $schedule = $this->schedule;
        call_user_func(static function () use ($schedule, $scheduleFile) {
            include $scheduleFile;
        });

        //TODO validate Schedule. Ensure that everything will work fine (for example, FileMutex cannot prevent overlapping on multiple servers)
    }
}