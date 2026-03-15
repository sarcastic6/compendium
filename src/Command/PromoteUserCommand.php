<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:promote-user',
    description: 'Promote a user to the admin role.',
)]
class PromoteUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The email address of the user to promote.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            $io->error(sprintf('No user found with email "%s".', $email));

            return Command::FAILURE;
        }

        if ($user->getRole() === UserRole::Admin) {
            $io->warning(sprintf('User "%s" is already an admin.', $email));

            return Command::SUCCESS;
        }

        $user->setRole(UserRole::Admin);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" has been promoted to admin.', $email));

        return Command::SUCCESS;
    }
}
