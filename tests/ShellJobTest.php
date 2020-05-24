<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\ShellJob;
use Symfony\Component\Process\Process;
use Yii;
use yii\base\ModelEvent;
use yii\console\Controller;
use yii\console\Request;
use yii\mutex\Mutex;

class ShellJobTest extends AbstractTestCase
{

    public function testBuildsSimpleCommand()
    {
        $cmd = 'php -i';
        $defOutput = '/dev/null';

        $jobMock = $this->createJobMock($cmd, ['getDefaultOutput']);
        $jobMock->expects($this->any())
            ->method('getDefaultOutput')
            ->willReturn($defOutput);

        $this->assertSame("{$cmd} > {$defOutput}", $jobMock->buildCommand());

        $jobMock->appendOutputTo($defOutput);
        $this->assertSame("{$cmd} >> {$defOutput}", $jobMock->buildCommand());

        $jobMock->sendOutputTo($defOutput);
        $this->assertSame("{$cmd} > {$defOutput}", $jobMock->buildCommand());
    }

    /**
     * @depends testBuildsSimpleCommand
     */
    public function testBuildsCommandChangingUser()
    {
        $jobMock = $this->createJobMock('php -i', ['isWindows']);
        $jobMock->expects($this->any())
            ->method('isWindows')
            ->willReturn(false);

        $cmd = $jobMock->getCommand();
        $defOutput = $jobMock->getDefaultOutput();

        $this->assertSame("{$cmd} > {$defOutput}", $jobMock->buildCommand());

        $userName = 'admin';
        $jobMock->user($userName);
        $this->assertSame("sudo -u {$userName} -- sh -c '{$cmd} > {$defOutput}'", $jobMock->buildCommand());
    }

    public function testBuildsBackgroundCommand()
    {
        $this->mockApplicationRequestScriptFileAndControllerId('yii', 'schedule');

        $cmd = 'controller/action';
        $mutexName = '12345';
        $jobMock = $this->createJobMock($cmd, ['isWindows', 'mutexName']);
        $jobMock->expects($this->any())
            ->method('isWindows')
            ->willReturn(false);
        $jobMock->expects($this->any())
            ->method('mutexName')
            ->willReturn($mutexName);

        $jobMock->runInBackground(true);
        $callbackCmd = strtr('{php} {yii} {controller}/finish "{id}" "{exitCode}"', [
            '{php}' => PHP_BINARY,
            '{yii}' => Yii::$app->request->scriptFile,
            '{controller}' => Yii::$app->controller->id,
            '{id}' => $mutexName,
            '{exitCode}' => '$?',
        ]);
        $this->assertSame(
            "({$cmd} > {$jobMock->getDefaultOutput()} ; {$callbackCmd}) > /dev/null 2>&1 &",
            $jobMock->buildCommand()
        );
    }

    public function testCreatesProcess()
    {
        $job = new ShellJob('php -i');
        $methodReflection = (new \ReflectionObject($job))->getMethod('createProcess');
        $methodReflection->setAccessible(true);

        /** @var \Symfony\Component\Process\Process $process */
        $process = $methodReflection->invoke($job, $job->getCommand());
        $this->assertInstanceOf('\Symfony\Component\Process\Process', $process);
        $this->assertSame($job->getCommand(), $process->getCommandLine());
    }

    public function testTriggersBeforeRunEvent()
    {
        $jobMock = $this->createJobMock('php -i', ['createProcess']);
        $jobMock->expects($this->once())
            ->method('createProcess')
            ->willReturn($this->createForegroundProcessMock(0));

        $fail = true;
        $jobMock->on($jobMock::EVENT_BEFORE_RUN, function (ModelEvent $e) use (&$fail) {
            $this->assertInstanceOf(ShellJob::className(), $e->sender);
            $this->assertTrue($e->isValid);
            $fail = false;
        });
        $jobMock->run();
        $fail && $this->fail('On beforeRun handler was not called.');
    }

    public function testTriggersAfterCompleteEvent()
    {
        $this->mockApplicationRequestScriptFileAndControllerId('yii', 'schedule');

        $expectedExitCode = 113;
        $jobMock = $this->createJobMock('php -i', ['createProcess']);
        $jobMock->expects($this->exactly(2))
            ->method('createProcess')
            ->willReturn($this->createForegroundProcessMock($expectedExitCode));

        // expect foreground run triggers complete
        $fail = true;
        $completeHandler = function (\yii\base\Event $e) use (&$fail, $expectedExitCode) {
            $this->assertInstanceOf(ShellJob::className(), $e->sender);
            $this->assertSame($expectedExitCode, $e->sender->exitCode);
            $fail = false;
        };
        $jobMock->on($jobMock::EVENT_AFTER_COMPLETE, $completeHandler);
        $jobMock->run();
        $fail && $this->fail('On afterComplete handler was not called.');
        $jobMock->off($jobMock::EVENT_AFTER_COMPLETE, $completeHandler);

        // expect background run does NOT triggers complete
        $fail = false;
        $completeHandler = function (\yii\base\Event $e) use (&$fail) {
            $this->assertNull($e->sender->exitCode);
            $fail = true;
        };
        $jobMock->on($jobMock::EVENT_AFTER_COMPLETE, $completeHandler);
        $jobMock->runInBackground()->run();
        $fail && $this->fail('On afterComplete handler was called.');
        $jobMock->off($jobMock::EVENT_AFTER_COMPLETE, $completeHandler);

        // expect finishing background triggers complete
        $fail = true;
        $completeHandler = function (\yii\base\Event $e) use (&$fail, $expectedExitCode) {
            $this->assertInstanceOf(ShellJob::className(), $e->sender);
            $this->assertSame($expectedExitCode, $e->sender->exitCode);
            $fail = false;
        };
        $jobMock->on($jobMock::EVENT_AFTER_COMPLETE, $completeHandler);
        $jobMock->finish($expectedExitCode);
        $fail && $this->fail('On afterComplete handler was not called.');
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
     * @return \PHPUnit_Framework_MockObject_MockObject|ShellJob
     */
    private function createJobMock($command, $methods = [])
    {
        return $this->getMock(ShellJob::className(), $methods, [$command]);
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