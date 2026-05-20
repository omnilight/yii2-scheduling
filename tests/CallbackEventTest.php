<?php

namespace omnilight\scheduling\Tests;

use omnilight\scheduling\CallbackEvent;
use yii\base\Application;
use yii\base\InvalidArgumentException;
use yii\mutex\Mutex;

class CallbackEventTest extends \PHPUnit\Framework\TestCase
{
    private function mockMutex(): Mutex
    {
        return $this->createMock(Mutex::class);
    }

    private function mockApp(): Application
    {
        return $this->createMock(Application::class);
    }

    public function testConstructorAcceptsClosure(): void
    {
        $event = new CallbackEvent($this->mockMutex(), function () {});
        $this->assertInstanceOf(CallbackEvent::class, $event);
    }

    public function testConstructorAcceptsStringCallback(): void
    {
        $event = new CallbackEvent($this->mockMutex(), 'strlen');
        $this->assertInstanceOf(CallbackEvent::class, $event);
    }

    public function testConstructorRejectsNonCallableNonString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CallbackEvent($this->mockMutex(), new \stdClass());
    }

    public function testRunExecutesCallbackAndReturnsResult(): void
    {
        $called = false;
        $event = new CallbackEvent($this->mockMutex(), function () use (&$called) {
            $called = true;
            return 'done';
        });

        $result = $event->run($this->mockApp());

        $this->assertTrue($called);
        $this->assertSame('done', $result);
    }

    public function testRunPassesParametersAndAppToCallback(): void
    {
        $received = null;
        $event = new CallbackEvent(
            $this->mockMutex(),
            function ($a, $b, $app) use (&$received) {
                $received = [$a, $b, $app instanceof Application];
            },
            ['foo', 'bar']
        );

        $event->run($this->mockApp());

        $this->assertSame(['foo', 'bar', true], $received);
    }

    public function testRunExecutesAfterCallbacks(): void
    {
        $log = [];
        $event = new CallbackEvent($this->mockMutex(), function () use (&$log) {
            $log[] = 'main';
        });
        $event->then(function () use (&$log) {
            $log[] = 'after';
        });

        $event->run($this->mockApp());

        $this->assertSame(['main', 'after'], $log);
    }

    public function testWithoutOverlappingThrowsWithoutDescription(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $event = new CallbackEvent($this->mockMutex(), function () {});
        $event->withoutOverlapping();
    }

    public function testGetSummaryForDisplayReturnsDescription(): void
    {
        $event = new CallbackEvent($this->mockMutex(), function () {});
        $event->description('My Callback Job');
        $this->assertSame('My Callback Job', $event->getSummaryForDisplay());
    }

    public function testGetSummaryForDisplayReturnsClosure(): void
    {
        $event = new CallbackEvent($this->mockMutex(), function () {});
        $this->assertSame('Closure', $event->getSummaryForDisplay());
    }

    public function testGetSummaryForDisplayReturnsStringCallback(): void
    {
        $event = new CallbackEvent($this->mockMutex(), 'strlen');
        $this->assertSame('strlen', $event->getSummaryForDisplay());
    }
}
