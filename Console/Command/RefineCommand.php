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
use GhostUnicorns\CrtBase\Model\Action\RefineAction;
use GhostUnicorns\CrtEntity\Api\EntityRepositoryInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefineCommand extends Command
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
     * @var RefineAction
     */
    private $refineAction;

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
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param CrtConfigInterface $config
     * @param RefineAction $refineAction
     * @param Console $consoleLogger
     * @param CrtListInterface $crtList
     * @param EntityRepositoryInterface $entityRepository
     * @param SerializerInterface $serializer
     * @param null $name
     */
    public function __construct(
        CrtConfigInterface $config,
        RefineAction $refineAction,
        Console $consoleLogger,
        CrtListInterface $crtList,
        EntityRepositoryInterface $entityRepository,
        SerializerInterface $serializer,
        $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->refineAction = $refineAction;
        $this->consoleLogger = $consoleLogger;
        $this->crtList = $crtList;
        $this->entityRepository = $entityRepository;
        $this->serializer = $serializer;
    }

    /**
     * @return string
     */
    public function getHelp()
    {
        $text = [];
        $text[] = __('Available RefinerList types: ')->getText();
        $allRefinerList = $this->crtList->getAllRefinerList();
        foreach ($allRefinerList as $name => $refinerList) {
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
        $this->setDescription('Crt: Refine for a specific Type');
        $this->addArgument(
            self::TYPE,
            InputArgument::REQUIRED,
            'RefinerList Type name'
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
        $sync = (bool)$input->getOption(self::SYNCHRONOUS_MODE);
        $activityId = $input->getOption(self::ACTIVITY_ID) ? (int)$input->getOption(self::ACTIVITY_ID) : null;
        $activityId = $this->refineAction->execute($type, $activityId);
        if ($sync && $activityId) {
            $result = $this->entityRepository->getAllDataRefinedByActivityIdGroupedByIdentifier($activityId);
            $output->writeln('Result:');
            $output->writeln($this->serializer->serialize($result));
        }
    }
}
