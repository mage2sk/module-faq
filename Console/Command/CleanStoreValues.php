<?php
declare(strict_types=1);

namespace Panth\Faq\Console\Command;

use Panth\Faq\Model\StoreValueCleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanStoreValues extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';

    public function __construct(private readonly StoreValueCleaner $cleaner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('panth:faq:clean-store-values')
            ->setDescription('Remove FAQ store view values that only copy the default value')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Only report what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);
        try {
            foreach ($this->cleaner->entityTypes() as $entity) {
                $stats = $this->cleaner->clean($entity, $dryRun);
                $output->writeln(sprintf(
                    '%s%s: %d store rows checked, %d deleted, %d trimmed, %d copied values cleared',
                    $dryRun ? '[dry run] ' : '',
                    ucfirst($entity),
                    $stats['rows'],
                    $stats['deleted'],
                    $stats['updated'],
                    $stats['values']
                ));
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        if (!$dryRun) {
            $output->writeln('Done. Run bin/magento cache:clean full_page so the storefront shows the default values.');
        }
        return Command::SUCCESS;
    }
}
