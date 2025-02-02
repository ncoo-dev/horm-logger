# Awesome package that allow to log HTTP Outbound Request for HORM

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ncoo-dev/horm-logger.svg?style=flat-square)](https://packagist.org/packages/ncoo-dev/horm-logger)
[![Tests](https://img.shields.io/github/actions/workflow/status/ncoo-dev/horm-logger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ncoo-dev/horm-logger/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/ncoo-dev/horm-logger.svg?style=flat-square)](https://packagist.org/packages/ncoo-dev/horm-logger)

This is where your description should go. Try and limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/horm-logger.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/horm-logger)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require ncoo-dev/horm-logger
```

## Prunning
You should schedule the horm:prune Artisan command in your application's App\Console\Kernel class. 
You are free to choose the appropriate interval at which this command should be run:

```php
/**
* Define the application's command schedule.
*
* @param  \Illuminate\Console\Scheduling\Schedule  $schedule
* @return void
  */
  protected function schedule(Schedule $schedule)
  {
      $schedule->command('horm:prune')->daily();
  }
```

Behind the scenes, the horm:prune command will use your configuration to know what logs to prune. 
By default, it will keep all logs for 2 days. 
You can change this behavior by publishing the configuration file:

```bash

```php
return [
    ...
    'model' => [
        'keep_history_for_days' => 2,
    ],
    ...
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Dominique Thomas](https://github.com/ncoo-dev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
