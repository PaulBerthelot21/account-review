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
    protected static $defaultDescription = 'Extrait les données des entités';

    private $entityManager;
    private $serializer;
    private $mailer;
    private $entityLocator;

    public function __construct(EntityManagerInterface $entityManager,
                                SerializerInterface    $serializer,
                                MailerInterface        $mailer,
                                EntityLocator          $entityLocator
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
            $entitiesClasses = $this->resolveEntityClass($input, $io);
            foreach ($entitiesClasses as $entityClass) {
                $this->extractData($input, $output, $io, $entityClass);
            }
            return 0; // Command::SUCCESS

        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de l\'extraction : ' . $e->getMessage());
            return 1; // Command::FAILURE
        }
    }

    private function resolveEntityClass(InputInterface $input, SymfonyStyle $io): array
    {
        // Récupération des entités exportables configurées dans le service.yml
        $entities = $this->entityLocator->getExportableEntities();

        if (empty($entities)) {
            $defaultEntity = 'App\Entity\User';
            $io->warning(sprintf('Aucune entité exportable trouvée. Utilisation de l\'entité par défaut : %s', $defaultEntity));

            if (class_exists($defaultEntity)) {
                $entities[] = $defaultEntity;
            } else {
                throw new \InvalidArgumentException(
                    sprintf('L\'entité par défaut %s n\'existe pas. Vérifiez votre configuration.', $defaultEntity)
                );
            }
        }

        return $entities;
    }

    private function extractData(InputInterface $input, OutputInterface $output, SymfonyStyle $io,
                                 string         $entityClass): void
    {
        $repository = $this->entityManager->getRepository($entityClass);
        $queryBuilder = $repository->createQueryBuilder('e')->orderBy('e.id', 'ASC');
        $entities = $queryBuilder->getQuery()->getResult();

        if (empty($entities)) {
            $io->warning('Aucune donnée trouvée pour cette entité.');
            return;
        }

        $extractedData = [];
        $metadata = $this->entityManager->getClassMetadata($entityClass);

        $excludedFields = $this->entityLocator->getExcludedFields($entityClass);
        $io->progressStart(count($entities));

        foreach ($entities as $entity) {
            $entityData = [];

            // Extraction dynamique des champs scalaires
            foreach ($metadata->getFieldNames() as $field) {
                if (in_array($field, $excludedFields)) {
                    continue;
                }

                $getter = 'get' . ucfirst($field);
                if (method_exists($entity, $getter)) {
                    $value = $entity->$getter();

                    // Si la valeur est un objet DateTime, on la formate
                    if ($value instanceof \DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    }
                    $entityData[$field] = $value;
                }
            }

            // Extraction des relations (ManyToOne, OneToOne, ManyToMany, OneToMany)
            foreach ($metadata->associationMappings as $association => $mapping) {

                // On ne traite que les associations qui existent dans les métadonnées
                if (!array_key_exists($association, $metadata->associationMappings)) {
                    $io->warning(sprintf('Association inconnue: %s', $association));
                    continue;
                }

                if (in_array($association, $excludedFields, true)) {
                    $io->warning(sprintf('Association exclue: %s', $association));
                    continue;
                }

                $getter = 'get' . ucfirst($association);
                if (!method_exists($entity, $getter)) {
                    $io->warning(sprintf('Méthode d\'accès manquante pour l\'association: %s', $association));
                    continue;
                }

                try {
                    $relatedEntity = $entity->$getter();

                    // Gestion des relations ManyToOne et OneToOne (relation unique)
                    if ($relatedEntity !== null && ($mapping['type'] === \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_ONE ||
                            $mapping['type'] === \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_ONE)) {
                        $entityData[$association] = method_exists($relatedEntity, '__toString') ? (string)$relatedEntity : $relatedEntity->getId();
                    }

                    // Gestion des relations OneToMany et ManyToMany (collection)
                    elseif ($relatedEntity instanceof \Doctrine\Common\Collections\Collection) {
                        $entityData[$association] = [];

                        foreach ($relatedEntity as $relatedItem) {
                            $entityData[$association][] = method_exists($relatedItem, '__toString') ? (string)$relatedItem : $relatedItem->getId();
                        }
                    }
                } catch (\Exception $e) {
                    $io->warning(sprintf('Erreur lors de l\'extraction de l\'association %s: %s', $association, $e->getMessage()));
                    continue; // Continue même en cas d'erreur
                }
            }

            $io->progressAdvance();
            $extractedData[] = $entityData;
        }

        $io->progressFinish();

        $format = $input->getOption('format');
        $content = $this->serializeData($extractedData, $format);

        $method = $input->getOption('method');
        switch ($method) {
            case 'mail':
                $this->handleMailMethod($input, $output, $content, $format, $entityClass);
                break;
            case 'local':
                $this->handleLocalMethod($input, $output, $content, $format, $entityClass);
                break;
            case 'log':
            default:
                $output->write($content);
        }
    }

    private function handleMailMethod(InputInterface $input, OutputInterface $output, string $content, string $format,
                                      string         $entityClass)
    {
        $io = new SymfonyStyle($input, $output);
        $recipient = $input->getOption('recipient');
        $emitter = $input->getOption('emitter');

        if (!$recipient) {
            throw new \InvalidArgumentException(
                'L\'option --recipient est requise pour l\'envoi par email'
            );
        }

        // Récupération du nom de la classe sans le namespace
        $className = substr(strrchr($entityClass, "\\"), 1);

        $timestamp = date('Y-m-d_H-i-s');
        $fileName = sprintf('accounts_review_%s_%s.%s', $className, $timestamp, $format);

        $email = (new Email())
            ->from($emitter)
            ->to($recipient)
            ->subject(sprintf('Revue de compte %s - Export %s', $className, date('d/m/Y')))
            ->text('Veuillez trouver ci-joint l\'export des données utilisateurs.')
            ->attach($content, $fileName, sprintf('application/%s', $format));

        $this->mailer->send($email);
        $io->success(sprintf('Les données ont été envoyées par email à %s', $recipient));
    }

    private function handleLocalMethod(InputInterface $input, OutputInterface $output, string $content, string $format,
                                       string         $entityClass)
    {
        $io = new SymfonyStyle($input, $output);

        // Extraction du court nom de la classe (sans le namespace)
        $className = substr(strrchr($entityClass, "\\"), 1);
        $timestamp = date('Y-m-d_H-i-s');

        $outputPath = sprintf('accounts_review_%s_%s.%s', $className, $timestamp, $format);

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
