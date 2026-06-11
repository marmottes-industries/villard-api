<?php

namespace App\Command;

use App\Notification\OccupationEndNotificationDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends the end-of-stay notification for every occupation ending today.
 *
 * On Infomaniak shared hosting the task scheduler can only call a URL, so in
 * production this is triggered through the HTTP endpoint
 * GET /api/cron/occupation-end-notifications (see CronController). This command
 * stays available for local runs / manual testing over SSH:
 *
 *   php bin/console app:notifications:dispatch-occupation-end
 *
 * Idempotent: each occupation is stamped with endNotifiedAt once notified, so
 * re-running the command (or running it several times a day) never double-sends.
 */
#[AsCommand(
    name: 'app:notifications:dispatch-occupation-end',
    description: 'Notify occupants whose stay ends today (email + push)',
)]
final class DispatchOccupationEndNotificationsCommand extends Command
{
    public function __construct(
        private readonly OccupationEndNotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            null,
            InputOption::VALUE_REQUIRED,
            'Override the reference day (Y-m-d), for testing',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateOption = $input->getOption('date');
        try {
            $day = $dateOption
                ? new \DateTimeImmutable($dateOption)
                : new \DateTimeImmutable('today');
        } catch (\Exception) {
            $io->error(sprintf('Invalid date "%s" (expected Y-m-d).', $dateOption));

            return Command::FAILURE;
        }

        $sent = $this->dispatcher->dispatch($day);

        if (0 === $sent) {
            $io->success(sprintf('No stay ending on %s — nothing to send.', $day->format('Y-m-d')));

            return Command::SUCCESS;
        }

        $io->success(sprintf('Dispatched %d end-of-stay notification(s) for %s.', $sent, $day->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
