<?php

namespace lexeo\yii2scheduling;

use Symfony\Component\Process\Process;
use yii\base\Application;
use yii\base\InvalidCallException;
use yii\mail\MailerInterface;
use yii\mutex\Mutex;

/**
 * Class Event
 *
 * @property-read string $command
 */
class Event extends AbstractEvent
{
    /**
     * Command string
     * @var string
     */
    protected $command;

    /**
     * The user the command should run as.
     *
     * @var string
     */
    protected $user;
    /**
     * The location that output should be sent to.
     *
     * @var string
     */
    protected $output;
    /**
     * The string for redirection.
     *
     * @var array
     */
    protected $redirect = ' > ';

    /**
     * Create a new event instance.
     *
     * @param Mutex $mutex
     * @param string $command
     * @param array $config
     */
    public function __construct(Mutex $mutex, $command, $config = [])
    {
        $this->command = $command;
        $this->mutex = $mutex;
        $this->output = $this->getDefaultOutput();
        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function run(Application $app)
    {
        $this->trigger(self::EVENT_BEFORE_RUN);
        if (count($this->afterCallbacks) > 0) {
            $this->runCommandInForeground($app);
        } else {
            $this->runCommandInBackground($app);
        }
        $this->trigger(self::EVENT_AFTER_RUN);
    }

    /**
     * @inheritDoc
     */
    protected function mutexName()
    {
        return 'framework/schedule-' . sha1($this->expression . $this->command);
    }

    /**
     * Run the command in the foreground.
     *
     * @param Application $app
     */
    protected function runCommandInForeground(Application $app)
    {
        (new Process(
            trim($this->buildCommand(), '& '),
            dirname($app->request->getScriptFile()),
            null,
            null,
            null
        ))->run();
        $this->callAfterCallbacks($app);
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    public function buildCommand()
    {
        $command = $this->command . $this->redirect . $this->output . (($this->omitErrors) ? ' 2>&1 &' : '');
        return $this->user ? 'sudo -u ' . $this->user . ' ' . $command : $command;
    }

    /**
     * Run the command in the background using exec.
     *
     * @param Application $app
     */
    protected function runCommandInBackground(Application $app)
    {
        chdir(dirname($app->request->getScriptFile()));
        exec($this->buildCommand());
    }

    /**
     * Get the command string.
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Set which user the command should run as.
     *
     * @param string $user
     * @return $this
     */
    public function user($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Send the output of the command to a given location.
     *
     * @param string $location
     * @return $this
     */
    public function sendOutputTo($location)
    {
        $this->redirect = ' > ';
        $this->output = $location;
        return $this;
    }

    /**
     * Append the output of the command to a given location.
     *
     * @param string $location
     * @return $this
     */
    public function appendOutputTo($location)
    {
        $this->redirect = ' >> ';
        $this->output = $location;
        return $this;
    }

    /**
     * E-mail the results of the scheduled operation.
     *
     * @param array $addresses
     * @return $this
     *
     * @throws InvalidCallException
     */
    public function emailOutputTo($addresses)
    {
        if (is_null($this->output) || $this->output === $this->getDefaultOutput()) {
            throw new InvalidCallException("Must direct output to a file in order to e-mail results.");
        }
        $addresses = is_array($addresses) ? $addresses : func_get_args();
        return $this->then(function (Application $app) use ($addresses) {
            $this->emailOutput($app->mailer, $addresses);
        });
    }

    /**
     * E-mail the output of the event to the recipients.
     *
     * @param MailerInterface $mailer
     * @param array $addresses
     */
    protected function emailOutput(MailerInterface $mailer, $addresses)
    {
        $textBody = file_get_contents($this->output);

        if (trim($textBody) !== '') {
            $mailer->compose()
                ->setTextBody($textBody)
                ->setSubject($this->getEmailSubject())
                ->setTo($addresses)
                ->send();
        }
    }

    /**
     * Get the e-mail subject line for output results.
     *
     * @return string
     */
    protected function getEmailSubject()
    {
        if ($this->description) {
            return 'Scheduled Job Output (' . $this->description . ')';
        }
        return 'Scheduled Job Output';
    }

    /**
     * @inheritDoc
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }
        return $this->buildCommand();
    }

    /**
     * Get the default output depending on the OS.
     *
     * @return string
     */
    public function getDefaultOutput()
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            return 'NUL';
        }
        return '/dev/null';
    }
}
