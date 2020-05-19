<?php

namespace lexeo\yii2scheduling\tests;

use DateTimeZone;
use lexeo\yii2scheduling\Event;
use yii\mutex\Mutex;

class EventTest extends AbstractTestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|Mutex
     */
    protected $mutexMock;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->mutexMock = $this->getMock(Mutex::className());
    }

    /**
     * @return array[]
     */
    public function buildCommandData()
    {
        return [
            [false, 'php -i', '/dev/null', 'php -i > /dev/null'],
            [false, 'php -i', '/my folder/foo.log', 'php -i > /my folder/foo.log'],
            [true, 'php -i', '/dev/null', 'php -i > /dev/null 2>&1 &'],
            [true, 'php -i', '/my folder/foo.log', 'php -i > /my folder/foo.log 2>&1 &'],
        ];
    }

    /**
     * @dataProvider buildCommandData
     * @param bool $omitErrors
     * @param string $command
     * @param string $outputTo
     * @param string $result
     */
    public function testBuildCommandSendOutputTo($omitErrors, $command, $outputTo, $result)
    {
        $event = new Event($this->mutexMock, $command);
        $event->omitErrors($omitErrors);
        $event->sendOutputTo($outputTo);
        $this->assertSame($result, $event->buildCommand());
    }

    public function testTimezoneAcceptsBothStringAndDateTimeZone()
    {
        $event = new Event($this->mutexMock, '');
        $propReflection = (new \ReflectionObject($event))->getProperty('timezone');
        $propReflection->setAccessible(true);

        $this->assertNull($propReflection->getValue($event));

        $expectedTzString = 'Europe/Moscow';
        $event->timezone($expectedTzString);
        $this->assertInstanceOf('DateTimeZone', $propReflection->getValue($event));
        $this->assertEquals($expectedTzString, $propReflection->getValue($event)->getName());

        $timeZone = new DateTimeZone('UTC');
        $event->timezone($timeZone);
        $this->assertSame($timeZone, $propReflection->getValue($event));
    }
}
