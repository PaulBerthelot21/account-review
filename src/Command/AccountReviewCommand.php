<?php

namespace Cordon\AccountReview\Command;

use Cordon\AccountReview\EntityLocator;
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
    private $entityLocator;

    public function __construct(EntityManagerInterface $entityManager,
                                SerializerInterface    $serializer,
                                MailerInterface        $mailer,
                                EntityLocator $entityLocator
    )
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->mailer = $mailer;
        $this->entityLocator = $entityLocator;
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
                'entity-tag',
                't',
                InputOption::VALUE_OPTIONAL,
                'Tag de l\'entité à utiliser pour l\'extraction'
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
            $entityClass = $this->resolveEntityClass($input, $io);

            $repository = $this->entityManager->getRepository($entityClass);
            $queryBuilder = $repository->createQueryBuilder('u')
                ->orderBy('u.id', 'ASC');
            $users = $queryBuilder->getQuery()->getResult();

            if (empty($users)) {
                $io->warning('Aucune donnée trouvée pour cette entité.');
                return 0; // Command::SUCCESS
            }

            $extractedData = [];
            $metadata = $this->entityManager->getClassMetadata(get_class($users[0])); // Récupération des métadonnées

            $io->success(sprintf('Extraction de %d utilisateurs', count($users)));
            $io->note(sprintf('Classe : %s', $metadata->getName()));
            $io->progressStart(count($users));

            foreach ($users as $user) {
                $userData = [];

                // Extraction dynamique des champs scalaires
                foreach ($metadata->getFieldNames() as $field) {
                    $getter = 'get' . ucfirst($field);
                    if (method_exists($user, $getter)) {
                        $value = $user->$getter();

                        // Si la valeur est un objet DateTime, on la formate
                        if ($value instanceof \DateTimeInterface) {
                            $value = $value->format('Y-m-d H:i:s');
                        }
                        $userData[$field] = $value;
                    }
                }

                // Extraction dynamique des relations (ex: ManyToOne, OneToOne)
                foreach ($metadata->getAssociationNames() as $association) {
                    $getter = 'get' . ucfirst($association);
                    if (method_exists($user, $getter)) {
                        $relatedEntity = $user->$getter();

                        if ($relatedEntity === null) {
                            // Log ou message d'erreur
                            $io->warning(sprintf('L\'association %s est vide pour l\'utilisateur %d', $association, $user->getId()));
                            continue; // Passer à l'association suivante
                        }

                        // Cas ManyToOne / OneToOne : on récupère l'ID
                        if (is_object($relatedEntity) && method_exists($relatedEntity, 'getId')) {
                            $userData[$association] = $relatedEntity->getId();
                        }

                        // Cas OneToMany / ManyToMany : on récupère une liste d'IDs
                        elseif ($relatedEntity instanceof \Doctrine\Common\Collections\Collection) {
                            $userData[$association] = $relatedEntity
                                ->map(fn($e) => method_exists($e, 'getId') ? $e->getId() : null)
                                ->filter(fn($id) => $id !== null) // On enlève les éventuels `null`
                                ->toArray();
                        }
                    } else {
                        // Log ou message d'erreur
                        $io->warning(sprintf('La méthode %s n\'existe pas pour l\'association %s', $getter, $association));
                    }
                }
                $io->progressAdvance();
                $extractedData[] = $userData;
            }

            $io->progressFinish();

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

    private function resolveEntityClass(InputInterface $input, SymfonyStyle $io): string
    {
        $entityTag = $input->getOption('entity-tag');
        $className = $input->getOption('class');

        if ($entityTag) {
            $className = $this->entityLocator->getEntityClass($entityTag);
        }

        if (!class_exists($className)) {
            throw new InvalidArgumentException(
                sprintf('La classe "%s" n\'existe pas.', $className)
            );
        }

        return $className;
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
