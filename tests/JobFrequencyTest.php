<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\AbstractJob;

class JobFrequencyTest extends AbstractTestCase
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
        $this->jobMock = $this->getMockForAbstractClass('lexeo\yii2scheduling\AbstractJob');
    }


    public function testEveryMinute()
    {
        $this->assertSame('* * * * *', $this->jobMock->getExpression());
        $this->assertSame('* * * * *', $this->jobMock->everyMinute()->getExpression());
    }

    public function testEveryNMinutes()
    {
        $this->assertSame('* * * * *', $this->jobMock->getExpression());
        $this->assertSame('*/6 * * * *', $this->jobMock->everyNMinutes(6)->getExpression());
        $this->assertSame('*/15 * * * *', $this->jobMock->everyNMinutes(15)->getExpression());
    }

    public function testEveryFiveMinutes()
    {
        $this->assertSame('*/5 * * * *', $this->jobMock->everyFiveMinutes()->getExpression());
    }

    public function testDaily()
    {
        $this->assertSame('0 0 * * *', $this->jobMock->daily()->getExpression());
    }

    public function testTwiceDaily()
    {
        $this->assertSame('0 3,15 * * *', $this->jobMock->twiceDaily(3, 15)->getExpression());
    }

    public function testOverrideWithHourly()
    {
        $this->assertSame('0 * * * *', $this->jobMock->everyFiveMinutes()->hourly()->getExpression());
        $this->assertSame('37 * * * *', $this->jobMock->hourlyAt(37)->getExpression());
        $this->assertSame('15,30,45 * * * *', $this->jobMock->hourlyAt([15, 30, 45])->getExpression());
    }

    public function testMonthlyOn()
    {
        $this->assertSame('0 15 4 * *', $this->jobMock->monthlyOn(4, '15:00')->getExpression());
    }

    public function testTwiceMonthly()
    {
        $this->assertSame('0 0 1,16 * *', $this->jobMock->twiceMonthly(1, 16)->getExpression());
    }

    public function testMonthlyOnWithMinutes()
    {
        $this->assertSame('15 15 4 * *', $this->jobMock->monthlyOn(4, '15:15')->getExpression());
    }

    public function testWeekdaysDaily()
    {
        $this->assertSame('0 0 * * 1-5', $this->jobMock->weekdays()->daily()->getExpression());
    }

    public function testWeekdaysHourly()
    {
        $this->assertSame('0 * * * 1-5', $this->jobMock->weekdays()->hourly()->getExpression());
    }

    public function testWeekdays()
    {
        $this->assertSame('* * * * 1-5', $this->jobMock->weekdays()->getExpression());
    }

    public function testSundays()
    {
        $this->assertSame('* * * * 0', $this->jobMock->sundays()->getExpression());
    }

    public function testMondays()
    {
        $this->assertSame('* * * * 1', $this->jobMock->mondays()->getExpression());
    }

    public function testTuesdays()
    {
        $this->assertSame('* * * * 2', $this->jobMock->tuesdays()->getExpression());
    }

    public function testWednesdays()
    {
        $this->assertSame('* * * * 3', $this->jobMock->wednesdays()->getExpression());
    }

    public function testThursdays()
    {
        $this->assertSame('* * * * 4', $this->jobMock->thursdays()->getExpression());
    }

    public function testFridays()
    {
        $this->assertSame('* * * * 5', $this->jobMock->fridays()->getExpression());
    }

    public function testSaturdays()
    {
        $this->assertSame('* * * * 6', $this->jobMock->saturdays()->getExpression());
    }

    public function testQuarterly()
    {
        $this->assertSame('0 0 1 1-12/3 *', $this->jobMock->quarterly()->getExpression());
    }
}