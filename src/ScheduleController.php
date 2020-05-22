<?php

namespace lexeo\yii2scheduling;

use Yii;
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

    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID),
            $actionID === 'run' ? ['scheduleFile', 'omitErrors'] : []
        );
    }


    public function init()
    {
        if (Yii::$app->has($this->schedule)) {
            $this->schedule = Instance::ensure($this->schedule, Schedule::className());
        } else {
            $this->schedule = Yii::createObject(Schedule::className());
        }
        parent::init();
    }


    public function actionRun()
    {
        $this->importScheduleFile();

        $jobs = $this->schedule->dueJobs();

        foreach ($jobs as $job) {
            if (!$job->filtersPass()) {
                continue;
            }

            if ($this->omitErrors !== null) {
                $job->omitErrors($this->omitErrors);
            }
            $this->stdout('Running scheduled command: ' . $job->getSummaryForDisplay() . "\n");
            $job->run();
        }

        if (count($jobs) === 0) {
            $this->stdout("No scheduled commands are ready to run.\n");
        }
    }

    public function actionFinish($id, $exitCode = 0)
    {
        $this->importScheduleFile();

        foreach ($this->schedule->getJobs() as $job) {
            /** @var ShellJob $job */
            if ($id === $job->mutexName()) {
                $job->finish($exitCode);
                break;
            }
        }
    }

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
    }
}