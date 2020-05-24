<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\Schedule;
use lexeo\yii2scheduling\ScheduleController;
use lexeo\yii2scheduling\ShellJob;
use Yii;
use yii\mutex\Mutex;

class ScheduleControllerTest extends AbstractTestCase
{
    public function testForcesRunningConcurrentShellJobsInBackground()
    {
        $this->mockApplication([
            'components' => [
                'schedule' => new Schedule([
                    'mutex' => $this->getMockForAbstractClass(Mutex::className()),
                    'yiiCliEntryPoint' => '/',
                ]),
            ],
]       );

        $controller = new ScheduleController('schedule-controller', Yii::$app);
        $controller->verbose = false;
        Yii::$app->controller = $controller;

        $jobMock1 = $this->createShellJobMock('php -i', ['createProcess', 'runCommandInBackground']);
        $jobMock1->expects($this->exactly(2))
            ->method('runCommandInBackground');

        $jobMock2 = $this->createShellJobMock('php -m', ['createProcess', 'runCommandInBackground']);
        $jobMock2->expects($this->exactly(2))
            ->method('runCommandInBackground');

        $this->assertEmpty($controller->schedule->getJobs());
        $controller->schedule->add($jobMock1);
        $controller->schedule->add($jobMock2);
        $this->assertCount(2, $controller->schedule->getJobs());

        $controller->runConcurrentShellJobsInBackground = true;

        $this->assertFalse($jobMock1->runInBackground);
        $this->assertFalse($jobMock2->runInBackground);
        $controller->actionRun();


        $jobMock3 = $this->createShellJobMock('php -v', ['createProcess', 'runCommandInForeground']);
        $jobMock3->expects($this->once())
            ->method('runCommandInForeground');

        $controller->schedule->add($jobMock3);
        $this->assertCount(3, $controller->schedule->getJobs());

        $controller->runConcurrentShellJobsInBackground = false;
        $this->assertFalse($jobMock3->runInBackground);
        $controller->actionRun();
    }

    /**
     * @param string $command
     * @param array|null $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|ShellJob $jobMock
     */
    protected function createShellJobMock($command, $methods = ['createProcess'])
    {
        return $this->getMock(ShellJob::className(), $methods, [$command]);
    }
}