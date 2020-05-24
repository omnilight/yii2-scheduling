<?php

namespace lexeo\yii2scheduling\tests;

use DateTimeZone;
use lexeo\yii2scheduling\AbstractJob;
use lexeo\yii2scheduling\CallbackJob;
use lexeo\yii2scheduling\ShellJob;
use lexeo\yii2scheduling\Schedule;
use yii\base\Event;
use yii\base\ModelEvent;
use yii\mutex\Mutex;

class ScheduleTest extends AbstractTestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    public function testCreatesShellCommandJob()
    {
        $schedule = new Schedule();
        $this->assertCount(0, $schedule->getJobs());

        $cmd = 'php -i';
        $job = $schedule->exec($cmd);
        $this->assertCount(1, $schedule->getJobs());
        $this->assertInstanceOf(ShellJob::className(), $job);
        $this->assertSame($cmd, $job->getCommand());
    }

    /**
     * @depends testCreatesShellCommandJob
     */
    public function testCreatesYiiCommandJob()
    {
        $schedule = new Schedule();
        $cmd = 'test/me';
        $job = $schedule->command($cmd);

        $this->assertInstanceOf(ShellJob::className(), $job);
        $this->assertStringEndsWith($schedule->yiiCliEntryPoint . ' ' . $cmd, $job->getCommand());
    }

    public function testCreatesCallbackJob()
    {
        $schedule = new Schedule();
        $this->assertCount(0, $schedule->getJobs());
        $params = [1, 2, 3];
        $job = $schedule->call(static function () {
        }, $params);

        $this->assertCount(1, $schedule->getJobs());
        $this->assertInstanceOf(CallbackJob::className(), $job);
        $propReflection = (new \ReflectionObject($job))->getProperty('parameters');
        $propReflection->setAccessible(true);

        $this->assertSame($params, $propReflection->getValue($job));
    }

    public function testUsesYiiCliEntryPointAbsolutePath()
    {
        $bootstrapFile = 'tests/bootstrap.php';
        $schedule = new Schedule(['yiiCliEntryPoint' => $bootstrapFile]);

        $this->assertStringEndsWith($bootstrapFile, $schedule->yiiCliEntryPoint);
        $this->assertNotEquals($bootstrapFile, $schedule->yiiCliEntryPoint);
    }

    public function testFailsIfYiiCliEntryPointInvalid()
    {
        $this->setExpectedException('\yii\base\InvalidConfigException');
        $this->mockApplication([
            'components' => [
                'schedule' => [
                    'class' => Schedule::className(),
                    'yiiCliEntryPoint' => '_invalid_path_',
                ],
            ],
        ], \yii\web\Application::className());
    }

    public function testSetTimezoneAcceptsBothStringAndDateTimeZone()
    {
        $schedule = new Schedule();
        $propReflection = (new \ReflectionClass($schedule))->getProperty('timezone');
        $propReflection->setAccessible(true);

        $this->assertNull($propReflection->getValue($schedule));

        $expectedTzString = 'Europe/Moscow';
        $schedule->setTimezone($expectedTzString);
        $this->assertInstanceOf('DateTimeZone', $propReflection->getValue($schedule));
        $this->assertEquals($expectedTzString, $propReflection->getValue($schedule)->getName());

        $timeZone = new DateTimeZone('UTC');
        $schedule->setTimezone($timeZone);
        $this->assertSame($timeZone, $propReflection->getValue($schedule));
    }

    public function testMutexPreventsOverlapping()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Mutex $mutexMock */
        $mutexMock = $this->getMockForAbstractClass(Mutex::className(), [], '', true, true, true, ['acquire', 'release']);
        /** @var \PHPUnit_Framework_MockObject_MockObject|AbstractJob $jobMock */
        $jobMock = $this->getMockForAbstractClass(AbstractJob::className());

        $schedule = new Schedule([
            'mutex' => $mutexMock,
        ]);
        $schedule->add($jobMock);

        $mutexMock->expects($this->exactly(2))
            ->method('acquire')
            ->willReturnOnConsecutiveCalls(true, false);

        // no need to set lock
        $this->assertTrue($this->triggerJobBeforeRun($jobMock)->isValid);

        // set lock
        $jobMock->withoutOverlapping();
        $this->assertTrue($this->triggerJobBeforeRun($jobMock)->isValid);

        // locked
        $this->assertFalse($this->triggerJobBeforeRun($jobMock)->isValid);

        // releases mock after complete
        $mutexMock->expects($this->once())
            ->method('release');
        $this->triggerJobAfterComplete($jobMock);
    }

    /**
     * @param AbstractJob $job
     * @return ModelEvent
     */
    protected function triggerJobBeforeRun(AbstractJob $job)
    {
        $event = new ModelEvent();
        $job->trigger($job::EVENT_BEFORE_RUN, $event);
        return $event;
    }

    /**
     * @param AbstractJob $job
     * @return Event
     */
    protected function triggerJobAfterComplete(AbstractJob $job)
    {
        $event = new Event();
        $job->trigger($job::EVENT_AFTER_COMPLETE, $event);
        return $event;
    }
}