<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\Event;
use Symfony\Component\Process\Process;
use Yii;
use yii\base\ModelEvent;
use yii\console\Controller;
use yii\console\Request;
use yii\mutex\Mutex;

class CliEventTest extends AbstractTestCase
{

    public function testBuildsSimpleCommand()
    {
        $cmd = 'php -i';
        $defOutput = '/dev/null';

        $eventMock = $this->createEventMock($cmd, ['getDefaultOutput']);
        $eventMock->expects($this->any())
            ->method('getDefaultOutput')
            ->willReturn($defOutput);

        $this->assertSame("{$cmd} > {$defOutput}", $eventMock->buildCommand());

        $eventMock->appendOutputTo($defOutput);
        $this->assertSame("{$cmd} >> {$defOutput}", $eventMock->buildCommand());

        $eventMock->sendOutputTo($defOutput);
        $this->assertSame("{$cmd} > {$defOutput}", $eventMock->buildCommand());
    }

    /**
     * @depends testBuildsSimpleCommand
     */
    public function testBuildsCommandChangingUser()
    {
        $eventMock = $this->createEventMock('php -i', ['isWindows']);
        $eventMock->expects($this->any())
            ->method('isWindows')
            ->willReturn(false);

        $cmd = $eventMock->getCommand();
        $defOutput = $eventMock->getDefaultOutput();

        $this->assertSame("{$cmd} > {$defOutput}", $eventMock->buildCommand());

        $userName = 'admin';
        $eventMock->user($userName);
        $this->assertSame("sudo -u {$userName} -- sh -c '{$cmd} > {$defOutput}'", $eventMock->buildCommand());
    }

    public function testBuildsBackgroundCommand()
    {
        $this->mockApplicationRequestScriptFileAndControllerId('yii', 'schedule');

        $cmd = 'controller/action';
        $mutexName = '12345';
        $eventMock = $this->createEventMock($cmd, ['isWindows', 'mutexName']);
        $eventMock->expects($this->any())
            ->method('isWindows')
            ->willReturn(false);
        $eventMock->expects($this->any())
            ->method('mutexName')
            ->willReturn($mutexName);

        $eventMock->runInBackground(true);
        $callbackCmd = strtr('{php} {yii} {controller}/finish "{id}" "{exitCode}"', [
            '{php}' => PHP_BINARY,
            '{yii}' => Yii::$app->request->scriptFile,
            '{controller}' => Yii::$app->controller->id,
            '{id}' => $mutexName,
            '{exitCode}' => '$?',
        ]);
        $this->assertSame(
            "({$cmd} > {$eventMock->getDefaultOutput()} ; {$callbackCmd}) > /dev/null 2>&1 &",
            $eventMock->buildCommand()
        );
    }

    public function testTriggersBeforeRunEvent()
    {
        $eventMock = $this->createEventMock('php -i', ['createProcess']);
        $eventMock->expects($this->once())
            ->method('createProcess')
            ->willReturn($this->createForegroundProcessMock(0));

        $fail = true;
        $eventMock->on($eventMock::EVENT_BEFORE_RUN, function (ModelEvent $e) use (&$fail) {
            $this->assertInstanceOf(Event::className(), $e->sender);
            $this->assertTrue($e->isValid);
            $fail = false;
        });
        $eventMock->run();
        $fail && $this->fail('On beforeRun handler was not called.');
    }

    public function testTriggersAfterCompleteEvent()
    {
        $this->mockApplicationRequestScriptFileAndControllerId('yii', 'schedule');

        $expectedExitCode = 113;
        $eventMock = $this->createEventMock('php -i', ['createProcess']);
        $eventMock->expects($this->exactly(2))
            ->method('createProcess')
            ->willReturn($this->createForegroundProcessMock($expectedExitCode));

        // expect foreground run triggers complete
        $fail = true;
        $completeHandler = function (\yii\base\Event $e) use (&$fail, $expectedExitCode) {
            $this->assertInstanceOf(Event::className(), $e->sender);
            $this->assertSame($expectedExitCode, $e->sender->exitCode);
            $fail = false;
        };
        $eventMock->on($eventMock::EVENT_AFTER_COMPLETE, $completeHandler);
        $eventMock->run();
        $fail && $this->fail('On afterComplete handler was not called.');
        $eventMock->off($eventMock::EVENT_AFTER_COMPLETE, $completeHandler);

        // expect background run does NOT triggers complete
        $fail = false;
        $completeHandler = function (\yii\base\Event $e) use (&$fail) {
            $this->assertNull($e->sender->exitCode);
            $fail = true;
        };
        $eventMock->on($eventMock::EVENT_AFTER_COMPLETE, $completeHandler);
        $eventMock->runInBackground()->run();
        $fail && $this->fail('On afterComplete handler was called.');
        $eventMock->off($eventMock::EVENT_AFTER_COMPLETE, $completeHandler);

        // expect finishing background triggers complete
        $fail = true;
        $completeHandler = function (\yii\base\Event $e) use (&$fail, $expectedExitCode) {
            $this->assertInstanceOf(Event::className(), $e->sender);
            $this->assertSame($expectedExitCode, $e->sender->exitCode);
            $fail = false;
        };
        $eventMock->on($eventMock::EVENT_AFTER_COMPLETE, $completeHandler);
        $eventMock->finish($expectedExitCode);
        $fail && $this->fail('On afterComplete handler was not called.');
    }

    /**
     * @depends testTriggersBeforeRunEvent
     */
    public function testMutexPreventsOverlapping()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Mutex $mutexMock */
        $mutexMock = $this->getMockForAbstractClass(Mutex::className(), [], '', true, true, true, ['acquire', 'release']);
        $mutexMock->expects($this->exactly(2))
            ->method('acquire')
            ->willReturnOnConsecutiveCalls(true, false);

        $eventMock = $this->createEventMock('php -i', ['createProcess']);
        $eventMock->expects($this->any())
            ->method('createProcess')
            ->willReturn($this->createForegroundProcessMock(0));
        $eventMock->setMutex($mutexMock);
        $eventMock->withoutOverlapping();

        // expect running
        $beforeRunHandler = function (ModelEvent $e) {
            $this->assertTrue($e->isValid);
        };
        $eventMock->on($eventMock::EVENT_BEFORE_RUN, $beforeRunHandler);
        $eventMock->run();
        $eventMock->off($eventMock::EVENT_BEFORE_RUN, $beforeRunHandler);

        // expect skipping
        $eventMock->on($eventMock::EVENT_BEFORE_RUN, function (ModelEvent $e) {
            $this->assertFalse($e->isValid);
        });
        $eventMock->run();
    }

    /**
     * @param int $exitCode
     * @param string $command
     * @return \PHPUnit_Framework_MockObject_MockObject|Process
     */
    private function createForegroundProcessMock($exitCode, $command = '')
    {
        $mock = $this->getMock('Symfony\Component\Process\Process', ['run', 'start'], [$command]);
        $mock->expects($this->any())
            ->method('run')
            ->willReturn($exitCode);
        return $mock;
    }

    /**
     * @param string $command
     * @param array|null $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|Event
     */
    private function createEventMock($command, $methods = [])
    {
        return $this->getMock(Event::className(), $methods, [$command]);
    }


    protected function mockApplicationRequestScriptFileAndControllerId($scriptFile, $controllerId)
    {
        $this->mockApplication();

        $requestMock = $this->getMock(Request::className(), ['getScriptFile']);
        $requestMock->expects($this->any())
            ->method('getScriptFile')
            ->willReturn($scriptFile);


        Yii::$app->set('request', $requestMock);
        Yii::$app->controller = new Controller($controllerId, null);
    }
}