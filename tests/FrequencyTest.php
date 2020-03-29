<?php

namespace omnilight\scheduling\Tests;

use omnilight\scheduling\Event;
use yii\mutex\Mutex;

class FrequencyTest extends \PHPUnit_Framework_TestCase
{
    /*
     * @var Mutex
     */
    protected $mutex;

    protected function setUp()
    {
        $this->mutex = $this->getMock(Mutex::className());
    }

    protected function getEvent()
    {
        return new Event(
            $this->mutex,
            ''
        );
    }

    public function testEveryMinute()
    {
        $this->assertSame('* * * * *', $this->getEvent()->getExpression());
        $this->assertSame('* * * * *', $this->getEvent()->everyMinute()->getExpression());
    }

    public function testEveryFiveMinutes()
    {
        $this->assertSame('*/5 * * * *', $this->getEvent()->everyFiveMinutes()->getExpression());
    }

    public function testDaily()
    {
        $this->assertSame('0 0 * * *', $this->getEvent()->daily()->getExpression());
    }

    public function testTwiceDaily()
    {
        $this->assertSame('0 3,15 * * *', $this->getEvent()->twiceDaily(3, 15)->getExpression());
    }

    public function testOverrideWithHourly()
    {
        $this->assertSame('0 * * * *', $this->getEvent()->everyFiveMinutes()->hourly()->getExpression());
        $this->assertSame('37 * * * *', $this->getEvent()->hourlyAt(37)->getExpression());
        $this->assertSame('15,30,45 * * * *', $this->getEvent()->hourlyAt([15, 30, 45])->getExpression());
    }

    public function testMonthlyOn()
    {
        $this->assertSame('0 15 4 * *', $this->getEvent()->monthlyOn(4, '15:00')->getExpression());
    }

    public function testTwiceMonthly()
    {
        $this->assertSame('0 0 1,16 * *', $this->getEvent()->twiceMonthly(1, 16)->getExpression());
    }

    public function testMonthlyOnWithMinutes()
    {
        $this->assertSame('15 15 4 * *', $this->getEvent()->monthlyOn(4, '15:15')->getExpression());
    }

    public function testWeekdaysDaily()
    {
        $this->assertSame('0 0 * * 1-5', $this->getEvent()->weekdays()->daily()->getExpression());
    }

    public function testWeekdaysHourly()
    {
        $this->assertSame('0 * * * 1-5', $this->getEvent()->weekdays()->hourly()->getExpression());
    }

    public function testWeekdays()
    {
        $this->assertSame('* * * * 1-5', $this->getEvent()->weekdays()->getExpression());
    }

    public function testSundays()
    {
        $this->assertSame('* * * * 0', $this->getEvent()->sundays()->getExpression());
    }

    public function testMondays()
    {
        $this->assertSame('* * * * 1', $this->getEvent()->mondays()->getExpression());
    }

    public function testTuesdays()
    {
        $this->assertSame('* * * * 2', $this->getEvent()->tuesdays()->getExpression());
    }

    public function testWednesdays()
    {
        $this->assertSame('* * * * 3', $this->getEvent()->wednesdays()->getExpression());
    }

    public function testThursdays()
    {
        $this->assertSame('* * * * 4', $this->getEvent()->thursdays()->getExpression());
    }

    public function testFridays()
    {
        $this->assertSame('* * * * 5', $this->getEvent()->fridays()->getExpression());
    }

    public function testSaturdays()
    {
        $this->assertSame('* * * * 6', $this->getEvent()->saturdays()->getExpression());
    }

    public function testQuarterly()
    {
        $this->assertSame('0 0 1 1-12/3 *', $this->getEvent()->quarterly()->getExpression());
    }
}
