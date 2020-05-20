<?php

namespace lexeo\yii2scheduling;

use Symfony\Component\Process\Process;
use yii\base\InvalidCallException;
use yii\mail\MailerInterface;
use Yii;

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
     * Current working directory.
     *
     * @var
     */
    protected $cwd;
    /**
     * The location that output should be sent to.
     *
     * @var string|null
     */
    protected $output;
    /**
     * Indicates whether output should be appended.
     *
     * @var bool
     */
    protected $shouldAppendOutput = false;

    /**
     * Create a new event instance.
     *
     * @param string $command
     * @param array $config
     */
    public function __construct($command, $config = [])
    {
        $this->command = $command;
        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        $this->trigger(self::EVENT_BEFORE_RUN);
        if (count($this->afterCallbacks) > 0) {
            $this->runCommandInForeground();
        } else {
            $this->runCommandInBackground();
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
     */
    protected function runCommandInForeground()
    {
        $process = new Process($this->buildCommand(), $this->cwd);
        $process->setTimeout(0);

        $process->run();
        $this->callAfterCallbacks();
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    public function buildCommand()
    {
        $command = trim($this->command, '& ')
            . ($this->shouldAppendOutput ? ' >> ' : ' > ') . ($this->output ?: $this->getDefaultOutput());
        if ($this->omitErrors) {
            $command .= ' 2>&1';
        }
        return $this->ensureCorrectUser($this->ensureCorrectDirectory($command));
    }

    /**
     * Finalize the command syntax with the correct directory.
     *
     * @param string $command
     * @return string
     */
    protected function ensureCorrectDirectory($command)
    {
        if (!$this->cwd) {
            return $command;
        }
        // Support changing drives in Windows
        $cdParameter = $this->isWindows() ? '/d ' : '';
        $andSign = $this->isWindows() ? ' &' : ';';

        return "cd {$cdParameter}{$this->cwd}{$andSign} {$command}";
    }

    /**
     * Finalize the command syntax with the correct user.
     *
     * @param string $command
     * @return string
     */
    protected function ensureCorrectUser($command)
    {
        if (!$this->user || $this->isWindows()) {
            return $command;
        }
        return sprintf("sudo -u %s -- sh -c '%s'", $this->user, $command);
    }

    /**
     * Run the command in the background using exec.
     */
    protected function runCommandInBackground()
    {
        $this->cwd && chdir($this->cwd);
        //FIXME https://www.php.net/manual/en/function.exec#refsect1-function.exec-notes
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
     * Change the current working directory.
     *
     * @param $directory
     * @return $this
     */
    public function in($directory)
    {
        $this->cwd  = $directory;
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
        $this->shouldAppendOutput = false;
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
        $this->shouldAppendOutput = true;
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
        return $this->then(function () use ($addresses) {
            $this->emailOutput(Yii::$app->mailer, $addresses);
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
        return $this->isWindows() ? 'NUL' : '/dev/null';
    }

    /**
     * @return bool
     */
    protected function isWindows()
    {
        return 0 === stripos(PHP_OS, 'WIN');
    }
}
