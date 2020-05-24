<?php

namespace lexeo\yii2scheduling;

use InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Class CallbackJob
 * @property-read mixed $result
 */
class CallbackJob extends AbstractJob
{
    /**
     * The callback to call.
     *
     * @var callable
     */
    protected $callback;
    /**
     * The parameters to pass to the method.
     *
     * @var array
     */
    protected $parameters;
    /**
     * The result of running given callback
     *
     * @var mixed
     */
    protected $result;

    /**
     * Create a new job instance.
     *
     * @param callable $callback
     * @param array $parameters
     * @throws InvalidArgumentException
     */
    public function __construct($callback, array $parameters = [])
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Invalid scheduled callback job. Must be callable.');
        }
        $this->callback = $callback;
        $this->parameters = $parameters;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        if (!$this->beforeRun()) {
            return;
        }
        try {
            $this->result = call_user_func_array($this->callback, $this->parameters);
        } catch (\Exception $e) {
        }
        $this->afterComplete();
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        $serialized = $this->callback instanceof \Closure
            ? serialize([(string) (new \ReflectionFunction($this->callback)), $this->parameters])
            : serialize([(array) $this->callback, $this->parameters]);

        return 'framework/schedule-' . sha1($this->expression . $serialized);
    }

    /**
     * @inheritDoc
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }
        return is_string($this->callback) ? $this->callback : 'Closure';
    }
}
