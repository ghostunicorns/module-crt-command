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
use GhostUnicorns\CrtBase\Model\Action\CollectAction;
use GhostUnicorns\CrtEntity\Api\EntityRepositoryInterface;
use GhostUnicorns\CrtActivity\Api\ActivityRepositoryInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CollectCommand extends Command
{
    /** @var string */
    const EXTRA = 'extra';

    /** @var string */
    const TYPE = 'type';

    /** @var string */
    const SYNCHRONOUS_MODE = 'synchronous';

    /**
     * @var CrtConfigInterface
     */
    private $config;

    /**
     * @var CollectAction
     */
    private $collectAction;

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
     * @param CollectAction $collectAction
     * @param Console $consoleLogger
     * @param CrtListInterface $crtList
     * @param EntityRepositoryInterface $entityRepository
     * @param ActivityRepositoryInterface $activityRepository
     * @param SerializerInterface $serializer
     * @param null $name
     */
    public function __construct(
        CrtConfigInterface $config,
        CollectAction $collectAction,
        Console $consoleLogger,
        CrtListInterface $crtList,
        EntityRepositoryInterface $entityRepository,
        ActivityRepositoryInterface $activityRepository,
        SerializerInterface $serializer,
        $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->collectAction = $collectAction;
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
        $text[] = __('Available CollectorList types: ')->getText();
        $allDownlaoderList = $this->crtList->getAllCollectorList();
        foreach ($allDownlaoderList as $name => $downlaoderList) {
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
        $this->setDescription('Crt: Collect for a specific Type');

        $this->addArgument(
            self::TYPE,
            InputArgument::REQUIRED,
            'CollectorList Type name'
        );

        $this->addArgument(
            self::EXTRA,
            InputArgument::OPTIONAL,
            'Extra data',
            ''
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
     * @return void
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
        $extra = $input->getArgument(self::EXTRA);
        $sync = (bool)$input->getOption(self::SYNCHRONOUS_MODE);
        $activityId = $this->collectAction->execute($type, $extra);
        if ($sync && $activityId) {
            $result = $this->entityRepository->getAllDataRefinedByActivityIdGroupedByIdentifier($activityId);
            $output->writeln('Result:');
            $output->writeln($this->serializer->serialize($result));
        }
        if ($activityId) {
            $activity = $this->activityRepository->getById($activityId);
            $activityExtraData = $activity->getExtra()->getData();
            $output->writeln('Activity %1 extra data:', $activityId);
            $output->writeln($this->serializer->serialize($activityExtraData));
        }
    }
}
