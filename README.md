# Basebox-installer


 [![Packagist](https://img.shields.io/packagist/v/enrise/basebox-installer.svg?style=flat-square)](https://packagist.org/packages/enrise/basebox-installer)  [![Packagist](https://img.shields.io/packagist/dm/enrise/basebox-installer.svg?style=flat-square)](https://packagist.org/packages/enrise/basebox-installer/stats) [![Packagist](https://img.shields.io/packagist/l/enrise/basebox-installer.svg?style=flat-square)](https://github.com/enrise/basebox-installer/blob/master/LICENSE)

## About

Basebox-installer is a little tool that helps you setting up an [Enrise Basebox](https://github.com/enrise/basebox) for a new project.

## Installation

The basebox-installer can be added as a composer package by running:

    composer global require "enrise/basebox-installer=~1"

Make sure to place the `~/.composer/vendor/bin` directory in your PATH so the `basebox` executable is found when you run the `basebox` command in your terminal.

## Usage
If the Symfony 2 [Console Component](http://symfony.com/doc/current/components/console/introduction.html) so the flags and options can be specified in the various formats outlined in the SF2 Console Component documentation.

The following options and flags are available:

| Option/flag      | Description                                                  | Default            |
| ---------------- |  ----------------------------------------------------------- | ------------------ |
| --webserver      | Which webserver do you want (nginx, apache)?                 | nginx              |
| --edition        | Which stack do you want (vanilla, zendserver)?               | zendserver         |
| --zs-version     | Which version of ZendServer do you want to use               | None               |
| --php-version    | Which version of PHP do you want to use                      | None               |
| --nginx-mainline | Use the NGINX Mainline or stable release                     | false              |
| --domain         | Create a domain with the following name                      | None               |
| --database       | Create a database with the following name                    | None               |
| --up             | Run "vagrant up" after installing the basebox                | -                  |

For instance, if you want to spin up an environment with PHP7, a database and NGINX you can execute the following from the root of your project:
```
basebox new  --webserver nginx --edition vanilla --domain test-dev.local --database test_dev --php-version=7.0
```

## Bugs, questions, and improvements

If you found a bug or have a question, please open an issue on the GitHub Issue tracker.
Improvements can be sent by a Pull Request against the develop branch and are greatly appreciated!
