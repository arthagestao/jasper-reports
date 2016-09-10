<?php
namespace JasperPHP\Tests;

use JasperPHP\JasperPHP;

class JasperPHPTest extends \PHPUnit_Framework_TestCase
{
    protected $executable = '/../../bin/JasperStarter/bin/jasperstarter';

    public function tearDown()
    {
        $examplesPath = __DIR__ . "/../examples";
        array_map('unlink', glob("$examplesPath/*.jasper"));

        if (file_exists($examplesPath . '/tmp/')) {
            array_map('unlink', glob("$examplesPath/tmp/*"));
            rmdir($examplesPath . '/tmp/');
        }
    }

    public function testCreateInstance()
    {
        $obj = new JasperPHP;
        static::assertInstanceOf(JasperPHP::class, $obj);
    }

    public function testJava()
    {
        exec('which java', $output, $returnVar);

        static::assertSame($returnVar, 0);
    }

    public function testJasperStarter()
    {
        $executable = realpath(__DIR__ . $this->executable);

        static::assertNotFalse($executable, "JasperStarter executable not found.\n");

        exec($executable . ' -h', $output, $returnVar);

        static::assertSame($returnVar, 1, $output[0] ?: null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testCompileException()
    {
        (new JasperPHP)->compile('');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testProcessException()
    {
        (new JasperPHP)->convert('');
    }

    public function testInitializationWithResourceDirectory()
    {
        $resourceDirectory = __DIR__ . '/../../../../';
        $jasper = new JasperPHP($resourceDirectory);

        static::assertEquals($jasper->getResourceDirectory(), $resourceDirectory);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /Invalid resource/
     */
    public function testInitializationWithInvalidResourceDirectory()
    {
        new JasperPHP(__DIR__ . '/invalid');
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /Invalid format/
     */
    public function testInvalidConvertFormatArray()
    {
        $jasper = new JasperPHP;

        try {
            $jasper->compile(__DIR__ . '/../examples/main_report.jrxml',
                __DIR__ . "/../examples/main_report")->execute();
        } catch (\Exception $ex) {
            self::fail($ex);
        }

        $params = [
            'php_version' => phpversion()
        ];

        $targetfile = __DIR__ . "/../examples/" . md5(base64_encode(random_bytes(64)));

        $jasper->convert(__DIR__ . "/../examples/main_report.jasper", $targetfile, ['pdf', 'pdfs'], $params)->execute();
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /exec function is disabled/
     */
    public function testExecFunctionDisabled()
    {
        $jasperMock = $this->getMockBuilder(JasperPHP::class)
            ->setMethods(array('isFunctionAvaliable'))
            ->getMock();

        $jasperMock->method('isFunctionAvaliable')->willReturn(false);
        $jasperMock->compile(__DIR__ . '/../examples/main_report.jrxml',
            __DIR__ . "/../examples/main_report")->execute();
    }

    public function testConvertWithJsonDriverConnection()
    {
        $jasperMock = $this->getMockBuilder(JasperPHP::class)
            ->setMethods(array('validate'))
            ->getMock();

        $jasperMock->method('validate')->willReturn(true);

        /**
         * @var $jasperMock JasperPHP
         */
        $jasperMock->convert('mocked', false, ['pdf'], [], [
            'driver' => 'json',
            'data-file' => __DIR__ . '/../examples/test.json',
            'json-query' => 'path.to.query'
        ]);

        $this->assertRegExp('/-t json/', $jasperMock->getCommand());
        $this->assertRegExp('/--data-file/', $jasperMock->getCommand());
        $this->assertRegExp('/--json-query/', $jasperMock->getCommand());
    }

    public function testConvertWithDataFileAndGenericConnection()
    {
        $jasperMock = $this->getMockBuilder(JasperPHP::class)
            ->setMethods(array('validate'))
            ->getMock();

        $jasperMock->method('validate')->willReturn(true);

        /**
         * @var $jasperMock JasperPHP
         */
        $jasperMock->convert('mocked', false, ['pdf'], [], [
            'driver' => 'generic',
            'password' => 'ignored',
            'data-file' => __DIR__ . '/../examples/test.json',
            'jdbc_driver' => 'com.drive.example.ExampleDriver',
            'jdbc_url' => 'jdbc:example://127.0.0.1/test',
            'jdbc_dir' => __DIR__ . '/../../bin/JasperStarter/jdbc',
            'db_sid' => 'EXAMPLE'
        ]);

        $this->assertRegExp('/-t generic/', $jasperMock->getCommand());
        $this->assertRegExp('/--data-file/', $jasperMock->getCommand());
        $this->assertRegExp('/--db-driver/', $jasperMock->getCommand());
        $this->assertRegExp('/--jdbc-dir/', $jasperMock->getCommand());
        $this->assertRegExp('/--db-url jdbc:example/', $jasperMock->getCommand());
        $this->assertRegExp('/--db-sid/', $jasperMock->getCommand());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /executable not found/
     */
    public function testSetInexistentJasperBinary()
    {
        $jasper = new JasperPHP;
        $jasper->setBinary('./invalid');
    }

    /**
     * @requires OS Linux
     * @expectedException \Exception
     */
    public function testExecutableFilePermission()
    {
        $executablePath = realpath(__DIR__ . $this->executable);
        chmod ($executablePath, 0600);
        
        try {
            $jasper = new JasperPHP;
            $jasper->setBinary($executablePath);
        } 
        finally {
            chmod ($executablePath, 0775);
        }
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessageRegExp /Input file not found/
     */
    public function testInexistentInputFile()
    {
        $jasper = new JasperPHP;
        $jasper->compile(__DIR__ . '/../examples/inexistent.jrxml');
    }

    public function testGetEscapedCommand()
    {
        $jasper = new JasperPHP;
        $jasper->compile(__DIR__ . '/../examples/main_report.jrxml');

        static::assertRegExp("/ compile /", $jasper->getCommand());
        static::assertRegExp("/^^^/", $jasper->getCommand());
    }

    public function testCompileOutput()
    {
        $jasper = new JasperPHP;
        $hash = md5(base64_encode(random_bytes(64)));
        $jasperfile = __DIR__ . "/../examples/main_report.jasper";

        try {
            if (!file_exists($jasperfile)) {
                $jasper->compile(__DIR__ . '/../examples/main_report.jrxml',
                    __DIR__ . "/../examples/main_report")->execute();
            }
        } catch (\Exception $ex) {
            self::fail($ex);
        }

        $params = [
            'php_version' => phpversion()
        ];

        $connection = [
            'driver' => null,
            'host' => null,
            'username' => null,
            'password' => null,
            'database' => null,
            'port' => null
        ];

        $targetfile = __DIR__ . "/../examples/" . $hash;
        $jasper->convert($jasperfile, $targetfile, ['pdf'], $params, $connection)->execute();

        // $jasper->convert("./examples/hello_world.jasper")
        //   ->withOutput($targetfile)
        //   ->withFormat("pdf")
        //   ->withParameters($params)
        //   ->withConnection($connection)
        //   ->execute();

        self::assertFileExists($targetfile . ".pdf");

        unlink($targetfile . ".pdf");
        unlink($jasperfile);
    }
}