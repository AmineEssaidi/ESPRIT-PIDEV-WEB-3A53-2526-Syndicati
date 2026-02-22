<?php

namespace App\Command;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-owner-password',
    description: 'Resets the password for the owner (ID 1)',
)]
class ResetOwnerPasswordCommand extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->find(1);

        if (!$user) {
            $io->error('User with ID 1 not found.');
            return Command::FAILURE;
        }

        $newPassword = 'Owner@2026!';
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        
        $user->setPasswordUser($hashedPassword);
        $this->entityManager->flush();

        $io->success(sprintf('Password for %s (ID: 1) has been updated to: %s', $user->getEmailUser(), $newPassword));

        return Command::SUCCESS;
    }
}
