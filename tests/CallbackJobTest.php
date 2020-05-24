<?php

namespace lexeo\yii2scheduling\tests;

use lexeo\yii2scheduling\CallbackJob;
use yii\base\Event;
use yii\base\ModelEvent;
use yii\mutex\Mutex;

class CallbackJobTest extends AbstractTestCase
{
    public function testFailsIfNotCallable()
    {
        $this->setExpectedException('InvalidArgumentException');
        new CallbackJob('123');
    }

    public function testRunsWithParams()
    {
        $expectedParams = [1, 2, 3];
        $expectedResult = 999;
        $fail = true;
        $job = new CallbackJob(function () use (&$fail, $expectedParams, $expectedResult) {
            $this->assertSame($expectedParams, func_get_args());
            $fail = false;
            return $expectedResult;
        }, $expectedParams);

        $job->run();
        $fail && $this->fail('Callback was not called.');
        $this->assertSame($job->getResult(), $expectedResult);

        // from string
        $job = new CallbackJob('max', [1, 9]);
        $job->run();
        $this->assertSame(9, $job->getResult());
    }

    /**
     * @depends testRunsWithParams
     */
    public function testCatchesException()
    {
        $job = new CallbackJob(static function () {
            throw new \LogicException('Fail!');
        });
        $job->run();
        $this->assertNull($job->getResult());
    }

    public function testTriggersBeforeRunEvent()
    {
        $job = new CallbackJob(static function () {}, []);

        $fail = true;
        $beforeRunHandler = function (ModelEvent $e) use (&$fail) {
            $this->assertInstanceOf(CallbackJob::className(), $e->sender);
            $this->assertTrue($e->isValid);
            $fail = false;
        };

        $job->on($job::EVENT_BEFORE_RUN, $beforeRunHandler);
        $job->run();
        $fail && $this->fail('On beforeRun handler was not called.');
    }

    public function testTriggersAfterCompleteEvent()
    {
        $job = new CallbackJob(static function () {}, []);

        $fail = true;
        $afterCompleteHandler = function (Event $e) use (&$fail) {
            $this->assertInstanceOf(CallbackJob::className(), $e->sender);
            $fail = false;
        };

        $job->on($job::EVENT_AFTER_COMPLETE, $afterCompleteHandler);
        $job->run();
        $fail && $this->fail('On afterComplete handler was not called.');
    }

    public function testProvidesCorrectMutexName()
    {
        // callable string
        $job1 = new CallbackJob('max', [1, 9]);
        $job2 = new CallbackJob('max', [1, 9]);
        $job3 = new CallbackJob('max', [1, 9, 99]);
        $this->assertEquals($job1->mutexName(), $job2->mutexName());
        $this->assertNotEquals($job1->mutexName(), $job3->mutexName());
        $this->assertNotEquals($job1->everyMinute()->mutexName(), $job2->daily()->mutexName());

        // callable array
        $job1 = new CallbackJob(['\PHPUnit\Framework\TestCase', 'assertTrue'], [true]);
        $job2 = new CallbackJob(['\PHPUnit\Framework\TestCase', 'assertTrue'], [true]);
        $job3 = new CallbackJob(['\PHPUnit\Framework\TestCase', 'assertTrue'], [false]);
        $this->assertEquals($job1->mutexName(), $job2->mutexName());
        $this->assertNotEquals($job1->mutexName(), $job3->mutexName());
        $this->assertNotEquals($job1->everyMinute()->mutexName(), $job2->daily()->mutexName());

        // callable Closure
        $closure = static function($a, $b) { return $a + $b; };
        $job1 = new CallbackJob($closure, [1, 2]);
        $job2 = new CallbackJob($closure, [1, 2]);
        $job3 = new CallbackJob($closure, [5, 7]);
        $this->assertEquals($job1->mutexName(), $job2->mutexName());
        $this->assertNotEquals($job1->mutexName(), $job3->mutexName());
        $this->assertNotEquals($job1->everyMinute()->mutexName(), $job2->daily()->mutexName());
    }
}