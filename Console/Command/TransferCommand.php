<?php
/*
  * Copyright © Ghost Unicorns snc. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace GhostUnicorns\CrtCommand\Console\Command;

use Exception;
use GhostUnicorns\CrtBase\Api\CrtConfigInterface;
use GhostUnicorns\CrtBase\Api\CrtListInterface;
use GhostUnicorns\CrtBase\Logger\Handler\Console;
use GhostUnicorns\CrtBase\Model\Action\TransferAction;
use GhostUnicorns\CrtEntity\Api\EntityRepositoryInterface;
use GhostUnicorns\CrtActivity\Api\ActivityRepositoryInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TransferCommand extends Command
{
    /** @var string */
    const TYPE = 'type';

    /** @var string */
    const SYNCHRONOUS_MODE = 'synchronous';

    /** @var string */
    const ACTIVITY_ID = 'activity';

    /**
     * @var CrtConfigInterface
     */
    private $config;

    /**
     * @var TransferAction
     */
    private $transferAction;

    /**
     * @var Console
     */
    private $consoleLogger;

    /**
     * @var CrtListInterface
     */
    private $crtList;

    /**
     * @var EntityRepositoryInterface
     */
    private $entityRepository;

    /**
     * @var ActivityRepositoryInterface
     */
    private $activityRepository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param CrtConfigInterface $config
     * @param TransferAction $transferAction
     * @param Console $consoleLogger
     * @param CrtListInterface $crtList
     * @param EntityRepositoryInterface $entityRepository
     * @param ActivityRepositoryInterface $activityRepository
     * @param SerializerInterface $serializer
     * @param null $name
     */
    public function __construct(
        CrtConfigInterface $config,
        TransferAction $transferAction,
        Console $consoleLogger,
        CrtListInterface $crtList,
        EntityRepositoryInterface $entityRepository,
        ActivityRepositoryInterface $activityRepository,
        SerializerInterface $serializer,
        $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->transferAction = $transferAction;
        $this->consoleLogger = $consoleLogger;
        $this->crtList = $crtList;
        $this->entityRepository = $entityRepository;
        $this->activityRepository = $activityRepository;
        $this->serializer = $serializer;
    }

    /**
     * @return string
     */
    public function getHelp()
    {
        $text = [];
        $text[] = __('Available TransferorList types: ')->getText();
        $allUplaoderList = $this->crtList->getAllTransferorList();
        foreach ($allUplaoderList as $name => $uplaoderList) {
            $text[] = $name;
            $text[] = ', ';
        }
        array_pop($text);
        return implode('', $text);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setDescription('Crt: Transfer for a specific Type');
        $this->addArgument(
            self::TYPE,
            InputArgument::REQUIRED,
            'TransferorList Type name'
        );

        $this->addOption(
            self::ACTIVITY_ID,
            'a',
            InputOption::VALUE_OPTIONAL,
            'Specify the activity id',
            null
        );

        $this->addOption(
            self::SYNCHRONOUS_MODE,
            's',
            InputOption::VALUE_NONE,
            'Run in Sync mode and print the return',
            null
        );
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->isEnabled()) {
            $output->writeln(
                'Please enable Crt to continue:'.' Stores -> Configurations -> CRT -> Base -> General -> Enalbe Crt'
            );
            return;
        }
        $this->consoleLogger->setConsoleOutput($output);
        $type = $input->getArgument(self::TYPE);
        $sync = (bool)$input->getOption(self::SYNCHRONOUS_MODE);
        $activityId = $input->getOption(self::ACTIVITY_ID) ? (int)$input->getOption(self::ACTIVITY_ID) : null;
        $activityId = $this->transferAction->execute($type, $activityId);
        if ($sync && $activityId) {
            $result = $this->entityRepository->getAllDataRefinedByActivityIdGroupedByIdentifier($activityId);
            $output->writeln('Result:');
            $output->writeln($this->serializer->serialize($result));
        }
        $activity = $this->activityRepository->getById($activityId);
        $activityExtraData = $activity->getExtra()->getData();
        $output->writeln('Activity %1 extra data:', $activityId);
        $output->writeln($this->serializer->serialize($activityExtraData));
    }
}
