Schedule extension for Yii2
===========================

This extension is the port of Laravel's Schedule component (http://laravel.com/docs/master/artisan#scheduling-artisan-commands)

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require omnilight/yii2-schedule "*"
```

or add

```json
"omnilight/yii2-schedule": "*"
```

to the `require` section of your composer.json.

Description
-----------

This project is inspired by the Laravel's Schedule component and tries to bring it's simplicity to the Yii framework.
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

This extension is support all features of Laravel's Schedule, except environments and maintance mode.

**Scheduling Closures**

```php
$schedule->call(function()
{
    // Do some task...

})->hourly();
```

**Scheduling Terminal Commands**

```php
$schedule->exec('composer self-update')->daily();
```

**Manual Cron Expression**

```php
$schedule->command('foo')->cron('* * * * *');
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
$schedule->command('foo')->monthly()->when(function()
{
    return true;
});
```

**E-mail The Output Of A Scheduled Job**

```php
$schedule->command('foo')->sendOutputTo($filePath)->emailOutputTo('foo@example.com');
```