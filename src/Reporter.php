<?php
namespace JasperPHP;

class Reporter
{
    private $jasper;
    private $reportDirectory;
    private $connection = [];

    public function __construct($reportDir = "../../../../../reports/", $resourcesDir = false)
    {
        $this->reportDirectory = $reportDir;
        $this->jasper = new JasperPHP($resourcesDir);
    }

    public function connection(array $connection)
    {
        $this->connection = $connection;
    }

    private function compileReport($name)
    {
        $reportFile = $this->reportDirectory . "/" . $name;

        if (!file_exists($reportFile . ".jasper")) {
            $this->jasper->compile($reportFile . ".jrxml", $reportFile)->execute();
        }
    }

    /**
     * @return JasperPHP
     */
    public function getJasper()
    {
        return $this->jasper;
    }

    /**
     * @return string
     */
    public function getReportDirectory()
    {
        return $this->reportDirectory;
    }

    /**
     * Generate a report with the specified name and parameters
     *
     * @param array [string]|string $files
     * @param array $parameters
     * @param string $format
     *
     * @return $this
     * @throws \Exception
     */
    public function generate($files, array $parameters = [], $format = "pdf")
    {
        $compiledFiles = [];

        // Compile report files
        if (is_array($files)) {

            // When multiple files
            foreach ($files as $parent => $filename) {

                // When multiple files with dependencies
                if (is_array($filename)) {

                    /**
                     * Loop through report dependencies
                     *
                     * @var string[] $filename
                     */
                    foreach ($filename as $dependency) {
                        $compiledFiles[] = [$dependency, false];
                        $this->compileReport($dependency);
                    }

                    // Compile the parent report after the dependencies
                    $compiledFiles[] = [$parent, true];
                    $this->compileReport($parent);

                } else {
                    throw new \Exception("Multiple report generation isn't supported.");
                    // $compiledFiles[] = [$filename, true];
                    // $this->compileReport($filename);
                }
            }

        } else {
            // When only one file
            $compiledFiles[] = [$files, true];
            $this->compileReport($files);
        }

        // Check temporary directory
        // @codeCoverageIgnoreStart
        $cacheDirectory = $this->reportDirectory . "/tmp/";
        if (!file_exists($cacheDirectory) && !@mkdir($cacheDirectory, 0755) && !is_dir($cacheDirectory)) {
            throw new \Exception("Failed to create temporary folder.");
        }
        // @codeCoverageIgnoreEnd

        $hash = md5(base64_encode(random_bytes(64)));

        $tempFileName = $this->reportDirectory . "/tmp/" . $hash;
        $jasperCopy = $tempFileName . ".jasper";

        $mainReportFile = array_filter($compiledFiles, function ($value) {
            return $value[1] === true;
        });

        $reportFileName = end($mainReportFile);

        // Copy report file to prevent resource conflict
        $reportFile = $this->reportDirectory . "/" . $reportFileName[0];
        copy($reportFile . '.jasper', $jasperCopy);
        chmod($reportFile . '.jasper', 0775);

        $reportContent = null;

        try {
            // Generate jasper report
            $this->jasper->convert($jasperCopy, $tempFileName, [$format], $parameters, $this->connection)->execute();

            // Check generated report exists
            // @codeCoverageIgnoreStart
            if (!file_exists($tempFileName . "." . $format)) {
                throw new \Exception("Failed to generate the report with name '$reportFile'");
            }
            // @codeCoverageIgnoreEnd

            // Get report content
            $reportContent = file_get_contents($tempFileName . "." . $format);

        } catch (\Exception $ex) {

            if (strpos($ex->getMessage(), "expression for source text") !== false) {

                $params = $this->jasper->listParameters($jasperCopy)->execute();
                $paramCount = count($params);
                $required = "";

                $specifiedParamCount = 0;

                foreach ($params as $i => $param) {

                    preg_match_all("/([a-z]{0,2}) (.*) (.*)/i", $param, $matches);
                    $cleanNameMatch = trim($matches[2][0]);

                    if (array_key_exists($cleanNameMatch, $parameters)) {
                        $required .= "- {$cleanNameMatch} : {$matches[3][0]} ({$parameters[$cleanNameMatch]})";
                        $specifiedParamCount++;
                    } else {
                        $required .= "- {$cleanNameMatch} : {$matches[3][0]}";
                    }

                    if (($paramCount - 1) !== $i) {
                        $required .= "\n";
                    }
                }

                if ($specifiedParamCount !== $paramCount) {
                    throw new \Exception("Missing parameters:\n" . $required, 0, $ex);
                } else {
                    throw $ex;
                }
            }

            throw $ex;

        } finally {
            unlink($jasperCopy);
            if (file_exists($tempFileName . "." . $format)) {
                unlink($tempFileName . "." . $format);
            }
        }

        // Return file content
        return $reportContent;
    }

}