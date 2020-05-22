<?php

namespace lexeo\yii2scheduling\tests;

use DateTimeZone;
use lexeo\yii2scheduling\AbstractJob;

class JobTest extends AbstractTestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|AbstractJob $eventMock
     */
    protected $eventMock;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->eventMock = $this->getMockForAbstractClass(AbstractJob::className());
    }

    public function testTimezoneAcceptsBothStringAndDateTimeZone()
    {
        $propReflection = (new \ReflectionClass($this->eventMock))->getProperty('timezone');
        $propReflection->setAccessible(true);

        $this->assertNull($propReflection->getValue($this->eventMock));

        $expectedTzString = 'Europe/Moscow';
        $this->eventMock->timezone($expectedTzString);
        $this->assertInstanceOf('DateTimeZone', $propReflection->getValue($this->eventMock));
        $this->assertEquals($expectedTzString, $propReflection->getValue($this->eventMock)->getName());

        $timeZone = new DateTimeZone('UTC');
        $this->eventMock->timezone($timeZone);
        $this->assertSame($timeZone, $propReflection->getValue($this->eventMock));
    }

    public function testBooleanFilters()
    {
        $methodReflection = (new \ReflectionObject($this->eventMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->when(true);
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->when(false);
        $this->assertFalse($methodReflection->invoke($this->eventMock));
    }

    public function testCallbackFilters()
    {
        $methodReflection = (new \ReflectionObject($this->eventMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->when(static function () {
            return true;
        });
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->when(static function () {
            return false;
        });
        $this->assertFalse($methodReflection->invoke($this->eventMock));
    }

    public function testBooleanRejects()
    {
        $methodReflection = (new \ReflectionObject($this->eventMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->skip(false);
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->skip(true);
        $this->assertFalse($methodReflection->invoke($this->eventMock));
    }

    public function testCallbackRejects()
    {
        $methodReflection = (new \ReflectionObject($this->eventMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->skip(static function () {
            return false;
        });
        $this->assertTrue($methodReflection->invoke($this->eventMock));

        $this->eventMock->skip(static function () {
            return true;
        });
        $this->assertFalse($methodReflection->invoke($this->eventMock));
    }
}
