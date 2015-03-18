<?php

namespace omnilight\scheduling;
use yii\console\Controller;
use yii\di\Instance;


/**
 * Class ScheduleController
 */
class ScheduleController extends Controller
{
    /**
     * @var Schedule
     */
    public $schedule = [];
    /**
     * Schedule file that will be used to run schedule
     * @var string
     */
    public $scheduleFile;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID),
            $actionID == 'run' ? ['scheduleFile'] : []
        );
    }


    public function init()
    {
        $this->schedule = Instance::ensure($this->schedule, Schedule::className());
        parent::init();
    }


    public function actionRun()
    {
        $this->importScheduleFile();

        $events = $this->schedule->dueEvents(\Yii::$app);

        foreach ($events as $event) {
            $this->stdout('Running scheduled command: '.$event->getSummaryForDisplay()."\n");
            $event->run(\Yii::$app);
        }

        if (count($events) === 0)
        {
            $this->stdout("No scheduled commands are ready to run.\n");
        }
    }

    protected function importScheduleFile()
    {
        if ($this->scheduleFile === null) {
            return;
        }

        $scheduleFile = \Yii::getAlias($this->scheduleFile);
        if (file_exists($scheduleFile) == false) {
            $this->stderr('Can not load schedule file '.$this->scheduleFile."\n");
            return;
        }

        $schedule = $this->schedule;
        call_user_func(function() use ($schedule, $scheduleFile) {
            include $scheduleFile;
        });
    }
}