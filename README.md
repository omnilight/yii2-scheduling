Job scheduling extension for Yii2
=================================

This extension inspired by Laravel's [Console\Scheduling](https://laravel.com/docs/master/scheduling) component
and based on the [omnilight/yii2-scheduling](https://github.com/omnilight/yii2-scheduling)
but intended to fix a number of found bugs in the last and provide a really working solution.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require lexeo/yii2-scheduling "*"
```

or add

```json
"lexeo/yii2-scheduling": "*"
```

to the `require` section of your composer.json.

Description
-----------

This project inspired by the Laravel's Scheduling component and tries to bring its simplicity to the Yii framework.
Quote from Laravel's documentation:

```
In the past, developers have generated a Cron entry for each console command they wished to schedule.
However, this is a headache. Your console schedule is no longer in source control,
and you must SSH into your server to add the Cron entries. Let's make our lives easier.
```

After installation all you have to do is to put single line into crontab:

```
* * * * * php /path/to/yii yii schedule/run --scheduleFile=@path/to/schedule.php 1>> /dev/null 2>&1
```

You can put your schedule into the `schedule.php` file, or add it withing bootstrapping of your extension or
application

Schedule examples
-----------------

This extension supports most of the features of Laravel's Scheduling, except environments and maintenance mode.

**Scheduling Closures**

```php
$schedule->call(function() {
    // Do some task...
})->hourly();
```

**Scheduling Terminal Commands**

```php
$schedule->exec('composer self-update')->daily();
```

**Running command of your application**

```php
$schedule->command('migrate')->cron('* * * * *');
```

**Frequent Jobs**

```php
$schedule->command('foo')->everyFiveMinutes();

$schedule->command('foo')->everyTenMinutes();

$schedule->command('foo')->everyThirtyMinutes();
```

**Daily Jobs**

```php
$schedule->command('foo')->daily();
```

**Daily Jobs At A Specific Time (24 Hour Time)**

```php
$schedule->command('foo')->dailyAt('15:00');
```

**Twice Daily Jobs**

```php
$schedule->command('foo')->twiceDaily();
```

**Job That Runs Every Weekday**

```php
$schedule->command('foo')->weekdays();
```

**Weekly Jobs**

```php
$schedule->command('foo')->weekly();

// Schedule weekly job for specific day (0-6) and time...
$schedule->command('foo')->weeklyOn(1, '8:00');
```

**Monthly Jobs**

```php
$schedule->command('foo')->monthly();
```

**Job That Runs On Specific Days**

```php
$schedule->command('foo')->mondays();
$schedule->command('foo')->tuesdays();
$schedule->command('foo')->wednesdays();
$schedule->command('foo')->thursdays();
$schedule->command('foo')->fridays();
$schedule->command('foo')->saturdays();
$schedule->command('foo')->sundays();
```

**Only Allow Job To Run When Callback Is True**

```php
$schedule->command('foo')->monthly()->when(function() {
    return true;
});
```

**E-mail The Output Of A Scheduled Job**

```php
$schedule->command('foo')->sendOutputTo($filePath)->emailOutputTo('foo@example.com');
```

**Preventing Task Overlaps**

```php
$schedule->command('foo')->withoutOverlapping();
```
Used by default [yii\mutex\FileMutex](https://www.yiiframework.com/doc/api/2.0/yii-mutex-filemutex) or 'mutex' application component if defined.

**Running Tasks On One Server**

>To utilize this feature, you must config mutex in the application component, except the FileMutex:  `yii\mutex\MysqlMutex`,`yii\mutex\PgsqlMutex`,`yii\mutex\OracleMutex` or `yii\redis\Mutex`. In addition, all servers must be communicating with the same central db/cache server.

Below shows the redis mutex demo:

```php
'components' => [
    'mutex' => [
        'class' => 'yii\redis\Mutex',
        'redis' => [
            'hostname' => 'localhost',
            'port' => 6379,
            'database' => 0,
        ]
    ],
],
```

```php
$schedule->command('report:generate')
                ->fridays()
                ->at('17:00')
                ->onOneServer();
```

How to use this extension in your application?
----------------------------------------------

You should create the following file under `@console/config/schedule.php` (note: you can create a file with any name
and extension and anywhere on your server, simply adjust the name of the scheduleFile in the command below):

```php
<?php
/**
 * @var \lexeo\yii2scheduling\Schedule $schedule
 */

// Place here all of your cron jobs

// This command will execute ls command every five minutes
$schedule->exec('ls')->everyFiveMinutes();

// This command will execute migration command of your application every hour
$schedule->command('migrate')->hourly();

// This command will call callback function every day at 10:00
$schedule->call(function() {
    // Some code here...
})->dailyAt('10:00');

```

Next you should add the following command to your crontab:
```
* * * * * php /path/to/yii yii schedule/run --scheduleFile=@console/config/schedule.php 1>> /dev/null 2>&1
```

That's all! Now all your cron jobs will be run as configured in your schedule.php file.

How to use this extension in your own extension?
------------------------------------------------

First of all, you should include dependency to the `lexeo\yii2-scheduling` into your composer.json:

```
...
'require': {
    "lexeo/yii2-schedule": "*"
}
...
```

Next you should create bootstrapping class for your extension, [as described in the documentation](http://www.yiiframework.com/doc-2.0/guide-structure-extensions.html#bootstrapping-classes)

Place into your bootstrapping method the following code:

```php
public function bootstrap(Application $app)
{
    if ($app instanceof \yii\console\Application) {
        if ($app->has('schedule')) {
            /** @var lexeo\yii2scheduling\Schedule $schedule */
            $schedule = $app->get('schedule');
            // Place all your schedule command below
            $schedule->command('my-extension-command')->dailyAt('12:00');
        }
    }
}
```

Add to the README of your extension info for the user to register `schedule` component for the application
and add `schedule/run` command to the crontab as described upper.

Using `schedule` component
--------------------------

You do not have to use `schedule` component directly or define it in your application if you use schedule only in your application (and do not want to give ability for extensions to register they own cron jobs). 
But if you what to give extensions ability to register cron jobs, you should define `schedule` component in the application config:

```php
'schedule' => 'lexeo\yii2scheduling\Schedule',
```

Using addition functions
------------------------

If you want to use `thenPing` method of the Event, you should add the following string to the `composer.json` of your app:
```
"guzzlehttp/guzzle": "~5.0"
```

Note about timezones
--------------------

Please note, that this is PHP extension, so it uses the timezone defined in php config or in your Yii's configuration file,
so set them correctly.
