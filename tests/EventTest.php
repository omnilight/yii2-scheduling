<?php

namespace omnilight\scheduling\Tests;

use omnilight\scheduling\Event;
use yii\mutex\Mutex;

class EventTest extends \PHPUnit_Framework_TestCase
{
    public function buildCommandData()
    {
        return [
            ['php -i', '/dev/null', "php -i > /dev/null 2>&1 &"],
            ['php -i', '/my folder/foo.log', "php -i > /my folder/foo.log 2>&1 &"],
        ];
    }

    /**
     * @dataProvider buildCommandData
     * @param $command
     * @param $outputTo
     * @param $result
     */
    public function testBuildCommandSendOutputTo($command, $outputTo, $result)
    {
        $event = new Event($this->getMock(Mutex::className()), $command);
        $event->sendOutputTo($outputTo);
        $this->assertSame($result, $event->buildCommand());
    }

    public function testExpression()
    {
        $event = new Event($this->getMock(Mutex::className()), 'test');
        $this->assertSame('0 * * * * *', $event->hourly()->getExpression());
        $this->assertSame('13 * * * * *', $event->everyMinute()->hourlyAt(13)->getExpression());
        $this->assertSame('0 5 0 1 *', $event->everyMinute()->cron('0 5 0 1 *')->getExpression());
        $this->assertSame('0 0 * * * *', $event->everyMinute()->daily()->getExpression());
        $this->assertSame('0 13 * * 1 *', $event->everyMinute()->weekly()->mondays()->at('13:00')->getExpression());
        $this->assertSame('0 4 * * * *', $event->everyMinute()->dailyAt('04:00')->getExpression());
        $this->assertSame('0 1,13 * * * *', $event->everyMinute()->twiceDaily()->getExpression());
        $this->assertSame('* * * * 1-5 *', $event->everyMinute()->weekdays()->getExpression());
        $this->assertSame('* * * * 0,6 *', $event->everyMinute()->weekends()->getExpression());
        $this->assertSame('* * * * 1 *', $event->everyMinute()->mondays()->getExpression());
        $this->assertSame('* * * * 2 *', $event->everyMinute()->days(2)->getExpression());
        $this->assertSame('* * * * 2 *', $event->everyMinute()->tuesdays()->getExpression());
        $this->assertSame('* * * * 3 *', $event->everyMinute()->wednesdays()->getExpression());
        $this->assertSame('* * * * 4 *', $event->everyMinute()->thursdays()->getExpression());
        $this->assertSame('* * * * 5 *', $event->everyMinute()->fridays()->getExpression());
        $this->assertSame('* * * * 6 *', $event->everyMinute()->saturdays()->getExpression());
        $this->assertSame('* * * * 0 *', $event->everyMinute()->sundays()->getExpression());
        $this->assertSame('0 0 * * 0 *', $event->everyMinute()->weekly()->getExpression());
        $this->assertSame('0 9 * * 3 *', $event->everyMinute()->weeklyOn(3, '9:00')->getExpression());
        $this->assertSame('0 8 2 * * *', $event->everyMinute()->monthlyOn(2, '8:00')->getExpression());
        $this->assertSame('0 0 2,8 * * *', $event->everyMinute()->twiceMonthly(2, 8)->getExpression());
        $this->assertSame('0 0 1 1-12/3 * *', $event->everyMinute()->quarterly()->getExpression());
        $this->assertSame('0 0 1 1 * *', $event->everyMinute()->yearly()->getExpression());
        $this->assertSame('* * * * * *', $event->everyMinute()->getExpression());
        $this->assertSame('*/6 * * * * *', $event->everyNMinutes(6)->getExpression());
        $this->assertSame('*/5 * * * * *', $event->everyFiveMinutes()->getExpression());
        $this->assertSame('*/10 * * * * *', $event->everyTenMinutes()->getExpression());
        $this->assertSame('*/15 * * * * *', $event->everyFifteenMinutes()->getExpression());
        $this->assertSame('0,30 * * * * *', $event->everyThirtyMinutes()->getExpression());
    }
}