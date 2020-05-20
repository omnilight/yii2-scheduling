<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\Event;

class CliEventTest extends AbstractTestCase
{

    public function testBuildsSimpleCommand()
    {
        $cmd = 'php -i';
        $defOutput = '/dev/null';

        /** @var \PHPUnit_Framework_MockObject_MockObject|Event $eventMock */
        $eventMock = $this->getMock(Event::className(), ['getDefaultOutput'], [$cmd]);
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
        /** @var \PHPUnit_Framework_MockObject_MockObject|Event $eventMock */
        $eventMock = $this->getMock(Event::className(), ['isWindows'], ['php -i']);
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

    public function testBuildsCommandChangingDir()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Event $eventMock */
        $eventMock = $this->getMock(Event::className(), ['isWindows'], ['php -i']);
        $eventMock->expects($this->any())
            ->method('isWindows')
            ->willReturn(false);

        $cmd = $eventMock->getCommand();
        $defOutput = $eventMock->getDefaultOutput();

        $this->assertSame("{$cmd} > {$defOutput}", $eventMock->buildCommand());

        $dir = '/var/www';
        $eventMock->in($dir);
        $this->assertSame("cd {$dir}; {$cmd} > {$defOutput}", $eventMock->buildCommand());
    }

    /**
     * @depends testBuildsCommandChangingUser
     * @depends testBuildsCommandChangingDir
     */
    public function testBuildsCommandChangingUserAndDir()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Event $eventMock */
        $eventMock = $this->getMock(Event::className(), ['isWindows'], ['php -i']);
        $eventMock->expects($this->any())
            ->method('isWindows')
            ->willReturn(false);

        $cmd = $eventMock->getCommand();
        $defOutput = $eventMock->getDefaultOutput();

        $this->assertSame("{$cmd} > {$defOutput}", $eventMock->buildCommand());

        $userName = 'admin';
        $dir = '/var/www';
        $eventMock->user($userName);
        $eventMock->in($dir);
        $this->assertSame("sudo -u {$userName} -- sh -c 'cd {$dir}; {$cmd} > {$defOutput}'", $eventMock->buildCommand());
    }
}