<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\AbstractEvent;

class EventFrequencyTest extends AbstractTestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|AbstractEvent
     */
    protected $eventMock;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->eventMock = $this->getMockForAbstractClass('lexeo\yii2scheduling\AbstractEvent');
    }


    public function testEveryMinute()
    {
        $this->assertSame('* * * * *', $this->eventMock->getExpression());
        $this->assertSame('* * * * *', $this->eventMock->everyMinute()->getExpression());
    }

    public function testEveryNMinutes()
    {
        $this->assertSame('* * * * *', $this->eventMock->getExpression());
        $this->assertSame('*/6 * * * *', $this->eventMock->everyNMinutes(6)->getExpression());
        $this->assertSame('*/15 * * * *', $this->eventMock->everyNMinutes(15)->getExpression());
    }

    public function testEveryFiveMinutes()
    {
        $this->assertSame('*/5 * * * *', $this->eventMock->everyFiveMinutes()->getExpression());
    }

    public function testDaily()
    {
        $this->assertSame('0 0 * * *', $this->eventMock->daily()->getExpression());
    }

    public function testTwiceDaily()
    {
        $this->assertSame('0 3,15 * * *', $this->eventMock->twiceDaily(3, 15)->getExpression());
    }

    public function testOverrideWithHourly()
    {
        $this->assertSame('0 * * * *', $this->eventMock->everyFiveMinutes()->hourly()->getExpression());
        $this->assertSame('37 * * * *', $this->eventMock->hourlyAt(37)->getExpression());
        $this->assertSame('15,30,45 * * * *', $this->eventMock->hourlyAt([15, 30, 45])->getExpression());
    }

    public function testMonthlyOn()
    {
        $this->assertSame('0 15 4 * *', $this->eventMock->monthlyOn(4, '15:00')->getExpression());
    }

    public function testTwiceMonthly()
    {
        $this->assertSame('0 0 1,16 * *', $this->eventMock->twiceMonthly(1, 16)->getExpression());
    }

    public function testMonthlyOnWithMinutes()
    {
        $this->assertSame('15 15 4 * *', $this->eventMock->monthlyOn(4, '15:15')->getExpression());
    }

    public function testWeekdaysDaily()
    {
        $this->assertSame('0 0 * * 1-5', $this->eventMock->weekdays()->daily()->getExpression());
    }

    public function testWeekdaysHourly()
    {
        $this->assertSame('0 * * * 1-5', $this->eventMock->weekdays()->hourly()->getExpression());
    }

    public function testWeekdays()
    {
        $this->assertSame('* * * * 1-5', $this->eventMock->weekdays()->getExpression());
    }

    public function testSundays()
    {
        $this->assertSame('* * * * 0', $this->eventMock->sundays()->getExpression());
    }

    public function testMondays()
    {
        $this->assertSame('* * * * 1', $this->eventMock->mondays()->getExpression());
    }

    public function testTuesdays()
    {
        $this->assertSame('* * * * 2', $this->eventMock->tuesdays()->getExpression());
    }

    public function testWednesdays()
    {
        $this->assertSame('* * * * 3', $this->eventMock->wednesdays()->getExpression());
    }

    public function testThursdays()
    {
        $this->assertSame('* * * * 4', $this->eventMock->thursdays()->getExpression());
    }

    public function testFridays()
    {
        $this->assertSame('* * * * 5', $this->eventMock->fridays()->getExpression());
    }

    public function testSaturdays()
    {
        $this->assertSame('* * * * 6', $this->eventMock->saturdays()->getExpression());
    }

    public function testQuarterly()
    {
        $this->assertSame('0 0 1 1-12/3 *', $this->eventMock->quarterly()->getExpression());
    }
}