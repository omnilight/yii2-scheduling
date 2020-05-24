<?php

namespace lexeo\yii2scheduling;

use Symfony\Component\Process\Process;
use yii\base\InvalidCallException;
use yii\mail\MailerInterface;
use Yii;

/**
 * Class ShellJob
 *
 * @property-read string $command
 * @property-read int|null $exitCode
 * @property-read bool $runInBackground
 */
class ShellJob extends AbstractJob
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
     * Indicates if the command should run in background.
     *
     * @var bool
     */
    protected $runInBackground = false;
    /**
     * The exit status code of the command.
     *
     * @var int|null
     */
    protected $exitCode;

    /**
     * Create a new job instance.
     *
     * @param string $command
     */
    public function __construct($command)
    {
        $this->command = $command;
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

        $this->runInBackground
            ? $this->runCommandInBackground()
            : $this->runCommandInForeground();
    }

    /**
     * Call all of the "after" callbacks for the job.
     *
     * @param int $exitCode
     * @return void
     */
    public function finish($exitCode)
    {
        $this->exitCode = (int) $exitCode;
        $this->afterComplete();
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return 'framework/schedule-' . sha1($this->expression . $this->command);
    }

    /**
     * Run the command in the foreground.
     */
    protected function runCommandInForeground()
    {
        $this->exitCode = $this->createProcess($this->buildCommand(), $this->cwd)->run();
        $this->afterComplete();
    }


    /**
     * Run the command in the background.
     */
    protected function runCommandInBackground()
    {
        $this->createProcess($this->buildCommand(), $this->cwd)->run();
    }

    /**
     * @param string $command
     * @param string|null $cwd
     * @param int|float|null $timeout
     * @return Process
     */
    protected function createProcess($command, $cwd = null, $timeout = null)
    {
        if (method_exists('Symfony\Component\Process\Process', 'fromShellCommandline')) {
            return Process::fromShellCommandline($command, $cwd, null, null, $timeout);
        }
        return new Process($command, $cwd, null, null, $timeout);
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    public function buildCommand()
    {
        $command = trim($this->command, '& ');
        $redirectOutput = ($this->shouldAppendOutput ? '>>' : '>') . ' ' . ($this->output ?: $this->getDefaultOutput());
        if ($this->omitErrors) {
            $redirectOutput .= ' 2>&1';
        }
        if (!$this->runInBackground) {
            return $this->ensureCorrectUser("{$command} {$redirectOutput}");
        }

        $callbackCmd = strtr('{php} {yii} {controller}/finish', [
            '{php}' => PHP_BINARY,
            '{yii}' => Yii::$app->request->scriptFile,
            '{controller}' => Yii::$app->controller->id,
        ]);
        if ($this->isWindows()) {
            $callback = strtr('{cmd} "{id}" "{exitCode}"', [
                '{cmd}' => $callbackCmd,
                '{id}' => $this->getId(),
                '{exitCode}' => '%errorlevel%',
            ]);
            return "start /b cmd /c \"({$command} & {$callback}) {$redirectOutput}\"";
        }

        $callback = strtr('{cmd} "{id}" "{exitCode}"', [
            '{cmd}' => $callbackCmd,
            '{id}' => $this->getId(),
            '{exitCode}' => '$?',
        ]);
        return $this->ensureCorrectUser(
            "({$command} {$redirectOutput} ; {$callback}) > {$this->getDefaultOutput()} 2>&1 &"
        );
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
     * Get the command string.
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @return int|null
     */
    public function getExitCode()
    {
        return $this->exitCode;
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
     * Set the initial working directory for the command.
     * This must be an absolute directory path, or NULL if you want to use the working dir of the current PHP process
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
     * State that the command should run in background or not.
     *
     * @param bool $inBackground
     * @return $this
     */
    public function runInBackground($inBackground = true)
    {
        $this->runInBackground = $inBackground;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRunInBackground()
    {
        return $this->runInBackground;
    }

    /**
     * Send the output of the command to a given location.
     *
     * @param string $location
     * @param bool $append
     * @return $this
     */
    public function sendOutputTo($location, $append = false)
    {
        $this->shouldAppendOutput = $append;
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
     * E-mail the output of the job to the recipients.
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
