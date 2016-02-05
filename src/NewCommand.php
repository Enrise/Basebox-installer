<?php

namespace Basebox\Installer\Console;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Install the basebox for the current project.')
            ->addOption('webserver', null, InputOption::VALUE_OPTIONAL, 'Which webserver do you want (nginx, apache)?',
                'nginx')

            ->addOption('edition', null, InputOption::VALUE_OPTIONAL, 'Which stack do you want (vanilla, zendserver)?',
                'zendserver')
            ->addOption('zs-version', null, InputOption::VALUE_OPTIONAL,
                'Which version of ZendServer do you want to use', null)
            ->addOption('php-version', null, InputOption::VALUE_OPTIONAL,
                'Which version of PHP do you want to use', null)

            ->addOption('nginx-mainline', null, InputOption::VALUE_OPTIONAL,
                'Use the NGINX Mainline or stable release', False)

            ->addOption('domain', null, InputOption::VALUE_OPTIONAL, 'Create a domain with the following name')
            ->addOption('database', null, InputOption::VALUE_OPTIONAL, 'Create a database with the following name')
            ->addOption('up', null, InputOption::VALUE_NONE, 'Run "vagrant up" after installing the basebox');
    }

    /**
     * Execute the command.
     *
     * @param  InputInterface $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->verifyGit($output);

        $this->verifyBaseboxDoesntExist(
            $directories = [
                getcwd() . '/dev/basebox',
                getcwd() . '/dev/salt',
            ],
            $output
        );

        $output->writeln('<info>Installing basebox...</info>');

        $commands = [
            'git submodule add git@github.com:enrise/basebox dev/basebox',
            'ln -s dev/basebox/Vagrantfile .',
            'cp dev/basebox/Vagrantfile.local.dist Vagrantfile.local',
            'cp dev/basebox/salt.dist dev/salt -r',
            'cd dev/basebox',
            'git submodule sync',
            'git submodule update --init --recursive',
        ];

        $process = new Process(implode(' && ', $commands), getcwd(), null, null, null);

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<info>Configuring basebox...</info>');
        $this->configureBasebox($input, $output);

        $output->writeln('<comment>Basebox ready! Build something amazing.</comment>');

        if ($input->getOption('up')) {
            $process = new Process('vagrant up', getcwd(), null, null, null);
            $process->run(function ($type, $line) use ($output) {
                $output->write($line);
            });
        }


    }

    /**
     * Configure the basebox
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function configureBasebox(InputInterface $input, OutputInterface $output)
    {
        $this->configureVagrant($input, $output);
        $this->configureVhosting($input, $output);
        $this->configureVhost($input, $output);
    }

    /**
     * Configure the vhosting module
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function configureVhosting(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Configuring vhosting module...</info>');
        $pillar = [];

        $pillar['vhosting'] = [
            'server' => [
                'webserver' => $input->getOption('webserver'),
                'webserver_edition' => $input->getOption('edition'),
            ]
        ];

        if (strtolower($input->getOption('edition')) === 'zendserver') {
            $pillar['zendserver'] = [
                'webserver' => $input->getOption('webserver'),
                'bootstrap' => false,
            ];

            if (null !== $input->getOption('zs-version')) {
                $pillar['zendserver']['version']['zend'] = $input->getOption('zs-version');
            }

            if (null !== $input->getOption('php-version')) {
                $pillar['zendserver']['version']['php'] = $input->getOption('php-version');
            }

            if (null !== $input->getOption('nginx-mainline')) {
                $pillar['zendserver']['nginx_mainline'] = (bool)$input->getOption('nginx-mainline');
            }
        } else {
            // Use vanilla
            $pillar['zendserver'] = '~';

            if (null !== $input->getOption('nginx-mainline')) {
                $pillar['nginx']['package']['mainline'] = (bool)$input->getOption('nginx-mainline');
            }

            if (null !== $input->getOption('php-version')) {
                $pillar['phpfpm']['php_versions'] = [$input->getOption('php-version')];
            }
        }

        $yaml = Yaml::dump($pillar, 10);
        file_put_contents(getcwd() . '/dev/salt/pillars/custom.sls', $yaml);
    }

    /**
     * Configure the vhost in Saltstack
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function configureVhost(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Configuring vhost...</info>');
        $pillar = ['vhosting' => ['users' => []]];
        $domain = $input->getOption('domain');

        $user = 'project';
        if (null !== $domain) {
            // Generate username based on domain
            $user = $this->generateUsername($domain);

            $vhost = [
                'webroot_public' => true,
                'fastcgi_params' =>
                    [
                        'fastcgi_param APPLICATION_ENV development;'
                    ],
                'extra_config' => [
                    'sendfile off;'
                ],
            ];

            // Allow PHP version to be specified
            if (null !== $input->getOption('php-version')) {
                $vhost['php_version'] = $input->getOption('php-version');
            }

            $pillar['vhosting']['users'][$user]['vhost'][$domain] = $vhost;

            $output->writeln(sprintf('<info>Vhost <comment>%s</comment> created. (user: <comment>%s</comment>)</info>',
                $domain, $user));
        }

        $database = $input->getOption('database');
        if (null !== $database) {
            $db_pass = $this->generatePassword(12);

            $pillar['vhosting']['users'][$user]['mysql_database'] = [
                $database => [
                    'password' => $db_pass
                ]
            ];

            $output->writeln(sprintf('<info>Database <comment>%s</comment> created. Password:</info> <comment>%s</comment>',
                $database, $db_pass));
        }

        // Save the pillar
        $yaml = Yaml::dump($pillar, 10);
        file_put_contents(getcwd() . '/dev/salt/pillars/vhosts.sls', $yaml);
    }

    /**
     * Configure the Vagrantfile.local
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function configureVagrant(InputInterface $input, OutputInterface $output)
    {
        // Edit the Vagrantfile.local
        $vagrantFile = file(getcwd() . '/Vagrantfile.local');

        $domain = $input->getOption('domain');

        $fp = fopen(getcwd() . '/Vagrantfile.local', 'w');
        foreach ($vagrantFile as $lineNum => $line) {
            if (strpos($line, '=') != 0) {
                $x = explode(' = ', $line);

                // Trim the values to get rid of excess whitespace
                $x[0] = trim($x[0]);
                $x[1] = trim($x[1]);

                switch ($x[0]) {
                    case '$vm_hostname':
                        $x[1] = sprintf('"%s"', $domain);
                        break;
                    case '$vm_ip':
                        $x[1] = sprintf('"192.168.56.%d"', rand(10, 200));
                        break;
                    case '#$vm_aliases':
                    case '$vm_aliases':
                        $x[0] = '$vm_aliases';
                        $x[1] = sprintf("['%s']", $domain);
                        break;
                }

                $line = implode(' = ', $x) . PHP_EOL;
                $vagrantFile[$lineNum] = $line;
            }
            // Write line
            fwrite($fp, $line);
        }
        fclose($fp);
    }

    /**
     * Generate a safe username for the Vagrant user
     *
     * @param $domain
     * @return mixed|string
     */
    protected function generateUsername($domain)
    {
        $string = strtolower($domain);
        $string = preg_replace('/[^a-zA-Z 0-9]+/', '_', $string);
        $string = substr($string, 0, 15); // max length of unix users = 16
        return $string;
    }

    /**
     * @param int $length
     * @return string
     */
    protected function generatePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        return substr(str_shuffle($chars), 0, $length);
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param array $directories
     * @param OutputInterface $output
     * @return void
     */
    protected function verifyBaseboxDoesntExist(array $directories, OutputInterface $output)
    {
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                throw new RuntimeException('Basebox already installed!');
            }
        }
    }

    /**
     * Verify that the current workdir is one that holds a Git repo
     *
     * @param OutputInterface $output
     */
    protected function verifyGit(OutputInterface $output)
    {
        if (!is_dir('.git')) {
            throw new RuntimeException('This is not a git repo');
        }
    }
}
