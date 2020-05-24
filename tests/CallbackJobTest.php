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

    /**
     * @depends testTriggersBeforeRunEvent
     */
    public function testMutexPreventsOverlapping()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|Mutex $mutexMock */
        $mutexMock = $this->getMockForAbstractClass(Mutex::className(), [], '', true, true, true, ['acquire', 'release']);
        $mutexMock->expects($this->exactly(2))
            ->method('acquire')
            ->willReturnOnConsecutiveCalls(true, false);

        $job = new CallbackJob('max', [3, 5]);
        $job->description('-test-job-');
        $job->setMutex($mutexMock);
        $job->withoutOverlapping();

        // expect running
        $beforeRunHandler = function (ModelEvent $e) {
            $this->assertTrue($e->isValid);
        };
        $job->on($job::EVENT_BEFORE_RUN, $beforeRunHandler);
        $job->run();
        $job->off($job::EVENT_BEFORE_RUN, $beforeRunHandler);

        // expect skipping
        $job->on($job::EVENT_BEFORE_RUN, function (ModelEvent $e) {
            $this->assertFalse($e->isValid);
        });
        $job->run();
    }
}