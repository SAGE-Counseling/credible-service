<?php
/*
 *
 *  * Copyright © ${YEAR} SAGE Counseling, Inc.
 *  * All rights reserved.
 *  *
 *  * This file is part SAGE Counseling, Inc. internal software systems.
 *  * Unauthorized use, reproduction, or distribution is strictly prohibited.
 *
 *
 */

namespace Sage\Credible\Services;

use Sage\Credible\Traits\ApiRetryWithBackoff;
use DOMDocument;
use Exception;
use Generator;
use Http;
use Log;
use SoapClient;
use Storage;
use Str;
use XMLReader;

/**
 *
 */
class CredibleService
{

    use ApiRetryWithBackoff;

    private $soapClient;
    private string $connection;
    private $connectionName;
    private string $baseUrl = 'https://reportservices.crediblebh.com';


    /**
     * CredibleService constructor.
     *
     * @param string $connection
     * @param SoapClient|null $soapClient
     * @throws Exception
     */
    public function __construct(string $connection, $soapClient = null)
    {
        if (empty($connection)) {
            throw new Exception('Connection string cannot be empty.');
        }

        if (is_string(config("credible.cross_reference.$connection"))) {
            $this->connectionName = config('credible.cross_reference.' . $connection . '.name');
        } else {
            $this->connectionName = config("credible.cross_reference.$connection") ?? substr($connection, 0, 10);
        }

        $this->connection = $connection;
        $this->soapClient = $soapClient;
        $this->initializeApiRetryWithBackoff();
    }

    /**
     * Fetches data from CredibleBH.com.
     *
     * @param string $param1
     * @param string $param2
     * @param string $param3
     * @param string|null $start_date
     * @param string|null $end_date
     * @param bool $returnArray
     * @return array
     * @throws \SoapFault
     */
    public function get(
        string $param1 = '',
        string $param2 = '',
        string $param3 = '',
        string $start_date = null,
        string $end_date = null,
        bool   $returnArray = true
    ): array
    {
        if ($delay = $this->checkAndApplyBackoff()) {
            Log::info("Applied API backoff delay of {$delay}s before calling Credible");
        }

        $start_date ??= '2020-01-01';
        $end_date ??= date('Y-m-d');

        $params = $this->prepareSoapParams($param1, $param2, $param3, $start_date, $end_date);

        try {
            $this->soapClient = $this->soapClient ?: $this->getSoapClient();
            $soapResponse = $this->callSoap('ExportDataSet', $params);

            $xmlString = $this->prepareXmlString(
                $soapResponse->ExportDataSetResult
            );

            $rows = $this->processXml($xmlString, true);

            $this->clearBackoff();

            return array_map(
                fn(array $row): array => array_map(
                    fn($v) => is_numeric($v) ? (int)$v : $v,
                    $row
                ),
                $rows
            );

        } catch (\SoapFault $fault) {
            log_exception($fault, 'An error occurred retrieving from Credible ' . $this->connectionName, __FUNCTION__, $this->connection);

            if (isset($fault->faultcode) && str_starts_with($fault->faultcode, 'HTTP') && strpos($fault->getMessage(), '500') !== false) {
                Log::error('500 Error - backoff triggered');
                $delay = $this->escalateBackoff();

                if (method_exists($this, 'release')) {
                    $this->release($delay);
                    return [];
                }
            }

            throw $fault;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            if (stripos($msg, 'Array to string conversion') !== false) {
                dump(
                    [
                        'param1' => $param1,
                        'param2' => $param2,
                    ]
                );
                dump($msg);
                if (isset($xmlString)) {
                    dump($xmlString);
                }
                if (isset($rows)) {
                    dump($rows);
                }


                // 3) if you really need the full $params for one‐off debugging, dump it to its own file:
                $filename = storage_path("logs/credible-params-{$param1}-{$param2}.log");
                file_put_contents($filename, print_r($params, true));
                if (isset($xmlString)) {
                    file_put_contents($filename, print_r($xmlString, true));
                }
                if (isset($rows)) {
                    file_put_contents($filename, print_r($rows, true));
                }

            }

            // 4) rethrow so the Fetcher still treats it as a permanent failure
            throw $e;
        }
    }


    private function getSoapClient(): SoapClient
    {
        $wsdlPath = __DIR__ . '/CredibleWsdl/ExportService.wsdl';
        if (!is_readable($wsdlPath)) {
            throw new \RuntimeException("WSDL file not found or unreadable at {$wsdlPath}");
        }

        $attempts = 0;
        $maxAttempts = 4;
        $options =
            [
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ];

        do {
            try {
                return new SoapClient($wsdlPath, $options);
            } catch (\SoapFault $e) {
                $attempts++;
                if (
                    str_contains($e->getMessage(), 'Parsing WSDL') ||
                    str_contains($e->getMessage(), 'failed to load external entity')
                ) {
                    $delay = rand(3, 6) * $attempts;
                    Log::warning("WSDL parse failed — backing off {$delay}s", ['attempt' => $attempts]);
                    sleep($delay);
                } else {
                    throw $e;
                }
            }
        } while ($attempts < $maxAttempts);

        throw new \RuntimeException("Failed to instantiate SoapClient after {$maxAttempts} attempts.");
    }


    /**
     * Prepares parameters for the SOAP call.
     */
    private function prepareSoapParams(
        string $param1,
        string $param2,
        string $param3,
        string $start_date,
        string $end_date
    ): array
    {
        return [
            'connection' => $this->connection,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'custom_param1' => $param1,
            'custom_param2' => $param2,
            'custom_param3' => $param3,
        ];
    }

    /**
     * Handles the SOAP call to the service.
     */
    private function callSoap(string $action, array $params)
    {
        return $this->soapClient->$action($params);
    }

    /**
     * Prepares the XML string from the SOAP response.
     */
    private function prepareXmlString($soapResult): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<root>'
            . $soapResult->schema
            . $soapResult->any
            . '</root>';
    }

    /**
     * Saves the XML string to a temporary file and parses it.
     */
    private function processXml(string $xmlString, bool $returnArray): array
    {
        // Save XML string to a temporary file
        $path = 'temp/' . Str::random(10) . '.xml';
        Storage::put($path, $xmlString);

        // Parse the XML file
        $absolutePath = Storage::path($path);
        $parsedData = $this->parseXmlFile($absolutePath, $returnArray);

        // Clean up the temporary file
        Storage::delete($path);

        return $parsedData;
    }

    /**
     * Parses the XML file using XMLReader and extracts data.
     */
    private function parseXmlFile(string $filePath, bool $returnArray): array
    {
        $reader = new XMLReader();
        $reader->open($filePath);

        $tables = [];
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == 'Table') {
                $doc = new DOMDocument();
                $xml = simplexml_import_dom($doc->importNode($reader->expand(), true));

                if ($returnArray) {
                    $tables[] = json_decode(json_encode($xml), true);
                } else {
                    $tables[] = $xml;
                }
            }
        }

        $reader->close();
        return $tables;
    }


    /**
     * Uploads a file to CredibleBH.com.
     *
     * @param string $fileContent The content of the file to upload (base64 encoded)
     * @param string|null $baseUrl
     * @return string|null The response from the service or null on failure
     */
    public function post(string $fileContent, string $baseUrl = null): ?string
    {
        $this->baseUrl = $baseUrl ?: $this->baseUrl;
        $url = $this->baseUrl . '/reports/Importservice.asmx/Import';

        $payload = [
            'connection' => $this->connection,
            'encodedfile' => $fileContent,
        ];

        try {
            $response = Http::post($url, $payload);
            $this->clearBackoff();
            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return null;
    }


    /**
     * Fetches data from CredibleBH.com and yields rows one by one for memory efficiency.
     * Handles nested and HTML-encoded XML structure.
     *
     * @param string $param1
     * @param string $param2
     * @param string $param3
     * @param string|null $start_date
     * @param string|null $end_date
     * @return Generator
     */
    public function yieldRows(string $param1 = '', string $param2 = '', string $param3 = '', ?string $start_date = null, ?string $end_date = null): Generator
    {
        $this->soapClient = $this->soapClient ?: $this->getSoapClient();

        $start_date ??= '2020-01-01';
        $end_date ??= date('Y-m-d');
        $params = $this->prepareSoapParams($param1, $param2, $param3, $start_date, $end_date);

        $reader = null;
        $path = null;

        try {
            $soapResponse = $this->callSoap('ExportDataSet', $params);

            // 1. Get the string content from the SOAP response object
            // Adjust 'ExportDataSetResult' if the actual property name differs
            $encodedXmlString = $soapResponse->ExportDataSetResult->any ?? '';
            if (empty($encodedXmlString)) {
                Log::warning('SOAP response did not contain expected ExportDataSetResult string.', ['param1' => $params['custom_param1']]);
                // Let execution continue to finally block
            } else {

                // 2. Decode HTML entities to get the actual XML
                $decodedXmlString = html_entity_decode($encodedXmlString);
                if (empty($decodedXmlString) || strpos($decodedXmlString, '<') === false) {
                    Log::error('Failed to decode or decoded XML string is empty/invalid.', ['param1' => $params['custom_param1']]);
                    // Let execution continue to finally block
                } else {
                    // --- DEBUG: Log snippet of decoded XML ---
                    // Log::debug('Decoded XML Snippet', ['param1' => $params['custom_param1'], 'snippet' => substr($decodedXmlString, 0, 500)]);

                    // 3. Use XMLReader on the decoded string (via temp file for memory)
                    $path = 'temp/credible_stream_' . Str::random(40) . '.xml';
                    Storage::put($path, $decodedXmlString);
                    $absolutePath = Storage::path($path);

                    $reader = new XMLReader();
                    if (!$reader->open($absolutePath)) {
                        Log::error('Failed to open temporary XML file for reading decoded XML.', ['path' => $absolutePath, 'param1' => $params['custom_param1']]);
                        // Let execution continue to finally block
                    } else {
                        $dataSetFound = false;
                        $rowsYieldedCount = 0;

                        // 4. Navigate to NewDataSet first
                        while ($reader->read() && $reader->localName !== 'NewDataSet') {
                            ;
                        }

                        if ($reader->localName === 'NewDataSet' && $reader->nodeType == XMLReader::ELEMENT) {
                            $dataSetFound = true;
                            // Log::debug('Found <NewDataSet> element.', ['param1' => $params['custom_param1']]);
                        } else {
                            Log::warning('Could not find <NewDataSet> element in decoded XML.', ['param1' => $params['custom_param1']]);
                        }

                        // 5. Now, look for Table elements *within* NewDataSet
                        if ($dataSetFound) {
                            while ($reader->read()) {
                                // Stop if we've exited NewDataSet
                                if ($reader->nodeType == XMLReader::END_ELEMENT && $reader->localName == 'NewDataSet') {
                                    break;
                                }

                                // Process only the Table elements (which act as rows here)
                                if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == 'Table') {
                                    // --- Wrap the processing of this single row in a try...catch ---
                                    try {
                                        // Log::debug('Found <Table> element (acting as row).', ['param1' => $params['custom_param1']]);
                                        $doc = new DOMDocument();
                                        // --- Add error suppression (@) temporarily if needed, but prefer fixing data ---
                                        // $node = @$reader->expand($doc);
                                        $node = $reader->expand($doc); // Expand the whole <Table> node

                                        if ($node) {
                                            $sxmlNode = simplexml_import_dom($node);
                                            if ($sxmlNode !== false) {
                                                // Convert the SimpleXML object for this <Table> to an array
                                                $rowData = json_decode(json_encode($sxmlNode), true);
                                                // --- Add a check for essential data if possible ---
                                                if (!empty($rowData) /* && isset($rowData['clientvisit_id']) */) {
                                                    yield $rowData; // Yield the row data
                                                    $rowsYieldedCount++;
                                                } else {
                                                    Log::warning('Skipping row due to empty data after conversion.', ['param1' => $params['custom_param1']]);
                                                }
                                            } else {
                                                Log::warning('Failed to convert <Table> DOM node to SimpleXML. Skipping row.', ['param1' => $params['custom_param1']]);
                                            }
                                        } else {
                                            // This case might be redundant if expand() throws an exception on failure
                                            Log::warning('XMLReader::expand returned null/false for a <Table> node. Skipping row.', ['param1' => $params['custom_param1']]);
                                        }
                                        // --- Catch exceptions specifically related to this row ---
                                    } catch (\Exception $e) {
                                        // Log the error specifically for this skipped row
                                        Log::error('Error processing a <Table> node, skipping row.', [
                                            'param1' => $params['custom_param1'],
                                            'error_message' => $e->getMessage(),
                                            // Avoid logging full trace here unless needed, it's verbose
                                            // 'trace' => $e->getTraceAsString()
                                        ]);
                                        // --- Crucially, continue to the next iteration of the while loop ---
                                        continue;
                                    }
                                    // --- End of try...catch for single row ---
                                }
                            }
                        }
                        Log::debug("Finished processing XML, yielded $rowsYieldedCount rows.", ['param1' => $params['custom_param1']]);
                    } // End reader opened successfully
                } // End decoded XML valid
            } // End SOAP result not empty
        } catch (\SoapFault $fault) {
            Log::error("SOAP Fault in CredibleService::yieldRows: " . $fault->getMessage(), ['param1' => $params['custom_param1']]);
        } catch (\Exception $e) {
            Log::error("Exception in CredibleService::yieldRows: " . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'param1' => $params['custom_param1']]);
        } finally {
            if ($reader instanceof XMLReader) {
                $reader->close();
            }
            if ($path && Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }

}
