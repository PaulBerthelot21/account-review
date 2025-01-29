<?php

namespace Cordon\AccountReview\Command;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Mime\Email;

class AccountReviewCommand extends Command
{
    protected static $defaultName = 'app:account-review';
    protected static $defaultDescription = 'Extrait les données des utilisateurs pour l\'audit ISO 27001';

    private $entityManager;
    private $serializer;
    private $mailer;

    public function __construct(EntityManagerInterface $entityManager,
                                SerializerInterface $serializer,
                                MailerInterface $mailer
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription)
            ->addOption(
                'class',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Nom de la classe User à utiliser pour l\'extraction',
                'App\Entity\User'
            )
            ->addOption(
                'method',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Méthode d\'envoi des données (log, local, mail)',
                'log'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Format de sortie (json, csv, xml)',
                'json'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Chemin du fichier de sortie'
            )
            ->addOption(
                'emitter',
                'em',
                InputOption::VALUE_OPTIONAL,
                'Adresse email de l\'émetteur',
                'no-reply@account-review.com'
            )
            ->addOption(
                'recipient',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Adresse email du destinataire'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $repository = $this->entityManager->getRepository($input->getOption('class'));
            $queryBuilder = $repository->createQueryBuilder('u');
            $users = $queryBuilder->getQuery()->getResult();

            $extractedData = [];
            foreach ($users as $user) {
                $userData = [
                    'id' => method_exists($user, 'getId') ? $user->getId() : null,
                    'email' => method_exists($user, 'getEmail') ? $user->getEmail() : null,
                    'login' => method_exists($user, 'getLogin') ? $user->getLogin() :
                        (method_exists($user, 'getUsername') ? $user->getUsername() : null),
                    'nom' => method_exists($user, 'getNom') ? $user->getNom() :
                        (method_exists($user, 'getLastName') ? $user->getLastName() : null),
                    'prenom' => method_exists($user, 'getPrenom') ? $user->getPrenom() :
                        (method_exists($user, 'getFirstName') ? $user->getFirstName() : null),
                    'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
                ];

                if (method_exists($user, 'getLastLogin')) {
                    $lastLogin = $user->getLastLogin();
                    $userData['lastLogin'] = $lastLogin instanceof \DateTimeInterface
                        ? $lastLogin->format('Y-m-d H:i:s')
                        : null;
                }

                if (method_exists($user, 'getCreatedAt')) {
                    $createdAt = $user->getCreatedAt();
                    $userData['createdAt'] = $createdAt instanceof \DateTimeInterface
                        ? $createdAt->format('Y-m-d H:i:s')
                        : null;
                }

                if (method_exists($user, 'isActive')) {
                    $userData['isActive'] = $user->isActive();
                }

                $extractedData[] = $userData;
            }

            $format = $input->getOption('format');
            $content = $this->serializeData($extractedData, $format);

            $method = $input->getOption('method');
            switch ($method) {
                case 'mail':
                    $this->handleMailMethod($input, $output, $content, $format);
                    break;
                case 'local':
                    $this->handleLocalMethod($input, $output, $content, $format);
                    break;
                case 'log':
                default:
                    $output->write($content);
            }
            return 0; // Command::SUCCESS

        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de l\'extraction : ' . $e->getMessage());
            return 1; // Command::FAILURE
        }
    }

    private function handleMailMethod(InputInterface $input, OutputInterface $output, string $content, string $format)
    {
        $io = new SymfonyStyle($input, $output);
        $recipient = $input->getOption('recipient');
        $emitter = $input->getOption('emitter');

        if (!$recipient) {
            throw new \InvalidArgumentException(
                'L\'option --recipient est requise pour l\'envoi par email'
            );
        }

        $timestamp = date('Y-m-d_H-i-s');
        $fileName = sprintf('accounts_review_%s.%s', $timestamp, $format);

        $email = (new Email())
            ->from($emitter)
            ->to($recipient)
            ->subject(sprintf('Revue de compte utilisateurs - Export %s', date('d/m/Y')))
            ->text('Veuillez trouver ci-joint l\'export des données utilisateurs.')
            ->attach($content, $fileName, sprintf('application/%s', $format));

        $this->mailer->send($email);
        $io->success(sprintf('Les données ont été envoyées par email à %s', $recipient));
    }

    private function handleLocalMethod(InputInterface $input, OutputInterface $output, string $content, string $format)
    {
        $io = new SymfonyStyle($input, $output);
        $outputPath = $input->getOption('output');

        if (!$outputPath) {
            throw new InvalidArgumentException(
                'L\'option --output est requise pour l\'export local'
            );
        }

        file_put_contents($outputPath, $content);
        $io->success(sprintf('Les données ont été exportées dans %s au format %s', $outputPath, $format));
    }

    private function serializeData(array $data, string $format): string
    {
        switch ($format) {
            case 'json':
                return $this->serializer->serialize($data, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);
            case 'csv':
                return $this->serializer->serialize($data, 'csv');
            case 'xml':
                return $this->serializer->serialize($data, 'xml');
            default:
                throw new InvalidArgumentException(
                    sprintf('Le format "%s" n\'est pas supporté. Utilisez json, csv ou xml.', $format)
                );
        }
    }
}
