<?php

namespace omnilight\scheduling\Tests;

use omnilight\scheduling\CallbackEvent;
use omnilight\scheduling\Event;
use omnilight\scheduling\Schedule;
use yii\base\Application;
use yii\mutex\Mutex;

class ScheduleTest extends \PHPUnit\Framework\TestCase
{
    private Schedule $schedule;
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $mock = $this->createMock(Application::class);
        $mock->method('has')->with('mutex')->willReturn(true);
        $mock->method('get')->with('mutex')->willReturn($this->createMock(Mutex::class));
        \Yii::$app = $mock;

        $this->schedule = new Schedule();
        $this->app = $mock;
    }

    protected function tearDown(): void
    {
        \Yii::$app = null;
        parent::tearDown();
    }

    public function testExecAddsEvent(): void
    {
        $this->schedule->exec('php -i');
        $events = $this->schedule->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(Event::class, $events[0]);
        $this->assertSame('php -i', $events[0]->command);
    }

    public function testCommandAddsPrefixedEvent(): void
    {
        $this->schedule->command('migrate');
        $events = $this->schedule->getEvents();
        $this->assertCount(1, $events);
        $this->assertSame(PHP_BINARY . ' yii migrate', $events[0]->command);
    }

    public function testCommandRespectsCustomCliScriptName(): void
    {
        $this->schedule->cliScriptName = 'artisan';
        $this->schedule->command('migrate');
        $events = $this->schedule->getEvents();
        $this->assertSame(PHP_BINARY . ' artisan migrate', $events[0]->command);
    }

    public function testCallAddsCallbackEvent(): void
    {
        $this->schedule->call(function () {});
        $events = $this->schedule->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CallbackEvent::class, $events[0]);
    }

    public function testMultipleEventsAccumulate(): void
    {
        $this->schedule->exec('cmd1');
        $this->schedule->exec('cmd2');
        $this->schedule->call(function () {});
        $this->assertCount(3, $this->schedule->getEvents());
    }

    public function testDueEventsReturnsAlwaysDueEvent(): void
    {
        $this->schedule->exec('php -i')->cron('* * * * *');
        $due = $this->schedule->dueEvents($this->app);
        $this->assertCount(1, $due);
    }

    public function testDueEventsFiltersOutSkippedEvent(): void
    {
        $this->schedule->exec('php -i')->cron('* * * * *')->skip(function () { return true; });
        $due = $this->schedule->dueEvents($this->app);
        $this->assertCount(0, $due);
    }

    public function testDueEventsReturnsMixedResults(): void
    {
        $this->schedule->exec('cmd1')->cron('* * * * *');
        $this->schedule->exec('cmd2')->cron('* * * * *')->skip(function () { return true; });
        $this->schedule->exec('cmd3')->cron('* * * * *');

        $due = array_values($this->schedule->dueEvents($this->app));
        $this->assertCount(2, $due);
        $this->assertSame('cmd1', $due[0]->command);
        $this->assertSame('cmd3', $due[1]->command);
    }
}
