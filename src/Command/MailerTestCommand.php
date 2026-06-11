<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Diagnostic helper: sends a plain test email straight through the mailer.
 *
 *   php bin/console app:mailer:test you@example.com --env=prod
 *
 * Unlike the notification flow, this does NOT swallow exceptions: any SMTP
 * error (auth, TLS, blocked port…) surfaces directly in the console.
 */
#[AsCommand(
    name: 'app:mailer:test',
    description: 'Send a test email to verify the SMTP configuration',
)]
final class MailerTestCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%app.mailer.from%')]
        private readonly string $fromAddress,
        #[Autowire('%app.mailer.from_name%')]
        private readonly string $fromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Recipient email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');

        $email = new Email()
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->subject('Les Marmottes — test SMTP')
            ->text('Si tu reçois cet e-mail, la configuration SMTP de production fonctionne.');

        $this->mailer->send($email);

        $io->success(sprintf('Test email handed to the mailer for %s (from %s).', $to, $this->fromAddress));

        return Command::SUCCESS;
    }
}
