<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\DataImport\Communication\Console;

use Exception;
use Generated\Shared\Transfer\DataImportConfigurationTransfer;
use Generated\Shared\Transfer\DataImporterConfigurationTransfer;
use Generated\Shared\Transfer\DataImporterReaderConfigurationTransfer;
use Generated\Shared\Transfer\DataImporterReportTransfer;
use Spryker\Zed\DataImport\DataImportConfig;
use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \Spryker\Zed\DataImport\Business\DataImportFacadeInterface getFacade()
 * @method \Spryker\Zed\DataImport\Communication\DataImportCommunicationFactory getFactory()
 */
class DataImportConsole extends Console
{
    public const DEFAULT_IMPORTER_TYPE = 'full';

    public const DEFAULT_NAME = 'data:import';
    public const DEFAULT_DESCRIPTION = 'This command executes your importers (full-import). Add this command with another name e.g. "new DataImportConsole(\'data:import:category\')" to your ConsoleDependencyProvider and you can run a single DataImporter which is mapped to the latter part of the command name.';

    public const IMPORTER_TYPE_DESCRIPTION = 'This command executes your "%s" importer.';

    public const OPTION_FILE_NAME = 'file-name';
    public const OPTION_FILE_NAME_SHORT = 'f';

    public const OPTION_OFFSET = 'offset';
    public const OPTION_OFFSET_SHORT = 'o';

    public const OPTION_LIMIT = 'limit';
    public const OPTION_LIMIT_SHORT = 'l';

    public const OPTION_CSV_DELIMITER = 'delimiter';
    public const OPTION_CSV_DELIMITER_SHORT = 'd';

    public const OPTION_CSV_ENCLOSURE = 'enclosure';
    public const OPTION_CSV_ENCLOSURE_SHORT = 'e';

    public const OPTION_CSV_ESCAPE = 'escape';
    public const OPTION_CSV_ESCAPE_SHORT = 's';

    public const OPTION_CSV_HAS_HEADER = 'has-header';
    public const OPTION_CSV_HAS_HEADER_SHORT = 'r';

    public const OPTION_THROW_EXCEPTION = 'throw-exception';
    public const OPTION_THROW_EXCEPTION_SHORT = 't';
    public const ARGUMENT_IMPORTER = 'importer';

    public const OPTION_IMPORT_GROUP = 'group';
    public const OPTION_IMPORT_GROUP_SHORT = 'g';

    public const OPTION_CONFIG = 'config';
    public const OPTION_CONFIG_SHORT = 'c';

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument(static::ARGUMENT_IMPORTER, InputArgument::OPTIONAL, 'Defines which DataImport plugin should be executed. If not set, full import will be executed. Run data:import:dump to see all applied DataImporter.');

        $this->addOption(static::OPTION_THROW_EXCEPTION, static::OPTION_THROW_EXCEPTION_SHORT, InputOption::VALUE_OPTIONAL, 'Set this option to throw exceptions when they occur.');

        $this->addOption(static::OPTION_FILE_NAME, static::OPTION_FILE_NAME_SHORT, InputOption::VALUE_REQUIRED, 'Defines which file to use for data import.');
        $this->addOption(static::OPTION_OFFSET, static::OPTION_OFFSET_SHORT, InputOption::VALUE_REQUIRED, 'Defines from where a import should start.');
        $this->addOption(static::OPTION_LIMIT, static::OPTION_LIMIT_SHORT, InputOption::VALUE_REQUIRED, 'Defines where a import should end. If not set import runs until the end of data sets.');
        $this->addOption(static::OPTION_CSV_DELIMITER, static::OPTION_CSV_DELIMITER_SHORT, InputOption::VALUE_REQUIRED, 'Sets the csv delimiter.');
        $this->addOption(static::OPTION_CSV_ENCLOSURE, static::OPTION_CSV_ENCLOSURE_SHORT, InputOption::VALUE_REQUIRED, 'Sets the csv enclosure.');
        $this->addOption(static::OPTION_CSV_ESCAPE, static::OPTION_CSV_ESCAPE_SHORT, InputOption::VALUE_REQUIRED, 'Sets the csv escape.');
        $this->addOption(static::OPTION_CSV_HAS_HEADER, static::OPTION_CSV_HAS_HEADER_SHORT, InputOption::VALUE_REQUIRED, 'Set this option to 0 (zero) to disable that the first row of the csv file is a used as keys for the data sets.', true);
        $this->addOption(static::OPTION_IMPORT_GROUP, static::OPTION_IMPORT_GROUP_SHORT, InputOption::VALUE_REQUIRED, 'Defines the import group. Import group determines a specific subset of data importers to be used.', DataImportConfig::IMPORT_GROUP_FULL);
        $this->addOption(static::OPTION_CONFIG, static::OPTION_CONFIG_SHORT, InputOption::VALUE_REQUIRED, 'Defines the import configuration .yml file.');

        if ($this->isAddedAsNamedDataImportCommand()) {
            $importerType = $this->getImporterType();

            $this->setName(static::DEFAULT_NAME . ':' . $importerType);
            $this->setDescription(sprintf(static::IMPORTER_TYPE_DESCRIPTION, $importerType));

            return;
        }

        $this->setName(static::DEFAULT_NAME)
            ->setDescription(static::DEFAULT_DESCRIPTION);
    }

    /**
     * @return bool
     */
    protected function isAddedAsNamedDataImportCommand()
    {
        try {
            return $this->getName() !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->checkContradictoryInputParameters($input)) {
            $this->error('Config can not be used when an importer is specified');

            return static::CODE_ERROR;
        }

        if ($input->hasParameterOption('--' . static::OPTION_CONFIG)) {
            return $this->executeByConfig($input);
        }

        $dataImporterConfigurationTransfer = $this->buildDataImportConfiguration($input);

        if (!$this->checkImportTypeAndGroupConfiguration($dataImporterConfigurationTransfer)) {
            $this->error(
                sprintf('No import group (except "%s") can be used when an import type is specified', DataImportConfig::IMPORT_GROUP_FULL)
            );

            return static::CODE_ERROR;
        }

        $this->info(sprintf('<fg=white>Start "<fg=green>%s</>" import</>', $this->getImporterType($input)));

        $dataImporterReportTransfer = $this->executeImport($dataImporterConfigurationTransfer);

        $this->info('<fg=white;options=bold>Overall Import status: </>' . $this->getImportStatusByDataImportReportStatus($dataImporterReportTransfer));

        if ($dataImporterReportTransfer->getIsSuccess()) {
            return static::CODE_SUCCESS;
        }

        return static::CODE_ERROR;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return int
     */
    protected function executeByConfig(InputInterface $input): int
    {
        $dataImportConfigurationTransfers = $this->getFactory()
            ->createDataImportConfigurationYamlParser()
            ->parseConfigurationFile($input->getOption(static::OPTION_CONFIG));

        $this->info(sprintf('<fg=white>Start configured import</>'));

        $returnExitCode = static::CODE_SUCCESS;
        foreach ($dataImportConfigurationTransfers as $dataImportConfigurationTransfer) {
            $dataImporterConfigurationTransfer = $this->buildDataImportConfiguration($input);
            $dataImporterConfigurationTransfer = $this->mapDataImportConfigTransferToDataImporterConfigTransfer(
                $dataImportConfigurationTransfer,
                $dataImporterConfigurationTransfer
            );

            $dataImporterReportTransfer = $this->executeImport($dataImporterConfigurationTransfer);

            if (!$dataImporterReportTransfer->getIsSuccess()) {
                $returnExitCode = static::CODE_ERROR;
            }
        }

        $importStatus = $returnExitCode === static::CODE_SUCCESS ? $this->getImportStatusSuccess() : $this->getImportStatusFailed();
        $this->info('<fg=white;options=bold>Overall Import status: </>' . $importStatus);

        return $returnExitCode;
    }

    /**
     * @param \Generated\Shared\Transfer\DataImporterConfigurationTransfer $dataImporterConfigurationTransfer
     *
     * @return \Generated\Shared\Transfer\DataImporterReportTransfer
     */
    protected function executeImport(DataImporterConfigurationTransfer $dataImporterConfigurationTransfer): DataImporterReportTransfer
    {
        $dataImporterReportTransfer = $this->getFacade()->import($dataImporterConfigurationTransfer);

        /** @var \Generated\Shared\Transfer\DataImporterReportTransfer[]|null $dataImporterReports */
        $dataImporterReports = $dataImporterReportTransfer->getDataImporterReports();
        if ($dataImporterReports) {
            $this->printDataImporterReports($dataImporterReports);
        }

        $this->info('<fg=green>---------------------------------</>');

        return $dataImporterReportTransfer;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     *
     * @return string
     */
    protected function getImporterType(?InputInterface $input = null): string
    {
        if ($input && $input->getArgument(static::ARGUMENT_IMPORTER)) {
            return $input->getArgument(static::ARGUMENT_IMPORTER);
        }

        if ($this->getName() === static::DEFAULT_NAME) {
            return static::DEFAULT_IMPORTER_TYPE;
        }

        $commandNameParts = explode(':', $this->getName());
        $importerType = array_pop($commandNameParts);

        return $importerType;
    }

    /**
     * @param \Generated\Shared\Transfer\DataImporterReportTransfer $dataImportReportTransfer
     *
     * @return string
     */
    protected function getImportStatusByDataImportReportStatus(DataImporterReportTransfer $dataImportReportTransfer): string
    {
        if ($dataImportReportTransfer->getIsSuccess()) {
            return $this->getImportStatusSuccess();
        }

        return $this->getImportStatusFailed();
    }

    /**
     * @return string
     */
    protected function getImportStatusSuccess(): string
    {
        return '<fg=green>Successful</>';
    }

    /**
     * @return string
     */
    protected function getImportStatusFailed(): string
    {
        return '<fg=red>Failed</>';
    }

    /**
     * @param \Generated\Shared\Transfer\DataImporterReportTransfer[] $dataImporterReports
     *
     * @return void
     */
    private function printDataImporterReports($dataImporterReports)
    {
        foreach ($dataImporterReports as $dataImporterReport) {
            $this->printDataImporterReport($dataImporterReport);
        }
    }

    /**
     * @param \Generated\Shared\Transfer\DataImporterReportTransfer $dataImporterReport
     *
     * @return void
     */
    private function printDataImporterReport(DataImporterReportTransfer $dataImporterReport)
    {
        $messageTemplate = PHP_EOL . '<fg=white>'
            . 'Importer type: <fg=green>%s</>' . PHP_EOL
            . 'Importable DataSets: <fg=green>%s</>' . PHP_EOL
            . 'Imported DataSets: <fg=green>%s</>' . PHP_EOL
            . 'Import Time Used: <fg=green>%.2f ms</>' . PHP_EOL
            . 'Import status: %s</>';

        $this->info(sprintf(
            $messageTemplate,
            $dataImporterReport->getImportType(),
            $dataImporterReport->getExpectedImportableDataSetCount(),
            $dataImporterReport->getImportedDataSetCount(),
            $dataImporterReport->getImportTime(),
            $this->getImportStatusByDataImportReportStatus($dataImporterReport)
        ));
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Generated\Shared\Transfer\DataImporterConfigurationTransfer
     */
    protected function buildDataImportConfiguration(InputInterface $input)
    {
        $dataImporterConfigurationTransfer = new DataImporterConfigurationTransfer();
        $dataImporterConfigurationTransfer
            ->setImportType($this->getImporterType($input))
            ->setImportGroup($input->getOption(static::OPTION_IMPORT_GROUP))
            ->setThrowException(false);

        if ($input->hasParameterOption('--' . static::OPTION_THROW_EXCEPTION) || $input->hasParameterOption('-' . static::OPTION_THROW_EXCEPTION_SHORT)) {
            $dataImporterConfigurationTransfer->setThrowException(true);
        }

        if ($input->getArgument(static::ARGUMENT_IMPORTER) !== null || $input->getOption(static::OPTION_FILE_NAME)) {
            $dataImporterReaderConfiguration = $this->buildReaderConfiguration($input);
            $dataImporterConfigurationTransfer->setReaderConfiguration($dataImporterReaderConfiguration);
        }

        return $dataImporterConfigurationTransfer;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Generated\Shared\Transfer\DataImporterReaderConfigurationTransfer
     */
    protected function buildReaderConfiguration(InputInterface $input)
    {
        $dataImporterReaderConfiguration = new DataImporterReaderConfigurationTransfer();
        $dataImporterReaderConfiguration
            ->setFileName($input->getOption(static::OPTION_FILE_NAME))
            ->setOffset($input->getOption(static::OPTION_OFFSET))
            ->setLimit($input->getOption(static::OPTION_LIMIT))
            ->setCsvDelimiter($input->getOption(static::OPTION_CSV_DELIMITER))
            ->setCsvEnclosure($input->getOption(static::OPTION_CSV_ENCLOSURE))
            ->setCsvEscape($input->getOption(static::OPTION_CSV_ESCAPE))
            ->setCsvHasHeader($input->getOption(static::OPTION_CSV_HAS_HEADER));

        return $dataImporterReaderConfiguration;
    }

    /**
     * Checks that import type and import group are not used at the same time.
     *
     * @param \Generated\Shared\Transfer\DataImporterConfigurationTransfer $dataImporterConfigurationTransfer
     *
     * @return bool
     */
    protected function checkImportTypeAndGroupConfiguration(DataImporterConfigurationTransfer $dataImporterConfigurationTransfer): bool
    {
        return $dataImporterConfigurationTransfer->getImportType() === static::DEFAULT_IMPORTER_TYPE
            || $dataImporterConfigurationTransfer->getImportGroup() === DataImportConfig::IMPORT_GROUP_FULL;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return bool
     */
    protected function checkContradictoryInputParameters(InputInterface $input): bool
    {
        return $input->getArgument(static::ARGUMENT_IMPORTER) && $input->hasParameterOption('--' . static::OPTION_CONFIG);
    }

    /**
     * @param \Generated\Shared\Transfer\DataImportConfigurationTransfer $dataImportConfigurationTransfer
     * @param \Generated\Shared\Transfer\DataImporterConfigurationTransfer $dataImporterConfigurationTransfer
     *
     * @return \Generated\Shared\Transfer\DataImporterConfigurationTransfer
     */
    protected function mapDataImportConfigTransferToDataImporterConfigTransfer(
        DataImportConfigurationTransfer $dataImportConfigurationTransfer,
        DataImporterConfigurationTransfer $dataImporterConfigurationTransfer
    ): DataImporterConfigurationTransfer {
        $dataImporterReaderConfigurationTransfer = $dataImporterConfigurationTransfer->getReaderConfiguration() ?? new DataImporterReaderConfigurationTransfer();
        $dataImporterReaderConfigurationTransfer->setFileName($dataImportConfigurationTransfer->getSource());

        $dataImporterConfigurationTransfer->setImportType($dataImportConfigurationTransfer->getDataEntity());
        $dataImporterConfigurationTransfer->setReaderConfiguration($dataImporterReaderConfigurationTransfer);

        return $dataImporterConfigurationTransfer;
    }
}
