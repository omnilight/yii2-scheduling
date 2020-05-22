<?php

namespace lexeo\yii2scheduling;

use InvalidArgumentException;
use yii\base\InvalidConfigException;

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
    public $result;

    /**
     * Create a new event instance.
     *
     * @param callable $callback
     * @param array $parameters
     * @param array $config
     * @throws InvalidArgumentException
     */
    public function __construct($callback, array $parameters = [], $config = [])
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Invalid scheduled callback event. Must be callable.');
        }
        $this->callback = $callback;
        $this->parameters = $parameters;
        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        if (!$this->beforeRun()) {
            return;
        }
        $this->result = call_user_func_array($this->callback, $this->parameters);
        $this->afterComplete();
    }

    /**
     * @inheritDoc
     * @throws InvalidConfigException
     */
    public function mutexName()
    {
        if (!$this->description) {
            throw new InvalidConfigException(
                "A scheduled event name is required to prevent overlapping. Use the 'description' method before 'withoutOverlapping'."
            );
        }
        return 'framework/schedule-' . sha1($this->description);
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
