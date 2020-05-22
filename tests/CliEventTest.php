<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\Event;
use Yii;
use yii\console\Controller;
use yii\console\Request;

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