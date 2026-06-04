<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user (admin by default)',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Username')
            ->addOption('no-admin', null, InputOption::VALUE_NONE, 'Create a regular user instead of admin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username') ?? $io->ask('Username');
        $password = $io->askHidden('Password');

        if (!$username || !$password) {
            $io->error('Username and password are required.');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setUsername($username);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setRoles($input->getOption('no-admin') ? ['ROLE_USER'] : ['ROLE_ADMIN']);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('User "%s" created.', $username));
        return Command::SUCCESS;
    }
}
