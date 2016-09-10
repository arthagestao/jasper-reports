<?php
namespace JasperPHP;

class JasperPHP
{
    protected $executable = __DIR__ . '/../bin/JasperStarter/bin/jasperstarter';
    protected $command;
    protected $redirectOutput;
    protected $runInBackground;
    protected $windows = false;

    /**
     * Path to report resource dir or jar file
     * @var string
     */
    protected $resource_directory;

    /**
     * Valid report formats
     * @var array
     */
    protected $formats = [
        'pdf',
        'rtf',
        'xls',
        'xlsx',
        'docx',
        'odt',
        'ods',
        'pptx',
        'csv',
        'html',
        'xhtml',
        'xml',
        'jrprint'
    ];

    public function __construct($resource_dir = false)
    {
        if (stripos(PHP_OS, 'WIN') === 0) {
            $this->windows = true;
        }

        if (!$resource_dir) {
            $this->resource_directory = __DIR__ . '/../../../../';
        } else {
            if (!file_exists($resource_dir)) {
                throw new \Exception('Invalid resource directory', 1);
            }
            $this->resource_directory = $resource_dir;
        }

        $this->setBinary($this->executable);
    }

    public function compile($input_file, $output_file = false, $background = false, $redirect_output = true)
    {
        $input_file = $this->validate($input_file);

        $command = "{$this->executable} compile {$input_file}";

        if ($output_file !== false) {
            $command .= ' -o ' . $output_file;
        }

        $this->redirectOutput = $redirect_output;
        $this->runInBackground = $background;

        $this->command = escapeshellcmd($command);

        return $this;
    }
    /**
     * Get resource_directory
     *
     * @return string
     */
    public function getResourceDirectory()
    {
        return $this->resource_directory;
    }

    public function convert(
        $input_file,
        $output_file = false,
        array $format = array('pdf'),
        array $parameters = array(),
        array $db_connection = array(),
        $background = false,
        $redirect_output = true
    ) {
        $input_file = $this->validate($input_file);

        foreach ($format as $key) {
            if (!in_array($key, $this->formats, false)) {
                throw new \Exception('Invalid format!', 1);
            }
        }

        $command = "{$this->executable} process $input_file";

        if ($output_file !== false) {
            $command .= ' -o ' . $output_file;
        }

        $command .= ' -f ' . implode(' ', $format);

        // Resources dir
        $command .= ' -r ' . $this->resource_directory;

        if (count($parameters) > 0) {
            $command .= ' -P';
            foreach ($parameters as $key => $value) {
                $command .= ' ' . $key . '=' . $value;
            }
        }

        if (!empty($db_connection['driver']) && count($db_connection) > 0) {

            $command .= ' -t ' . $db_connection['driver'];

            if ($db_connection['driver'] === 'json') {
                if (!empty($db_connection['data-file'])) {
                    $command .= ' --data-file ' . $db_connection['data-file'];
                }

                if (!empty($db_connection['json-query'])) {
                    $command .= ' --json-query ' . $db_connection['json-query'];
                }
            } else {
                if (!empty($db_connection['data-file'])) {
                    $command .= ' --data-file ' . $db_connection['data-file'];
                }
            }

            if (!empty($db_connection['username'])) {
                $command .= ' -u ' . $db_connection['username'];
            }

            if (!empty($db_connection['password'])) {
                $command .= ' -p ' . $db_connection['password'];
            }

            if (!empty($db_connection['host'])) {
                $command .= ' -H ' . $db_connection['host'];
            }

            if (!empty($db_connection['database'])) {
                $command .= ' -n ' . $db_connection['database'];
            }

            if (!empty($db_connection['port'])) {
                $command .= ' --db-port ' . $db_connection['port'];
            }

            if (!empty($db_connection['jdbc_driver'])) {
                $command .= ' --db-driver ' . $db_connection['jdbc_driver'];
            }

            if (!empty($db_connection['jdbc_url'])) {
                $command .= ' --db-url ' . $db_connection['jdbc_url'];
            }

            if (!empty($db_connection['jdbc_dir'])) {
                $command .= ' --jdbc-dir ' . $db_connection['jdbc_dir'];
            }

            if (!empty($db_connection['db_sid'])) {
                $command .= ' --db-sid ' . $db_connection['db_sid'];
            }
        }

        $this->redirectOutput = $redirect_output;
        $this->runInBackground = $background;
        $this->command = escapeshellcmd($command);

        return $this;
    }

    public function listParameters($input_file)
    {
        $input_file = $this->validate($input_file);

        $command = "{$this->executable} list_parameters {$input_file}";
        $this->command = escapeshellcmd($command);

        return $this;
    }

    public function execute($run_as_user = false)
    {
        if (!$this->isFunctionAvaliable('exec')) {
            throw new \Exception('The exec function is disabled.', 1);
        }

        if ($this->redirectOutput) {
            $this->command .= ' 2>&1';
        }

        // @codeCoverageIgnoreStart
        if (!$this->windows && $this->runInBackground) {
            $this->command .= ' &';
        }

        if (!$this->windows && !empty($run_as_user)) {
            $this->command = 'su -u ' . $run_as_user . " -c \"" . $this->command . "\"";
        }
        // @codeCoverageIgnoreEnd

        $output = array();
        $return_var = 0;

        exec($this->command, $output, $return_var);

        if ($return_var !== 0) {
            $message = "Your report has an error and couldn't be processed! Try to output the command using the function `output();` and run it manually in the console.";
            if ($output[0] !== null) {
                $message = "[JasperStarter] {$output[0]}";
            }
            throw new \Exception($message, 1);
        }

        return $output;
    }

    public function validate($inputFile)
    {
        if (null === $inputFile || is_string($inputFile) === false || $inputFile === '') {
            throw new \InvalidArgumentException('No input file', 1);
        } elseif (!file_exists($inputFile)) {
            throw new \InvalidArgumentException("Input file not found ({$inputFile}).");
        }
        return realpath($inputFile);
    }

    public function setBinary($value = null)
    {
        if ($value !== null) {
            if (file_exists($value) === false) {
                throw new \Exception("JasperStarter executable not found ({$value}).");
            } // @codeCoverageIgnoreStart
            elseif (!$this->windows && is_executable($value) === false) {
                throw new \Exception("Missing execution permission for file: {$value}.");
            }
            //@codeCoverageIgnoreEnd
            $this->executable = realpath($value);
        }

        return $this->executable;
    }

    public function getCommand()
    {
        return escapeshellcmd($this->command);
    }

    public function isFunctionAvaliable($name)
    {
        return function_exists($name);
    }
}
