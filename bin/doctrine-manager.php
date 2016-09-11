<?php
/**
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder)
 * Doctrine2 managers builder
 *
 * @license BSD-3-Clause
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

use Symfony\Component\Console\Application;

$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require $autoloadFile;
    }
}

$application = null;

$directories = [
    getcwd(),
    getcwd() . DIRECTORY_SEPARATOR . 'config',
];
foreach ($directories as $directory) {
    $configFile = $directory . DIRECTORY_SEPARATOR . 'cli-config.php';

    if (file_exists($configFile) && is_readable($configFile)) {
        $application = require $configFile;

        break;
    }
}

if (!$application instanceof Application) {
    echo <<<'HELP'
You are missing a "./cli-config.php" or "./config/cli-config.php" file
in your project, the file is not readable or it's not returning a
"Symfony\Component\Console\Application" object.
You can use the following sample as a template:

<?php
use \Jgut\Doctrine\ManagerBuilder\ConsoleBuilder;
use \Jgut\Doctrine\ManagerBuilder\RelationalBuilder;

// replace with path to your own project bootstrap file
require 'bootstrap.php';

$consoleBuilder = new ConsoleBuilder;
$consoleBuilder->addBuilder(new RelationalBuilder($options));

return $consoleBuilder->getApplication();

HELP;

    exit(1);
}

$application->run();
