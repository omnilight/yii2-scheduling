<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\CallbackJob;
use lexeo\yii2scheduling\ShellJob;
use lexeo\yii2scheduling\Schedule;

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
}