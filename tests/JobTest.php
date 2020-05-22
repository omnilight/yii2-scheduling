<?php

namespace lexeo\yii2scheduling\tests;

use DateTimeZone;
use lexeo\yii2scheduling\AbstractJob;

class JobTest extends AbstractTestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|AbstractJob
     */
    protected $jobMock;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->jobMock = $this->getMockForAbstractClass(AbstractJob::className());
    }

    public function testTimezoneAcceptsBothStringAndDateTimeZone()
    {
        $propReflection = (new \ReflectionClass($this->jobMock))->getProperty('timezone');
        $propReflection->setAccessible(true);

        $this->assertNull($propReflection->getValue($this->jobMock));

        $expectedTzString = 'Europe/Moscow';
        $this->jobMock->timezone($expectedTzString);
        $this->assertInstanceOf('DateTimeZone', $propReflection->getValue($this->jobMock));
        $this->assertEquals($expectedTzString, $propReflection->getValue($this->jobMock)->getName());

        $timeZone = new DateTimeZone('UTC');
        $this->jobMock->timezone($timeZone);
        $this->assertSame($timeZone, $propReflection->getValue($this->jobMock));
    }

    public function testBooleanFilters()
    {
        $methodReflection = (new \ReflectionObject($this->jobMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->when(true);
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->when(false);
        $this->assertFalse($methodReflection->invoke($this->jobMock));
    }

    public function testCallbackFilters()
    {
        $methodReflection = (new \ReflectionObject($this->jobMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->when(static function () {
            return true;
        });
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->when(static function () {
            return false;
        });
        $this->assertFalse($methodReflection->invoke($this->jobMock));
    }

    public function testBooleanRejects()
    {
        $methodReflection = (new \ReflectionObject($this->jobMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->skip(false);
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->skip(true);
        $this->assertFalse($methodReflection->invoke($this->jobMock));
    }

    public function testCallbackRejects()
    {
        $methodReflection = (new \ReflectionObject($this->jobMock))->getMethod('filtersPass');
        $methodReflection->setAccessible(true);
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->skip(static function () {
            return false;
        });
        $this->assertTrue($methodReflection->invoke($this->jobMock));

        $this->jobMock->skip(static function () {
            return true;
        });
        $this->assertFalse($methodReflection->invoke($this->jobMock));
    }
}
