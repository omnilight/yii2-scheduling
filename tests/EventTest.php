<?php

namespace lexeo\yii2scheduling\tests;

use DateTimeZone;
use lexeo\yii2scheduling\AbstractEvent;

class EventTest extends AbstractTestCase
{
    public function testTimezoneAcceptsBothStringAndDateTimeZone()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|AbstractEvent $eventMock */
        $eventMock = $this->getMockForAbstractClass(AbstractEvent::className());

        $propReflection = (new \ReflectionClass($eventMock))->getProperty('timezone');
        $propReflection->setAccessible(true);

        $this->assertNull($propReflection->getValue($eventMock));

        $expectedTzString = 'Europe/Moscow';
        $eventMock->timezone($expectedTzString);
        $this->assertInstanceOf('DateTimeZone', $propReflection->getValue($eventMock));
        $this->assertEquals($expectedTzString, $propReflection->getValue($eventMock)->getName());

        $timeZone = new DateTimeZone('UTC');
        $eventMock->timezone($timeZone);
        $this->assertSame($timeZone, $propReflection->getValue($eventMock));
    }
}
