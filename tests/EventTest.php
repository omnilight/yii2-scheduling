<?php

namespace omnilight\scheduling\Tests;

use omnilight\scheduling\Event;
use yii\base\Application;
use yii\mutex\Mutex;

class EventTest extends \PHPUnit\Framework\TestCase
{
    private function makeEvent(string $command = 'php -i'): Event
    {
        return new Event($this->createMock(Mutex::class), $command);
    }

    private function mockApp(): Application
    {
        return $this->createMock(Application::class);
    }

    public static function buildCommandData(): array
    {
        return [
            [false, 'php -i', '/dev/null', 'php -i > /dev/null &'],
            [false, 'php -i', '/my folder/foo.log', 'php -i > /my folder/foo.log &'],
            [true, 'php -i', '/dev/null', 'php -i > /dev/null 2>&1 &'],
            [true, 'php -i', '/my folder/foo.log', 'php -i > /my folder/foo.log 2>&1 &'],
        ];
    }

    /**
     * @dataProvider buildCommandData
     */
    public function testBuildCommandSendOutputTo(bool $omitErrors, string $command, string $outputTo, string $result): void
    {
        $event = $this->makeEvent($command);
        $event->omitErrors($omitErrors);
        $event->sendOutputTo($outputTo);
        $this->assertSame($result, $event->buildCommand());
    }

    public function testBuildCommandAppendOutputTo(): void
    {
        $event = $this->makeEvent();
        $event->appendOutputTo('/var/log/app.log');
        $this->assertSame('php -i >> /var/log/app.log &', $event->buildCommand());
    }

    public function testBuildCommandWithUser(): void
    {
        $event = $this->makeEvent();
        $event->user('www-data')->sendOutputTo('/dev/null');
        $this->assertSame('sudo -u www-data php -i > /dev/null &', $event->buildCommand());
    }

    public function testDefaultExpression(): void
    {
        $this->assertSame('* * * * *', $this->makeEvent()->getExpression());
    }

    public function testCronSetsExpression(): void
    {
        $event = $this->makeEvent();
        $event->cron('5 4 * * *');
        $this->assertSame('5 4 * * *', $event->getExpression());
    }

    public static function cronHelperData(): array
    {
        return [
            ['hourly', '0 * * * *'],
            ['daily', '0 0 * * *'],
            ['weekly', '0 0 * * 0'],
            ['monthly', '0 0 1 * *'],
            ['yearly', '0 0 1 1 *'],
            ['everyMinute', '* * * * *'],
            ['everyFiveMinutes', '*/5 * * * *'],
            ['everyTenMinutes', '*/10 * * * *'],
            ['everyThirtyMinutes', '0,30 * * * *'],
            ['twiceDaily', '0 1,13 * * *'],
            ['weekdays', '* * * * 1-5'],
            ['mondays', '* * * * 1'],
            ['tuesdays', '* * * * 2'],
            ['wednesdays', '* * * * 3'],
            ['thursdays', '* * * * 4'],
            ['fridays', '* * * * 5'],
            ['saturdays', '* * * * 6'],
            ['sundays', '* * * * 0'],
        ];
    }

    /**
     * @dataProvider cronHelperData
     */
    public function testCronHelpers(string $method, string $expected): void
    {
        $event = $this->makeEvent();
        $event->$method();
        $this->assertSame($expected, $event->getExpression());
    }

    public function testDailyAt(): void
    {
        $event = $this->makeEvent();
        $event->dailyAt('14:30');
        $this->assertSame('30 14 * * *', $event->getExpression());
    }

    public function testAt(): void
    {
        $event = $this->makeEvent();
        $event->at('9:00');
        $this->assertSame('0 9 * * *', $event->getExpression());
    }

    public function testEveryNMinutes(): void
    {
        $event = $this->makeEvent();
        $event->everyNMinutes(15);
        $this->assertSame('*/15 * * * *', $event->getExpression());
    }

    public function testWeeklyOn(): void
    {
        $event = $this->makeEvent();
        $event->weeklyOn(3, '10:30');
        $this->assertSame('30 10 * * 3', $event->getExpression());
    }

    public function testDaysArray(): void
    {
        $event = $this->makeEvent();
        $event->days([1, 3, 5]);
        $this->assertSame('* * * * 1,3,5', $event->getExpression());
    }

    public function testTimezoneAsStringDoesNotThrow(): void
    {
        $event = $this->makeEvent();
        $event->timezone('America/New_York');
        $event->isDue($this->mockApp());
        $this->addToAssertionCount(1);
    }

    public function testTimezoneAsObjectDoesNotThrow(): void
    {
        $event = $this->makeEvent();
        $event->timezone(new \DateTimeZone('America/New_York'));
        $event->isDue($this->mockApp());
        $this->addToAssertionCount(1);
    }

    public function testIsDueWithAlwaysTrueExpression(): void
    {
        $this->assertTrue($this->makeEvent()->isDue($this->mockApp()));
    }

    public function testIsDueReturnsFalseWhenWhenFilterFails(): void
    {
        $event = $this->makeEvent();
        $event->when(function () { return false; });
        $this->assertFalse($event->isDue($this->mockApp()));
    }

    public function testIsDueReturnsTrueWhenWhenFilterPasses(): void
    {
        $event = $this->makeEvent();
        $event->when(function () { return true; });
        $this->assertTrue($event->isDue($this->mockApp()));
    }

    public function testIsDueReturnsFalseWhenSkipReturnsTrue(): void
    {
        $event = $this->makeEvent();
        $event->skip(function () { return true; });
        $this->assertFalse($event->isDue($this->mockApp()));
    }

    public function testIsDueReturnsTrueWhenSkipReturnsFalse(): void
    {
        $event = $this->makeEvent();
        $event->skip(function () { return false; });
        $this->assertTrue($event->isDue($this->mockApp()));
    }

    public function testGetSummaryForDisplayWithDescription(): void
    {
        $event = $this->makeEvent();
        $event->description('My Job');
        $this->assertSame('My Job', $event->getSummaryForDisplay());
    }

    public function testGetSummaryForDisplayFallsBackToCommand(): void
    {
        $event = $this->makeEvent();
        $event->sendOutputTo('/dev/null');
        $this->assertSame('php -i > /dev/null &', $event->getSummaryForDisplay());
    }
}
