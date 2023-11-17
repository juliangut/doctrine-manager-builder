<?php

/*
 * doctrine-manager-builder (https://github.com/juliangut/doctrine-manager-builder).
 * Doctrine2 managers builder.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/doctrine-manager-builder
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Doctrine\ManagerBuilder\Console\Command\MongoDB;

use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;
use Doctrine\ORM\Mapping\MappingException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InfoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('odm:info')
            ->setDescription('Show basic information about all mapped documents')
            ->setHelp(
                <<<'EOT'
                The <info>%command.name%</info> shows basic information about which
                documents exist and possibly if their mapping information contains errors or
                not.
                EOT,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = (new SymfonyStyle($input, $output))->getErrorStyle();

        /** @var DocumentManagerHelper $helper */
        $helper = $this->getHelper('documentManager');
        $documentManager = $helper->getDocumentManager();

        $mappingDriver = $documentManager->getConfiguration()
            ->getMetadataDriverImpl();
        if ($mappingDriver === null) {
            $style->caution(['You do not have Doctrine mapping configuration properly configured.']);

            return self::FAILURE;
        }

        /** @var list<class-string<object>> $documentClassNames */
        $documentClassNames = $mappingDriver->getAllClassNames();
        if (\count($documentClassNames) === 0) {
            $style->caution(
                [
                    'You do not have any mapped Doctrine documents according to the current configuration.',
                    'If you have documents or mapping files you should check your mapping configuration for errors.',
                ],
            );

            return self::FAILURE;
        }

        $style->text(sprintf('Found <info>%d</info> mapped documents:', \count($documentClassNames)));
        $style->newLine();

        $failure = false;

        foreach ($documentClassNames as $entityClassName) {
            try {
                $documentManager->getClassMetadata($entityClassName);
                $style->text(sprintf('<info>[OK]</info>   %s', $entityClassName));
            } catch (MappingException $e) {
                $style->text(
                    [
                        sprintf('<error>[FAIL]</error> %s', $entityClassName),
                        sprintf('<comment>%s</comment>', $e->getMessage()),
                        '',
                    ],
                );

                $failure = true;
            }
        }

        return $failure ? self::FAILURE : self::SUCCESS;
    }
}
