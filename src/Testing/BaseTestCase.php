<?php

namespace JsonSchema\Testing;

abstract class BaseTestCase extends \PHPUnit_Framework_TestCase
{
    private $exceptionClass;

    /**
     * Sets a default exception class for #expectException().
     *
     * @param string $class
     */
    protected function setExceptionClass($class)
    {
        $this->exceptionClass = $class;
    }

    /**
     * Asserts an exception of the class previously set will be thrown
     * with a given code.
     *
     * @param int $code
     */
    protected function expectException($code)
    {
        $this->setExpectedException($this->exceptionClass, null, $code);
    }

    /**
     * Returns the JSON-decoded content of a file.
     *
     * @param string $file
     * @return mixed
     * @throws \Exception
     */
    protected function loadJsonFromFile($file)
    {
        if (!file_exists($file)) {
            throw new \Exception("File {$file} doesn't exist");
        }

        $content = json_decode(file_get_contents($file));

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(sprintf(
                'json_decode error in file %s -> Error: %s',
                $file,
                json_last_error_msg()
            ));
        }

        return $content;
    }

    /**
     * Returns a JSON-decoded schema from tests/Data/schemas.
     *
     * @param string $name Name of the file without the extension
     * @return mixed
     */
    protected function loadSchema($name)
    {
        $schemaDir = realpath(__DIR__ . '/../../tests/Data/schemas');

        return $this->loadJsonFromFile("{$schemaDir}/{$name}.json");
    }

    /**
     * Asserts the validation results equal the expected one and make
     * a full report otherwise.
     *
     * @param string    $file
     * @param string    $title
     * @param mixed     $instance
     * @param \stdClass $schema
     * @param bool      $isInstanceValid
     * @param array     $expectedErrors
     * @param array     $actualErrors
     */
    protected function assertValidationResult(
        $file,
        $title,
        $instance,
        \stdClass $schema,
        $isInstanceValid,
        array $expectedErrors,
        array $actualErrors
    ) {
        $reportParameters = array(
            $file,
            $title,
            $this->dump($schema),
            $this->dump($instance),
            count($expectedErrors) > 0 ? $this->dump($expectedErrors) : 'no error',
            count($actualErrors) > 0 ? $this->dump($actualErrors) : 'no error'
        );

        if (!$isInstanceValid && count($expectedErrors) === 0) {
            $reportParameters[4] = 'at least one error';
            $this->assertHasError($actualErrors, $reportParameters);
        } else {
            $this->assertErrorsAreEqual($expectedErrors, $actualErrors, $reportParameters);
        }
    }

    /**
     * Extracts the tests from a JSON case file.
     *
     * @param string $caseFile
     * @return array
     * @throws \Exception
     */
    protected function collectTests($caseFile)
    {
        $case = $this->loadJsonFromFile($caseFile);
        $tests = [];

        foreach ($case->tests as $test) {
            if (!isset($test->valid) && !isset($test->invalid)) {
                throw new \Exception(sprintf(
                    'Test case "%s %s" has neither "valid" or "invalid" data (file: %s)',
                    $case->title,
                    $test->title,
                    $caseFile
                ));
            }

            if (isset($test->valid)) {
                foreach ($test->valid as $i => $instance) {
                    $tests[] = [
                        $caseFile,
                        "{$case->title} {$test->title}, valid instance #{$i}",
                        $instance,
                        $test->schema,
                        true,
                        []
                    ];
                }
            }

            if (isset($test->invalid)) {
                foreach ($test->invalid as $i => $set) {
                    $tests[] = [
                        $caseFile,
                        "{$case->title} {$test->title}, invalid instance #{$i}",
                        $set->instance,
                        $test->schema,
                        false,
                        array_map(function ($violation) {
                            return (array)$violation;
                        }, $set->violations)
                    ];
                }
            }
        }

        return $tests;
    }

    private function dump($variable)
    {
        if (defined('JSON_PRETTY_PRINT')) {
            $options = JSON_PRETTY_PRINT;

            if (defined('JSON_PRESERVE_ZERO_FRACTION')) {
                $options |= JSON_PRESERVE_ZERO_FRACTION;
            }

            return json_encode($variable, $options);
        }

        return print_r($variable, true);
    }

    private function assertHasError(array $errors, array $reportParameters)
    {
        if (count($errors) === 0) {
            $this->fail(vsprintf($this->getFailureReportMask(), $reportParameters));
        }
    }

    private function assertErrorsAreEqual(array $actual, array $expected, array $reportParameters)
    {
        $report = vsprintf($this->getFailureReportMask(), $reportParameters);

        if (count($actual) !== count($expected)) {
            $this->fail($report);
        }

        foreach ($expected as $error) {
            if (!in_array($error, $actual)) {
                $this->fail($report);
            }
        }
    }

    private function getFailureReportMask()
    {
        return <<<MSG
**********************************************************************

File: %s

Test: %s

Schema: %s

Instance: %s

Expected: %s

Actual: %s

**********************************************************************
MSG;
    }
}
