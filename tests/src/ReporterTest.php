<?php
namespace JasperPHP\Tests;

use JasperPHP\Reporter;

class ReporterTest extends \PHPUnit_Framework_TestCase
{
    /** @var Reporter */
    private $reporter;

    public function tearDown()
    {
        $examplesPath = __DIR__ . "/../examples";
        array_map('unlink', glob("$examplesPath/*.jasper"));

        if (file_exists($examplesPath . '/tmp/')) {
            array_map('unlink', glob("$examplesPath/tmp/*"));
            rmdir($examplesPath . '/tmp/');
        }
    }

    protected function setUp()
    {
        $this->reporter = new Reporter(__DIR__ . "/../examples");
    }

    public function testGenerationWithSingleFile()
    {
        $report = $this->reporter->generate("main_report", ["php_version" => phpversion()]);

        static::assertNotNull($report);
        // $base64 = "data:application/pdf;base64," . base64_encode($report);
        // exec("start chrome.exe $base64");
    }

    /**
     * @expectedException \Exception
     */
    public function testGenerationWithSingleFileMissingOneParameter()
    {
        $report = $this->reporter->generate("main_report", []);

        static::assertNotNull($report);
        // $base64 = "data:application/pdf;base64," . base64_encode($report);
        // exec("start chrome.exe $base64");
    }

    /**
     * @expectedException \Exception
     * // TODO: expectedExceptionMessageRegExp
     */
    public function testGenerationWithOneParameterSpecifiedAndOneMissing()
    {
        $report = $this->reporter->generate("multiple_parameters", ['php_version' => phpversion()]);
        static::assertNotNull($report);
    }

    /**
     * @expectedException \Exception
     */
    public function testGenerationWithGenericException()
    {
        $report = $this->reporter->generate("generic_exception", ['php_version' => phpversion()]);
        static::assertNotNull($report);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /link failure|Unknown database/
     */
    public function testGenerationWithInvalidDatasourceConnection()
    {
        $this->reporter->connection([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'database' => 'test'
        ]);

        $report = $this->reporter->generate("ds_connetion", []);
        static::assertNotNull($report);
    }

    public function testGenerationWithDatasourceConnection()
    {
        $this->reporter->connection([
            'port' => 3306,
            'driver' => 'mysql',
            'host' => 'mysql',
            'username' => 'root',
            'password' => 'default',
            'database' => 'mysql'
        ]);

        $report = $this->reporter->generate("ds_connetion", []);

        //$base64 = "data:application/pdf;base64," . base64_encode($report);
        //exec("start chrome.exe $base64");

        static::assertNotNull($report);
    }

    public function testGenerationWithDependencies()
    {
        $reportFiles = ['main_report' => ['child_one', 'child_two']];
        $report = $this->reporter->generate($reportFiles, ["php_version" => phpversion()]);

        static::assertNotNull($report);
    }

    /**
     * @expectedException \Exception
     */
    public function testGenerationWithMultipleFilesMustThrowError()
    {
        $reportFiles = ['main_report', 'child_one', 'child_two'];
        $this->reporter->generate($reportFiles, ["php_version" => phpversion()]);
    }

}