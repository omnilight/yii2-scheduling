<?php

namespace omnilight\scheduling\Tests;

class EventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function outputData()
    {
        return [
            ['test', 'test'],
            ['', '(empty)'],
        ];
    }

    public function testEmailOutputCalled()
    {
        $process = $this->getMockBuilder('Symfony\Component\Process\Process')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $mailer = $this->getMock('yii\mail\BaseMailer');

        /** @var \yii\console\Application|\PHPUnit_Framework_MockObject_MockObject $app */
        $app = $this->getMockBuilder('yii\console\Application')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $app->expects($this->once())
            ->method('__get')
            ->willReturnCallback(function ($name) use ($mailer) {
                if ($name === 'mailer') {
                    return $mailer;
                }
                return null;
            })
        ;

        /** @var \omnilight\scheduling\Event|\PHPUnit_Framework_MockObject_MockObject $event */
        $event = $this->getMockBuilder('omnilight\scheduling\Event')
            ->disableOriginalConstructor()
            ->setMethods(['emailOutput', 'runCommandInForeground'])
            ->getMock()
        ;
        $event->expects($this->once())
            ->method('emailOutput')
        ;
        $event->expects($this->once())
            ->method('runCommandInForeground')
            ->willReturn($process)
        ;

        $event->emailOutputTo([]);
        $event->run($app);
    }

    /**
     * @dataProvider outputData
     * @param string $commandOutput
     * @param string $expectedBody
     */
    public function testEmailOutputGetsData($commandOutput, $expectedBody)
    {
        $message = $this->getMock('yii\mail\BaseMessage');
        $message->expects($this->once())->method('setTextBody')->with($this->equalTo($expectedBody))->willReturnSelf();
        $message->expects($this->once())->method('setSubject')->willReturnSelf();
        $message->expects($this->once())->method('setTo')->willReturnSelf();

        $mailer = $this->getMock('yii\mail\BaseMailer');
        $mailer->expects($this->once())->method('compose')->willReturn($message);

        $process = $this->getMockBuilder('Symfony\Component\Process\Process')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $process->expects($this->once())->method('getOutput')->willReturn($commandOutput);

        $event = $this->getMockBuilder('omnilight\scheduling\Event')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->invokeMethod($event, 'emailOutput', [$mailer, [], $process]);
    }
}