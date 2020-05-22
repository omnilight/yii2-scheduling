<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\CallbackEvent;
use lexeo\yii2scheduling\Event;
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

    public function testCreatesCliCommandEvent()
    {
        $schedule = new Schedule();
        $this->assertCount(0, $schedule->getEvents());

        $cmd = 'php -i';
        $event = $schedule->exec($cmd);
        $this->assertCount(1, $schedule->getEvents());
        $this->assertInstanceOf(Event::className(), $event);
        $this->assertSame($cmd, $event->getCommand());
    }

    /**
     * @depends testCreatesCliCommandEvent
     */
    public function testCreatesYiiCommandEvent()
    {
        $schedule = new Schedule();
        $cmd = 'test/me';
        $event = $schedule->command($cmd);

        $this->assertInstanceOf(Event::className(), $event);
        $this->assertStringEndsWith($schedule->yiiCliEntryPoint . ' ' . $cmd, $event->getCommand());
    }

    public function testCreatesCallbackEvent()
    {
        $schedule = new Schedule();
        $this->assertCount(0, $schedule->getEvents());
        $params = [1, 2, 3];
        $event = $schedule->call(static function () {
        }, $params);

        $this->assertCount(1, $schedule->getEvents());
        $this->assertInstanceOf(CallbackEvent::className(), $event);
        $propReflection = (new \ReflectionObject($event))->getProperty('parameters');
        $propReflection->setAccessible(true);

        $this->assertSame($params, $propReflection->getValue($event));
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